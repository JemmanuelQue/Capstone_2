<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
// Enforce HR role (3)
if (!validateSession($conn, 3)) { exit; }

$guardId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$guardId) {
    header('Location: performance_evaluation.php');
    exit;
}

// Get guard information
$guardStmt = $conn->prepare("
    SELECT u.*, gl.location_name 
    FROM users u 
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
    WHERE u.User_ID = ? AND u.Role_ID = 5 AND u.status = 'Active'
");
$guardStmt->execute([$guardId]);
$guard = $guardStmt->fetch(PDO::FETCH_ASSOC);

if (!$guard) {
    $_SESSION['error'] = "Guard not found or invalid.";
    header('Location: performance_evaluation.php');
    exit;
}

// Process form submission
if ($_POST) {
    try {
        $conn->beginTransaction();
        
        $evaluationData = [
            'user_id' => $guardId,
            'evaluation_date' => $_POST['evaluation_date'],
            'evaluation_period_start' => $_POST['period_start'],
            'evaluation_period_end' => $_POST['period_end'],
            'overall_rating' => $_POST['overall_rating'],
            'performance_score' => $_POST['performance_score'],
            'strengths' => $_POST['strengths'],
            'areas_for_improvement' => $_POST['areas_for_improvement'],
            'goals_next_period' => $_POST['goals_next_period'],
            'evaluator_id' => $_SESSION['user_id'],
            'status' => 'Completed'
        ];
        
        $insertStmt = $conn->prepare("
            INSERT INTO performance_evaluations 
            (user_id, evaluation_date, evaluation_period_start, evaluation_period_end, 
             overall_rating, performance_score, strengths, areas_for_improvement, 
             goals_next_period, evaluator_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insertStmt->execute(array_values($evaluationData));
        
        $conn->commit();
        $_SESSION['success'] = "Performance evaluation completed successfully!";
        header('Location: performance_evaluation.php');
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error saving evaluation: " . $e->getMessage();
    }
}

// Calculate employment status
$hiredDate = $guard['hired_date'] ?: $guard['Created_At'];
$hiredDateTime = new DateTime($hiredDate);
$currentDateTime = new DateTime();
$interval = $hiredDateTime->diff($currentDateTime);
$monthsDiff = ($interval->y * 12) + $interval->m;
$employmentStatus = $monthsDiff >= 6 ? 'Regular' : 'Probationary';

// Get HR name for header
$hrStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE User_ID = ?");
$hrStmt->execute([$_SESSION['user_id']]);
$hrData = $hrStmt->fetch(PDO::FETCH_ASSOC);
$hrName = $hrData ? $hrData['First_Name'] . ' ' . $hrData['Last_Name'] : "Human Resource";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Evaluation Form - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/performance_evaluation.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .evaluation-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
        }
        .guard-info-card {
            background: linear-gradient(135deg, #2a7d4f, #3a9d6f);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .rating-input {
            max-width: 120px;
        }
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        .form-section:last-child {
            border-bottom: none;
        }
        .section-title {
            color: #2a7d4f;
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 1.2rem;
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
                        <h1 class="page-title">Performance Evaluation Form</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="hr_dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="performance_evaluation.php">Performance Evaluation</a></li>
                                <li class="breadcrumb-item active">Evaluate Guard</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <span class="text-muted">Evaluator: <?php echo htmlspecialchars($hrName); ?></span>
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
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="badge bg-light text-dark fs-6">
                                <?php echo $employmentStatus; ?> Employee
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Evaluation Form -->
                <div class="evaluation-form">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" id="evaluationForm">
                        <!-- Evaluation Period Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="material-icons">date_range</i> Evaluation Period
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="evaluation_date" class="form-label">Evaluation Date</label>
                                    <input type="date" class="form-control" id="evaluation_date" name="evaluation_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="period_start" class="form-label">Period Start</label>
                                    <input type="date" class="form-control" id="period_start" name="period_start" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="period_end" class="form-label">Period End</label>
                                    <input type="date" class="form-control" id="period_end" name="period_end" required>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Rating Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="material-icons">star_rate</i> Performance Rating
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="overall_rating" class="form-label">Overall Rating (1.0 - 5.0)</label>
                                    <input type="number" class="form-control rating-input" id="overall_rating" name="overall_rating" 
                                           min="1.0" max="5.0" step="0.1" required>
                                    <small class="form-text text-muted">5.0 = Outstanding, 4.0 = Exceeds Expectations, 3.0 = Meets Expectations, 2.0 = Below Expectations, 1.0 = Unsatisfactory</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="performance_score" class="form-label">Performance Score (0-100)</label>
                                    <input type="number" class="form-control rating-input" id="performance_score" name="performance_score" 
                                           min="0" max="100" required>
                                    <small class="form-text text-muted">Numerical score based on performance metrics</small>
                                </div>
                            </div>
                        </div>

                        <!-- Evaluation Details Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="material-icons">assessment</i> Evaluation Details
                            </div>
                            <div class="mb-3">
                                <label for="strengths" class="form-label">Strengths and Positive Observations</label>
                                <textarea class="form-control" id="strengths" name="strengths" rows="4" 
                                          placeholder="Describe the guard's strengths, positive behaviors, and accomplishments during this evaluation period..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="areas_for_improvement" class="form-label">Areas for Improvement</label>
                                <textarea class="form-control" id="areas_for_improvement" name="areas_for_improvement" rows="4" 
                                          placeholder="Identify areas where the guard can improve their performance..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="goals_next_period" class="form-label">Goals for Next Evaluation Period</label>
                                <textarea class="form-control" id="goals_next_period" name="goals_next_period" rows="4" 
                                          placeholder="Set specific, measurable goals for the next evaluation period..."></textarea>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-end gap-3">
                            <a href="performance_evaluation.php" class="btn btn-secondary">
                                <i class="material-icons">cancel</i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="material-icons">save</i> Complete Evaluation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Set default period dates based on employment status
        document.addEventListener('DOMContentLoaded', function() {
            const employmentStatus = '<?php echo $employmentStatus; ?>';
            const hiredDate = new Date('<?php echo $hiredDate; ?>');
            const today = new Date();
            
            let periodStart, periodEnd;
            
            if (employmentStatus === 'Probationary') {
                // For probationary, set 3-month period
                periodEnd = new Date(today);
                periodStart = new Date(today);
                periodStart.setMonth(periodStart.getMonth() - 3);
            } else {
                // For regular, set 12-month period
                periodEnd = new Date(today);
                periodStart = new Date(today);
                periodStart.setFullYear(periodStart.getFullYear() - 1);
            }
            
            // Ensure period start is not before hire date
            if (periodStart < hiredDate) {
                periodStart = hiredDate;
            }
            
            document.getElementById('period_start').value = periodStart.toISOString().split('T')[0];
            document.getElementById('period_end').value = periodEnd.toISOString().split('T')[0];
        });

        // Form validation
        document.getElementById('evaluationForm').addEventListener('submit', function(e) {
            const rating = parseFloat(document.getElementById('overall_rating').value);
            const score = parseInt(document.getElementById('performance_score').value);
            
            if (rating < 1.0 || rating > 5.0) {
                e.preventDefault();
                Swal.fire('Invalid Rating', 'Overall rating must be between 1.0 and 5.0', 'error');
                return;
            }
            
            if (score < 0 || score > 100) {
                e.preventDefault();
                Swal.fire('Invalid Score', 'Performance score must be between 0 and 100', 'error');
                return;
            }
            
            // Confirm submission
            e.preventDefault();
            Swal.fire({
                title: 'Complete Evaluation?',
                text: 'Are you sure you want to submit this performance evaluation?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2a7d4f',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Submit',
                cancelButtonText: 'Review Again'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    </script>
</body>
</html>
