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

// Get current OIC user's info
$oicStmt = $conn->prepare("SELECT User_ID, First_Name, Last_Name FROM users WHERE Role_ID = 8 AND status = 'Active' AND User_ID = ?");
$oicStmt->execute([$_SESSION['user_id']]);
$oicData = $oicStmt->fetch(PDO::FETCH_ASSOC);

if (!$oicData) {
    echo '<div class="alert alert-danger">OIC not found</div>';
    exit;
}

$oicName = $oicData['First_Name'] . ' ' . $oicData['Last_Name'];

// Get OIC's assigned locations
$oicLocationsQuery = "SELECT location_name FROM oic_locations WHERE oic_user_id = ? AND is_active = 1";
$oicLocationsStmt = $conn->prepare($oicLocationsQuery);
$oicLocationsStmt->execute([$_SESSION['user_id']]);
$oicLocations = $oicLocationsStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($oicLocations)) {
    echo '<div class="alert alert-danger">You are not assigned to any locations</div>';
    exit;
}

// Get guard information and verify they are in OIC's assigned location
$guardQuery = "
    SELECT 
        u.User_ID, 
        u.employee_id,
        u.First_Name, 
        u.Last_Name, 
        u.middle_name,
        u.hired_date,
        u.Created_At,
        gl.location_name,
        u.phone_number,
        u.sex,
        u.civil_status
    FROM users u
    INNER JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_active = 1
    WHERE u.Role_ID = 5 AND u.status = 'Active' AND u.User_ID = ?
";

$guardStmt = $conn->prepare($guardQuery);
$guardStmt->execute([$guardId]);
$guard = $guardStmt->fetch(PDO::FETCH_ASSOC);

if (!$guard) {
    echo '<div class="alert alert-danger">Guard not found</div>';
    exit;
}

