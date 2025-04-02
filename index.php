<?php
session_start();

require_once 'config.php';

createRequiredTables();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$page = isset($_GET['page']) ? $_GET['page'] : 'chat';

function createRequiredTables() {
    global $conn;
    
    $conn->query("
        CREATE TABLE IF NOT EXISTS chats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255) DEFAULT 'New Chat',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id INT NOT NULL,
            role ENUM('user', 'assistant') NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            stored_filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
        )
    ");
    
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
    
    $conn->query("
        CREATE TABLE IF NOT EXISTS contract_analyses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_id INT NOT NULL,
            summary TEXT,
            key_terms TEXT,
            unfavorable_clauses TEXT,
            recommendation TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        )
    ");
    
    $conn->query("
        CREATE TABLE IF NOT EXISTS contract_comparisons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            conclusion TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    $conn->query("
        CREATE TABLE IF NOT EXISTS comparison_contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comparison_id INT NOT NULL,
            contract_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (comparison_id) REFERENCES contract_comparisons(id) ON DELETE CASCADE,
            FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
        )
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SignSmart - Contract Analysis</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <h2>SignSmart</h2>
            </div>
            
            <div class="nav-menu">
                <a href="index.php?page=chat" class="nav-item <?php echo $page === 'chat' ? 'active' : ''; ?>">
                    <i class="fas fa-comment-alt"></i> AI Chat
                </a>
                <a href="index.php?page=contracts" class="nav-item <?php echo $page === 'contracts' ? 'active' : ''; ?>">
                    <i class="fas fa-file-contract"></i> My Contracts
                </a>
                <a href="index.php?page=comparisons" class="nav-item <?php echo $page === 'comparisons' ? 'active' : ''; ?>">
                    <i class="fas fa-balance-scale"></i> Comparisons
                </a>
            </div>
            
            <div class="sidebar-section">
                <h3>Options</h3>
                <div class="new-chat">
                    <button id="new-chat-btn">
                        <i class="fas fa-plus"></i> New Chat
                    </button>
                </div>
            </div>

            <?php if ($page === 'chat'): ?>
            <div class="sidebar-section chat-history-section">
                <h3>Chat History</h3>
                <div class="chat-history" id="chat-history">
                    <?php
                    $stmt = $conn->prepare("SELECT id, title, created_at FROM chats WHERE user_id = ? ORDER BY updated_at DESC");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        echo '<div class="empty-history">No chat history yet</div>';
                    } else {
                        while ($chat = $result->fetch_assoc()) {
                            $isActive = isset($_GET['chat_id']) && $_GET['chat_id'] == $chat['id'] ? 'active' : '';
                            echo '<div class="chat-history-item ' . $isActive . '" data-id="' . $chat['id'] . '">';
                            echo '<div class="chat-history-title">' . htmlspecialchars($chat['title']) . '</div>';
                            echo '<div class="chat-history-date">' . date('M j', strtotime($chat['created_at'])) . '</div>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="user-info">
                <span><?php echo htmlspecialchars($username); ?></span>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="main-content">
            <?php 
            switch($page) {
                case 'chat':
                    include 'chat_page.php';
                    break;
                case 'contracts':
                    include 'contracts_page.php';
                    break;
                case 'comparisons':
                    include 'comparisons_page.php';
                    break;
                default:
                    include 'chat_page.php';
            }
            ?>
        </div>
    </div>

    <div id="upload-contract-modal" class="modal upload-contract-modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Upload Contract</h2>
            <form id="contract-upload-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="contract-title">Contract Title:</label>
                    <input type="text" id="contract-title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="contract-description">Description (optional):</label>
                    <textarea id="contract-description" name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Upload Method:</label>
                    <div class="upload-tabs">
                        <button type="button" class="upload-tab active" data-tab="file">File Upload</button>
                        <button type="button" class="upload-tab" data-tab="link">URL Link</button>
                    </div>
                    
                    <div id="file-upload-tab" class="upload-tab-content" style="display: block;">
                        <label for="contract-file" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i> Choose File
                            <span id="selected-file-name">No file chosen</span>
                        </label>
                        <input type="file" id="contract-file" name="contract_file" accept=".pdf,.doc,.docx,.txt,image/*">
                    </div>
                    
                    <div id="link-upload-tab" class="upload-tab-content" style="display: none;">
                        <input type="url" id="contract-link" name="contract_link" placeholder="Enter URL to contract">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="primary-btn">Upload Contract</button>
                    <button type="button" class="secondary-btn" id="cancel-upload">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>

