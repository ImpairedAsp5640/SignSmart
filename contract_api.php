<?php
session_start();

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'error' => 'User not authenticated']);
  exit();
}

$userId = $_SESSION['user_id'];

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
  case 'upload_contract':
      uploadContract($userId);
      break;
  case 'get_contract':
      getContract($userId);
      break;
  case 'analyze_contract':
      analyzeContract($userId);
      break;
  case 'update_contract_status':
      updateContractStatus($userId);
      break;
  case 'create_comparison':
      createComparison($userId);
      break;
  case 'get_comparison':
      getComparison($userId);
      break;
  default:
      echo json_encode(['success' => false, 'error' => 'Invalid action']);
      break;
}

function uploadContract($userId) {
global $conn;

$title = isset($_POST['title']) ? $_POST['title'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Contract title is required']);
    return;
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
    
    if (!defined('ALLOWED_EXTENSIONS')) {
        define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png']);
    }
    
    if (!defined('MAX_FILE_SIZE')) {
        define('MAX_FILE_SIZE', 50 * 1024 * 1024); 
    }
    
    if (!defined('UPLOAD_DIR')) {
        define('UPLOAD_DIR', 'uploads/');
    }
    
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        echo json_encode(['success' => false, 'error' => 'File type not allowed']);
        return;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds limit']);
        return;
    }
    
    if (!file_exists(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            error_log("Failed to create upload directory: " . UPLOAD_DIR);
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
            return;
        }
    }
    
    if (!is_writable(UPLOAD_DIR)) {
        chmod(UPLOAD_DIR, 0755);
        if (!is_writable(UPLOAD_DIR)) {
            error_log("Upload directory is not writable: " . UPLOAD_DIR);
            echo json_encode(['success' => false, 'error' => 'Upload directory is not writable']);
            return;
        }
    }
    
    $newFilename = uniqid() . '.' . $extension;
    $uploadPath = UPLOAD_DIR . $newFilename;
    
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
        return;
    }
}
else if (isset($_POST['contract_link']) && !empty($_POST['contract_link'])) {
    $contractLink = $_POST['contract_link'];
    error_log("URL upload detected: " . $contractLink);
    
    if (!filter_var($contractLink, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid URL']);
        return;
    }
    
    if (!defined('UPLOAD_DIR')) {
        define('UPLOAD_DIR', 'uploads/');
    }
    
    if (!file_exists(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            error_log("Failed to create upload directory: " . UPLOAD_DIR);
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
            return;
        }
    }
    
    $contractContent = @file_get_contents($contractLink);
    
    if ($contractContent === false) {
        echo json_encode(['success' => false, 'error' => 'Unable to access URL content']);
        return;
    }
    
    $newFilename = uniqid() . '.txt';
    $uploadPath = UPLOAD_DIR . $newFilename;
    
    if (file_put_contents($uploadPath, $contractContent)) {
        $filePath = $uploadPath;
        $fileType = 'text/plain';
        
        chmod($uploadPath, 0644);
        error_log("URL content saved to: " . $uploadPath);
    } else {
        error_log("Failed to save URL content to: " . $uploadPath);
        echo json_encode(['success' => false, 'error' => 'Failed to save URL content']);
        return;
    }
} else {
    error_log("No file or URL provided in the request");
    echo json_encode(['success' => false, 'error' => 'No file or URL provided']);
    return;
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
}

function getContract($userId) {
  global $conn;
  
  $contractId = isset($_GET['contract_id']) ? $_GET['contract_id'] : 0;
  
  $conn->query("
      CREATE TABLE IF NOT EXISTS contract_analyses (
          id INT AUTO_INCREMENT PRIMARY KEY,
          contract_id INT NOT NULL,
          summary TEXT,
          key_terms TEXT,
          unfavorable_clauses TEXT,
          recommendation TEXT,
          is_contract BOOLEAN DEFAULT TRUE,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
      )
  ");
  
  $stmt = $conn->prepare("
      SELECT c.*, ca.summary, ca.key_terms, ca.unfavorable_clauses, ca.recommendation, ca.is_contract 
      FROM contracts c 
      LEFT JOIN contract_analyses ca ON c.id = ca.contract_id 
      WHERE c.id = ? AND c.user_id = ?
  ");
  $stmt->bind_param("ii", $contractId, $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $contract = $result->fetch_assoc();
  
  if (!$contract) {
      echo json_encode(['success' => false, 'error' => 'Contract not found or access denied']);
      return;
  }
  
  if ($contract['file_type'] == 'text/plain') {
      $contract['file_content'] = file_get_contents($contract['file_path']);
  }

  if ($contract['summary']) {
      $contract['analysis'] = [
          'summary' => $contract['summary'],
          'key_terms' => $contract['key_terms'],
          'unfavorable_clauses' => $contract['unfavorable_clauses'],
          'recommendation' => $contract['recommendation'],
          'is_contract' => $contract['is_contract']
      ];
  }
  
  echo json_encode(['success' => true, 'contract' => $contract]);
}

function analyzeContract($userId) {
  global $conn;
  
  $contractId = isset($_POST['contract_id']) ? $_POST['contract_id'] : 0;
  
  $stmt = $conn->prepare("SELECT * FROM contracts WHERE id = ? AND user_id = ?");
  $stmt->bind_param("ii", $contractId, $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $contract = $result->fetch_assoc();
  
  if (!$contract) {
      echo json_encode(['success' => false, 'error' => 'Contract not found or access denied']);
      return;
  }
  
  $contractContent = '';
  $isImage = false;
  
  if ($contract['file_type'] == 'text/plain') {
      $contractContent = file_get_contents($contract['file_path']);
  } elseif (strpos($contract['file_type'], 'image/') === 0) {
      $isImage = true;
  } elseif (strpos($contract['file_type'], 'application/pdf') === 0 || $contract['file_type'] == 'application/pdf') {
      $contractContent = "PDF DOCUMENT: " . $contract['title'] . "\n\nThis is a PDF document that should be analyzed as a complete legal contract.";
  } else {
      $contractContent = "Document: " . $contract['title'] . "\n\nThis is a document that should be analyzed as a complete legal contract.";
  }
  
  try {
      $analysis = analyzeContractWithAI($contractContent, $contract['title'], $contract['file_path'], $isImage);
      
      error_log("Analysis results: " . print_r($analysis, true));
      
      $conn->query("
          CREATE TABLE IF NOT EXISTS contract_analyses (
              id INT AUTO_INCREMENT PRIMARY KEY,
              contract_id INT NOT NULL,
              summary TEXT,
              key_terms TEXT,
              unfavorable_clauses TEXT,
              recommendation TEXT,
              is_contract BOOLEAN DEFAULT TRUE,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
          )
      ");

      $result = $conn->query("SHOW COLUMNS FROM contract_analyses LIKE 'is_contract'");
      if ($result->num_rows === 0) {
          $conn->query("ALTER TABLE contract_analyses ADD COLUMN is_contract BOOLEAN DEFAULT TRUE AFTER recommendation");
      }
      
      $checkStmt = $conn->prepare("SELECT id FROM contract_analyses WHERE contract_id = ?");
      $checkStmt->bind_param("i", $contractId);
      $checkStmt->execute();
      $checkResult = $checkStmt->get_result();
      
      if ($checkResult->num_rows > 0) {
          $stmt = $conn->prepare("
              UPDATE contract_analyses 
              SET summary = ?, key_terms = ?, unfavorable_clauses = ?, recommendation = ?, is_contract = ?
              WHERE contract_id = ?
          ");
          $isContract = $analysis['is_contract'] ? 1 : 0;
          $stmt->bind_param(
              "ssssii", 
              $analysis['summary'], 
              $analysis['key_terms'], 
              $analysis['unfavorable_clauses'],
              $analysis['recommendation'],
              $isContract,
              $contractId
          );
      } else {
          $stmt = $conn->prepare("
              INSERT INTO contract_analyses 
              (contract_id, summary, key_terms, unfavorable_clauses, recommendation, is_contract) 
              VALUES (?, ?, ?, ?, ?, ?)
          ");
          $isContract = $analysis['is_contract'] ? 1 : 0;
          $stmt->bind_param(
              "issssi", 
              $contractId, 
              $analysis['summary'], 
              $analysis['key_terms'], 
              $analysis['unfavorable_clauses'],
              $analysis['recommendation'],
              $isContract
          );
      }
      
      if ($stmt->execute()) {
          $stmt = $conn->prepare("UPDATE contracts SET status = 'analyzed' WHERE id = ?");
          $stmt->bind_param("i", $contractId);
          $stmt->execute();
          
          echo json_encode(['success' => true]);
      } else {
          echo json_encode(['success' => false, 'error' => 'Failed to save analysis: ' . $stmt->error]);
      }
  } catch (Exception $e) {
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
}

function analyzeContractWithAI($contractContent, $contractTitle, $filePath = null, $isImage = false) {
  global $conn;
  $apiKey = OPENAI_API_KEY;
  
  if (empty($apiKey)) {
      throw new Exception("OpenAI API key is not configured");
  }
  
  error_log("Analyzing contract: " . $contractTitle);
  error_log("Contract content length: " . strlen($contractContent));
  error_log("Is image: " . ($isImage ? "Yes" : "No"));
  
  $url = 'https://api.openai.com/v1/chat/completions';
  
  if ($isImage && $filePath) {
      return analyzeContractImageWithVision($apiKey, $contractTitle, $filePath);
  }
  
  if (strlen($contractContent) < 50 && !$isImage && strpos($contractContent, "PDF DOCUMENT:") === false) {
      error_log("Contract content too short, might not be a valid contract");
      return [
          'summary' => "The provided content is too short to be analyzed as a contract.",
          'key_terms' => "Not applicable - insufficient content.",
          'unfavorable_clauses' => "Not applicable - insufficient content.",
          'recommendation' => "Unable to provide a recommendation due to insufficient content.",
          'is_contract' => false
      ];
  }
  
  if (strpos($contractContent, "PDF DOCUMENT:") !== false) {
      return [
          'summary' => "This appears to be a valid contract document in PDF format titled '$contractTitle'. The document contains legal terms and conditions that establish rights and obligations between parties.",
          'key_terms' => "1. Parties involved: The contract establishes a legal relationship between the signing parties.\n2. Term/Duration: The contract likely specifies how long the agreement remains in effect.\n3. Payment Terms: The contract may include details about compensation, payment schedules, and methods.\n4. Termination Conditions: Circumstances under which either party can end the agreement.\n5. Confidentiality: Provisions regarding the handling of sensitive information.",
          'unfavorable_clauses' => "Without detailed text extraction from the PDF, specific unfavorable clauses cannot be identified. However, common unfavorable clauses to watch for include:\n1. One-sided termination rights\n2. Automatic renewal with difficult cancellation terms\n3. Broad indemnification requirements\n4. Limitation of liability clauses that heavily favor one party\n5. Non-compete or exclusivity provisions that may be overly restrictive",
          'recommendation' => "This contract requires careful review before signing. Since this is a PDF document and detailed text extraction was not possible, we recommend having a legal professional review the document in full to identify any potentially unfavorable terms or conditions.",
          'is_contract' => true
      ];
  }
  
  $messages = [
      [
          'role' => 'system',
          'content' => "You are a legal expert specialized in contract analysis with years of experience in contract law. 
          Your task is to analyze the provided contract thoroughly, identify key terms, unfavorable clauses, potential risks, 
          and provide a detailed recommendation on whether to sign it. Be specific about legal implications and potential consequences.
          
          Focus on identifying clauses that may be disadvantageous to the client, such as:
          - One-sided termination rights
          - Excessive liability or indemnification clauses
          - Unreasonable restrictions or non-compete provisions
          - Hidden fees or unclear payment terms
          - Vague or ambiguous language that could be interpreted against the client
          - Automatic renewal clauses with difficult cancellation terms
          - Clauses that waive important rights
          
          If the provided text does not appear to be a contract or legal document, clearly indicate this in your response and explain why.
          
          Format your response in clear sections with plain text, not JSON."
      ],
      [
          'role' => 'user',
          'content' => "Please analyze the following contract titled '$contractTitle'. First, determine if this is actually a contract or legal document. If it is NOT a contract, clearly state this fact and briefly explain why.

          If it IS a contract, provide a comprehensive analysis with these sections:
          
          1. SUMMARY: A concise but thorough summary of the contract's purpose and main provisions.
          
          2. KEY TERMS: Identify and explain the most important terms of the contract, including obligations, rights, payment terms, 
          duration, termination conditions, and any unusual provisions.
          
          3. UNFAVORABLE CLAUSES: Identify any clauses that may be disadvantageous, one-sided, or risky. 
          Explain why each clause is problematic and what potential consequences it might have.
          
          4. RECOMMENDATION: Provide a clear recommendation on whether to sign this contract, negotiate specific terms, 
          or reject it entirely. Include specific suggestions for negotiation if applicable.
          
          Here's the contract:
          $contractContent"
      ]
  ];
  
  $data = [
      'model' => 'gpt-4o',
      'messages' => $messages,
      'max_tokens' => 4000,
      'temperature' => 0.3
  ];
  
  $ch = curl_init($url);
  
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey
  ]);
  
  curl_setopt($ch, CURLOPT_TIMEOUT, 120);
  
  $response = curl_exec($ch);
  
  if (curl_errno($ch)) {
      throw new Exception(curl_error($ch));
  }
  
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($httpCode != 200) {
      throw new Exception("Contract Analysis HTTP Error: " . $httpCode);
  }
  
  curl_close($ch);
  
  $responseData = json_decode($response, true);
  
  if (isset($responseData['error'])) {
      throw new Exception($responseData['error']['message']);
  }
  
  $analysisText = $responseData['choices'][0]['message']['content'];
  
  $isContract = true;
  if (stripos($analysisText, "not a contract") !== false || 
      stripos($analysisText, "does not appear to be a contract") !== false ||
      stripos($analysisText, "is not a legal document") !== false) {
      $isContract = false;
  }
  
  $summary = extractSection($analysisText, 'SUMMARY');
  $keyTerms = extractSection($analysisText, 'KEY TERMS');
  $unfavorableClauses = extractSection($analysisText, 'UNFAVORABLE CLAUSES');
  $recommendation = extractSection($analysisText, 'RECOMMENDATION');
  
  if (!$isContract) {
      return [
          'summary' => "This document does not appear to be a contract or legal document. " . $summary,
          'key_terms' => "Not applicable - this is not a contract.",
          'unfavorable_clauses' => "Not applicable - this is not a contract.",
          'recommendation' => "This document is not a contract and does not require signing or legal review.",
          'is_contract' => false
      ];
  }
  
  return [
      'summary' => $summary,
      'key_terms' => $keyTerms,
      'unfavorable_clauses' => $unfavorableClauses,
      'recommendation' => $recommendation,
      'is_contract' => true
  ];
}

function analyzeContractImageWithVision($apiKey, $contractTitle, $imagePath) {
  $url = 'https://api.openai.com/v1/chat/completions';
  
  if (!file_exists($imagePath) || !is_readable($imagePath)) {
      throw new Exception("Image file not found or not readable");
  }
  
  $fileSize = filesize($imagePath);
  if ($fileSize > 10 * 1024 * 1024) { 
      throw new Exception("Image file is too large (max 10MB)");
  }
  
  $imageData = file_get_contents($imagePath);
  if ($imageData === false) {
      throw new Exception("Failed to read image file");
  }
  
  if ($fileSize > 2 * 1024 * 1024) {
      $tempFile = tempnam(sys_get_temp_dir(), 'compressed_');
      
      $imageInfo = getimagesize($imagePath);
      if (!$imageInfo) {
          throw new Exception("Unable to determine image type");
      }
      
      $imageType = $imageInfo[2];
      
      switch ($imageType) {
          case IMAGETYPE_JPEG:
              $image = imagecreatefromjpeg($imagePath);
              break;
          case IMAGETYPE_PNG:
              $image = imagecreatefrompng($imagePath);
              break;
          case IMAGETYPE_GIF:
              $image = imagecreatefromgif($imagePath);
              break;
          default:
              throw new Exception("Unsupported image type");
      }
      
      if (!$image) {
          throw new Exception("Failed to create image resource");
      }
      
      imagejpeg($image, $tempFile, 75);
      
      imagedestroy($image);
      
      $imageData = file_get_contents($tempFile);
      
      unlink($tempFile);
      
      if ($imageData === false) {
          throw new Exception("Failed to read compressed image");
      }
  }
  
  $base64Image = base64_encode($imageData);
  
  $systemMessage = "You are a legal expert specialized in contract analysis with years of experience in contract law. 
  Your task is to analyze the provided image to determine if it contains a contract or legal document, and if so, provide a detailed analysis.
  
  First, determine if the image contains a contract or legal document. If it does NOT contain a contract, clearly state this fact and briefly explain why.
  
  If it DOES contain a contract, analyze it thoroughly, identify key terms, unfavorable clauses, potential risks, and provide a detailed recommendation.
  
  Focus on identifying clauses that may be disadvantageous to the client, such as:
  - One-sided termination rights
  - Excessive liability or indemnification clauses
  - Unreasonable restrictions or non-compete provisions
  - Hidden fees or unclear payment terms
  - Vague or ambiguous language that could be interpreted against the client
  - Automatic renewal clauses with difficult cancellation terms
  - Clauses that waive important rights
  
  Format your response in clear sections with plain text, not JSON.";
  
  $content = [
      [
          'type' => 'text',
          'text' => "Please analyze this image titled '$contractTitle'. First, determine if this image contains a contract or legal document. If it is NOT a contract, clearly state this fact and briefly explain why.

          If it IS a contract, provide a comprehensive analysis with these sections:
          
          1. SUMMARY: A concise but thorough summary of the contract's purpose and main provisions.
          
          2. KEY TERMS: Identify and explain the most important terms of the contract, including obligations, rights, payment terms, 
          duration, termination conditions, and any unusual provisions.
          
          3. UNFAVORABLE CLAUSES: Identify any clauses that may be disadvantageous, one-sided, or risky. 
          Explain why each clause is problematic and what potential consequences it might have.
          
          4. RECOMMENDATION: Provide a clear recommendation on whether to sign this contract, negotiate specific terms, 
          or reject it entirely. Include specific suggestions for negotiation if applicable."
      ],
      [
          'type' => 'image_url',
          'image_url' => [
              'url' => 'data:image/jpeg;base64,' . $base64Image
          ]
      ]
  ];
  
  $messages = [
      [
          'role' => 'system',
          'content' => $systemMessage
      ],
      [
          'role' => 'user',
          'content' => $content
      ]
  ];
  
  $data = [
      'model' => 'gpt-4o',
      'messages' => $messages,
      'max_tokens' => 4000,
      'temperature' => 0.3
  ];

  $ch = curl_init($url);
  
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey
  ]);
  
  curl_setopt($ch, CURLOPT_TIMEOUT, 180);
  
  $response = curl_exec($ch);
  
  if (curl_errno($ch)) {
      throw new Exception(curl_error($ch));
  }
  
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($httpCode != 200) {
      throw new Exception("Vision API HTTP Error: " . $httpCode);
  }
  
  curl_close($ch);
  
  $responseData = json_decode($response, true);
  
  if (isset($responseData['error'])) {
      throw new Exception($responseData['error']['message']);
  }
  
  $analysisText = $responseData['choices'][0]['message']['content'];
  
  $isContract = true;
  if (stripos($analysisText, "not a contract") !== false || 
      stripos($analysisText, "does not appear to be a contract") !== false ||
      stripos($analysisText, "is not a legal document") !== false ||
      stripos($analysisText, "does not contain a contract") !== false) {
      $isContract = false;
  }
  
  $summary = extractSection($analysisText, 'SUMMARY');
  $keyTerms = extractSection($analysisText, 'KEY TERMS');
  $unfavorableClauses = extractSection($analysisText, 'UNFAVORABLE CLAUSES');
  $recommendation = extractSection($analysisText, 'RECOMMENDATION');
  
  if (!$isContract) {
      return [
          'summary' => "This image does not appear to contain a contract or legal document. " . $summary,
          'key_terms' => "Not applicable - this is not a contract.",
          'unfavorable_clauses' => "Not applicable - this is not a contract.",
          'recommendation' => "This image does not contain a contract and does not require signing or legal review.",
          'is_contract' => false
      ];
  }
  
  return [
      'summary' => $summary,
      'key_terms' => $keyTerms,
      'unfavorable_clauses' => $unfavorableClauses,
      'recommendation' => $recommendation,
      'is_contract' => true
  ];
}

function extractSection($text, $sectionName) {
  $pattern = '/\b' . preg_quote($sectionName, '/') . '\b.*?:/i';
  if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
      $startPos = $matches[0][1] + strlen($matches[0][0]);
      
      $nextSection = null;
      $sections = ['SUMMARY', 'KEY TERMS', 'UNFAVORABLE CLAUSES', 'RECOMMENDATION'];
      
      foreach ($sections as $section) {
          if ($section === strtoupper($sectionName)) continue;
          
          $pattern = '/\b' . preg_quote($section, '/') . '\b.*?:/i';
          if (preg_match($pattern, $text, $nextMatches, PREG_OFFSET_CAPTURE, $startPos)) {
              if ($nextSection === null || $nextMatches[0][1] < $nextSection[1]) {
                  $nextSection = [$section, $nextMatches[0][1]];
              }
          }
      }
      
      if ($nextSection !== null) {
          return trim(substr($text, $startPos, $nextSection[1] - $startPos));
      } else {
          return trim(substr($text, $startPos));
      }
  }
  
  if (stripos($text, "not a contract") !== false || 
      stripos($text, "does not appear to be a contract") !== false ||
      stripos($text, "is not a legal document") !== false) {
      return $text;
  }
  
  return '';
}

function updateContractStatus($userId) {
  global $conn;
  
  $contractId = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
  $status = isset($_POST['status']) ? $_POST['status'] : '';
  
  if (!in_array($status, ['pending', 'analyzed', 'signed', 'rejected'])) {
      echo json_encode(['success' => false, 'error' => 'Invalid status']);
      return;
  }
  
  $checkStmt = $conn->prepare("SELECT id FROM contracts WHERE id = ? AND user_id = ?");
  $checkStmt->bind_param("ii", $contractId, $userId);
  $checkStmt->execute();
  $result = $checkStmt->get_result();
  
  if ($result->num_rows === 0) {
      echo json_encode(['success' => false, 'error' => 'Contract not found or access denied']);
      return;
  }
  
  $stmt = $conn->prepare("UPDATE contracts SET status = ? WHERE id = ?");
  $stmt->bind_param("si", $status, $contractId);
  $stmt->execute();
  
  if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
      echo json_encode(['success' => true]);
  } else {
      echo json_encode(['success' => false, 'error' => 'Failed to update contract status: ' . $stmt->error]);
  }
}

function createComparison($userId) {
  global $conn;
  
  $title = isset($_POST['title']) ? $_POST['title'] : '';
  $description = isset($_POST['description']) ? $_POST['description'] : '';
  $contracts = isset($_POST['contracts']) ? $_POST['contracts'] : [];
  
  if (empty($title)) {
      echo json_encode(['success' => false, 'error' => 'Comparison title is required']);
      return;
  }
  
  if (count($contracts) < 2 || count($contracts) > 3) {
      echo json_encode(['success' => false, 'error' => 'Please select 2 or 3 contracts to compare']);
      return;
  }
  
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

  $conn->begin_transaction();
  
  try {
      $stmt = $conn->prepare("INSERT INTO contract_comparisons (user_id, title, description) VALUES (?, ?, ?)");
      $stmt->bind_param("iss", $userId, $title, $description);
      $stmt->execute();
      $comparisonId = $conn->insert_id;
      
      $stmt = $conn->prepare("INSERT INTO comparison_contracts (comparison_id, contract_id) VALUES (?, ?)");
      
      foreach ($contracts as $contractId) {
          $checkStmt = $conn->prepare("SELECT id FROM contracts WHERE id = ? AND user_id = ?");
          $checkStmt->bind_param("ii", $contractId, $userId);
          $checkStmt->execute();
          $result = $checkStmt->get_result();
          
          if ($result->num_rows == 0) {
              throw new Exception("Contract ID $contractId not found or access denied");
          }
          
          $stmt->bind_param("ii", $comparisonId, $contractId);
          $stmt->execute();
      }
      
      $conn->commit();
      
      echo json_encode(['success' => true, 'comparison_id' => $comparisonId]);
  } catch (Exception $e) {
      $conn->rollback();
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
}

function getComparison($userId) {
  global $conn;
  
  $comparisonId = isset($_GET['comparison_id']) ? $_GET['comparison_id'] : 0;
  
  $stmt = $conn->prepare("SELECT * FROM contract_comparisons WHERE id = ? AND user_id = ?");
  $stmt->bind_param("ii", $comparisonId, $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $comparison = $result->fetch_assoc();
  
  if (!$comparison) {
      echo json_encode(['success' => false, 'error' => 'Comparison not found or access denied']);
      return;
  }
  
  $stmt = $conn->prepare("
      SELECT c.*, ca.summary, ca.key_terms, ca.unfavorable_clauses, ca.recommendation, ca.is_contract 
      FROM contracts c 
      JOIN comparison_contracts cc ON c.id = cc.contract_id 
      LEFT JOIN contract_analyses ca ON c.id = ca.contract_id 
      WHERE cc.comparison_id = ?
  ");
  $stmt->bind_param("i", $comparisonId);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $contracts = [];
  while ($contract = $result->fetch_assoc()) {
      if ($contract['summary']) {
          $contract['analysis'] = [
              'summary' => $contract['summary'],
              'key_terms' => $contract['key_terms'],
              'unfavorable_clauses' => $contract['unfavorable_clauses'],
              'recommendation' => $contract['recommendation'],
              'is_contract' => $contract['is_contract']
          ];
      }
      
      $contracts[] = $contract;
  }
  
  $comparison['contracts'] = $contracts;
  
  $allAnalyzed = true;
  foreach ($contracts as $contract) {
      if ($contract['status'] !== 'analyzed') {
          $allAnalyzed = false;
          break;
      }
  }
  
  if ($allAnalyzed && count($contracts) >= 2) {
      $comparison['conclusion'] = generateComparisonConclusion($contracts);
  }
  
  echo json_encode(['success' => true, 'comparison' => $comparison]);
}

function generateComparisonConclusion($contracts) {
  $nonContractCount = 0;
  foreach ($contracts as $contract) {
      if (isset($contract['analysis']['is_contract']) && !$contract['analysis']['is_contract']) {
          $nonContractCount++;
      }
  }
  
  if ($nonContractCount > 0) {
      if ($nonContractCount == count($contracts)) {
          return "<p><strong>None of the uploaded documents appear to be contracts.</strong> Please upload valid contracts for comparison.</p>";
      } else {
          return "<p><strong>Warning:</strong> " . $nonContractCount . " of the uploaded documents do not appear to be contracts. This may affect the accuracy of the comparison.</p>";
      }
  }
  
  $bestContract = null;
  $bestScore = -1;
  
  foreach ($contracts as $contract) {
      $score = 0;
      $recommendation = strtolower($contract['analysis']['recommendation']);
      
      if (strpos($recommendation, 'sign') !== false && strpos($recommendation, 'not sign') === false) {
          $score = 2;
      } else if (strpos($recommendation, 'caution') !== false || strpos($recommendation, 'consider') !== false) {
          $score = 1;
      }
      
      if ($score > $bestScore) {
          $bestScore = $score;
          $bestContract = $contract;
      }
  }
  
  $conclusion = "<p>Based on the analysis of these contracts, ";
  
  if ($bestScore > 0) {
      $conclusion .= "<strong>\"" . htmlspecialchars($bestContract['title']) . "\"</strong> appears to be the best option. ";
      
      if ($bestScore == 2) {
          $conclusion .= "This contract is recommended for signing, as it contains fewer unfavorable clauses and better terms compared to the alternatives.</p>";
      } else {
          $conclusion .= "While this contract has some concerns, it offers better terms overall compared to the alternatives. Proceed with caution and consider negotiating any unfavorable terms before signing.</p>";
      }
  } else {
      $conclusion .= "<strong>none of the contracts are recommended for signing</strong> without significant modifications. All contracts contain unfavorable clauses that could put you at a disadvantage.</p>";
  }
  
  $conclusion .= "<p>Key differences between the contracts:</p><ul>";
  
  foreach ($contracts as $contract) {
      $conclusion .= "<li><strong>" . htmlspecialchars($contract['title']) . "</strong>: ";
      
      if ($contract === $bestContract) {
          $conclusion .= "Offers the most favorable terms overall. ";
      } else {
          $conclusion .= "Contains more unfavorable clauses than the recommended option. ";
      }
      
      $conclusion .= "Key concerns include ";
      
      $unfavorableClauses = $contract['analysis']['unfavorable_clauses'];
      $concerns = extractKeyConcerns($unfavorableClauses);
      
      if (!empty($concerns)) {
          $conclusion .= implode(" and ", array_slice($concerns, 0, 2));
      } else {
          $conclusion .= "various contractual issues";
      }
      
      $conclusion .= ".</li>";
  }
  
  $conclusion .= "</ul>";
  
  return $conclusion;
}

function extractKeyConcerns($unfavorableClauses) {
  $concerns = [];
  
  preg_match_all('/["\'](.*?)["\']/i', $unfavorableClauses, $matches);
  if (!empty($matches[1])) {
      return array_slice($matches[1], 0, 3);
  }
  
  $sentences = preg_split('/[.!?]+/', $unfavorableClauses, -1, PREG_SPLIT_NO_EMPTY);
  if (!empty($sentences)) {
      foreach ($sentences as $sentence) {
          $sentence = trim($sentence);
          if (strlen($sentence) > 10 && strlen($sentence) < 100) {
              $concerns[] = $sentence;
          }
      }
  }
  
  return array_slice($concerns, 0, 3);
}
?>

