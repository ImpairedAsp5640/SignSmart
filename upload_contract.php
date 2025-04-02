<?php
session_start();

require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];

$title = isset($_POST['title']) ? $_POST['title'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Contract title is required']);
    exit();
}

$filePath = null;
$fileType = null;
$contractContent = null;

error_log("Upload contract request received. Title: " . $title);

if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
    $file = $_FILES['contract_file'];
    error_log("File upload detected: " . print_r($file, true));
    
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'];
    
    $maxFileSize = 20 * 1024 * 1024;
    
    $uploadDir = 'uploads/';
    
    if (!in_array($extension, $allowedExtensions)) {
        echo json_encode(['success' => false, 'error' => 'File type not allowed']);
        exit();
    }
    
    if ($file['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds limit (20MB)']);
        exit();
    }
    
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Failed to create upload directory: " . $uploadDir);
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
            exit();
        }
    }
    
    if (!is_writable($uploadDir)) {
        chmod($uploadDir, 0755); 
        if (!is_writable($uploadDir)) {
            error_log("Upload directory is not writable: " . $uploadDir);
            echo json_encode(['success' => false, 'error' => 'Upload directory is not writable']);
            exit();
        }
    }
    
    $newFilename = uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . $newFilename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $filePath = $uploadPath;
        $fileType = $file['type'] ?: mime_content_type($uploadPath); 
        
        chmod($uploadPath, 0644);
        
        if ($extension == 'txt') {
            $contractContent = file_get_contents($uploadPath);
        }
        
        error_log("File successfully uploaded to: " . $uploadPath);
    } else {
        $uploadError = error_get_last();
        error_log("Contract file upload error: " . print_r($uploadError, true));
        echo json_encode(['success' => false, 'error' => 'Failed to upload file: ' . ($uploadError ? $uploadError['message'] : 'Unknown error')]);
        exit();
    }
}
else if (isset($_POST['contract_link']) && !empty($_POST['contract_link'])) {
    $contractLink = $_POST['contract_link'];
    error_log("URL upload detected: " . $contractLink);
    
    if (!filter_var($contractLink, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid URL']);
        exit();
    }
    
    $uploadDir = 'uploads/';
    
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Failed to create upload directory: " . $uploadDir);
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
            exit();
        }
    }
    
    $contractContent = @file_get_contents($contractLink);
    
    if ($contractContent === false) {
        echo json_encode(['success' => false, 'error' => 'Unable to access URL content']);
        exit();
    }
    
    $newFilename = uniqid() . '.txt';
    $uploadPath = $uploadDir . $newFilename;
    
    if (file_put_contents($uploadPath, $contractContent)) {
        $filePath = $uploadPath;
        $fileType = 'text/plain';
        
        chmod($uploadPath, 0644);
        error_log("URL content saved to: " . $uploadPath);
    } else {
        error_log("Failed to save URL content to: " . $uploadPath);
        echo json_encode(['success' => false, 'error' => 'Failed to save URL content']);
        exit();
    }
} else {
    error_log("No file or URL provided in the request");
    echo json_encode(['success' => false, 'error' => 'No file or URL provided']);
    exit();
}

$conn->query("
    CREATE TABLE IF NOT EXISTS contracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        status ENUM('pending', 'analyzed', 'signed', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

$stmt = $conn->prepare("INSERT INTO contracts (user_id, title, description, file_path, file_type, status) VALUES (?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param("issss", $userId, $title, $description, $filePath, $fileType);

if ($stmt->execute()) {
    $contractId = $conn->insert_id;
    error_log("Contract successfully saved to database with ID: " . $contractId);
    echo json_encode(['success' => true, 'contract_id' => $contractId]);
} else {
    error_log("Database error when saving contract: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Failed to save contract: ' . $stmt->error]);
}
?>

