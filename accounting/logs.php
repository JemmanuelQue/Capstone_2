<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn, 4);
require_once '../db_connection.php';

// Get current accounting user's name
$accountingStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 4 AND status = 'Active' AND User_ID = ?");
$accountingStmt->execute([$_SESSION['user_id']]);
$accountingData = $accountingStmt->fetch(PDO::FETCH_ASSOC);
$accountingName = $accountingData ? $accountingData['First_Name'] . ' ' . $accountingData['Last_Name'] : "Accounting";

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
$profilePic = $profileData['Profile_Pic'] ? $profileData['Profile_Pic'] : '../images/default_profile.png';

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited (except profile)
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

// Set default date filters to current month if not specified
$currentYear = date('Y');
$currentMonth = date('m');
$firstDayOfMonth = "$currentYear-$currentMonth-01";
$lastDayOfMonth = date('Y-m-t'); // t gives the last day of the month

// Get filters from request
$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$dateFrom = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : $firstDayOfMonth;
$dateTo = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : $lastDayOfMonth;
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $recordsPerPage;

// Define accounting-related activity types - only relevant activities
$accountingActivityTypes = [
    'Rate Update',
    'Payroll Generation', 
    'Attendance Archive',
    'Attendance Edit',
    'Holiday Management',
    'Holiday System',
    'Cash Advance',
    'Cash Bond',
    'Salary Disbursement',
    'Financial Update',
    'Attendance Add',
    'Attendance Delete Permanent'
];

// First, get activity types from database - only accounting-related
$activityTypesQuery = "
    SELECT DISTINCT Activity_Type 
    FROM (
        SELECT 'Attendance Edit' as Activity_Type FROM edit_attendance_logs 
        WHERE Editor_User_ID IN (SELECT User_ID FROM users WHERE Role_ID = 4)
        UNION
        SELECT Activity_Type FROM activity_logs 
        WHERE (User_ID IN (SELECT User_ID FROM users WHERE Role_ID = 4)
        OR Activity_Type IN ('" . implode("', '", $accountingActivityTypes) . "'))
        AND Activity_Type NOT LIKE '%Login%'
        AND Activity_Type NOT LIKE '%Logout%'
        AND Activity_Type NOT LIKE '%User Creation%'
        AND Activity_Type NOT LIKE '%User Update%'
        AND Activity_Type NOT LIKE '%User Archive%'
        AND Activity_Type NOT LIKE '%User Recovery%'
        AND Activity_Type NOT LIKE '%Guard%'
        AND Activity_Type NOT LIKE '%Password Reset%'
    ) combined
    ORDER BY Activity_Type ASC
";
$activityTypesStmt = $conn->query($activityTypesQuery);
$activityTypesFromDB = $activityTypesStmt->fetchAll(PDO::FETCH_COLUMN);

// Merge database activity types with predefined accounting types to ensure all are available
$allActivityTypes = array_unique(array_merge($activityTypesFromDB, $accountingActivityTypes));
sort($allActivityTypes);

// Prepare base queries for activity logs - only accounting-relevant activities
$activityLogsBaseQuery = "
    SELECT 
        al.Log_ID,
        al.Activity_Type,
        al.Activity_Details,
        al.Timestamp,
        al.User_ID,
        u.First_Name,
        u.Last_Name,
        u.Role_ID,
        r.Role_Name,
        NULL as Old_Time_In,
        NULL as New_Time_In,
        NULL as Old_Time_Out,
        NULL as New_Time_Out,
        'activity_logs' as source_table
    FROM activity_logs al
    LEFT JOIN users u ON al.User_ID = u.User_ID
    LEFT JOIN roles r ON u.Role_ID = r.Role_ID
    WHERE ((u.Role_ID = 4 AND al.Activity_Type IN ('" . implode("', '", $accountingActivityTypes) . "'))
    OR al.Activity_Type IN ('" . implode("', '", $accountingActivityTypes) . "'))
    AND al.Activity_Type NOT LIKE '%Login%'
    AND al.Activity_Type NOT LIKE '%Logout%'
    AND al.Activity_Type NOT LIKE '%User Creation%'
    AND al.Activity_Type NOT LIKE '%User Update%'
    AND al.Activity_Type NOT LIKE '%User Archive%'
    AND al.Activity_Type NOT LIKE '%User Recovery%'
    AND al.Activity_Type NOT LIKE '%Guard%'
    AND al.Activity_Type NOT LIKE '%Password Reset%'
