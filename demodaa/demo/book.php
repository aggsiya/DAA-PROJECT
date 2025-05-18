 <?php
session_start();
// Encryption / Decryption functions
function encryptData($data, $key = "mysecretkey12345") {
    $key = substr(hash('sha256', $key, true), 0, 16);
    $encrypted = openssl_encrypt($data, "AES-128-ECB", $key, OPENSSL_RAW_DATA);
    return base64_encode($encrypted);
}
function decryptData($data, $key = "mysecretkey12345") {
    $key = substr(hash('sha256', $key, true), 0, 16);
    $decoded = base64_decode($data);
    return openssl_decrypt($decoded, "AES-128-ECB", $key, OPENSSL_RAW_DATA);
}
// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "register";
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Get username from session
$uname = $_SESSION['uname'] ?? null;
if (!$uname) {
    die("User not logged in. Please register or login first.");
}
// Get data from POST safely
$flight = $_POST['flights'][0] ?? null;
$flightName = $flight['flightName'] ?? null;
$date = $flight['date'] ?? null;        // <-- date expected here now
$source = $flight['source'] ?? null;
$destination = $flight['destination'] ?? null;
$depaTime = $flight['depaTime'] ?? null;
$arrivalTime = $flight['arrivalTime'] ?? null;
$price = $flight['price'] ?? null;
// Validate required fields
if (!$uname || !$flightName || !$date || !$source || !$destination || !$depaTime || !$arrivalTime || !$price) {
    die("Missing required booking information.");
}
// Max seats per flight
$maxSeats = 100;
// Step 1: Get all booked seats for that flight & date
$sql = "SELECT seatNo FROM booking WHERE flightName = ? AND `date` = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $flightName, $date);
$stmt->execute();
$result = $stmt->get_result();

$bookedSeats = [];
while ($row = $result->fetch_assoc()) {
    $bookedSeats[] = (int)$row['seatNo'];
}
$stmt->close();

// Step 2: Allocate next available seat
$seatNo = null;
for ($i = 1; $i <= $maxSeats; $i++) {
    if (!in_array($i, $bookedSeats)) {
        $seatNo = $i;
        break;
    }
}

if ($seatNo === null) {
    die("Sorry, no seats available.");
}

// Encrypt username before storing
$encryptedUname = encryptData($uname);

// Step 3: Insert booking into database
$sqlInsert = "INSERT INTO booking (uname, flightName, seatNo, `date`, source, destination, depaTime, arrivalTime, price)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmtInsert = $conn->prepare($sqlInsert);
$stmtInsert->bind_param("ssisssssd", $encryptedUname, $flightName, $seatNo, $date, $source, $destination, $depaTime, $arrivalTime, $price);

if ($stmtInsert->execute()) {
    echo "<h3>Booking Successful!</h3>";
    echo "<p><strong>Username:</strong> " . htmlspecialchars($uname) . "</p>";
    echo "<p><strong>Flight:</strong> " . htmlspecialchars($flightName) . "</p>";
    echo "<p><strong>Date:</strong> " . htmlspecialchars($date) . "</p>";
    echo "<p><strong>From:</strong> " . htmlspecialchars($source) . " <strong>To:</strong> " . htmlspecialchars($destination) . "</p>";
    echo "<p><strong>Departure:</strong> " . htmlspecialchars($depaTime) . " <strong>Arrival:</strong> " . htmlspecialchars($arrivalTime) . "</p>";
    echo "<p><strong>Seat Number:</strong> " . $seatNo . "</p>";
    echo "<p><strong>Price:</strong> ₹" . htmlspecialchars($price) . "</p>";
} else {
    echo "Error in booking: " . $stmtInsert->error;
}

$stmtInsert->close();
$conn->close();
?>
