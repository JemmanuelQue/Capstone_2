<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

// Set content type for JSON response
header('Content-Type: application/json');

// Enforce OIC role (8)
if (!validateSession($conn, 8, false)) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Get form data
    $guard_id = $_POST['guard_id'] ?? null;
    $evaluator_id = $_POST['evaluator_id'] ?? null;
    $evaluation_period = $_POST['evaluation_period'] ?? '';
    
    // New evaluation form fields
    $average_rating = $_POST['average_rating'] ?? '';
    $overall_performance = $_POST['overall_performance'] ?? '';
    $recommendation = $_POST['recommendation'] ?? '';
    $contract_term = $_POST['contract_term'] ?? '';
    $other_recommendation = $_POST['other_recommendation'] ?? '';
    $evaluated_by = $_POST['evaluated_by'] ?? '';
    $gmsai_representative = $_POST['gmsai_representative'] ?? '';
    $client_representative = $_POST['client_representative'] ?? '';
    $evaluation_date = $_POST['evaluation_date'] ?? date('Y-m-d');
    
    // Rating scores - updated to match HR form structure
    $ratings = [
        'tech_job_knowledge' => $_POST['tech_job_knowledge'] ?? null,
        'tech_tool_competency' => $_POST['tech_tool_competency'] ?? null,
        'tech_safety_procedure' => $_POST['tech_safety_procedure'] ?? null,
        'quality_accuracy' => $_POST['quality_accuracy'] ?? null,
        'quality_completeness' => $_POST['quality_completeness'] ?? null,
        'quality_reliability' => $_POST['quality_reliability'] ?? null,
        'productivity_time' => $_POST['productivity_time'] ?? null,
        'productivity_output' => $_POST['productivity_output'] ?? null,
        'productivity_priority' => $_POST['productivity_priority'] ?? null,
        'diligence_instructions' => $_POST['diligence_instructions'] ?? null,
        'diligence_flexibility' => $_POST['diligence_flexibility'] ?? null,
        'diligence_customer' => $_POST['diligence_customer'] ?? null,
        'attendance_presence' => $_POST['attendance_presence'] ?? null,
        'attendance_punctuality' => $_POST['attendance_punctuality'] ?? null,
        'interpersonal_communication' => $_POST['interpersonal_communication'] ?? null,
        'interpersonal_teamwork' => $_POST['interpersonal_teamwork'] ?? null,
        'attitude_conduct' => $_POST['attitude_conduct'] ?? null
    ];
    
    // Comments - updated to match new criteria
    $comments = [
        'tech_job_knowledge_comments' => $_POST['tech_job_knowledge_comments'] ?? '',
        'tech_tool_competency_comments' => $_POST['tech_tool_competency_comments'] ?? '',
        'tech_safety_procedure_comments' => $_POST['tech_safety_procedure_comments'] ?? '',
        'quality_accuracy_comments' => $_POST['quality_accuracy_comments'] ?? '',
        'quality_completeness_comments' => $_POST['quality_completeness_comments'] ?? '',
        'quality_reliability_comments' => $_POST['quality_reliability_comments'] ?? '',
        'productivity_time_comments' => $_POST['productivity_time_comments'] ?? '',
        'productivity_output_comments' => $_POST['productivity_output_comments'] ?? '',
        'productivity_priority_comments' => $_POST['productivity_priority_comments'] ?? '',
        'diligence_instructions_comments' => $_POST['diligence_instructions_comments'] ?? '',
        'diligence_flexibility_comments' => $_POST['diligence_flexibility_comments'] ?? '',
        'diligence_customer_comments' => $_POST['diligence_customer_comments'] ?? '',
        'attendance_presence_comments' => $_POST['attendance_presence_comments'] ?? '',
        'attendance_punctuality_comments' => $_POST['attendance_punctuality_comments'] ?? '',
        'interpersonal_communication_comments' => $_POST['interpersonal_communication_comments'] ?? '',
        'interpersonal_teamwork_comments' => $_POST['interpersonal_teamwork_comments'] ?? '',
        'attitude_conduct_comments' => $_POST['attitude_conduct_comments'] ?? ''
    ];
    
    // Validate required fields
    if (!$guard_id || !$evaluator_id || !$evaluation_period || !$recommendation) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields'
        ]);
        exit;
    }
    
    // Validate all ratings are provided
    foreach ($ratings as $key => $value) {
        if ($value === null || $value === '') {
            echo json_encode([
                'success' => false,
                'message' => 'All rating criteria must be completed'
            ]);
            exit;
        }
    }
    
    // Verify the evaluator is an OIC and has permission to evaluate this guard
    $verifyQuery = "
        SELECT u.User_ID, u.First_Name, u.Last_Name, ol.location_name as oic_location, gl.location_name as guard_location
        FROM users u
        INNER JOIN oic_locations ol ON u.User_ID = ol.oic_user_id AND ol.is_active = 1
        INNER JOIN users g ON g.User_ID = ? AND g.Role_ID = 5 AND g.status = 'Active'
        INNER JOIN guard_locations gl ON g.User_ID = gl.user_id AND gl.is_active = 1
        WHERE u.User_ID = ? AND u.Role_ID = 8 AND u.status = 'Active'
        AND ol.location_name = gl.location_name
    ";
    
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->execute([$guard_id, $evaluator_id]);
    $verification = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verification) {
        echo json_encode([
            'success' => false,
            'message' => 'You are not authorized to evaluate this guard or the guard is not in your assigned location'
        ]);
        exit;
    }
    
    // Calculate overall rating if not provided (average of all ratings)
    if (empty($average_rating)) {
        $totalScore = array_sum($ratings);
        $ratingCount = count($ratings);
        $average_rating = round($totalScore / $ratingCount, 2);
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Get guard name for logging
    $guardQuery = "SELECT CONCAT(First_Name, ' ', Last_Name) as guard_name FROM users WHERE User_ID = ?";
    $guardStmt = $conn->prepare($guardQuery);
    $guardStmt->execute([$guard_id]);
    $guardInfo = $guardStmt->fetch(PDO::FETCH_ASSOC);
    $guardName = $guardInfo['guard_name'];
    
    // Insert the performance evaluation
    $insertEvalQuery = "
        INSERT INTO performance_evaluations (
            user_id, evaluator_id, evaluation_date, evaluation_period, overall_rating, overall_performance,
            recommendation, contract_term, other_recommendation, evaluated_by, client_representative, gmsai_representative,
            employee_name, position, area_assigned, status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, NOW()
        )
    ";

    $insertEvalStmt = $conn->prepare($insertEvalQuery);
    $insertEvalStmt->execute([
        $guard_id,
        $evaluator_id,
        $evaluation_date,
        $evaluation_period,
        $average_rating,
        $overall_performance,
        $recommendation,
        $contract_term,
        $other_recommendation,
        $evaluated_by,
        $client_representative,
        $gmsai_representative,
        $guardName, // employee_name
        'Security Guard', // position
        $verification['guard_location'], // area_assigned
        'Completed' // status
    ]);

    $evaluation_id = $conn->lastInsertId();
    
    // Insert detailed ratings into evaluation_ratings table (if it exists)
    try {
        $ratingInsertQuery = "
            INSERT INTO evaluation_ratings (
                evaluation_id, 
                tech_job_knowledge, tech_tool_competency, tech_safety_procedure,
                quality_accuracy, quality_completeness, quality_reliability,
                productivity_time, productivity_output, productivity_priority,
                diligence_instructions, diligence_flexibility, diligence_customer,
                attendance_presence, attendance_punctuality,
                interpersonal_communication, interpersonal_teamwork, attitude_conduct,
                tech_job_knowledge_comments, tech_tool_competency_comments, tech_safety_procedure_comments,
                quality_accuracy_comments, quality_completeness_comments, quality_reliability_comments,
                productivity_time_comments, productivity_output_comments, productivity_priority_comments,
                diligence_instructions_comments, diligence_flexibility_comments, diligence_customer_comments,
                attendance_presence_comments, attendance_punctuality_comments,
                interpersonal_communication_comments, interpersonal_teamwork_comments, attitude_conduct_comments
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ";
        
        $ratingStmt = $conn->prepare($ratingInsertQuery);
        $ratingStmt->execute([
            $evaluation_id,
            // Rating values (17 criteria)
            $ratings['tech_job_knowledge'], $ratings['tech_tool_competency'], $ratings['tech_safety_procedure'],
            $ratings['quality_accuracy'], $ratings['quality_completeness'], $ratings['quality_reliability'],
            $ratings['productivity_time'], $ratings['productivity_output'], $ratings['productivity_priority'],
            $ratings['diligence_instructions'], $ratings['diligence_flexibility'], $ratings['diligence_customer'],
            $ratings['attendance_presence'], $ratings['attendance_punctuality'],
            $ratings['interpersonal_communication'], $ratings['interpersonal_teamwork'], $ratings['attitude_conduct'],
            // Comment values (17 criteria)
            $comments['tech_job_knowledge_comments'], $comments['tech_tool_competency_comments'], $comments['tech_safety_procedure_comments'],
            $comments['quality_accuracy_comments'], $comments['quality_completeness_comments'], $comments['quality_reliability_comments'],
            $comments['productivity_time_comments'], $comments['productivity_output_comments'], $comments['productivity_priority_comments'],
            $comments['diligence_instructions_comments'], $comments['diligence_flexibility_comments'], $comments['diligence_customer_comments'],
            $comments['attendance_presence_comments'], $comments['attendance_punctuality_comments'],
            $comments['interpersonal_communication_comments'], $comments['interpersonal_teamwork_comments'], $comments['attitude_conduct_comments']
        ]);
    } catch (Exception $e) {
        // evaluation_ratings table might not exist, continue without it
    }
    
    // Log the activity
    $activityQuery = "
        INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) 
        VALUES (?, 'Performance Evaluation', ?, NOW())
    ";
    $activityStmt = $conn->prepare($activityQuery);
    $activityDetails = "OIC {$evaluated_by} completed performance evaluation for guard {$guardName} (Overall Rating: {$average_rating}% - {$overall_performance})";
    $activityStmt->execute([$evaluator_id, $activityDetails]);
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Performance evaluation for {$guardName} has been successfully submitted with an overall rating of {$average_rating}% ({$overall_performance})",
        'data' => [
            'guard_name' => $guardName,
            'overall_rating' => $average_rating,
            'overall_performance' => $overall_performance,
            'evaluation_id' => $evaluation_id
        ]
    ]);
    exit;
    
} catch (Exception $e) {
    // Rollback transaction
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error submitting evaluation: ' . $e->getMessage()
    ]);
    exit;
}
?>