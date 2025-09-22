<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
// Enforce HR role (3)
if (!validateSession($conn, 3)) { exit; }

// Get current hr user's name
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
                <a class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Leave Requests">
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
                        <input type="text" class="form-control" id="guard_name" name="guard_name" placeholder="Search by guard name"
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
                        <th class="fw-bold text-center"><i class="material-icons align-middle me-1" style="font-size: 18px;">settings</i>ACTION</th>
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
                            <td>
                                <?php if($request['Status'] === 'Pending'): ?>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-success btn-sm accept-btn" 
                                            data-request-id="<?php echo $request['ID']; ?>"
                                            data-guard-name="<?php echo htmlspecialchars($request['First_Name']) . ' ' . 
                                                htmlspecialchars($request['Last_Name']); ?>"
                                            data-email="<?php echo htmlspecialchars($request['Email']); ?>"
                                            data-leave-type="<?php echo ucfirst($request['Leave_Type']); ?>"
                                            data-period="<?php echo $startDateFormatted . ($startDateFormatted != $endDateFormatted ? " - " . $endDateFormatted : ""); ?>"
                                            data-location="<?php echo htmlspecialchars($request['location_name'] ?? 'N/A'); ?>">
                                            <i class="material-icons">check</i> 
                                        <button class="btn btn-danger btn-sm reject-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal"
                                            data-request-id="<?php echo $request['ID']; ?>"
                                            data-guard-name="<?php echo htmlspecialchars($request['First_Name']) . ' ' . 
                                                htmlspecialchars($request['Last_Name']); ?>"
                                            data-email="<?php echo htmlspecialchars($request['Email']); ?>"
                                            data-leave-type="<?php echo ucfirst($request['Leave_Type']); ?>"
                                            data-period="<?php echo $startDateFormatted . ($startDateFormatted != $endDateFormatted ? " - " . $endDateFormatted : ""); ?>"
                                            data-location="<?php echo htmlspecialchars($request['location_name'] ?? 'N/A'); ?>">
                                            <i class="material-icons">close</i> 
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php if($request['Status'] === 'Approved'): ?>
                                        <span class="badge bg-success bg-gradient">
                                            <i class="material-icons align-middle me-1" style="font-size: 14px;">check_circle</i>
                                            Approved
                                        </span>
                                    <?php elseif($request['Status'] === 'Rejected'): ?>
                                        <span class="badge bg-danger bg-gradient">
                                            <i class="material-icons align-middle me-1" style="font-size: 14px;">cancel</i>
                                            Rejected
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">No action needed</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php } ?>

    

    <!-- Reject Leave Request Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectModalLabel">Reject Leave Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rejectLeaveForm" method="post" action="process_leave_request.php">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" id="reject_request_id">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="guard_name" id="reject_guard_name">
                        <input type="hidden" name="guard_email" id="reject_guard_email">
                        <input type="hidden" name="leave_type" id="reject_leave_type">
                        <input type="hidden" name="leave_period" id="reject_leave_period">
                        <input type="hidden" name="location" id="reject_location">
                        
                        <p>You are about to reject the leave request for <strong id="guardNameText"></strong>.</p>
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required
                                placeholder="Please provide a detailed reason for rejecting this leave request..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Accept Leave Request Form (Hidden) -->
    <form id="acceptLeaveForm" method="post" action="process_leave_request.php" style="display: none;">
        <input type="hidden" name="request_id" id="accept_request_id">
        <input type="hidden" name="action" value="accept">
        <input type="hidden" name="guard_name" id="accept_guard_name">
        <input type="hidden" name="guard_email" id="accept_guard_email">
        <input type="hidden" name="leave_type" id="accept_leave_type">
        <input type="hidden" name="leave_period" id="accept_leave_period">
        <input type="hidden" name="location" id="accept_location">
    </form>

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
    <script src="js/hr_dashboard.js"></script>

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
            <a href="hr_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item active">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Leave Request</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
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
                { orderable: false, targets: [2, 3, 8] } // Type, Reason, Action
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

    // Handle Reject button click
    $('.reject-btn').click(function() {
        const requestId = $(this).data('request-id');
        const guardName = $(this).data('guard-name');
        const email = $(this).data('email');
        const leaveType = $(this).data('leave-type');
        const period = $(this).data('period');
        const location = $(this).data('location');
        
        $('#reject_request_id').val(requestId);
        $('#reject_guard_name').val(guardName);
        $('#reject_guard_email').val(email);
        $('#reject_leave_type').val(leaveType);
        $('#reject_leave_period').val(period);
        $('#reject_location').val(location);
        $('#guardNameText').text(guardName);
    });
    
    // Handle Accept button click
    $('.accept-btn').click(function() {
        const requestId = $(this).data('request-id');
        const guardName = $(this).data('guard-name');
        const email = $(this).data('email');
        const leaveType = $(this).data('leave-type');
        const period = $(this).data('period');
        const location = $(this).data('location');
        
        $('#accept_request_id').val(requestId);
        $('#accept_guard_name').val(guardName);
        $('#accept_guard_email').val(email);
        $('#accept_leave_type').val(leaveType);
        $('#accept_leave_period').val(period);
        $('#accept_location').val(location);
        
        // Confirm before accepting
        Swal.fire({
            title: 'Accept Leave Request',
            text: `Are you sure you want to accept ${guardName}'s leave request?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2a7d4f',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, accept it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#acceptLeaveForm').submit();
            }
        });
    });
});
</script>
</body>
</html>