<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn);

// Get current superadmin user's name
$hrStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 1 AND status = 'Active' AND User_ID = ?");
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
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/leave_request.css">

    <!-- DataTables CSS for pagination/search -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom styles for horizontal scrolling -->
    <style>
        /* Force horizontal scrolling on all screen sizes */
        .table-responsive {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }
        
        /* Ensure table maintains minimum width for proper column spacing */
        #leaveRequestsTable {
            min-width: 1400px;
            width: 100%;
            table-layout: auto;
        }
        
        /* Set specific column widths for better layout */
        #leaveRequestsTable th:nth-child(1),
        #leaveRequestsTable td:nth-child(1) {
            min-width: 180px; /* Guard Name */
            white-space: nowrap;
        }
        
        #leaveRequestsTable th:nth-child(2),
        #leaveRequestsTable td:nth-child(2) {
            min-width: 120px; /* Location */
            white-space: nowrap;
        }
        
        #leaveRequestsTable th:nth-child(3),
        #leaveRequestsTable td:nth-child(3) {
            min-width: 100px; /* Type */
            white-space: nowrap;
        }
        
        #leaveRequestsTable th:nth-child(4),
        #leaveRequestsTable td:nth-child(4) {
            min-width: 250px; /* Reason */
            max-width: 350px;
            white-space: normal;
            word-wrap: break-word;
        }
        
        #leaveRequestsTable th:nth-child(5),
        #leaveRequestsTable td:nth-child(5) {
            min-width: 200px; /* Period */
            white-space: nowrap;
        }
        
        #leaveRequestsTable th:nth-child(6),
        #leaveRequestsTable td:nth-child(6) {
            min-width: 120px; /* Request Date */
            white-space: nowrap;
        }
        
        #leaveRequestsTable th:nth-child(7),
        #leaveRequestsTable td:nth-child(7) {
            min-width: 100px; /* Status */
            white-space: nowrap;
        }
        
        #leaveRequestsTable th:nth-child(8),
        #leaveRequestsTable td:nth-child(8) {
            min-width: 250px; /* Rejection Reason */
            max-width: 350px;
            white-space: normal;
            word-wrap: break-word;
        }
        
        /* Custom scrollbar for better UX */
        .table-responsive::-webkit-scrollbar {
            height: 12px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f8f9fa;
            border-radius: 6px;
            margin: 0 10px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: #6c757d;
            border-radius: 6px;
            border: 2px solid #f8f9fa;
        }
        
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #495057;
        }
        
        /* Add subtle shadow to indicate scrollable content */
        .table-responsive {
            box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);
            border-radius: 0.375rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            #leaveRequestsTable {
                min-width: 1200px;
            }
        }
        
        @media (max-width: 576px) {
            #leaveRequestsTable {
                min-width: 1000px;
            }
            
            .table-responsive::-webkit-scrollbar {
                height: 8px;
            }
        }
    </style>
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
                <a class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Leave Request">
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
        </div><br>
    
    
    <!-- Filter Section -->
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <form id="filterForm" method="GET">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : date('Y-m-t'); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="location" class="form-label">Location</label>
                        <select class="form-select" id="location" name="location">
                            <option value="">All Locations</option>
                            <?php
                            // Get all unique locations from guard_locations table
                            $locationQuery = "SELECT DISTINCT location_name FROM guard_locations WHERE is_active = 1 ORDER BY location_name";
                            $locationStmt = $conn->prepare($locationQuery);
                            $locationStmt->execute();
                            $locations = $locationStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach($locations as $loc) {
                                $selected = (isset($_GET['location']) && $_GET['location'] == $loc['location_name']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($loc['location_name']) . "' $selected>" . 
                                     htmlspecialchars($loc['location_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label for="guard_name" class="form-label">Guard Name</label>
                        <input type="text" class="form-control" id="guard_name" name="guard_name" placeholder="Search guard name..."
                               value="<?php echo isset($_GET['guard_name']) ? htmlspecialchars($_GET['guard_name']) : ''; ?>">
                    </div>        
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2 d-flex align-items-center">
                            <i class="material-icons me-1">search</i> Filter
                        </button>
                        <a href="leave_request.php" class="btn btn-outline-secondary d-flex align-items-center">
                            <i class="material-icons me-1">clear</i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <div class="d-flex align-items-center">
                <i class="material-icons me-2">event_note</i>
                <h5 class="mb-0">Leave Requests Management</h5>
            </div>
        </div>
        <div class="card-body p-0">
            <?php
            // Set default date range to current month if no filter applied
            $currentMonth = date('Y-m');
            $startDate = $currentMonth . '-01';
            $endDate = date('Y-m-t', strtotime($startDate)); // Last day of current month
            
            // Build the WHERE clause based on filters
            $whereConditions = [];
            $queryParams = [];
            
            // Date range filter
            if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
                $startDate = $_GET['start_date'];
            }
            if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
                $endDate = $_GET['end_date'];
            }
            
            $whereConditions[] = "(lr.Start_Date BETWEEN ? AND ? OR lr.End_Date BETWEEN ? AND ?)";
            $queryParams[] = $startDate;
            $queryParams[] = $endDate;
            $queryParams[] = $startDate;
            $queryParams[] = $endDate;
            
            // Location filter
            if (isset($_GET['location']) && !empty($_GET['location'])) {
                $whereConditions[] = "gl.location_name = ?";
                $queryParams[] = $_GET['location'];
            }

            // Guard name search filter
            if (isset($_GET['guard_name']) && trim($_GET['guard_name']) !== '') {
                $whereConditions[] = "(u.First_Name LIKE ? OR u.Last_Name LIKE ? OR CONCAT(u.First_Name, ' ', u.Last_Name) LIKE ?)";
                $name = '%' . trim($_GET['guard_name']) . '%';
                $queryParams[] = $name;
                $queryParams[] = $name;
                $queryParams[] = $name;
            }
            
            // Fetch leave requests with filters
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $leaveRequestsQuery = "
                SELECT lr.*, u.First_Name, u.Last_Name, u.middle_name, u.Email, u.Profile_Pic, gl.location_name
                FROM leave_requests lr
                JOIN users u ON lr.User_ID = u.User_ID
                LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
                $whereClause
                ORDER BY lr.Request_Date DESC
            ";
            
            $leaveRequestsStmt = $conn->prepare($leaveRequestsQuery);
            $leaveRequestsStmt->execute($queryParams);
            $leaveRequests = $leaveRequestsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count pending leave requests for current month
            $currentMonthStart = date('Y-m-01');
            $currentMonthEnd = date('Y-m-t');
            $pendingCountQuery = "
                SELECT COUNT(*) as pending_count
                FROM leave_requests lr
                JOIN users u ON lr.User_ID = u.User_ID
                LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
                WHERE lr.Status = 'Pending' 
                AND (lr.Start_Date BETWEEN ? AND ? OR lr.End_Date BETWEEN ? AND ?)
            ";
            $pendingStmt = $conn->prepare($pendingCountQuery);
            $pendingStmt->execute([$currentMonthStart, $currentMonthEnd, $currentMonthStart, $currentMonthEnd]);
            $pendingCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
            
            if (count($leaveRequests) == 0) {
                echo '<div class="alert alert-info">
                    <i class="material-icons align-middle me-2">info</i>
                    No leave requests found.
                </div>';
            } else {
            ?>
                <div class="table-responsive">
            <table id="leaveRequestsTable" class="table table-hover table-striped mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="fw-bold"><i class="material-icons align-middle me-1" style="font-size: 18px;">person</i>GUARD NAME</th>
                        <th class="fw-bold"><i class="material-icons align-middle me-1" style="font-size: 18px;">location_on</i>LOCATION</th>
                        <th class="fw-bold"><i class="material-icons align-middle me-1" style="font-size: 18px;">category</i>TYPE</th>
                        <th class="fw-bold"><i class="material-icons align-middle me-1" style="font-size: 18px;">description</i>REASON</th>
                        <th class="fw-bold"><i class="material-icons align-middle me-1" style="font-size: 18px;">date_range</i>PERIOD</th>
                        <th class="fw-bold"><i class="material-icons align-middle me-1" style="font-size: 18px;">schedule</i>REQUEST DATE</th>
                        <th class="fw-bold"><i class="material-icons align-middle me-1" style="font-size: 18px;">info</i>STATUS</th>
                        <th class="fw-bold"><i class="material-icons align-middle me-1" style="font-size: 18px;">comment</i>REJECTION REASON</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($leaveRequests as $request): ?>
                        <tr class="align-middle">
                            <td class="fw-semibold">
                                <div class="d-flex align-items-center">
                                    <?php
                                    // Set profile picture path
                                    $profilePic = '../images/default_profile.png';
                                    if (!empty($request['Profile_Pic']) && file_exists($request['Profile_Pic'])) {
                                        $profilePic = $request['Profile_Pic'];
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($profilePic); ?>" 
                                         alt="Profile" 
                                         class="rounded-circle me-2" 
                                         style="width: 32px; height: 32px; object-fit: cover;">
                                    <div>
                                        <?php 
                                        echo htmlspecialchars($request['First_Name']) . ' ' . 
                                            (!empty($request['middle_name']) ? strtoupper($request['middle_name'][0]) . '. ' : '') . 
                                            htmlspecialchars($request['Last_Name']); 
                                        ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info bg-gradient text-dark fw-semibold px-3 py-2">
                                    <i class="material-icons align-middle me-1" style="font-size: 16px;">location_on</i>
                                    <?php echo htmlspecialchars($request['location_name'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $leaveTypeClass = '';
                                $leaveTypeIcon = '';
                                switch(strtolower($request['Leave_Type'])) {
                                    case 'sick':
                                        $leaveTypeClass = 'bg-warning text-dark';
                                        $leaveTypeIcon = 'local_hospital';
                                        break;
                                    case 'emergency':
                                        $leaveTypeClass = 'bg-danger';
                                        $leaveTypeIcon = 'emergency';
                                        break;
                                    case 'vacation':
                                        $leaveTypeClass = 'bg-success';
                                        $leaveTypeIcon = 'beach_access';
                                        break;
                                    default:
                                        $leaveTypeClass = 'bg-secondary';
                                        $leaveTypeIcon = 'event';
                                }
                                ?>
                                <span class="badge <?php echo $leaveTypeClass; ?> bg-gradient fw-semibold px-3 py-2">
                                    <i class="material-icons align-middle me-1" style="font-size: 16px;"><?php echo $leaveTypeIcon; ?></i>
                                    <?php echo ucfirst($request['Leave_Type']); ?>
                                </span>
                            </td>
                            <td style="min-width: 250px; max-width: 350px;">
                                <div class="text-wrap" style="word-break: break-word; line-height: 1.4;">
                                    <?php echo htmlspecialchars($request['Leave_Reason']); ?>
                                </div>
                            </td>
                            <td class="period-cell" style="min-width: 200px;">
                                <div>
                                    <div class="fw-semibold text-primary mb-1">
                                        <?php 
                                        $startDateFormatted = date('M d, Y', strtotime($request['Start_Date']));
                                        $endDateFormatted = date('M d, Y', strtotime($request['End_Date']));
                                        echo $startDateFormatted . ($startDateFormatted != $endDateFormatted ? " - " . $endDateFormatted : "");
                                        ?>
                                    </div>
                                    <?php
                                    // Calculate number of days
                                    $start = new DateTime($request['Start_Date']);
                                    $end = new DateTime($request['End_Date']);
                                    $end->modify('+1 day');
                                    $days = $start->diff($end)->days;
                                    ?>
                                    <small class="badge bg-light text-dark">
                                        <?php echo $days . " " . ($days > 1 ? "days" : "day"); ?>
                                    </small>
                                </div>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($request['Request_Date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($request['Status']); ?>">
                                    <?php echo $request['Status']; ?>
                                </span>
                            </td>
                            <td style="min-width: 250px; max-width: 350px;">
                                <?php if($request['Status'] === 'Rejected' && !empty($request['rejection_reason'])): ?>
                                    <div class="text-wrap" style="word-break: break-word; line-height: 1.4;">
                                        <i class="material-icons align-middle me-1 text-danger" style="font-size: 16px;">cancel</i>
                                        <span class="text-danger fw-semibold"><?php echo htmlspecialchars($request['rejection_reason']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php } ?>

    

    

    <!-- SWAL Alerts for leave requests only (profile picture alerts removed) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Leave request success/error messages
            <?php if(isset($_SESSION['leave_success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo $_SESSION['leave_success']; ?>',
                    confirmButtonColor: '#2a7d4f'
                });
                <?php unset($_SESSION['leave_success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['leave_error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo $_SESSION['leave_error']; ?>',
                    confirmButtonColor: '#dc3545'
                });
                <?php unset($_SESSION['leave_error']); ?>
            <?php endif; ?>
        });
    </script>

    

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/superadmin_dashboard.js"></script>

    <!-- DataTables JS for pagination/search -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <!-- Date Range Picker Initialization -->
    <script>
        $(document).ready(function() {
            // Show pending leave requests toast notification
            <?php if ($pendingCount > 0): ?>
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: 'info',
                title: 'Pending Leave Requests',
                html: `<strong><?php echo $pendingCount; ?></strong> pending leave request<?php echo $pendingCount > 1 ? 's' : ''; ?> for <?php echo date('F Y'); ?>`,
                background: '#fff3cd',
                color: '#856404',
                iconColor: '#ffc107'
            });
            <?php endif; ?>
            
            // Handle filter form submission with toast notification
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate date range
                var startDate = $('#start_date').val();
                var endDate = $('#end_date').val();
                
                if (startDate && endDate && startDate > endDate) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Date Range',
                        text: 'Start date cannot be later than end date.',
                        confirmButtonColor: '#dc3545'
                    });
                    return;
                }
                
                // Show loading toast
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });

                Toast.fire({
                    icon: 'info',
                    title: 'Applying filters...'
                });

                // Submit form after short delay
                setTimeout(() => {
                    this.submit();
                }, 500);
            });

            // Show success toast if filters were applied
            <?php if (isset($_GET['start_date']) || isset($_GET['end_date']) || isset($_GET['location']) || isset($_GET['guard_name'])): ?>
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });

            Toast.fire({
                icon: 'success',
                title: 'Filters applied successfully!'
            });
            <?php endif; ?>
        });
    </script>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="superadmin_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item active">
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

    <script>
        $(document).ready(function() {
            // Make table rows clickable to open view modal
            $('#leaveRequestsTable tbody tr').css('cursor', 'pointer').click(function() {
                // Get the view button in this row and click it
                $(this).find('.view-request').trigger('click');
            });
        });
    </script>

    <script>
$(document).ready(function() {
    // Initialize DataTable with pagination and disable default search box (we use Guard Name field)
    if ($('#leaveRequestsTable').length) {
        var table = $('#leaveRequestsTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[5, 'desc']], // Request Date column
            columnDefs: [
                { orderable: false, targets: [2, 3] } // Type, Reason
            ],
            dom: 'lrtip' // hide built-in search box
        });

        // Bind Guard Name input to column 0 search
        $('#guard_name').on('input', function() {
            table.column(0).search(this.value).draw();
        });

        // Apply initial search if value present from GET
        if ($('#guard_name').val()) {
            table.column(0).search($('#guard_name').val()).draw();
        }
    }

    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Actions removed (accept/reject)
});
</script>
</body>
</html>