<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

function sendMessage() {
    global $conn;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User not authenticated']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $chatId = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : null;
    
    $filePath = null;
    $originalFilename = null;
    $fileType = null;
    $fileSize = null;
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File size exceeds the 20MB limit']);
            return;
        }
        
        if (!file_exists(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        
        $originalFilename = $_FILES['file']['name'];
        $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $newFilename = uniqid() . '.' . $fileExtension;
        $uploadPath = UPLOAD_DIR . $newFilename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
            $filePath = $uploadPath;
            $fileType = $_FILES['file']['type'];
            $fileSize = $_FILES['file']['size'];
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
            return;
        }
    }
    
    if (!$chatId) {
        $stmt = $conn->prepare("INSERT INTO chats (user_id, title) VALUES (?, ?)");
        $title = "New Chat";
        $stmt->bind_param("is", $userId, $title);
        $stmt->execute();
        $chatId = $conn->insert_id;
        $updateHistory = true;
    } else {
        $stmt = $conn->prepare("SELECT id FROM chats WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $chatId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Chat not found or access denied']);
            return;
        }
        
        $updateHistory = false;
    }
    
    $stmt = $conn->prepare("INSERT INTO messages (chat_id, role, content) VALUES (?, 'user', ?)");
    $stmt->bind_param("is", $chatId, $message);
    $stmt->execute();
    $messageId = $conn->insert_id;
    
    if ($filePath) {
        $stmt = $conn->prepare("INSERT INTO uploads (message_id, original_filename, stored_filename, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
        $storedFilename = $newFilename;
        $stmt->bind_param("issssi", $messageId, $originalFilename, $storedFilename, $filePath, $fileType, $fileSize);
        $stmt->execute();
    }
    
    $stmt = $conn->prepare("UPDATE chats SET title = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $title = substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '');
    $stmt->bind_param("si", $title, $chatId);
    $stmt->execute();
    
    try {
        if ($filePath && strpos($fileType, 'image/') === 0) {
            $response = processImageWithVision(OPENAI_API_KEY, $message, $filePath);
            $responseData = json_decode($response, true);
            
            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']['message']);
            }
            
            $aiResponse = $responseData['choices'][0]['message']['content'];
        } else {
            $aiResponse = generateAIResponse($message, $filePath);
        }
        
        $stmt = $conn->prepare("INSERT INTO messages (chat_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt->bind_param("is", $chatId, $aiResponse);
        $stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'response' => $aiResponse, 
            'chat_id' => $chatId,
            'update_history' => $updateHistory,
            'file_path' => $filePath
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generateAIResponse($message, $filePath = null) {
    $apiKey = OPENAI_API_KEY;
    
    if (empty($apiKey)) {
        throw new Exception("OpenAI API key is not configured");
    }
    
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $fileContent = '';
    if ($filePath && file_exists($filePath)) {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (in_array($fileExtension, ['txt', 'text'])) {
            $fileContent = file_get_contents($filePath);
            $fileContent = "The user has uploaded a text file with the following content:\n\n" . $fileContent;
        } else {
            $fileContent = "The user has uploaded a file of type: " . $fileExtension;
        }
    }
    
    $messages = [
        [
            'role' => 'system',
            'content' => "You are an AI assistant specialized in contract analysis. Help the user understand their contracts, identify potential issues, and provide clear explanations."
        ],
        [
            'role' => 'user',
            'content' => $message . ($fileContent ? "\n\n" . $fileContent : "")
        ]
    ];
    
    $data = [
        'model' => 'gpt-4o',
        'messages' => $messages,
        'max_tokens' => 2000,
        'temperature' => 0.7
    ];
    
    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['error'])) {
        throw new Exception($responseData['error']['message']);
    }
    
    return $responseData['choices'][0]['message']['content'];
}

function processImageWithVision($apiKey, $message, $imagePath) {
  $url = 'https://api.openai.com/v1/chat/completions';
  
  if (!file_exists($imagePath) || !is_readable($imagePath)) {
      throw new Exception("Image file not found or not readable");
  }
  
  $fileSize = filesize($imagePath);
  if ($fileSize > 20 * 1024 * 1024) { 
      throw new Exception("Image file is too large (max 20MB)");
  }
  
  error_log("Processing image: " . $imagePath . ", Size: " . $fileSize . " bytes");
  
  $imageData = file_get_contents($imagePath);
  if ($imageData === false) {
      throw new Exception("Failed to read image file");
  }

  $base64Image = base64_encode($imageData);

  $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey,
  ];

  $data = [
      'model' => 'gpt-4-turbo',
      'messages' => [
          [
              'role' => 'user',
              'content' => [
                  [
                      'type' => 'text',
                      'text' => $message,
                  ],
                  [
                      'type' => 'image_url',
                      'image_url' => [
                          'url' => "data:image/jpeg;base64," . $base64Image,
                      ],
                  ],
              ],
          ],
      ],
      'max_tokens' => 300,
  ];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);

  if (curl_errno($ch)) {
      throw new Exception('Curl error: ' . curl_error($ch));
  }

  curl_close($ch);

  return $response;
}

function getChatHistory() {
    global $conn;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User not authenticated']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT id, title, created_at FROM chats WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $chats = [];
    while ($chat = $result->fetch_assoc()) {
        $chats[] = $chat;
    }
    
    echo json_encode($chats);
}

function getChat() {
    global $conn;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User not authenticated']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    $chatId = isset($_GET['chat_id']) ? intval($_GET['chat_id']) : 0;
    
    $stmt = $conn->prepare("SELECT id FROM chats WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $chatId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Chat not found or access denied']);
        return;
    }
    
    $stmt = $conn->prepare("
        SELECT m.*, u.file_path 
        FROM messages m 
        LEFT JOIN uploads u ON m.id = u.message_id 
        WHERE m.chat_id = ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($message = $result->fetch_assoc()) {
        $messages[] = [
            'role' => $message['role'],
            'content' => $message['content'],
            'file_path' => $message['file_path']
        ];
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function newChat() {
    global $conn;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User not authenticated']);
        return;
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
}

if (basename(__FILE__) == 'api.php') {
    session_start();
    
    require_once 'config.php';
    
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    switch ($action) {
        case 'send_message':
            sendMessage();
            break;
        case 'get_chat_history':
            getChatHistory();
            break;
        case 'get_chat':
            getChat();
            break;
        case 'new_chat':
            newChat();
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}
?>

