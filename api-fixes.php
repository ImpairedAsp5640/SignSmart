<?php
header('Content-Type: application/json');

ob_start();

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
      'model' => 'gpt-4-vision-preview',
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

function sendMessage($name = null, $email = null, $message = null, $file = null) {
    $name = $name ?? $_POST['name'] ?? '';
    $email = $email ?? $_POST['email'] ?? '';
    $message = $message ?? $_POST['message'] ?? '';
    $file = $file ?? $_FILES['file'] ?? null;

    $to = 'email@example.com';
    $subject = 'New Contact Form Submission';
    $body = "Name: $name\nEmail: $email\nMessage:\n$message";
    $headers = "From: $email\r\n";
    $headers .= "Reply-To: $email\r\n";
    $attachments = [];

    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        if ($file['size'] > 50 * 1024 * 1024) {
            error_log("File size exceeds limit: " . $file['size'] . " bytes");
            echo json_encode(['success' => false, 'error' => 'File size exceeds the 50MB limit']);
            return;
        }

        $file_name = $file['name'];
        $file_tmp_name = $file['tmp_name'];
        $file_type = $file['type'];
        $file_size = $file['size'];

        $file_content = file_get_contents($file_tmp_name);
        $file_content_encoded = chunk_split(base64_encode($file_content));

        $boundary = md5(time());

        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n\r\n";

        $body = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $body . "\r\n\r\n";

        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: " . $file_type . "; name=\"" . $file_name . "\"\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . $file_name . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $file_content_encoded . "\r\n\r\n";
        $body .= "--" . $boundary . "--";
    }

    if (mail($to, $subject, $body, $headers)) {
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    } else {
        error_log("Failed to send email. Errors: " . error_get_last()['message']);
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
}


if (basename(__FILE__) == 'api.php') {
    session_start();
    
    require_once 'config.php';
    
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    try {
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
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

ob_end_flush();
?>