";

// Prepare base query for attendance edits logs - only accounting role edits with actual changes
$editLogsBaseQuery = "
    SELECT DISTINCT
        eal.Log_ID,
        'Attendance Edit' as Activity_Type,
        CONCAT('Edited attendance record ID ', eal.Attendance_ID, 
               IF(eal.Action_Description LIKE '%Reason:%', 
                  CONCAT(' - Reason: ', SUBSTRING_INDEX(eal.Action_Description, 'Reason:', -1)), 
                  IF(eal.Action_Description LIKE '%reason:%',
                     CONCAT(' - Reason: ', SUBSTRING_INDEX(eal.Action_Description, 'reason:', -1)),
                     ''))) as Activity_Details,
        eal.Edit_Timestamp as Timestamp,
        eal.Editor_User_ID as User_ID,
        u.First_Name,
        u.Last_Name,
        u.Role_ID,
        r.Role_Name,
        eal.Old_Time_In,
        eal.New_Time_In,
        eal.Old_Time_Out,
        eal.New_Time_Out,
        'edit_attendance_logs' as source_table
    FROM edit_attendance_logs eal
    LEFT JOIN users u ON eal.Editor_User_ID = u.User_ID
    LEFT JOIN roles r ON u.Role_ID = r.Role_ID
    WHERE u.Role_ID = 4
    AND (
        -- STRICT: Only show records that have both old AND new time values and they are different
        (eal.Old_Time_In IS NOT NULL AND eal.New_Time_In IS NOT NULL AND eal.Old_Time_In != eal.New_Time_In)
        OR 
        (eal.Old_Time_Out IS NOT NULL AND eal.New_Time_Out IS NOT NULL AND eal.Old_Time_Out != eal.New_Time_Out)
    )
";

// Apply date filters
if (!empty($dateFrom)) {
    $formattedDateFrom = date('Y-m-d', strtotime($dateFrom));
    $activityLogsBaseQuery .= " AND DATE(al.Timestamp) >= '$formattedDateFrom'";
    $editLogsBaseQuery .= " AND DATE(eal.Edit_Timestamp) >= '$formattedDateFrom'";
}

if (!empty($dateTo)) {
    $formattedDateTo = date('Y-m-d', strtotime($dateTo));
    $activityLogsBaseQuery .= " AND DATE(al.Timestamp) <= '$formattedDateTo'";
    $editLogsBaseQuery .= " AND DATE(eal.Edit_Timestamp) <= '$formattedDateTo'";
}

// Apply activity type filter
if (!empty($activityType)) {
    if ($activityType == 'Attendance Edit') {
        $activityLogsBaseQuery .= " AND al.Activity_Type = '$activityType'";
        // Keep edit_attendance_logs as is (all entries are attendance edits)
    } else {
        $activityLogsBaseQuery .= " AND al.Activity_Type = '$activityType'";
        // Create an empty result with the SAME column structure
        $editLogsBaseQuery = "
            SELECT 
                eal.Log_ID,
                'Attendance Edit' as Activity_Type,
                '' as Activity_Details,
                eal.Edit_Timestamp as Timestamp,
                eal.Editor_User_ID as User_ID,
                u.First_Name,
                u.Last_Name,
                u.Role_ID,
                r.Role_Name,
                eal.Old_Time_In,
                eal.New_Time_In,
                eal.Old_Time_Out,
                eal.New_Time_Out,
                'edit_attendance_logs' as source_table
            FROM edit_attendance_logs eal
            LEFT JOIN users u ON eal.Editor_User_ID = u.User_ID
            LEFT JOIN roles r ON u.Role_ID = r.Role_ID
            WHERE 1=0
        ";
    }
}

