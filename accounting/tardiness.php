<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn, 4);
require_once __DIR__ . '/../db_connection.php';
// No payroll calculator needed for tardiness report

if (session_status() === PHP_SESSION_NONE) session_start();

// Params
$month = $_GET['month'] ?? date('Y-m');
$dateRange = $_GET['dateRange'] ?? '1-15';
$lastDayOfMonth = date('t', strtotime($month . '-01'));
if (preg_match('/^1-3[01]$/', $dateRange) || preg_match('/^1-2[89]$/', $dateRange)) {
    $dateRange = '1-' . $lastDayOfMonth;
} elseif (preg_match('/^16-3[01]$/', $dateRange) || preg_match('/^16-2[89]$/', $dateRange)) {
    $dateRange = '16-' . $lastDayOfMonth;
}
if ($dateRange === '1-15') {
    $startDate = "$month-01";
    $endDate = "$month-15";
} elseif ($dateRange === '16-' . $lastDayOfMonth) {
    $startDate = "$month-16";
    $endDate = date('Y-m-t', strtotime($month));
} else {
    // default full month
    $startDate = "$month-01";
    $endDate = date('Y-m-t', strtotime($month));
}

// Build date range

// Optional location filter
$selectedLocation = isset($_GET['location']) ? $_GET['location'] : '';

// Fetch tardiness records within range with optional location filter
// NOTE: attendance table has no attendance_date/late_minutes/reliever columns.
//       We derive date from DATE(Time_In), compute late vs scheduled shift, and
//       mark reliever based on guard_schedules.shift_type.
$sql = "SELECT 
            DATE(a.time_in) AS date,
            u.user_id,
            CONCAT(u.first_name, ' ', 
                   CASE WHEN u.middle_name IS NOT NULL AND u.middle_name != '' 
                        THEN CONCAT(UPPER(LEFT(u.middle_name,1)), '. ') 
                        ELSE '' END, 
                   u.last_name) AS name,
            a.time_in,
            a.time_out,
            GREATEST(
                0,
                TIMESTAMPDIFF(
                    MINUTE,
                    CASE
                        WHEN TIME(a.time_in) >= '18:00:00' THEN CONCAT(DATE(a.time_in), ' 18:00:00')
                        WHEN TIME(a.time_in) >= '06:00:00' THEN CONCAT(DATE(a.time_in), ' 06:00:00')
                        ELSE CONCAT(DATE(DATE_SUB(a.time_in, INTERVAL 1 DAY)), ' 18:00:00')
                    END,
                    a.time_in
                )
            ) AS late_minutes
        FROM attendance a
        JOIN users u ON a.user_id = u.user_id
        JOIN roles r ON u.role_id = r.role_id
        ";
if (!empty($selectedLocation)) {
    $sql .= " JOIN guard_locations gl ON u.user_id = gl.user_id AND gl.location_name = :location_name AND gl.is_active = 1";
}
$sql .= " WHERE r.role_name = 'Security Guard' 
           AND u.status = 'Active' 
           AND DATE(a.time_in) BETWEEN :start AND :end
          ORDER BY DATE(a.time_in) ASC, u.last_name ASC, u.first_name ASC";
$stmt = $conn->prepare($sql);
if (!empty($selectedLocation)) {
    $stmt->bindParam(':location_name', $selectedLocation);
}
$stmt->bindParam(':start', $startDate);
$stmt->bindParam(':end', $endDate);
$stmt->execute();
$tardiness = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: convert minutes to HH:MM
function minutesToHHMM($minutes) {
    $minutes = max(0, (int)$minutes);
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d', $h, $m);
}

