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

// Handle profile picture with proper default fallback
if (empty($profileData['Profile_Pic']) || !file_exists($profileData['Profile_Pic'])) {
    $profileData['Profile_Pic'] = '../images/default_profile.png';
}

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited (except profile)
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

// Get current date info for filtering
$currentYear = date('Y');
$currentMonth = date('n'); // 1-12 numeric representation
$currentDay = date('j');

// Handle year filter
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;
$yearChanged = isset($_GET['year_changed']) && $_GET['year_changed'] == '1';

// Array of month names
$monthNames = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
];

// Generate available cutoff periods
$cutoffPeriods = [];

// Only show months up to current month for current year
$maxMonth = ($selectedYear == $currentYear) ? $currentMonth : 12;

// Generate periods in reverse order (most recent first)
for ($month = $maxMonth; $month >= 1; $month--) {
    // Second half of month (16-end) - add first for reverse chronological order
    // For current month/year, only show if we're past the 15th
    if ($selectedYear < $currentYear || $month < $currentMonth || 
        ($month == $currentMonth && $currentDay > 15)) {
        
        $lastDay = date('t', strtotime("$selectedYear-$month-01"));
        $cutoffPeriods[] = [
            'start' => sprintf('%d-%02d-16', $selectedYear, $month),
            'end' => sprintf('%d-%02d-%d', $selectedYear, $month, $lastDay),
            'label' => sprintf('%s 16-%d, %d', $monthNames[$month], $lastDay, $selectedYear)
        ];
    }
    
    // First half of month (1-15) - add second for reverse chronological order
    $cutoffPeriods[] = [
        'start' => sprintf('%d-%02d-01', $selectedYear, $month),
        'end' => sprintf('%d-%02d-15', $selectedYear, $month),
        'label' => sprintf('%s 1-15, %d', $monthNames[$month], $selectedYear)
    ];
}

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
    <title>Guard Dashboard - Green Meadows Security Agency</title>
    
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
                <div> SECURITY AGENCY</div>
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
                <a href="payslip.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Payslip">
                    <span class="material-icons">payments</span>
                    <span>Payslip</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="leave_request.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Request Leave">
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
                                 style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #28a745;"
                                 onerror="this.src='../images/default_profile.png'">
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

    <!-- Your Payslips Section -->
    <div class="container-fluid mt-4">
        <div class="dashboard-card bg-white">
            <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <span class="material-icons me-2">receipt</span>
                    <span>Your Payslips</span>
                </div>
                <div>
                    <form id="yearFilterForm" method="GET" class="d-flex align-items-center">
                        <label for="year" class="text-white me-2">Year:</label>
                        <select class="form-select form-select-sm" id="year" name="year" onchange="changeYear(this.value)" style="width: 100px;">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="year_changed" id="year_changed" value="0">
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60%">Cutoff Period</th>
                                <th style="width: 40%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php if (empty($cutoffPeriods)): ?>
        <tr>
            <td colspan="2" class="text-center py-4">
                <span class="material-icons text-muted mb-2" style="font-size: 48px;">receipt_long</span>
                <p class="text-muted">No payslip periods available for this year.</p>
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($cutoffPeriods as $period): ?>
            <tr>
                <td><?php echo $period['label']; ?></td>
                <td class="text-center">
                    <a href="generate_payslip.php?start=<?php echo $period['start']; ?>&end=<?php echo $period['end']; ?>" 
                       class="btn btn-success btn-sm" target="_blank">
                        <i class="material-icons align-middle" style="font-size: 16px;">download</i> 
                        Download
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/guards_dashboard.js"></script>
    <!-- Add this JavaScript for SweetAlert year filter confirmation -->
    <script>
        function changeYear(year) {
            // Set the year_changed flag to 1
            document.getElementById('year_changed').value = '1';
            // Submit the form
            document.getElementById('yearFilterForm').submit();
        }      

        // Show SweetAlert if year filter was just changed
        <?php if ($yearChanged): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Year Filter Applied',
                text: 'Now showing payslips for <?php echo $selectedYear; ?>',
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        });
        <?php endif; ?>
    </script>

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
        <a href="payslip.php" class="mobile-nav-item active">
            <span class="material-icons">payments</span>
            <span class="mobile-nav-text">Payslip</span>
        </a>
        <a href="leave_request.php" class="mobile-nav-item">
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

    <!-- Add to your existing JavaScript before the closing </script> tag -->
    <script>
        // Show active page in mobile nav
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.mobile-nav-item').forEach(item => {
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
            
            // Add this to properly handle phone number validation
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
        });
    </script>
</body>
</html>