// Apply search term filter
if (!empty($searchTerm)) {
    $activityLogsBaseQuery .= " AND (
        al.Activity_Details LIKE '%$searchTerm%'
        OR u.First_Name LIKE '%$searchTerm%'
        OR u.Last_Name LIKE '%$searchTerm%'
    )";
    
    $editLogsBaseQuery .= " AND (
        eal.Action_Description LIKE '%$searchTerm%'
        OR u.First_Name LIKE '%$searchTerm%'
        OR u.Last_Name LIKE '%$searchTerm%'
        OR eal.Attendance_ID LIKE '%$searchTerm%'
    )";
}

// Combined query for counting total records
$countQuery = "
    SELECT COUNT(*) as total FROM (
        $activityLogsBaseQuery
        UNION ALL
        $editLogsBaseQuery
    ) as combined_logs
";
$countStmt = $conn->query($countQuery);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get logs with pagination
$logsQuery = "
    SELECT * FROM (
        $activityLogsBaseQuery
        UNION ALL
        $editLogsBaseQuery
    ) as combined_logs
    ORDER BY Timestamp DESC
";
$logsStmt = $conn->query($logsQuery);
$allLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get pagination parameters for categories
$categoryPages = [];
if (isset($_GET['cat_page'])) {
    $categoryPages = json_decode(urldecode($_GET['cat_page']), true) ?: [];
}

// Define how logs should be categorized/grouped - separate tables for each activity type
if (empty($activityType)) {
    // For "All Activities" view, create separate categories for each activity type
    $categories = [
        'Attendance Add' => ['Attendance Add'],
        'Attendance Edit' => ['Attendance Edit'], 
        'Attendance Archive' => ['Attendance Archive'],
        'Attendance Delete Permanent' => ['Attendance Delete Permanent'],
        'Holiday Management' => ['Holiday Management'],
        'Rate Update' => ['Rate Update'],
        'Payroll Generation' => ['Payroll Generation'],
        'Cash Advance' => ['Cash Advance']
    ];

    // Initialize categorized logs array
    $categorizedLogs = [];
    $categoryCounts = [];

    foreach ($categories as $categoryName => $activityTypesList) {
        $categorizedLogs[$categoryName] = [];
        $categoryCounts[$categoryName] = 0;
    }

    // Process logs into categories - only assign to defined categories
    foreach ($allLogs as $log) {
        $logActivityType = $log['Activity_Type'];
        $assigned = false;
        
        foreach ($categories as $categoryName => $activityTypesList) {
            if (!empty($activityTypesList) && in_array($logActivityType, $activityTypesList)) {
                $categorizedLogs[$categoryName][] = $log;
                $categoryCounts[$categoryName]++;
                $assigned = true;
                break;
            }
        }
        // Note: Removed "Other Activities" - logs not matching defined categories are excluded
    }
    
    // Apply pagination to each category independently
    $paginatedCategorizedLogs = [];
    foreach ($categorizedLogs as $categoryName => $logs) {
        // Get total records for this category
        $totalCategoryRecords = count($logs);
        
        // Get current page for this category
        $categoryCurrentPage = isset($categoryPages[$categoryName]) ? intval($categoryPages[$categoryName]) : 1;
        if ($categoryCurrentPage < 1) $categoryCurrentPage = 1;
        
        // Calculate total pages for this category
        $categoryTotalPages = ceil($totalCategoryRecords / $recordsPerPage);
        if ($categoryCurrentPage > $categoryTotalPages) $categoryCurrentPage = $categoryTotalPages;
        
        // Calculate offset for this category
        $categoryOffset = ($categoryCurrentPage - 1) * $recordsPerPage;
        
        // Apply pagination to this category
        $paginatedCategorizedLogs[$categoryName] = array_slice($logs, $categoryOffset, $recordsPerPage);
        
        // Store pagination info for display
        $categorizedLogs[$categoryName . '_total_pages'] = $categoryTotalPages;
        $categorizedLogs[$categoryName . '_current_page'] = $categoryCurrentPage;
    }
    
    // Replace categorizedLogs with paginated version
    $categorizedLogs = $paginatedCategorizedLogs;
} else {
    // For specific activity type filter, create individual categories
    $categories = [
        'Attendance Add' => ['Attendance Add'],
        'Attendance Edit' => ['Attendance Edit'], 
        'Attendance Archive' => ['Attendance Archive'],
        'Attendance Delete Permanent' => ['Attendance Delete Permanent'],
        'Holiday Management' => ['Holiday Management'],
        'Rate Update' => ['Rate Update'],
        'Payroll Generation' => ['Payroll Generation'],
        'Cash Advance' => ['Cash Advance']
    ];

    // Categorize logs
    $categorizedLogs = [];
    $categoryCounts = [];

    foreach ($categories as $categoryName => $activityTypesList) {
        $categorizedLogs[$categoryName] = [];
        $categoryCounts[$categoryName] = 0;
    }

    // Process logs into categories - only assign to defined categories
    foreach ($allLogs as $log) {
        $logActivityType = $log['Activity_Type'];
        $assigned = false;
        
        foreach ($categories as $categoryName => $activityTypesList) {
            if (!empty($activityTypesList) && in_array($logActivityType, $activityTypesList)) {
                $categorizedLogs[$categoryName][] = $log;
                $categoryCounts[$categoryName]++;
                $assigned = true;
                break;
            }
        }
        // Note: Removed "Other Activities" - logs not matching defined categories are excluded
    }
    
    // Apply pagination to each category when filtering by specific activity type
    $paginatedCategorizedLogs = [];
    foreach ($categorizedLogs as $categoryName => $logs) {
        // Get total records for this category
        $totalCategoryRecords = count($logs);
        
        // Get current page for this category
        $categoryCurrentPage = isset($categoryPages[$categoryName]) ? intval($categoryPages[$categoryName]) : 1;
        if ($categoryCurrentPage < 1) $categoryCurrentPage = 1;
        
        // Calculate total pages for this category
        $categoryTotalPages = ceil($totalCategoryRecords / $recordsPerPage);
        if ($categoryCurrentPage > $categoryTotalPages) $categoryCurrentPage = $categoryTotalPages;
        
        // Calculate offset for this category
        $categoryOffset = ($categoryCurrentPage - 1) * $recordsPerPage;
        
        // Apply pagination to this category
        $paginatedCategorizedLogs[$categoryName] = array_slice($logs, $categoryOffset, $recordsPerPage);
        
        // Store pagination info for display
        $categorizedLogs[$categoryName . '_total_pages'] = $categoryTotalPages;
        $categorizedLogs[$categoryName . '_current_page'] = $categoryCurrentPage;
    }
    
    // Replace categorizedLogs with paginated version
    $categorizedLogs = $paginatedCategorizedLogs;
}

