<?php

header('Content-Type: application/json');

ob_start();

function analyzeContract($userId) {
  global $conn;
  
  $contractId = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
  
  error_log("Analyzing contract ID: " . $contractId . " for user ID: " . $userId);
  
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
  
  try {
      if ($contract['file_type'] == 'text/plain') {
          if (file_exists($contract['file_path']) && is_readable($contract['file_path'])) {
              $contractContent = file_get_contents($contract['file_path']);
              if ($contractContent === false) {
                  throw new Exception("Failed to read contract file");
              }
          } else {
              throw new Exception("Contract file not found or not readable");
          }
      } elseif (strpos($contract['file_type'], 'image/') === 0) {
          $isImage = true;
          if (!file_exists($contract['file_path']) || !is_readable($contract['file_path'])) {
              throw new Exception("Image file not found or not readable");
          }
      } elseif (strpos($contract['file_type'], 'application/pdf') === 0 || $contract['file_type'] == 'application/pdf') {
          $contractContent = "PDF DOCUMENT: " . $contract['title'] . "\n\nThis is a PDF document that should be analyzed as a complete legal contract.";
      } else {
          $contractContent = "Document: " . $contract['title'] . "\n\nThis is a document that should be analyzed as a complete legal contract.";
      }
      
      $analysis = analyzeContractWithAI($contractContent, $contract['title'], $contract['file_path'], $isImage);
      
      error_log("Analysis results received for contract ID: " . $contractId);
      
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
          throw new Exception('Failed to save analysis: ' . $stmt->error);
      }
  } catch (Exception $e) {
      error_log("Error analyzing contract: " . $e->getMessage());
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
}

if (basename(__FILE__) == 'contract_api.php') {
    session_start();
    
    require_once 'config.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User not authenticated']);
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    try {
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
    } catch (Exception $e) {
        error_log("API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

ob_end_flush();
?>

