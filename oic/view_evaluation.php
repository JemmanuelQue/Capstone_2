<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

// Enforce OIC role (8)
if (!validateSession($conn, 8)) { exit; }

$guard_id = $_GET['guard_id'] ?? null;

if (!$guard_id) {
    header('Location: dashboard.php?error=invalid_guard');
    exit;
}

// Get current OIC user's info
$oicStmt = $conn->prepare("SELECT User_ID, First_Name, Last_Name FROM users WHERE Role_ID = 8 AND status = 'Active' AND User_ID = ?");
$oicStmt->execute([$_SESSION['user_id']]);
$oicData = $oicStmt->fetch(PDO::FETCH_ASSOC);

if (!$oicData) {
    header('Location: ../logout.php');
    exit;
}

$oicName = $oicData['First_Name'] . ' ' . $oicData['Last_Name'];

// Get OIC's assigned locations
$oicLocationsQuery = "SELECT location_name FROM oic_locations WHERE oic_user_id = ? AND is_active = 1";
$oicLocationsStmt = $conn->prepare($oicLocationsQuery);
$oicLocationsStmt->execute([$_SESSION['user_id']]);
$oicLocations = $oicLocationsStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($oicLocations)) {
    header('Location: dashboard.php?error=no_locations');
    exit;
}

// Get guard information and verify they are in OIC's assigned location
$guardQuery = "
    SELECT 
        u.User_ID, u.employee_id, u.First_Name, u.Last_Name, u.middle_name,
        u.hired_date, u.Created_At, gl.location_name, u.phone_number, u.sex, u.civil_status
    FROM users u
    INNER JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_active = 1
    WHERE u.User_ID = ? AND u.Role_ID = 5 AND u.status = 'Active'
";

$guardStmt = $conn->prepare($guardQuery);
$guardStmt->execute([$guard_id]);
$guard = $guardStmt->fetch(PDO::FETCH_ASSOC);

if (!$guard) {
    header('Location: dashboard.php?error=guard_not_found');
    exit;
}

// Verify the guard is in one of the OIC's assigned locations
if (!in_array($guard['location_name'], $oicLocations)) {
    header('Location: dashboard.php?error=unauthorized_location');
    exit;
}

$guardName = trim($guard['First_Name'] . ' ' . $guard['middle_name'] . ' ' . $guard['Last_Name']);
$hiredDate = $guard['hired_date'] ?: $guard['Created_At'];

// Function to determine employment status
function getEmploymentStatus($hiredDate) {
    if (!$hiredDate) return 'Unknown';
    
    $hiredDateTime = new DateTime($hiredDate);
    $currentDateTime = new DateTime();
    $interval = $hiredDateTime->diff($currentDateTime);
    $monthsDiff = ($interval->y * 12) + $interval->m;
    
    return $monthsDiff >= 6 ? 'Regular' : 'Probationary';
}

$employmentStatus = getEmploymentStatus($hiredDate);

// Get all evaluations for this guard (by any evaluator, but focus on OIC's evaluations)
$allEvaluationsQuery = "
    SELECT pe.*, u.First_Name as evaluator_first, u.Last_Name as evaluator_last, u.Role_ID as evaluator_role
    FROM performance_evaluations pe
    LEFT JOIN users u ON pe.evaluator_id = u.User_ID
    WHERE pe.user_id = ? AND pe.status = 'Completed'
    ORDER BY pe.evaluation_date DESC
";
$evaluationsStmt = $conn->prepare($allEvaluationsQuery);
$evaluationsStmt->execute([$guard_id]);
$allEvaluations = $evaluationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get the latest evaluation for detailed view
$latestEvaluation = $allEvaluations[0] ?? null;

// Function to get rating description
function getRatingDescription($rating) {
    if ($rating >= 90) return 'Outstanding';
    if ($rating >= 85) return 'Good';
    if ($rating >= 80) return 'Fair';
    if ($rating >= 75) return 'Needs Improvement';
    return 'Poor';
}

