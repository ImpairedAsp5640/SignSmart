<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];

$conn->query("
    CREATE TABLE IF NOT EXISTS chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT 'New Chat',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

$title = "New Chat";
$stmt = $conn->prepare("INSERT INTO chats (user_id, title) VALUES (?, ?)");
$stmt->bind_param("is", $userId, $title);

if ($stmt->execute()) {
    $chatId = $conn->insert_id;
    echo json_encode(['success' => true, 'chat_id' => $chatId]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to create new chat: ' . $stmt->error]);
}
?>

