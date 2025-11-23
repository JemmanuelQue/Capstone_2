<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
        $conflicts = [];

// Enforce OIC role (8)
if (!validateSession($conn, 8)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    if ($action === 'save_schedule') {
        $scheduleData = json_decode($_POST['schedule_data'], true);
        
        if (!$scheduleData) {
            throw new Exception('Invalid schedule data');
        }
        
        $conn->beginTransaction();
        
        $insertStmt = $conn->prepare("
            INSERT INTO guard_schedules 
            (user_id, schedule_date, shift_type, location_name, hours_scheduled, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            shift_type = VALUES(shift_type),
            hours_scheduled = VALUES(hours_scheduled),
            notes = VALUES(notes),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        $successCount = 0;
        foreach ($scheduleData as $schedule) {
            $insertStmt->execute([
                $schedule['user_id'],
                $schedule['date'],
                $schedule['shift_type'],
                $schedule['location'],
                $schedule['hours'] ?? 12,
                $schedule['notes'] ?? null,
                $_SESSION['user_id']
            ]);
            $successCount++;
        }
        
        $conn->commit();
        
        // Log activity
        $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, ?, ?)");
        $logStmt->execute([
            $_SESSION['user_id'],
            'Schedule Creation',
            "Created/Updated $successCount schedule(s) for guards"
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "$successCount schedule(s) saved successfully"
        ]);
        
    } elseif ($action === 'get_schedules') {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        $location = $_GET['location'] ?? '';
        
        $query = "
            SELECT 
                gs.schedule_id,
                gs.user_id,
                gs.schedule_date,
                gs.shift_type,
                gs.location_name,
                gs.hours_scheduled,
                gs.notes,
                u.First_Name,
                u.Last_Name,
                u.employee_id
            FROM guard_schedules gs
            INNER JOIN users u ON gs.user_id = u.User_ID
            WHERE gs.schedule_date BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        
        if ($location) {
            $query .= " AND gs.location_name = ?";
            $params[] = $location;
        }
        
        $query .= " ORDER BY gs.schedule_date, u.Last_Name, u.First_Name";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'schedules' => $schedules
        ]);
        
    } elseif ($action === 'delete_schedule') {
        $conflicts = [];
        $scheduleId = $_POST['schedule_id'] ?? 0;
        
        $stmt = $conn->prepare("DELETE FROM guard_schedules WHERE schedule_id = ?");
        $stmt->execute([$scheduleId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Schedule deleted successfully'
        ]);

    } elseif ($action === 'delete_schedule_by_date') {
        // New: allow deletion by user_id + schedule_date (used by UI modal)
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $scheduleDate = $_POST['schedule_date'] ?? '';
        if (!$userId || !$scheduleDate) {
            throw new Exception('Missing required parameters for deletion');
        }

        // Verify guard belongs to OIC locations
        $verifyStmt = $conn->prepare('SELECT 1 
            FROM guard_locations gl 
            INNER JOIN oic_locations ol 
                ON ol.location_name = gl.location_name 
               AND ol.oic_user_id = ? 
               AND ol.is_active = 1 
            WHERE gl.user_id = ? AND gl.is_primary = 1 LIMIT 1');
        $verifyStmt->execute([$_SESSION['user_id'], $userId]);
        if (!$verifyStmt->fetchColumn()) {
            throw new Exception('Unauthorized: guard not under your locations');
        }

        $del = $conn->prepare('DELETE FROM guard_schedules WHERE user_id = ? AND schedule_date = ? LIMIT 1');
        $del->execute([$userId, $scheduleDate]);

        // Log activity
        $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, ?, ?)");
        $logStmt->execute([
            $_SESSION['user_id'],
            'Schedule Delete',
            'Deleted schedule for user ID ' . $userId . ' on ' . $scheduleDate
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Schedule deleted successfully'
        ]);
        
    } elseif ($action === 'add_single_schedule') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $scheduleDate = $_POST['schedule_date'] ?? '';
        $shiftType = $_POST['shift_type'] ?? '';
        $notes = $_POST['notes'] ?? null;
        if (!$userId || !$scheduleDate || !$shiftType) {
            throw new Exception('Missing required parameters for single schedule add');
        }
        // Guard must be active (not archived)
        $statusStmt = $conn->prepare('SELECT status, archived_at FROM users WHERE User_ID = ? AND Role_ID = 5 LIMIT 1');
        $statusStmt->execute([$userId]);
        $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
        if (!$statusRow || $statusRow['status'] !== 'Active' || !empty($statusRow['archived_at'])) {
            throw new Exception('Cannot add schedule: guard is archived or inactive');
        }
        // Allow any day within the current month; block other months
        $scheduleYm = substr($scheduleDate,0,7);
        if ($scheduleYm !== date('Y-m')) {
            throw new Exception('Cannot add schedule outside the current month');
        }
        // Verify guard belongs to OIC locations
        $verifyStmt = $conn->prepare('SELECT 1 
            FROM guard_locations gl 
            INNER JOIN oic_locations ol 
                ON ol.location_name = gl.location_name 
               AND ol.oic_user_id = ? 
               AND ol.is_active = 1 
            WHERE gl.user_id = ? AND gl.is_primary = 1 LIMIT 1');
        $verifyStmt->execute([$_SESSION['user_id'], $userId]);
        if (!$verifyStmt->fetchColumn()) {
            throw new Exception('Unauthorized: guard not under your locations');
        }
        // Prevent duplicate insert
        $existStmt = $conn->prepare('SELECT schedule_id FROM guard_schedules WHERE user_id = ? AND schedule_date = ? LIMIT 1');
        $existStmt->execute([$userId, $scheduleDate]);
        if ($existStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Schedule already exists for this date');
        }
        // Resolve location
        $locStmt = $conn->prepare('SELECT location_name FROM guard_locations WHERE user_id = ? AND is_active = 1 LIMIT 1');
        $locStmt->execute([$userId]);
        $locRow = $locStmt->fetch(PDO::FETCH_ASSOC);
        $locationName = $locRow ? $locRow['location_name'] : null;
        $hoursScheduled = ($shiftType === 'Reliever' || $shiftType === 'Rest Day') ? 0 : 12;
        $insStmt = $conn->prepare('INSERT INTO guard_schedules (user_id, schedule_date, shift_type, location_name, hours_scheduled, notes, created_by) VALUES (?,?,?,?,?,?,?)');
        $insStmt->execute([$userId, $scheduleDate, $shiftType, $locationName, $hoursScheduled, $notes, $_SESSION['user_id']]);
        // Log
        $logStmt = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?,?,?)');
        $logStmt->execute([$_SESSION['user_id'], 'Schedule Add', 'Added single schedule ('.$shiftType.') for user ID '.$userId.' on '.$scheduleDate]);
        echo json_encode(['success' => true, 'message' => 'Schedule added successfully']);
    } elseif ($action === 'bulk_assign') {
        $userIds = json_decode($_POST['user_ids'], true);
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $shiftType = $_POST['shift_type'];
        // Location is no longer provided by the UI; derive per guard from guard_locations

        if (empty($userIds) || empty($startDate) || empty($endDate) || empty($shiftType)) {
            throw new Exception('Missing required parameters for bulk assignment');
        }

        // Enforce current month scope for entire selected range
        $currentYm = date('Y-m');
        if (substr($startDate,0,7) !== $currentYm || substr($endDate,0,7) !== $currentYm) {
            throw new Exception('Bulk assignment allowed only within the current month (' . $currentYm . ').');
        }

        $conn->beginTransaction();

        // Pre-check all guards for active status (abort if any archived/inactive)
        $archivedProblem = [];
        $statusCheckStmt = $conn->prepare('SELECT User_ID, status, archived_at FROM users WHERE User_ID = ? AND Role_ID = 5 LIMIT 1');
        foreach ($userIds as $gId) {
            $statusCheckStmt->execute([$gId]);
            $sr = $statusCheckStmt->fetch(PDO::FETCH_ASSOC);
            if (!$sr || $sr['status'] !== 'Active' || !empty($sr['archived_at'])) {
                $archivedProblem[] = $gId;
            }
        }
        if (!empty($archivedProblem)) {
            $conn->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Bulk assignment aborted. Archived/inactive guards included: ' . implode(', ', $archivedProblem)
            ]);
            exit;
        }

        $insertStmt = $conn->prepare(
            "INSERT INTO guard_schedules 
            (user_id, schedule_date, shift_type, location_name, hours_scheduled, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
            "
        );

        // Prepared statement to get active location for a guard
        $guardLocStmt = $conn->prepare(
            "SELECT location_name FROM guard_locations WHERE user_id = ? AND is_active = 1 LIMIT 1"
        );

        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, (clone $end)->modify('+1 day'));

        // Build ordered list of ISO week keys within the selected period (e.g., 2025-34, 2025-35)
        $orderedWeekKeys = [];
        $seenWeeks = [];
        foreach ($period as $dateTmp) {
            $weekKey = $dateTmp->format('o-\WW'); // ISO year-week
            if (!isset($seenWeeks[$weekKey])) {
                $seenWeeks[$weekKey] = true;
                $orderedWeekKeys[] = $weekKey;
            }
        }

        // Determine the Monday of the first week in the selected period
        $firstPeriodDay = new DateTime($startDate);
        $firstMonday = clone $firstPeriodDay;
        $dow = (int)$firstMonday->format('N'); // 1=Mon..7=Sun
        if ($dow > 1) { $firstMonday->modify('-' . ($dow - 1) . ' days'); }

        // Prepared statement to check last week's dominant shift for a guard
        $prevWeekStmt = $conn->prepare(
            "SELECT shift_type, COUNT(*) AS cnt
             FROM guard_schedules
             WHERE user_id = ? AND schedule_date BETWEEN ? AND ?
               AND shift_type IN ('Day Shift','Night Shift')
             GROUP BY shift_type"
        );

        // Pre-scan for conflicts (any existing schedule in range aborts operation)
        $conflicts = [];
        $locationCache = [];
        $interval = new DateInterval('P1D');
        $startScan = new DateTime($startDate);
        $endScan = new DateTime($endDate);
        foreach ($userIds as $userId) {
            $periodScan = new DatePeriod($startScan, $interval, (clone $endScan)->modify('+1 day'));
            foreach ($periodScan as $scanDate) {
                $scanStr = $scanDate->format('Y-m-d');
                $existStmt = $conn->prepare('SELECT schedule_id FROM guard_schedules WHERE user_id = ? AND schedule_date = ? LIMIT 1');
                $existStmt->execute([$userId, $scanStr]);
                if ($existStmt->fetch(PDO::FETCH_ASSOC)) {
                    $conflicts[] = $userId . '|' . $scanStr;
                }
            }
        }
        if (!empty($conflicts)) {
            $conn->rollBack();
            // Format conflicts as guardID:date list
            $friendly = implode(', ', array_map(function($c){ list($g,$d)=explode('|',$c); return 'Guard ' . $g . ' on ' . $d; }, $conflicts));
            echo json_encode([
                'success' => false,
                'message' => 'Bulk assignment aborted. Existing schedules found: ' . $friendly
            ]);
            exit;
        }

        $count = 0;
        foreach ($userIds as $userId) {
            // Resolve guard's active location once per guard
            if (!isset($locationCache[$userId])) {
                $guardLocStmt->execute([$userId]);
                $locationRow = $guardLocStmt->fetch(PDO::FETCH_ASSOC);
                $locationCache[$userId] = $locationRow ? $locationRow['location_name'] : null;
            }
            $guardLocation = $locationCache[$userId];

            // If base shift is Day/Night, compute weekly alternation; else assign as-is
            $useAlternation = in_array($shiftType, ['Day Shift', 'Night Shift'], true);

            // Determine first week's shift for this guard
            $firstWeekShift = $shiftType;

            if ($useAlternation) {
                // Look at the immediate previous week to the first week in range
                $prevWeekStart = (clone $firstMonday)->modify('-7 days');
                $prevWeekEnd = (clone $prevWeekStart)->modify('+6 days');

                $dayCnt = 0; $nightCnt = 0;
                try {
                    $prevWeekStmt->execute([
                        $userId,
                        $prevWeekStart->format('Y-m-d'),
                        $prevWeekEnd->format('Y-m-d')
                    ]);
                    $rows = $prevWeekStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $r) {
                        if ($r['shift_type'] === 'Day Shift') $dayCnt = (int)$r['cnt'];
                        if ($r['shift_type'] === 'Night Shift') $nightCnt = (int)$r['cnt'];
                    }
                } catch (Exception $e) {
                    // Table might not exist yet or other issue; default to provided shift
                }

                if ($dayCnt > $nightCnt) {
                    $firstWeekShift = 'Night Shift';
                } elseif ($nightCnt > $dayCnt) {
                    $firstWeekShift = 'Day Shift';
                } else {
                    // Tie or no history: use provided base shift as first week's shift
                    $firstWeekShift = $shiftType;
                }
            }

            // Iterate the period again per guard to insert with weekly alternation
            $currentWeekKey = null;
            $weekIndex = -1; // 0 for first week in range
            foreach (new DatePeriod(new DateTime($startDate), $interval, (clone new DateTime($endDate))->modify('+1 day')) as $date) {
                $weekKey = $date->format('o-\WW');
                if ($weekKey !== $currentWeekKey) {
                    $currentWeekKey = $weekKey;
                    $weekIndex++;
                }

                $assignShift = $shiftType; // default for non-alternating base types
                if ($useAlternation) {
                    // Alternate weekly: even index = firstWeekShift; odd = opposite
                    if (($weekIndex % 2) === 0) {
                        $assignShift = $firstWeekShift;
                    } else {
                        $assignShift = ($firstWeekShift === 'Day Shift') ? 'Night Shift' : 'Day Shift';
                    }
                }

                $insertStmt->execute([
                    $userId,
                    $date->format('Y-m-d'),
                    $assignShift,
                    $guardLocation,
                    $assignShift === 'Reliever' ? 0 : 12,
                    $_SESSION['user_id']
                ]);
                $count++;
            }
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "$count schedule(s) assigned successfully (weekly rotation applied where applicable)"
        ]);
        
    } elseif ($action === 'update_schedule') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $scheduleDate = $_POST['schedule_date'] ?? '';
        $newShift = $_POST['shift_type'] ?? '';
        $notes = $_POST['notes'] ?? null;
        if (!$userId || !$scheduleDate || !$newShift) {
            throw new Exception('Missing required data for update');
        }
        // Guard must be active (not archived)
        $statusStmt = $conn->prepare('SELECT status, archived_at FROM users WHERE User_ID = ? AND Role_ID = 5 LIMIT 1');
        $statusStmt->execute([$userId]);
        $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
        if (!$statusRow || $statusRow['status'] !== 'Active' || !empty($statusRow['archived_at'])) {
            throw new Exception('Cannot edit schedule: guard is archived or inactive');
        }
        // Basic validations: only allow edits inside the current month
        $scheduleYm = substr($scheduleDate,0,7);
        if ($scheduleYm !== date('Y-m')) {
            throw new Exception('Cannot edit schedule outside the current month');
        }
        // Verify guard under this OIC
        $verifyStmt = $conn->prepare('SELECT 1 FROM guard_locations gl INNER JOIN oic_locations ol ON ol.location_name = gl.location_name AND ol.oic_user_id = ? AND ol.is_active = 1 WHERE gl.user_id = ? AND gl.is_primary = 1 LIMIT 1');
        $verifyStmt->execute([$_SESSION['user_id'], $userId]);
        if (!$verifyStmt->fetchColumn()) {
            throw new Exception('Unauthorized: guard not under your locations');
        }
        // Fetch existing schedule
        $schedStmt = $conn->prepare('SELECT schedule_id, shift_type FROM guard_schedules WHERE user_id = ? AND schedule_date = ? LIMIT 1');
        $schedStmt->execute([$userId, $scheduleDate]);
        $existing = $schedStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new Exception('Schedule not found for specified date');
        }
        $oldShift = $existing['shift_type'];
        // Attendance conflict: if setting Rest Day but attendance exists that day
        if (strcasecmp($newShift, 'Rest Day') === 0) {
            $attStmt = $conn->prepare('SELECT COUNT(*) FROM attendance WHERE User_ID = ? AND DATE(Time_In) = ?');
            $attStmt->execute([$userId, $scheduleDate]);
            if ($attStmt->fetchColumn() > 0) {
                throw new Exception('Cannot set Rest Day: attendance already recorded for this date');
            }
        }
        // If attendance exists, enforce compatibility with shift change
        $attInfoStmt = $conn->prepare('SELECT Time_In, Time_Out FROM attendance WHERE User_ID = ? AND DATE(Time_In) = ? LIMIT 1');
        $attInfoStmt->execute([$userId, $scheduleDate]);
        $attRow = $attInfoStmt->fetch(PDO::FETCH_ASSOC);
        if ($attRow) {
            $inDate = date('Y-m-d', strtotime($attRow['Time_In']));
            $outDate = $attRow['Time_Out'] ? date('Y-m-d', strtotime($attRow['Time_Out'])) : null;
            if (strcasecmp($newShift, 'Day Shift') === 0 && $outDate && $inDate !== $outDate) {
                throw new Exception('Cannot change to Day Shift: recorded attendance spans overnight');
            }
            if (strcasecmp($newShift, 'Night Shift') === 0 && $outDate && $inDate === $outDate) {
                throw new Exception('Cannot change to Night Shift: attendance does not span overnight');
            }
        }
        // Perform update
        $updStmt = $conn->prepare('UPDATE guard_schedules SET shift_type = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE schedule_id = ?');
        $updStmt->execute([$newShift, $notes, $existing['schedule_id']]);
        // Log activity
        $logStmt = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, ?, ?)');
        $logStmt->execute([$_SESSION['user_id'], 'Schedule Edit', 'Changed shift from ' . $oldShift . ' to ' . $newShift . ' for user ID ' . $userId . ' on ' . $scheduleDate]);
        echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Check if error is due to missing table
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Table") !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Database tables not found. Please run the SQL setup script (oic_scheduling_tables.sql) in phpMyAdmin first.',
            'error_type' => 'missing_tables'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
