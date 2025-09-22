<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
if (!validateSession($conn, 3)) { exit; }

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo '<div class="alert alert-danger">Invalid user ID provided.</div>';
    exit;
}

// Get guard information
$guardQuery = "
    SELECT 
        u.User_ID, u.employee_id, u.First_Name, u.Last_Name, u.middle_name,
        u.hired_date, u.Created_At, gl.location_name
    FROM users u
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
    WHERE u.User_ID = ? AND u.Role_ID = 5 AND u.status = 'Active'
";

$guardStmt = $conn->prepare($guardQuery);
$guardStmt->execute([$user_id]);
$guard = $guardStmt->fetch(PDO::FETCH_ASSOC);

if (!$guard) {
    echo '<div class="alert alert-danger">Guard not found.</div>';
    exit;
}

// Get latest evaluation
$evaluationQuery = "
    SELECT * FROM performance_evaluations 
    WHERE user_id = ? AND status = 'Completed'
    ORDER BY evaluation_date DESC 
    LIMIT 1
";

$evaluationStmt = $conn->prepare($evaluationQuery);
$evaluationStmt->execute([$user_id]);
$evaluation = $evaluationStmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluation) {
    echo '<div class="alert alert-warning">No completed evaluation found for this guard.</div>';
    exit;
}

// Get detailed ratings
$ratingsQuery = "
    SELECT * FROM evaluation_ratings 
    WHERE evaluation_id = ?
    ORDER BY rating_id
";

$ratingsStmt = $conn->prepare($ratingsQuery);
$ratingsStmt->execute([$evaluation['evaluation_id']]);
$ratings = $ratingsStmt->fetchAll(PDO::FETCH_ASSOC);

$fullName = trim($guard['First_Name'] . ' ' . $guard['middle_name'] . ' ' . $guard['Last_Name']);

// Get all evaluations for this guard
$allEvaluationsQuery = "
    SELECT pe.*, u.First_Name as evaluator_first, u.Last_Name as evaluator_last 
    FROM performance_evaluations pe
    LEFT JOIN users u ON pe.evaluator_id = u.User_ID
    WHERE pe.user_id = ? AND pe.status = 'Completed'
    ORDER BY pe.evaluation_date DESC
";
$evaluationsStmt = $conn->prepare($allEvaluationsQuery);
$evaluationsStmt->execute([$user_id]);
$allEvaluations = $evaluationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate employment status
$hiredDate = $guard['hired_date'] ?: $guard['Created_At'];
$hiredDateTime = new DateTime($hiredDate);
$currentDateTime = new DateTime();
$interval = $hiredDateTime->diff($currentDateTime);
$monthsDiff = ($interval->y * 12) + $interval->m;
$employmentStatus = $monthsDiff >= 6 ? 'Regular' : 'Probationary';

// Get HR name for header
$hrStmt = $conn->prepare("SELECT First_Name, Last_Name, Profile_Pic FROM users WHERE User_ID = ?");
$hrStmt->execute([$_SESSION['user_id']]);
$hrData = $hrStmt->fetch(PDO::FETCH_ASSOC);
$hrName = $hrData ? $hrData['First_Name'] . ' ' . $hrData['Last_Name'] : "Human Resource";

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $hrProfile = $profileData['Profile_Pic'];
} else {
    $hrProfile = '../images/default_profile.png';
}
// Function to get rating description
function getRatingDescription($rating) {
    if ($rating >= 4.5) return 'Outstanding';
    if ($rating >= 3.5) return 'Exceeds Expectations';
    if ($rating >= 2.5) return 'Meets Expectations';
    if ($rating >= 1.5) return 'Below Expectations';
    return 'Unsatisfactory';
}

