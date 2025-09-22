<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn);
require_once '../db_connection.php';

// Get current super admin user's name
$superadminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 1 AND status = 'Active' AND User_ID = ?");
$superadminStmt->execute([$_SESSION['user_id']]);
$superadminData = $superadminStmt->fetch(PDO::FETCH_ASSOC);
$superadminName = $superadminData ? $superadminData['First_Name'] . ' ' . $superadminData['Last_Name'] : "Super Admin";

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $superadminProfile = $profileData['Profile_Pic'];
} else {
    $superadminProfile = '../images/default_profile.png';
}

// Get filters from request
$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$logsPerPage = 10; // Number of logs to show per page

// Build query conditions
$conditions = [];
$params = [];

if (!empty($activityType)) {
    $conditions[] = "al.Activity_Type = ?";
    $params[] = $activityType;
}

if (!empty($dateFrom)) {
    // Ensure date is in YYYY-MM-DD format for database comparison
    $formattedDateFrom = date('Y-m-d', strtotime($dateFrom));
    $conditions[] = "DATE(al.Timestamp) >= ?";
    $params[] = $formattedDateFrom;
}

if (!empty($dateTo)) {
    // Ensure date is in YYYY-MM-DD format for database comparison
    $formattedDateTo = date('Y-m-d', strtotime($dateTo));
    $conditions[] = "DATE(al.Timestamp) <= ?";
    $params[] = $formattedDateTo;
}

if (!empty($searchTerm)) {
    $conditions[] = "(al.Activity_Details LIKE ? OR u.First_Name LIKE ? OR u.Last_Name LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

// Construct the WHERE clause
$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get distinct activity types
$activityTypesStmt = $conn->query("SELECT DISTINCT Activity_Type FROM activity_logs ORDER BY Activity_Type");
$activityTypes = $activityTypesStmt->fetchAll(PDO::FETCH_COLUMN);

// Activity categories (for grouping) - expanded to cover values present in SQL dump
$categories = [
    'User Creation'      => ['User Creation'],
    'User Update'        => ['User Update', 'Guard Update', 'Employee Update'],
    'User Archive'       => ['User Archive', 'Employee Archive'],
    'User Recovery'      => ['User Recovery', 'Guard Recovery', 'Attendance Recovery'],
    'User Deletion'      => ['User Deletion', 'Attendance Delete Permanent', 'Guard Deletion'],
    'Login Activity'     => ['Login', 'Logout', 'Login Failed'],
    'System Changes'     => ['System Settings Update', 'Configuration Change', 'Rate Update', 'Holiday Management', 'Holiday System', 'Password Reset', 'Password Management'],
    'Attendance Actions' => ['Attendance Add', 'Attendance Edit', 'Attendance Archive', 'Attendance Restore'],
    'Other Activities'   => []
];

// Helper functions for category handling and rendering
function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    return trim($text, '-');
}

function buildCategoryWhereAndParams($categoryName, $categories, $conditions, $params) {
    $categoryConditions = $conditions;
    $categoryParams = $params;

    // Special prefix-based buckets to be resilient to new subtypes
    if ($categoryName === 'Attendance Actions') {
        $categoryConditions[] = "al.Activity_Type LIKE ?";
        $categoryParams[] = 'Attendance %';
    } elseif ($categoryName === 'System Changes') {
        // System settings + any Holiday* and Password* and Rate Update
        $categoryConditions[] = "(al.Activity_Type IN (?,?,?) OR al.Activity_Type LIKE ? OR al.Activity_Type LIKE ?)";
        array_push($categoryParams, 'System Settings Update', 'Configuration Change', 'Rate Update', 'Holiday %', 'Password %');
    } else if (!empty($categories[$categoryName])) {
        $types = $categories[$categoryName];
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $categoryConditions[] = "al.Activity_Type IN ($placeholders)";
        foreach ($types as $t) { $categoryParams[] = $t; }
    } else {
        // Other Activities = not in any defined type OR prefix categories
        $allDefined = [];
        foreach ($categories as $cn => $types) {
            if ($cn !== 'Other Activities') {
                $allDefined = array_merge($allDefined, $types);
            }
        }
        if (!empty($allDefined)) {
            $placeholders = implode(',', array_fill(0, count($allDefined), '?'));
            $categoryConditions[] = "al.Activity_Type NOT IN ($placeholders)";
            foreach ($allDefined as $t) { $categoryParams[] = $t; }
        }
        // Also exclude prefix groups explicitly
        $categoryConditions[] = "al.Activity_Type NOT LIKE ?"; $categoryParams[] = 'Attendance %';
        $categoryConditions[] = "al.Activity_Type NOT LIKE ?"; $categoryParams[] = 'Holiday %';
        $categoryConditions[] = "al.Activity_Type NOT LIKE ?"; $categoryParams[] = 'Password %';
    }

    $where = !empty($categoryConditions) ? "WHERE " . implode(" AND ", $categoryConditions) : "";
    return [$where, $categoryParams];
}

