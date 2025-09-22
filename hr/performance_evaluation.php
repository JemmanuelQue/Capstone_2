<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php'; // assumes $conn is PDO
// Enforce HR role (3)
if (!validateSession($conn, 3)) { exit; }

$currentDate = date('Y-m-d');

// Handle filter form submission
if (isset($_GET['filter_submit'])) {
    $employmentStatus = $_GET['employment_status'] ?? 'all';
    $evaluationStatus = $_GET['evaluation_status'] ?? 'all';
    $searchTerm = isset($_GET['guardSearch']) ? trim($_GET['guardSearch']) : '';
    $locationFilter = isset($_GET['location']) ? $_GET['location'] : '';
} else {
    // Default values
    $employmentStatus = 'all';
    $evaluationStatus = 'all';
    $searchTerm = '';
    $locationFilter = '';
}

// Get current HR user's name
$hrStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 3 AND status = 'Active' AND User_ID = ?");
$hrStmt->execute([$_SESSION['user_id']]);
$hrData = $hrStmt->fetch(PDO::FETCH_ASSOC);
$hrName = $hrData ? $hrData['First_Name'] . ' ' . $hrData['Last_Name'] : "Human Resource";

// Get HR's profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $hrProfile = $profileData['Profile_Pic'];
} else {
    $hrProfile = '../images/default_profile.png';
}

// Get all unique location names for filter dropdown
$locationsQuery = "SELECT DISTINCT location_name FROM guard_locations WHERE location_name != '' ORDER BY location_name";
$locationsStmt = $conn->prepare($locationsQuery);
$locationsStmt->execute();
$locations = $locationsStmt->fetchAll(PDO::FETCH_COLUMN);

// Function to determine employment status based on hired date
function getEmploymentStatus($hiredDate) {
    if (!$hiredDate) return 'Unknown';
    
    $hiredDateTime = new DateTime($hiredDate);
    $currentDateTime = new DateTime();
    $interval = $hiredDateTime->diff($currentDateTime);
    $monthsDiff = ($interval->y * 12) + $interval->m;
    
    return $monthsDiff >= 6 ? 'Regular' : 'Probationary';
}

// Function to determine evaluation status and next due date
function getEvaluationStatus($hiredDate, $lastEvaluationDate, $employmentStatus) {
    if (!$hiredDate) return ['status' => 'Not Yet Started', 'next_due' => null];
    
    $currentDate = new DateTime();
    $hiredDateTime = new DateTime($hiredDate);
    
    if (!$lastEvaluationDate) {
        // No evaluation yet - check if it's time for first evaluation
        $interval = $hiredDateTime->diff($currentDate);
        $monthsSinceHired = ($interval->y * 12) + $interval->m;
        
        if ($employmentStatus === 'Probationary') {
            // Probationary: evaluated at 3 months and 6 months
            if ($monthsSinceHired >= 6) {
                $nextDue = clone $hiredDateTime;
                $nextDue->add(new DateInterval('P6M'));
                return ['status' => 'Overdue', 'next_due' => $nextDue];
            } elseif ($monthsSinceHired >= 3) {
                $nextDue = clone $hiredDateTime;
                $nextDue->add(new DateInterval('P3M'));
                return ['status' => 'Due', 'next_due' => $nextDue];
            } else {
                $nextDue = clone $hiredDateTime;
                $nextDue->add(new DateInterval('P3M'));
                return ['status' => 'Not Yet Started', 'next_due' => $nextDue];
            }
        } else {
            // Regular: evaluated annually
            if ($monthsSinceHired >= 12) {
                $nextDue = clone $hiredDateTime;
                $nextDue->add(new DateInterval('P12M'));
                return ['status' => 'Due', 'next_due' => $nextDue];
            } else {
                $nextDue = clone $hiredDateTime;
                $nextDue->add(new DateInterval('P12M'));
                return ['status' => 'Not Yet Started', 'next_due' => $nextDue];
            }
        }
    } else {
        // Has previous evaluation - check if next one is due
        $lastEvalDateTime = new DateTime($lastEvaluationDate);
        $interval = $lastEvalDateTime->diff($currentDate);
        $monthsSinceLastEval = ($interval->y * 12) + $interval->m;
        
        if ($employmentStatus === 'Probationary') {
            $nextDue = clone $lastEvalDateTime;
            $nextDue->add(new DateInterval('P3M'));
            if ($monthsSinceLastEval >= 3) {
                return ['status' => $currentDate > $nextDue ? 'Overdue' : 'Due', 'next_due' => $nextDue];
            } else {
                return ['status' => 'Completed', 'next_due' => $nextDue];
            }
        } else {
            $nextDue = clone $lastEvalDateTime;
            $nextDue->add(new DateInterval('P12M'));
            if ($monthsSinceLastEval >= 12) {
                return ['status' => $currentDate > $nextDue ? 'Overdue' : 'Due', 'next_due' => $nextDue];
            } else {
                return ['status' => 'Completed', 'next_due' => $nextDue];
            }
        }
    }
}

// Prepare search and filter conditions
$searchCondition = '';
$locationCondition = '';
$params = [];

