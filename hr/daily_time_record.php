<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
// Validate session and require HR role (3); handles redirect on failure
if (!validateSession($conn, 3)) { exit; }

// Database connection (session_check already requires db_connection.php; keep for clarity)
require_once '../db_connection.php'; // assumes $conn is PDO

// Get current date info for default filter values
$currentYear = date('Y');
$currentMonth = date('m');
$day = date('d');
$datePeriod = ($day <= 15) ? '1-15' : '16-31';

// Handle filter form submission
if (isset($_GET['filter_submit'])) {
    $month = $_GET['month'];
    $year = $_GET['year'];
    $datePeriod = $_GET['period'];
    $searchTerm = isset($_GET['guardSearch']) ? $_GET['guardSearch'] : '';
    $locationFilter = isset($_GET['location']) ? $_GET['location'] : '';
} else {
    // Default values
    $month = $currentMonth;
    $year = $currentYear;
    $searchTerm = '';
    $locationFilter = '';
}

if (session_status() === PHP_SESSION_NONE) session_start();
// Save current page as last visited (except profile)
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

// Get current Accounting user's name
$superadminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 3 AND status = 'Active' AND User_ID = ?");
$superadminStmt->execute([$_SESSION['user_id']]);
$superadminData = $superadminStmt->fetch(PDO::FETCH_ASSOC);
$superadminName = $superadminData ? $superadminData['First_Name'] . ' ' . $superadminData['Last_Name'] : "Accounting";

// Get superadmin's profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $superadminProfile = $profileData['Profile_Pic'];
} else {
    $superadminProfile = '../images/default_profile.png';
}

// Determine date range based on period
if ($datePeriod == '1-15') {
    $startDate = "$year-$month-01";
    $endDate = "$year-$month-15";
    $periodLabel = "1st - 15th";
} else if ($datePeriod == '16-31') {
    $startDate = "$year-$month-16";
    $lastDay = date('t', strtotime("$year-$month-01"));
    $endDate = "$year-$month-$lastDay";
    $periodLabel = "16th - 31st";
} else {
    // All dates in month
    $startDate = "$year-$month-01";
    $lastDay = date('t', strtotime("$year-$month-01"));
    $endDate = "$year-$month-$lastDay";
    $periodLabel = "Whole Month";
}

// Get all unique location names for filter dropdown
$locationsQuery = "SELECT DISTINCT location_name FROM guard_locations WHERE location_name != '' ORDER BY location_name";
$locationsStmt = $conn->prepare($locationsQuery);
$locationsStmt->execute();
$locations = $locationsStmt->fetchAll(PDO::FETCH_COLUMN);

// Prepare search condition
$searchCondition = '';
$locationCondition = '';
$params = [];

if (!empty($searchTerm)) {
    $searchCondition = " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR CONCAT(u.First_Name, ' ', u.Last_Name) LIKE ?) ";
    $searchParam = "%$searchTerm%";
    $params = [$searchParam, $searchParam, $searchParam];
}

if (!empty($locationFilter)) {
    $locationCondition = " AND gl.location_name = ? ";
    $params[] = $locationFilter;
}

// Get list of guards with attendance data in the specified period
$guardsQuery = "
    SELECT DISTINCT u.User_ID, u.First_Name, u.Last_Name, u.middle_name, gl.location_name
    FROM users u
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
    WHERE u.Role_ID = 5 AND u.status = 'Active'
    $searchCondition
    $locationCondition
    ORDER BY u.Last_Name, u.First_Name
";

$guardsStmt = $conn->prepare($guardsQuery);

// Bind parameters
if (!empty($params)) {
    foreach ($params as $index => $param) {
        $guardsStmt->bindValue($index + 1, $param);
    }
}

