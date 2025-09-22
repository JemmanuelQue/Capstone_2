<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
// Enforce HR role (3)
if (!validateSession($conn, 3)) { exit; }

// Get current super admin user's name
$hrStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 3 AND status = 'Active' AND User_ID = ?");
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

// Get filters from request
$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Get pagination for each category independently
$categoryPages = [];
foreach (['Guard Creation', 'Guard Updates', 'Guard Archives', 'Guard Recovery', 'Guard Deletion', 'Leave Management', 'Performance Management', 'Attendance Management', 'Profile Management', 'Password Management', 'Other HR Activities'] as $category) {
    $categoryPages[$category] = isset($_GET['page_' . str_replace(' ', '_', strtolower($category))]) ? intval($_GET['page_' . str_replace(' ', '_', strtolower($category))]) : 1;
}

$logsPerPage = 10; // Number of logs to show per page for each category

// Build filters and params (clear names)
$filters = [];
$filterParams = [];

// ALWAYS filter for HR-specific activities first
$hrActivityTypes = [
    'User Creation', 'Guard Creation',
    'User Update', 'Guard Update', 
    'User Archive', 'Archived Guards',
    'User Recovery', 'Guard Recovery',
    'User Deletion', 'Guard Deletion',
    'Leave Request Submitted',
    'Leave Request Approved',
    'Leave Request Rejected',
    'Performance Evaluation Started',
    'Performance Evaluation Completed',
    'Attendance Record Added',
    'Attendance Add',
    'Attendance Edit', 
    'Attendance Archive',
    'Attendance Delete Permanent',
    'Profile Update',
    'Password Reset'
];

// Add condition to show only HR-related activities ALWAYS
$hrActivityPlaceholders = implode(',', array_fill(0, count($hrActivityTypes), '?'));
$filters[] = "al.Activity_Type IN ($hrActivityPlaceholders)";
foreach ($hrActivityTypes as $activity) {
    $filterParams[] = $activity;
}

// If user selected a specific activity type from dropdown, add additional filter
if (!empty($activityType)) {
    // Map display types back to actual database types for additional filtering
    $reverseActivityMapping = [
        'Guard Creation' => ['User Creation', 'Guard Creation'],
        'Guard Update' => ['User Update', 'Guard Update'],
        'Guard Archive' => ['User Archive', 'Archived Guards'],
        'Guard Recovery' => ['User Recovery', 'Guard Recovery'],
        'Guard Deletion' => ['User Deletion', 'Guard Deletion'],
        'Attendance Add' => ['Attendance Add', 'Attendance Record Added'],
        'Attendance Edit' => ['Attendance Edit'],
        'Attendance Archive' => ['Attendance Archive'],
        'Attendance Delete Permanent' => ['Attendance Delete Permanent'],
        'Profile Update' => ['Profile Update'],
        'Password Reset' => ['Password Reset'],
        'Leave Request Submitted' => ['Leave Request Submitted'],
        'Leave Request Approved' => ['Leave Request Approved'],
        'Leave Request Rejected' => ['Leave Request Rejected'],
        'Performance Evaluation Started' => ['Performance Evaluation Started'],
        'Performance Evaluation Completed' => ['Performance Evaluation Completed']
    ];
    
    // Find if the selected activity type matches any display type
    $matchingTypes = [$activityType]; // Default to the selected type itself
    
    foreach ($reverseActivityMapping as $displayType => $actualTypes) {
        if (in_array($activityType, $actualTypes)) {
            $matchingTypes = $actualTypes;
            break;
        }
    }
    
    if (count($matchingTypes) > 1) {
        $placeholders = implode(',', array_fill(0, count($matchingTypes), '?'));
        $filters[] = "al.Activity_Type IN ($placeholders)";
        foreach ($matchingTypes as $type) {
            $filterParams[] = $type;
        }
    } else {
        $filters[] = "al.Activity_Type = ?";
        $filterParams[] = $activityType;
    }
}

