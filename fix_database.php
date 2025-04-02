<?php
require_once 'config.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
    echo "Access denied. You must be logged in as admin.";
    exit();
}

echo "<h1>SignSmart Database Fix Tool</h1>";

echo "<h2>Checking contract_analyses table...</h2>";

$tableExists = $conn->query("SHOW TABLES LIKE 'contract_analyses'")->num_rows > 0;

if (!$tableExists) {
    echo "<p>Creating contract_analyses table...</p>";
    
    $result = $conn->query("
        CREATE TABLE contract_analyses (
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
    
    if ($result) {
        echo "<p style='color:green'>Table created successfully!</p>";
    } else {
        echo "<p style='color:red'>Error creating table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Table exists, checking columns...</p>";
    
    $columnExists = $conn->query("SHOW COLUMNS FROM contract_analyses LIKE 'is_contract'")->num_rows > 0;
    
    if (!$columnExists) {
        echo "<p>Adding is_contract column...</p>";
        
        $result = $conn->query("ALTER TABLE contract_analyses ADD COLUMN is_contract BOOLEAN DEFAULT TRUE AFTER recommendation");
        
        if ($result) {
            echo "<p style='color:green'>Column added successfully!</p>";
        } else {
            echo "<p style='color:red'>Error adding column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:green'>is_contract column exists.</p>";
    }
}

echo "<h2>Checking for contracts with missing analysis...</h2>";

$result = $conn->query("
    SELECT c.id, c.title 
    FROM contracts c 
    LEFT JOIN contract_analyses ca ON c.id = ca.contract_id 
    WHERE c.status = 'analyzed' AND ca.id IS NULL
");

if ($result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " contracts with status 'analyzed' but no analysis data:</p>";
    echo "<ul>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<li>Contract ID: " . $row['id'] . ", Title: " . htmlspecialchars($row['title']) . "</li>";
    }
    
    echo "</ul>";
    
    echo "<p>Would you like to reset these contracts to 'pending' status? <a href='?fix_contracts=1'>Yes, fix them</a></p>";
    
    if (isset($_GET['fix_contracts']) && $_GET['fix_contracts'] == 1) {
        $updateResult = $conn->query("
            UPDATE contracts c 
            LEFT JOIN contract_analyses ca ON c.id = ca.contract_id 
            SET c.status = 'pending' 
            WHERE c.status = 'analyzed' AND ca.id IS NULL
        ");
        
        if ($updateResult) {
            echo "<p style='color:green'>Contracts reset to 'pending' status successfully!</p>";
        } else {
            echo "<p style='color:red'>Error resetting contracts: " . $conn->error . "</p>";
        }
    }
} else {
    echo "<p style='color:green'>No contracts with missing analysis found.</p>";
}

echo "<h2>Database check complete!</h2>";
echo "<p><a href='index.php'>Return to application</a></p>";
?>