// Format month name for display
$monthName = date('F', strtotime("$year-$month-01"));
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Daily Time Record - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/daily_time_record.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .badge-primary {
            background-color: #0d6efd;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
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
                <a href="hr_dashboard.php"class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Daily Time Record">
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
                <a href="performance_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
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
                    <span><?php echo $superadminName; ?></span>
                    <img src="<?php echo $superadminProfile; ?>" alt="User Profile">
                </a>
        </div>

    <!-- Main content area -->
    <div class="container-fluid mt-4">
            <h1 class="page-title">Daily Time Records - Guards</h1>
            
            <!-- Filters -->
            <div class="filter-container">
                <form method="GET" id="dtrFilters" class="filter-form-custom row g-2 align-items-end d-flex justify-content-center">
                    <div class="col-6 col-md-2">
                        <label for="monthFilter" class="form-label">Month</label>
                        <select class="form-select" id="monthFilter" name="month">
                            <option value="01" <?php echo $month == '01' ? 'selected' : ''; ?>>January</option>
                            <option value="02" <?php echo $month == '02' ? 'selected' : ''; ?>>February</option>
                            <option value="03" <?php echo $month == '03' ? 'selected' : ''; ?>>March</option>
                            <option value="04" <?php echo $month == '04' ? 'selected' : ''; ?>>April</option>
                            <option value="05" <?php echo $month == '05' ? 'selected' : ''; ?>>May</option>
                            <option value="06" <?php echo $month == '06' ? 'selected' : ''; ?>>June</option>
                            <option value="07" <?php echo $month == '07' ? 'selected' : ''; ?>>July</option>
                            <option value="08" <?php echo $month == '08' ? 'selected' : ''; ?>>August</option>
                            <option value="09" <?php echo $month == '09' ? 'selected' : ''; ?>>September</option>
                            <option value="10" <?php echo $month == '10' ? 'selected' : ''; ?>>October</option>
                            <option value="11" <?php echo $month == '11' ? 'selected' : ''; ?>>November</option>
                            <option value="12" <?php echo $month == '12' ? 'selected' : ''; ?>>December</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <label for="yearFilter" class="form-label">Year</label>
                        <select class="form-select" id="yearFilter" name="year">
                            <?php for($i = date('Y'); $i >= date('Y')-5; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="periodFilter" class="form-label">Cutoff Period</label>
                        <select class="form-select" id="periodFilter" name="period">
                            <option value="1-15" <?php echo $datePeriod == '1-15' ? 'selected' : ''; ?>>1st - 15th</option>
                            <option value="16-31" <?php echo $datePeriod == '16-31' ? 'selected' : ''; ?>>16th - 31st</option>
                            <option value="all" <?php echo $datePeriod == 'all' ? 'selected' : ''; ?>>Whole Month</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="locationFilter" class="form-label">Location</label>
                        <select class="form-select" id="locationFilter" name="location">
                            <option value="">All Locations</option>
                            <?php foreach($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $locationFilter == $location ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="guardSearch" class="form-label">Search Guard</label>
                        <input type="text" class="form-control" id="guardSearch" name="guardSearch" placeholder="Enter guard name" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <div class="col-12 col-md-1 d-grid">
                        <label class="form-label" style="visibility:hidden;">Apply</label>
                        <button type="submit" class="btn btn-success filter-btn" name="filter_submit">
                            <i class="material-icons">search</i> Apply
                        </button>
                    </div>
                </form>
            </div>
    
                        
            <!-- Period Summary Banner -->
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="material-icons me-2">date_range</i>
                    <strong>Viewing: </strong> &nbsp; <?php echo $monthName . ' ' . $year . ' (' . $periodLabel . ')'; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <!-- Export buttons placed outside the filter container, aligned to the right -->
            <div class="d-flex justify-content-end mt-2 gap-2">
                <button type="button" id="exportPdfBtn" class="btn btn-danger">
                    <span class="material-icons" style="vertical-align:middle;">picture_as_pdf</span>
                    <span>Export PDF</span>
                </button>
                <button type="button" id="exportExcelBtn" class="btn btn-primary">
                    <span class="material-icons" style="vertical-align:middle;">table_view</span>
                    <span>Export Excel</span>
                </button>
            </div><br>
            
            <!-- Guard attendance records display -->
            <div id="guardAttendanceData">
                <?php
                // Prepare search condition
                $searchCondition = '';
                $locationCondition = '';
                $params = [];
                
                if (!empty($searchTerm)) {
                    $searchCondition = " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR CONCAT(u.First_Name, ' ', u.Last_Name) LIKE ?) ";
                    $searchParam = "%$searchTerm%";
                    $params = [$searchParam, $searchParam, $searchParam];
                }

                if (!empty($locationFilter)) {
                    $locationCondition = " AND gl.location_name = ? ";
                    $params[] = $locationFilter;
                }
                
                // Get list of guards with attendance data in the specified period
                $guardsQuery = "
                    SELECT DISTINCT u.User_ID, u.First_Name, u.Last_Name, u.middle_name, gl.location_name
                    FROM users u
                    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
                    WHERE u.Role_ID = 5 AND u.status = 'Active'
                    $searchCondition
                    $locationCondition
                    ORDER BY u.Last_Name, u.First_Name
                ";
                
                $guardsStmt = $conn->prepare($guardsQuery);
                
                // Bind search parameters if they exist
                if (!empty($params)) {
                    foreach ($params as $index => $param) {
                        $guardsStmt->bindValue($index + 1, $param);
                    }
                }
                
                $guardsStmt->execute();
                
                if ($guardsStmt->rowCount() == 0) {
                    echo '<div class="alert alert-info">
                            <i class="material-icons align-middle me-2">info</i>
                            No guards found matching your search criteria.
                          </div>';
                } else {
                    // Loop through each guard
                    while ($guard = $guardsStmt->fetch(PDO::FETCH_ASSOC)) {
                        $guardId = $guard['User_ID'];
                        $guardName = $guard['First_Name'] . ' ' . (!empty($guard['middle_name']) ? $guard['middle_name'] . ' ' : '') . $guard['Last_Name'];
                        $location = $guard['location_name'] ? $guard['location_name'] : 'Not Assigned';
                        
                                                // Build guard name variants for matching in activity logs (with and without middle name)
                                                $guardNameNoMiddle = $guard['First_Name'] . ' ' . $guard['Last_Name'];

                                                // Get attendance records for this guard, inferring manual-entry author via nearest matching activity log
                                                $attendanceQuery = "
                                                        SELECT 
                                                                a.ID,
                                                                DATE(a.Time_In) as date,
                                                                a.Time_In as time_in,
                                                                a.Time_Out as time_out,
                                                                a.Latitude,
                                                                a.Longitude,
                                                                a.Time_Out_Latitude,
                                                                a.Time_Out_Longitude,
                                                                a.verification_image_path,
                                                                a.Time_Out_Image,
                                                                a.face_verified,
                                                                a.Created_At,
                                                                CASE 
                                                                        WHEN a.Time_Out IS NULL THEN 0 
                                                                        ELSE TIMESTAMPDIFF(HOUR, a.Time_In, a.Time_Out)
                                                                END as hours_worked_raw,
                                                                CASE 
                                                                        WHEN a.verification_image_path IS NULL AND a.Latitude IS NULL THEN 'Manual'
                                                                        ELSE 'Facial Recognition'
                                                                END as entry_method,
                                                                (
                                                                        SELECT CONCAT(u1.First_Name, ' ', u1.Last_Name)
                                                                        FROM activity_logs al1
                                                                        JOIN users u1 ON u1.User_ID = al1.User_ID
                                                                        WHERE al1.Activity_Type = 'Attendance Add'
                                                                            AND (
                                                                                al1.Activity_Details LIKE CONCAT('% for ', ?, '%')
                                                                                OR al1.Activity_Details LIKE CONCAT('% for ', ?, '%')
                                                                            )
                                                                        ORDER BY ABS(TIMESTAMPDIFF(MINUTE, COALESCE(a.Created_At, a.Time_In), al1.Timestamp)) ASC
                                                                        LIMIT 1
                                                                ) as added_by_name,
                                                                (
                                                                        SELECT al2.Timestamp
                                                                        FROM activity_logs al2
                                                                        WHERE al2.Activity_Type = 'Attendance Add'
                                                                            AND (
                                                                                al2.Activity_Details LIKE CONCAT('% for ', ?, '%')
                                                                                OR al2.Activity_Details LIKE CONCAT('% for ', ?, '%')
                                                                            )
                                                                        ORDER BY ABS(TIMESTAMPDIFF(MINUTE, COALESCE(a.Created_At, a.Time_In), al2.Timestamp)) ASC
                                                                        LIMIT 1
                                                                ) as added_timestamp
                                                        FROM attendance a
                                                        WHERE a.User_ID = ?
                                                            AND DATE(a.Time_In) BETWEEN ? AND ?
                                                        ORDER BY a.Time_In DESC
                                                ";

                                                $attendanceStmt = $conn->prepare($attendanceQuery);
                                                $attendanceStmt->execute([$guardName, $guardNameNoMiddle, $guardName, $guardNameNoMiddle, $guardId, $startDate, $endDate]);
                        
                        // Calculate total hours
                        $totalHoursQuery = "
                            SELECT COALESCE(SUM(
                                CASE 
                                    WHEN Time_Out IS NULL THEN 0
                                    ELSE 
                                        CASE
                                            WHEN TIME_TO_SEC(Time_Out) < TIME_TO_SEC(Time_In) THEN
                                                -- Overnight shift: add 24 hours (86400 seconds) to time_out before calculating
                                                (TIME_TO_SEC(Time_Out) + 86400 - TIME_TO_SEC(Time_In)) / 3600
                                            ELSE
                                                (TIME_TO_SEC(Time_Out) - TIME_TO_SEC(Time_In)) / 3600
                                        END
                                END
                            ), 0) as total_hours
                            FROM attendance
                            WHERE User_ID = ?
                            AND DATE(Time_In) BETWEEN ? AND ?
                        ";
                        
                        $totalHoursStmt = $conn->prepare($totalHoursQuery);
                        $totalHoursStmt->execute([$guardId, $startDate, $endDate]);
                        
                        $totalHours = round($totalHoursStmt->fetchColumn(), 1); // Round to 1 decimal place
                        
                        ?>
                        <div class="guard-card">
                            <div class="guard-header">
                                <div class="guard-info">
                                    <h4 class="guard-name"><?php echo htmlspecialchars($guardName); ?></h4>
                                    <span class="guard-location"><i class="material-icons">place</i> <?php echo htmlspecialchars($location); ?></span>
                                </div>
                                <div class="total-hours">
                                    Total Hours: <span><?php echo $totalHours; ?> hours</span>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="material-icons text-primary" style="font-size: 14px;">face</i> Facial Recognition &nbsp;&nbsp;
                                        <i class="material-icons text-warning" style="font-size: 14px;">person</i> Manual Entry
                                    </small>
                                </div>
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Hours Worked</th>
                                            <th class="text-center" style="min-width: 120px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($attendanceStmt->rowCount() > 0) {
                                            while ($record = $attendanceStmt->fetch(PDO::FETCH_ASSOC)) {
                                                // Debug: Check what data we're getting
                                                // echo "<!-- DEBUG: Guard: $guardName, Added By: " . ($record['added_by_name'] ?? 'NULL') . ", Entry Method: " . ($record['entry_method'] ?? 'NULL') . " -->";
                                                
                                                // Calculate hours properly for overnight shifts
                                                $hoursWorked = 0;
                                                $timeInObj = new DateTime($record['time_in']);
                                                
                                                if ($record['time_out']) {
                                                    $timeOutObj = new DateTime($record['time_out']);
                                                    
                                                    // If time_out is earlier than time_in, it's an overnight shift
                                                    if ($timeOutObj < $timeInObj) {
                                                        // Add one day to time_out for correct calculation
                                                        $timeOutObj->modify('+1 day');
                                                    }
                                                    
                                                    // Calculate difference in hours
                                                    $interval = $timeOutObj->diff($timeInObj);
                                                    $hoursWorked = $interval->h + ($interval->days * 24);
                                                    
                                                    // Format the date to show the range if overnight shift
                                                    $dateDisplay = date('M j, Y', strtotime($record['date']));
                                                    if ($timeInObj->format('Y-m-d') != $timeOutObj->format('Y-m-d')) {
                                                        $dateDisplay = date('M j', strtotime($record['date'])) . ' to ' . 
                                                                     date('M j, Y', strtotime($timeOutObj->format('Y-m-d')));
                                                    }
                                                } else {
                                                    $dateDisplay = date('M j, Y', strtotime($record['date']));
                                                }
                                                ?>
                                                <tr>
                                                    <td><?php echo $dateDisplay; ?></td>
                                                    <td>
                                                        <?php echo date('h:i A', strtotime($record['time_in'])); ?>
                                                        <?php if ($record['entry_method'] === 'Manual'): ?>
                                                            <small class="text-warning ms-1" title="Manual Entry">
                                                                <i class="material-icons" style="font-size: 14px;">person</i>
                                                            </small>
                                                        <?php else: ?>
                                                            <small class="text-primary ms-1" title="Facial Recognition">
                                                                <i class="material-icons" style="font-size: 14px;">face</i>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($record['time_out']): ?>
                                                            <?php echo date('h:i A', strtotime($record['time_out'])); ?>
                                                            <?php if ($record['entry_method'] === 'Manual'): ?>
                                                                <small class="text-warning ms-1" title="Manual Entry">
                                                                    <i class="material-icons" style="font-size: 14px;">person</i>
                                                                </small>
                                                            <?php else: ?>
                                                                <small class="text-primary ms-1" title="Facial Recognition">
                                                                    <i class="material-icons" style="font-size: 14px;">face</i>
                                                                </small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            Not yet logged out
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $hoursWorked; ?> hours</td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-info view-attendance me-1" 
                                                                data-id="<?php echo $record['ID']; ?>"
                                                                data-guard-name="<?php echo htmlspecialchars($guardName); ?>"
                                                                data-date="<?php echo $dateDisplay; ?>"
                                                                data-timein="<?php echo date('h:i A', strtotime($record['time_in'])); ?>"
                                                                data-timeout="<?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : 'Not yet logged out'; ?>"
                                                                data-latitude="<?php echo $record['Latitude'] ?? ''; ?>"
                                                                data-longitude="<?php echo $record['Longitude'] ?? ''; ?>"
                                                                data-timeout-latitude="<?php echo $record['Time_Out_Latitude'] ?? ''; ?>"
                                                                data-timeout-longitude="<?php echo $record['Time_Out_Longitude'] ?? ''; ?>"
                                                                data-timein-image="<?php echo $record['verification_image_path'] ?? ''; ?>"
                                                                data-timeout-image="<?php echo $record['Time_Out_Image'] ?? ''; ?>"
                                                                data-entry-method="<?php echo $record['entry_method'] ?? 'Unknown'; ?>"
                                                                data-face-verified="<?php echo $record['face_verified'] ?? 0; ?>"
                                                                data-added-by="<?php echo $record['added_by_name'] ?? ''; ?>"
                                                                data-added-timestamp="<?php echo $record['added_timestamp'] ?? ''; ?>"
                                                                title="View Attendance Details">
                                                            <i class="material-icons">visibility</i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-success add-attendance me-1" 
                                                                data-guard-id="<?php echo $guardId; ?>"
                                                                data-guard-name="<?php echo htmlspecialchars($guardName); ?>"
                                                                title="Add New Attendance">
                                                            <i class="material-icons">add</i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-primary edit-attendance me-1" 
                                                                data-id="<?php echo $record['ID']; ?>"
                                                                data-timein="<?php echo date('Y-m-d\TH:i', strtotime($record['time_in'])); ?>"
                                                                data-timeout="<?php echo $record['time_out'] ? date('Y-m-d\TH:i', strtotime($record['time_out'])) : ''; ?>">
                                                            <i class="material-icons">edit</i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger archive-attendance" 
                                                                data-id="<?php echo $record['ID']; ?>">
                                                            <i class="material-icons">archive</i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No attendance records found for this period</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-success add-attendance" 
                                                            data-guard-id="<?php echo $guardId; ?>"
                                                            data-guard-name="<?php echo htmlspecialchars($guardName); ?>"
                                                            title="Add New Attendance">
                                                         Add Record <strong>+</strong>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
    </div>
        
    

    <!-- Edit Attendance Modal -->
    <div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-labelledby="editAttendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAttendanceModalLabel">Edit Attendance Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAttendanceForm">
                        <input type="hidden" id="attendanceId" name="attendanceId">
                        
                        <div class="mb-3">
                            <label for="editTimeIn" class="form-label">Time In</label>
                            <input type="datetime-local" class="form-control" id="editTimeIn" name="timeIn" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editTimeOut" class="form-label">Time Out</label>
                            <input type="datetime-local" class="form-control" id="editTimeOut" name="timeOut">
                            <small class="form-text text-muted">Leave empty if guard hasn't logged out yet</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editReason" class="form-label">Reason for Edit</label>
                            <textarea class="form-control" id="editReason" name="reason" rows="3" required 
                                placeholder="Please provide a reason for editing this attendance record"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveAttendanceBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

     <!-- Add Attendance Modal -->
    <div class="modal fade" id="addAttendanceModal" tabindex="-1" aria-labelledby="addAttendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAttendanceModalLabel">Add New Attendance Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="material-icons align-middle me-2">info</i>
                        Adding attendance record for: <strong id="selectedGuardName"></strong>
                    </div>
                    
                    <form id="addAttendanceForm">
                        <input type="hidden" id="selectedGuardId" name="guardId">
                        
                        <div class="mb-3">
                            <label for="addTimeIn" class="form-label">Time In</label>
                            <input type="datetime-local" class="form-control" id="addTimeIn" name="timeIn" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="addTimeOut" class="form-label">Time Out</label>
                            <input type="datetime-local" class="form-control" id="addTimeOut" name="timeOut">
                            <small class="form-text text-muted">Leave empty if guard hasn't logged out yet</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="addReason" class="form-label">Reason for Adding</label>
                            <textarea class="form-control" id="addReason" name="reason" rows="3" required 
                                placeholder="Please provide a reason for adding this attendance record (e.g., missed punch, manual entry, etc.)"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="saveNewAttendanceBtn">
                        <i class="material-icons me-1">save</i> Add Attendance
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div class="modal fade" id="archiveAttendanceModal" tabindex="-1" aria-labelledby="archiveAttendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="archiveAttendanceModalLabel">Archive Attendance Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive this attendance record? The record will be moved to the archives.</p>
                    <form id="archiveAttendanceForm">
                        <input type="hidden" id="archiveAttendanceId" name="attendanceId">
                        <div class="mb-3">
                            <label for="archiveReason" class="form-label">Reason for Archiving</label>
                            <textarea class="form-control" id="archiveReason" name="reason" rows="3" required 
                                placeholder="Please provide a reason for archiving this record"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmArchiveBtn">Archive Record</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Attendance Details Modal -->
    <div class="modal fade view-attendance-modal" id="viewAttendanceModal" tabindex="-1" aria-labelledby="viewAttendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewAttendanceModalLabel">
                        <i class="material-icons me-2">visibility</i>Attendance Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="alert alert-info">
                                <strong>Guard:</strong> <span id="viewGuardName"></span><br>
                                <strong>Date:</strong> <span id="viewDate"></span><br>
                                <strong>Entry Method:</strong> <span id="viewEntryMethod" class="badge"></span><br>
                                <div id="viewManualEntryDetails" style="display: none; margin-top: 10px;">
                                    <strong>Added by:</strong> <span id="viewAddedBy"></span><br>
                                    <strong>Added on:</strong> <span id="viewAddedTimestamp"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Time In Details -->
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="material-icons me-2">login</i>Time In Details</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Time:</strong> <span id="viewTimeIn"></span></p>
                                    <p><strong>Location:</strong><br>
                                        <span id="viewTimeInLocation">
                                            <span id="viewLatitude"></span>, <span id="viewLongitude"></span>
                                            <br>
                                            <a href="#" id="viewTimeInMapLink" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                                <i class="material-icons me-1">map</i>View on Google Maps
                                            </a>
                                        </span>
                                    </p>
                                    <div class="text-center">
                                        <p><strong>Photo:</strong></p>
                                        <img id="viewTimeInImage" src="" alt="Time In Photo" class="img-fluid rounded shadow" 
                                             style="max-height: 200px; cursor: pointer;" onclick="openImageModal(this.src)">
                                        <div id="viewTimeInNoImage" class="text-muted" style="display: none;">
                                            <i class="material-icons" style="font-size: 48px;">no_photography</i><br>
                                            No photo available
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Time Out Details -->
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0"><i class="material-icons me-2">logout</i>Time Out Details</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Time:</strong> <span id="viewTimeOut"></span></p>
                                    <div id="viewTimeOutDetails">
                                        <p><strong>Location:</strong><br>
                                            <span id="viewTimeOutLocation">
                                                <span id="viewTimeOutLatitude"></span>, <span id="viewTimeOutLongitude"></span>
                                                <br>
                                                <a href="#" id="viewTimeOutMapLink" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                                    <i class="material-icons me-1">map</i>View on Google Maps
                                                </a>
                                            </span>
                                        </p>
                                        <div class="text-center">
                                            <p><strong>Photo:</strong></p>
                                            <img id="viewTimeOutImage" src="" alt="Time Out Photo" class="img-fluid rounded shadow" 
                                                 style="max-height: 200px; cursor: pointer;" onclick="openImageModal(this.src)">
                                            <div id="viewTimeOutNoImage" class="text-muted" style="display: none;">
                                                <i class="material-icons" style="font-size: 48px;">no_photography</i><br>
                                                No photo available
                                            </div>
                                        </div>
                                    </div>
                                    <div id="viewTimeOutPending" class="text-center text-muted" style="display: none;">
                                        <i class="material-icons" style="font-size: 48px;">schedule</i><br>
                                        Guard hasn't logged out yet
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Zoom Modal -->
    <div class="modal fade" id="imageZoomModal" tabindex="-1" aria-labelledby="imageZoomModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageZoomModalLabel">Attendance Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="zoomedImage" src="" alt="Zoomed Attendance Photo" class="img-fluid">
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
            if (profilePicInput) {
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
            }
        });
    </script>

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <!-- Sidebar and Tooltip??? JS -->
    <script src="js/hr_dashboard.js"></script>

   <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="hr_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item active">
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
                <span class="material-icons">assignment</span>
                <span class="mobile-nav-text">Masterlist</span>
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
    console.log('Document ready - jQuery loaded successfully');
    
    // Build export URL with current filters
    function buildExportUrl(type){
        const params = new URLSearchParams();
        params.set('month', $('#monthFilter').val());
        params.set('year', $('#yearFilter').val());
        params.set('period', $('#periodFilter').val());
        const loc = $('#locationFilter').val();
        const search = $('#guardSearch').val();
        if (loc) params.set('location', loc);
        if (search) params.set('guardSearch', search);
        // Unified endpoint
        let endpoint = 'dtr_pdf_excel.php';
        // Map type to action param
        let action = 'excel';
        if (type === 'pdf') action = 'pdf';
        params.set('action', action);
        return endpoint + '?' + params.toString();
    }

    $('#exportPdfBtn').on('click', function(){
        console.log('PDF export clicked');
        const url = buildExportUrl('pdf');
        console.log('PDF URL:', url);
        window.open(url, '_blank');
    });
    $('#exportExcelBtn').on('click', function(){
        console.log('Excel export clicked');
        const url = buildExportUrl('excel');
        console.log('Excel URL:', url);
        window.open(url, '_blank');
    });
    // Removed: PDF by Location and ZIP per Guard options

    // View attendance button click
    $(document).on('click', '.view-attendance', function() {
        console.log('View attendance button clicked');
        const guardName = $(this).data('guard-name');
        const date = $(this).data('date');
        const timeIn = $(this).data('timein');
        const timeOut = $(this).data('timeout');
        const latitude = $(this).data('latitude');
        const longitude = $(this).data('longitude');
        const timeOutLatitude = $(this).data('timeout-latitude');
        const timeOutLongitude = $(this).data('timeout-longitude');
        const timeInImage = $(this).data('timein-image');
        const timeOutImage = $(this).data('timeout-image');
        const entryMethod = $(this).data('entry-method');
        const faceVerified = $(this).data('face-verified');
        const addedBy = $(this).data('added-by');
        const addedTimestamp = $(this).data('added-timestamp');
        
        // console.log('DEBUG: Entry Method:', entryMethod, 'Added By:', addedBy, 'Added Timestamp:', addedTimestamp);
        
        // Populate modal with data
        $('#viewGuardName').text(guardName);
        $('#viewDate').text(date);
        $('#viewTimeIn').text(timeIn);
        $('#viewTimeOut').text(timeOut);
        
        // Handle entry method badge
        const entryBadge = $('#viewEntryMethod');
        if (entryMethod === 'Manual') {
            entryBadge.removeClass('badge-primary').addClass('badge-warning').text('Manual Entry');
            // Show manual entry details if available
            if (addedBy) {
                $('#viewAddedBy').text(addedBy);
                if (addedTimestamp) {
                    const timestamp = new Date(addedTimestamp);
                    $('#viewAddedTimestamp').text(timestamp.toLocaleString());
                } else {
                    $('#viewAddedTimestamp').text('Not available');
                }
                $('#viewManualEntryDetails').show();
            } else {
                $('#viewManualEntryDetails').hide();
            }
        } else {
            entryBadge.removeClass('badge-warning').addClass('badge-primary').text('Facial Recognition');
            $('#viewManualEntryDetails').hide();
        }
        
        // Handle Time In location and image based on entry method
        if (entryMethod === 'Manual') {
            // For manual entries, show that location wasn't captured
            $('#viewTimeInLocation').html('<span class="text-muted"><i class="material-icons me-1">location_off</i>Location not captured (Manual Entry)</span>').show();
            $('#viewTimeInImage').hide();
            $('#viewTimeInNoImage').html('<i class="material-icons" style="font-size: 48px;">person</i><br>Manual Entry<br><small class="text-muted">No photo required</small>').show();
        } else {
            // For facial recognition entries, show GPS and photo data
            if (latitude && longitude) {
                $('#viewLatitude').text(latitude);
                $('#viewLongitude').text(longitude);
                $('#viewTimeInMapLink').attr('href', `https://www.google.com/maps?q=${latitude},${longitude}`);
                $('#viewTimeInLocation').show();
            } else {
                $('#viewTimeInLocation').hide();
            }
            
            if (timeInImage) {
                $('#viewTimeInImage').attr('src', timeInImage).show();
                $('#viewTimeInNoImage').hide();
            } else {
                $('#viewTimeInImage').hide();
                $('#viewTimeInNoImage').show();
            }
        }
        
        // Handle Time Out details
        if (timeOut !== 'Not yet logged out') {
            $('#viewTimeOutDetails').show();
            $('#viewTimeOutPending').hide();
            
            if (entryMethod === 'Manual') {
                // For manual entries, show that location wasn't captured
                $('#viewTimeOutLocation').html('<span class="text-muted"><i class="material-icons me-1">location_off</i>Location not captured (Manual Entry)</span>').show();
                $('#viewTimeOutImage').hide();
                $('#viewTimeOutNoImage').html('<i class="material-icons" style="font-size: 48px;">person</i><br>Manual Entry<br><small class="text-muted">No photo required</small>').show();
            } else {
                // For facial recognition entries, show GPS and photo data
                if (timeOutLatitude && timeOutLongitude) {
                    $('#viewTimeOutLatitude').text(timeOutLatitude);
                    $('#viewTimeOutLongitude').text(timeOutLongitude);
                    $('#viewTimeOutMapLink').attr('href', `https://www.google.com/maps?q=${timeOutLatitude},${timeOutLongitude}`);
                    $('#viewTimeOutLocation').show();
                } else {
                    $('#viewTimeOutLocation').hide();
                }
                
                if (timeOutImage) {
                    $('#viewTimeOutImage').attr('src', timeOutImage).show();
                    $('#viewTimeOutNoImage').hide();
                } else {
                    $('#viewTimeOutImage').hide();
                    $('#viewTimeOutNoImage').show();
                }
            }
        } else {
            $('#viewTimeOutDetails').hide();
            $('#viewTimeOutPending').show();
        }
        
        $('#viewAttendanceModal').modal('show');
    });

    // Edit attendance button click
    $(document).on('click', '.edit-attendance', function() {
        console.log('Edit attendance button clicked');
        const id = $(this).data('id');
        const timeIn = $(this).data('timein');
        const timeOut = $(this).data('timeout');
        
        $('#attendanceId').val(id);
        $('#editTimeIn').val(timeIn);
        $('#editTimeOut').val(timeOut);
        $('#editReason').val('');
        
        $('#editAttendanceModal').modal('show');
    });
    
    // Save attendance changes
    $('#saveAttendanceBtn').click(function() {
        // Validate form
        const form = $('#editAttendanceForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const attendanceId = $('#attendanceId').val();
        const timeIn = $('#editTimeIn').val();
        const timeOut = $('#editTimeOut').val();
        const reason = $('#editReason').val();
        
        $.ajax({
            url: 'dtr_management.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'edit',
                attendanceId: attendanceId,
                timeIn: timeIn,
                timeOut: timeOut,
                reason: reason
            },
            beforeSend: function() {
                console.log('[EDIT] sending payload', { attendanceId, timeIn, timeOut, reason });
            },
            success: function(response) {
                console.log('[EDIT] success raw response', response);
                const result = (typeof response === 'string') ? (function(){ try { return JSON.parse(response); } catch(e) { return { success: false, message: 'Invalid JSON in response' }; } })() : response;
                if (result.success) {
                    $('#editAttendanceModal').modal('hide');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Attendance record updated successfully.',
                        confirmButtonColor: '#2a7d4f'
                    }).then(() => {
                        // Reload the page to show updated data
                        location.reload();
                    });
                } else {
                    console.warn('[EDIT] server reported failure', result);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message || 'Failed to update attendance record.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('[EDIT] ajax error', { status: jqXHR.status, textStatus, errorThrown, responseText: jqXHR.responseText });
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'A server error occurred. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });
    
    // Archive attendance button click
    $(document).on('click', '.archive-attendance', function() {
        console.log('Archive attendance button clicked');
        const id = $(this).data('id');
        $('#archiveAttendanceId').val(id);
        $('#archiveReason').val('');
        $('#archiveAttendanceModal').modal('show');
    });
    
    // Confirm archive attendance
    $('#confirmArchiveBtn').click(function() {
        // Validate form
        const form = $('#archiveAttendanceForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const attendanceId = $('#archiveAttendanceId').val();
        const reason = $('#archiveReason').val();
        
        $.ajax({
            url: 'dtr_management.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'archive',
                attendanceId: attendanceId,
                reason: reason
            },
            beforeSend: function() {
                console.log('[ARCHIVE] sending payload', { attendanceId, reason });
            },
            success: function(response) {
                console.log('[ARCHIVE] success raw response', response);
                const result = (typeof response === 'string') ? (function(){ try { return JSON.parse(response); } catch(e) { return { success: false, message: 'Invalid JSON in response' }; } })() : response;
                if (result.success) {
                    $('#archiveAttendanceModal').modal('hide');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Attendance record archived successfully.',
                        confirmButtonColor: '#2a7d4f'
                    }).then(() => {
                        // Reload the page to show updated data
                        location.reload();
                    });
                } else {
                    console.warn('[ARCHIVE] server reported failure', result);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message || 'Failed to archive attendance record.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('[ARCHIVE] ajax error', { status: jqXHR.status, textStatus, errorThrown, responseText: jqXHR.responseText });
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'A server error occurred. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });

    // Add attendance button click
    $(document).on('click', '.add-attendance', function() {
        console.log('Add attendance button clicked');
        const guardId = $(this).data('guard-id');
        const guardName = $(this).data('guard-name');
        
        $('#selectedGuardId').val(guardId);
        $('#selectedGuardName').text(guardName);
        
        // Clear form
        $('#addAttendanceForm')[0].reset();
        $('#selectedGuardId').val(guardId); // Set again after reset
        
        // Set default time to current date at 6:00 AM
        const today = new Date();
        const defaultTimeIn = today.getFullYear() + '-' + 
                             String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                             String(today.getDate()).padStart(2, '0') + 'T06:00';
        $('#addTimeIn').val(defaultTimeIn);
        
        $('#addAttendanceModal').modal('show');
    });

    // Save new attendance record
    $('#saveNewAttendanceBtn').click(function() {
        // Validate form
        const form = $('#addAttendanceForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const guardId = $('#selectedGuardId').val();
        const timeIn = $('#addTimeIn').val();
        const timeOut = $('#addTimeOut').val();
        const reason = $('#addReason').val();
        
        // Additional validation
        if (timeOut && new Date(timeOut) <= new Date(timeIn)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Time',
                text: 'Time out must be after time in.',
                confirmButtonColor: '#dc3545'
            });
            return;
        }
        
        // Disable button to prevent double submission
        $(this).prop('disabled', true).html('<i class="material-icons me-1">hourglass_empty</i> Saving...');
        
        $.ajax({
            url: 'dtr_management.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'add',
                guardId: guardId,
                timeIn: timeIn,
                timeOut: timeOut,
                reason: reason
            },
            beforeSend: function() {
                console.log('[ADD] sending payload', { guardId, timeIn, timeOut, reason });
            },
            success: function(response) {
                console.log('[ADD] success raw response', response);
                const result = (typeof response === 'string') ? (function(){ try { return JSON.parse(response); } catch(e) { return { success: false, message: 'Invalid JSON in response' }; } })() : response;
                if (result.success) {
                    $('#addAttendanceModal').modal('hide');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message,
                        confirmButtonColor: '#2a7d4f'
                    }).then(() => {
                        // Reload the page to show new data
                        location.reload();
                    });
                } else {
                    console.warn('[ADD] server reported failure', result);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message || 'Failed to add attendance record.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('[ADD] ajax error', { status: jqXHR.status, textStatus, errorThrown, responseText: jqXHR.responseText });
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'A server error occurred. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            },
            complete: function() {
                // Re-enable button
                $('#saveNewAttendanceBtn').prop('disabled', false).html('<i class="material-icons me-1">save</i> Add Attendance');
            }
        });
    });

    // Clear form when modal is closed
    $('#addAttendanceModal').on('hidden.bs.modal', function() {
        $('#addAttendanceForm')[0].reset();
        $('#saveNewAttendanceBtn').prop('disabled', false).html('<i class="material-icons me-1">save</i> Add Attendance');
    });
});

// Function to open image in zoom modal (outside jQuery ready)
function openImageModal(imageSrc) {
    $('#zoomedImage').attr('src', imageSrc);
    $('#imageZoomModal').modal('show');
}
</script>
</body>
</html>