if (!empty($dateFrom)) {
    // Ensure date is in YYYY-MM-DD format for database comparison
    $formattedDateFrom = date('Y-m-d', strtotime($dateFrom));
    $filters[] = "DATE(Timestamp) >= ?";
    $filterParams[] = $formattedDateFrom;
}

if (!empty($dateTo)) {
    // Ensure date is in YYYY-MM-DD format for database comparison
    $formattedDateTo = date('Y-m-d', strtotime($dateTo));
    $filters[] = "DATE(Timestamp) <= ?";
    $filterParams[] = $formattedDateTo;
}

if (!empty($searchTerm)) {
    $filters[] = "(al.Activity_Details LIKE ? OR u.First_Name LIKE ? OR u.Last_Name LIKE ?)";
    $filterParams[] = "%$searchTerm%";
    $filterParams[] = "%$searchTerm%";
    $filterParams[] = "%$searchTerm%";
}

// Strictly limit to actions performed by HR users (Role_ID = 3) - no more, no less
$filters[] = "u.Role_ID = 3";

// Construct the WHERE clause
// Helpful: overall where (unused directly below but aids readability)
$overallWhereSql = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";

// Get distinct activity types - only HR-related ones (same as our filter above)
$activityTypes = $hrActivityTypes;

// Activity categories (for grouping) - HR specific, more granular
$categories = [
    'Guard Creation' => ['Guard Creation', 'User Creation'],
    'Guard Updates' => ['Guard Update', 'User Update'],
    'Guard Archives' => ['Archived Guards', 'User Archive'],
    'Guard Recovery' => ['Guard Recovery', 'User Recovery'],
    'Guard Deletion' => ['Guard Deletion', 'User Deletion'],
    'Leave Management' => ['Leave Request Submitted', 'Leave Request Approved', 'Leave Request Rejected'],
    'Performance Management' => ['Performance Evaluation Started', 'Performance Evaluation Completed'],
    'Attendance Management' => ['Attendance Record Added', 'Attendance Add', 'Attendance Edit', 'Attendance Archive', 'Attendance Delete Permanent'],
    'Profile Management' => ['Profile Update'],
    'Password Management' => ['Password Reset'],
    'Other HR Activities' => []
];

// Categorize and fetch logs for each activity type category
$categorizedLogs = [];
$categoryCounts = [];

// First, count total records for each category for pagination
foreach ($categories as $categoryName => $activityTypesList) {
    $categoryFilters = $filters;
    
    if (!empty($activityTypesList)) {
        $placeholders = implode(',', array_fill(0, count($activityTypesList), '?'));
    $categoryFilters[] = "al.Activity_Type IN ($placeholders)";
    $categoryParams = $filterParams;
    foreach ($activityTypesList as $type) { $categoryParams[] = $type; }
    } else {
        // For "Other HR Activities", get those not in any defined category but still HR-related
        $allDefinedTypes = [];
        foreach ($categories as $cat => $types) {
            if ($cat !== 'Other HR Activities') {
                $allDefinedTypes = array_merge($allDefinedTypes, $types);
            }
        }
        
        if (!empty($allDefinedTypes)) {
            $placeholders = implode(',', array_fill(0, count($allDefinedTypes), '?'));
            $categoryFilters[] = "al.Activity_Type NOT IN ($placeholders)";
            $categoryParams = $filterParams;
            foreach ($allDefinedTypes as $type) {
                $categoryParams[] = $type;
            }
        } else {
            $categoryParams = $filterParams;
        }
    }
    
    $categoryWhereSql = !empty($categoryFilters) ? "WHERE " . implode(" AND ", $categoryFilters) : "";
    
    $countSql = "
        SELECT COUNT(*) as total
        FROM activity_logs al
        LEFT JOIN users u ON al.User_ID = u.User_ID
        $categoryWhereSql
    ";
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($categoryParams);
    $totalLogs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $categoryCounts[$categoryName] = $totalLogs;
    
    // Calculate pagination for this category
    $totalPages = ceil($totalLogs / $logsPerPage);
    
    // Now fetch actual logs for this category based on its own pagination
    if ($totalLogs > 0) {
        // Get the current page for this specific category
        $categoryPageKey = str_replace(' ', '_', strtolower($categoryName));
        $currentPage = $categoryPages[$categoryName] ?? 1;
        
        // Calculate offset for this category's pagination
        $offset = ($currentPage - 1) * $logsPerPage;
        $limit = $logsPerPage; // Always limit to 10 per category
        
        $selectSql = "
            SELECT al.*, u.First_Name, u.Last_Name, u.Role_ID, r.Role_Name 
            FROM activity_logs al
            LEFT JOIN users u ON al.User_ID = u.User_ID
            LEFT JOIN roles r ON u.Role_ID = r.Role_ID
            $categoryWhereSql
            ORDER BY al.Timestamp DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $selectStmt = $conn->prepare($selectSql);
        $selectStmt->execute($categoryParams);
        $categoryLogs = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $categorizedLogs[$categoryName] = [
            'logs' => $categoryLogs,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'totalLogs' => $totalLogs
        ];
    } else {
        $categorizedLogs[$categoryName] = [
            'logs' => [],
            'totalPages' => 0,
            'currentPage' => 1
        ];
    }
}

