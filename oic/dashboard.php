<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

// Enforce OIC role (8)
if (!validateSession($conn, 8)) { exit; }

$currentDate = date('Y-m-d');

// Handle filter form submission
if (isset($_GET['filter_submit'])) {
    $employmentStatus = $_GET['employment_status'] ?? 'all';
    $evaluationStatus = $_GET['evaluation_status'] ?? 'all';
    $searchTerm = isset($_GET['guardSearch']) ? trim($_GET['guardSearch']) : '';
} else {
    // Default values - show all guards by default
    $employmentStatus = 'all';
    $evaluationStatus = 'all';
    $searchTerm = '';
}

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited (except profile)
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

// Get current OIC user's name and info
$oicStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 8 AND status = 'Active' AND User_ID = ?");
$oicStmt->execute([$_SESSION['user_id']]);
$oicData = $oicStmt->fetch(PDO::FETCH_ASSOC);
$oicName = $oicData ? $oicData['First_Name'] . ' ' . $oicData['Last_Name'] : "Officer in Charge";

// Get OIC's profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $oicProfile = $profileData['Profile_Pic'];
} else {
    $oicProfile = '../images/default_profile.png';
}

// Get OIC's assigned locations
$oicLocationsQuery = "SELECT location_name FROM oic_locations WHERE oic_user_id = ? AND is_active = 1";
$oicLocationsStmt = $conn->prepare($oicLocationsQuery);
$oicLocationsStmt->execute([$_SESSION['user_id']]);
$oicLocations = $oicLocationsStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($oicLocations)) {
    echo "<div class='alert alert-warning'>You are not assigned to any locations. Please contact your administrator.</div>";
    echo "<div class='alert alert-info'>Debug: Your User ID is " . $_SESSION['user_id'] . "</div>";
    exit;
}

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
$params = [];

