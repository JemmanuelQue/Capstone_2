<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn);

// Database connection
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

// Get current superadmin user's name
$superadminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 1 AND status = 'Active' AND User_ID = ?");
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
    <title>Super Admin Daily Time Record - Green Meadows Security Agency</title>
    
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
                    <a class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Daily Time Record">
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
                <a href="../logout.php" class="nav-link mt-5" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout">
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
                <form method="GET" id="dtrFilters" class="filter-form-custom row g-2 align-items-end">
                    <div class="col-6 col-md-1">
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
                    <div class="col-6 col-md-2">
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
                    <div class="col-6 col-md-3">
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
                        
                        // Get attendance records for this guard
                        $attendanceQuery = "
                            SELECT 
                                a.ID,
                                DATE(a.Time_In) as date,
                                a.Time_In as time_in,
                                a.Time_Out as time_out,
                                CASE 
                                    WHEN a.Time_Out IS NULL THEN 0 
                                    ELSE TIMESTAMPDIFF(HOUR, a.Time_In, a.Time_Out)
                                END as hours_worked_raw
                            FROM attendance a
                            WHERE a.User_ID = ?
                            AND DATE(a.Time_In) BETWEEN ? AND ?
                            ORDER BY a.Time_In DESC
                        ";
                        
                        $attendanceStmt = $conn->prepare($attendanceQuery);
                        $attendanceStmt->execute([$guardId, $startDate, $endDate]);
                        
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
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Hours Worked</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($attendanceStmt->rowCount() > 0) {
                                            while ($record = $attendanceStmt->fetch(PDO::FETCH_ASSOC)) {
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
                                                    <td><?php echo date('h:i A', strtotime($record['time_in'])); ?></td>
                                                    <td>
                                                        <?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : 'Not yet logged out'; ?>
                                                    </td>
                                                    <td><?php echo $hoursWorked; ?> hours</td>
                                                </tr>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No attendance records found for this period</td>
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
        
    

    <!-- Actions removed for Super Admin view-only mode: edit/add/archive modals and handlers deleted -->

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

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <!-- Sidebar and Tooltip??? JS -->
    <script src="js/superadmin_dashboard.js"></script>

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
            <a href="daily_time_record.php" class="mobile-nav-item active">
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
        const url = buildExportUrl('pdf');
        window.open(url, '_blank');
    });
    $('#exportExcelBtn').on('click', function(){
        const url = buildExportUrl('excel');
        window.open(url, '_blank');
    });
    // Removed: PDF by Location and ZIP per Guard options

    // Actions (edit/add/archive) removed for Super Admin view-only DTR
});
</script>
</body>
</html>