// Function to get rating color
function getRatingColor($rating) {
    if ($rating >= 4.5) return 'success';
    if ($rating >= 3.5) return 'info';
    if ($rating >= 2.5) return 'primary';
    if ($rating >= 1.5) return 'warning';
    return 'danger';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Performance Evaluation - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/performance_evaluation.css">
    
    <!-- Chart.js for performance trends -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="page-title">Performance Evaluation Details</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="hr_dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="performance_evaluation.php">Performance Evaluation</a></li>
                                <li class="breadcrumb-item active">View Evaluation</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="performance_evaluation.php" class="btn btn-secondary">
                            <i class="material-icons">arrow_back</i> Back to List
                        </a>
                    </div>
                </div>

                <!-- Guard Information Card -->
                <div class="guard-info-card">
                    <div class="row">
                        <div class="col-md-8">
                            <h3><?php echo htmlspecialchars($guard['First_Name'] . ' ' . $guard['middle_name'] . ' ' . $guard['Last_Name']); ?></h3>
                            <p class="mb-1"><strong>Employee ID:</strong> <?php echo htmlspecialchars($guard['employee_id'] ?: sprintf("EMP%04d", $guard['User_ID'])); ?></p>
                            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($guard['location_name'] ?: 'Not Assigned'); ?></p>
                            <p class="mb-1"><strong>Hired Date:</strong> <?php echo date('M d, Y', strtotime($hiredDate)); ?></p>
                            <p class="mb-1"><strong>Total Evaluations:</strong> <?php echo count($allEvaluations); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="badge bg-light text-dark fs-6 mb-2">
                                <?php echo $employmentStatus; ?> Employee
                            </span>
                            <?php if ($evaluation && $evaluation['evaluation_date']): ?>
                                <br>
                                <span class="badge bg-success fs-6">
                                    Last Evaluated: <?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($evaluation): ?>
                    <!-- Latest Evaluation Details -->
                    <div class="evaluation-card">
                        <div class="section-title">
                            <i class="material-icons">assessment</i> Latest Performance Evaluation
                        </div>
                        
                        <!-- Evaluation Summary -->
                        <div class="row mb-4">
                            <div class="col-md-3 text-center">
                                <div class="rating-display text-<?php echo getRatingColor($evaluation['overall_rating']); ?>">
                                    <?php echo number_format($evaluation['overall_rating'], 1); ?>
                                </div>
                                <div class="fs-5 text-<?php echo getRatingColor($evaluation['overall_rating']); ?>">
                                    <?php echo getRatingDescription($evaluation['overall_rating']); ?>
                                </div>
                                <small class="text-muted">Overall Rating</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="rating-display text-info">
                                    <?php echo count($ratings); ?>
                                </div>
                                <div class="fs-5 text-info">Rating Criteria</div>
                                <small class="text-muted">Total Evaluated</small>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Evaluation Date:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Employee Name:</strong></td>
                                        <td><?php echo htmlspecialchars($evaluation['employee_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Position:</strong></td>
                                        <td><?php echo htmlspecialchars($evaluation['position']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td><span class="badge bg-success"><?php echo $evaluation['status']; ?></span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Detailed Ratings -->
                        <?php if (count($ratings) > 0): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="section-title">
                                    <i class="material-icons">star_rate</i> Detailed Ratings
                                </h5>
                                <div class="row">
                                    <?php 
                                    $categories = [
                                        'PROFESSIONALISM' => [],
                                        'RELIABILITY' => [],
                                        'TECHNICAL SKILLS' => [],
                                        'INTERPERSONAL SKILLS' => [],
                                        'INITIATIVE' => []
                                    ];
                                    
                                    // Group ratings by category
                                    foreach ($ratings as $rating) {
                                        $found = false;
                                        foreach ($categories as $category => $items) {
                                            if (strpos(strtoupper($rating['criterion_name']), $category) !== false) {
                                                $categories[$category][] = $rating;
                                                $found = true;
                                                break;
                                            }
                                        }
                                        if (!$found) {
                                            $categories['TECHNICAL SKILLS'][] = $rating;
                                        }
                                    }
                                    
                                    foreach ($categories as $category => $categoryRatings):
                                        if (count($categoryRatings) > 0):
                                    ?>
                                    <div class="col-md-4 mb-3">
                                        <h6 class="text-primary"><?php echo $category; ?></h6>
                                        <?php foreach ($categoryRatings as $rating): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small><?php echo htmlspecialchars($rating['criterion_name']); ?></small>
                                            <span class="badge bg-<?php echo getRatingColor($rating['rating_score']); ?>">
                                                <?php echo $rating['rating_score']; ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Evaluation Details -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="evaluation-section">
                                    <h5 class="text-success">
                                        <i class="material-icons">business</i> Client Assignment
                                    </h5>
                                    <div class="border-start border-success border-3 ps-3">
                                        <?php echo $evaluation['client_assignment'] ? htmlspecialchars($evaluation['client_assignment']) : '<em class="text-muted">No client assignment recorded</em>'; ?>
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
                                        if ($evaluation['recommendation']) {
                                            echo '<span class="badge bg-' . ($evaluation['recommendation'] == 'renewal' ? 'success' : ($evaluation['recommendation'] == 'termination' ? 'danger' : 'info')) . '">';
                                            echo ucfirst($evaluation['recommendation']);
                                            echo '</span>';
                                            if ($evaluation['other_recommendation']) {
                                                echo '<br><small>' . htmlspecialchars($evaluation['other_recommendation']) . '</small>';
                                            }
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
                                    <div class="border-start border-primary border-3 ps-3">
                                        <?php echo $evaluation['evaluated_by'] ? htmlspecialchars($evaluation['evaluated_by']) : '<em class="text-muted">No evaluator recorded</em>'; ?>
                                        <?php if ($evaluation['client_representative']): ?>
                                            <br><small><strong>Client Rep:</strong> <?php echo htmlspecialchars($evaluation['client_representative']); ?></small>
                                        <?php endif; ?>
                                        <?php if ($evaluation['gmsai_representative']): ?>
                                            <br><small><strong>GMSAI Rep:</strong> <?php echo htmlspecialchars($evaluation['gmsai_representative']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Trend Chart -->
                    <?php if (count($allEvaluations) > 1): ?>
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
                                                echo number_format($avgRating, 2);
                                            ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Evaluation History -->
                    <div class="evaluation-card">
                        <div class="section-title">
                            <i class="material-icons">history</i> Evaluation History
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Rating</th>
                                        <th>Position</th>
                                        <th>Client Assignment</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allEvaluations as $eval): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($eval['evaluation_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($eval['employee_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getRatingColor($eval['overall_rating']); ?>">
                                                    <?php echo number_format($eval['overall_rating'], 1); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($eval['position'] ?: 'Security Guard'); ?></td>
                                            <td><?php echo htmlspecialchars($eval['client_assignment'] ?: 'N/A'); ?></td>
                                            <td><span class="badge bg-success"><?php echo $eval['status']; ?></span></td>
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
                            <a href="performance_evaluation2.php?evaluate=<?php echo $user_id; ?>" class="btn btn-primary">
                                <i class="material-icons">assignment</i> Start Evaluation
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (count($allEvaluations) > 1): ?>
    <script>
        // Performance Trend Chart
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const evaluations = <?php echo json_encode($allEvaluations); ?>;
        
        const labels = evaluations.map(eval => {
            const date = new Date(eval.evaluation_date);
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }).reverse();
        
        const ratingData = evaluations.map(eval => parseFloat(eval.overall_rating)).reverse();
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Overall Rating',
                    data: ratingData,
                    borderColor: '#2a7d4f',
                    backgroundColor: 'rgba(42, 125, 79, 0.1)',
                    yAxisID: 'y'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        // Adjusted to show percentage-based ratings range 70–90
                        min: 70,
                        max: 90,
                        title: {
                            display: true,
                            text: 'Overall Rating (%)'
                        },
                        ticks: {
                            stepSize: 5
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
