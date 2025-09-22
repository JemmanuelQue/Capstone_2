<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

// Check if user is logged in and has guard role
if (!validateSession($conn, 5)) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}

if (!isset($_GET['evaluation_id']) || !is_numeric($_GET['evaluation_id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid evaluation ID.</div>';
    exit;
}

$evaluationId = (int)$_GET['evaluation_id'];
$userId = $_SESSION['user_id'];

try {
    // Fetch evaluation details
    $stmt = $conn->prepare("
        SELECT 
            pe.*,
            u.First_Name,
            u.Last_Name,
            evaluator.First_Name as evaluator_first_name,
            evaluator.Last_Name as evaluator_last_name
        FROM performance_evaluations pe
        LEFT JOIN users u ON pe.user_id = u.User_ID
        LEFT JOIN users evaluator ON pe.evaluator_id = evaluator.User_ID
        WHERE pe.evaluation_id = ? AND pe.user_id = ?
    ");
    $stmt->execute([$evaluationId, $userId]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evaluation) {
        echo '<div class="alert alert-warning">Evaluation not found or access denied.</div>';
        exit;
    }

    // Fetch evaluation ratings/criteria
    $ratingsStmt = $conn->prepare("
        SELECT criterion_name, rating_score, comments
        FROM evaluation_ratings
        WHERE evaluation_id = ?
        ORDER BY criterion_name
    ");
    $ratingsStmt->execute([$evaluationId]);
    $ratings = $ratingsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching evaluation details: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error loading evaluation details.</div>';
    exit;
}
?>

<div class="evaluation-details">
    <!-- Header Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h6 class="text-primary">Employee Information</h6>
            <table class="table table-sm table-borderless">
                <tr>
                    <td class="fw-semibold">Name:</td>
                    <td><?php echo htmlspecialchars($evaluation['employee_name']); ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Position:</td>
                    <td><?php echo htmlspecialchars($evaluation['position']); ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Area Assigned:</td>
                    <td><?php echo htmlspecialchars($evaluation['area_assigned'] ?: 'Not specified'); ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Client Assignment:</td>
                    <td><?php echo htmlspecialchars($evaluation['client_assignment'] ?: 'Not specified'); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-primary">Evaluation Information</h6>
            <table class="table table-sm table-borderless">
                <tr>
                    <td class="fw-semibold">Evaluation Date:</td>
                    <td><?php echo date('F j, Y', strtotime($evaluation['evaluation_date'])); ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Evaluation Period:</td>
                    <td><?php echo htmlspecialchars($evaluation['evaluation_period'] ?: 'Not specified'); ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Evaluated By:</td>
                    <td>
                        <?php 
                        if ($evaluation['evaluator_first_name'] && $evaluation['evaluator_last_name']) {
                            echo htmlspecialchars($evaluation['evaluator_first_name'] . ' ' . $evaluation['evaluator_last_name']);
                        } else {
                            echo htmlspecialchars($evaluation['evaluated_by'] ?: 'Not specified');
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold">Status:</td>
                    <td><span class="badge bg-success"><?php echo $evaluation['status']; ?></span></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Overall Rating -->
    <?php if ($evaluation['overall_rating']): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title text-primary">Overall Rating</h6>
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-<?php 
                    $rating = (float)$evaluation['overall_rating'];
                    if ($rating >= 90.00) echo 'success';
                    elseif ($rating >= 85.00) echo 'primary';
                    elseif ($rating >= 80.00) echo 'info';
                    elseif ($rating >= 75.00) echo 'warning';
                    else echo 'danger';
                ?> me-3 fs-6">
                    <?php echo number_format($evaluation['overall_rating'], 2); ?>
                </span>
                <div class="rating-stars">
                    <?php
                    // Convert percentage to 5-star scale for display
                    $starRating = ($rating / 100) * 5;
                    $fullStars = floor($starRating);
                    $hasHalfStar = ($starRating - $fullStars) >= 0.5;
                    
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $fullStars) {
                            echo '<span class="material-icons text-warning me-1">star</span>';
                        } elseif ($i == $fullStars + 1 && $hasHalfStar) {
                            echo '<span class="material-icons text-warning me-1">star_half</span>';
                        } else {
                            echo '<span class="material-icons text-muted me-1">star_border</span>';
                        }
                    }
                    ?>
                </div>
                <span class="ms-2 text-muted">
                    <?php
                    if ($rating >= 90.00) echo 'Outstanding';
                    elseif ($rating >= 85.00) echo 'Good';
                    elseif ($rating >= 80.00) echo 'Fair';
                    elseif ($rating >= 75.00) echo 'Needs Improvement';
                    else echo 'Poor';
                    ?>
                </span>
            </div>
            <?php if ($evaluation['overall_performance']): ?>
                <p class="mb-2"><strong>Performance Level:</strong> <?php echo htmlspecialchars($evaluation['overall_performance']); ?></p>
            <?php endif; ?>
            
            <!-- Performance Statistics -->
            <div class="mt-3 p-3 bg-light rounded">
                <h6 class="mb-2 text-secondary">Performance Level Guide:</h6>
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted d-block">
                            <strong>O - Outstanding:</strong> 90% and above<br>
                            <strong>G - Good:</strong> 85% - 89.99%<br>
                            <strong>F - Fair:</strong> 80% - 84.99%
                        </small>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">
                            <strong>NI - Needs Improvement:</strong> 75% - 79.99%<br>
                            <strong>P - Poor:</strong> Below 75%
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detailed Ratings -->
    <?php if (!empty($ratings)): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title text-primary">Detailed Evaluation Criteria</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Criterion</th>
                            <th>Rating</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ratings as $rating): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo htmlspecialchars($rating['criterion_name']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    $score = (int)$rating['rating_score'];
                                    if ($score >= 4) echo 'success';
                                    elseif ($score >= 3) echo 'primary';
                                    elseif ($score >= 2) echo 'warning';
                                    else echo 'danger';
                                ?>"><?php echo $rating['rating_score']; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($rating['comments'] ?: 'No comments'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recommendation -->
    <?php if ($evaluation['recommendation']): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title text-primary">Recommendation</h6>
            <div class="mb-2">
                <span class="badge bg-<?php 
                    echo $evaluation['recommendation'] == 'renewal' ? 'success' : 
                        ($evaluation['recommendation'] == 'termination' ? 'danger' : 'secondary'); 
                ?> fs-6">
                    <?php echo ucfirst($evaluation['recommendation']); ?>
                </span>
            </div>
            <?php if ($evaluation['contract_term']): ?>
                <p class="mb-2"><strong>Contract Term:</strong> <?php echo htmlspecialchars($evaluation['contract_term']); ?></p>
            <?php endif; ?>
            <?php if ($evaluation['other_recommendation']): ?>
                <p class="mb-0"><strong>Additional Notes:</strong> <?php echo nl2br(htmlspecialchars($evaluation['other_recommendation'])); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Representatives -->
    <?php if ($evaluation['client_representative'] || $evaluation['gmsai_representative']): ?>
    <div class="card">
        <div class="card-body">
            <h6 class="card-title text-primary">Representatives</h6>
            <?php if ($evaluation['client_representative']): ?>
                <p class="mb-1"><strong>Client Representative:</strong> <?php echo htmlspecialchars($evaluation['client_representative']); ?></p>
            <?php endif; ?>
            <?php if ($evaluation['gmsai_representative']): ?>
                <p class="mb-0"><strong>GMSAI Representative:</strong> <?php echo htmlspecialchars($evaluation['gmsai_representative']); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
