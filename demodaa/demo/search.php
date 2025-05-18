 <?php
session_start(); // required to access session variables

function timeToMinutes($time) {
    list($h, $m, $s) = explode(':', $time);
    return $h * 60 + $m;
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "register";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$source = $_POST['source'];
$destination = $_POST['destination'];
$date = $_POST['date'];
$uname = $_SESSION['uname'] ?? ''; // Get uname from session if logged in

echo "<h2>Search Results for $source → $destination on $date</h2>";

// Step 1: Fetch all flights for that date
$sql = "SELECT * FROM flights WHERE date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$graph = [];
$allFlights = [];
while ($row = $result->fetch_assoc()) {
    $from = $row['source'];
    $to = $row['destination'];
    $depTime = timeToMinutes($row['depaTime']);
    $arrTime = timeToMinutes($row['arrivalTime']);
    $duration = $arrTime - $depTime;
    if ($duration < 0) $duration += 1440;

    $flight = [
        'flightName' => $row['flightName'],
        'source' => $from,
        'destination' => $to,
        'depaTime' => $row['depaTime'],
        'arrivalTime' => $row['arrivalTime'],
        'duration' => $duration,
        'price' => $row['price'],
         'date' => $row['date']
    ];

    $graph[$from][] = [
        'to' => $to,
        'duration' => $duration,
        'flight' => $flight
    ];

    $allFlights[] = $flight;
}

// Step 2: Dijkstra's
$dist = [];
$prev = [];
$visited = [];
foreach ($graph as $node => $_) {
    $dist[$node] = INF;
    $prev[$node] = null;
}
$dist[$source] = 0;
$queue = [[$source, 0]];

while (!empty($queue)) {
    usort($queue, fn($a, $b) => $a[1] <=> $b[1]);
    [$curr, $currDist] = array_shift($queue);
    if (isset($visited[$curr])) continue;
    $visited[$curr] = true;

    foreach ($graph[$curr] ?? [] as $neighbor) {
        $alt = $currDist + $neighbor['duration'];
        if ($alt < ($dist[$neighbor['to']] ?? INF)) {
            $dist[$neighbor['to']] = $alt;
            $prev[$neighbor['to']] = [
                'from' => $curr,
                'flight' => $neighbor['flight']
            ];
            $queue[] = [$neighbor['to'], $alt];
        }
    }
}

// Step 3: Shortest path
$shortestPath = [];
$node = $destination;
while (isset($prev[$node]) && $prev[$node] !== null) {
    array_unshift($shortestPath, $prev[$node]['flight']);
    $node = $prev[$node]['from'];
}

// Step 4: Show best path
if (!empty($shortestPath)) {
    echo "<h3>Best Route (Shortest Time):</h3>";
    echo "<form method='POST' action='book.php'>";
    echo "<table border='1' cellpadding='8'>
        <tr>
            <th>Flight Name</th>
            <th>From</th>
            <th>To</th>
            <th>Departure</th>
            <th>Arrival</th>
            <th>Duration</th>
            <th>Price</th>
        </tr>";

    $totalPrice = 0;
    foreach ($shortestPath as $i => $flight) {
        $totalPrice += $flight['price'];
        echo "<tr>
            <td>{$flight['flightName']}</td>
            <td>{$flight['source']}</td>
            <td>{$flight['destination']}</td>
            <td>{$flight['depaTime']}</td>
            <td>{$flight['arrivalTime']}</td>
            <td>{$flight['duration']} mins</td>
            <td>₹{$flight['price']}</td>
        </tr>";
        foreach ($flight as $key => $value) {
            echo "<input type='hidden' name='flights[$i][$key]' value='" . htmlspecialchars($value) . "'>";
        }
    }
    echo "</table><br>";
    echo "<input type='hidden' name='totalPrice' value='$totalPrice'>";
    echo "<input type='hidden' name='uname' value='" . htmlspecialchars($uname) . "'>";
    echo "<input type='submit' value='Book This Route'>";
    echo "</form>";
} else {
    echo "<p><strong>No path found using Dijkstra from $source to $destination on $date.</strong></p>";
}

// Step 5: All Direct Flights
echo "<h3>All Direct Flights:</h3>";
$foundDirect = false;
echo "<table border='1' cellpadding='8'>
    <tr>
        <th>Flight Name</th>
        <th>From</th>
        <th>To</th>
        <th>Departure</th>
        <th>Arrival</th>
        <th>Duration</th>
        <th>Price</th>
        <th>Book</th>
    </tr>";
foreach ($allFlights as $flight) {
    if ($flight['source'] === $source && $flight['destination'] === $destination) {
        $foundDirect = true;
        echo "<tr>
            <td>{$flight['flightName']}</td>
            <td>{$flight['source']}</td>
            <td>{$flight['destination']}</td>
            <td>{$flight['depaTime']}</td>
            <td>{$flight['arrivalTime']}</td>
            <td>{$flight['duration']} mins</td>
            <td>₹{$flight['price']}</td>
            <td>
                <form method='POST' action='book.php'>";
        foreach ($flight as $key => $value) {
            echo "<input type='hidden' name='flights[0][$key]' value='" . htmlspecialchars($value) . "'>";
        }
        echo "<input type='hidden' name='totalPrice' value='{$flight['price']}'>";
        echo "<input type='hidden' name='uname' value='" . htmlspecialchars($uname) . "'>";
        echo "<input type='submit' value='Book'>";
        echo "</form>
            </td>
        </tr>";
    }
}
echo "</table>";

if (!$foundDirect) echo "<p>No direct flights available.</p>";

$stmt->close();
$conn->close();
?>