// Function to get rating color
function getRatingColor($rating) {
    if ($rating >= 90) return 'success';
    if ($rating >= 85) return 'info';
    if ($rating >= 80) return 'primary';
    if ($rating >= 75) return 'warning';
    return 'danger';
}

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Evaluations - <?php echo htmlspecialchars($guardName); ?> - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js for performance trends -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/oic_dashboard.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .main-content { margin-left: 0; }
        .evaluation-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .guard-info-card { background: linear-gradient(135deg, #2a7d4f, #20c997); color: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .rating-badge { font-size: 1.1em; font-weight: 600; padding: 8px 16px; border-radius: 20px; }
        .criteria-section { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .criteria-title { color: #2a7d4f; font-weight: 600; border-bottom: 2px solid #2a7d4f; padding-bottom: 5px; margin-bottom: 15px; }
        .criteria-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #e9ecef; }
        .criteria-item:last-child { border-bottom: none; }
        .no-evaluations { text-align: center; padding: 40px; color: #6c757d; }
        .trend-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; height: 400px; }
        
        @media (max-width: 768px) {
            .criteria-item { flex-direction: column; align-items: flex-start; }
            .criteria-item .rating-info { margin-top: 5px; }
        }
    </style>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Performance Evaluations</h1>
                <p class="text-muted mb-0">View evaluation history and performance trends</p>
            </div>
            <div>
                <a href="performance_evaluation.php?guard_id=<?php echo $guard_id; ?>" class="btn btn-primary me-2">
                    <i class="material-icons" style="vertical-align: middle;">assessment</i> New Evaluation
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="material-icons" style="vertical-align: middle;">arrow_back</i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Guard Information Card -->
        <div class="guard-info-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-2">
                        <i class="material-icons" style="vertical-align: middle;">person</i>
                        <?php echo htmlspecialchars($guardName); ?>
                    </h4>
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-1"><strong>Employee ID:</strong> <?php echo htmlspecialchars($guard['employee_id'] ?: 'N/A'); ?></p>
                            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($guard['location_name']); ?></p>
                            <p class="mb-0"><strong>Employment Status:</strong> <?php echo $employmentStatus; ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-1"><strong>Hired Date:</strong> <?php echo $hiredDate ? date('M d, Y', strtotime($hiredDate)) : 'N/A'; ?></p>
                            <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($guard['phone_number'] ?: 'N/A'); ?></p>
                            <p class="mb-0"><strong>Total Evaluations:</strong> <?php echo count($allEvaluations); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if ($latestEvaluation): ?>
                        <div class="text-center">
                            <div class="display-6 mb-1"><?php echo number_format($latestEvaluation['overall_rating'], 1); ?>%</div>
                            <div class="small">Latest Rating</div>
                            <div class="badge bg-<?php echo getRatingColor($latestEvaluation['overall_rating']); ?> mt-1">
                                <?php echo getRatingDescription($latestEvaluation['overall_rating']); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <div class="text-muted">No evaluations yet</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (empty($allEvaluations)): ?>
            <div class="evaluation-card">
                <div class="no-evaluations">
                    <i class="material-icons" style="font-size: 4rem; color: #ddd;">assignment</i>
                    <h4>No Evaluations Found</h4>
                    <p>This guard has not been evaluated yet.</p>
                    <a href="performance_evaluation.php?guard_id=<?php echo $guard_id; ?>" class="btn btn-primary">
                        <i class="material-icons" style="vertical-align: middle;">add</i> Create First Evaluation
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Performance Trend Chart -->
                <div class="col-md-6">
                    <div class="trend-card">
                        <h5 class="mb-3">
                            <i class="material-icons" style="vertical-align: middle;">trending_up</i>
                            Performance Trend
                        </h5>
                        <canvas id="performanceTrendChart"></canvas>
                    </div>
                </div>

                <!-- Latest Evaluation Summary -->
                <div class="col-md-6">
                    <?php if ($latestEvaluation): ?>
                        <div class="evaluation-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="material-icons" style="vertical-align: middle;">assignment_turned_in</i>
                                    Latest Evaluation
                                </h5>
                                <span class="badge bg-<?php echo getRatingColor($latestEvaluation['overall_rating']); ?> rating-badge">
                                    <?php echo number_format($latestEvaluation['overall_rating'], 1); ?>% - <?php echo getRatingDescription($latestEvaluation['overall_rating']); ?>
                                </span>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Date:</small><br>
                                    <strong><?php echo date('M d, Y', strtotime($latestEvaluation['evaluation_date'])); ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Evaluator:</small><br>
                                    <strong><?php echo htmlspecialchars($latestEvaluation['evaluator_first'] . ' ' . $latestEvaluation['evaluator_last']); ?></strong>
                                    <?php if ($latestEvaluation['evaluator_role'] == 8): ?>
                                        <span class="badge bg-info ms-1">OIC</span>
                                    <?php elseif ($latestEvaluation['evaluator_role'] == 3): ?>
                                        <span class="badge bg-primary ms-1">HR</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($latestEvaluation['recommendation']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Recommendation:</small><br>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($latestEvaluation['recommendation']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($latestEvaluation['overall_strengths']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Strengths:</small><br>
                                    <p class="mb-0"><?php echo htmlspecialchars($latestEvaluation['overall_strengths']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($latestEvaluation['overall_improvements']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Areas for Improvement:</small><br>
                                    <p class="mb-0"><?php echo htmlspecialchars($latestEvaluation['overall_improvements']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detailed Latest Evaluation -->
            <?php if ($latestEvaluation): ?>
                <div class="evaluation-card">
                    <h5 class="mb-4">
                        <i class="material-icons" style="vertical-align: middle;">visibility</i>
                        Detailed Evaluation Breakdown
                    </h5>

                    <div class="row">
                        <div class="col-md-6">
                            <!-- Technical Skills -->
                            <div class="criteria-section">
                                <h6 class="criteria-title">Technical Skills</h6>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Job Knowledge</strong>
                                        <?php if ($latestEvaluation['tech_job_knowledge_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['tech_job_knowledge_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['tech_job_knowledge']); ?>">
                                            <?php echo $latestEvaluation['tech_job_knowledge']; ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Tool Competency</strong>
                                        <?php if ($latestEvaluation['tech_tool_competency_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['tech_tool_competency_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['tech_tool_competency']); ?>">
                                            <?php echo $latestEvaluation['tech_tool_competency']; ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Safety Procedures</strong>
                                        <?php if ($latestEvaluation['tech_safety_procedure_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['tech_safety_procedure_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['tech_safety_procedure']); ?>">
                                            <?php echo $latestEvaluation['tech_safety_procedure']; ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quality -->
                            <div class="criteria-section">
                                <h6 class="criteria-title">Quality</h6>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Accuracy</strong>
                                        <?php if ($latestEvaluation['quality_accuracy_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['quality_accuracy_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['quality_accuracy']); ?>">
                                            <?php echo $latestEvaluation['quality_accuracy']; ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Completeness</strong>
                                        <?php if ($latestEvaluation['quality_completeness_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['quality_completeness_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['quality_completeness']); ?>">
                                            <?php echo $latestEvaluation['quality_completeness']; ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Reliability</strong>
                                        <?php if ($latestEvaluation['quality_reliability_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['quality_reliability_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['quality_reliability']); ?>">
                                            <?php echo $latestEvaluation['quality_reliability']; ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <!-- Productivity -->
                            <div class="criteria-section">
                                <h6 class="criteria-title">Productivity</h6>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Work Output</strong>
                                        <?php if ($latestEvaluation['productivity_output_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['productivity_output_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['productivity_output']); ?>">
                                            <?php echo $latestEvaluation['productivity_output']; ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Time Management</strong>
                                        <?php if ($latestEvaluation['productivity_time_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['productivity_time_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['productivity_time']); ?>">
                                            <?php echo $latestEvaluation['productivity_time']; ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance -->
                            <div class="criteria-section">
                                <h6 class="criteria-title">Attendance & Punctuality</h6>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Attendance</strong>
                                        <?php if ($latestEvaluation['attendance_presence_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['attendance_presence_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['attendance_presence']); ?>">
                                            <?php echo $latestEvaluation['attendance_presence']; ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Punctuality</strong>
                                        <?php if ($latestEvaluation['attendance_punctuality_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['attendance_punctuality_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['attendance_punctuality']); ?>">
                                            <?php echo $latestEvaluation['attendance_punctuality']; ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Interpersonal Skills -->
                            <div class="criteria-section">
                                <h6 class="criteria-title">Interpersonal Skills</h6>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Communication</strong>
                                        <?php if ($latestEvaluation['interpersonal_communication_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['interpersonal_communication_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['interpersonal_communication']); ?>">
                                            <?php echo $latestEvaluation['interpersonal_communication']; ?>%
                                        </span>
                                    </div>
                                </div>
                                <div class="criteria-item">
                                    <div>
                                        <strong>Teamwork</strong>
                                        <?php if ($latestEvaluation['interpersonal_teamwork_comments']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($latestEvaluation['interpersonal_teamwork_comments']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rating-info">
                                        <span class="badge bg-<?php echo getRatingColor($latestEvaluation['interpersonal_teamwork']); ?>">
                                            <?php echo $latestEvaluation['interpersonal_teamwork']; ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Evaluation History -->
            <div class="evaluation-card">
                <h5 class="mb-4">
                    <i class="material-icons" style="vertical-align: middle;">history</i>
                    Evaluation History
                </h5>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Evaluator</th>
                                <th>Overall Rating</th>
                                <th>Recommendation</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allEvaluations as $evaluation): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($evaluation['evaluator_first'] . ' ' . $evaluation['evaluator_last']); ?>
                                        <?php if ($evaluation['evaluator_role'] == 8): ?>
                                            <span class="badge bg-info ms-1">OIC</span>
                                        <?php elseif ($evaluation['evaluator_role'] == 3): ?>
                                            <span class="badge bg-primary ms-1">HR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getRatingColor($evaluation['overall_rating']); ?> rating-badge">
                                            <?php echo number_format($evaluation['overall_rating'], 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($evaluation['recommendation']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($evaluation['recommendation']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewEvaluationDetails(<?php echo $evaluation['evaluation_id']; ?>)">
                                            <i class="material-icons" style="font-size: 16px;">visibility</i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Include JavaScript libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Performance Trend Chart
        <?php if (count($allEvaluations) > 1): ?>
        const trendData = {
            labels: [<?php echo implode(',', array_map(function($eval) { return '"' . date('M Y', strtotime($eval['evaluation_date'])) . '"'; }, array_reverse($allEvaluations))); ?>],
            datasets: [{
                label: 'Overall Rating (%)',
                data: [<?php echo implode(',', array_map(function($eval) { return $eval['overall_rating']; }, array_reverse($allEvaluations))); ?>],
                borderColor: '#2a7d4f',
                backgroundColor: 'rgba(42, 125, 79, 0.1)',
                tension: 0.4,
                fill: true
            }]
        };

        const trendConfig = {
            type: 'line',
            data: trendData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 60,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        };

        const trendChart = new Chart(document.getElementById('performanceTrendChart'), trendConfig);
        <?php else: ?>
        // Show message when there's insufficient data for trend
        document.getElementById('performanceTrendChart').style.display = 'none';
        document.querySelector('.trend-card').innerHTML = '<div class="text-center pt-5"><i class="material-icons" style="font-size: 3rem; color: #ddd;">trending_up</i><h5 class="text-muted">Insufficient Data</h5><p class="text-muted">At least 2 evaluations needed for trend analysis</p></div>';
        <?php endif; ?>
        
        function viewEvaluationDetails(evaluationId) {
            // For now, just show an alert. In the future, this could open a modal with detailed view
            Swal.fire({
                title: 'Evaluation Details',
                text: 'Detailed evaluation view for ID: ' + evaluationId,
                icon: 'info',
                confirmButtonText: 'OK'
            });
        }
    </script>
</body>
</html>