function renderRowsHtml(array $logs) {
    ob_start();
    foreach ($logs as $log) {
        $ts = date('M d, Y g:i A', strtotime($log['Timestamp']));
        $userRole = isset($log['Role_Name']) ? $log['Role_Name'] : 'Unknown';
        $userName = (isset($log['First_Name']) && isset($log['Last_Name'])) ? ($log['First_Name'].' '.$log['Last_Name']) : 'System';
        $activityTypeRow = $log['Activity_Type'];
        $badgeClass = 'bg-primary';
        if (stripos($activityTypeRow, 'Delete') !== false) $badgeClass = 'bg-danger';
        elseif (stripos($activityTypeRow, 'Archive') !== false) $badgeClass = 'bg-warning text-dark';
        elseif (stripos($activityTypeRow, 'Recovery') !== false || stripos($activityTypeRow, 'Recover') !== false) $badgeClass = 'bg-success';
        elseif (stripos($activityTypeRow, 'Update') !== false || stripos($activityTypeRow, 'Edit') !== false) $badgeClass = 'bg-info text-dark';
        elseif (stripos($activityTypeRow, 'Create') !== false || stripos($activityTypeRow, 'Add') !== false) $badgeClass = 'bg-success';
        ?>
        <tr>
            <td><?php echo htmlspecialchars($ts); ?></td>
            <td><?php echo htmlspecialchars($userName); ?> <span class="text-muted">(<?php echo htmlspecialchars($userRole); ?>)</span></td>
            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($activityTypeRow); ?></span></td>
            <td><?php echo htmlspecialchars($log['Activity_Details']); ?></td>
        </tr>
        <?php
    }
    return ob_get_clean();
}

// Remove the AJAX pagination endpoint - DataTables will handle this

// Categorize and fetch logs for each activity type category
$categorizedLogs = [];
$categoryCounts = [];