// Move empty categories to the end
$nonEmptyCategories = [];
$emptyCategories = [];

foreach ($categories as $categoryName => $activityTypesList) {
    if (!empty($categorizedLogs[$categoryName]['logs'])) {
        $nonEmptyCategories[$categoryName] = $categorizedLogs[$categoryName];
    } else {
        $emptyCategories[$categoryName] = $categorizedLogs[$categoryName];
    }
}

$categorizedLogs = array_merge($nonEmptyCategories, $emptyCategories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/logs.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <style>
        html { scroll-behavior: smooth; }
        /* Offset anchor to account for fixed header spacing */
        .anchor-offset { scroll-margin-top: 80px; }
    </style>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div>SECURITY AGENCY</div>
            </div>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a href="hr_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
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
                <a href="performance_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Performance Evaluation">
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
                <a href="logs.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Logs">
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

        <div class="content-container">
            <div class="container-fluid px-4">
                <h1 class="activity-logs-title">HR Activity Logs</h1>
                <p class="text-center mb-4">View HR activity logs including guard management, leave requests, and performance evaluations</p>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="activity_type" class="form-label">Activity Type</label>
                                <select name="activity_type" id="activity_type" class="form-select">
                                    <option value="">All Activities</option>
                                    <?php 
                                    // Create unique activity types for dropdown to avoid duplicates
                                    $uniqueActivityTypes = [];
                                    $activityLabels = array(
                                        'User Creation' => 'Guard Creation',
                                        'User Update' => 'Guard Update',
                                        'User Archive' => 'Guard Archive',
                                        'User Recovery' => 'Guard Recovery',
                                        'User Deletion' => 'Guard Deletion',
                                        'Guard Creation' => 'Guard Creation',
                                        'Guard Update' => 'Guard Update',
                                        'Archived Guards' => 'Guard Archive',
                                        'Guard Recovery' => 'Guard Recovery',
                                        'Guard Deletion' => 'Guard Deletion',
                                        'Leave Request Submitted' => 'Leave Request Submitted',
                                        'Leave Request Approved' => 'Leave Request Approved',
                                        'Leave Request Rejected' => 'Leave Request Rejected',
                                        'Performance Evaluation Started' => 'Performance Evaluation Started',
                                        'Performance Evaluation Completed' => 'Performance Evaluation Completed',
                                        'Attendance Record Added' => 'Attendance Record Added',
                                        'Attendance Add' => 'Attendance Add',
                                        'Attendance Edit' => 'Attendance Edit',
                                        'Attendance Archive' => 'Attendance Archive',
                                        'Attendance Delete Permanent' => 'Attendance Delete Permanent',
                                        'Profile Update' => 'Profile Update',
                                        'Password Reset' => 'Password Reset'
                                    );
                                    
                                    // Group similar activities to avoid duplicates in dropdown
                                    foreach ($activityTypes as $type) {
                                        $displayType = isset($activityLabels[$type]) ? $activityLabels[$type] : $type;
                                        if (!in_array($displayType, array_column($uniqueActivityTypes, 'display'))) {
                                            $uniqueActivityTypes[] = [
                                                'value' => $type,
                                                'display' => $displayType
                                            ];
                                        }
                                    }
                                    
                                    // Sort by display name for better UX
                                    usort($uniqueActivityTypes, function($a, $b) {
                                        return strcmp($a['display'], $b['display']);
                                    });
                                    
                                    foreach ($uniqueActivityTypes as $activityInfo): 
                                    ?>
                                        <option value="<?php echo $activityInfo['value']; ?>" <?php echo ($activityType === $activityInfo['value']) ? 'selected' : ''; ?>>
                                            <?php echo $activityInfo['display']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="date_range" class="form-label">Date Range</label>
                                <div class="input-group dropdown-input-group">
                                    <span class="input-group-text"><i class="material-icons">date_range</i></span>
                                    <input type="text" class="form-control dropdown-toggle" id="date_range" name="date_range" 
                                           placeholder="Select date range" value="<?php echo (!empty($dateFrom) && !empty($dateTo)) ? date('m/d/Y', strtotime($dateFrom)) . ' - ' . date('m/d/Y', strtotime($dateTo)) : ''; ?>" 
                                           readonly style="background-color: white; cursor: pointer;">
                                    <span class="input-group-text dropdown-indicator"><i class="material-icons">arrow_drop_down</i></span>
                                    <input type="hidden" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                                    <input type="hidden" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Details or names" value="<?php echo $searchTerm; ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="material-icons">search</i> Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <a href="logs.php" class="btn btn-primary">
                                    <i class="material-icons align-middle">refresh</i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Activity Log Categories -->
                <div class="accordion" id="activityLogsAccordion">
                    <?php $index = 0; ?>
                    <?php foreach ($categorizedLogs as $categoryName => $categoryData): ?>
                        <?php if ($categoryCounts[$categoryName] > 0): ?>
                            <?php $categorySlug = str_replace(' ', '_', strtolower($categoryName)); ?>
                            <div class="accordion-item mb-3 anchor-offset" id="cat-<?php echo $categorySlug; ?>">
                                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" 
                                            aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                            aria-controls="collapse<?php echo $index; ?>">
                                        <i class="material-icons me-2">
                                            <?php
                                            switch($categoryName) {
                                                case 'Guard Creation': 
                                                    echo 'person_add'; 
                                                    break;
                                                case 'Guard Updates': 
                                                    echo 'manage_accounts'; 
                                                    break;
                                                case 'Guard Archives': 
                                                    echo 'archive'; 
                                                    break;
                                                case 'Guard Recovery': 
                                                    echo 'settings_backup_restore'; 
                                                    break;
                                                case 'Guard Deletion': 
                                                    echo 'delete_forever'; 
                                                    break;
                                                case 'Leave Management': 
                                                    echo 'event_note'; 
                                                    break;
                                                case 'Performance Management': 
                                                    echo 'assessment'; 
                                                    break;
                                                case 'Attendance Management': 
                                                    echo 'schedule'; 
                                                    break;
                                                case 'Profile Management': 
                                                    echo 'account_circle'; 
                                                    break;
                                                case 'Password Management': 
                                                    echo 'lock_reset'; 
                                                    break;
                                                case 'Other HR Activities': 
                                                    echo 'more_horiz'; 
                                                    break;
                                                default: 
                                                    echo 'list'; 
                                                    break;
                                            }
                                            ?>
                                        </i>
                                        <?php echo $categoryName; ?> 
                                        <span class="badge bg-primary ms-2"><?php echo $categoryCounts[$categoryName]; ?></span>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $index; ?>" 
                                     class="accordion-collapse collapse show" 
                                     aria-labelledby="heading<?php echo $index; ?>">
                                    <div class="accordion-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover mb-0">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>User & Role</th>
                                                        <th>Activity Type</th>
                                                        <th>Details</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($categoryData['logs'])): ?>
                                                        <?php foreach ($categoryData['logs'] as $log): ?>
                                                            <tr>
                                                                <td><?php echo date('M d, Y g:i A', strtotime($log['Timestamp'])); ?></td>
                                                                <td>
                                                                    <?php 
                                                                    if (isset($log['First_Name']) && isset($log['Last_Name'])) {
                                                                        echo $log['First_Name'] . ' ' . $log['Last_Name'];
                                                                        echo ' <span class="text-muted">(' . ($log['Role_Name'] ?? 'Unknown') . ')</span>';
                                                                    } else {
                                                                        echo "System";
                                                                    }
                                                                    ?>
                                                                </td>
                                                                <td>
                                                                    <?php 
                                                                    $rowActivityType = $log['Activity_Type'];
                                                                    $badgeClass = 'bg-primary';
                                                                    $iconName = 'info';
                                                                    
                                                                    if (strpos($rowActivityType, 'Delete') !== false || strpos($rowActivityType, 'Deletion') !== false) {
                                                                        $badgeClass = 'bg-danger';
                                                                        $iconName = 'delete_forever';
                                                                    } elseif (strpos($rowActivityType, 'Archive') !== false) {
                                                                        $badgeClass = 'bg-warning text-dark';
                                                                        $iconName = 'folder_delete';
                                                                    } elseif (strpos($rowActivityType, 'Recovery') !== false || strpos($rowActivityType, 'Recover') !== false) {
                                                                        $badgeClass = 'bg-success';
                                                                        $iconName = 'settings_backup_restore';
                                                                    } elseif (strpos($rowActivityType, 'Update') !== false || strpos($rowActivityType, 'Edit') !== false) {
                                                                        $badgeClass = 'bg-info text-dark';
                                                                        $iconName = 'manage_accounts';
                                                                    } elseif (strpos($rowActivityType, 'Create') !== false || strpos($rowActivityType, 'Add') !== false) {
                                                                        $badgeClass = 'bg-success';
                                                                        $iconName = 'person_add_alt';
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                                        <i class="material-icons align-text-bottom" style="font-size: 14px;"><?php echo $iconName; ?></i>
                                                                        <?php echo htmlspecialchars($rowActivityType); ?>
                                                                    </span>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($log['Activity_Details']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center">No activity logs found for this category</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Independent Pagination for this category -->
                                        <?php if ($categoryData['totalPages'] > 1): ?>
                                            <div class="pagination-container">
                                                <nav aria-label="Page navigation for <?php echo $categoryName; ?>">
                                                    <ul class="pagination">
                                                        <?php 
                                                        $categoryPageParam = 'page_' . str_replace(' ', '_', strtolower($categoryName));
                                                        $currentPage = $categoryData['currentPage'];
                                                        ?>
                                                        
                                                        <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="?activity_type=<?php echo urlencode($activityType); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&search=<?php echo urlencode($searchTerm); ?>&<?php echo $categoryPageParam; ?>=<?php echo $currentPage - 1; ?><?php 
                                                            // Preserve ALL other category pages (even page 1)
                                                            foreach ($categoryPages as $catName => $catPage) {
                                                                if ($catName !== $categoryName) {
                                                                    $catParam = 'page_' . str_replace(' ', '_', strtolower($catName));
                                                                    echo '&' . $catParam . '=' . $catPage;
                                                                }
                                                            }
                                                            ?>#cat-<?php echo $categorySlug; ?>">
                                                                Previous
                                                            </a>
                                                        </li>
                                                        
                                                        <?php for ($i = 1; $i <= $categoryData['totalPages']; $i++): ?>
                                                            <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                                                <a class="page-link" href="?activity_type=<?php echo urlencode($activityType); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&search=<?php echo urlencode($searchTerm); ?>&<?php echo $categoryPageParam; ?>=<?php echo $i; ?><?php 
                                                                // Preserve ALL other category pages (even page 1)
                                                                foreach ($categoryPages as $catName => $catPage) {
                                                                    if ($catName !== $categoryName) {
                                                                        $catParam = 'page_' . str_replace(' ', '_', strtolower($catName));
                                                                        echo '&' . $catParam . '=' . $catPage;
                                                                    }
                                                                }
                                                                ?>#cat-<?php echo $categorySlug; ?>">
                                                                    <?php echo $i; ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>
                                                        
                                                        <li class="page-item <?php echo ($currentPage >= $categoryData['totalPages']) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="?activity_type=<?php echo urlencode($activityType); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&search=<?php echo urlencode($searchTerm); ?>&<?php echo $categoryPageParam; ?>=<?php echo $currentPage + 1; ?><?php 
                                                            // Preserve ALL other category pages (even page 1)
                                                            foreach ($categoryPages as $catName => $catPage) {
                                                                if ($catName !== $categoryName) {
                                                                    $catParam = 'page_' . str_replace(' ', '_', strtolower($catName));
                                                                    echo '&' . $catParam . '=' . $catPage;
                                                                }
                                                            }
                                                            ?>#cat-<?php echo $categorySlug; ?>">
                                                                Next
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </nav>
                                                <div class="text-center mt-2">
                                                    <small class="text-muted">
                                                        Showing <?php echo (($currentPage - 1) * $logsPerPage) + 1; ?>-<?php echo min($currentPage * $logsPerPage, $categoryData['totalLogs']); ?> of <?php echo $categoryData['totalLogs']; ?> entries
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php $index++; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if ($index === 0): ?>
                        <div class="alert alert-info text-center">
                            <i class="material-icons align-middle me-2">info</i>
                            No activity logs found matching your search criteria
                        </div>
                    <?php endif; ?>
                </div>

                <?php 
                $hasFilters = !empty($activityType) || !empty($dateFrom) || !empty($dateTo) || !empty($searchTerm);
                $hasResults = $index > 0;

                if (!$hasResults): 
                ?>
                    <div class="alert alert-info text-center mt-4">
                        <div class="py-4">
                            <i class="material-icons" style="font-size: 48px;">search_off</i>
                            <h4 class="mt-3">No activity logs found</h4>
                            <?php if ($hasFilters): ?>
                                <p class="mb-0">
                                    No records match your search criteria.
                                    <?php if (!empty($dateFrom)): ?>
                                        <br>Date from: <strong><?php echo date('F j, Y', strtotime($dateFrom)); ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($dateTo)): ?>
                                        <br>Date to: <strong><?php echo date('F j, Y', strtotime($dateTo)); ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($activityType)): ?>
                                        <br>Activity type: <strong><?php echo htmlspecialchars($activityType); ?></strong>
                                    <?php endif; ?>
                                </p>
                                <a href="logs.php" class="btn btn-primary mt-3">
                                    <i class="material-icons align-middle">refresh</i> Clear Filters
                                </a>
                            <?php else: ?>
                                <p>There are no activity logs in the system yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        

    <!-- SWAL Alerts for Profile Picture -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if(isset($_SESSION['profilepic_success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo $_SESSION['profilepic_success']; ?>',
                    confirmButtonColor: '#2a7d4f'
                });
                <?php unset($_SESSION['profilepic_success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['profilepic_error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo $_SESSION['profilepic_error']; ?>',
                    confirmButtonColor: '#dc3545'
                });
                <?php unset($_SESSION['profilepic_error']); ?>
            <?php endif; ?>
        });
    </script>

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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/hr_dashboard.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

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
            <a href="performance_evaluation.php" class="mobile-nav-item">
                <span class="material-icons">assessment</span>
                <span class="mobile-nav-text">Performance Evaluation</span>
            </a>
            <a href="archives.php" class="mobile-nav-item">
                <span class="material-icons">archive</span>
                <span class="mobile-nav-text">Archives</span>
            </a>
            <a href="logs.php" class="mobile-nav-item active">
                <span class="material-icons">receipt_long</span>
                <span class="mobile-nav-text">Logs</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Auto-filter script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit when activity type changes
            document.getElementById('activity_type').addEventListener('change', function() {
                // Submit the form automatically
                this.closest('form').submit();
            });
            
            // Auto-submit when dates change
            document.getElementById('date_from').addEventListener('change', function() {
                this.closest('form').submit();
            });
            
            document.getElementById('date_to').addEventListener('change', function() {
                this.closest('form').submit();
            });
            
            // Keep all accordion items open when there are results
            const accordionItems = document.querySelectorAll('.accordion-collapse');
            accordionItems.forEach(item => {
                try {
                    const bsCollapse = new bootstrap.Collapse(item, {
                        toggle: false
                    });
                    bsCollapse.show();
                } catch (error) {
                    // Fallback if bootstrap object fails
                    item.classList.add('show');
                }
            });
            
            // Add expand/collapse all buttons functionality
            document.getElementById('expandAllBtn')?.addEventListener('click', function() {
                const accordionItems = document.querySelectorAll('.accordion-collapse');
                accordionItems.forEach(item => {
                    const bsCollapse = new bootstrap.Collapse(item, {
                        toggle: false
                    });
                    bsCollapse.show();
                });
            });
            
            document.getElementById('collapseAllBtn')?.addEventListener('click', function() {
                const accordionItems = document.querySelectorAll('.accordion-collapse');
                accordionItems.forEach(item => {
                    const bsCollapse = new bootstrap.Collapse(item, {
                        toggle: false
                    });
                    bsCollapse.hide();
                });
            });
        });
    </script>

    <!-- Modify the daterangepicker initialization -->
    <script>
    $(document).ready(function() {
        // Make entire input group clickable to open the date picker
        $('.dropdown-input-group').click(function(e) {
            $('#date_range').trigger('click');
        });

        // Initialize date range picker with dropdown behavior
        $('#date_range').daterangepicker({
            autoUpdateInput: false,
            opens: 'left',
            showDropdowns: true,
            alwaysShowCalendars: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'MM/DD/YYYY',
                separator: ' - ',
                applyLabel: 'Apply',
                fromLabel: 'From',
                toLabel: 'To',
            },
            ranges: {
               'Today': [moment(), moment()],
               'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               'Last 7 Days': [moment().subtract(6, 'days'), moment()],
               'Last 30 Days': [moment().subtract(29, 'days'), moment()],
               'This Month': [moment().startOf('month'), moment().endOf('month')],
               'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
               // Removed duplicate Custom Range - it's automatically added by daterangepicker
            }
        });

        // Handle date range picker events
        $('#date_range').on('apply.daterangepicker', function(ev, picker) {
            // Update the displayed value
            $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
            
            // Update hidden inputs with formatted dates for server
            $('#date_from').val(picker.startDate.format('YYYY-MM-DD'));
            $('#date_to').val(picker.endDate.format('YYYY-MM-DD'));
            
            // Auto-submit the form when dates are selected
            $(this).closest('form').submit();
        });

        $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
            // Clear the input and hidden fields
            $(this).val('');
            $('#date_from').val('');
            $('#date_to').val('');
            
            // Auto-submit to clear date filters
            $(this).closest('form').submit();
        });
    });
    </script>
</body>
</html>