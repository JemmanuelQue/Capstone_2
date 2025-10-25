<?php
session_start();
require_once __DIR__ . '/../../includes/session_check.php';
require_once '../../db_connection.php';

// Enforce OIC role (8)
if (!validateSession($conn, 8)) {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

// Get guard ID from parameter
$guardId = isset($_GET['guard_id']) ? (int)$_GET['guard_id'] : 0;

if (!$guardId) {
    echo '<div class="alert alert-danger">Invalid guard ID</div>';
    exit;
}

// Get current OIC user's info and verify permissions
$oicStmt = $conn->prepare("SELECT User_ID, First_Name, Last_Name FROM users WHERE Role_ID = 8 AND status = 'Active' AND User_ID = ?");
$oicStmt->execute([$_SESSION['user_id']]);
$oicData = $oicStmt->fetch(PDO::FETCH_ASSOC);

if (!$oicData) {
    echo '<div class="alert alert-danger">OIC not found</div>';
    exit;
}

// Get OIC's assigned locations and verify guard access
$oicLocationsQuery = "SELECT location_name FROM oic_locations WHERE oic_user_id = ? AND is_active = 1";
$oicLocationsStmt = $conn->prepare($oicLocationsQuery);
$oicLocationsStmt->execute([$_SESSION['user_id']]);
$oicLocations = $oicLocationsStmt->fetchAll(PDO::FETCH_COLUMN);

// Get guard information
$guardQuery = "
    SELECT u.User_ID, u.employee_id, u.First_Name, u.Last_Name, u.middle_name, 
           u.hired_date, u.Created_At, gl.location_name
    FROM users u
    INNER JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_active = 1
    WHERE u.Role_ID = 5 AND u.status = 'Active' AND u.User_ID = ?
";
$guardStmt = $conn->prepare($guardQuery);
$guardStmt->execute([$guardId]);
$guard = $guardStmt->fetch(PDO::FETCH_ASSOC);

if (!$guard || !in_array($guard['location_name'], $oicLocations)) {
    echo '<div class="alert alert-danger">You are not authorized to view this guard\'s evaluations</div>';
    exit;
}

// Get all evaluations for this guard
$evaluationsQuery = "
    SELECT 
        pe.evaluation_id,
        pe.evaluation_date,
        pe.overall_rating,
        pe.overall_performance,
        pe.recommendation,
        pe.status,
        pe.evaluated_by,
        pe.client_representative,
        pe.gmsai_representative,
        pe.employee_name,
        pe.position,
        pe.area_assigned,
        pe.created_at,
        CONCAT(evaluator.First_Name, ' ', evaluator.Last_Name) as evaluator_name,
        evaluator.Role_ID as evaluator_role
    FROM performance_evaluations pe
    LEFT JOIN users evaluator ON pe.evaluator_id = evaluator.User_ID
    WHERE pe.user_id = ? AND pe.status = 'Completed'
    ORDER BY pe.evaluation_date DESC, pe.created_at DESC
";

$evaluationsStmt = $conn->prepare($evaluationsQuery);
$evaluationsStmt->execute([$guardId]);
$allEvaluations = $evaluationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get latest evaluation
$latestEvaluation = !empty($allEvaluations) ? $allEvaluations[0] : null;

// Fetch detailed ratings for the latest evaluation (if any)
$latestRatings = [];
if ($latestEvaluation && !empty($latestEvaluation['evaluation_id'])) {
    $ratingsStmt = $conn->prepare("SELECT criterion_name, rating_score, comments FROM evaluation_ratings WHERE evaluation_id = ? ORDER BY rating_id ASC");
    $ratingsStmt->execute([$latestEvaluation['evaluation_id']]);
    $latestRatings = $ratingsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$guardName = trim($guard['First_Name'] . ' ' . $guard['middle_name'] . ' ' . $guard['Last_Name']);

// Calculate employment status
$hiredDate = $guard['hired_date'] ?: $guard['Created_At'];
$hiredDateTime = new DateTime($hiredDate);
$currentDateTime = new DateTime();
$interval = $hiredDateTime->diff($currentDateTime);
$monthsDiff = ($interval->y * 12) + $interval->m;
$employmentStatus = $monthsDiff >= 6 ? 'Regular' : 'Probationary';

// Function to get rating description for percentage-based ratings
function getRatingDescription($rating) {
    if ($rating >= 90) return 'Excellent';
    if ($rating >= 80) return 'Good';
    if ($rating >= 70) return 'Fair';
    return 'Poor';
}

// Function to get rating color for percentage-based ratings
function getRatingColor($rating) {
    if ($rating >= 90) return 'success';
    if ($rating >= 80) return 'info';
    if ($rating >= 70) return 'warning';
    return 'danger';
}
?>

<style>
    .evaluation-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
        padding: 30px;
        margin-bottom: 20px;
    }
    .guard-info-card {
        background: linear-gradient(135deg, #2a7d4f, #3a9d6f);
        color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
    }
    .rating-display {
        font-size: 3rem;
        font-weight: bold;
    }
    .section-title {
        color: #2a7d4f;
        font-weight: 600;
        margin-bottom: 20px;
        font-size: 1.2rem;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 10px;
    }
    .evaluation-section {
        margin-bottom: 30px;
    }
    .no-evaluation {
        text-align: center;
        padding: 50px;
        color: #6c757d;
    }
    /* Horizontal scroll helpers for tight mobile screens */
    .mobile-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .mobile-scroll-inner { min-width: 520px; }
    
    /* Mobile responsive table - Show all details */
    @media (max-width: 768px) {
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Ensure all table columns are visible on mobile */
        .table th, .table td {
            white-space: nowrap;
            min-width: 100px;
            font-size: 0.85rem;
        }
        
        /* Make table scrollable horizontally to show all data */
        .table {
            min-width: 800px; /* Ensure minimum width to show all columns */
        }
        
        .filter-controls .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        
        /* Adjust card padding for mobile */
        .evaluation-card {
            padding: 20px;
        }
        
        /* Make rating display smaller on mobile */
        .rating-display {
            font-size: 2rem;
        }
        
        /* Stack evaluation details vertically on mobile */
        .evaluation-section {
            margin-bottom: 20px;
        }
    }
    
    /* For very small screens */
    @media (max-width: 576px) {
        .table th, .table td {
            font-size: 0.75rem;
            min-width: 80px;
            padding: 0.5rem 0.25rem;
        }
        
        .evaluation-card {
            padding: 15px;
        }
        
        .rating-display {
            font-size: 1.8rem;
        }
    }
</style>

<!-- Guard Information Card -->
<div class="guard-info-card">
    <div class="row">
        <div class="col-md-8">
            <h3><?php echo htmlspecialchars($guardName); ?></h3>
            <p class="mb-1"><strong>Employee ID:</strong> <?php echo htmlspecialchars($guard['employee_id'] ?: sprintf("EMP%04d", $guard['User_ID'])); ?></p>
            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($guard['location_name'] ?: 'Not Assigned'); ?></p>
            <p class="mb-1"><strong>Hired Date:</strong> <?php echo date('M d, Y', strtotime($hiredDate)); ?></p>
            <p class="mb-1"><strong>Total Evaluations:</strong> <?php echo count($allEvaluations); ?></p>
        </div>
        <div class="col-md-4 text-end">
            <span class="badge bg-light text-dark fs-6 mb-2">
                <?php echo $employmentStatus; ?> Employee
            </span>
            <?php if ($latestEvaluation && $latestEvaluation['evaluation_date']): ?>
                <br>
                <span class="badge bg-success fs-6">
                    Last Evaluated: <?php echo date('M d, Y', strtotime($latestEvaluation['evaluation_date'])); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($latestEvaluation): ?>
    <!-- Latest Evaluation Details -->
    <div class="evaluation-card">
        <div class="section-title">
            <i class="material-icons">assessment</i> Latest Performance Evaluation
        </div>
        
        <!-- Evaluation Summary -->
        <div class="row mb-4">
            <div class="col-md-3 text-center">
                <div class="rating-display text-<?php echo getRatingColor($latestEvaluation['overall_rating']); ?>">
                    <?php echo number_format($latestEvaluation['overall_rating'], 1); ?>%
                </div>
                <div class="fs-5 text-<?php echo getRatingColor($latestEvaluation['overall_rating']); ?>">
                    <?php echo getRatingDescription($latestEvaluation['overall_rating']); ?>
                </div>
                <small class="text-muted">Overall Rating</small>
            </div>
            <div class="col-md-3 text-center">
                <div class="rating-display text-info">
                    <?php echo count($latestRatings); ?>
                </div>
                <div class="fs-5 text-info">Criteria Rated</div>
                <small class="text-muted">Total Evaluated</small>
            </div>
            <div class="col-md-6">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Evaluation Date:</strong></td>
                            <td><?php echo date('M d, Y', strtotime($latestEvaluation['evaluation_date'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Employee Name:</strong></td>
                            <td><?php echo htmlspecialchars($latestEvaluation['employee_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Position:</strong></td>
                            <td><?php echo htmlspecialchars($latestEvaluation['position'] ?: 'Security Guard'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class="badge bg-success"><?php echo $latestEvaluation['status']; ?></span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Evaluation Details -->
        <div class="row">
            <div class="col-md-4">
                <div class="evaluation-section">
                    <h5 class="text-success">
                        <i class="material-icons">location_on</i> Area Assignment
                    </h5>
                    <div class="border-start border-success border-3 ps-3">
                        <?php echo $latestEvaluation['area_assigned'] ? htmlspecialchars($latestEvaluation['area_assigned']) : '<em class="text-muted">No area assignment recorded</em>'; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="evaluation-section">
                    <h5 class="text-warning">
                        <i class="material-icons">trending_up</i> Recommendation
                    </h5>
                    <div class="border-start border-warning border-3 ps-3">
                        <?php 
                        if ($latestEvaluation['recommendation']) {
                            echo '<span class="badge bg-' . ($latestEvaluation['recommendation'] == 'renewal' ? 'success' : ($latestEvaluation['recommendation'] == 'termination' ? 'danger' : 'info')) . '">';
                            echo ucfirst($latestEvaluation['recommendation']);
                            echo '</span>';
                        } else {
                            echo '<em class="text-muted">No recommendation recorded</em>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="evaluation-section">
                    <h5 class="text-primary">
                        <i class="material-icons">person</i> Evaluated By
                    </h5>
                    <div class="border-start border-primary border-3 ps-3 mobile-scroll">
                        <div class="mobile-scroll-inner">
                            <?php echo $latestEvaluation['evaluated_by'] ? htmlspecialchars($latestEvaluation['evaluated_by']) : '<em class="text-muted">No evaluator recorded</em>'; ?>
                            <?php if ($latestEvaluation['client_representative']): ?>
                                <br><small><strong>Client Rep:</strong> <?php echo htmlspecialchars($latestEvaluation['client_representative']); ?></small>
                            <?php endif; ?>
                            <?php if ($latestEvaluation['gmsai_representative']): ?>
                                <br><small><strong>GMSAI Rep:</strong> <?php echo htmlspecialchars($latestEvaluation['gmsai_representative']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Criteria Breakdown -->
        <div class="row">
            <div class="col-12">
                <div class="evaluation-section">
                    <h5 class="text-secondary">
                        <i class="material-icons">list</i> Criteria Breakdown
                    </h5>
                    <?php if (!empty($latestRatings)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:280px;">Criterion</th>
                                        <th style="width:140px;">Score</th>
                                        <th>Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latestRatings as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['criterion_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getRatingColor((float)$r['rating_score']); ?>">
                                                    <?php echo number_format((float)$r['rating_score'], 1); ?>%
                                                </span>
                                            </td>
                                            <td><?php echo $r['comments'] ? htmlspecialchars($r['comments']) : '<em class="text-muted">—</em>'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="border-start border-secondary border-3 ps-3">
                            <em class="text-muted">No detailed criterion ratings recorded for this evaluation.</em>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Trend Chart - Show even with single evaluation -->
    <div class="evaluation-card">
        <div class="section-title">
            <i class="material-icons">trending_up</i> Performance Trend
        </div>
        <div class="row">
            <div class="col-md-8">
                <canvas id="performanceChart" width="400" height="200"></canvas>
            </div>
            <div class="col-md-4">
                <h6>Performance Summary</h6>
                <ul class="list-unstyled">
                    <li><strong>Total Evaluations:</strong> <?php echo count($allEvaluations); ?></li>
                    <li><strong>Average Rating:</strong> 
                        <?php 
                            $overallRatings = array_column($allEvaluations, 'overall_rating');
                            $avgRating = count($overallRatings) ? array_sum($overallRatings) / count($overallRatings) : 0;
                            echo number_format($avgRating, 2) . '%';
                        ?>
                    </li>
                    <li><strong>Latest Performance:</strong> 
                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['overall_rating']); ?>">
                            <?php echo getRatingDescription($latestEvaluation['overall_rating']); ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Evaluation History -->
    <div class="evaluation-card">
        <div class="section-title">
            <i class="material-icons">history</i> Evaluation History
        </div>
        
        <!-- Filter Controls - Make responsive -->
        <div class="row mb-3 filter-controls">
            <div class="col-6">
                <button class="btn btn-outline-primary btn-sm w-100" onclick="filterEvaluations('all')">
                    <i class="material-icons">filter_list</i> All
                </button>
            </div>
            <div class="col-6">
                <button class="btn btn-outline-secondary btn-sm w-100" onclick="resetFilters()">
                    <i class="material-icons">refresh</i> Reset
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover" id="evaluationHistoryTable">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Rating</th>
                        <th>Performance</th>
                        <th>Area Assigned</th>
                        <th>Status</th>
                        <th>Evaluator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allEvaluations as $eval): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($eval['evaluation_date'])); ?></td>
                            <td><?php echo htmlspecialchars($eval['employee_name']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo getRatingColor($eval['overall_rating']); ?>">
                                    <?php echo number_format($eval['overall_rating'], 1); ?>%
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo getRatingColor($eval['overall_rating']); ?>">
                                    <?php echo getRatingDescription($eval['overall_rating']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($eval['area_assigned'] ?: 'N/A'); ?></td>
                            <td><span class="badge bg-success"><?php echo $eval['status']; ?></span></td>
                            <td>
                                <?php echo htmlspecialchars($eval['evaluator_name'] ?: $eval['evaluated_by']); ?>
                                <?php if ($eval['evaluator_role']): ?>
                                    <span class="badge bg-info ms-1">
                                        <?php 
                                        switch($eval['evaluator_role']) {
                                            case 3: echo 'HR'; break;
                                            case 8: echo 'OIC'; break;
                                            default: echo 'Staff'; break;
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- No Evaluation Found -->
    <div class="evaluation-card">
        <div class="no-evaluation">
            <i class="material-icons" style="font-size: 4rem; color: #dee2e6;">assignment</i>
            <h4 class="mt-3">No Performance Evaluation Found</h4>
            <p>This guard has not been evaluated yet.</p>
        </div>
    </div>
<?php endif; ?>

<script>
// Performance Trend Chart - Initialize immediately since content is loaded via AJAX
window.initializePerformanceChart = function() {
    const ctx = document.getElementById('performanceChart');
    if (ctx && typeof Chart !== 'undefined') {
        // Destroy existing chart if it exists
        if (window.performanceChartInstance) {
            window.performanceChartInstance.destroy();
        }
        
        const chartCtx = ctx.getContext('2d');
        const evaluations = <?php echo json_encode($allEvaluations); ?>;
        
        let labels, ratingData;
        
        if (evaluations.length === 1) {
            // For single evaluation, show a point chart with trend line
            const evalDate = new Date(evaluations[0].evaluation_date);
            labels = [evalDate.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })];
            ratingData = [parseFloat(evaluations[0].overall_rating)];
        } else {
            // For multiple evaluations, show the trend
            labels = evaluations.map(eval => {
                const date = new Date(eval.evaluation_date);
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            }).reverse();
            
            ratingData = evaluations.map(eval => parseFloat(eval.overall_rating)).reverse();
        }
        
        window.performanceChartInstance = new Chart(chartCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Overall Rating (%)',
                    data: ratingData,
                    borderColor: '#2a7d4f',
                    backgroundColor: 'rgba(42, 125, 79, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#2a7d4f',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        min: 60,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Overall Rating (%)'
                        },
                        ticks: {
                            stepSize: 10
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Evaluation Period'
                        }
                    }
                }
            }
        });
    }
};

// Initialize chart immediately with delay
setTimeout(function() {
    if (typeof window.initializePerformanceChart === 'function') {
        window.initializePerformanceChart();
    }
}, 100);

// Filter functions for mobile responsiveness
function filterEvaluations(filter) {
    const table = document.getElementById('evaluationHistoryTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        if (filter === 'all') {
            rows[i].style.display = '';
        }
        // Add more filter logic as needed
    }
}

function resetFilters() {
    filterEvaluations('all');
}

function viewEvaluationDetails(evaluationId) {
    // For now, show an alert. You can implement a detailed view later
    Swal.fire({
        icon: 'info',
        title: 'Feature Coming Soon',
        text: 'Detailed evaluation view will be available soon.',
        confirmButtonColor: '#2a7d4f'
    });
}
</script>