// Verify the guard is in one of the OIC's assigned locations
if (!in_array($guard['location_name'], $oicLocations)) {
    echo '<div class="alert alert-danger">You are not authorized to evaluate this guard</div>';
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

// Check if there's already a recent evaluation for this guard
$recentEvalQuery = "
    SELECT evaluation_id, evaluation_date, overall_rating, status 
    FROM performance_evaluations 
    WHERE user_id = ? AND evaluation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY evaluation_date DESC 
    LIMIT 1
";
$recentEvalStmt = $conn->prepare($recentEvalQuery);
$recentEvalStmt->execute([$guardId]);
$recentEval = $recentEvalStmt->fetch(PDO::FETCH_ASSOC);
?>

<style>
    .evaluation-form { background: white; padding: 20px; border-radius: 10px; }
    .section-title { color: #2a7d4f; font-weight: 600; border-bottom: 2px solid #2a7d4f; padding-bottom: 5px; margin-bottom: 15px; }
    .evaluation-section { border: 1px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
    .evaluation-item { margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; }
    .rating-options { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .rating-options input[type="radio"] { margin-right: 5px; }
    .rating-options label { background: #e9ecef; padding: 5px 10px; border-radius: 15px; cursor: pointer; font-size: 0.9em; margin-right: 8px; transition: all 0.3s; }
    .rating-options input[type="radio"]:checked + label { background: #2a7d4f; color: white; }
    .guard-info-card { background: linear-gradient(135deg, #2a7d4f, #20c997); color: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
    .alert-recent-eval { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
    
    @media (max-width: 768px) {
        .rating-options { flex-direction: column; align-items: flex-start; }
        .rating-options label { margin-bottom: 5px; }
    }
</style>

<!-- Guard Information Card -->
<div class="guard-info-card">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h5 class="mb-2">
                <i class="material-icons" style="vertical-align: middle;">person</i>
                <?php echo htmlspecialchars($guardName); ?>
            </h5>
            <div class="row">
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Employee ID:</strong> <?php echo htmlspecialchars($guard['employee_id'] ?: 'N/A'); ?></p>
                    <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($guard['location_name']); ?></p>
                    <p class="mb-0"><strong>Employment Status:</strong> <?php echo $employmentStatus; ?></p>
                </div>
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Hired Date:</strong> <?php echo $hiredDate ? date('M d, Y', strtotime($hiredDate)) : 'N/A'; ?></p>
                    <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($guard['phone_number'] ?: 'N/A'); ?></p>
                    <p class="mb-0"><strong>Evaluator:</strong> <?php echo htmlspecialchars($oicName); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 text-md-end">
            <p class="mb-0"><small>Evaluation Date: <?php echo date('F d, Y'); ?></small></p>
        </div>
    </div>
</div>

<?php if ($recentEval): ?>
<div class="alert alert-recent-eval">
    <i class="material-icons" style="vertical-align: middle;">info</i>
    <strong>Recent Evaluation Found:</strong> This guard was evaluated on 
    <?php echo date('M d, Y', strtotime($recentEval['evaluation_date'])); ?> 
    with a rating of <?php echo number_format($recentEval['overall_rating'], 1); ?>%. 
    Status: <?php echo $recentEval['status']; ?>
</div>
<?php endif; ?>

<!-- Evaluation Form -->
<div class="evaluation-form">
    <form id="evaluationForm" method="POST">
        <input type="hidden" name="guard_id" value="<?php echo $guardId; ?>">
        <input type="hidden" name="evaluator_id" value="<?php echo $_SESSION['user_id']; ?>">
        <input type="hidden" name="evaluation_period" value="<?php echo date('Y-m'); ?>">
        
        <!-- Rating Scale Information -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="material-icons">grade</i> Rating Scale</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Rating</th>
                                <th>Description</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>O</strong></td><td>Outstanding</td><td>90%</td></tr>
                            <tr><td><strong>G</strong></td><td>Good</td><td>85%</td></tr>
                            <tr><td><strong>F</strong></td><td>Fair</td><td>80%</td></tr>
                            <tr><td><strong>NI</strong></td><td>Needs Improvement</td><td>75%</td></tr>
                            <tr><td><strong>P</strong></td><td>Poor</td><td>70%</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 1. Technical Skills -->
        <div class="evaluation-section">
            <h6 class="section-title">1. Technical Skills</h6>
            
            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>1.1 Job Knowledge</strong></label>
                        <p class="text-muted small">Understanding of security procedures and protocols</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="tech_job_knowledge" value="70" id="tech_job_knowledge_70" required>
                            <label for="tech_job_knowledge_70">P (70%)</label>
                            <input type="radio" name="tech_job_knowledge" value="75" id="tech_job_knowledge_75">
                            <label for="tech_job_knowledge_75">NI (75%)</label>
                            <input type="radio" name="tech_job_knowledge" value="80" id="tech_job_knowledge_80">
                            <label for="tech_job_knowledge_80">F (80%)</label>
                            <input type="radio" name="tech_job_knowledge" value="85" id="tech_job_knowledge_85">
                            <label for="tech_job_knowledge_85">G (85%)</label>
                            <input type="radio" name="tech_job_knowledge" value="90" id="tech_job_knowledge_90">
                            <label for="tech_job_knowledge_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="tech_job_knowledge_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>1.2 Tool Competency</strong></label>
                        <p class="text-muted small">Proper use of security equipment and tools</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="tech_tool_competency" value="70" id="tech_tool_competency_70" required>
                            <label for="tech_tool_competency_70">P (70%)</label>
                            <input type="radio" name="tech_tool_competency" value="75" id="tech_tool_competency_75">
                            <label for="tech_tool_competency_75">NI (75%)</label>
                            <input type="radio" name="tech_tool_competency" value="80" id="tech_tool_competency_80">
                            <label for="tech_tool_competency_80">F (80%)</label>
                            <input type="radio" name="tech_tool_competency" value="85" id="tech_tool_competency_85">
                            <label for="tech_tool_competency_85">G (85%)</label>
                            <input type="radio" name="tech_tool_competency" value="90" id="tech_tool_competency_90">
                            <label for="tech_tool_competency_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="tech_tool_competency_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>1.3 Safety Procedures</strong></label>
                        <p class="text-muted small">Adherence to safety protocols and standards</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="tech_safety_procedure" value="70" id="tech_safety_procedure_70" required>
                            <label for="tech_safety_procedure_70">P (70%)</label>
                            <input type="radio" name="tech_safety_procedure" value="75" id="tech_safety_procedure_75">
                            <label for="tech_safety_procedure_75">NI (75%)</label>
                            <input type="radio" name="tech_safety_procedure" value="80" id="tech_safety_procedure_80">
                            <label for="tech_safety_procedure_80">F (80%)</label>
                            <input type="radio" name="tech_safety_procedure" value="85" id="tech_safety_procedure_85">
                            <label for="tech_safety_procedure_85">G (85%)</label>
                            <input type="radio" name="tech_safety_procedure" value="90" id="tech_safety_procedure_90">
                            <label for="tech_safety_procedure_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="tech_safety_procedure_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Quality -->
        <div class="evaluation-section">
            <h6 class="section-title">2. Quality</h6>
            
            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>2.1 Accuracy</strong></label>
                        <p class="text-muted small">Precision in security tasks and reporting</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="quality_accuracy" value="70" id="quality_accuracy_70" required>
                            <label for="quality_accuracy_70">P (70%)</label>
                            <input type="radio" name="quality_accuracy" value="75" id="quality_accuracy_75">
                            <label for="quality_accuracy_75">NI (75%)</label>
                            <input type="radio" name="quality_accuracy" value="80" id="quality_accuracy_80">
                            <label for="quality_accuracy_80">F (80%)</label>
                            <input type="radio" name="quality_accuracy" value="85" id="quality_accuracy_85">
                            <label for="quality_accuracy_85">G (85%)</label>
                            <input type="radio" name="quality_accuracy" value="90" id="quality_accuracy_90">
                            <label for="quality_accuracy_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="quality_accuracy_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>2.2 Completeness / Orderliness</strong></label>
                        <p class="text-muted small">Thoroughness and organization in duties</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="quality_completeness" value="70" id="quality_completeness_70" required>
                            <label for="quality_completeness_70">P (70%)</label>
                            <input type="radio" name="quality_completeness" value="75" id="quality_completeness_75">
                            <label for="quality_completeness_75">NI (75%)</label>
                            <input type="radio" name="quality_completeness" value="80" id="quality_completeness_80">
                            <label for="quality_completeness_80">F (80%)</label>
                            <input type="radio" name="quality_completeness" value="85" id="quality_completeness_85">
                            <label for="quality_completeness_85">G (85%)</label>
                            <input type="radio" name="quality_completeness" value="90" id="quality_completeness_90">
                            <label for="quality_completeness_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="quality_completeness_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>2.3 Reliability</strong></label>
                        <p class="text-muted small">Consistency and dependability in performance</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="quality_reliability" value="70" id="quality_reliability_70" required>
                            <label for="quality_reliability_70">P (70%)</label>
                            <input type="radio" name="quality_reliability" value="75" id="quality_reliability_75">
                            <label for="quality_reliability_75">NI (75%)</label>
                            <input type="radio" name="quality_reliability" value="80" id="quality_reliability_80">
                            <label for="quality_reliability_80">F (80%)</label>
                            <input type="radio" name="quality_reliability" value="85" id="quality_reliability_85">
                            <label for="quality_reliability_85">G (85%)</label>
                            <input type="radio" name="quality_reliability" value="90" id="quality_reliability_90">
                            <label for="quality_reliability_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="quality_reliability_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Productivity -->
        <div class="evaluation-section">
            <h6 class="section-title">3. Productivity</h6>
            
            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>3.1 Time Management</strong></label>
                        <p class="text-muted small">Effective use of time and punctuality</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="productivity_time" value="70" id="productivity_time_70" required>
                            <label for="productivity_time_70">P (70%)</label>
                            <input type="radio" name="productivity_time" value="75" id="productivity_time_75">
                            <label for="productivity_time_75">NI (75%)</label>
                            <input type="radio" name="productivity_time" value="80" id="productivity_time_80">
                            <label for="productivity_time_80">F (80%)</label>
                            <input type="radio" name="productivity_time" value="85" id="productivity_time_85">
                            <label for="productivity_time_85">G (85%)</label>
                            <input type="radio" name="productivity_time" value="90" id="productivity_time_90">
                            <label for="productivity_time_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="productivity_time_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>3.2 Utilization of Resources</strong></label>
                        <p class="text-muted small">Efficient use of available resources</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="productivity_output" value="70" id="productivity_output_70" required>
                            <label for="productivity_output_70">P (70%)</label>
                            <input type="radio" name="productivity_output" value="75" id="productivity_output_75">
                            <label for="productivity_output_75">NI (75%)</label>
                            <input type="radio" name="productivity_output" value="80" id="productivity_output_80">
                            <label for="productivity_output_80">F (80%)</label>
                            <input type="radio" name="productivity_output" value="85" id="productivity_output_85">
                            <label for="productivity_output_85">G (85%)</label>
                            <input type="radio" name="productivity_output" value="90" id="productivity_output_90">
                            <label for="productivity_output_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="productivity_output_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>3.3 Priority Setting</strong></label>
                        <p class="text-muted small">Ability to prioritize tasks effectively</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="productivity_priority" value="70" id="productivity_priority_70" required>
                            <label for="productivity_priority_70">P (70%)</label>
                            <input type="radio" name="productivity_priority" value="75" id="productivity_priority_75">
                            <label for="productivity_priority_75">NI (75%)</label>
                            <input type="radio" name="productivity_priority" value="80" id="productivity_priority_80">
                            <label for="productivity_priority_80">F (80%)</label>
                            <input type="radio" name="productivity_priority" value="85" id="productivity_priority_85">
                            <label for="productivity_priority_85">G (85%)</label>
                            <input type="radio" name="productivity_priority" value="90" id="productivity_priority_90">
                            <label for="productivity_priority_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="productivity_priority_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Diligence and Professional Approach -->
        <div class="evaluation-section">
            <h6 class="section-title">4. Diligence and Professional Approach</h6>
            
            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>4.1 Follows Instructions</strong></label>
                        <p class="text-muted small">Adherence to directives and guidelines</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="diligence_instructions" value="70" id="diligence_instructions_70" required>
                            <label for="diligence_instructions_70">P (70%)</label>
                            <input type="radio" name="diligence_instructions" value="75" id="diligence_instructions_75">
                            <label for="diligence_instructions_75">NI (75%)</label>
                            <input type="radio" name="diligence_instructions" value="80" id="diligence_instructions_80">
                            <label for="diligence_instructions_80">F (80%)</label>
                            <input type="radio" name="diligence_instructions" value="85" id="diligence_instructions_85">
                            <label for="diligence_instructions_85">G (85%)</label>
                            <input type="radio" name="diligence_instructions" value="90" id="diligence_instructions_90">
                            <label for="diligence_instructions_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="diligence_instructions_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>4.2 Flexibility / Adaptable</strong></label>
                        <p class="text-muted small">Adaptability to changing situations</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="diligence_flexibility" value="70" id="diligence_flexibility_70" required>
                            <label for="diligence_flexibility_70">P (70%)</label>
                            <input type="radio" name="diligence_flexibility" value="75" id="diligence_flexibility_75">
                            <label for="diligence_flexibility_75">NI (75%)</label>
                            <input type="radio" name="diligence_flexibility" value="80" id="diligence_flexibility_80">
                            <label for="diligence_flexibility_80">F (80%)</label>
                            <input type="radio" name="diligence_flexibility" value="85" id="diligence_flexibility_85">
                            <label for="diligence_flexibility_85">G (85%)</label>
                            <input type="radio" name="diligence_flexibility" value="90" id="diligence_flexibility_90">
                            <label for="diligence_flexibility_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="diligence_flexibility_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>4.3 Customer Focus / Responsiveness</strong></label>
                        <p class="text-muted small">Responsiveness to service requests</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="diligence_customer" value="70" id="diligence_customer_70" required>
                            <label for="diligence_customer_70">P (70%)</label>
                            <input type="radio" name="diligence_customer" value="75" id="diligence_customer_75">
                            <label for="diligence_customer_75">NI (75%)</label>
                            <input type="radio" name="diligence_customer" value="80" id="diligence_customer_80">
                            <label for="diligence_customer_80">F (80%)</label>
                            <input type="radio" name="diligence_customer" value="85" id="diligence_customer_85">
                            <label for="diligence_customer_85">G (85%)</label>
                            <input type="radio" name="diligence_customer" value="90" id="diligence_customer_90">
                            <label for="diligence_customer_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="diligence_customer_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>4.4 Attendance</strong></label>
                        <p class="text-muted small">Punctuality and attendance record</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="attendance_presence" value="70" id="attendance_presence_70" required>
                            <label for="attendance_presence_70">P (70%)</label>
                            <input type="radio" name="attendance_presence" value="75" id="attendance_presence_75">
                            <label for="attendance_presence_75">NI (75%)</label>
                            <input type="radio" name="attendance_presence" value="80" id="attendance_presence_80">
                            <label for="attendance_presence_80">F (80%)</label>
                            <input type="radio" name="attendance_presence" value="85" id="attendance_presence_85">
                            <label for="attendance_presence_85">G (85%)</label>
                            <input type="radio" name="attendance_presence" value="90" id="attendance_presence_90">
                            <label for="attendance_presence_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="attendance_presence_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>4.5 Compliance to Rules and Policies</strong></label>
                        <p class="text-muted small">Adherence to company policies</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="attendance_punctuality" value="70" id="attendance_punctuality_70" required>
                            <label for="attendance_punctuality_70">P (70%)</label>
                            <input type="radio" name="attendance_punctuality" value="75" id="attendance_punctuality_75">
                            <label for="attendance_punctuality_75">NI (75%)</label>
                            <input type="radio" name="attendance_punctuality" value="80" id="attendance_punctuality_80">
                            <label for="attendance_punctuality_80">F (80%)</label>
                            <input type="radio" name="attendance_punctuality" value="85" id="attendance_punctuality_85">
                            <label for="attendance_punctuality_85">G (85%)</label>
                            <input type="radio" name="attendance_punctuality" value="90" id="attendance_punctuality_90">
                            <label for="attendance_punctuality_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="attendance_punctuality_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Work Attitude -->
        <div class="evaluation-section">
            <h6 class="section-title">5. Work Attitude</h6>
            
            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>5.1 Team Cooperation</strong></label>
                        <p class="text-muted small">Collaboration and teamwork</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="interpersonal_communication" value="70" id="interpersonal_communication_70" required>
                            <label for="interpersonal_communication_70">P (70%)</label>
                            <input type="radio" name="interpersonal_communication" value="75" id="interpersonal_communication_75">
                            <label for="interpersonal_communication_75">NI (75%)</label>
                            <input type="radio" name="interpersonal_communication" value="80" id="interpersonal_communication_80">
                            <label for="interpersonal_communication_80">F (80%)</label>
                            <input type="radio" name="interpersonal_communication" value="85" id="interpersonal_communication_85">
                            <label for="interpersonal_communication_85">G (85%)</label>
                            <input type="radio" name="interpersonal_communication" value="90" id="interpersonal_communication_90">
                            <label for="interpersonal_communication_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="interpersonal_communication_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>5.2 Respect to Co-workers and Superiors</strong></label>
                        <p class="text-muted small">Professional relationships and respect</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="interpersonal_teamwork" value="70" id="interpersonal_teamwork_70" required>
                            <label for="interpersonal_teamwork_70">P (70%)</label>
                            <input type="radio" name="interpersonal_teamwork" value="75" id="interpersonal_teamwork_75">
                            <label for="interpersonal_teamwork_75">NI (75%)</label>
                            <input type="radio" name="interpersonal_teamwork" value="80" id="interpersonal_teamwork_80">
                            <label for="interpersonal_teamwork_80">F (80%)</label>
                            <input type="radio" name="interpersonal_teamwork" value="85" id="interpersonal_teamwork_85">
                            <label for="interpersonal_teamwork_85">G (85%)</label>
                            <input type="radio" name="interpersonal_teamwork" value="90" id="interpersonal_teamwork_90">
                            <label for="interpersonal_teamwork_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="interpersonal_teamwork_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="evaluation-item">
                <div class="row">
                    <div class="col-md-4">
                        <label><strong>5.3 Conduct and Behavior</strong></label>
                        <p class="text-muted small">Professional conduct and behavior</p>
                    </div>
                    <div class="col-md-5">
                        <div class="rating-options">
                            <input type="radio" name="attitude_conduct" value="70" id="attitude_conduct_70" required>
                            <label for="attitude_conduct_70">P (70%)</label>
                            <input type="radio" name="attitude_conduct" value="75" id="attitude_conduct_75">
                            <label for="attitude_conduct_75">NI (75%)</label>
                            <input type="radio" name="attitude_conduct" value="80" id="attitude_conduct_80">
                            <label for="attitude_conduct_80">F (80%)</label>
                            <input type="radio" name="attitude_conduct" value="85" id="attitude_conduct_85">
                            <label for="attitude_conduct_85">G (85%)</label>
                            <input type="radio" name="attitude_conduct" value="90" id="attitude_conduct_90">
                            <label for="attitude_conduct_90">O (90%)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <textarea class="form-control" name="attitude_conduct_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overall Rating Section -->
        <div class="evaluation-section">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="material-icons">calculate</i> Overall Rating
                        <small class="text-muted">(Calculated automatically when all criteria are rated)</small>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Average Percentage Rating</label>
                                <input type="number" class="form-control" id="average_rating" name="average_rating" readonly 
                                       placeholder="Complete all ratings above">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Overall Performance Rating</label>
                                <input type="text" class="form-control" id="overall_performance" name="overall_performance" readonly 
                                       placeholder="Complete all ratings above">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommendation Section -->
        <div class="evaluation-section">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="material-icons">recommend</i> Recommendation</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="recommendation" value="renewal" id="recommendation_renewal">
                            <label class="form-check-label" for="recommendation_renewal">
                                For renewal of service contract
                            </label>
                        </div>
                         <div class="mb-3">
                            <label class="form-label">Term of Contract (if renewal)</label>
                            <input type="text" class="form-control" name="contract_term" placeholder="e.g., 1 year">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="recommendation" value="termination" id="recommendation_termination">
                            <label class="form-check-label" for="recommendation_termination">
                                For pull-out termination of service contract
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="recommendation" value="others" id="recommendation_others">
                            <label class="form-check-label" for="recommendation_others">
                                Others, please state:
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <textarea class="form-control" name="other_recommendation" rows="3" placeholder="Please specify other recommendations"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Evaluator Information -->
        <div class="evaluation-section">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="material-icons">person</i> Evaluator Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Evaluated by</label>
                                <input type="text" class="form-control" name="evaluated_by" value="<?php echo htmlspecialchars($oicName); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">GMSAI Representative</label>
                                <input type="text" class="form-control" name="gmsai_representative" value="<?php echo htmlspecialchars($oicName); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Client's Representative</label>
                                <input type="text" class="form-control" name="client_representative" placeholder="Enter client representative name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Evaluation Date</label>
                                <input type="date" class="form-control" name="evaluation_date" value="<?php echo date('Y-m-d'); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-footer mt-4">
            <div class="row">
                <div class="col-md-6">
                    <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">
                        <i class="material-icons">cancel</i> Cancel
                    </button>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="material-icons">save</i> Submit Evaluation
                    </button>
                </div>
            </div>
            <div class="mt-2">
                <small class="text-muted">
                    <i class="material-icons" style="vertical-align: middle; font-size: 16px;">info</i>
                    All fields marked with ratings are required
                </small>
            </div>
        </div>
    </form>
</div>

<script>
// Auto-calculate rating when all fields are completed
$('input[type="radio"]').on('change', function() {
    checkAndCalculateRating();
});

function checkAndCalculateRating() {
    const requiredRatings = [
        'tech_job_knowledge', 'tech_tool_competency', 'tech_safety_procedure',
        'quality_accuracy', 'quality_completeness', 'quality_reliability',
        'productivity_time', 'productivity_output', 'productivity_priority',
        'diligence_instructions', 'diligence_flexibility', 'diligence_customer',
        'attendance_presence', 'attendance_punctuality',
        'interpersonal_communication', 'interpersonal_teamwork', 'attitude_conduct'
    ];
    
    let allCompleted = true;
    let completedCount = 0;
    
    requiredRatings.forEach(function(rating) {
        if ($('input[name="' + rating + '"]:checked').length) {
            completedCount++;
        } else {
            allCompleted = false;
        }
    });
    
    if (allCompleted) {
        // All 17 ratings completed, calculate automatically
        const ratingInputs = document.querySelectorAll('input[type="radio"]:checked');
        let totalScore = 0;
        
        ratingInputs.forEach(input => {
            totalScore += parseInt(input.value);
        });
        
        const averageRating = totalScore / requiredRatings.length; // Use 17 for exact calculation
        document.getElementById('average_rating').value = averageRating.toFixed(2);
        
        // Determine performance rating based on grading scale
        let performanceRating = '';
        if (averageRating >= 90) {
            performanceRating = 'Outstanding (O)';
        } else if (averageRating >= 85) {
            performanceRating = 'Good (G)';
        } else if (averageRating >= 80) {
            performanceRating = 'Fair (F)';
        } else if (averageRating >= 75) {
            performanceRating = 'Needs Improvement (NI)';
        } else {
            performanceRating = 'Poor (P)';
        }
        
        document.getElementById('overall_performance').value = performanceRating;
        
        // Show a brief notification
        Swal.fire({
            icon: 'success',
            title: 'Rating Calculated',
            text: `Overall Rating: ${averageRating.toFixed(2)}% (${performanceRating})`,
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } else {
        // Clear the calculated values if not all completed
        document.getElementById('average_rating').value = '';
        document.getElementById('overall_performance').value = '';
        
        // Optionally show progress
        if (completedCount > 0) {
            const progressPercent = ((completedCount / requiredRatings.length) * 100).toFixed(0);
            document.getElementById('average_rating').placeholder = `Progress: ${completedCount}/${requiredRatings.length} (${progressPercent}%)`;
        }
    }
}
</script>