<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

// Enforce OIC role (8)
if (!validateSession($conn, 8)) { exit; }

// Get OIC's profile
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

$oicProfile = (!empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) 
    ? $profileData['Profile_Pic'] 
    : '../images/default_profile.png';

// Get OIC's assigned locations
$oicLocationsQuery = "SELECT location_name FROM oic_locations WHERE oic_user_id = ? AND is_active = 1";
$oicLocationsStmt = $conn->prepare($oicLocationsQuery);
$oicLocationsStmt->execute([$_SESSION['user_id']]);
$oicLocations = $oicLocationsStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($oicLocations)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Scheduling - OIC</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class='container mt-5'>
            <div class="alert alert-warning">
                <h4>No Locations Assigned</h4>
                <p>You are not assigned to any locations. Contact your administrator.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get date range from Month + Cutoff controls (dynamic cutoff default based on today's date)
$periodMonth = isset($_GET['period_month']) ? $_GET['period_month'] : date('Y-m'); // format YYYY-MM
if (isset($_GET['cutoff']) && ($_GET['cutoff'] === '1' || $_GET['cutoff'] === '2')) {
    $cutoff = $_GET['cutoff'];
} else {
    // If viewing current month, choose cutoff by today's day; otherwise default to first cutoff
    $cutoff = ($periodMonth === date('Y-m') && (int)date('d') > 15) ? '2' : '1';
}

// Derive start and end dates based on cutoff
try {
    [$yrStr, $moStr] = explode('-', $periodMonth);
    $year = (int)$yrStr; $month = (int)$moStr;
    $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    if ($cutoff === '2') {
        $startDate = sprintf('%04d-%02d-16', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
    } else {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-15', $year, $month);
        $cutoff = '1';
    }
} catch (Throwable $e) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-15');
    $periodMonth = date('Y-m');
    $cutoff = '1';
}

// View mode toggle: 'schedule' (default) or 'hours'
$viewMode = (isset($_GET['view_mode']) && $_GET['view_mode'] === 'hours') ? 'hours' : 'schedule';

// Create date range
$startDateTime = new DateTime($startDate);
$endDateTime = new DateTime($endDate);
$interval = new DateInterval('P1D');
$dateRange = new DatePeriod($startDateTime, $interval, $endDateTime->modify('+1 day'));

// Determine if selected period is the current active cutoff
$todayYm = date('Y-m');
$todayCutoff = ((int)date('d') <= 15) ? '1' : '2';
$isCurrentPeriod = ($periodMonth === $todayYm) && ($cutoff === $todayCutoff);

// Get guards with LEFT JOIN to guard_schedules for selected period
$locationPlaceholders = str_repeat('?,', count($oicLocations) - 1) . '?';

$guardsQuery = "
    SELECT DISTINCT
        u.User_ID,
        u.employee_id,
        u.First_Name,
        u.Last_Name,
        u.middle_name,
        gl.location_name
    FROM users u
    INNER JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_active = 1
    WHERE u.Role_ID = 5 
    AND u.status = 'Active'
    AND u.archived_at IS NULL
    AND NOT EXISTS (SELECT 1 FROM archived_guards ag WHERE ag.user_id = u.User_ID)
    AND gl.location_name IN ($locationPlaceholders)
    ORDER BY gl.location_name, u.Last_Name, u.First_Name
";

$guardsStmt = $conn->prepare($guardsQuery);
$guardsStmt->execute($oicLocations);
$guards = $guardsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance data
$attendanceByUser = [];
if (!empty($guards)) {
    $guardIds = array_column($guards, 'User_ID');
    $attendanceQuery = "
        SELECT 
            User_ID,
            DATE(Time_In) as attendance_date,
            Time_In,
            Time_Out,
            Hours_Worked
        FROM attendance
        WHERE User_ID IN (" . implode(',', $guardIds) . ")
        AND DATE(Time_In) BETWEEN ? AND ?
        ORDER BY User_ID, attendance_date
    ";
    $attendanceStmt = $conn->prepare($attendanceQuery);
    $attendanceStmt->execute([$startDate, $endDate]);
    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($attendanceRecords as $record) {
        $userId = $record['User_ID'];
        $date = $record['attendance_date'];
        if (!isset($attendanceByUser[$userId])) {
            $attendanceByUser[$userId] = [];
        }
        $attendanceByUser[$userId][$date] = $record;
    }
}

// Get schedules (with error handling if table doesn't exist)
$scheduleByUser = [];
if (!empty($guards)) {
    try {
        $guardIds = array_column($guards, 'User_ID');
        $scheduleQuery = "
            SELECT 
                user_id,
                schedule_date,
                shift_type,
                hours_scheduled,
                notes
            FROM guard_schedules
            WHERE user_id IN (" . implode(',', $guardIds) . ")
            AND schedule_date BETWEEN ? AND ?
            ORDER BY user_id, schedule_date
        ";
        $scheduleStmt = $conn->prepare($scheduleQuery);
        $scheduleStmt->execute([$startDate, $endDate]);
        $scheduleRecords = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($scheduleRecords as $record) {
            $userId = $record['user_id'];
            $date = $record['schedule_date'];
            if (!isset($scheduleByUser[$userId])) {
                $scheduleByUser[$userId] = [];
            }
            $scheduleByUser[$userId][$date] = $record;
        }
    } catch (PDOException $e) {
        // Table doesn't exist yet - that's okay
        $scheduleByUser = [];
    }
}

// Get holidays
$holidaysQuery = "SELECT holiday_date, holiday_name FROM holidays WHERE holiday_date BETWEEN ? AND ?";
$holidaysStmt = $conn->prepare($holidaysQuery);
$holidaysStmt->execute([$startDate, $endDate]);
$holidays = $holidaysStmt->fetchAll(PDO::FETCH_ASSOC);

$holidayDates = [];
foreach ($holidays as $holiday) {
    $holidayDates[$holiday['holiday_date']] = $holiday['holiday_name'];
}

// Helper: compute hours from attendance times (in hours, float)
function computeHoursFromAttendance(?string $timeIn, ?string $timeOut): float {
    if (empty($timeIn) || empty($timeOut)) return 0.0;
    $inTs = strtotime($timeIn);
    $outTs = strtotime($timeOut);
    if ($inTs === false || $outTs === false) return 0.0;
    $diff = $outTs - $inTs;
    if ($diff <= 0) return 0.0;
    return $diff / 3600.0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduling - OIC - Green Meadows Security Agency</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="css/oic_dashboard.css">
    <link rel="stylesheet" href="css/scheduling.css">
    <style>
        /* Print only the schedule table and fit to bond paper */
        @media print {
            /* Long bond paper in landscape */
            @page { size: legal landscape; margin: 6mm; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            /* Hide everything by default */
            body * { visibility: hidden; }
            /* Show only the schedule table container */
            .schedule-table-container, .schedule-table-container * { visibility: visible; }
            .schedule-table-container { position: absolute; top: 0; left: 0; width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; overflow: visible !important; }

            /* Compact table to fit width */
            .schedule-table { width: 100% !important; max-width: 100% !important; table-layout: fixed; border-collapse: collapse; font-size: 8.5px; }
            .schedule-table th, .schedule-table td { padding: 4px 6px; }
            .location-row td, .shift-section-row td { padding: 6px; }
            .guard-name-header { width: 140px; }
            .guard-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .schedule-table thead th { position: static; }
            .guard-row, .location-row, .shift-section-row { page-break-inside: avoid; }

            /* Final safety: scale down slightly to avoid clipping on some printers */
            .schedule-table-container { zoom: 0.90; transform: scale(0.90); transform-origin: top left; }
        }
    </style>
</head>
<body>
    <?php include '../global/oic_sidebar.php'; ?>

    <div class="main-content" id="main-content">
        <!-- Header -->
        <div class="header">
            <button class="toggle-sidebar" id="toggleSidebar">
                <span class="material-icons">menu</span>
            </button>
            <div class="current-datetime ms-3 d-none d-md-block">
                <span id="current-date"></span> | <span id="current-time"></span>
            </div>
            <a href="profile.php" class="user-profile" style="color:black; text-decoration:none;">
                <span><?php echo $profileData['First_Name'] . ' ' . $profileData['Last_Name']; ?></span>
                <img src="<?php echo $oicProfile; ?>" alt="Profile">
            </a>
        </div>

        <!-- Content -->
        <div class="schedule-container">
            <div class="schedule-header">
                <h1><span class="material-icons align-middle me-2">event_note</span>Daily Time Record</h1>
                <p>GREEN MEADOWS SECURITY AGENCY, INC.</p>
            </div>

            <!-- Period Selector -->
            <div class="period-selector">
                <form method="GET" id="periodForm">
                    <div class="schedule-controls">
                        <div class="form-group">
                            <label for="period_month">Month</label>
                            <input type="month" class="form-control" id="period_month" name="period_month" 
                                   value="<?php echo htmlspecialchars($periodMonth); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="cutoff">Cutoff</label>
                            <?php $lastDayUi = cal_days_in_month(CAL_GREGORIAN, (int)date('m', strtotime($periodMonth.'-01')), (int)date('Y', strtotime($periodMonth.'-01'))); ?>
                            <select class="form-select" id="cutoff" name="cutoff" required>
                                <option value="1" <?php echo ($cutoff==='1')?'selected':''; ?>>1-15</option>
                                <option value="2" <?php echo ($cutoff==='2')?'selected':''; ?>><?php echo '16-' . $lastDayUi; ?></option>
                            </select>
                        </div>
                        <input type="hidden" name="view_mode" id="view_mode" value="<?php echo htmlspecialchars($viewMode); ?>">
                        <div>
                            <button type="submit" class="btn btn-generate">
                                <span class="material-icons align-middle me-1">search</span>
                                Generate Schedule
                            </button>
                        </div>
                        <div>
                            <button type="button" id="createScheduleBtn" class="btn btn-export" onclick="openScheduleModal()" <?php echo $isCurrentPeriod ? '' : 'disabled title="Editing disabled for non-current periods"'; ?>>
                                <span class="material-icons align-middle me-1">edit_calendar</span>
                                Create Schedule
                            </button>
                        </div>
                        <div>
                            <button type="button" class="btn btn-export" onclick="window.print()">
                                <span class="material-icons align-middle me-1">print</span>
                                Print
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php if (!$isCurrentPeriod): ?>
            <div class="alert alert-warning mt-2" role="alert">
                You are viewing a non-current period (<?php echo htmlspecialchars($periodMonth); ?>, cutoff <?php echo $cutoff==='1' ? '1-15' : '16-' . cal_days_in_month(CAL_GREGORIAN, (int)substr($periodMonth,5,2), (int)substr($periodMonth,0,4)); ?>). Editing and schedule creation are disabled.
            </div>
            <?php endif; ?>

            <!-- Locations -->
            <div class="location-info">
                <h5><span class="material-icons align-middle me-2">place</span>Your Assigned Locations</h5>
                <?php foreach ($oicLocations as $location): ?>
                    <span class="location-badge"><?php echo htmlspecialchars($location); ?></span>
                <?php endforeach; ?>
            </div>

            <!-- Legend -->
            <div class="shift-legend">
                <h6>Legend</h6>
                <div class="legend-items">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #d4edda;"></div>
                        <span>Present (Hours Worked)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #fff3cd;"></div>
                        <span>Day Shift Scheduled</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #cfe2ff;"></div>
                        <span>Night Shift Scheduled</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f8d7da;"></div>
                        <span>Rest Day</span>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="badge bg-secondary">View: <?php echo ($viewMode==='hours') ? 'Hours' : 'Schedule'; ?></span>
                    <div class="btn-group btn-group-sm ms-2" role="group" aria-label="View Toggle">
                        <button type="button" class="btn <?php echo ($viewMode==='schedule') ? 'btn-success' : 'btn-outline-success'; ?>" onclick="setViewMode('schedule')">Show Schedule</button>
                        <button type="button" class="btn <?php echo ($viewMode==='hours') ? 'btn-success' : 'btn-outline-success'; ?>" onclick="setViewMode('hours')">Show Hours</button>
                    </div>
                </div>
            </div>

            <!-- Schedule Table -->
            <div class="schedule-table-container" id="scheduleTableRoot">
                <?php if (!empty($guards)): ?>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th class="guard-name-header" rowspan="2">NAME</th>
                            <?php 
                            $days = [];
                            foreach ($dateRange as $date): 
                                $days[] = $date;
                            ?>
                            <th class="shift-header"><?php echo $date->format('D'); ?><br><?php echo $date->format('d'); ?></th>
                            <?php endforeach; ?>
                            <th class="shift-header" rowspan="2">Total Days</th>
                            <th class="shift-header" rowspan="2">Total Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $currentLocation = '';
                        $grandTotalDays = 0;
                        $grandTotalHours = 0;
                        
                        // Group guards by location and shift
                        $guardsByLocationAndShift = [];
                        foreach ($guards as $guard) {
                            $location = $guard['location_name'];
                            if (!isset($guardsByLocationAndShift[$location])) {
                                $guardsByLocationAndShift[$location] = [
                                    'Day Shift' => [],
                                    'Night Shift' => [],
                                    'Reliever' => []
                                ];
                            }
                            
                            // Determine primary shift based on schedules
                            $primaryShift = 'Day Shift'; // Default
                            $shiftCounts = ['Day Shift' => 0, 'Night Shift' => 0, 'Reliever' => 0];
                            
                            if (isset($scheduleByUser[$guard['User_ID']])) {
                                foreach ($scheduleByUser[$guard['User_ID']] as $schedule) {
                                    $shiftType = $schedule['shift_type'];
                                    if (isset($shiftCounts[$shiftType])) {
                                        $shiftCounts[$shiftType]++;
                                    }
                                }
                                // Get shift with most occurrences
                                $primaryShift = array_search(max($shiftCounts), $shiftCounts);
                            }
                            
                            $guardsByLocationAndShift[$location][$primaryShift][] = $guard;
                        }
                        
                        foreach ($guardsByLocationAndShift as $location => $shifts):
                        ?>
                        <!-- Location Header -->
                        <tr class="location-row">
                            <td colspan="<?php echo count($days) + 3; ?>" class="location-cell">
                                <span class="material-icons">place</span>
                                <?php echo htmlspecialchars($location); ?>
                            </td>
                        </tr>
                        
                        <!-- DAY SHIFT Section -->
                        <?php if (!empty($shifts['Day Shift'])): ?>
                        <tr class="shift-section-row">
                            <td colspan="<?php echo count($days) + 3; ?>" style="background: #fff3cd; padding: 8px; font-weight: bold; color: #856404; text-align: left;">
                                <span class="material-icons align-middle" style="font-size: 18px;">wb_sunny</span>
                                DAY SHIFT
                            </td>
                        </tr>
                        <?php 
                        foreach ($shifts['Day Shift'] as $guard):
                            $totalDays = 0;
                            $totalHours = 0;
                        ?>
                        <tr class="guard-row">
                            <td class="guard-name">
                                <?php echo htmlspecialchars($guard['employee_id']); ?><br>
                                <strong><?php echo htmlspecialchars($guard['Last_Name'] . ', ' . $guard['First_Name']); ?></strong>
                            </td>
                            <?php 
                            foreach ($days as $date):
                                $dateStr = $date->format('Y-m-d');
                                $hasAttendance = isset($attendanceByUser[$guard['User_ID']][$dateStr]);
                                $hasSchedule = isset($scheduleByUser[$guard['User_ID']][$dateStr]);

                                // Always compute totals from attendance regardless of view
                                $hours = 0.0;
                                if ($hasAttendance) {
                                    $rec = $attendanceByUser[$guard['User_ID']][$dateStr];
                                    $hours = computeHoursFromAttendance($rec['Time_In'], $rec['Time_Out']);
                                    $totalDays++;
                                    $totalHours += $hours;
                                }

                                if ($viewMode === 'hours') {
                                    if ($hasAttendance) {
                                        echo '<td class="attendance-cell" style="background: #d4edda; text-align: center;" title="Attendance recorded">' . round($hours) . '</td>';
                                    } elseif (!$hasSchedule && $isCurrentPeriod) {
                                        echo '<td class="add-schedule-cell" style="cursor:pointer;" title="Add schedule" onclick="openAddScheduleModal(' . (int)$guard['User_ID'] . ',\'' . $dateStr . '\')"></td>';
                                    } else {
                                        echo '<td></td>';
                                    }
                                } else { // schedule view
                                    if ($hasSchedule) {
                                        $shift = $scheduleByUser[$guard['User_ID']][$dateStr]['shift_type'];
                                        $display = '';
                                        $bg = '';
                                        if ($shift === 'Day Shift') { $display = 'DS'; $bg = '#fff3cd'; }
                                        elseif ($shift === 'Rest Day') { $display = 'RD'; $bg = '#f8d7da'; }
                                        elseif ($shift === 'Night Shift') { $display = 'NS'; $bg = '#cfe2ff'; }
                                        elseif ($shift === 'Reliever') { $display = 'R'; $bg = '#e7f3ef'; }
                                        if ($display) {
                                            if ($isCurrentPeriod) {
                                                echo '<td class="schedule-cell" style="background: ' . $bg . '; text-align: center; cursor:pointer;" ' .
                                                     'onclick="openEditScheduleModal(' . (int)$guard['User_ID'] . ',\'' . $dateStr . '\',\'' . htmlspecialchars($shift, ENT_QUOTES) . '\')" ' .
                                                     'title="Edit schedule">' . $display . '</td>';
                                            } else {
                                                echo '<td class="schedule-cell" style="background: ' . $bg . '; text-align: center;" title="Past cutoff - view only">' . $display . '</td>';
                                            }
                                        } else { echo '<td></td>'; }
                                    } elseif ($isCurrentPeriod) {
                                        echo '<td class="add-schedule-cell" style="cursor:pointer;" title="Add schedule" onclick="openAddScheduleModal(' . (int)$guard['User_ID'] . ',\'' . $dateStr . '\')"></td>';
                                    } else {
                                        echo '<td></td>';
                                    }
                                }
                            endforeach;
                            
                            $grandTotalDays += $totalDays;
                            $grandTotalHours += $totalHours;
                            ?>
                            <td class="totals-cell"><?php echo $totalDays; ?></td>
                            <td class="totals-cell"><strong><?php echo round($totalHours); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- NIGHT SHIFT Section -->
                        <?php if (!empty($shifts['Night Shift'])): ?>
                        <tr class="shift-section-row">
                            <td colspan="<?php echo count($days) + 3; ?>" style="background: #333; padding: 8px; font-weight: bold; color: white; text-align: left;">
                                <span class="material-icons align-middle" style="font-size: 18px;">nightlight</span>
                                NIGHT SHIFT
                            </td>
                        </tr>
                        <?php 
                        foreach ($shifts['Night Shift'] as $guard):
                            $totalDays = 0;
                            $totalHours = 0;
                        ?>
                        <tr class="guard-row">
                            <td class="guard-name">
                                <?php echo htmlspecialchars($guard['employee_id']); ?><br>
                                <strong><?php echo htmlspecialchars($guard['Last_Name'] . ', ' . $guard['First_Name']); ?></strong>
                            </td>
                            <?php 
                            foreach ($days as $date):
                                $dateStr = $date->format('Y-m-d');
                                $hasAttendance = isset($attendanceByUser[$guard['User_ID']][$dateStr]);
                                $hasSchedule = isset($scheduleByUser[$guard['User_ID']][$dateStr]);

                                // Always compute totals from attendance regardless of view
                                $hours = 0.0;
                                if ($hasAttendance) {
                                    $rec = $attendanceByUser[$guard['User_ID']][$dateStr];
                                    $hours = computeHoursFromAttendance($rec['Time_In'], $rec['Time_Out']);
                                    $totalDays++;
                                    $totalHours += $hours;
                                }

                                if ($viewMode === 'hours') {
                                    if ($hasAttendance) {
                                        echo '<td class="attendance-cell" style="background: #d4edda; text-align: center;" title="Attendance recorded">' . round($hours) . '</td>';
                                    } elseif (!$hasSchedule && $isCurrentPeriod) {
                                        echo '<td class="add-schedule-cell" style="cursor:pointer;" title="Add schedule" onclick="openAddScheduleModal(' . (int)$guard['User_ID'] . ',\'' . $dateStr . '\')"></td>';
                                    } else {
                                        echo '<td></td>';
                                    }
                                } else { // schedule view
                                    if ($hasSchedule) {
                                        $shift = $scheduleByUser[$guard['User_ID']][$dateStr]['shift_type'];
                                        $display = '';
                                        $bg = '';
                                        if ($shift === 'Day Shift') { $display = 'DS'; $bg = '#fff3cd'; }
                                        elseif ($shift === 'Rest Day') { $display = 'RD'; $bg = '#f8d7da'; }
                                        elseif ($shift === 'Night Shift') { $display = 'NS'; $bg = '#cfe2ff'; }
                                        elseif ($shift === 'Reliever') { $display = 'R'; $bg = '#e7f3ef'; }
                                        if ($display) {
                                            if ($isCurrentPeriod) {
                                                echo '<td class="schedule-cell" style="background: ' . $bg . '; text-align: center; cursor:pointer;" ' .
                                                     'onclick="openEditScheduleModal(' . (int)$guard['User_ID'] . ',\'' . $dateStr . '\',\'' . htmlspecialchars($shift, ENT_QUOTES) . '\')" ' .
                                                     'title="Edit schedule">' . $display . '</td>';
                                            } else {
                                                echo '<td class="schedule-cell" style="background: ' . $bg . '; text-align: center;" title="Past cutoff - view only">' . $display . '</td>';
                                            }
                                        } else { echo '<td></td>'; }
                                    } elseif ($isCurrentPeriod) {
                                        echo '<td class="add-schedule-cell" style="cursor:pointer;" title="Add schedule" onclick="openAddScheduleModal(' . (int)$guard['User_ID'] . ',\'' . $dateStr . '\')"></td>';
                                    } else {
                                        echo '<td></td>';
                                    }
                                }
                            endforeach;
                            
                            $grandTotalDays += $totalDays;
                            $grandTotalHours += $totalHours;
                            ?>
                            <td class="totals-cell"><?php echo $totalDays; ?></td>
                            <td class="totals-cell"><strong><?php echo round($totalHours); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- RELIEVER Section -->
                        <?php if (!empty($shifts['Reliever'])): ?>
                        <tr class="shift-section-row">
                            <td colspan="<?php echo count($days) + 3; ?>" style="background: #e7f3ef; padding: 8px; font-weight: bold; color: #0f5132; text-align: left;">
                                <span class="material-icons align-middle" style="font-size: 18px;">swap_horiz</span>
                                RELIEVER
                            </td>
                        </tr>
                        <?php 
                        foreach ($shifts['Reliever'] as $guard):
                            $totalDays = 0;
                            $totalHours = 0;
                        ?>
                        <tr class="guard-row">
                            <td class="guard-name">
                                <?php echo htmlspecialchars($guard['employee_id']); ?><br>
                                <strong><?php echo htmlspecialchars($guard['Last_Name'] . ', ' . $guard['First_Name']); ?></strong>
                            </td>
                            <?php 
                            foreach ($days as $date):
                                $dateStr = $date->format('Y-m-d');
                                $hasAttendance = isset($attendanceByUser[$guard['User_ID']][$dateStr]);
                                $hasSchedule = isset($scheduleByUser[$guard['User_ID']][$dateStr]);

                                // Always compute totals from attendance regardless of view
                                $hours = 0.0;
                                if ($hasAttendance) {
                                    $rec = $attendanceByUser[$guard['User_ID']][$dateStr];
                                    $hours = computeHoursFromAttendance($rec['Time_In'], $rec['Time_Out']);
                                    $totalDays++;
                                    $totalHours += $hours;
                                }

                                if ($viewMode === 'hours') {
                                    if ($hasAttendance) {
                                        echo '<td class="attendance-cell" style="background: #d4edda; text-align: center;" title="Attendance recorded">' . round($hours) . '</td>';
                                    } elseif (!$hasSchedule && $isCurrentPeriod) {
                                        echo '<td class="add-schedule-cell" style="cursor:pointer;" title="Add schedule" onclick="openAddScheduleModal(' . (int)$guard['User_ID'] . ',\'' . $dateStr . '\')"></td>';
                                    } else {
                                        echo '<td></td>';
                                    }
                                } else { // schedule view
                                    if ($hasSchedule) {
                                        $shift = $scheduleByUser[$guard['User_ID']][$dateStr]['shift_type'];
                                        $display = '';
                                        $bg = '';
                                        if ($shift === 'Day Shift') { $display = 'DS'; $bg = '#fff3cd'; }
                                        elseif ($shift === 'Rest Day') { $display = 'RD'; $bg = '#f8d7da'; }
                                        elseif ($shift === 'Night Shift') { $display = 'NS'; $bg = '#cfe2ff'; }
                                        elseif ($shift === 'Reliever') { $display = 'R'; $bg = '#e7f3ef'; }
                                        if ($display) {
                                            if ($isCurrentPeriod) {
                                                echo '<td class="schedule-cell" style="background: ' . $bg . '; text-align: center; cursor:pointer;" ' .
                                                     'onclick="openEditScheduleModal(' . (int)$guard['User_ID'] . ',\'' . $dateStr . '\',\'' . htmlspecialchars($shift, ENT_QUOTES) . '\')" ' .
                                                     'title="Edit schedule">' . $display . '</td>';
                                            } else {
                                                echo '<td class="schedule-cell" style="background: ' . $bg . '; text-align: center;" title="Past cutoff - view only">' . $display . '</td>';
                                            }
                                        } else { echo '<td></td>'; }
                                    } elseif ($isCurrentPeriod) {
                                        echo '<td class="add-schedule-cell" style="cursor:pointer;" title="Add schedule" onclick="openAddScheduleModal(' . (int)$guard['User_ID'] . ',\'' . $dateStr . '\')"></td>';
                                    } else {
                                        echo '<td></td>';
                                    }
                                }
                            endforeach;
                            
                            $grandTotalDays += $totalDays;
                            $grandTotalHours += $totalHours;
                            ?>
                            <td class="totals-cell"><?php echo $totalDays; ?></td>
                            <td class="totals-cell"><strong><?php echo round($totalHours); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php endforeach; ?>
                        
                        <!-- Grand Total Row -->
                        <tr class="grand-total-row">
                            <td colspan="<?php echo count($days) + 1; ?>" class="grand-total-label">
                                <strong>GRAND TOTAL</strong>
                            </td>
                            <td class="grand-total-number"><?php echo $grandTotalDays; ?></td>
                            <td class="grand-total-number"><strong><?php echo round($grandTotalHours); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-info">
                    <h5>No Guards Found</h5>
                    <p>No active guards assigned to: <strong><?php echo implode(', ', $oicLocations); ?></strong></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: #2a7d4f; color: white;">
                    <h5 class="modal-title">
                        <span class="material-icons align-middle me-2">edit_calendar</span>
                        Create Guard Schedule
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="scheduleForm">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label>Start Date</label>
                                <input type="date" class="form-control" id="scheduleStartDate" required>
                            </div>
                            <div class="col-md-4">
                                <label>End Date</label>
                                <input type="date" class="form-control" id="scheduleEndDate" required>
                            </div>
                            <div class="col-md-4">
                                <label>Shift Type</label>
                                <select class="form-select" id="scheduleShiftType" required>
                                    <option value="">Select Shift</option>
                                    <option value="Day Shift">Day Shift</option>
                                    <option value="Night Shift">Night Shift</option>
                                    <option value="Reliever">Reliever</option>
                                    <option value="Rest Day">Rest Day</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>Select Guards</label>
                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <?php 
                                $currentLoc = '';
                                foreach ($guards as $guard):
                                    if ($currentLoc !== $guard['location_name']):
                                        $currentLoc = $guard['location_name'];
                                ?>
                                <div class="fw-bold text-success mt-2 mb-2">
                                    <span class="material-icons align-middle" style="font-size: 16px;">place</span>
                                    <?php echo htmlspecialchars($currentLoc); ?>
                                </div>
                                <?php endif; ?>
                                <div class="form-check">
                                    <input class="form-check-input guard-checkbox" type="checkbox" 
                                           value="<?php echo $guard['User_ID']; ?>" 
                                           data-location="<?php echo htmlspecialchars($guard['location_name']); ?>"
                                           id="guard_<?php echo $guard['User_ID']; ?>">
                                    <label class="form-check-label" for="guard_<?php echo $guard['User_ID']; ?>">
                                        <?php echo htmlspecialchars($guard['employee_id'] . ' - ' . $guard['Last_Name'] . ', ' . $guard['First_Name']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="selectAllGuards()">
                                Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                                Clear Selection
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="saveSchedule()">
                        <span class="material-icons align-middle me-1" style="font-size: 16px;">save</span>
                        Save Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:#2a7d4f; color:#fff;">
                    <h5 class="modal-title"><span class="material-icons align-middle me-1" style="font-size:20px;">edit</span>Edit Schedule</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editScheduleForm">
                        <input type="hidden" id="editScheduleUserId">
                        <input type="hidden" id="editScheduleDate">
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <div id="editScheduleDateDisplay" class="fw-semibold"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Shift</label>
                            <div id="editScheduleCurrentShift" class="badge bg-secondary"></div>
                        </div>
                        <div class="mb-3">
                            <label for="editShiftType" class="form-label">New Shift Type</label>
                            <select class="form-select" id="editShiftType" required>
                                <option value="">Select Shift</option>
                                <option value="Day Shift">Day Shift</option>
                                <option value="Night Shift">Night Shift</option>
                                <option value="Reliever">Reliever</option>
                                <option value="Rest Day">Rest Day</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editScheduleNotes" class="form-label">Notes (optional)</label>
                            <textarea class="form-control" id="editScheduleNotes" rows="2" maxlength="255"></textarea>
                        </div>
                    </form>
                    <div class="alert alert-info py-2" style="font-size:12px;">
                        Attendance conflicts prevent switching to Rest Day and enforce overnight rules for Night vs Day shifts.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger me-auto" onclick="deleteSchedule()"><span class="material-icons align-middle" style="font-size:16px;">delete</span> Delete</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitScheduleEdit()"><span class="material-icons align-middle" style="font-size:16px;">save</span> Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Single Schedule Modal -->
    <div class="modal fade" id="addSingleScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:#2a7d4f; color:#fff;">
                    <h5 class="modal-title"><span class="material-icons align-middle me-1" style="font-size:20px;">add</span>Add Schedule (Single Day)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addSingleScheduleForm">
                        <input type="hidden" id="addScheduleUserId">
                        <input type="hidden" id="addScheduleDate">
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <div id="addScheduleDateDisplay" class="fw-semibold"></div>
                        </div>
                        <div class="mb-3">
                            <label for="addShiftType" class="form-label">Shift Type</label>
                            <select class="form-select" id="addShiftType" required>
                                <option value="">Select Shift</option>
                                <option value="Day Shift">Day Shift</option>
                                <option value="Night Shift">Night Shift</option>
                                <option value="Reliever">Reliever</option>
                                <option value="Rest Day">Rest Day</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="addScheduleNotes" class="form-label">Notes (optional)</label>
                            <textarea class="form-control" id="addScheduleNotes" rows="2" maxlength="255"></textarea>
                        </div>
                    </form>
                    <div class="alert alert-info py-2" style="font-size:12px;">
                        You can add a schedule for any day within the currently selected month.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="saveSingleSchedule()"><span class="material-icons align-middle" style="font-size:16px;">save</span> Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            const toggleBtn = document.getElementById('toggleSidebar');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            
            if (toggleBtn && sidebar && mainContent) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');
                });
            }

            // Current date and time
            function updateDateTime() {
                const now = new Date();
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const dateEl = document.getElementById('current-date');
                const timeEl = document.getElementById('current-time');
                
                if (dateEl && timeEl) {
                    dateEl.textContent = now.toLocaleDateString('en-US', options);
                    timeEl.textContent = now.toLocaleTimeString('en-US');
                }
            }
            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Period selector validation (Month + Cutoff)
            const periodForm = document.getElementById('periodForm');
            if (periodForm) {
                periodForm.addEventListener('submit', function(e) {
                    const monthField = document.getElementById('period_month');
                    const cutoffField = document.getElementById('cutoff');
                    if (!monthField.value || !cutoffField.value) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Missing Selection',
                            text: 'Please select a month and cutoff.',
                            confirmButtonColor: '#2a7d4f'
                        });
                    }
                });
            }

            // Location filter removed; OIC can only schedule their assigned guards
            if (window.location.hash === '#scheduleTableRoot') {
                const target = document.getElementById('scheduleTableRoot');
                if (target) {
                    setTimeout(()=>{ target.scrollIntoView({behavior:'smooth', block:'start'}); }, 50);
                }
            }
        });

        function setViewMode(mode) {
            const vm = document.getElementById('view_mode');
            const monthField = document.getElementById('period_month');
            const cutoffField = document.getElementById('cutoff');
            if (vm && monthField && cutoffField) {
                const url = new URL(window.location.href);
                url.searchParams.set('period_month', monthField.value);
                url.searchParams.set('cutoff', cutoffField.value);
                url.searchParams.set('view_mode', mode);
                url.hash = 'scheduleTableRoot';
                window.location.replace(url.toString());
            }
        }

        function openScheduleModal() {
            const isDisabled = <?php echo $isCurrentPeriod ? 'false' : 'true'; ?>;
            if (isDisabled) {
                Swal.fire({
                    icon: 'info',
                    title: 'Editing Disabled',
                    text: 'You are viewing a non-current period. Editing and schedule creation are disabled.',
                    confirmButtonColor: '#2a7d4f'
                });
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
            modal.show();
        }

        function selectAllGuards() {
            document.querySelectorAll('.guard-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function clearSelection() {
            document.querySelectorAll('.guard-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        function saveSchedule() {
            const startDate = document.getElementById('scheduleStartDate').value;
            const endDate = document.getElementById('scheduleEndDate').value;
            const shiftType = document.getElementById('scheduleShiftType').value;
            
            if (!startDate || !endDate || !shiftType) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Form',
                    text: 'Please fill in all required fields',
                    confirmButtonColor: '#2a7d4f'
                });
                return;
            }

            // Allow past days inside the current month; validation handled server-side for month scope

            const selectedGuards = [];
            document.querySelectorAll('.guard-checkbox:checked').forEach(checkbox => {
                selectedGuards.push(checkbox.value);
            });

            if (selectedGuards.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Guards Selected',
                    text: 'Please select at least one guard',
                    confirmButtonColor: '#2a7d4f'
                });
                return;
            }

            // Show confirmation
            Swal.fire({
                title: 'Confirm Schedule',
                html: `
                    <p>Create schedules for:</p>
                    <ul style="text-align: left;">
                        <li><strong>Guards:</strong> ${selectedGuards.length} selected</li>
                        <li><strong>Period:</strong> ${startDate} to ${endDate}</li>
                        <li><strong>Shift:</strong> ${shiftType}</li>
                    </ul>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2a7d4f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Create Schedule',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'bulk_assign');
                    formData.append('user_ids', JSON.stringify(selectedGuards));
                    formData.append('start_date', startDate);
                    formData.append('end_date', endDate);
                    formData.append('shift_type', shiftType);

                    fetch('process_schedule.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message,
                                confirmButtonColor: '#2a7d4f'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonColor: '#2a7d4f'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to save schedule: ' + error,
                            confirmButtonColor: '#2a7d4f'
                        });
                    });
                }
            });
        }
    </script>
    <script>
        function openEditScheduleModal(userId, dateStr, currentShift) {
            const modalEl = document.getElementById('editScheduleModal');
            if (!modalEl) return;
            const isDisabled = <?php echo $isCurrentPeriod ? 'false' : 'true'; ?>;
            if (isDisabled) {
                Swal.fire({
                    icon: 'info',
                    title: 'Editing Disabled',
                    text: 'This cutoff period has ended. Editing schedules is only allowed for the active cutoff.',
                    confirmButtonColor: '#2a7d4f'
                });
                return;
            }
            document.getElementById('editScheduleUserId').value = userId;
            document.getElementById('editScheduleDate').value = dateStr;
            document.getElementById('editScheduleDateDisplay').textContent = dateStr;
            document.getElementById('editScheduleCurrentShift').textContent = currentShift;
            document.getElementById('editShiftType').value = '';
            document.getElementById('editScheduleNotes').value = '';
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
        }
        function openAddScheduleModal(userId, dateStr) {
            const isDisabled = <?php echo $isCurrentPeriod ? 'false' : 'true'; ?>;
            if (isDisabled) {
                Swal.fire({icon:'info', title:'Editing Disabled', text:'Cannot add schedule in a non-current period.', confirmButtonColor:'#2a7d4f'});
                return;
            }
            const modalEl = document.getElementById('addSingleScheduleModal');
            if (!modalEl) return;
            document.getElementById('addScheduleUserId').value = userId;
            document.getElementById('addScheduleDate').value = dateStr;
            document.getElementById('addScheduleDateDisplay').textContent = dateStr;
            document.getElementById('addShiftType').value = '';
            document.getElementById('addScheduleNotes').value = '';
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
        }
        function saveSingleSchedule() {
            const userId = document.getElementById('addScheduleUserId').value;
            const dateStr = document.getElementById('addScheduleDate').value;
            const shiftType = document.getElementById('addShiftType').value;
            const notes = document.getElementById('addScheduleNotes').value.trim();
            if (!userId || !dateStr || !shiftType) {
                Swal.fire({icon:'warning', title:'Missing Data', text:'Select a shift before saving.', confirmButtonColor:'#2a7d4f'});
                return;
            }
            const fd = new FormData();
            fd.append('action','add_single_schedule');
            fd.append('user_id', userId);
            fd.append('schedule_date', dateStr);
            fd.append('shift_type', shiftType);
            fd.append('notes', notes);
            fetch('process_schedule.php',{method:'POST', body: fd})
                .then(r=>r.json())
                .then(data=>{
                    if (data.success) {
                        Swal.fire({icon:'success', title:'Added', text:data.message, confirmButtonColor:'#2a7d4f'}).then(()=>window.location.reload());
                    } else {
                        Swal.fire({icon:'error', title:'Error', text:data.message, confirmButtonColor:'#2a7d4f'});
                    }
                })
                .catch(err=>{
                    Swal.fire({icon:'error', title:'Error', text:'Request failed: '+err, confirmButtonColor:'#2a7d4f'});
                });
        }
        function submitScheduleEdit() {
            const userId = document.getElementById('editScheduleUserId').value;
            const dateStr = document.getElementById('editScheduleDate').value;
            const newShift = document.getElementById('editShiftType').value;
            const notes = document.getElementById('editScheduleNotes').value.trim();
            if (!userId || !dateStr || !newShift) {
                Swal.fire({icon:'warning', title:'Missing Data', text:'Please select a new shift.', confirmButtonColor:'#2a7d4f'});
                return;
            }
            const fd = new FormData();
            fd.append('action','update_schedule');
            fd.append('user_id', userId);
            fd.append('schedule_date', dateStr);
            fd.append('shift_type', newShift);
            fd.append('notes', notes);
            fetch('process_schedule.php',{method:'POST', body: fd})
                .then(r=>r.json())
                .then(data=>{
                    if (data.success) {
                        Swal.fire({icon:'success', title:'Updated', text:data.message, confirmButtonColor:'#2a7d4f'}).then(()=>window.location.reload());
                    } else {
                        Swal.fire({icon:'error', title:'Error', text:data.message, confirmButtonColor:'#2a7d4f'});
                    }
                })
                .catch(err=>{
                    Swal.fire({icon:'error', title:'Error', text:'Request failed: '+err, confirmButtonColor:'#2a7d4f'});
                });
        }
        function deleteSchedule() {
            const userId = document.getElementById('editScheduleUserId').value;
            const dateStr = document.getElementById('editScheduleDate').value;
            if (!userId || !dateStr) {
                Swal.fire({icon:'warning', title:'Missing Data', text:'Cannot determine schedule to delete.', confirmButtonColor:'#2a7d4f'});
                return;
            }
            Swal.fire({
                icon: 'warning',
                title: 'Delete this schedule?',
                text: `This will remove the schedule on ${dateStr}.`,
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it'
            }).then((res)=>{
                if (!res.isConfirmed) return;
                const fd = new FormData();
                fd.append('action','delete_schedule_by_date');
                fd.append('user_id', userId);
                fd.append('schedule_date', dateStr);
                fetch('process_schedule.php',{method:'POST', body: fd})
                    .then(r=>r.json())
                    .then(data=>{
                        if (data.success) {
                            Swal.fire({icon:'success', title:'Deleted', text:data.message, confirmButtonColor:'#2a7d4f'}).then(()=>window.location.reload());
                        } else {
                            Swal.fire({icon:'error', title:'Error', text:data.message, confirmButtonColor:'#2a7d4f'});
                        }
                    })
                    .catch(err=>{
                        Swal.fire({icon:'error', title:'Error', text:'Request failed: '+err, confirmButtonColor:'#2a7d4f'});
                    });
            });
        }
    </script>
</body>
</html>
