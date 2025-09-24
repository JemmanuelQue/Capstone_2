<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn, 4);
require_once '../db_connection.php';

// Get current Accounting user's name
$accountingStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 4 AND status = 'Active' AND User_ID = ?");
$accountingStmt->execute([$_SESSION['user_id']]);
$accountingData = $accountingStmt->fetch(PDO::FETCH_ASSOC);
$accountingName = $accountingData ? $accountingData['First_Name'] . ' ' . $accountingData['Last_Name'] : "Accounting";

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
$profilePic = $profileData['Profile_Pic'] ? $profileData['Profile_Pic'] : '../images/default_profile.png';

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $recordsPerPage;

// Get filter parameters
$filterName = isset($_GET['name']) ? $_GET['name'] : '';
$filterLocation = isset($_GET['location']) ? $_GET['location'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Build the WHERE clause for filters
$whereClause = "1=1"; // Always true condition to start with
$params = [];

if (!empty($filterName)) {
    $whereClause .= " AND (a.first_name LIKE ? OR a.last_name LIKE ?)";
    $params[] = "%$filterName%";
    $params[] = "%$filterName%";
}

if (!empty($filterLocation)) {
    $whereClause .= " AND gl.location_name = ?";
    $params[] = $filterLocation;
}

if (!empty($filterDateFrom)) {
    $whereClause .= " AND DATE(a.time_in) >= ?";
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $whereClause .= " AND DATE(a.time_in) <= ?";
    $params[] = $filterDateTo;
}

if ($filterStatus === 'logged_out') {
    $whereClause .= " AND a.time_out IS NOT NULL";
} elseif ($filterStatus === 'not_logged_out') {
    $whereClause .= " AND a.time_out IS NULL";
}

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited (except profile)
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

// Get available locations for filter dropdown
$locationsQuery = "SELECT DISTINCT gl.location_name 
                   FROM guard_locations gl 
                   ORDER BY gl.location_name ASC";
$locationsStmt = $conn->prepare($locationsQuery);
$locationsStmt->execute();
$locations = $locationsStmt->fetchAll(PDO::FETCH_COLUMN);

// Count total records for pagination
$countQuery = "
    SELECT COUNT(*) 
    FROM archive_dtr_data a
    LEFT JOIN users u ON a.User_ID = u.User_ID
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
    WHERE $whereClause
";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get archived attendance records with pagination and filtering
$archivedAttendanceQuery = "
    SELECT 
        a.ID, 
        a.User_ID, 
        a.first_name, 
        a.last_name, 
        a.time_in, 
        a.time_out,
        gl.location_name,
        CASE 
            WHEN a.time_out IS NULL THEN 0
            ELSE 
                CASE 
                    WHEN UNIX_TIMESTAMP(a.time_out) < UNIX_TIMESTAMP(a.time_in) THEN
                        (UNIX_TIMESTAMP(a.time_out) + 86400 - UNIX_TIMESTAMP(a.time_in))/3600
                    ELSE
                        TIMESTAMPDIFF(HOUR, a.time_in, a.time_out)
                END
        END as hours_worked
    FROM archive_dtr_data a
    LEFT JOIN users u ON a.User_ID = u.User_ID
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
    WHERE $whereClause
    ORDER BY a.time_in DESC
    LIMIT $offset, $recordsPerPage
";

// Remove pagination parameters from the params array
// (they're now directly in the query)

$archivedAttendanceStmt = $conn->prepare($archivedAttendanceQuery);
$archivedAttendanceStmt->execute($params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archives | Green Meadows</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="css/archives.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .archived-header {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2a7d4f;
        }
        .tab-content {
            padding-top: 20px;
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
        }
        .action-btn-group {
            white-space: nowrap;
        }
        .filter-form {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .page-info {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div> SECURITY AGENCY</div>
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
                <a href="archives.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Archives">
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

        <div class="content-container">
            <div class="container-fluid px-4">
                <div class="archived-header">Archives</div>
                
                <!-- Nav tabs for different archive types -->
                <ul class="nav nav-tabs" id="archiveTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" 
                                type="button" role="tab" aria-controls="attendance" aria-selected="true">
                            <i class="material-icons align-middle me-1">schedule</i> Attendance Records
                        </button>
                    </li>
                </ul>
                
                <!-- Tab content -->
                <div class="tab-content">
                    <!-- Attendance Archives -->
                    <div class="tab-pane fade show active" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                        <!-- Filter form - updated structure with buttons in the same row as filters -->
                        <div class="filter-form">
                            <form method="GET" action="archives.php" class="row g-3">
                                <div class="col-md-2">
                                    <label for="name" class="form-label">Guard Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($filterName); ?>" placeholder="Search name...">
                                </div>
                                <div class="col-md-2">
                                    <label for="location" class="form-label">Location</label>
                                    <select class="form-select" id="location" name="location">
                                        <option value="">All Locations</option>
                                        <?php foreach ($locations as $location): ?>
                                            <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $filterLocation === $location ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($location); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="date_from" class="form-label">Date From</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="date_to" class="form-label">Date To</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All</option>
                                        <option value="logged_out" <?php echo $filterStatus === 'logged_out' ? 'selected' : ''; ?>>Logged Out</option>
                                        <option value="not_logged_out" <?php echo $filterStatus === 'not_logged_out' ? 'selected' : ''; ?>>Not Logged Out</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="d-flex">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="material-icons align-middle">search</i>
                                        </button>
                                        <a href="archives.php" class="btn btn-outline-secondary">
                                            <i class="material-icons align-middle">refresh</i> Reset
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mt-3">
                                    <label class="form-label me-2">Records per page</label>
                                    <div class="btn-group" role="group">
                                        <?php foreach ([10, 25, 50, 100] as $limit): ?>
                                            <input type="radio" class="btn-check" name="limit" id="limit_<?php echo $limit; ?>" value="<?php echo $limit; ?>" <?php echo $recordsPerPage == $limit ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-secondary" for="limit_<?php echo $limit; ?>"><?php echo $limit; ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Guard Name</th>
                                                <th>Location</th>
                                                <th>Date</th>
                                                <th>Time In</th>
                                                <th>Time Out</th>
                                                <th>Hours Worked</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($archivedAttendanceStmt->rowCount() > 0) {
                                                while ($record = $archivedAttendanceStmt->fetch(PDO::FETCH_ASSOC)) {
                                                    // Format date display for overnight shifts
                                                    $timeInObj = new DateTime($record['time_in']);
                                                    $dateDisplay = date('M j, Y', strtotime($record['time_in']));
                                                    
                                                    if ($record['time_out']) {
                                                        $timeOutObj = new DateTime($record['time_out']);
                                                        
                                                        // Check if overnight shift
                                                        if ($timeOutObj < $timeInObj) {
                                                            $nextDay = (clone $timeInObj)->modify('+1 day');
                                                            $dateDisplay = date('M j', strtotime($record['time_in'])) . ' - ' . 
                                                                          date('M j, Y', strtotime($nextDay->format('Y-m-d')));
                                                        }
                                                    }
                                                    
                                                    echo '<tr>';
                                                    echo '<td>' . htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($record['location_name'] ?? 'N/A') . '</td>';
                                                    echo '<td>' . $dateDisplay . '</td>';
                                                    echo '<td>' . date('h:i A', strtotime($record['time_in'])) . '</td>';
                                                    echo '<td>' . ($record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : 'Not logged out') . '</td>';
                                                    echo '<td>' . round($record['hours_worked'], 1) . ' hours</td>';
                                                    echo '<td class="action-btn-group">';
                                                    echo '<button class="btn btn-sm btn-success btn-icon me-1 restore-attendance" data-id="' . $record['ID'] . '" data-userid="' . $record['User_ID'] . '">';
                                                    echo '<i class="material-icons">restore</i></button>';
                                                    echo '<button class="btn btn-sm btn-danger btn-icon delete-attendance" data-id="' . $record['ID'] . '">';
                                                    echo '<i class="material-icons">delete_forever</i></button>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="7" class="text-center">';
                                                echo '<div class="alert alert-info mb-0"><i class="material-icons align-middle me-2">info</i> No archived attendance records found</div>';
                                                echo '</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination controls -->
                                <?php if ($totalRecords > 0): ?>
                                <div class="pagination-container">
                                    <div class="page-info">
                                        Showing <?php echo min(($page - 1) * $recordsPerPage + 1, $totalRecords); ?> to 
                                        <?php echo min($page * $recordsPerPage, $totalRecords); ?> of 
                                        <?php echo $totalRecords; ?> records
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination">
                                            <?php
                                            // Previous page link
                                            $prevDisabled = ($page <= 1) ? 'disabled' : '';
                                            $prevPage = $page - 1;
                                            
                                            // Build the query string for pagination links
                                            $queryParams = $_GET;
                                            
                                            echo '<li class="page-item ' . $prevDisabled . '">';
                                            if (!$prevDisabled) {
                                                $queryParams['page'] = $prevPage;
                                                $queryString = http_build_query($queryParams);
                                                echo '<a class="page-link" href="?'. $queryString .'" aria-label="Previous">';
                                            } else {
                                                echo '<span class="page-link" aria-label="Previous">';
                                            }
                                            echo '<span aria-hidden="true">&laquo;</span>';
                                            echo $prevDisabled ? '</span>' : '</a>';
                                            echo '</li>';
                                            
                                            // Page number links
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($startPage + 4, $totalPages);
                                            
                                            if ($startPage > 1) {
                                                $queryParams['page'] = 1;
                                                echo '<li class="page-item"><a class="page-link" href="?'. http_build_query($queryParams) .'">1</a></li>';
                                                if ($startPage > 2) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                            }
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++) {
                                                $active = ($i == $page) ? 'active' : '';
                                                $queryParams['page'] = $i;
                                                echo '<li class="page-item ' . $active . '">';
                                                if ($active) {
                                                    echo '<span class="page-link">' . $i . '</span>';
                                                } else {
                                                    echo '<a class="page-link" href="?'. http_build_query($queryParams) .'">' . $i . '</a>';
                                                }
                                                echo '</li>';
                                            }
                                            
                                            if ($endPage < $totalPages) {
                                                if ($endPage < $totalPages - 1) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                $queryParams['page'] = $totalPages;
                                                echo '<li class="page-item"><a class="page-link" href="?'. http_build_query($queryParams) .'">' . $totalPages . '</a></li>';
                                            }
                                            
                                            // Next page link
                                            $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
                                            $nextPage = $page + 1;
                                            
                                            echo '<li class="page-item ' . $nextDisabled . '">';
                                            if (!$nextDisabled) {
                                                $queryParams['page'] = $nextPage;
                                                $queryString = http_build_query($queryParams);
                                                echo '<a class="page-link" href="?'. $queryString .'" aria-label="Next">';
                                            } else {
                                                echo '<span class="page-link" aria-label="Next">';
                                            }
                                            echo '<span aria-hidden="true">&raquo;</span>';
                                            echo $nextDisabled ? '</span>' : '</a>';
                                            echo '</li>';
                                            ?>
                                        </ul>
                                    </nav>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Attendance Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="restoreModalLabel">Restore Attendance Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to restore this attendance record? It will be moved back to the active attendance records.</p>
                    <form id="restoreForm">
                        <input type="hidden" id="restoreAttendanceId" name="attendanceId">
                        <input type="hidden" id="restoreUserId" name="userId">
                        <div class="mb-3">
                            <label for="restoreReason" class="form-label">Reason for Restoring</label>
                            <textarea class="form-control" id="restoreReason" name="reason" rows="3" required 
                                placeholder="Please provide a reason for restoring this record"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-icon" id="confirmRestoreBtn">
                        <i class="material-icons">restore</i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Attendance Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Permanently Delete Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="material-icons me-2">warning</i>
                        <strong>Warning:</strong> This action cannot be undone. The record will be permanently deleted.
                    </div>
                    <form id="deleteForm">
                        <input type="hidden" id="deleteAttendanceId" name="attendanceId">
                        <div class="mb-3">
                            <label for="deleteReason" class="form-label">Reason for Deletion</label>
                            <textarea class="form-control" id="deleteReason" name="reason" rows="3" required 
                                placeholder="Please provide a reason for permanently deleting this record"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-icon" id="confirmDeleteBtn">
                        <i class="material-icons">delete_forever</i>
                    </button>
                </div>
            </div>
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
                    <form id="updateProfileForm" method="POST" action="update_profile.php" enctype="multipart/form-data">
                        <div class="mb-3 text-center">
                            <img src="<?php echo $profilePic; ?>" class="rounded-circle" width="100" height="100" id="profilePreview">
                            <div class="mt-2">
                                <label for="profilePic" class="btn btn-sm btn-outline-secondary">
                                    Change Profile Picture
                                </label>
                                <input type="file" class="d-none" id="profilePic" name="profilePic" accept="image/*">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" 
                                value="<?php echo $accountingData['First_Name']; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" 
                                value="<?php echo $accountingData['Last_Name']; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
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

        // Auto-submit form when records per page changes
        document.querySelectorAll('input[name="limit"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.querySelector('form').submit();
            });
        });

        // Restore attendance button click
        document.querySelectorAll('.restore-attendance').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const userId = this.getAttribute('data-userid');
                
                document.getElementById('restoreAttendanceId').value = id;
                document.getElementById('restoreUserId').value = userId;
                document.getElementById('restoreReason').value = '';
                
                new bootstrap.Modal(document.getElementById('restoreModal')).show();
            });
        });

        // Delete attendance button click
        document.querySelectorAll('.delete-attendance').forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                
                document.getElementById('deleteAttendanceId').value = id;
                document.getElementById('deleteReason').value = '';
                
                new bootstrap.Modal(document.getElementById('deleteModal')).show();
            });
        });

        // Confirm restore attendance
        document.getElementById('confirmRestoreBtn').addEventListener('click', function() {
            const form = document.getElementById('restoreForm');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const attendanceId = document.getElementById('restoreAttendanceId').value;
            const userId = document.getElementById('restoreUserId').value;
            const reason = document.getElementById('restoreReason').value;
            
            // Send AJAX request to restore
            fetch('restore_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `attendanceId=${attendanceId}&userId=${userId}&reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Attendance record restored successfully.',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to restore attendance record.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            });
        });

        // Confirm delete attendance
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            const form = document.getElementById('deleteForm');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const attendanceId = document.getElementById('deleteAttendanceId').value;
            const reason = document.getElementById('deleteReason').value;
            
            // Send AJAX request to delete
            fetch('delete_archived_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `attendanceId=${attendanceId}&reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Attendance record permanently deleted.',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to delete attendance record.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            });
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
                <span class="material-icons">list_alt</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
            <a href="archives.php" class="mobile-nav-item active">
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