// Fetch current accounting user profile and name (from payroll.php pattern)
$profilePic = '../images/default_profile.png';
$profileName = '';
try {
    $profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
    $profileStmt->execute([$_SESSION['user_id'] ?? 0]);
    $profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if ($profileData) {
        $profileName = trim(($profileData['First_Name'] ?? '') . ' ' . ($profileData['Last_Name'] ?? ''));
        if (!empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
            $profilePic = $profileData['Profile_Pic'];
        }
    }
} catch (Exception $e) {
    // fallback to default values if query fails
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tardiness Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="css/payroll.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/fixedcolumns/4.0.2/css/fixedColumns.dataTables.min.css">
</head>
<body>
    <style>
        .table-hscroll { overflow-x: auto; }
        .table-hscroll::-webkit-scrollbar { height: 12px; }
        .table-hscroll::-webkit-scrollbar-thumb { background-color: #c5c5c5; border-radius: 6px; }
        .table-hscroll::-webkit-scrollbar-track { background-color: #f1f1f1; }
    </style>
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
                    <a href="tardiness.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Tardiness Report">
                        <span class="material-icons">timer</span>
                        <span>Tardiness Report</span>
                    </a>
            </li>
            <li class="nav-item">
                <a href="payroll.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
                    <span class="material-icons">payments</span>
                    <span>Payroll</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payroll_register.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll Register">
                    <span class="material-icons">receipt_long</span>
                    <span>Payroll Register</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="rate_locations.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Users List">
                    <span class="material-icons">attach_money</span>
                    <span>Rate per Locations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="calendar.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
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
            <li class="nav-item">
                <a href="employee_share.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Employee Share">
                    <span class="material-icons">diversity_3</span>
                    <span>Employer Contributions</span>
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
            <div class="user-profile" id="userProfile" data-bs-toggle="modal" data-bs-target="#profileModal">
                <span><?= htmlspecialchars($profileName ?: ($_SESSION['user_id'] ?? '')) ?></span>
                <a href="profile.php"><img src="<?= htmlspecialchars($profilePic) ?>" alt="User Profile"></a>
            </div>
        </div>

        <div class="container-fluid py-3">
            <div class="d-flex align-items-center justify-content-center mb-3">
                <h3 class="mb-0 text-center">Tardiness Report</h3>
            </div>

            <div class="container-fluid py-3 bg-white rounded mb-3">
            <form class="row g-2 align-items-end mb-3 justify-content-center" method="GET" action="">
                <div class="col-md-2 col-6 text-center">
                    <label class="form-label">Month</label>
                    <input type="month" class="form-control" name="month" value="<?= htmlspecialchars($month) ?>">
                </div>
                <div class="col-md-2 col-6 text-center">
                    <label class="form-label">Cutoff Period</label>
                    <?php $secondLabel = '16-' . $lastDayOfMonth; ?>
                    <select class="form-select" name="dateRange">
                        <option value="1-15" <?= $dateRange==='1-15' ? 'selected' : '' ?>>1st - 15th</option>
                        <option value="<?= $secondLabel ?>" <?= $dateRange===$secondLabel ? 'selected' : '' ?>>16th - <?= $lastDayOfMonth ?></option>
                    </select>
                </div>
                <div class="col-md-2 col-6 text-center">
                    <label class="form-label">Location</label>
                    <select class="form-select" name="location">
                        <option value="">All Locations</option>
                        <?php
                        $locationsQuery = "SELECT DISTINCT location_name FROM guard_locations WHERE is_active = 1 ORDER BY location_name";
                        $locationsStmt = $conn->query($locationsQuery);
                        while ($location = $locationsStmt->fetch(PDO::FETCH_ASSOC)) {
                            $sel = ($selectedLocation === $location['location_name']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($location['location_name']) . "' $sel>" . htmlspecialchars($location['location_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2 col-6 d-grid">
                    <label class="form-label" style="visibility:hidden;">Apply</label>
                    <button class="btn btn-success d-flex align-items-center justify-content-center" type="submit">
                        <span class="material-icons">search</span>
                        <span class="ms-1">Apply</span>
                    </button>
                </div>
            </form>
        </div>

            <!-- Action: Export as PDF -->
            <div class="container-fluid mb-3 d-flex justify-content-end gap-2">
                <button type="button" id="btnExportPDF" class="btn btn-danger d-flex align-items-center">
                    <span class="material-icons me-1">picture_as_pdf</span>
                    <span>Export as PDF</span>
                </button>
            </div>

            <div class="bg-white rounded p-3 mb-3">
                <div class="table-responsive table-hscroll">
                <table id="tardinessTable" class="table table-striped table-bordered mb-0">
                    <thead>
                        <tr class="table-primary">
                            <th>Date</th>
                            <th>Name</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Late (HH:MM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalLate = 0;
                        foreach ($tardiness as $row) {
                            $lateMin = (int)($row['late_minutes'] ?? 0);
                            $totalLate += $lateMin;
                            echo '<tr>';
                            echo '<td>'.htmlspecialchars(date('M d, Y', strtotime($row['date']))).'</td>';
                            echo '<td>'.htmlspecialchars($row['name']).'</td>';
                            echo '<td>'.htmlspecialchars($row['time_in'] ?? '').'</td>';
                            echo '<td>'.htmlspecialchars($row['time_out'] ?? '').'</td>';
                            echo '<td title="'.htmlspecialchars($lateMin).' min">'.htmlspecialchars(minutesToHHMM($lateMin)).'</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-secondary">
                            <td colspan="4" class="fw-bold text-end">TOTAL LATE (HH:MM)</td>
                            <td class="fw-bold" title="<?php echo htmlspecialchars($totalLate); ?> min"><?php echo htmlspecialchars(minutesToHHMM($totalLate)); ?></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live date/time
        function updateDateTime() {
            const now = new Date();
            document.getElementById('current-date').textContent = now.toLocaleDateString();
            document.getElementById('current-time').textContent = now.toLocaleTimeString();
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();

        // Sidebar toggle
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('expanded');
        });

        // Export as PDF
        document.getElementById('btnExportPDF').addEventListener('click', function() {
            const params = new URLSearchParams({
                month: document.querySelector('input[name="month"]').value,
                dateRange: document.querySelector('select[name="dateRange"]').value,
                location: document.querySelector('select[name="location"]').value
            });
            window.open('export_tardiness_pdf.php?' + params.toString(), '_blank');
        });
    </script>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
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
            <a href="rate_locations.php" class="mobile-nav-item">
                <span class="material-icons">attach_money</span>
                <span class="mobile-nav-text">Rate Locations</span>
            </a>
            <a href="masterlist.php" class="mobile-nav-item">
                <span class="material-icons">list</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
            <a href="calendar.php" class="mobile-nav-item">
                <span class="material-icons">date_range</span>
                <span class="mobile-nav-text">Calendar</span>
            </a>
            <a href="archives.php" class="mobile-nav-item">
                <span class="material-icons">archive</span>
                <span class="mobile-nav-text">Archives</span>
            </a>
            <a href="logs.php" class="mobile-nav-item">
                <span class="material-icons">receipt_long</span>
                <span class="mobile-nav-text">Logs</span>
            </a>
            <a href="employee_share.php" class="mobile-nav-item">
                <span class="material-icons">diversity_3</span>
                <span class="mobile-nav-text">Employer Contribution</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/fixedcolumns/4.0.2/js/dataTables.fixedColumns.min.js"></script>
<script>
$(function(){
    var table;
    if ($.fn.dataTable) {
        table = $('#registerTable').DataTable({
            paging: true,
            searching: true
        });
    }

    var empSearch = document.getElementById('empSearch');
    if (empSearch && table) {
        empSearch.addEventListener('keyup', function(){ table.search(this.value).draw(); });
        empSearch.addEventListener('change', function(){ table.search(this.value).draw(); });
    }

    function updateDateTime(){
        var now = new Date();
        var dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
        var timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        var dateStr = now.toLocaleDateString('en-PH', dateOptions);
        var timeStr = now.toLocaleTimeString('en-PH', timeOptions);
        var dateEl = document.getElementById('current-date');
        var timeEl = document.getElementById('current-time');
        if (dateEl) dateEl.textContent = dateStr;
        if (timeEl) timeEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    var btnSaveExcel = document.getElementById('btnSaveExcel');
    if (btnSaveExcel) {
        btnSaveExcel.addEventListener('click', function(){
            var params = new URLSearchParams({
                month: '<?= htmlspecialchars($month) ?>',
                dateRange: '<?= htmlspecialchars($dateRange) ?>',
                location: '<?= htmlspecialchars($selectedLocation) ?>'
            }).toString();
            window.open('export_payroll_register_excel.php?' + params, '_blank');
        });
    }
});
</script>
<script src="js/accounting_dashboard.js"></script>
</body>
</html>
