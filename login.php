<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $query = "SELECT id, username, password FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }

        $stmt->close();
    } else {
        $error = "Please enter both username and password.";
    }
    
    $conn->close();
}

if (isset($error)) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Error - SignSmart</title>
        <link rel="stylesheet" href="login_style.css">
    </head>
    <body>
        <h1>SignSmart</h1>
        
        <div class="modal-content animate" style="display: block; position: relative; padding-top: 0; width: 40%; max-width: 500px;">
            
            <div class="container">
                <h2 style="text-align: center; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">Login Error</h2>
                <p style="text-align: center; color: #f44336; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">' . $error . '</p>
                
                <a href="login.html">
                    <button type="button" style="margin-top: 20px;">Try Again</button>
                </a>
            </div>
            
            <div class="container" style="background-color:#f1f1f1">
                <a href="register_page.html">
                    <button type="button" class="registerbtn">Register</button>
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