// Remove empty categories
foreach ($categorizedLogs as $category => $logs) {
    if (empty($logs)) {
        unset($categorizedLogs[$category]);
        unset($categoryCounts[$category]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Logs - Green Meadows Security Agency</title>
    
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
    
    <style>
        .filter-form {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }
        .badge-category {
            font-size: 0.8em;
            padding: 5px 8px;
        }
        .badge-financial {
            background-color: #28a745;
            color: white;
        }
        .badge-attendance {
            background-color: #007bff;
            color: white;
        }
        .badge-holiday {
            background-color: #fd7e14;
            color: white;
        }
        .badge-other {
            background-color: #6c757d;
            color: white;
        }

        /* Add these styles to your existing CSS */
        .accordion-button {
            font-weight: 500;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: rgba(42, 125, 79, 0.1);
            color: #2a7d4f;
            box-shadow: none;
        }
        
        .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(42, 125, 79, 0.25);
        }
        
        .accordion-item {
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.25rem;
            overflow: hidden;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>
<body>
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
                <a href="accounting_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
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
                <a href="payroll.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
                    <span class="material-icons">payments</span>
                    <span>Payroll</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="rate_locations.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Rate per Locations">
                    <span class="material-icons">attach_money</span>
                    <span>Rate per Locations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="calendar.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Calendar">
                    <span class="material-icons">date_range</span>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="masterlist.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">assignment</span>
                    <span>Masterlist</span>
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
            <div class="user-profile" id="userProfile" data-bs-toggle="modal" data-bs-target="#profileModal">
                <span><?php echo $accountingName; ?></span>
                <a href="profile.php"><img src="<?php echo $profilePic; ?>" alt="User Profile"></a>
            </div>
        </div>

        <script>
            function updateDateTime() {
                const now = new Date();
                // Format date: YYYY-MM-DD or whatever you want
                const date = now.toLocaleDateString('en-US', {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                });
                // Format time: HH:MM:SS
                const time = now.toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });

                document.getElementById('current-date').textContent = date;
                document.getElementById('current-time').textContent = time;
            }
            // update every second
            setInterval(updateDateTime, 1000);
            // run immediately
            updateDateTime();
            </script>

        <div class="content-container">
            <div class="container-fluid px-4">
                <h1 class="activity-logs-title my-4">Accounting Activity Logs</h1>
                
                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="activity_type" class="form-label">Activity Type</label>
                                <select name="activity_type" id="activity_type" class="form-select" onchange="submitForm()">
                                    <option value="">All Activities</option>
                                    <?php 
                                    // Define only the activity types that actually exist in accounting logs
                                    $availableActivityTypes = [
                                        'Attendance Add',
                                        'Attendance Archive', 
                                        'Attendance Delete Permanent',
                                        'Attendance Edit',
                                        'Holiday Management',
                                        'Rate Update'
                                    ];
                                    
                                    // Filter to only show types that exist in the database
                                    $filteredActivityTypes = [];
                                    foreach ($availableActivityTypes as $type) {
                                        if (in_array($type, $allActivityTypes)) {
                                            $filteredActivityTypes[] = $type;
                                        }
                                    }
                                    
                                    foreach ($filteredActivityTypes as $type): 
                                    ?>
                                        <option value="<?php echo $type; ?>" <?php echo ($activityType === $type) ? 'selected' : ''; ?>>
                                            <?php echo $type; ?>
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
                <?php if (array_sum($categoryCounts) > 0): ?>
                    <div class="accordion mb-4" id="activityLogsAccordion">
                        <?php $index = 0; ?>
                        <?php foreach ($categorizedLogs as $categoryName => $categoryLogs): ?>
                            <?php if (count($categoryLogs) > 0): ?>
                                <div class="accordion-item mb-3">
                                    <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                        <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" 
                                                aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                                aria-controls="collapse<?php echo $index; ?>">
                                            <i class="material-icons me-2">
                                                <?php
                                                // Icon based on category
                                                switch($categoryName) {
                                                    case 'Attendance Add':
                                                        echo 'add_circle';
                                                        break;
                                                    case 'Attendance Edit':
                                                        echo 'edit';
                                                        break;
                                                    case 'Attendance Archive':
                                                        echo 'archive';
                                                        break;
                                                    case 'Attendance Delete Permanent':
                                                        echo 'delete_forever';
                                                        break;
                                                    case 'Holiday Management':
                                                        echo 'event_note';
                                                        break;
                                                    case 'Rate Update':
                                                        echo 'trending_up';
                                                        break;
                                                    case 'Payroll Generation':
                                                        echo 'receipt_long';
                                                        break;
                                                    case 'Cash Advance':
                                                        echo 'attach_money';
                                                        break;
                                                    default:
                                                        echo 'list';
                                                        break;
                                                }
                                                ?>
                                            </i>
                                            <?php echo $categoryName; ?> 
                                            <?php if (!empty($activityType) && $categoryCounts[$categoryName] > count($categoryLogs)): ?>
                                                <span class="badge bg-primary ms-2">Showing <?php echo count($categoryLogs); ?> of <?php echo $categoryCounts[$categoryName]; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-primary ms-2"><?php echo count($categoryLogs); ?></span>
                                                <?php if ($categoryCounts[$categoryName] > $recordsPerPage): ?>
                                                    <span class="badge bg-secondary ms-2">
                                                        Showing <?php echo count($categoryLogs); ?> of <?php echo $categoryCounts[$categoryName]; ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $index; ?>" 
                                         class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
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
                                                        <?php foreach ($categoryLogs as $log): ?>
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
                                                                    $activityType = $log['Activity_Type'];
                                                                    $badgeClass = 'bg-primary';
                                                                    $iconName = 'info';
                                                                    
                                                                    if (strpos($activityType, 'Delete') !== false || strpos($activityType, 'Deletion') !== false) {
                                                                        $badgeClass = 'bg-danger';
                                                                        $iconName = 'delete_forever';
                                                                    } elseif (strpos($activityType, 'Archive') !== false) {
                                                                        $badgeClass = 'bg-warning text-dark';
                                                                        $iconName = 'folder_delete';
                                                                    } elseif (strpos($activityType, 'Edit') !== false || strpos($activityType, 'Update') !== false) {
                                                                        $badgeClass = 'bg-info text-dark';
                                                                        $iconName = 'edit';
                                                                    } elseif (strpos($activityType, 'Add') !== false || strpos($activityType, 'Generation') !== false) {
                                                                        $badgeClass = 'bg-success';
                                                                        $iconName = 'add_circle';
                                                                    } elseif (strpos($activityType, 'Holiday') !== false) {
                                                                        $badgeClass = 'bg-warning text-dark';
                                                                        $iconName = 'event';
                                                                    } elseif (strpos($activityType, 'Rate') !== false || strpos($activityType, 'Financial') !== false) {
                                                                        $badgeClass = 'bg-success';
                                                                        $iconName = 'attach_money';
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                                        <i class="material-icons align-text-bottom" style="font-size: 14px;"><?php echo $iconName; ?></i>
                                                                        <?php echo $activityType; ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php 
                                                                    echo htmlspecialchars($log['Activity_Details']);
                                                                    
                                                                    // Show changes for Attendance Edit entries with time changes
                                                                    if ($log['Activity_Type'] === 'Attendance Edit' && 
                                                                        (isset($log['Old_Time_In']) || isset($log['Old_Time_Out']))) {
                                                                        echo '<br><small class="text-muted"><strong>Changes:</strong><br>';
                                                                        
                                                                        if (isset($log['Old_Time_In']) && isset($log['New_Time_In']) && $log['Old_Time_In'] !== $log['New_Time_In']) {
                                                                            echo 'Time In: ' . ($log['Old_Time_In'] ?? 'None') . ' → ' . ($log['New_Time_In'] ?? 'None') . '<br>';
                                                                        }
                                                                        
                                                                        if (isset($log['Old_Time_Out']) && isset($log['New_Time_Out']) && $log['Old_Time_Out'] !== $log['New_Time_Out']) {
                                                                            echo 'Time Out: ' . ($log['Old_Time_Out'] ?? 'None') . ' → ' . ($log['New_Time_Out'] ?? 'None');
                                                                        }
                                                                        
                                                                        echo '</small>';
                                                                    }
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                <?php if ($categoryCounts[$categoryName] > $recordsPerPage): ?>
                                                    <div class="d-flex justify-content-between align-items-center p-2 bg-light border-top">
                                                        <div>
                                                            Showing <?php echo count($categoryLogs); ?> of <?php echo $categoryCounts[$categoryName]; ?> entries
                                                        </div>
                                                        <div class="pagination-container">
                                                            <nav aria-label="Category pagination">
                                                                <ul class="pagination pagination-sm mb-0">
                                                                    <?php 
                                                                    $categoryCurrentPage = isset($categoryPages[$categoryName]) ? intval($categoryPages[$categoryName]) : 1;
                                                                    $categoryTotalPages = ceil($categoryCounts[$categoryName] / $recordsPerPage);
                                                                    
                                                                    // Previous page button
                                                                    if ($categoryCurrentPage > 1): 
                                                                        $prevCategoryPages = $categoryPages;
                                                                        $prevCategoryPages[$categoryName] = $categoryCurrentPage - 1;
                                                                    ?>
                                                                    <li class="page-item">
                                                                        <a class="page-link" href="?activity_type=<?php echo urlencode($activityType); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&search=<?php echo urlencode($searchTerm); ?>&limit=<?php echo $recordsPerPage; ?>&cat_page=<?php echo urlencode(json_encode($prevCategoryPages)); ?>">
                                                                            &laquo;
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php 
                                                                    // Page number buttons - show up to 5 page numbers
                                                                    $startPage = max(1, min($categoryCurrentPage - 2, $categoryTotalPages - 4));
                                                                    $endPage = min($categoryTotalPages, max($categoryCurrentPage + 2, 5));
                                                                    
                                                                    for ($i = $startPage; $i <= $endPage; $i++): 
                                                                        $pageCategoryPages = $categoryPages;
                                                                        $pageCategoryPages[$categoryName] = $i;
                                                                    ?>
                                                                    <li class="page-item <?php echo ($i == $categoryCurrentPage) ? 'active' : ''; ?>">
                                                                        <a class="page-link" href="?activity_type=<?php echo urlencode($activityType); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&search=<?php echo urlencode($searchTerm); ?>&limit=<?php echo $recordsPerPage; ?>&cat_page=<?php echo urlencode(json_encode($pageCategoryPages)); ?>">
                                                                            <?php echo $i; ?>
                                                                        </a>
                                                                    </li>
                                                                    <?php endfor; ?>
                                                                    
                                                                    <?php 
                                                                    // Next page button
                                                                    if ($categoryCurrentPage < $categoryTotalPages): 
                                                                        $nextCategoryPages = $categoryPages;
                                                                        $nextCategoryPages[$categoryName] = $categoryCurrentPage + 1;
                                                                    ?>
                                                                    <li class="page-item">
                                                                        <a class="page-link" href="?activity_type=<?php echo urlencode($activityType); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&search=<?php echo urlencode($searchTerm); ?>&limit=<?php echo $recordsPerPage; ?>&cat_page=<?php echo urlencode(json_encode($nextCategoryPages)); ?>">
                                                                            &raquo;
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                </ul>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php $index++; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <div class="py-4">
                            <i class="material-icons" style="font-size: 48px;">search_off</i>
                            <h4 class="mt-3">No activity logs found</h4>
                            
                            <?php if (!empty($dateFrom) || !empty($dateTo) || !empty($activityType) || !empty($searchTerm)): ?>
                                <p class="mb-0">No records match your search criteria.</p>
                                <a href="logs.php" class="btn btn-primary mt-3">
                                    <i class="material-icons align-middle">refresh</i> Clear Filters
                                </a>
                            <?php else: ?>
                                <p>There are no accounting logs in the system yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            document.getElementById('toggleSidebar').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('collapsed');
                document.getElementById('main-content').classList.toggle('expanded');
            });

            // Profile picture preview
            document.getElementById('profilePic').addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('profilePreview').src = e.target.result;
                    }
                    reader.readAsDataURL(e.target.files[0]);
                }
            });

            // Display current date and time
            function updateDateTime() {
                const now = new Date();
                const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
                
                document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', dateOptions);
                document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', timeOptions);
            }
            
            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Fix for activity type dropdown - ensure it shows the correct value
            const activityTypeSelect = document.getElementById('activity_type');
            if (activityTypeSelect) {
                // If there's no activityType in the URL, set to "All Activities"
                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.has('activity_type') || urlParams.get('activity_type') === '') {
                    activityTypeSelect.value = '';
                }
            }

            // Function to submit form when dropdown changes
            function submitForm() {
                const form = document.querySelector('form');
                if (form) {
                    form.submit();
                }
            }

            // Make submitForm available globally
            window.submitForm = submitForm;
        });
    </script>

      <script>
        $(function() {
            $('#date_range').daterangepicker({
                autoUpdateInput: false,
                locale: { cancelLabel: 'Clear' }
            });

            $('#date_range').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
                $('#date_from').val(picker.startDate.format('YYYY-MM-DD'));
                $('#date_to').val(picker.endDate.format('YYYY-MM-DD'));
            });

            $('#date_range').on('cancel.daterangepicker', function() {
                $(this).val('');
                $('#date_from').val('');
                $('#date_to').val('');
            });
        });
        </script>

    <!-- Mobile Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <!-- FIXED: Match sidebar links -->
            <a href="accounting_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
            </a>
            <!-- FIXED: Add missing links to match sidebar -->
            <a href="rate_locations.php" class="mobile-nav-item">
                <span class="material-icons">attach_money</span>
                <span class="mobile-nav-text">Rate per Locations</span>
            </a>
            <a href="calendar.php" class="mobile-nav-item">
                <span class="material-icons">date_range</span>
                <span class="mobile-nav-text">Calendar</span>
            </a>
            <a href="masterlist.php" class="mobile-nav-item">
                <span class="material-icons">assignment</span>
                <span class="mobile-nav-text">Masterlist</span>
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

</body>
</html>