package com.daaproject.demo.Service;
import org.springframework.stereotype.Service;
@Service
public class userservice {

    public boolean validateUser(String username, String password) {
        if ("admin".equals(username) && "admin123".equals(password)) {
            return true;
        } else {
            return false;
        }
    }
}
