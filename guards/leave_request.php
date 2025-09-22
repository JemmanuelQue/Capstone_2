<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
if (!validateSession($conn, 5)) { exit; }

// Add this section to fetch profile data
$userId = $_SESSION['user_id'];
$profileStmt = $conn->prepare("SELECT * FROM users WHERE User_ID = ?");
$profileStmt->execute([$userId]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

// Set default profile pic if none exists
if (!$profileData || empty($profileData['Profile_Pic']) || !file_exists($profileData['Profile_Pic'])) {
    $profileData['Profile_Pic'] = '../images/default_profile.png';
}
// Get current date info for filtering
$currentYear = date('Y');
$currentMonth = date('n'); // 1-12 numeric representation
$currentDay = date('j');

// Handle year and month filters
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : 0; // 0 means all months
$filterChanged = isset($_GET['filter_changed']) && $_GET['filter_changed'] == '1';

// Array of month names
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Query to fetch leave requests from the database
$query = "SELECT * FROM leave_requests WHERE User_ID = ?";
$params = [$userId];

// Add date filters if selected
if ($selectedMonth > 0) {
    $query .= " AND (MONTH(Start_Date) = ? OR MONTH(End_Date) = ?)";
    $params[] = $selectedMonth;
    $params[] = $selectedMonth;
}

if ($selectedYear > 0) {
    $query .= " AND (YEAR(Start_Date) = ? OR YEAR(End_Date) = ?)";
    $params[] = $selectedYear;
    $params[] = $selectedYear;
}

$query .= " ORDER BY Request_Date DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$leaveRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available years (current year and 3 years back)
$availableYears = [];
for ($year = $currentYear; $year >= $currentYear - 3; $year--) {
    $availableYears[] = $year;
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
    <link rel="stylesheet" href="css/guards_dashboard.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <a href="guards_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="register_face.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Register Face">
                    <span class="material-icons">face</span>
                    <span>Register Face</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Attendance">
                    <span class="material-icons">schedule</span>
                    <span>Attendance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payslip.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payslip">
                    <span class="material-icons">payments</span>
                    <span>Payslip</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="leave_request.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Request Leave">
                    <span class="material-icons">event_note</span>
                    <span>Request Leave</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="view_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Performance Evaluation">
                    <span class="material-icons">fact_check</span>
                    <span>Performance Evaluation</span>
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
                <span><?php echo $profileData['First_Name'] . ' ' . $profileData['Last_Name']; ?></span>
                <img src="<?php echo $profileData['Profile_Pic']; ?>" alt="User Profile">
            </a>
        </div>
        
        <!-- Profile Modal -->
        <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="profileModalLabel">Update Profile</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Simplified form with only phone number field -->
                        <form id="updateProfileForm" method="POST" action="update_profile.php">
                            <div class="text-center mb-4">
                                <img src="<?php echo $profileData['Profile_Pic']; ?>" 
                                     alt="Profile Picture" class="rounded-circle" 
                                     style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #28a745;">
                                <h4 class="mt-2"><?php echo $profileData['First_Name'] . ' ' . $profileData['Last_Name']; ?></h4>
                                <p class="text-muted"><?php echo $profileData['Email']; ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phoneNumber" class="form-label fw-bold">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="material-icons">phone</i></span>
                                    <input type="text" class="form-control" id="phoneNumber" name="phoneNumber" 
                                          value="<?php echo isset($profileData['phone_number']) ? $profileData['phone_number'] : ''; ?>" 
                                          pattern="09[0-9]{9}" title="Phone number must start with 09 followed by 9 digits" required>
                                </div>
                                <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="material-icons align-middle me-1" style="font-size: 16px;">save</i> 
                                    Save Changes
                                </button>
                                <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">
                                    <i class="material-icons align-middle me-1" style="font-size: 16px;">cancel</i>
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave Requests Section -->
        <div class="container-fluid mt-4">
            <div class="dashboard-card bg-white">
                <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <span class="material-icons me-2">event_note</span>
                        <span>Leave Requests</span>
                    </div>
                    <div>
                        <form id="filterForm" method="GET" class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center">
                                <label for="month" class="text-white me-2">Month:</label>
                                <select class="form-select form-select-sm" id="month" name="month" style="width: 130px;">
                                    <option value="0" <?php echo ($selectedMonth == 0) ? 'selected' : ''; ?>>All Months</option>
                                    <?php foreach ($monthNames as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo ($selectedMonth == $num) ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="d-flex align-items-center">
                                <label for="year" class="text-white me-2">Year:</label>
                                <select class="form-select form-select-sm" id="year" name="year" style="width: 100px;">
                                    <?php foreach ($availableYears as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="filter_changed" id="filter_changed" value="0">
                            <button type="submit" class="btn btn-light btn-sm">
                                <i class="material-icons align-middle" style="font-size: 16px;">filter_list</i>
                                Filter
                            </button>
                        </form>
                    </div>
                </div> <br>
                <div class="card-body">
                    <div class="mb-3 d-flex justify-content-end">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newLeaveRequestModal">
                            <i class="material-icons align-middle me-1" style="font-size: 16px;">add</i>
                            New Leave Request
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Period</th>
                                    <th>Reason</th>
                                    <th>Requested On</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leaveRequests)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <span class="material-icons text-muted mb-2" style="font-size: 48px;">event_busy</span>
                                            <p class="text-muted">No leave requests found for the selected period.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($leaveRequests as $request): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-medium text-capitalize">
                                                    <?php
                                                    $icon = '';
                                                    switch(strtolower($request['Leave_Type'])) {
                                                        case 'vacation':
                                                            $icon = 'beach_access';
                                                            break;
                                                        case 'sick':
                                                            $icon = 'healing';
                                                            break;
                                                        case 'emergency':
                                                            $icon = 'warning';
                                                            break;
                                                        default:
                                                            $icon = 'event_note';
                                                    }
                                                    ?>
                                                    <i class="material-icons align-middle me-1" style="font-size: 16px;"><?php echo $icon; ?></i>
                                                    <?php echo ucfirst($request['Leave_Type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $startDate = date('M j, Y', strtotime($request['Start_Date']));
                                                $endDate = date('M j, Y', strtotime($request['End_Date']));
                                                $days = (strtotime($request['End_Date']) - strtotime($request['Start_Date'])) / (60 * 60 * 24) + 1;
                                                echo "$startDate - $endDate <span class='badge bg-secondary ms-1'>$days day" . ($days > 1 ? "s" : "") . "</span>";
                                                ?>
                                            </td>
                                            <td><?php echo $request['Leave_Reason']; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($request['Request_Date'])); ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = '';
                                                $statusIcon = '';
                                                
                                                switch($request['Status']) {
                                                    case 'Approved':
                                                        $statusClass = 'bg-success';
                                                        $statusIcon = 'check_circle';
                                                        break;
                                                    case 'Rejected':
                                                        $statusClass = 'bg-danger';
                                                        $statusIcon = 'cancel';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-warning';
                                                        $statusIcon = 'pending';
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <i class="material-icons align-middle" style="font-size: 12px;"><?php echo $statusIcon; ?></i>
                                                    <?php echo $request['Status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Leave Request Modal -->
        <div class="modal fade" id="newLeaveRequestModal" tabindex="-1" aria-labelledby="newLeaveRequestModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="newLeaveRequestModalLabel">New Leave Request</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="leaveRequestForm" action="submit_leave_request.php" method="POST">
                            <div class="mb-3">
                                <label for="leaveType" class="form-label">Leave Type</label>
                                <select class="form-select" id="leaveType" name="leaveType" required>
                                    <option value="" selected disabled>Select leave type</option>
                                    <option value="vacation">Vacation Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="emergency">Emergency Leave</option>
                                </select>
                            </div>
                            <div class="row mb-3">
                                <div class="col">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="leaveReason" class="form-label">Reason for Leave</label>
                                <textarea class="form-control" id="leaveReason" name="leaveReason" rows="3" required placeholder="Please provide details about your leave request"></textarea>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success ms-2">Submit Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="guards_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="register_face.php" class="mobile-nav-item">
                <span class="material-icons">face</span>
                <span class="mobile-nav-text">Register Face</span>
            </a>
            <a href="attendance.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Attendance</span>
            </a>
            <a href="payslip.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payslip</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item active">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Request Leave</span>
            </a>
            <a href="view_evaluation.php" class="mobile-nav-item">
                <span class="material-icons">fact_check</span>
                <span class="mobile-nav-text">Performance</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/guards_dashboard.js"></script>

    <script>
        // Form validation and date range enforcement
        document.addEventListener('DOMContentLoaded', function() {
            // Date range validation
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');

            startDateInput.addEventListener('change', function() {
                endDateInput.min = this.value;
                if (endDateInput.value && endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                }
            });

            // Filter change handling
            document.querySelectorAll('#month, #year').forEach(element => {
                element.addEventListener('change', function() {
                    document.getElementById('filter_changed').value = '1';
                    document.getElementById('filterForm').submit();
                });
            });

            // Show active page in mobile nav
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.mobile-nav-item').forEach(item => {
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
            
            // Handle phone number validation
            const phoneInput = document.getElementById('phoneNumber');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    // Remove any non-numeric characters
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Enforce 11 digit limit with 09 prefix
                    if (this.value.length > 11) {
                        this.value = this.value.slice(0, 11);
                    }
                    
                    // Ensure it starts with 09
                    if (this.value.length >= 2 && this.value.substring(0, 2) !== '09') {
                        this.value = '09' + this.value.slice(2);
                    }
                });
            }

            // Show SweetAlert if filter was just changed
            <?php if ($filterChanged): ?>
                Swal.fire({
                    title: 'Filter Applied',
                    text: 'Now showing leave requests for <?php 
                        echo ($selectedMonth > 0 ? $monthNames[$selectedMonth] . ' ' : 'all months in ') . $selectedYear; 
                    ?>',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            <?php endif; ?>

            // Check for success/error messages in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success') && urlParams.get('success') === '1') {
                Swal.fire({
                    title: 'Success!',
                    text: 'Your leave request has been submitted successfully.',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
                
                // Remove the parameter from URL without refreshing the page
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.pushState({path: newUrl}, '', newUrl);
            } else if (urlParams.has('error')) {
                const errorCode = urlParams.get('error');
                const errorMessage = urlParams.get('message') || 'An error occurred with your leave request.';
                
                let title = 'Error!';
                switch(errorCode) {
                    case '2':
                        title = 'Missing Information';
                        break;
                    case '3':
                        title = 'Invalid Dates';
                        break;
                    case '4':
                        title = 'Duplicate Request';
                        break;
                    case '5':
                        title = 'Date Conflict';
                        break;
                }
                
                Swal.fire({
                    title: title,
                    text: errorMessage,
                    icon: 'error',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000
                });
                
                // Remove parameters from URL
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.pushState({path: newUrl}, '', newUrl);
            }
        });
    </script>
</body>
</html>