if (!empty($searchTerm)) {
    $searchCondition = " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR CONCAT(u.First_Name, ' ', u.Last_Name) LIKE ?) ";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if (!empty($locationFilter)) {
    $locationCondition = " AND gl.location_name = ? ";
    $params[] = $locationFilter;
}

// Get guards data with their evaluation information
$guardsQuery = "
    SELECT 
        u.User_ID, 
        u.employee_id,
        u.First_Name, 
        u.Last_Name, 
        u.middle_name,
        u.hired_date,
        u.Created_At,
        gl.location_name,
        pe.evaluation_date as last_evaluation_date,
        pe.overall_rating,
        pe.status as eval_status,
        COUNT(pe2.evaluation_id) as total_evaluations
    FROM users u
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
    /* Get the latest completed evaluation per user with matching rating/status */
    LEFT JOIN (
        SELECT pe1.user_id, pe1.evaluation_date, pe1.overall_rating, pe1.status
        from performance_evaluations pe1
        INNER JOIN (
            SELECT user_id, MAX(evaluation_date) AS max_date
            FROM performance_evaluations
            WHERE status = 'Completed'
            GROUP BY user_id
        ) latest ON latest.user_id = pe1.user_id AND pe1.evaluation_date = latest.max_date
        WHERE pe1.status = 'Completed'
    ) pe ON u.User_ID = pe.user_id
    LEFT JOIN performance_evaluations pe2 ON u.User_ID = pe2.user_id AND pe2.status = 'Completed'
    WHERE u.Role_ID = 5 AND u.status = 'Active'
    $searchCondition
    $locationCondition
    GROUP BY u.User_ID, u.employee_id, u.First_Name, u.Last_Name, u.middle_name, u.hired_date, u.Created_At, gl.location_name, pe.evaluation_date, pe.overall_rating, pe.status
    ORDER BY u.Last_Name, u.First_Name
";

$guardsStmt = $conn->prepare($guardsQuery);

// Bind parameters
if (!empty($params)) {
    foreach ($params as $index => $param) {
        $guardsStmt->bindValue($index + 1, $param);
    }
}

$guardsStmt->execute();
$guards = $guardsStmt->fetchAll(PDO::FETCH_ASSOC);

// Process guards data and apply additional filters
$filteredGuards = [];
foreach ($guards as $guard) {
    $hiredDate = $guard['hired_date'] ?: $guard['Created_At']; // fallback to Created_At if hired_date is null
    $employmentStatusCalc = getEmploymentStatus($hiredDate);
    $evaluationInfo = getEvaluationStatus($hiredDate, $guard['last_evaluation_date'], $employmentStatusCalc);
    
    $guard['employment_status'] = $employmentStatusCalc;
    $guard['evaluation_status'] = $evaluationInfo['status'];
    $guard['next_due_date'] = $evaluationInfo['next_due'];
    $guard['effective_hired_date'] = $hiredDate;
    
    // Apply employment status filter
    if ($employmentStatus !== 'all' && $employmentStatusCalc !== $employmentStatus) {
        continue;
    }
    
    // Apply evaluation status filter
    if ($evaluationStatus !== 'all' && $evaluationInfo['status'] !== $evaluationStatus) {
        continue;
    }
    
    $filteredGuards[] = $guard;
}

// Count statistics
$totalGuards = count($filteredGuards);
$probationaryCount = count(array_filter($filteredGuards, function($g) { return $g['employment_status'] === 'Probationary'; }));
$regularCount = count(array_filter($filteredGuards, function($g) { return $g['employment_status'] === 'Regular'; }));
$dueCount = count(array_filter($filteredGuards, function($g) { return in_array($g['evaluation_status'], ['Due', 'Overdue']); }));
$overdueCount = count(array_filter($filteredGuards, function($g) { return $g['evaluation_status'] === 'Overdue'; }));
$completedCount = count(array_filter($filteredGuards, function($g) { return $g['evaluation_status'] === 'Completed'; }));
$notStartedCount = count(array_filter($filteredGuards, function($g) { return $g['evaluation_status'] === 'Not Yet Started'; }));
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Evaluation - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/performance_evaluation.css">
    
    <style>
        .badge.evaluation-due {
            background-color: #dc3545;
        }
        .badge.evaluation-complete {
            background-color: #198754;
        }
        .badge.evaluation-not-started {
            background-color: #6c757d;
        }
        .badge.status-probationary {
            background-color: #fd7e14;
        }
        .badge.status-regular {
            background-color: #0d6efd;
        }
        
        /* Performance Evaluation Form Styles */
        .evaluation-section {
            border-left: 4px solid #007bff;
            padding-left: 15px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .section-title {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .evaluation-item {
            margin-bottom: 15px;
            padding: 10px;
            background-color: white;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        
        .rating-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .rating-options input[type="radio"] {
            margin-right: 5px;
        }
        
        .rating-options label {
            margin-right: 15px;
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            white-space: nowrap;
        }
        
        .table-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
            border-color: #dc3545;
        }
        
        .table-danger td {
            border-color: rgba(220, 53, 69, 0.2);
        }
        
        .modal-xl {
            max-width: 95%;
        }
        
        /* Single-row filter toolbar */
        .filter-toolbar {
            display: flex;
            flex-wrap: nowrap; /* keep items on one line */
            gap: 1rem;
            align-items: flex-end;
            overflow-x: auto; /* scroll on small screens */
            white-space: nowrap;
            padding-bottom: .25rem;
        }
        .filter-toolbar .form-group { min-width: 220px; }
        .filter-toolbar .btn-group { flex: 0 0 auto; }
        .filter-toolbar::-webkit-scrollbar { height: 6px; }
        .filter-toolbar::-webkit-scrollbar-thumb { background: #ced4da; border-radius: 3px; }

        @media (max-width: 768px) {
            .rating-options {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .rating-options label {
                margin-right: 0;
                margin-bottom: 5px;
            }
        }
    </style>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div> SECURITY AGENCY</div>
            </div>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a href="hr_dashboard.php"class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="daily_time_record.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Daily Time Record">
                    <span class="material-icons">schedule</span>
                    <span>Daily Time Record</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="leave_request.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Leave Requests">
                    <span class="material-icons">event_note</span>
                    <span>Leave Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="recruitment.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Recruitment">
                    <span class="material-icons">person_add</span>
                    <span>Recruitment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="masterlist.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">assignment</span>
                    <span>Masterlist</span>
                </a>
            </li>
             <li class="nav-item">
                <a href="performance_evaluation.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">assessment</span>
                    <span>Performance Evaluation</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="archives.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Archives">
                    <span class="material-icons">archive</span>
                    <span>Archives</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logs.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Logs">
                    <span class="material-icons">receipt_long</span>
                    <span>Logs</span>
                </a>
            </li>
            <li class="nav-item mt-5">
                <a href="../logout.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout">
                    <span class="material-icons">logout</span>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Header -->
        <div class="header">
            <button class="toggle-sidebar" id="toggleSidebar">
                <span class="material-icons">menu</span>
            </button>
            <div class="current-datetime ms-3 d-none d-md-block">
                    <span id="current-date"></span> | <span id="current-time"></span>
                </div>
                <a href="profile.php" class="user-profile" id="userProfile" style="color:black; text-decoration:none;">
                    <span><?php echo htmlspecialchars($hrName); ?></span>
                    <img src="<?php echo $hrProfile; ?>" alt="User Profile">
                </a>
        </div>

    <!-- Main content area -->
    <div class="container-fluid mt-4">
        <h1 class="page-title">Performance Evaluation System</h1>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $totalGuards; ?></h4>
                                <p class="mb-0">Total Guards</p>
                            </div>
                            <div class="align-self-center">
                                <i class="material-icons" style="font-size: 2rem;">people</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $probationaryCount; ?></h4>
                                <p class="mb-0">Probationary</p>
                            </div>
                            <div class="align-self-center">
                                <i class="material-icons" style="font-size: 2rem;">schedule</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $regularCount; ?></h4>
                                <p class="mb-0">Regular</p>
                            </div>
                            <div class="align-self-center">
                                <i class="material-icons" style="font-size: 2rem;">verified</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $dueCount; ?></h4>
                                <p class="mb-0">Evaluations Due</p>
                            </div>
                            <div class="align-self-center">
                                <i class="material-icons" style="font-size: 2rem;">warning</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="material-icons">filter_list</i> Filters</h5>
            </div>
            <div class="card-body">
                <!-- replaced grid with single-row toolbar -->
                <form method="GET" class="filter-toolbar">
                    <div class="form-group">
                        <label for="employment_status" class="form-label mb-1">Employment Status</label>
                        <select class="form-select" id="employment_status" name="employment_status">
                            <option value="all" <?php echo $employmentStatus == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Probationary" <?php echo $employmentStatus == 'Probationary' ? 'selected' : ''; ?>>Probationary</option>
                            <option value="Regular" <?php echo $employmentStatus == 'Regular' ? 'selected' : ''; ?>>Regular</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="evaluation_status" class="form-label mb-1">Evaluation Status</label>
                        <select class="form-select" id="evaluation_status" name="evaluation_status">
                            <option value="all" <?php echo $evaluationStatus == 'all' ? 'selected' : ''; ?>>All Evaluations</option>
                            <option value="Due" <?php echo $evaluationStatus == 'Due' ? 'selected' : ''; ?>>Due</option>
                            <option value="Overdue" <?php echo $evaluationStatus == 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="Completed" <?php echo $evaluationStatus == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Not Yet Started" <?php echo $evaluationStatus == 'Not Yet Started' ? 'selected' : ''; ?>>Not Yet Started</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="location" class="form-label mb-1">Location</label>
                        <select class="form-select" id="location" name="location">
                            <option value="" <?php echo empty($locationFilter) ? 'selected' : ''; ?>>All Locations</option>
                            <?php foreach($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $locationFilter == $location ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="guardSearch" class="form-label mb-1">Search Guard</label>
                        <input type="text" class="form-control" id="guardSearch" name="guardSearch" placeholder="Enter guard name" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <div class="btn-group ms-auto">
                        <button type="submit" class="btn btn-success" name="filter_submit" value="1">
                            <span class="material-icons" style="vertical-align: middle; font-size: 18px;">search</span>
                            <span class="ms-1">Apply</span>
                        </button>
                        <a href="performance_evaluation.php" class="btn btn-secondary ms-2">
                            <span class="material-icons" style="vertical-align: middle; font-size: 18px;">clear</span>
                            <span class="ms-1">Clear</span>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Performance Evaluation Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="material-icons">assessment</i> Guards Performance Evaluation</h5>
                <div>
                    <button class="btn btn-success btn-sm" onclick="exportToExcel()">
                        <i class="material-icons">download</i> Export Excel
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="performanceTable" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Hired Date</th>
                                <th>Employment Status</th>
                                <th>Total Evaluations</th>
                                <th>Last Evaluation</th>
                                <th>Evaluation Status</th>
                                <th>Next Due</th>
                                <th>Rating</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredGuards as $guard): ?>
                                <?php 
                                    $rowClass = in_array($guard['evaluation_status'], ['Due', 'Overdue']) ? 'table-danger' : '';
                                    $statusBadgeClass = $guard['employment_status'] === 'Probationary' ? 'bg-warning' : 'bg-info';
                                    $evalBadgeClass = '';
                                    switch($guard['evaluation_status']) {
                                        case 'Due': $evalBadgeClass = 'bg-warning'; break;
                                        case 'Overdue': $evalBadgeClass = 'bg-danger'; break;
                                        case 'Completed': $evalBadgeClass = 'bg-success'; break;
                                        case 'Not Yet Started': $evalBadgeClass = 'bg-secondary'; break;
                                    }
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td><?php echo htmlspecialchars($guard['employee_id'] ?: sprintf("EMP%04d", $guard['User_ID'])); ?></td>
                                    <td>
                                        <?php 
                                            $fullName = trim($guard['First_Name'] . ' ' . $guard['middle_name'] . ' ' . $guard['Last_Name']);
                                            echo htmlspecialchars($fullName);
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($guard['location_name'] ?: 'Not Assigned'); ?></td>
                                    <td><?php echo $guard['effective_hired_date'] ? date('M d, Y', strtotime($guard['effective_hired_date'])) : 'Not Set'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadgeClass; ?>">
                                            <?php echo $guard['employment_status']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo $guard['total_evaluations']; ?></td>
                                    <td><?php echo $guard['last_evaluation_date'] ? date('M d, Y', strtotime($guard['last_evaluation_date'])) : 'Never'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $evalBadgeClass; ?>">
                                            <?php echo $guard['evaluation_status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $guard['next_due_date'] ? $guard['next_due_date']->format('M d, Y') : 'N/A'; ?></td>
                                    <td>
                                        <?php if ($guard['overall_rating']): ?>
                                            <span class="badge bg-secondary"><?php echo number_format($guard['overall_rating'], 1); ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (in_array($guard['evaluation_status'], ['Due', 'Overdue'])): ?>
                                            <button 
                                                type="button"
                                                class="btn btn-sm btn-primary btn-evaluate"
                                                data-user-id="<?php echo (int)$guard['User_ID']; ?>"
                                                data-guard-name="<?php echo htmlspecialchars($fullName, ENT_QUOTES); ?>"
                                                data-location-name="<?php echo htmlspecialchars($guard['location_name'] ?? '', ENT_QUOTES); ?>"
                                            >
                                                <i class="material-icons">assignment</i> Evaluate
                                            </button>
                                        <?php elseif ($guard['evaluation_status'] === 'Completed'): ?>
                                            <button 
                                                type="button"
                                                class="btn btn-sm btn-info btn-view"
                                                data-user-id="<?php echo (int)$guard['User_ID']; ?>"
                                                data-guard-name="<?php echo htmlspecialchars($fullName, ENT_QUOTES); ?>"
                                            >
                                                <i class="material-icons">visibility</i> View
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled>
                                                <i class="material-icons">schedule</i> Pending
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance Evaluation Modal -->
    <div class="modal fade" id="evaluationModal" tabindex="-1" aria-labelledby="evaluationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="evaluationModalLabel">
                        <i class="material-icons">assignment</i> Performance Evaluation Form
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="evaluationForm" method="POST" action="process_evaluation.php">
                        <input type="hidden" id="guard_id" name="guard_id">
                        <input type="hidden" id="evaluator_id" name="evaluator_id" value="<?php echo (int)$_SESSION['user_id']; ?>">
                        
                        <!-- Employee Information Section -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="material-icons">person</i> Employee Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Employee Name</label>
                                            <input type="text" class="form-control" id="employee_name" name="employee_name" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Position / Scope of Work</label>
                                            <input type="text" class="form-control" name="position" value="Security Guard" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Client Assignment</label>
                                            <input type="text" class="form-control" id="client_assignment" name="client_assignment" placeholder="" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Area Assigned</label>
                                            <input type="text" class="form-control" id="area_assigned" name="area_assigned" placeholder="" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Evaluation Period</label>
                                            <input type="text" class="form-control" name="evaluation_period" placeholder="e.g., January 2025 - March 2025">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rating Scale Section -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="material-icons">grade</i> Rating Scale</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Rating</th>
                                                <th>Scale</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td><strong>O - Outstanding</strong></td><td>90%</td><td>Approaches best possible performance</td></tr>
                                            <tr><td><strong>G - Good</strong></td><td>85%</td><td>Exceeds the normal requirements</td></tr>
                                            <tr><td><strong>F - Fair</strong></td><td>80%</td><td>Acceptable or within standards</td></tr>
                                            <tr><td><strong>NI - Needs Improvement</strong></td><td>75%</td><td>Needs serious attention and guidance</td></tr>
                                            <tr><td><strong>P - Poor</strong></td><td>70%</td><td>Inefficient</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Rating Factors Section -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="material-icons">checklist</i> Rating Factors</h6>
                            </div>
                            <div class="card-body">
                                <!-- Technical Skills -->
                                <div class="evaluation-section mb-4">
                                    <h6 class="section-title">1. Technical Skills</h6>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>1.1 Job Knowledge</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="tech_job_knowledge" value="70" id="tech_job_knowledge_70">
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
                                            <div class="col-md-4"><label>1.2 Employs tool of the job competently</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="tech_tool_competency" value="70" id="tech_tool_competency_70">
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
                                            <div class="col-md-4"><label>1.3 Follows standard and safety procedure</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="tech_safety_procedure" value="70" id="tech_safety_procedure_70">
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

                                <!-- Quality -->
                                <div class="evaluation-section mb-4">
                                    <h6 class="section-title">2. Quality</h6>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>2.1 Accuracy</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="quality_accuracy" value="70" id="quality_accuracy_70">
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
                                            <div class="col-md-4"><label>2.2 Completeness / Orderliness</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="quality_completeness" value="70" id="quality_completeness_70">
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
                                            <div class="col-md-4"><label>2.3 Reliability</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="quality_reliability" value="70" id="quality_reliability_70">
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

                                <!-- Productivity -->
                                <div class="evaluation-section mb-4">
                                    <h6 class="section-title">3. Productivity</h6>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>3.1 Time management</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="productivity_time_management" value="70" id="productivity_time_management_70">
                                                    <label for="productivity_time_management_70">P (70%)</label>
                                                    <input type="radio" name="productivity_time_management" value="75" id="productivity_time_management_75">
                                                    <label for="productivity_time_management_75">NI (75%)</label>
                                                    <input type="radio" name="productivity_time_management" value="80" id="productivity_time_management_80">
                                                    <label for="productivity_time_management_80">F (80%)</label>
                                                    <input type="radio" name="productivity_time_management" value="85" id="productivity_time_management_85">
                                                    <label for="productivity_time_management_85">G (85%)</label>
                                                    <input type="radio" name="productivity_time_management" value="90" id="productivity_time_management_90">
                                                    <label for="productivity_time_management_90">O (90%)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <textarea class="form-control" name="productivity_time_management_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>3.2 Utilization of resources</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="productivity_resource_utilization" value="70" id="productivity_resource_utilization_70">
                                                    <label for="productivity_resource_utilization_70">P (70%)</label>
                                                    <input type="radio" name="productivity_resource_utilization" value="75" id="productivity_resource_utilization_75">
                                                    <label for="productivity_resource_utilization_75">NI (75%)</label>
                                                    <input type="radio" name="productivity_resource_utilization" value="80" id="productivity_resource_utilization_80">
                                                    <label for="productivity_resource_utilization_80">F (80%)</label>
                                                    <input type="radio" name="productivity_resource_utilization" value="85" id="productivity_resource_utilization_85">
                                                    <label for="productivity_resource_utilization_85">G (85%)</label>
                                                    <input type="radio" name="productivity_resource_utilization" value="90" id="productivity_resource_utilization_90">
                                                    <label for="productivity_resource_utilization_90">O (90%)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <textarea class="form-control" name="productivity_resource_utilization_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>3.3 Priority setting</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="productivity_priority_setting" value="70" id="productivity_priority_setting_70">
                                                    <label for="productivity_priority_setting_70">P (70%)</label>
                                                    <input type="radio" name="productivity_priority_setting" value="75" id="productivity_priority_setting_75">
                                                    <label for="productivity_priority_setting_75">NI (75%)</label>
                                                    <input type="radio" name="productivity_priority_setting" value="80" id="productivity_priority_setting_80">
                                                    <label for="productivity_priority_setting_80">F (80%)</label>
                                                    <input type="radio" name="productivity_priority_setting" value="85" id="productivity_priority_setting_85">
                                                    <label for="productivity_priority_setting_85">G (85%)</label>
                                                    <input type="radio" name="productivity_priority_setting" value="90" id="productivity_priority_setting_90">
                                                    <label for="productivity_priority_setting_90">O (90%)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <textarea class="form-control" name="productivity_priority_setting_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Diligence and Professional Approach -->
                                <div class="evaluation-section mb-4">
                                    <h6 class="section-title">4. Diligence and Professional Approach</h6>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>4.1 Follows instructions</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="diligence_follows_instructions" value="70" id="diligence_follows_instructions_70">
                                                    <label for="diligence_follows_instructions_70">P (70%)</label>
                                                    <input type="radio" name="diligence_follows_instructions" value="75" id="diligence_follows_instructions_75">
                                                    <label for="diligence_follows_instructions_75">NI (75%)</label>
                                                    <input type="radio" name="diligence_follows_instructions" value="80" id="diligence_follows_instructions_80">
                                                    <label for="diligence_follows_instructions_80">F (80%)</label>
                                                    <input type="radio" name="diligence_follows_instructions" value="85" id="diligence_follows_instructions_85">
                                                    <label for="diligence_follows_instructions_85">G (85%)</label>
                                                    <input type="radio" name="diligence_follows_instructions" value="90" id="diligence_follows_instructions_90">
                                                    <label for="diligence_follows_instructions_90">O (90%)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <textarea class="form-control" name="diligence_follows_instructions_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>4.2 Flexibility / Adaptable</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="diligence_flexibility" value="70" id="diligence_flexibility_70">
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
                                            <div class="col-md-4"><label>4.3 Customer Focus / Responsiveness to service request</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="diligence_customer_focus" value="70" id="diligence_customer_focus_70">
                                                    <label for="diligence_customer_focus_70">P (70%)</label>
                                                    <input type="radio" name="diligence_customer_focus" value="75" id="diligence_customer_focus_75">
                                                    <label for="diligence_customer_focus_75">NI (75%)</label>
                                                    <input type="radio" name="diligence_customer_focus" value="80" id="diligence_customer_focus_80">
                                                    <label for="diligence_customer_focus_80">F (80%)</label>
                                                    <input type="radio" name="diligence_customer_focus" value="85" id="diligence_customer_focus_85">
                                                    <label for="diligence_customer_focus_85">G (85%)</label>
                                                    <input type="radio" name="diligence_customer_focus" value="90" id="diligence_customer_focus_90">
                                                    <label for="diligence_customer_focus_90">O (90%)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <textarea class="form-control" name="diligence_customer_focus_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>4.4 Attendance</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="diligence_attendance" value="70" id="diligence_attendance_70">
                                                    <label for="diligence_attendance_70">P (70%)</label>
                                                    <input type="radio" name="diligence_attendance" value="75" id="diligence_attendance_75">
                                                    <label for="diligence_attendance_75">NI (75%)</label>
                                                    <input type="radio" name="diligence_attendance" value="80" id="diligence_attendance_80">
                                                    <label for="diligence_attendance_80">F (80%)</label>
                                                    <input type="radio" name="diligence_attendance" value="85" id="diligence_attendance_85">
                                                    <label for="diligence_attendance_85">G (85%)</label>
                                                    <input type="radio" name="diligence_attendance" value="90" id="diligence_attendance_90">
                                                    <label for="diligence_attendance_90">O (90%)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <textarea class="form-control" name="diligence_attendance_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>4.5 Compliance to rules and policies</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="diligence_compliance" value="70" id="diligence_compliance_70">
                                                    <label for="diligence_compliance_70">P (70%)</label>
                                                    <input type="radio" name="diligence_compliance" value="75" id="diligence_compliance_75">
                                                    <label for="diligence_compliance_75">NI (75%)</label>
                                                    <input type="radio" name="diligence_compliance" value="80" id="diligence_compliance_80">
                                                    <label for="diligence_compliance_80">F (80%)</label>
                                                    <input type="radio" name="diligence_compliance" value="85" id="diligence_compliance_85">
                                                    <label for="diligence_compliance_85">G (85%)</label>
                                                    <input type="radio" name="diligence_compliance" value="90" id="diligence_compliance_90">
                                                    <label for="diligence_compliance_90">O (90%)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <textarea class="form-control" name="diligence_compliance_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Work Attitude -->
                                <div class="evaluation-section mb-4">
                                    <h6 class="section-title">5. Work Attitude</h6>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>5.1 Team Cooperation</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="attitude_team_cooperation" value="70" id="attitude_team_cooperation_70">
                                                    <label for="attitude_team_cooperation_70">P (70%)</label>
                                                    <input type="radio" name="attitude_team_cooperation" value="75" id="attitude_team_cooperation_75">
                                                    <label for="attitude_team_cooperation_75">NI (75%)</label>
                                                    <input type="radio" name="attitude_team_cooperation" value="80" id="attitude_team_cooperation_80">
                                                    <label for="attitude_team_cooperation_80">F (80%)</label>
                                                    <input type="radio" name="attitude_team_cooperation" value="85" id="attitude_team_cooperation_85">
                                                    <label for="attitude_team_cooperation_85">G (85%)</label>
                                                    <input type="radio" name="attitude_team_cooperation" value="90" id="attitude_team_cooperation_90">
                                                    <label for="attitude_team_cooperation_90">O (90%)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <textarea class="form-control" name="attitude_team_cooperation_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>5.2 Respect to co-worker and superior</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="attitude_respect" value="70" id="attitude_respect_70">
                                                    <label for="attitude_respect_70">P (70%)</label>
                                                    <input type="radio" name="attitude_respect" value="75" id="attitude_respect_75">
                                                    <label for="attitude_respect_75">NI (75%)</label>
                                                    <input type="radio" name="attitude_respect" value="80" id="attitude_respect_80">
                                                    <label for="attitude_respect_80">F (80%)</label>
                                                    <input type="radio" name="attitude_respect" value="85" id="attitude_respect_85">
                                                    <label for="attitude_respect_85">G (85%)</label>
                                                    <input type="radio" name="attitude_respect" value="90" id="attitude_respect_90">
                                                    <label for="attitude_respect_90">O (90%)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <textarea class="form-control" name="attitude_respect_comments" placeholder="Comments / Critical Incidents" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="evaluation-item">
                                        <div class="row">
                                            <div class="col-md-4"><label>5.3 Conduct and behavior</label></div>
                                            <div class="col-md-5">
                                                <div class="rating-options">
                                                    <input type="radio" name="attitude_conduct" value="70" id="attitude_conduct_70">
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
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="material-icons">calculate</i> Overall Rating</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Average Percentage Rating</label>
                                                    <input type="number" class="form-control" id="average_rating" name="average_rating" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Overall Performance Rating</label>
                                                    <input type="text" class="form-control" id="overall_performance" name="overall_performance" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recommendation Section -->
                                <div class="card mt-3">
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

                                <!-- Evaluator Information -->
                                <div class="card mt-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="material-icons">person</i> Evaluator Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Evaluated by</label>
                                                    <input type="text" class="form-control" name="evaluated_by" value="<?php echo $hrName; ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">GMSAI Representative</label>
                                                    <input type="text" class="form-control" name="gmsai_representative" value="<?php echo $hrName; ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Client's Representative</label>
                                                    <input type="text" class="form-control" name="client_representative" placeholder="Enter client representative name">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Evaluation Date</label>
                                                    <input type="date" class="form-control" name="evaluation_date" value="<?php echo $currentDate; ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <!-- Auto-calculated; button removed per requirements -->
                    <button type="button" class="btn btn-success" onclick="submitEvaluation()">Submit Evaluation</button>
                </div>
            </div>
        </div>
    </div>
    </div>
    

    <!-- Profile Picture Preview Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the file input and preview image elements
            const profilePicInput = document.getElementById('profilePic');
            const previewContainer = document.getElementById('imagePreviewContainer');
            const previewImage = document.getElementById('imagePreview');
            const currentImage = document.getElementById('currentProfileImage');
            
            // Listen for file selection
            profilePicInput.addEventListener('change', function() {
                const file = this.files[0];
                
                // Check if a file was selected
                if (file) {
                    // Show the preview container
                    previewContainer.style.display = 'block';
                    
                    // Hide the current image
                    if (currentImage) {
                        currentImage.style.display = 'none';
                    }
                    
                    // Create a FileReader to read the image
                    const reader = new FileReader();
                    
                    // Set up the FileReader onload event
                    reader.onload = function(e) {
                        // Set the preview image source to the loaded data URL
                        previewImage.src = e.target.result;
                    }
                    
                    // Read the file as a data URL
                    reader.readAsDataURL(file);
                    
                } else {
                    // If no file selected or selection canceled, show current image
                    previewContainer.style.display = 'none';
                    if (currentImage) {
                        currentImage.style.display = 'block';
                    }
                }
            });
        });
    </script>

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>

    <!-- Performance Evaluation JavaScript -->
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#performanceTable').DataTable({
                responsive: true,
                order: [[7, 'desc'], [4, 'asc']], // Sort by evaluation status, then employment status
                pageLength: 25,
                // Remove 'B' to hide built-in buttons UI; we still keep buttons config for programmatic export
                dom: 'frtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="material-icons">download</i> Export Excel',
                        className: 'btn btn-success btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] // Exclude Actions column
                        }
                    }
                ],
                columnDefs: [
                    { targets: [10], orderable: false }, // Actions column not sortable
                    { targets: [4, 7], className: 'text-center' }, // Center alignment for status columns
                ]
            });

            // Show pending evaluations toast notification
            checkDueEvaluations();

            // Delegated click handlers for action buttons
            $(document).on('click', '.btn-evaluate', function() {
                const userId = $(this).data('user-id');
                const guardName = $(this).data('guard-name');
                const locationName = $(this).data('location-name');
                startEvaluation(userId, guardName, locationName);
            });

            $(document).on('click', '.btn-view', function() {
                const userId = $(this).data('user-id');
                const guardName = $(this).data('guard-name');
                viewEvaluation(userId, guardName);
            });
        });

        // Check due evaluations and show sweet alert toast
        function checkDueEvaluations() {
            const dueCount = <?php echo $dueCount; ?>;
            const overdueCount = <?php echo $overdueCount; ?>;
            
            if (dueCount > 0) {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });

                let message = `You have ${dueCount} performance evaluation${dueCount > 1 ? 's' : ''} pending.`;
                if (overdueCount > 0) {
                    message += ` ${overdueCount} of them ${overdueCount > 1 ? 'are' : 'is'} overdue!`;
                }

                Toast.fire({
                    icon: overdueCount > 0 ? 'error' : 'warning',
                    title: 'Pending Evaluations',
                    text: message
                });
            }
        }

        // Start evaluation function with confirmation
    function startEvaluation(userId, guardName, locationName) {
            Swal.fire({
                title: 'Start Performance Evaluation',
                text: `Are you sure you want to start the performance evaluation for ${guardName}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Start Evaluation',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
            openEvaluationModal(userId, guardName, locationName);
                }
            });
        }

        // Open evaluation modal
        function openEvaluationModal(userId, guardName, locationName) {
            // Reset form
            document.getElementById('evaluationForm').reset();
            
            // Set guard information
            document.getElementById('guard_id').value = userId;
            document.getElementById('employee_name').value = guardName;
            
            // Do not allow manual edits; prefill if available, but fields are readonly
            if (locationName && typeof locationName === 'string') {
                document.getElementById('client_assignment').value = locationName;
                document.getElementById('area_assigned').value = locationName;
            } else {
                document.getElementById('client_assignment').value = '';
                document.getElementById('area_assigned').value = '';
            }
            
            
            // Show modal
            const evaluationModal = new bootstrap.Modal(document.getElementById('evaluationModal'));
            evaluationModal.show();

            // Ensure any pre-checked radios compute immediately
            autoCalculateRating();
        }

        // View evaluation function
        function viewEvaluation(userId, guardName) {
            Swal.fire({
                title: 'View Performance Evaluation',
                text: `View evaluation details for ${guardName}?`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#17a2b8',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'View Details',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `view_evaluation.php?user_id=${userId}`;
                }
            });
        }

        // Calculate overall rating
        function calculateRating() {
            const ratingInputs = document.querySelectorAll('input[type="radio"]:checked');
            
            if (ratingInputs.length < 13) { // 13 total evaluation criteria
                Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Evaluation',
                    text: 'Please complete all rating criteria before calculating the overall rating.',
                    confirmButtonColor: '#ffc107'
                });
                return;
            }

            let totalScore = 0;
            ratingInputs.forEach(input => {
                totalScore += parseInt(input.value);
            });

            const averageRating = totalScore / ratingInputs.length;
            document.getElementById('average_rating').value = averageRating.toFixed(2);

            // Determine performance rating
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

            Swal.fire({
                icon: 'success',
                title: 'Rating Calculated',
                text: `Overall Rating: ${averageRating.toFixed(2)}% (${performanceRating})`,
                confirmButtonColor: '#28a745'
            });
        }

        // Submit evaluation
        function submitEvaluation() {
            // Validate form
            const form = document.getElementById('evaluationForm');
            const formData = new FormData(form);
            
            // Check if rating is calculated
            const averageRating = document.getElementById('average_rating').value;
            if (!averageRating) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Rating',
                    text: 'Please calculate the overall rating before submitting the evaluation.',
                    confirmButtonColor: '#ffc107'
                });
                return;
            }

            // Check required fields (client_assignment is readonly and optional)
            const requiredFields = ['evaluation_period', 'client_representative'];
            let missingFields = [];
            
            requiredFields.forEach(field => {
                if (!formData.get(field)) {
                    missingFields.push(field.replace('_', ' ').toUpperCase());
                }
            });

            if (missingFields.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: `Please fill in the following fields: ${missingFields.join(', ')}`,
                    confirmButtonColor: '#ffc107'
                });
                return;
            }

            // Final confirmation
            Swal.fire({
                title: 'Submit Performance Evaluation',
                text: 'Are you sure you want to submit this performance evaluation? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Submit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit form via AJAX
                    fetch('process_evaluation.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Evaluation Submitted',
                                text: 'Performance evaluation has been successfully submitted.',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                location.reload(); // Refresh page to update table
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Submission Failed',
                                text: data.message || 'Failed to submit evaluation. Please try again.',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while submitting the evaluation.',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                }
            });
        }

        // Export to Excel function
        function exportToExcel() {
            const table = $('#performanceTable').DataTable();
            table.button('.buttons-excel').trigger();
        }

        // Auto-calculate rating when radio buttons change
        $(document).on('change', 'input[type="radio"]', function() {
            autoCalculateRating();
        });

        function autoCalculateRating() {
            const groups = [
                'tech_job_knowledge','tech_tool_competency','tech_safety_procedure',
                'quality_accuracy','quality_completeness','quality_reliability',
                'productivity_time_management','productivity_resource_utilization','productivity_priority_setting',
                'diligence_follows_instructions','diligence_flexibility','diligence_customer_focus','diligence_attendance','diligence_compliance',
                'attitude_team_cooperation','attitude_respect','attitude_conduct'
            ];
            let total = 0; let count = 0;
            groups.forEach(name => {
                const sel = document.querySelector(`input[name="${name}"]:checked`);
                if (sel) { total += parseInt(sel.value, 10); count++; }
            });
            if (count > 0) {
                const avg = total / count;
                const avgEl = document.getElementById('average_rating');
                const overallEl = document.getElementById('overall_performance');
                avgEl.value = avg.toFixed(2);
                let performanceRating = '';
                if (avg >= 90) performanceRating = 'Outstanding (O)';
                else if (avg >= 85) performanceRating = 'Good (G)';
                else if (avg >= 80) performanceRating = 'Fair (F)';
                else if (avg >= 75) performanceRating = 'Needs Improvement (NI)';
                else performanceRating = 'Poor (P)';
                overallEl.value = performanceRating;
            }
        }
    </script>

  
    <!-- Sidebar and Tooltip??? JS -->
    <script src="js/hr_dashboard.js"></script>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="hr_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Leave Request</span>
            </a>
            <a href="recruitment.php" class="mobile-nav-item">
            <span class="material-icons">person_add</span>
            <span class="mobile-nav-text">Recruitment</span>
            </a>
            <a href="masterlist.php" class="mobile-nav-item">
                <span class="material-icons">assignment</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
             <a href="performance_evaluation.php" class="mobile-nav-item active">
                <span class="material-icons">assessment</span>
                <span class="mobile-nav-text">Performance Evaluation</span>
            </a>
            <a href="archives.php" class="mobile-nav-item">
                <span class="material-icons">archive</span>
                <span class="mobile-nav-text">Archives</span>
            </a>
            <a href="logs.php" class="mobile-nav-item">
                <span class="material-icons">receipt_long</span>
                <span class="mobile-nav-text">Logs</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>
</body>
</html>