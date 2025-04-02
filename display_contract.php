<?php

function formatContractAnalysis($analysis) {
    $output = '';
    
    if (empty($analysis) || !is_array($analysis)) {
        return '<div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <p>No analysis data available. The contract may still be processing or an error occurred.</p>
        </div>';
    }
    
    if (!empty($analysis['summary'])) {
        $output .= '<div class="analysis-section">';
        $output .= '<h4><i class="fas fa-file-alt"></i> Summary</h4>';
        $output .= '<div class="analysis-content">';
        $output .= '<p>' . nl2br(htmlspecialchars($analysis['summary'])) . '</p>';
        $output .= '</div></div>';
    }

    if (isset($analysis['is_contract']) && $analysis['is_contract'] === false) {
        $output .= '<div class="analysis-section">';
        $output .= '<h4><i class="fas fa-exclamation-circle"></i> Not a Contract</h4>';
        $output .= '<div class="analysis-content">';
        $output .= '<p>This document does not appear to be a contract or legal document.</p>';
        $output .= '</div></div>';
    }
    
    if (!empty($analysis['key_terms'])) {
        $output .= '<div class="analysis-section">';
        $output .= '<h4><i class="fas fa-list"></i> Key Terms</h4>';
        $output .= '<div class="analysis-content">';
        $output .= '<ul class="key-terms-list">';
        
        $keyTerms = json_decode($analysis['key_terms'], true);
        
        if (is_array($keyTerms)) {
            foreach ($keyTerms as $term) {
                if (is_array($term) && isset($term['term']) && isset($term['explanation'])) {
                    $output .= '<li><strong>' . htmlspecialchars($term['term']) . ':</strong> ' . 
                               htmlspecialchars($term['explanation']) . '</li>';
                } elseif (is_array($term) && isset($term['item'])) {
                    $output .= '<li>' . htmlspecialchars($term['item']) . '</li>';
                } elseif (is_string($term)) {
                    $output .= '<li>' . htmlspecialchars($term) . '</li>';
                }
            }
        } else {
            $lines = explode("\n", $analysis['key_terms']);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    if (preg_match('/^(.+?):\s*(.+)$/', $line, $matches)) {
                        $output .= '<li><strong>' . htmlspecialchars($matches[1]) . ':</strong> ' . 
                                   htmlspecialchars($matches[2]) . '</li>';
                    } else {
                        $output .= '<li>' . htmlspecialchars($line) . '</li>';
                    }
                }
            }
        }
        
        $output .= '</ul>';
        $output .= '</div></div>';
    }
    
    if (!empty($analysis['unfavorable_clauses'])) {
        $output .= '<div class="analysis-section">';
        $output .= '<h4><i class="fas fa-exclamation-triangle"></i> Unfavorable Clauses</h4>';
        $output .= '<div class="analysis-content">';
        $output .= '<ul class="clause-list">';
        
        $unfavorableClauses = json_decode($analysis['unfavorable_clauses'], true);
        
        if (is_array($unfavorableClauses)) {
            foreach ($unfavorableClauses as $clause) {
                if (is_array($clause) && isset($clause['clause']) && isset($clause['risk'])) {
                    $output .= '<li class="unfavorable-clause"><strong>' . htmlspecialchars($clause['clause']) . ':</strong> ' . 
                               htmlspecialchars($clause['risk']) . '</li>';
                } elseif (is_array($clause) && isset($clause['term']) && isset($clause['explanation'])) {
                    $output .= '<li class="unfavorable-clause"><strong>' . htmlspecialchars($clause['term']) . ':</strong> ' . 
                               htmlspecialchars($clause['explanation']) . '</li>';
                } elseif (is_array($clause) && isset($clause['item'])) {
                    $output .= '<li class="unfavorable-clause">' . htmlspecialchars($clause['item']) . '</li>';
                } elseif (is_string($clause)) {
                    $output .= '<li class="unfavorable-clause">' . htmlspecialchars($clause) . '</li>';
                }
            }
        } else {
            $lines = explode("\n", $analysis['unfavorable_clauses']);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    if (preg_match('/^(.+?):\s*(.+)$/', $line, $matches)) {
                        $output .= '<li class="unfavorable-clause"><strong>' . htmlspecialchars($matches[1]) . ':</strong> ' . 
                                   htmlspecialchars($matches[2]) . '</li>';
                    } else {
                        $output .= '<li class="unfavorable-clause">' . htmlspecialchars($line) . '</li>';
                    }
                }
            }
        }
        
        $output .= '</ul>';
        $output .= '</div></div>';
    }
    
    if (!empty($analysis['risk_assessment'])) {
        $output .= '<div class="analysis-section">';
        $output .= '<h4><i class="fas fa-shield-alt"></i> Risk Assessment</h4>';
        $output .= '<div class="analysis-content">';
        $output .= '<p>' . nl2br(htmlspecialchars($analysis['risk_assessment'])) . '</p>';
        $output .= '</div></div>';
    }
    
    if (!empty($analysis['recommendation'])) {
        $recommendation = $analysis['recommendation'];
        $recommendationClass = 'caution-recommendation';
        $recommendationIcon = 'fa-exclamation-circle';
        
        $recommendationLower = strtolower($recommendation);
        if (strpos($recommendationLower, 'sign') !== false && strpos($recommendationLower, 'not sign') === false) {
            $recommendationClass = 'sign-recommendation';
            $recommendationIcon = 'fa-check-circle';
        } else if (strpos($recommendationLower, 'not sign') !== false || strpos($recommendationLower, 'reject') !== false) {
            $recommendationClass = 'reject-recommendation';
            $recommendationIcon = 'fa-times-circle';
        }
        
        $output .= '<div class="final-recommendation">';
        $output .= '<div class="recommendation-header ' . $recommendationClass . '">';
        $output .= '<i class="fas ' . $recommendationIcon . '"></i>';
        $output .= '<h4>Recommendation</h4>';
        $output .= '</div>';
        $output .= '<p>' . nl2br(htmlspecialchars($recommendation)) . '</p>';
        $output .= '</div>';
    }
    
    return $output;
}

if (isset($_POST['action']) && $_POST['action'] === 'format_analysis' && isset($_POST['analysis'])) {
    $analysis = json_decode($_POST['analysis'], true);
    echo formatContractAnalysis($analysis);
    exit;
}
?>

