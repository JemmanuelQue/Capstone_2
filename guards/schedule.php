<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
if (!validateSession($conn, 5)) { exit; }

// Profile data (for header/avatar)
$userId = $_SESSION['user_id'];
$profileStmt = $conn->prepare("SELECT * FROM users WHERE User_ID = ?");
$profileStmt->execute([$userId]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
if (!$profileData || empty($profileData['Profile_Pic']) || !file_exists($profileData['Profile_Pic'])) {
    $profileData['Profile_Pic'] = '../images/default_profile.png';
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

// Date range filter for schedules
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';
$dateTo   = isset($_GET['to']) ? trim($_GET['to']) : '';
$validFrom = null; $validTo = null;
if ($dateFrom !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateFrom);
    if ($dt && $dt->format('Y-m-d') === $dateFrom) { $validFrom = $dt->format('Y-m-d'); }
}
if ($dateTo !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateTo);
    if ($dt && $dt->format('Y-m-d') === $dateTo) { $validTo = $dt->format('Y-m-d'); }
}

// Fetch schedules for this guard only
$schedules = [];
$sql = "SELECT schedule_date, shift_type, location_name, hours_scheduled, notes, created_at
        FROM guard_schedules
        WHERE user_id = ?";
$params = [$userId];
if ($validFrom && $validTo) {
    $sql .= " AND schedule_date BETWEEN ? AND ?";
    $params[] = $validFrom; $params[] = $validTo;
} elseif ($validFrom) {
    $sql .= " AND schedule_date >= ?";
    $params[] = $validFrom;
} elseif ($validTo) {
    $sql .= " AND schedule_date <= ?";
    $params[] = $validTo;
}
$sql .= " ORDER BY schedule_date DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Green Meadows Security Agency</title>
    
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
                <a href="schedule.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="My Schedule">
                    <span class="material-icons">event_note</span>
                    <span>My Schedule</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="leave_request.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Request Leave">
                    <span class="material-icons">event</span>
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

        <!-- My Schedule Section -->
        <div class="container-fluid mt-4">
            <div class="dashboard-card bg-white">
                <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <span class="material-icons me-2">event_note</span>
                        <span>My Schedule</span>
                    </div>
                    <div>
                        <form method="GET" class="d-flex align-items-end gap-2">
                            <div class="d-flex flex-column">
                                <label for="from" class="text-white mb-1">From</label>
                                <input type="date" id="from" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($validFrom ?? ''); ?>">
                            </div>
                            <div class="d-flex flex-column">
                                <label for="to" class="text-white mb-1">To</label>
                                <input type="date" id="to" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($validTo ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-light btn-sm">
                                <i class="material-icons align-middle" style="font-size: 16px;">filter_list</i>
                                Filter
                            </button>
                            <a href="schedule.php" class="btn btn-outline-light btn-sm">Clear</a>
                        </form>
                    </div>
                </div>
                <br>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Shift</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($schedules)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center py-4">
                                            <span class="material-icons text-muted mb-2" style="font-size: 48px;">event_busy</span>
                                            <p class="text-muted">No schedules found<?php echo ($validFrom || $validTo) ? ' for the selected period' : ''; ?>.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($schedules as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($row['schedule_date']))); ?></td>
                                            <td class="text-capitalize"><?php echo htmlspecialchars($row['shift_type'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
            <a href="schedule.php" class="mobile-nav-item active">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">My Schedule</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item">
                <span class="material-icons">event</span>
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
        // Basic enhancement: keep mobile nav active styling
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.mobile-nav-item').forEach(item => {
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>