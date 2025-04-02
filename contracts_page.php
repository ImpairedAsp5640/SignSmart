<?php
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT c.*, ca.summary, ca.recommendation 
    FROM contracts c 
    LEFT JOIN contract_analyses ca ON c.id = ca.contract_id 
    WHERE c.user_id = ? 
    ORDER BY c.updated_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$contracts = [];
while ($row = $result->fetch_assoc()) {
    $contracts[] = $row;
}
?>

<div class="page-header">
    <h1>My Contracts</h1>
    <button id="upload-contract-btn" class="primary-btn">
        <i class="fas fa-plus"></i> Upload New Contract
    </button>
</div>

<div class="contracts-container">
    <?php if (empty($contracts)): ?>
        <div class="empty-state">
            <i class="fas fa-file-contract"></i>
            <h2>No Contracts Yet</h2>
            <p>Upload your first contract to get started with AI-powered analysis</p>
            <button id="upload-first-contract-btn" class="primary-btn">Upload Contract</button>
        </div>
    <?php else: ?>
        <div class="contract-filters">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="contract-search" placeholder="Search contracts...">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="analyzed">Analyzed</button>
                <button class="filter-btn" data-filter="signed">Signed</button>
                <button class="filter-btn" data-filter="rejected">Rejected</button>
                <button class="filter-btn" data-filter="pending">Pending</button>
            </div>
        </div>
        
        <div class="contracts-grid">
            <?php foreach ($contracts as $contract): ?>
                <div class="contract-card" data-status="<?php echo $contract['status']; ?>">
                    <div class="contract-card-header">
                        <div class="file-icon">
                            <?php
                            $iconClass = 'fa-file-alt';
                            if (strpos($contract['file_type'], 'pdf') !== false) {
                                $iconClass = 'fa-file-pdf';
                            } elseif (strpos($contract['file_type'], 'doc') !== false) {
                                $iconClass = 'fa-file-word';
                            } elseif (strpos($contract['file_type'], 'image') !== false) {
                                $iconClass = 'fa-file-image';
                            }
                            ?>
                            <i class="fas <?php echo $iconClass; ?>"></i>
                        </div>
                        <div class="contract-status <?php echo $contract['status']; ?>">
                            <?php echo ucfirst($contract['status']); ?>
                        </div>
                    </div>
                    
                    <div class="contract-card-body">
                        <h3><?php echo htmlspecialchars($contract['title']); ?></h3>
                        <p class="contract-date">
                            <i class="fas fa-calendar-alt"></i> 
                            <?php echo date('M d, Y', strtotime($contract['created_at'])); ?>
                        </p>
                        
                        <?php if ($contract['status'] === 'analyzed'): ?>
                            <div class="contract-recommendation">
                                <?php if (strpos(strtolower($contract['recommendation']), 'sign') !== false): ?>
                                    <span class="recommendation positive">
                                        <i class="fas fa-check-circle"></i> Recommended to sign
                                    </span>
                                <?php else: ?>
                                    <span class="recommendation negative">
                                        <i class="fas fa-exclamation-circle"></i> Not recommended
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="contract-card-footer">
                        <button class="view-contract-btn" data-id="<?php echo $contract['id']; ?>">
                            <i class="fas fa-eye"></i> View
                        </button>
                        
                        <?php if ($contract['status'] === 'analyzed'): ?>
                            <div class="action-buttons">
                                <button class="sign-btn" data-id="<?php echo $contract['id']; ?>">
                                    <i class="fas fa-signature"></i> Sign
                                </button>
                                <button class="reject-btn" data-id="<?php echo $contract['id']; ?>">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        <?php elseif ($contract['status'] === 'pending'): ?>
                            <button class="analyze-btn" data-id="<?php echo $contract['id']; ?>">
                                <i class="fas fa-robot"></i> Analyze
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="contract-details-modal" class="modal">
    <div class="modal-content large-modal">
        <span class="close">&times;</span>
        <div id="contract-details-container">
        </div>
    </div>
</div>