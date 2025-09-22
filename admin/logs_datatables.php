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

// Get all activity logs with user information for DataTables
$logsQuery = "
    SELECT al.*, u.First_Name, u.Last_Name, u.Role_ID, r.Role_Name 
    FROM activity_logs al
    LEFT JOIN users u ON al.User_ID = u.User_ID
    LEFT JOIN roles r ON u.Role_ID = r.Role_ID
    ORDER BY al.Timestamp DESC
";
$logsStmt = $conn->prepare($logsQuery);
$logsStmt->execute();
$allLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct activity types for filter dropdown
$activityTypesStmt = $conn->query("SELECT DISTINCT Activity_Type FROM activity_logs ORDER BY Activity_Type");
$activityTypes = $activityTypesStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/logs.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .logs-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        
        .activity-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .badge-delete { background-color: #dc3545; color: white; }
        .badge-archive { background-color: #ffc107; color: #000; }
        .badge-recovery { background-color: #28a745; color: white; }
        .badge-update { background-color: #17a2b8; color: white; }
        .badge-create { background-color: #28a745; color: white; }
        .badge-login { background-color: #007bff; color: white; }
        .badge-logout { background-color: #6c757d; color: white; }
        .badge-default { background-color: #007bff; color: white; }
        
        .dt-buttons {
            margin-bottom: 20px;
        }
        
        .dataTables_filter {
            margin-bottom: 20px;
        }
        
        .custom-filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div>Green Meadows</div>
                <div>Security Agency</div>
            </div>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php" data-bs-toggle="tooltip" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="user_management.php" data-bs-toggle="tooltip" title="User Management">
                    <span class="material-icons">people</span>
                    <span>User Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="logs_datatables.php" data-bs-toggle="tooltip" title="Activity Logs">
                    <span class="material-icons">assignment</span>
                    <span>Activity Logs</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php" data-bs-toggle="tooltip" title="Profile">
                    <span class="material-icons">person</span>
                    <span>Profile</span>
                </a>
            </li>
            <li class="nav-item mt-5">
                <a class="nav-link" href="../logout.php" data-bs-toggle="tooltip" title="Logout">
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
                <div id="current-date"></div>
                <div id="current-time"></div>
            </div>
            <div class="header-right">
                <div class="user-profile" id="userProfile">
                    <img src="<?php echo htmlspecialchars($superadminProfile); ?>" alt="Profile" class="profile-pic">
                    <span class="user-name d-none d-md-inline"><?php echo htmlspecialchars($superadminName); ?></span>
                </div>
            </div>
        </div>

        <div class="content-container">
            <div class="container-fluid px-4">
                <div class="row">
                    <div class="col-12">
                        <h2 class="mb-4">
                            <span class="material-icons me-2">assignment</span>
                            Activity Logs
                        </h2>

                        <!-- Custom Filters -->
                        <div class="custom-filters">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="activityTypeFilter" class="form-label">Activity Type:</label>
                                    <select class="form-select" id="activityTypeFilter">
                                        <option value="">All Activity Types</option>
                                        <?php foreach ($activityTypes as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>">
                                                <?php echo htmlspecialchars($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="dateFromFilter" class="form-label">Date From:</label>
                                    <input type="date" class="form-control" id="dateFromFilter">
                                </div>
                                <div class="col-md-3">
                                    <label for="dateToFilter" class="form-label">Date To:</label>
                                    <input type="date" class="form-control" id="dateToFilter">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-secondary w-100" id="clearFilters">
                                        <span class="material-icons">clear</span> Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Logs Table -->
                        <div class="logs-table-container">
                            <table id="logsTable" class="table table-striped table-hover w-100">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>User & Role</th>
                                        <th>Activity Type</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allLogs as $log): 
                                        $ts = date('M d, Y g:i A', strtotime($log['Timestamp']));
                                        $userRole = isset($log['Role_Name']) ? $log['Role_Name'] : 'Unknown';
                                        $userName = (isset($log['First_Name']) && isset($log['Last_Name'])) ? ($log['First_Name'].' '.$log['Last_Name']) : 'System';
                                        $activityType = $log['Activity_Type'];
                                        
                                        // Determine badge class
                                        $badgeClass = 'badge-default';
                                        if (stripos($activityType, 'Delete') !== false) $badgeClass = 'badge-delete';
                                        elseif (stripos($activityType, 'Archive') !== false) $badgeClass = 'badge-archive';
                                        elseif (stripos($activityType, 'Recovery') !== false || stripos($activityType, 'Recover') !== false) $badgeClass = 'badge-recovery';
                                        elseif (stripos($activityType, 'Update') !== false || stripos($activityType, 'Edit') !== false) $badgeClass = 'badge-update';
                                        elseif (stripos($activityType, 'Create') !== false || stripos($activityType, 'Add') !== false) $badgeClass = 'badge-create';
                                        elseif (stripos($activityType, 'Login') !== false) $badgeClass = 'badge-login';
                                        elseif (stripos($activityType, 'Logout') !== false) $badgeClass = 'badge-logout';
                                    ?>
                                    <tr>
                                        <td data-order="<?php echo strtotime($log['Timestamp']); ?>">
                                            <?php echo htmlspecialchars($ts); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($userName); ?> 
                                            <span class="text-muted">(<?php echo htmlspecialchars($userRole); ?>)</span>
                                        </td>
                                        <td>
                                            <span class="activity-badge <?php echo $badgeClass; ?>">
                                                <?php echo htmlspecialchars($activityType); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['Activity_Details']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="admin_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a href="user_management.php" class="mobile-nav-item">
                <span class="material-icons">people</span>
                <span>Users</span>
            </a>
            <a href="logs_datatables.php" class="mobile-nav-item active">
                <span class="material-icons">assignment</span>
                <span>Logs</span>
            </a>
            <a href="profile.php" class="mobile-nav-item">
                <span class="material-icons">person</span>
                <span>Profile</span>
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    
    <script src="js/superadmin_dashboard.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#logsTable').DataTable({
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[0, 'desc']], // Sort by date descending
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"B>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="material-icons">file_download</i> Excel',
                        className: 'btn btn-success btn-sm',
                        title: 'Activity_Logs_' + new Date().toISOString().slice(0,10)
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="material-icons">picture_as_pdf</i> PDF',
                        className: 'btn btn-danger btn-sm',
                        title: 'Activity Logs',
                        orientation: 'landscape',
                        pageSize: 'A4'
                    },
                    {
                        extend: 'print',
                        text: '<i class="material-icons">print</i> Print',
                        className: 'btn btn-info btn-sm',
                        title: 'Activity Logs'
                    }
                ],
                language: {
                    search: "Search logs:",
                    lengthMenu: "Show _MENU_ logs per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ logs",
                    infoEmpty: "No logs available",
                    infoFiltered: "(filtered from _MAX_ total logs)",
                    zeroRecords: "No matching logs found",
                    emptyTable: "No activity logs available"
                },
                columnDefs: [
                    { responsivePriority: 1, targets: 0 }, // Date & Time
                    { responsivePriority: 2, targets: 2 }, // Activity Type
                    { responsivePriority: 3, targets: 1 }, // User & Role
                    { responsivePriority: 4, targets: 3 }  // Details
                ]
            });

            // Custom filter functions
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    var activityTypeFilter = $('#activityTypeFilter').val();
                    var dateFromFilter = $('#dateFromFilter').val();
                    var dateToFilter = $('#dateToFilter').val();
                    
                    var activityType = data[2]; // Activity Type column (contains HTML)
                    var dateTime = data[0]; // Date & Time column
                    
                    // Extract activity type from badge HTML
                    var activityTypeText = $(activityType).text().trim();
                    
                    // Activity type filter
                    if (activityTypeFilter && activityTypeFilter !== '') {
                        if (activityTypeText !== activityTypeFilter) {
                            return false;
                        }
                    }
                    
                    // Date filters
                    if (dateFromFilter || dateToFilter) {
                        // Parse the date from the datetime string
                        var logDate = new Date(dateTime.replace(/(\w+) (\d+), (\d+) .+/, '$3-$1-$2'));
                        
                        if (dateFromFilter) {
                            var fromDate = new Date(dateFromFilter);
                            if (logDate < fromDate) {
                                return false;
                            }
                        }
                        
                        if (dateToFilter) {
                            var toDate = new Date(dateToFilter);
                            toDate.setHours(23, 59, 59, 999); // End of day
                            if (logDate > toDate) {
                                return false;
                            }
                        }
                    }
                    
                    return true;
                }
            );

            // Event listeners for custom filters
            $('#activityTypeFilter, #dateFromFilter, #dateToFilter').on('change', function() {
                table.draw();
            });

            // Clear filters
            $('#clearFilters').on('click', function() {
                $('#activityTypeFilter').val('');
                $('#dateFromFilter').val('');
                $('#dateToFilter').val('');
                table.draw();
            });

            // Add some custom styling to DataTables elements
            $('.dataTables_filter input').addClass('form-control');
            $('.dataTables_length select').addClass('form-select');
            
            // Style the buttons
            $('.dt-buttons .btn').addClass('me-2 mb-2');
        });
    </script>
</body>
</html>
