<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/session_check.php';

// Validate session and require specific role
if (!validateSession($conn, 3)) { // 3 = HR role
    exit(); // validateSession handles the redirect
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Unauthorized access. Please log in first.</div>';
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited (except profile)
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

// Date helpers with optional month filter (?month=YYYY-MM)
$selectedYm = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$monthStart = date('Y-m-01', strtotime($selectedYm . '-01'));
$monthEnd   = date('Y-m-t', strtotime($monthStart));
$currentMonthLabel = date('M Y', strtotime($monthStart));

// Get total guards
$guardsQuery = $conn->query("SELECT COUNT(*) AS total_guards FROM users WHERE Role_ID = 5 AND status = 'Active'");
$guardData = $guardsQuery->fetch(PDO::FETCH_ASSOC);
$totalGuards = $guardData['total_guards'];

// Get pending leave requests for current month only
$leaveStmt = $conn->prepare("SELECT COUNT(*) AS total_pending_requests
                                                         FROM leave_requests
                                                         WHERE Status = 'Pending'
                                                             AND (Start_Date BETWEEN ? AND ? OR End_Date BETWEEN ? AND ?)");
$leaveStmt->execute([$monthStart, $monthEnd, $monthStart, $monthEnd]);
$leaveData = $leaveStmt->fetch(PDO::FETCH_ASSOC);
$pendingRequests = $leaveData ? (int)$leaveData['total_pending_requests'] : 0;

// Get guards currently clocked in
$attendanceQuery = $conn->query("SELECT COUNT(*) AS guards_clocked_in FROM attendance WHERE Time_Out IS NULL");
$attendanceData = $attendanceQuery->fetch(PDO::FETCH_ASSOC);
$guardsClocked = $attendanceData['guards_clocked_in'];

// New hires this month (fallback to Created_At when hired_date is null)
$newHiresStmt = $conn->prepare("SELECT COUNT(*) AS new_hires
                                                                FROM users
                                                                WHERE Role_ID = 5 AND status = 'Active'
                                                                    AND ( (hired_date IS NOT NULL AND hired_date BETWEEN ? AND ?)
                                                                         OR (hired_date IS NULL AND Created_At BETWEEN ? AND ?) )");
$newHiresStmt->execute([$monthStart, $monthEnd, $monthStart, $monthEnd]);
$newHires = (int)($newHiresStmt->fetch(PDO::FETCH_ASSOC)['new_hires'] ?? 0);

// Performance analytics (this month)
$evalMonthStmt = $conn->prepare("SELECT 
                COUNT(*) AS eval_count,
                COALESCE(AVG(overall_rating),0) AS avg_rating,
                SUM(CASE WHEN recommendation = 'renewal' THEN 1 ELSE 0 END) AS renewals,
                SUM(CASE WHEN recommendation = 'termination' THEN 1 ELSE 0 END) AS terminations
        FROM performance_evaluations
        WHERE status = 'Completed' AND evaluation_date BETWEEN ? AND ?");
$evalMonthStmt->execute([$monthStart, $monthEnd]);
$evalMonth = $evalMonthStmt->fetch(PDO::FETCH_ASSOC) ?: ['eval_count'=>0,'avg_rating'=>0,'renewals'=>0,'terminations'=>0];

// Underperformers (lowest ratings this month)
$underStmt = $conn->prepare("SELECT user_id, employee_name, overall_rating, evaluation_date
                                                         FROM performance_evaluations
                                                         WHERE status = 'Completed'
                                                             AND evaluation_date BETWEEN ? AND ?
                                                             AND overall_rating >= 70 AND overall_rating < 80
                                                         ORDER BY overall_rating ASC, evaluation_date DESC
                                                         LIMIT 5");
$underStmt->execute([$monthStart, $monthEnd]);
$underperformers = $underStmt->fetchAll(PDO::FETCH_ASSOC);

// Trend: avg rating by month for last 6 months (including current)
$trendStart = date('Y-m-01', strtotime('-5 months', strtotime($monthStart)));
$trendStmt = $conn->prepare("SELECT DATE_FORMAT(evaluation_date, '%Y-%m') ym,
                                                                        ROUND(AVG(overall_rating),2) avg_rating,
                                                                        COUNT(*) cnt
                                                         FROM performance_evaluations
                                                         WHERE status='Completed' AND evaluation_date >= ?
                                                         GROUP BY ym
                                                         ORDER BY ym ASC");
$trendStmt->execute([$trendStart]);
$trendRows = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize trend data to ensure all 6 months are present
$trendMap = [];
foreach ($trendRows as $r) { $trendMap[$r['ym']] = (float)$r['avg_rating']; }
$trendLabels = [];
$trendValues = [];
for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-$i months", strtotime($monthStart)));
        $trendLabels[] = date('M Y', strtotime($ym . '-01'));
        $trendValues[] = isset($trendMap[$ym]) ? $trendMap[$ym] : 0;
}

// Rating label helper
$avgRating = (float)($evalMonth['avg_rating'] ?? 0);
function ratingBandLabel($v){
    if ($v >= 90) return 'Outstanding';
    if ($v >= 85) return 'Good';
    if ($v >= 80) return 'Fair';
    if ($v >= 75) return 'Needs Improvement';
    if ($v >= 70) return 'Poor';
    return 'N/A';
}
$avgRatingLabel = ratingBandLabel($avgRating);

// Avg Rating color mapping by band
$avgRatingColor = '#6c757d'; // default secondary
$avgRatingTextColor = '#fff';
if ($avgRating >= 90) { // Outstanding
    $avgRatingColor = '#198754'; // success green
    $avgRatingTextColor = '#fff';
} elseif ($avgRating >= 85) { // Good
    $avgRatingColor = '#0d6efd'; // primary blue
    $avgRatingTextColor = '#fff';
} elseif ($avgRating >= 80) { // Fair
    $avgRatingColor = '#fd7e14'; // orange
    $avgRatingTextColor = '#fff';
} elseif ($avgRating >= 75) { // Needs Improvement
    $avgRatingColor = '#ffc107'; // warning yellow
    $avgRatingTextColor = '#000';
} elseif ($avgRating >= 70) { // Poor
    $avgRatingColor = '#dc3545'; // danger red
    $avgRatingTextColor = '#fff';
}

// Evaluation progress
$totalToEvaluate = (int)$totalGuards; // all active guards
$completedEval = (int)($evalMonth['eval_count'] ?? 0);
$evalProgress = $totalToEvaluate > 0 ? round(($completedEval / $totalToEvaluate) * 100, 1) : 0;

// Dynamic color for "Evaluations Completed" KPI card
// - 90% to 100%: Green (best)
// - 75% to <90%: Orange (almost there)
// - 50% to <75%: Yellow (needs attention)
// - <50%: Red (worst)
$evalCardBg = '#dc3545'; // default worst
$evalCardText = '#fff';
if ($evalProgress >= 90) {
    $evalCardBg = '#198754'; // success green
    $evalCardText = '#fff';
} elseif ($evalProgress >= 75) {
    $evalCardBg = '#fd7e14'; // orange
    $evalCardText = '#fff';
} elseif ($evalProgress >= 50) {
    $evalCardBg = '#ffc107'; // yellow
    $evalCardText = '#000';
}

// Gender and Marital Status distributions among active guards
$genderStmt = $conn->query("SELECT COALESCE(NULLIF(sex,''),'Not set') as label, COUNT(*) cnt FROM users WHERE Role_ID=5 AND status='Active' GROUP BY label ORDER BY cnt DESC");
$genderRows = $genderStmt ? $genderStmt->fetchAll(PDO::FETCH_ASSOC) : [];
$genderLabels = array_map(fn($r)=>$r['label'], $genderRows);
$genderCounts = array_map(fn($r)=> (int)$r['cnt'], $genderRows);

$maritalStmt = $conn->query("SELECT COALESCE(NULLIF(civil_status,''),'Not set') as label, COUNT(*) cnt FROM users WHERE Role_ID=5 AND status='Active' GROUP BY label ORDER BY cnt DESC");
$maritalRows = $maritalStmt ? $maritalStmt->fetchAll(PDO::FETCH_ASSOC) : [];
$maritalLabels = array_map(fn($r)=>$r['label'], $maritalRows);
$maritalCounts = array_map(fn($r)=> (int)$r['cnt'], $maritalRows);

// Get current super admin user's name
$hrStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 3 AND status = 'Active' AND User_ID = ?");
$hrStmt->execute([$_SESSION['user_id']]);
$hrData = $hrStmt->fetch(PDO::FETCH_ASSOC);

// Add null check
if ($hrData) {
    $hrName = $hrData['First_Name'] . ' ' . $hrData['Last_Name'];
} else {
    $hrName = "Human Resource";
}

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

// Add null check
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
    <title>Human Resource Dashboard - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/hr_dashboard.css">
    <style>
        /* Fixed height wrapper for the rating trend chart */
        .chart-wrap { height: 360px; }
        .kpi-card { color: #fff; border: 0; border-radius: 10px; padding: 16px; height: 100%; }
        .kpi-title { font-weight: 600; display:flex; align-items:center; gap:8px; }
        .kpi-value { font-size: 36px; font-weight: 700; }
        .kpi-sub { opacity: 0.9; }
        .progress { height: 10px; }
        .grid-tight .col-md-3, .grid-tight .col-md-4, .grid-tight .col-lg-6 { padding-left: 8px; padding-right: 8px; }
        .container-fluid { padding-left: 12px; padding-right: 12px; }
        .row { margin-left: -8px; margin-right: -8px; }
        .dashboard-card { background:#198754; color:#fff; }
        .card.shadow-sm .card-body { padding: 12px; }
        .filters .dataTables_wrapper .dataTables_filter { float: left; text-align: left; }
        .mini-chart { height: 260px; }
    </style>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
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
        </div>
        
        <!-- Dashboard Content -->
        <div class="container-fluid mt-3">
            <!-- Filters and Month Picker (DataTables-powered table) -->
            <div class="card mb-3 filters">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="monthFilterTable" class="table table-sm table-striped" style="width:100%">
                            <thead><tr><th>Month</th><th>Go</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="month" id="monthPicker" class="form-control form-control-sm" value="<?php echo htmlspecialchars(date('Y-m', strtotime($monthStart))); ?>">
                                    </td>
                                    <td><button id="applyMonth" class="btn btn-sm btn-primary">Apply</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="row grid-tight">
                <div class="col-md-3 mb-3">
                    <div class="kpi-card" style="background:#0d6efd">
                        <div class="kpi-title"><span class="material-icons">security</span> Total Guards</div>
                        <div class="kpi-value"><?php echo $totalGuards; ?></div>
                        <div class="kpi-sub">Active in system</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="kpi-card" style="background:<?php echo ($pendingRequests>0)?'#dc3545':'#198754'; ?>">
                        <div class="kpi-title"><span class="material-icons"><?php echo ($pendingRequests>0)?'pending_actions':'check_circle'; ?></span> Pending Leaves</div>
                        <div class="kpi-value"><?php echo $pendingRequests; ?></div>
                        <div class="kpi-sub"><?php echo ($pendingRequests > 0) ? 'Awaiting approval' : 'All clear'; ?></div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="kpi-card" style="background:#198754">
                        <div class="kpi-title"><span class="material-icons">how_to_reg</span> Clocked In</div>
                        <div class="kpi-value"><?php echo $guardsClocked; ?></div>
                        <div class="kpi-sub">Currently on duty</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="kpi-card" style="background:#6f42c1">
                        <div class="kpi-title"><span class="material-icons">person_add</span> New Hires (<?php echo date('M', strtotime($monthStart)); ?>)</div>
                        <div class="kpi-value"><?php echo $newHires; ?></div>
                        <div class="kpi-sub">Onboarded this month</div>
                    </div>
                </div>
            </div>

            <!-- Quick KPIs (This Month) -->
            <div class="row grid-tight">
                <div class="col-md-4 mb-3">
                    <div class="kpi-card" style="background:<?php echo $evalCardBg; ?>; color: <?php echo $evalCardText; ?>;">
                        <div class="kpi-title"><span class="material-icons">fact_check</span> Evaluations Completed</div>
                        <div class="kpi-value"><?php echo $completedEval; ?> / <?php echo $totalToEvaluate; ?></div>
                        <div class="progress bg-light"><div class="progress-bar bg-dark" role="progressbar" style="width: <?php echo $evalProgress; ?>%"></div></div>
                        <div class="kpi-sub mt-1"><?php echo $evalProgress; ?>% of guards evaluated</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="kpi-card" style="background:<?php echo $avgRatingColor; ?>; color: <?php echo $avgRatingTextColor; ?>;">
                        <div class="kpi-title"><span class="material-icons">star_rate</span> Avg Rating</div>
                        <div class="kpi-value"><?php echo number_format($avgRating, 2); ?>% <span style="font-size:16px; font-weight:600">(<?php echo $avgRatingLabel; ?>)</span></div>
                        <div class="kpi-sub">O:90+ | G:85+ | F:80+ | NI:75+ | P:70+</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="kpi-card" style="background:#0dcaf0">
                        <div class="kpi-title"><span class="material-icons">gavel</span> Decisions</div>
                        <div class="kpi-value"><?php echo (int)$evalMonth['renewals']; ?> / <?php echo (int)$evalMonth['terminations']; ?></div>
                        <div class="kpi-sub">Renewals / Terminations</div>
                    </div>
                </div>
            </div>

            <!-- Performance Analytics -->
            <div class="row grid-tight">
                <div class="col-lg-6 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-danger text-white d-flex align-items-center">
                            <span class="material-icons me-2">trending_down</span>
                            Underperformers (<?php echo htmlspecialchars($currentMonthLabel); ?>)
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th class="text-center">Rating</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($underperformers) === 0): ?>
                                            <tr><td colspan="3" class="text-center text-muted p-3">No underperformers (70%–79.99%) this month.</td></tr>
                                        <?php else: foreach ($underperformers as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                                <td class="text-center"><span class="badge bg-danger"><?php echo number_format($row['overall_rating'],1); ?>%</span></td>
                                                <td><?php echo date('M d, Y', strtotime($row['evaluation_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-2 text-end">
                                <a href="performance_evaluation.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-success text-white d-flex align-items-center">
                            <span class="material-icons me-2">show_chart</span>
                            Rating Trend (Last 6 Months)
                        </div>
                        <div class="card-body">
                            <div class="chart-wrap"><canvas id="ratingTrendChart"></canvas></div>
                            <small class="text-muted">Average overall rating per month</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Demographics -->
            <div class="row grid-tight">
                <div class="col-lg-6 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-info text-white d-flex align-items-center">
                            <span class="material-icons me-2">male</span>
                            Gender Distribution
                        </div>
        <div class="card-body"><div class="mini-chart"><canvas id="genderChart"></canvas></div></div>
                    </div>
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-warning text-dark d-flex align-items-center">
                            <span class="material-icons me-2">favorite</span>
                            Marital Status Distribution
                        </div>
        <div class="card-body"><div class="mini-chart"><canvas id="maritalChart"></canvas></div></div>
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

    

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/hr_dashboard.js"></script>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="hr_dashboard.php" class="mobile-nav-item active">
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
                <span class="material-icons">list</span>
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
        // Rating Trend Chart (Last 6 Months)
        (function(){
            const ctx = document.getElementById('ratingTrendChart');
            if (!ctx) return;
            const labels = <?php echo json_encode($trendLabels); ?>;
            const data = <?php echo json_encode($trendValues); ?>;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Avg Rating (%)',
                        data: data,
                        borderColor: 'rgba(25, 135, 84, 1)',
                        backgroundColor: 'rgba(25, 135, 84, 0.15)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { min: 60, max: 100, title: { display: true, text: 'Percent' } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        })();
        // Gender and Marital Doughnut Charts
        (function(){
            const genderEl = document.getElementById('genderChart');
            const maritalEl = document.getElementById('maritalChart');
            const palette = ['#0d6efd','#20c997','#dc3545','#ffc107','#6610f2','#6f42c1','#198754','#0dcaf0','#fd7e14','#adb5bd'];
            if (genderEl) {
                const labels = <?php echo json_encode($genderLabels); ?>;
                const data = <?php echo json_encode($genderCounts); ?>;
                new Chart(genderEl, {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{ data, backgroundColor: palette, borderWidth: 0 }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }
            if (maritalEl) {
                const labels = <?php echo json_encode($maritalLabels); ?>;
                const data = <?php echo json_encode($maritalCounts); ?>;
                new Chart(maritalEl, {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{ data, backgroundColor: palette, borderWidth: 0 }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }
        })();

        // Month filter with DataTables shell
        (function(){
            $(function(){
                const table = $('#monthFilterTable').DataTable({
                    paging: false,
                    info: false,
                    searching: false,
                    ordering: false,
                    lengthChange: false,
                });
                function applyMonth(){
                    const m = $('#monthPicker').val();
                    if (!m) return;
                    const base = window.location.pathname.split('/').pop();
                    const url = new URL(window.location.href);
                    url.searchParams.set('month', m);
                    window.location.href = url.toString();
                }
                $('#applyMonth').on('click', applyMonth);
                $('#monthPicker').on('change', function(){ /* auto apply on change */ applyMonth(); });
            });
        })();
    </script>
</body>
</html>