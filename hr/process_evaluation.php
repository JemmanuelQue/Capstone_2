<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
// Enforce HR role (3)
if (!validateSession($conn, 3, false)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get form data
    $guard_id = $_POST['guard_id'] ?? null;
    $employee_name = $_POST['employee_name'] ?? '';
    $client_assignment = $_POST['client_assignment'] ?? '';
    $position = $_POST['position'] ?? 'Security Guard';
    $area_assigned = $_POST['area_assigned'] ?? '';
    $evaluation_period = $_POST['evaluation_period'] ?? '';
    
    // Rating scores
    $ratings = [
        'tech_job_knowledge' => $_POST['tech_job_knowledge'] ?? null,
        'tech_tool_competency' => $_POST['tech_tool_competency'] ?? null,
        'tech_safety_procedure' => $_POST['tech_safety_procedure'] ?? null,
        'quality_accuracy' => $_POST['quality_accuracy'] ?? null,
        'quality_completeness' => $_POST['quality_completeness'] ?? null,
        'quality_reliability' => $_POST['quality_reliability'] ?? null,
        'productivity_time_management' => $_POST['productivity_time_management'] ?? null,
        'productivity_resource_utilization' => $_POST['productivity_resource_utilization'] ?? null,
        'productivity_priority_setting' => $_POST['productivity_priority_setting'] ?? null,
        'diligence_follows_instructions' => $_POST['diligence_follows_instructions'] ?? null,
        'diligence_flexibility' => $_POST['diligence_flexibility'] ?? null,
        'diligence_customer_focus' => $_POST['diligence_customer_focus'] ?? null,
        'diligence_attendance' => $_POST['diligence_attendance'] ?? null,
        'diligence_compliance' => $_POST['diligence_compliance'] ?? null,
        'attitude_team_cooperation' => $_POST['attitude_team_cooperation'] ?? null,
        'attitude_respect' => $_POST['attitude_respect'] ?? null,
        'attitude_conduct' => $_POST['attitude_conduct'] ?? null
    ];
    
    // Comments
    $comments = [
        'tech_job_knowledge_comments' => $_POST['tech_job_knowledge_comments'] ?? '',
        'tech_tool_competency_comments' => $_POST['tech_tool_competency_comments'] ?? '',
        'tech_safety_procedure_comments' => $_POST['tech_safety_procedure_comments'] ?? '',
        'quality_accuracy_comments' => $_POST['quality_accuracy_comments'] ?? '',
        'quality_completeness_comments' => $_POST['quality_completeness_comments'] ?? '',
        'quality_reliability_comments' => $_POST['quality_reliability_comments'] ?? '',
        'productivity_time_management_comments' => $_POST['productivity_time_management_comments'] ?? '',
        'productivity_resource_utilization_comments' => $_POST['productivity_resource_utilization_comments'] ?? '',
        'productivity_priority_setting_comments' => $_POST['productivity_priority_setting_comments'] ?? '',
        'diligence_follows_instructions_comments' => $_POST['diligence_follows_instructions_comments'] ?? '',
        'diligence_flexibility_comments' => $_POST['diligence_flexibility_comments'] ?? '',
        'diligence_customer_focus_comments' => $_POST['diligence_customer_focus_comments'] ?? '',
        'diligence_attendance_comments' => $_POST['diligence_attendance_comments'] ?? '',
        'diligence_compliance_comments' => $_POST['diligence_compliance_comments'] ?? '',
        'attitude_team_cooperation_comments' => $_POST['attitude_team_cooperation_comments'] ?? '',
        'attitude_respect_comments' => $_POST['attitude_respect_comments'] ?? '',
        'attitude_conduct_comments' => $_POST['attitude_conduct_comments'] ?? ''
    ];
    
    // Overall rating and recommendation
    $average_rating = $_POST['average_rating'] ?? null;
    $overall_performance = $_POST['overall_performance'] ?? '';
    $recommendation = $_POST['recommendation'] ?? '';
    $contract_term = $_POST['contract_term'] ?? '';
    $other_recommendation = $_POST['other_recommendation'] ?? '';
    
    // Evaluator information
    $evaluated_by = $_POST['evaluated_by'] ?? '';
    $client_representative = $_POST['client_representative'] ?? '';
    $gmsai_representative = $_POST['gmsai_representative'] ?? '';
    $evaluation_date = $_POST['evaluation_date'] ?? date('Y-m-d');
    
    // Validate required fields
    // client_assignment is optional (readonly field); keep period and client representative required
    if (!$guard_id || !$evaluation_period || !$client_representative) {
        throw new Exception('Missing required fields');
    }
    
    // Validate all ratings are provided
    foreach ($ratings as $key => $value) {
        if ($value === null) {
            throw new Exception('All rating criteria must be completed');
        }
    }
    
    if (!$average_rating) {
        throw new Exception('Overall rating must be calculated');
    }
    
    $conn->beginTransaction();
    
    // Insert main evaluation record
    $evaluationQuery = "
        INSERT INTO performance_evaluations (
            user_id, evaluator_id, employee_name, client_assignment, position, 
            area_assigned, evaluation_period, overall_rating, overall_performance,
            recommendation, contract_term, other_recommendation, evaluated_by,
            client_representative, gmsai_representative, evaluation_date,
            status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', NOW()
        )
    ";
    
    $evaluationStmt = $conn->prepare($evaluationQuery);
    $evaluationStmt->execute([
        $guard_id, $_SESSION['user_id'], $employee_name, $client_assignment, $position,
        $area_assigned, $evaluation_period, $average_rating, $overall_performance,
        $recommendation, $contract_term, $other_recommendation, $evaluated_by,
        $client_representative, $gmsai_representative, $evaluation_date
    ]);
    
    $evaluation_id = $conn->lastInsertId();
    
    // Insert detailed ratings
    $ratingQuery = "
        INSERT INTO evaluation_ratings (
            evaluation_id, criterion_name, rating_score, comments
        ) VALUES (?, ?, ?, ?)
    ";
    
    $ratingStmt = $conn->prepare($ratingQuery);
    
    // Define criterion names mapping
    $criterionNames = [
        'tech_job_knowledge' => 'Technical Skills - Job Knowledge',
        'tech_tool_competency' => 'Technical Skills - Tool Competency',
        'tech_safety_procedure' => 'Technical Skills - Safety Procedure',
        'quality_accuracy' => 'Quality - Accuracy',
        'quality_completeness' => 'Quality - Completeness/Orderliness',
        'quality_reliability' => 'Quality - Reliability',
        'productivity_time_management' => 'Productivity - Time Management',
        'productivity_resource_utilization' => 'Productivity - Resource Utilization',
        'productivity_priority_setting' => 'Productivity - Priority Setting',
        'diligence_follows_instructions' => 'Diligence - Follows Instructions',
        'diligence_flexibility' => 'Diligence - Flexibility/Adaptable',
        'diligence_customer_focus' => 'Diligence - Customer Focus',
        'diligence_attendance' => 'Diligence - Attendance',
        'diligence_compliance' => 'Diligence - Compliance',
        'attitude_team_cooperation' => 'Work Attitude - Team Cooperation',
        'attitude_respect' => 'Work Attitude - Respect',
        'attitude_conduct' => 'Work Attitude - Conduct and Behavior'
    ];
    
    // Insert each rating
    foreach ($ratings as $key => $score) {
        $criterionName = $criterionNames[$key];
        $comment = $comments[$key . '_comments'] ?? '';
        
        $ratingStmt->execute([
            $evaluation_id, $criterionName, $score, $comment
        ]);
    }
    
    // Log the evaluation action (align with existing activity_logs schema)
    $logQuery = "
        INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp)
        VALUES (?, 'Performance Evaluation', ?, NOW())
    ";
    $logDetails = "Completed performance evaluation for User ID: $guard_id ($employee_name) with overall rating: $average_rating%";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->execute([$_SESSION['user_id'], $logDetails]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Performance evaluation submitted successfully',
        'evaluation_id' => $evaluation_id
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    error_log("Performance Evaluation Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