foreach ($categories as $categoryName => $activityTypesList) {
    // Build WHERE/params with the same helper as AJAX
    [$categoryWhereClause, $categoryParams] = buildCategoryWhereAndParams($categoryName, $categories, $conditions, $params);

    // Count total logs for this category
    $countQuery = "
        SELECT COUNT(*) as total
        FROM activity_logs al
        LEFT JOIN users u ON al.User_ID = u.User_ID
        $categoryWhereClause
    ";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($categoryParams);
    $totalLogs = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $categoryCounts[$categoryName] = $totalLogs;

    // Fetch ALL logs for this category (no pagination - let DataTables handle it)
    if ($totalLogs > 0) {
        $logsQuery = "
            SELECT al.*, u.First_Name, u.Last_Name, u.Role_ID, r.Role_Name 
            FROM activity_logs al
            LEFT JOIN users u ON al.User_ID = u.User_ID
            LEFT JOIN roles r ON u.Role_ID = r.Role_ID
            $categoryWhereClause
            ORDER BY al.Timestamp DESC
        ";
        $logsStmt = $conn->prepare($logsQuery);
        $logsStmt->execute($categoryParams);
        $categoryLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
        $categorizedLogs[$categoryName] = [
            'logs' => $categoryLogs,
            'count' => $totalLogs
        ];
    } else {
        $categorizedLogs[$categoryName] = [
            'logs' => [],
            'count' => 0
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
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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
                <a href="superadmin_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payroll.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
                    <span class="material-icons">payments</span>
                    <span>Payroll</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="leave_request.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Leave Request">
                    <span class="material-icons">event_note</span>
                    <span>Leave Request</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="recruitment.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Recruitment">
                    <span class="material-icons">person_search</span>
                    <span>Recruitment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="performance_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Performance Evaluation">
                    <span class="material-icons">assessment</span>
                    <span>Performance Evaluation</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users_list.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">people</span>
                    <span>Masterlist</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="daily_time_record.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Daily Time Record">
                    <span class="material-icons">schedule</span>
                    <span>Daily Time Record</span>
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
        <!-- Header -->
        <div class="header">
            <button class="toggle-sidebar" id="toggleSidebar">
                <span class="material-icons">menu</span>
            </button>
            <div class="current-datetime ms-3 d-none d-md-block">
                    <span id="current-date"></span> | <span id="current-time"></span>
                </div>
                <a href="profile.php" class="user-profile" id="userProfile" style="color:black; text-decoration:none;">
                    <span><?php echo htmlspecialchars($superadminName); ?></span>
                    <img src="<?php echo $superadminProfile; ?>" alt="User Profile">
                </a>
        </div><br>

        <div class="content-container">
            <div class="container-fluid px-4">
                <h1 class="activity-logs-title">Activity Logs</h1>
                <p class="text-center mb-4">View system activity logs including user management operations</p>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="activity_type" class="form-label">Activity Type</label>
                                <select name="activity_type" id="activity_type" class="form-select">
                                    <option value="">All Activities</option>
                                    <?php foreach ($activityTypes as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo ($activityType === $type) ? 'selected' : ''; ?>>
                                            <?php echo $type; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Search details or user names" value="<?php echo $searchTerm; ?>">
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
                        
                        <!-- Accordion Controls -->
                        <div class="mt-3 text-center">
                            <button type="button" class="btn btn-outline-primary btn-sm me-2" id="expandAllBtn">
                                <i class="material-icons align-middle">expand_more</i> Expand All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAllBtn">
                                <i class="material-icons align-middle">expand_less</i> Collapse All
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Activity Log Categories -->
                <div class="accordion" id="activityLogsAccordion">
                    <?php $index = 0; ?>
            <?php foreach ($categorizedLogs as $categoryName => $categoryData): ?>
                        <?php if ($categoryCounts[$categoryName] > 0): ?>
                <?php $slug = slugify($categoryName); ?>
                <div class="accordion-item mb-3" data-category="<?php echo $slug; ?>">
                                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" 
                                            aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                            aria-controls="collapse<?php echo $index; ?>">
                                        <i class="material-icons me-2">
                                            <?php
                                            switch($categoryName) {
                                                case 'User Creation': echo 'person_add'; break;
                                                case 'User Update': echo 'edit'; break;
                                                case 'User Archive': echo 'archive'; break;
                                                case 'User Recovery': echo 'restore'; break;
                                                case 'User Deletion': echo 'delete_forever'; break;
                                                case 'Login Activity': echo 'login'; break;
                        case 'System Changes': echo 'settings_applications'; break;
                        case 'Attendance Actions': echo 'schedule'; break;
                                                default: echo 'list'; break;
                                            }
                                            ?>
                                        </i>
                                        <?php echo $categoryName; ?> 
                                        <span class="badge bg-primary ms-2"><?php echo $categoryCounts[$categoryName]; ?></span>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $index; ?>" 
                                     class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                                     aria-labelledby="heading<?php echo $index; ?>">
                                    <div class="accordion-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover mb-0 logs-datatable" id="table-<?php echo $slug; ?>">
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
                                                                    $activityType = $log['Activity_Type'];
                                                                    $badgeClass = 'bg-primary';
                                                                    
                                                                    if (strpos($activityType, 'Delete') !== false) {
                                                                        $badgeClass = 'bg-danger';
                                                                    } elseif (strpos($activityType, 'Archive') !== false) {
                                                                        $badgeClass = 'bg-warning text-dark';
                                                                    } elseif (strpos($activityType, 'Recovery') !== false || strpos($activityType, 'Recover') !== false) {
                                                                        $badgeClass = 'bg-success';
                                                                    } elseif (strpos($activityType, 'Update') !== false || strpos($activityType, 'Edit') !== false) {
                                                                        $badgeClass = 'bg-info text-dark';
                                                                    } elseif (strpos($activityType, 'Create') !== false || strpos($activityType, 'Add') !== false) {
                                                                        $badgeClass = 'bg-success';
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $activityType; ?></span>
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

        <!-- Update Profile Modal -->
        <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="profileModalLabel">Update Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Add method and enctype for file uploads -->
                        <form id="updateProfileForm" method="POST" action="update_profile.php" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="profilePic" class="form-label">Profile Image</label>
                                <div class="text-center mb-3">
                                    <!-- Current profile image -->
                                    <img id="currentProfileImage" src="<?php echo !empty($superadminProfile) ? $superadminProfile : '../assets/images/profile.jpg'; ?>" 
                                        alt="Current Profile" class="rounded-circle" width="100" height="100">
                                        
                                    <!-- Preview container (initially hidden) -->
                                    <div id="imagePreviewContainer" style="display: none;" class="mt-3">
                                        <p id="previewText" class="text-muted mb-2"></p>
                                        <img id="imagePreview" src="#" alt="Image Preview" class="rounded-circle" width="100" height="100">
                                    </div>
                                </div>
                                <input type="file" class="form-control" id="profilePic" name="profilePic" 
                                    accept=".jpg,.jpeg,.png,.avif">
                                <small class="form-text text-muted">Accepted file types: JPG, PNG, AVIF. Max size: 5MB</small>
                            </div>
                            <div class="mb-3">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" 
                                    value="<?php echo isset($superadminData['First_Name']) ? $superadminData['First_Name'] : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" 
                                    value="<?php echo isset($superadminData['Last_Name']) ? $superadminData['Last_Name'] : ''; ?>">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
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
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/superadmin_dashboard.js"></script>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">

            <!-- Mirror sidebar links and order -->
            <a href="superadmin_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Leave Request</span>
            </a>
            <a href="recruitment.php" class="mobile-nav-item">
                <span class="material-icons">person_search</span>
                <span class="mobile-nav-text">Recruitment</span>
            </a>
            <a href="performance_evaluation.php" class="mobile-nav-item">
                <span class="material-icons">assessment</span>
                <span class="mobile-nav-text">Performance Evaluation</span>
            </a>
            <a href="users_list.php" class="mobile-nav-item">
                <span class="material-icons">people</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
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
            
            // Only open all accordion items if "All Activities" is EXPLICITLY selected
            const activityTypeSelect = document.getElementById('activity_type');
            const urlParams = new URLSearchParams(window.location.search);
            
            // Check if "All Activities" is explicitly selected (not just default empty value)
            if (urlParams.has('activity_type') && (activityTypeSelect.value === '' || activityTypeSelect.value === 'All Activities')) {
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
            }
            
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
            
            // Initialize DataTables for all log tables
            const logTables = document.querySelectorAll('.logs-datatable');
            logTables.forEach(table => {
                $(table).DataTable({
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                    order: [[0, 'desc']], // Sort by first column (timestamp) descending
                    language: {
                        search: "Search within category:",
                        lengthMenu: "Show _MENU_ entries per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    },
                    responsive: true,
                    dom: 'lfrtip' // Default layout
                });
            });
        });
    </script>
</body>
</html>