if (!empty($searchTerm)) {
    $searchCondition = " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR CONCAT(u.First_Name, ' ', u.Last_Name) LIKE ?) ";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

// Build location condition for OIC's assigned locations
$locationPlaceholders = str_repeat('?,', count($oicLocations) - 1) . '?';
$locationCondition = " AND gl.location_name IN ($locationPlaceholders) ";
$params = array_merge($params, $oicLocations);

// Debug: Show main query guards (before filters)
$debugMainQuery = "
    SELECT 
        u.User_ID, 
        u.employee_id,
        u.First_Name, 
        u.Last_Name, 
        u.hired_date,
        gl.location_name
    FROM users u
    INNER JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_active = 1
    WHERE u.Role_ID = 5 AND u.status = 'Active'
    AND gl.location_name IN ($locationPlaceholders)
";
$debugMainStmt = $conn->prepare($debugMainQuery);
foreach ($oicLocations as $index => $location) {
    $debugMainStmt->bindValue($index + 1, $location);
}
$debugMainStmt->execute();
$debugMainGuards = $debugMainStmt->fetchAll(PDO::FETCH_ASSOC);

// Get guards data with their evaluation information (only from OIC's locations)
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
    INNER JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_active = 1
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
$allGuardsWithStatus = []; // Debug: Store all guards with their calculated status
$filteredGuards = [];
foreach ($guards as $guard) {
    $hiredDate = $guard['hired_date'] ?: $guard['Created_At']; // fallback to Created_At if hired_date is null
    $employmentStatusCalc = getEmploymentStatus($hiredDate);
    $evaluationInfo = getEvaluationStatus($hiredDate, $guard['last_evaluation_date'], $employmentStatusCalc);
    
    $guard['employment_status'] = $employmentStatusCalc;
    $guard['evaluation_status'] = $evaluationInfo['status'];
    $guard['next_due_date'] = $evaluationInfo['next_due'];
    $guard['effective_hired_date'] = $hiredDate;
    
    // Debug: Store all guards with their status
    $allGuardsWithStatus[] = $guard;
    
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

// Count statistics (only for OIC's locations)
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
    <title>OIC Dashboard - Performance Evaluation - Green Meadows Security Agency</title>
    
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
    <link rel="stylesheet" href="css/oic_dashboard.css">
    
    <style>
        .badge.evaluation-due {
            background-color: #dc3545;
        }
        .badge.evaluation-overdue {
            background-color: #dc3545;
            animation: pulse 2s infinite;
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
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .table-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
            border-color: #dc3545;
        }
        
        .table-danger td {
            border-color: rgba(220, 53, 69, 0.2);
        }
        
        .location-info {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .location-badge {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            margin: 2px;
            display: inline-block;
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
                <div>GREEN MEADOWS</div>
                <div>SECURITY AGENCY</div>
            </div>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="performance_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Performance Evaluation">
                    <span class="material-icons">assessment</span>
                    <span>Performance Evaluation</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="view_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="View Evaluations">
                    <span class="material-icons">assignment_turned_in</span>
                    <span>View Evaluations</span>
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
                <span><?php echo htmlspecialchars($oicName); ?></span>
                <img src="<?php echo $oicProfile; ?>" alt="User Profile">
            </a>
        </div>

        <!-- Main content area -->
        <div class="container-fluid mt-4">
            <h1 class="page-title">OIC Dashboard - Performance Evaluation</h1>
            
            <!-- Location Information -->
            <div class="location-info">
                <h5><i class="material-icons" style="vertical-align: middle;">location_on</i> Your Assigned Locations</h5>
                <p class="mb-2">As an Officer in Charge, you are responsible for evaluating guards in the following locations:</p>
                <?php foreach ($oicLocations as $location): ?>
                    <span class="location-badge"><?php echo htmlspecialchars($location); ?></span>
                <?php endforeach; ?>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-3">
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
                <div class="col-md-3 col-6 mb-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $dueCount; ?></h4>
                                    <p class="mb-0">Evaluations Due</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="material-icons" style="font-size: 2rem;">assignment_late</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $overdueCount; ?></h4>
                                    <p class="mb-0">Overdue</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="material-icons" style="font-size: 2rem;">schedule</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $completedCount; ?></h4>
                                    <p class="mb-0">Completed</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="material-icons" style="font-size: 2rem;">assignment_turned_in</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter and Search Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-4 col-6">
                                <label for="guardSearch" class="form-label">Search Guard:</label>
                                <input type="text" class="form-control" id="guardSearch" name="guardSearch" 
                                       placeholder="Enter guard name..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                            </div>
                            <div class="col-md-3 col-6">
                                <label for="employment_status" class="form-label">Employment Status:</label>
                                <select class="form-control" id="employment_status" name="employment_status">
                                    <option value="all" <?php echo $employmentStatus === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="Probationary" <?php echo $employmentStatus === 'Probationary' ? 'selected' : ''; ?>>Probationary</option>
                                    <option value="Regular" <?php echo $employmentStatus === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <label for="evaluation_status" class="form-label">Evaluation Status:</label>
                                <select class="form-control" id="evaluation_status" name="evaluation_status">
                                    <option value="all" <?php echo $evaluationStatus === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="Due" <?php echo $evaluationStatus === 'Due' ? 'selected' : ''; ?>>Due</option>
                                    <option value="Overdue" <?php echo $evaluationStatus === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                                    <option value="Completed" <?php echo $evaluationStatus === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Not Yet Started" <?php echo $evaluationStatus === 'Not Yet Started' ? 'selected' : ''; ?>>Not Yet Started</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-6 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" name="filter_submit" class="btn btn-primary">
                                        <i class="material-icons" style="vertical-align: middle;">search</i> Filter
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="material-icons" style="vertical-align: middle;">refresh</i> Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Guards Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="material-icons" style="vertical-align: middle;">assignment</i>
                        Guards Performance Evaluation Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($filteredGuards)): ?>
                        <div class="alert alert-info text-center">
                            <i class="material-icons" style="font-size: 3rem;">info</i>
                            <h4>No Guards Found</h4>
                            <p>No guards match your current filter criteria in your assigned locations.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="guardsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Employee ID</th>
                                        <th>Guard Name</th>
                                        <th>Location</th>
                                        <th>Employment Status</th>
                                        <th>Evaluation Status</th>
                                        <th>Last Evaluation</th>
                                        <th>Next Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredGuards as $guard): ?>
                                        <?php 
                                        $rowClass = '';
                                        if ($guard['evaluation_status'] === 'Overdue') {
                                            $rowClass = 'table-danger';
                                        } elseif ($guard['evaluation_status'] === 'Due') {
                                            $rowClass = 'table-warning';
                                        }
                                        ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td><?php echo htmlspecialchars($guard['employee_id'] ?: 'N/A'); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($guard['First_Name'] . ' ' . $guard['Last_Name']); ?></strong>
                                                <?php if (!empty($guard['middle_name'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($guard['middle_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?php echo htmlspecialchars($guard['location_name'] ?: 'Not Assigned'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $guard['employment_status'] === 'Probationary' ? 'status-probationary' : 'status-regular'; ?>">
                                                    <?php echo $guard['employment_status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = '';
                                                switch ($guard['evaluation_status']) {
                                                    case 'Due':
                                                        $badgeClass = 'evaluation-due';
                                                        break;
                                                    case 'Overdue':
                                                        $badgeClass = 'evaluation-overdue';
                                                        break;
                                                    case 'Completed':
                                                        $badgeClass = 'evaluation-complete';
                                                        break;
                                                    case 'Not Yet Started':
                                                        $badgeClass = 'evaluation-not-started';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <?php echo $guard['evaluation_status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($guard['last_evaluation_date']): ?>
                                                    <?php echo date('M d, Y', strtotime($guard['last_evaluation_date'])); ?>
                                                    <?php if ($guard['overall_rating']): ?>
                                                        <br><small class="text-muted">Rating: <?php echo number_format($guard['overall_rating'], 1); ?>/90.0</small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No evaluation yet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($guard['next_due_date']): ?>
                                                    <?php echo $guard['next_due_date']->format('M d, Y'); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (in_array($guard['evaluation_status'], ['Due', 'Overdue'])): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="openEvaluationModal(<?php echo $guard['User_ID']; ?>, '<?php echo htmlspecialchars($guard['First_Name'] . ' ' . $guard['Last_Name']); ?>')" title="Conduct Evaluation">
                                                        <i class="material-icons">assessment</i> Evaluate
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($guard['total_evaluations'] > 0): ?>
                                                    <button class="btn btn-sm btn-info" onclick="openViewEvaluationModal(<?php echo $guard['User_ID']; ?>, '<?php echo htmlspecialchars($guard['First_Name'] . ' ' . $guard['Last_Name']); ?>')" title="View Past Evaluations">
                                                        <i class="material-icons">visibility</i> View
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance Evaluation Modal -->
    <div class="modal fade" id="evaluationModal" tabindex="-1" aria-labelledby="evaluationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="evaluationModalLabel">
                        <i class="material-icons">assessment</i> Performance Evaluation - <span id="evalGuardName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="evaluationModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading evaluation form...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Evaluation Modal -->
    <div class="modal fade" id="viewEvaluationModal" tabindex="-1" aria-labelledby="viewEvaluationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewEvaluationModalLabel">
                        <i class="material-icons">visibility</i> Evaluation History - <span id="viewGuardName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewEvaluationModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading evaluation history...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include JavaScript libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    
    <!-- Chart.js for performance trends -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="js/oic_dashboard.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#guardsTable').DataTable({
                responsive: true,
                order: [[ 4, 'desc' ]], // Sort by evaluation status
                pageLength: 25,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="material-icons">file_download</i> Excel',
                        className: 'btn btn-success btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6] // Exclude actions column
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="material-icons">print</i> Print',
                        className: 'btn btn-secondary btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6] // Exclude actions column
                        }
                    }
                ]
            });
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                weekday: 'long'
            };
            const timeOptions = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true
            };
            
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', dateOptions);
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', timeOptions);
        }
        
        // Update every second
        setInterval(updateDateTime, 1000);
        updateDateTime(); // Initial call
        
        // Open Performance Evaluation Modal
        function openEvaluationModal(guardId, guardName) {
            $('#evalGuardName').text(guardName);
            $('#evaluationModal').modal('show');
            
            // Load evaluation form content
            $.ajax({
                url: 'ajax/load_evaluation_form.php',
                type: 'GET',
                data: { guard_id: guardId },
                success: function(response) {
                    $('#evaluationModalBody').html(response);
                },
                error: function() {
                    $('#evaluationModalBody').html('<div class="alert alert-danger">Error loading evaluation form. Please try again.</div>');
                }
            });
        }
        
        // Open View Evaluation Modal
        function openViewEvaluationModal(guardId, guardName) {
            $('#viewGuardName').text(guardName);
            $('#viewEvaluationModal').modal('show');
            
            // Load evaluation history content
            $.ajax({
                url: 'ajax/load_evaluation_history.php',
                type: 'GET',
                data: { guard_id: guardId },
                success: function(response) {
                    $('#viewEvaluationModalBody').html(response);
                    // Initialize chart after content is loaded
                    if (typeof initializePerformanceChart === 'function') {
                        setTimeout(initializePerformanceChart, 200);
                    }
                },
                error: function() {
                    $('#viewEvaluationModalBody').html('<div class="alert alert-danger">Error loading evaluation history. Please try again.</div>');
                }
            });
        }
        
        // Handle form submission from modal
        $(document).on('submit', '#evaluationForm', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            
            // Show loading
            Swal.fire({
                title: 'Submitting...',
                text: 'Please wait while we process your evaluation.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'process_evaluation.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Evaluation Submitted',
                            text: response.message,
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            $('#evaluationModal').modal('hide');
                            location.reload(); // Refresh the page to update the dashboard
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Submission Failed',
                            text: response.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while submitting the evaluation.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        });
    </script>
</body>
</html>