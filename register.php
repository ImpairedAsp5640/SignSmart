<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $check_query = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "Username already taken!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $username, $email, $hashed_password);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Registration failed: " . $stmt->error;
        }
    }

    $stmt->close();
}

$conn->close();

if (isset($success)) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Success - SignSmart</title>
        <link rel="stylesheet" href="login_style.css">
    </head>
    <body>
        <h1>SignSmart</h1>
        
        <div class="modal-content animate" style="display: block; position: relative; padding-top: 0; width: 40%; max-width: 500px;">
            
            <div class="container">
                <h2 style="text-align: center; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">Registration Successful</h2>
                <p style="text-align: center; color: #4CAF50; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">Your account has been created successfully.</p>
                
                <a href="login.html">
                    <button type="button" style="margin-top: 20px;">Login Now</button>
                </a>
            </div>
            
            <div class="container" style="background-color:#f1f1f1">
                <a href="index.html">
                    <button type="button" class="registerbtn">Back to Home</button>
                </a>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
            <p>&copy; 2025 SignSmart. All rights reserved.</p>
        </div>
    </body>
    </html>';
} elseif (isset($error)) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Error - SignSmart</title>
        <link rel="stylesheet" href="login_style.css">
    </head>
    <body>
        <h1>SignSmart</h1>
        
        <div class="modal-content animate" style="display: block; position: relative; padding-top: 0; width: 40%; max-width: 500px;">
            
            <div class="container">
                <h2 style="text-align: center; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">Registration Error</h2>
                <p style="text-align: center; color: #f44336; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">' . $error . '</p>
                
                <a href="register.html">
                    <button type="button" style="margin-top: 20px;">Try Again</button>
                </a>
            </div>
            
            <div class="container" style="background-color:#f1f1f1">
                <a href="login.html">
                    <button type="button" class="registerbtn">Login</button>
                </a>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
            <p>&copy; 2025 SignSmart. All rights reserved.</p>
        </div>
    </body>
    </html>';
}
?>