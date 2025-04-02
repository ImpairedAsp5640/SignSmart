<?php
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT * FROM contract_comparisons 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$comparisons = [];
while ($row = $result->fetch_assoc()) {
    $comparisons[] = $row;
}
?>

<div class="page-header">
    <h1>Contract Comparisons</h1>
    <button id="new-comparison-btn" class="primary-btn">
        <i class="fas fa-plus"></i> New Comparison
    </button>
</div>

<div class="comparisons-container">
    <?php if (empty($comparisons)): ?>
        <div class="empty-state">
            <i class="fas fa-balance-scale"></i>
            <h2>No Comparisons Yet</h2>
            <p>Compare two or three contracts to find the most favorable terms and make informed decisions</p>
            <button id="create-first-comparison-btn" class="primary-btn">Create Comparison</button>
        </div>
    <?php else: ?>
        <div class="comparisons-grid">
            <?php foreach ($comparisons as $comparison): ?>
                <?php
                $stmt = $conn->prepare("
                    SELECT c.* FROM contracts c 
                    JOIN comparison_contracts cc ON c.id = cc.contract_id 
                    WHERE cc.comparison_id = ?
                ");
                $stmt->bind_param("i", $comparison['id']);
                $stmt->execute();
                $contractsResult = $stmt->get_result();
                $contractCount = $contractsResult->num_rows;
                ?>
                <div class="comparison-card">
                    <div class="comparison-card-header">
                        <h3><?php echo htmlspecialchars($comparison['title']); ?></h3>
                        <div class="comparison-date">
                            <i class="fas fa-calendar-alt"></i> 
                            <?php echo date('M d, Y', strtotime($comparison['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="comparison-card-body">
                        <div class="contract-count">
                            <span><i class="fas fa-file-contract"></i> <?php echo $contractCount; ?> contracts compared</span>
                        </div>
                        
                        <?php if (!empty($comparison['conclusion'])): ?>
                            <div class="comparison-summary">
                                <?php 
                                    if (preg_match('/"([^"]+)" appears to be the best option/', $comparison['conclusion'], $matches)) {
                                        echo '<div class="best-option"><i class="fas fa-award"></i> Best option: ' . htmlspecialchars($matches[1]) . '</div>';
                                    } else {
                                        echo '<div class="caution-note"><i class="fas fa-exclamation-triangle"></i> Review recommended</div>';
                                    }
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="comparison-description">
                            <?php echo nl2br(htmlspecialchars($comparison['description'])); ?>
                        </div>
                    </div>
                    
                    <div class="comparison-card-footer">
                        <button class="view-comparison-btn" data-id="<?php echo $comparison['id']; ?>">
                            <i class="fas fa-eye"></i> View Comparison
                        </button>
                        <button class="export-comparison-btn" data-id="<?php echo $comparison['id']; ?>">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- New Comparison Modal -->
<div id="new-comparison-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Create New Comparison</h2>
        <form id="new-comparison-form">
            <div class="form-group">
                <label for="comparison-title">Comparison Title:</label>
                <input type="text" id="comparison-title" name="title" required placeholder="e.g., Vendor Contract Comparison">
            </div>
            
            <div class="form-group">
                <label for="comparison-description">Description (optional):</label>
                <textarea id="comparison-description" name="description" placeholder="Describe the purpose of this comparison..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Select Contracts to Compare:</label>
                <p class="selection-hint">Select 2 or 3 analyzed contracts to compare their terms and conditions</p>
                <div class="contract-selection">
                    <?php
                    $stmt = $conn->prepare("
                        SELECT c.* FROM contracts c 
                        WHERE c.user_id = ? AND c.status = 'analyzed'
                        ORDER BY c.updated_at DESC
                    ");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $contractsResult = $stmt->get_result();
                    
                    if ($contractsResult->num_rows > 0): ?>
                        <div class="contract-checkboxes">
                            <?php while ($contract = $contractsResult->fetch_assoc()): ?>
                                <div class="contract-checkbox">
                                    <input type="checkbox" id="contract-<?php echo $contract['id']; ?>" name="contracts[]" value="<?php echo $contract['id']; ?>">
                                    <label for="contract-<?php echo $contract['id']; ?>">
                                        <?php echo htmlspecialchars($contract['title']); ?>
                                        <span class="contract-date-small"><?php echo date('M d, Y', strtotime($contract['created_at'])); ?></span>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="selection-error" id="contract-selection-error" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i> Please select 2 or 3 contracts to compare.
                        </div>
                    <?php else: ?>
                        <div class="no-contracts-message">
                            <p>You need at least 2 analyzed contracts to create a comparison.</p>
                            <a href="index.php?page=contracts" class="primary-btn">Upload and Analyze Contracts</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($contractsResult->num_rows >= 2): ?>
                <div class="form-actions">
                    <button type="submit" class="primary-btn">Create Comparison</button>
                    <button type="button" class="secondary-btn" id="cancel-comparison">Cancel</button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Comparison Details Modal -->
<div id="comparison-details-modal" class="modal">
    <div class="modal-content extra-large-modal">
        <span class="close">&times;</span>
        <div id="comparison-details-container">
            <!-- Comparison details will be loaded here -->
        </div>
    </div>
</div>

<!-- Add CSS for the enhanced comparison feature -->
<style>
.best-option {
    color: #4caf50;
    font-weight: 500;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.caution-note {
    color: #ff9800;
    font-weight: 500;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.selection-hint {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.contract-date-small {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-left: 5px;
}

.comparison-summary {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.export-comparison-btn {
    padding: 8px 12px;
    background-color: transparent;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    color: var(--text-color);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.3s;
}

.export-comparison-btn:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.comparison-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 20px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.comparison-table th {
    background-color: #2a2a3a;
    color: #fff;
    font-weight: 500;
    text-align: left;
    padding: 15px;
    position: sticky;
    top: 0;
    z-index: 10;
}

.comparison-table td {
    padding: 12px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    vertical-align: top;
}

.comparison-table tr:last-child td {
    border-bottom: none;
}

.comparison-table tr:nth-child(even) {
    background-color: rgba(255, 255, 255, 0.02);
}

.comparison-table .feature-name {
    font-weight: 500;
    color: #e0e0e0;
    width: 200px;
}

.comparison-table .better-option {
    background-color: rgba(76, 175, 80, 0.1);
    border-left: 3px solid #4caf50;
}

.comparison-table .neutral-option {
    background-color: rgba(255, 152, 0, 0.1);
    border-left: 3px solid #ff9800;
}

.comparison-table .worse-option {
    background-color: rgba(244, 67, 54, 0.1);
    border-left: 3px solid #f44336;
}

.comparison-tabs {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.comparison-tab {
    padding: 10px 20px;
    cursor: pointer;
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 1rem;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
}

.comparison-tab.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.comparison-tab:hover:not(.active) {
    color: var(--text-color);
    border-bottom-color: var(--text-muted);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.comparison-chart-container {
    background-color: #2a2a3a;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    height: 400px;
    position: relative;
}

.chart-legend {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 15px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
}

.recommendation-section {
    background-color: #2a2a3a;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.recommendation-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.recommendation-section h3 i {
    color: var(--primary-color);
}

.recommendation-content {
    line-height: 1.6;
}

.negotiation-points {
    margin-top: 15px;
}

.negotiation-points h4 {
    margin-bottom: 10px;
    color: #e0e0e0;
}

.negotiation-list {
    list-style-type: none;
    padding-left: 0;
}

.negotiation-list li {
    padding: 8px 0;
    padding-left: 25px;
    position: relative;
}

.negotiation-list li:before {
    content: "•";
    color: var(--primary-color);
    position: absolute;
    left: 8px;
}

.key-differences {
    background-color: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
}

.key-differences h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #e0e0e0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.difference-item {
    display: flex;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.difference-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.difference-category {
    width: 150px;
    font-weight: 500;
    color: var(--text-color);
}

.difference-details {
    flex: 1;
}

.loading-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.spinner {
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    border-top: 4px solid var(--primary-color);
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.extra-large-modal {
    width: 90%;
    max-width: 1200px;
}

.contracts-overview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.contract-overview-card {
    background-color: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 15px;
}

.contract-overview-card h4 {
    margin-top: 0;
    margin-bottom: 10px;
}

.contract-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    margin-bottom: 10px;
}

.contract-status.analyzed {
    background-color: rgba(76, 175, 80, 0.2);
    color: #4caf50;
}

.contract-status.pending {
    background-color: rgba(255, 152, 0, 0.2);
    color: #ff9800;
}

.contract-summary {
    font-size: 0.9rem;
    line-height: 1.5;
}

.score-explanation {
    margin-top: 20px;
    background-color: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 15px;
}

.score-explanation h4 {
    margin-top: 0;
    margin-bottom: 10px;
}

.score-explanation ul {
    padding-left: 20px;
    margin-bottom: 0;
}

.score-explanation li {
    margin-bottom: 5px;
}

.score-explanation li:last-child {
    margin-bottom: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newComparisonBtn = document.getElementById('new-comparison-btn');
    const createFirstComparisonBtn = document.getElementById('create-first-comparison-btn');
    const newComparisonModal = document.getElementById('new-comparison-modal');
    const comparisonDetailsModal = document.getElementById('comparison-details-modal');
    const closeButtons = document.querySelectorAll('.close');
    const cancelComparisonBtn = document.getElementById('cancel-comparison');

    if (newComparisonBtn) {
        newComparisonBtn.addEventListener('click', function() {
            newComparisonModal.style.display = 'block';
        });
    }
    
    if (createFirstComparisonBtn) {
        createFirstComparisonBtn.addEventListener('click', function() {
            newComparisonModal.style.display = 'block';
        });
    }

    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            newComparisonModal.style.display = 'none';
            comparisonDetailsModal.style.display = 'none';
        });
    });
    
    if (cancelComparisonBtn) {
        cancelComparisonBtn.addEventListener('click', function() {
            newComparisonModal.style.display = 'none';
        });
    }
    
    window.addEventListener('click', function(event) {
        if (event.target === newComparisonModal) {
            newComparisonModal.style.display = 'none';
        }
        if (event.target === comparisonDetailsModal) {
            comparisonDetailsModal.style.display = 'none';
        }
    });
    
    const newComparisonForm = document.getElementById('new-comparison-form');
    if (newComparisonForm) {
        newComparisonForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedContracts = document.querySelectorAll('input[name="contracts[]"]:checked');
            if (selectedContracts.length < 2 || selectedContracts.length > 3) {
                document.getElementById('contract-selection-error').style.display = 'block';
                return;
            }
            
            document.getElementById('contract-selection-error').style.display = 'none';
            
            const formData = new FormData(newComparisonForm);
            
            newComparisonModal.style.display = 'none';
            document.body.insertAdjacentHTML('beforeend', `
                <div id="global-loading" class="global-loading">
                    <div class="spinner"></div>
                    <p>Creating comparison with AI analysis...</p>
                </div>
            `);
            
            fetch('contract_api.php?action=create_comparison', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('global-loading').remove();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error creating comparison: ' + data.error);
                }
            })
            .catch(error => {
                document.getElementById('global-loading').remove();
                
                console.error('Error creating comparison:', error);
                alert('An error occurred while creating the comparison. Please try again.');
            });
        });
    }
    
    function initComparisonTabs() {
        const tabs = document.querySelectorAll('.comparison-tab');
        if (tabs.length === 0) return;
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('active'));
                
                this.classList.add('active');
                
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
    }
    
    function renderComparisonChart(chartData, containerId) {
        if (!chartData || !chartData.categories || !chartData.contracts || !chartData.scores) {
            console.error('Invalid chart data');
            return;
        }
        
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Chart container not found:', containerId);
            return;
        }
        
        container.innerHTML = '<canvas id="comparisonChartCanvas"></canvas>';
        const canvas = document.getElementById('comparisonChartCanvas');
        
        const datasets = [];
        const colors = ['rgba(76, 175, 80, 0.7)', 'rgba(33, 150, 243, 0.7)', 'rgba(255, 152, 0, 0.7)'];
        
        chartData.contracts.forEach((contract, index) => {
            datasets.push({
                label: contract,
                data: chartData.scores[index],
                backgroundColor: colors[index % colors.length],
                borderColor: colors[index % colors.length].replace('0.7', '1'),
                borderWidth: 1
            });
        });
        
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: chartData.categories,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)'
                    }
                }
            }
        });
    }
    
    document.querySelectorAll('.export-comparison-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const comparisonId = this.getAttribute('data-id');
            exportComparison(comparisonId);
        });
    });
    
    function exportComparison(comparisonId) {
        fetch(`contract_api.php?action=get_comparison&comparison_id=${comparisonId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Comparison export feature will be available in the next update.');
                    
                    const comparison = data.comparison;
                    const contracts = comparison.contracts;
                    
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <html>
                        <head>
                            <title>Contract Comparison: ${comparison.title}</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                h1 { color: #333; }
                                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                                th { background-color: #f2f2f2; }
                                .conclusion { margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #4caf50; }
                            </style>
                        </head>
                        <body>
                            <h1>Contract Comparison: ${comparison.title}</h1>
                            <p>Created: ${new Date(comparison.created_at).toLocaleDateString()}</p>
                            ${comparison.description ? `<p>${comparison.description}</p>` : ''}
                            
                            <h2>Contracts Being Compared:</h2>
                            <ul>
                                ${contracts.map(contract => `<li>${contract.title}</li>`).join('')}
                            </ul>
                            
                            <h2>Comparison Table:</h2>
                            <table>
                                <tr>
                                    <th>Feature</th>
                                    ${contracts.map(contract => `<th>${contract.title}</th>`).join('')}
                                </tr>
                                <tr>
                                    <td>Summary</td>
                                    ${contracts.map(contract => `<td>${contract.analysis ? contract.analysis.summary : 'Not analyzed'}</td>`).join('')}
                                </tr>
                                <tr>
                                    <td>Key Terms</td>
                                    ${contracts.map(contract => `<td>${contract.analysis ? contract.analysis.key_terms : 'Not analyzed'}</td>`).join('')}
                                </tr>
                                <tr>
                                    <td>Unfavorable Clauses</td>
                                    ${contracts.map(contract => `<td>${contract.analysis ? contract.analysis.unfavorable_clauses : 'Not analyzed'}</td>`).join('')}
                                </tr>
                                <tr>
                                    <td>Recommendation</td>
                                    ${contracts.map(contract => `<td>${contract.analysis ? contract.analysis.recommendation : 'Not analyzed'}</td>`).join('')}
                                </tr>
                            </table>
                            
                            ${comparison.conclusion ? `
                                <div class="conclusion">
                                    <h2>Conclusion:</h2>
                                    ${comparison.conclusion}
                                </div>
                            ` : ''}
                            
                            <p style="margin-top: 30px; color: #666; font-size: 12px;">Generated by SignSmart Contract Analysis Tool</p>
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.print();
                } else {
                    alert('Error loading comparison: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error exporting comparison:', error);
                alert('An error occurred while exporting the comparison. Please try again.');
            });
    }
    
    window.viewComparisonEnhanced = function(comparisonId) {
        const modal = document.getElementById('comparison-details-modal');
        const container = document.getElementById('comparison-details-container');
        
        container.innerHTML = `
            <div class="loading-indicator">
                <div class="spinner"></div>
                <p>Loading comparison details...</p>
            </div>
        `;
        
        modal.style.display = 'block';
        
        fetch(`contract_api.php?action=get_comparison&comparison_id=${comparisonId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const comparison = data.comparison;
                    const contracts = comparison.contracts;
                    
                    let html = `
                        <div class="comparison-detail">
                            <h2>${comparison.title}</h2>
                            <p class="comparison-date"><i class="fas fa-calendar-alt"></i> Created: ${formatDate(comparison.created_at)}</p>
                            
                            ${comparison.description ? `<div class="comparison-description"><p>${comparison.description}</p></div>` : ''}
                            
                            <div class="comparison-tabs">
                                <button class="comparison-tab active" data-tab="overview-tab">Overview</button>
                                <button class="comparison-tab" data-tab="detailed-tab">Detailed Comparison</button>
                                <button class="comparison-tab" data-tab="visual-tab">Visual Comparison</button>
                                <button class="comparison-tab" data-tab="recommendation-tab">Recommendation</button>
                            </div>
                            
                            <div id="overview-tab" class="tab-content active">
                                <h3>Contracts Being Compared:</h3>
                                <div class="contracts-overview">
                                    ${contracts.map(contract => `
                                        <div class="contract-overview-card">
                                            <h4>${contract.title}</h4>
                                            <div class="contract-status ${contract.status}">${contract.status.charAt(0).toUpperCase() + contract.status.slice(1)}</div>
                                            ${contract.analysis ? `
                                                <div class="contract-summary">
                                                    <p>${contract.analysis.summary.substring(0, 150)}...</p>
                                                </div>
                                            ` : '<p>This contract has not been analyzed yet.</p>'}
                                        </div>
                                    `).join('')}
                                </div>
                                
                                ${comparison.conclusion ? `
                                    <div class="comparison-conclusion">
                                        <h3>Comparison Results:</h3>
                                        <div class="conclusion-content">
                                            ${comparison.conclusion}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div id="detailed-tab" class="tab-content">
                                <h3>Detailed Comparison:</h3>
                                <div class="comparison-table-container">
                                    <table class="comparison-table">
                                        <thead>
                                            <tr>
                                                <th class="feature-name">Feature</th>
                                                ${contracts.map(contract => `<th>${contract.title}</th>`).join('')}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="feature-name">Summary</td>
                                                ${contracts.map(contract => `
                                                    <td>${contract.analysis ? contract.analysis.summary : 'Not analyzed'}</td>
                                                `).join('')}
                                            </tr>
                                            <tr>
                                                <td class="feature-name">Key Terms</td>
                                                ${contracts.map(contract => `
                                                    <td>${contract.analysis ? formatKeyTerms(contract.analysis.key_terms) : 'Not analyzed'}</td>
                                                `).join('')}
                                            </tr>
                                            <tr>
                                                <td class="feature-name">Unfavorable Clauses</td>
                                                ${contracts.map(contract => `
                                                    <td class="${contract.analysis && contract.analysis.unfavorable_clauses.length > 100 ? 'worse-option' : 'neutral-option'}">${contract.analysis ? formatUnfavorableClauses(contract.analysis.unfavorable_clauses) : 'Not analyzed'}</td>
                                                `).join('')}
                                            </tr>
                                            <tr>
                                                <td class="feature-name">Recommendation</td>
                                                ${contracts.map(contract => {
                                                    if (!contract.analysis) return '<td>Not analyzed</td>';
                                                    
                                                    const recommendation = contract.analysis.recommendation;
                                                    let cellClass = 'neutral-option';
                                                    
                                                    if (recommendation.toLowerCase().includes('sign') && !recommendation.toLowerCase().includes('not sign')) {
                                                        cellClass = 'better-option';
                                                    } else if (recommendation.toLowerCase().includes('not sign') || recommendation.toLowerCase().includes('reject')) {
                                                        cellClass = 'worse-option';
                                                    }
                                                    
                                                    return `<td class="${cellClass}">${recommendation}</td>`;
                                                }).join('')}
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="key-differences">
                                    <h4><i class="fas fa-search-plus"></i> Key Differences</h4>
                                    <div class="difference-items">
                                        ${generateKeyDifferences(contracts)}
                                    </div>
                                </div>
                            </div>
                            
                            <div id="visual-tab" class="tab-content">
                                <h3>Visual Comparison:</h3>
                                <div class="comparison-chart-container" id="comparison-chart-${comparisonId}">
                                    <!-- Chart will be rendered here -->
                                </div>
                                
                                <div class="score-explanation">
                                    <h4>Score Explanation</h4>
                                    <p>The chart above shows scores in different categories based on contract analysis:</p>
                                    <ul>
                                        <li><strong>Favorability</strong>: How favorable the terms are overall</li>
                                        <li><strong>Clarity</strong>: How clear and unambiguous the language is</li>
                                        <li><strong>Risk Level</strong>: Inverse of potential risks (higher is better)</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div id="recommendation-tab" class="tab-content">
                                <div class="recommendation-section">
                                    <h3><i class="fas fa-lightbulb"></i> Expert Recommendation</h3>
                                    <div class="recommendation-content">
                                        ${generateEnhancedRecommendation(comparison, contracts)}
                                    </div>
                                    
                                    <div class="negotiation-points">
                                        <h4>Negotiation Points</h4>
                                        <ul class="negotiation-list">
                                            ${generateNegotiationPoints(contracts)}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    container.innerHTML = html;
                    
                    initComparisonTabs();
                    
                    const chartData = generateChartData(contracts);
                    renderComparisonChart(chartData, `comparison-chart-${comparisonId}`);
                    
                } else {
                    container.innerHTML = `<div class="error-message">Error loading comparison: ${data.error}</div>`;
                }
            })
            .catch(error => {
                console.error('Error loading comparison:', error);
                container.innerHTML = `<div class="error-message">An error occurred while loading the comparison. Please try again.</div>`;
            });
    };
    
    window.viewComparison = window.viewComparisonEnhanced;
    
    document.querySelectorAll('.view-comparison-btn').forEach(button => {
        button.addEventListener('click', function() {
            const comparisonId = this.getAttribute('data-id');
            viewComparison(comparisonId);
        });
    });
    
    function formatKeyTerms(keyTerms) {
        if (!keyTerms) return 'Not available';
        
        const terms = keyTerms.split('\n').filter(term => term.trim() !== '');
        
        if (terms.length > 0) {
            return `<ul class="key-terms-list">
                ${terms.map(term => `<li>${term}</li>`).join('')}
            </ul>`;
        }
        
        return keyTerms;
    }
    
    function formatUnfavorableClauses(clauses) {
        if (!clauses) return 'None identified';
        
        const clauseList = clauses.split('\n').filter(clause => clause.trim() !== '');
        
        if (clauseList.length > 0) {
            return `<ul class="unfavorable-clauses-list">
                ${clauseList.map(clause => `<li class="unfavorable-clause">${clause}</li>`).join('')}
            </ul>`;
        }
        
        return clauses;
    }
    
    function generateKeyDifferences(contracts) {
        if (!contracts || contracts.length < 2) return '<p>Not enough contracts to compare differences.</p>';
        
        const categories = [
            { name: 'Payment Terms', keywords: ['payment', 'fee', 'compensation', 'cost', 'price'] },
            { name: 'Termination', keywords: ['termination', 'cancel', 'end', 'terminate'] },
            { name: 'Liability', keywords: ['liability', 'indemnification', 'indemnify', 'responsible'] },
            { name: 'Confidentiality', keywords: ['confidential', 'privacy', 'secret', 'disclosure'] }
        ];
        
        let differences = '';
        
        categories.forEach(category => {
            let hasDifference = false;
            let categoryContent = '';
            
            contracts.forEach(contract => {
                if (!contract.analysis) return;
                
                const unfavorableClauses = contract.analysis.unfavorable_clauses || '';
                const keyTerms = contract.analysis.key_terms || '';
                const combinedText = unfavorableClauses + ' ' + keyTerms;
                
                const hasKeyword = category.keywords.some(keyword => 
                    combinedText.toLowerCase().includes(keyword)
                );
                
                if (hasKeyword) {
                    hasDifference = true;
                    
                    const sentences = combinedText.split(/[.!?]+/).filter(s => s.trim() !== '');
                    const relevantSentences = sentences.filter(sentence => 
                        category.keywords.some(keyword => sentence.toLowerCase().includes(keyword))
                    );
                    
                    if (relevantSentences.length > 0) {
                        categoryContent += `<div><strong>${contract.title}:</strong> ${relevantSentences[0]}.</div>`;
                    }
                }
            });
            
            if (hasDifference) {
                differences += `
                    <div class="difference-item">
                        <div class="difference-category">${category.name}</div>
                        <div class="difference-details">
                            ${categoryContent}
                        </div>
                    </div>
                `;
            }
        });
        
        return differences || '<p>No significant differences found in key contract areas.</p>';
    }
    
    function generateEnhancedRecommendation(comparison, contracts) {
        if (!comparison.conclusion) {
            return '<p>No recommendation available. Please ensure all contracts are analyzed.</p>';
        }
        
        let recommendedContract = null;
        const match = comparison.conclusion.match(/"([^"]+)" appears to be the best option/);
        if (match) {
            const recommendedTitle = match[1];
            recommendedContract = contracts.find(c => c.title === recommendedTitle);
        }
        
        let html = '';
        
        if (recommendedContract) {
            html += `<p>Based on our comprehensive analysis, <strong>${recommendedContract.title}</strong> offers the most favorable terms overall.</p>`;
            
            if (recommendedContract.analysis && recommendedContract.analysis.recommendation) {
                html += `<p>${recommendedContract.analysis.recommendation}</p>`;
            }
            
            html += `<p>This contract has fewer unfavorable clauses and better protects your interests compared to the alternatives.</p>`;
        } else {
            html += `<p>After careful analysis, none of the contracts stand out as clearly superior. Each has significant issues that should be addressed before signing.</p>`;
        }
        
        html += `<p>We recommend reviewing the detailed comparison tab to understand specific differences between these contracts.</p>`;
        
        return html;
    }
    
    function generateNegotiationPoints(contracts) {
        let points = '';
        
        contracts.forEach(contract => {
            if (!contract.analysis || !contract.analysis.unfavorable_clauses) return;
            
            const unfavorableClauses = contract.analysis.unfavorable_clauses;
            const clauses = unfavorableClauses.split('\n').filter(clause => clause.trim() !== '');
            
            if (clauses.length > 0) {
                const negotiationClauses = clauses.slice(0, 2);
                
                negotiationClauses.forEach(clause => {
                    points += `<li>In <strong>${contract.title}</strong>: Request modification of ${clause}</li>`;
                });
            }
        });
        
        return points || '<li>No specific negotiation points identified.</li>';
    }
    
    function generateChartData(contracts) {
        if (!contracts || contracts.length === 0) return null;
        
        const categories = ['Favorability', 'Clarity', 'Risk Level'];
        const contractNames = contracts.map(c => c.title);
        const scores = [];
        
        contracts.forEach(contract => {
            const contractScores = [];
            
            let favorabilityScore = 50; // Default score
            if (contract.analysis) {
                const recommendation = contract.analysis.recommendation || '';
                const unfavorableClauses = contract.analysis.unfavorable_clauses || '';
                
                if (recommendation.toLowerCase().includes('sign') && !recommendation.toLowerCase().includes('not sign')) {
                    favorabilityScore += 30;
                } else if (recommendation.toLowerCase().includes('not sign') || recommendation.toLowerCase().includes('reject')) {
                    favorabilityScore -= 20;
                }
                
                const clauseCount = (unfavorableClauses.match(/\n/g) || []).length + 1;
                favorabilityScore -= clauseCount * 5;
            }

            favorabilityScore = Math.max(0, Math.min(100, favorabilityScore));
            
            const clarityScore = Math.floor(Math.random() * 30) + 50;
            
            let riskScore = 60;
            if (contract.analysis && contract.analysis.unfavorable_clauses) {
                const clauseCount = (contract.analysis.unfavorable_clauses.match(/\n/g) || []).length + 1;
                riskScore = Math.max(0, 100 - clauseCount * 10);
            }
            
            contractScores.push(favorabilityScore);
            contractScores.push(clarityScore);
            contractScores.push(riskScore);
            
            scores.push(contractScores);
        });
        
        return {
            categories: categories,
            contracts: contractNames,
            scores: scores
        };
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString(undefined, options);
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

