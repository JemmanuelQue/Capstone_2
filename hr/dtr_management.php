<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

header('Content-Type: application/json');

// Allow HR (3) only via centralized validator
if (!validateSession($conn, 3, false)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            addAttendance($conn);
            break;
        case 'edit':
            editAttendance($conn);
            break;
        case 'archive':
            archiveAttendance($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Normalize various datetime input formats to 'Y-m-d H:i:s'.
 * Supports:
 *  - HTML datetime-local: Y-m-d\TH:i or Y-m-d\TH:i:s
 *  - Common DB formats: Y-m-d H:i or Y-m-d H:i:s
 *  - Locale formats: d/m/Y h:i A, m/d/Y h:i A, and variants with g (no leading zero)
 */
function normalizeDateTime(string $input): ?string {
    $input = trim($input);
    if ($input === '') return null;

    $patterns = [
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'd/m/Y h:i A',
        'm/d/Y h:i A',
        'd/m/Y g:i A',
        'm/d/Y g:i A',
        'd-m-Y H:i',
        'm-d-Y H:i',
    ];

    foreach ($patterns as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $input);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    // Final fallback to strtotime parsing
    $ts = strtotime($input);
    if ($ts !== false) {
        return date('Y-m-d H:i:s', $ts);
    }
    return null;
}

function currentUserName(PDO $conn): string {
    $stmt = $conn->prepare('SELECT First_Name, Last_Name FROM users WHERE User_ID = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    return $u ? ($u['First_Name'] . ' ' . $u['Last_Name']) : 'User';
}

function guardName(PDO $conn, $guardId): string {
    $stmt = $conn->prepare('SELECT First_Name, Last_Name, middle_name FROM users WHERE User_ID = ? AND Role_ID = 5');
    $stmt->execute([$guardId]);
    $g = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$g) return '';
    return $g['First_Name'] . ' ' . (!empty($g['middle_name']) ? $g['middle_name'] . ' ' : '') . $g['Last_Name'];
}

function addAttendance(PDO $conn) {
    $guardId = $_POST['guardId'] ?? null;
    $rawTimeIn = $_POST['timeIn'] ?? null;
    $rawTimeOut = $_POST['timeOut'] ?? null; // optional
    $reason = $_POST['reason'] ?? '';

    if (!$guardId || !$rawTimeIn) {
        echo json_encode(['success' => false, 'message' => 'Missing guardId or timeIn']);
        return;
    }

    // Normalize datetime inputs
    $timeIn = normalizeDateTime($rawTimeIn);
    $timeOut = $rawTimeOut !== null && $rawTimeOut !== '' ? normalizeDateTime($rawTimeOut) : null;
    if (!$timeIn) {
        echo json_encode(['success' => false, 'message' => 'Invalid timeIn format']);
        return;
    }
    if ($rawTimeOut && !$timeOut) {
        echo json_encode(['success' => false, 'message' => 'Invalid timeOut format']);
        return;
    }

    // Validate guard
    $gName = guardName($conn, $guardId);
    if (!$gName) {
        echo json_encode(['success' => false, 'message' => 'Guard not found or invalid']);
        return;
    }

    // Disallow future-dated Time In (cannot add attendance beyond today)
    $today = date('Y-m-d');
    $timeInDateOnly = date('Y-m-d', strtotime($timeIn));
    if ($timeInDateOnly > $today) {
        echo json_encode(['success' => false, 'message' => 'Cannot add attendance for a future date']);
        return;
    }

    if (!empty($timeOut) && strtotime($timeOut) <= strtotime($timeIn)) {
        echo json_encode(['success' => false, 'message' => 'Time out must be after time in']);
        return;
    }

    // Prevent duplicate per day
    $dateCheck = date('Y-m-d', strtotime($timeIn));
    $dup = $conn->prepare('SELECT COUNT(*) FROM attendance WHERE User_ID = ? AND DATE(Time_In) = ?');
    $dup->execute([$guardId, $dateCheck]);
    if ($dup->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Attendance already exists for ' . date('F j, Y', strtotime($dateCheck))]);
        return;
    }

    $conn->beginTransaction();

    // Insert
    $ins = $conn->prepare('INSERT INTO attendance (User_ID, Time_In, Time_Out, IP_Address, Created_At) VALUES (?, ?, ?, ?, NOW())');
    $ins->execute([$guardId, $timeIn, $timeOut ?: null, $_SERVER['REMOTE_ADDR'] ?? '']);
    $attendanceId = $conn->lastInsertId();

    $actor = currentUserName($conn);

    // Activity log
    $timeInDate = date('F j, Y', strtotime($timeIn));
    $timeInTime = date('g:i A', strtotime($timeIn));
    if ($timeOut) {
        $timeOutDate = date('F j, Y', strtotime($timeOut));
        $timeOutTime = date('g:i A', strtotime($timeOut));
        $dateRange = ($timeInDate === $timeOutDate) ? $timeInDate : ($timeInDate . ' to ' . $timeOutDate);
        $timeRange = $timeInTime . ' to ' . $timeOutTime;
    } else {
        $dateRange = $timeInDate;
        $timeRange = $timeInTime . ' (no time out)';
    }
    $details = $actor . ' added attendance record for ' . $gName . ' - Date: ' . $dateRange . ' - Time: ' . $timeRange . ' - Reason: ' . $reason;

    $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, "Attendance Add", ?, NOW())');
    $log->execute([$_SESSION['user_id'], $details]);

    // Audit trail
    try {
        $audit = $conn->prepare('INSERT INTO edit_attendance_logs (Attendance_ID, Editor_User_ID, Editor_Name, Old_Time_In, New_Time_In, Old_Time_Out, New_Time_Out, Edit_Timestamp, IP_Address, Action_Description) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)');
        $audit->execute([$attendanceId, $_SESSION['user_id'], $actor, '1970-01-01 00:00:00', $timeIn, '1970-01-01 00:00:00', $timeOut ?: null, $_SERVER['REMOTE_ADDR'] ?? '', 'Added new attendance record - Reason: ' . $reason]);
    } catch (Exception $e) { /* ignore if table missing */ }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Attendance record added successfully for ' . $gName, 'attendanceId' => $attendanceId]);
}

function editAttendance(PDO $conn) {
    // Only HR (Role_ID = 3) can edit attendance
    if (($_SESSION['role_id'] ?? null) !== 3) {
        echo json_encode(['success' => false, 'message' => 'Only HR can edit attendance']);
        return;
    }

    $attendanceId = $_POST['attendanceId'] ?? null;
    // support both newTimeIn/newTimeOut or timeIn/timeOut param names
    $rawNewIn = $_POST['newTimeIn'] ?? ($_POST['timeIn'] ?? null);
    $rawNewOut = $_POST['newTimeOut'] ?? ($_POST['timeOut'] ?? null);
    $reason = $_POST['reason'] ?? '';

    if (!$attendanceId || !$rawNewIn) {
        echo json_encode(['success' => false, 'message' => 'Missing attendanceId or timeIn']);
        return;
    }

    // Normalize new times
    $newTimeIn = normalizeDateTime($rawNewIn);
    $newTimeOut = $rawNewOut !== null && $rawNewOut !== '' ? normalizeDateTime($rawNewOut) : null;
    if (!$newTimeIn) {
        echo json_encode(['success' => false, 'message' => 'Invalid timeIn format']);
        return;
    }
    if ($rawNewOut && !$newTimeOut) {
        echo json_encode(['success' => false, 'message' => 'Invalid timeOut format']);
        return;
    }

    // Disallow setting future-dated Time In on edit as well
    $today = date('Y-m-d');
    $newInDateOnly = date('Y-m-d', strtotime($newTimeIn));
    if ($newInDateOnly > $today) {
        echo json_encode(['success' => false, 'message' => 'Cannot set Time In to a future date']);
        return;
    }

    if (!empty($newTimeOut) && strtotime($newTimeOut) <= strtotime($newTimeIn)) {
        echo json_encode(['success' => false, 'message' => 'Time out must be after time in']);
        return;
    }

    // Fetch existing
    $get = $conn->prepare('SELECT a.*, u.First_Name, u.Last_Name FROM attendance a JOIN users u ON a.User_ID = u.User_ID WHERE a.ID = ?');
    $get->execute([$attendanceId]);
    $row = $get->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
        return;
    }

    // Optional duplicate prevention when date changes
    $oldDate = $row['Time_In'] ? date('Y-m-d', strtotime($row['Time_In'])) : null;
    $newDate = date('Y-m-d', strtotime($newTimeIn));
    if ($oldDate !== $newDate) {
        $dup = $conn->prepare('SELECT COUNT(*) FROM attendance WHERE User_ID = ? AND DATE(Time_In) = ? AND ID <> ?');
        $dup->execute([$row['User_ID'], $newDate, $attendanceId]);
        if ($dup->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Another attendance exists for this guard on ' . date('F j, Y', strtotime($newDate))]);
            return;
        }
    }

    $conn->beginTransaction();

    // Update
    $upd = $conn->prepare('UPDATE attendance SET Time_In = ?, Time_Out = ?, IP_Address = ? WHERE ID = ?');
    $upd->execute([$newTimeIn, $newTimeOut ?: null, $_SERVER['REMOTE_ADDR'] ?? '', $attendanceId]);

    $actor = currentUserName($conn);

    // Activity log with readable old/new date & time
    $oldInDate = $row['Time_In'] ? date('F j, Y', strtotime($row['Time_In'])) : '';
    $oldInTime = $row['Time_In'] ? date('g:i A', strtotime($row['Time_In'])) : '';
    $oldOutTime = $row['Time_Out'] ? date('g:i A', strtotime($row['Time_Out'])) : 'no time out';

    $newInDate = date('F j, Y', strtotime($newTimeIn));
    $newInTime = date('g:i A', strtotime($newTimeIn));
    $newOutTime = $newTimeOut ? date('g:i A', strtotime($newTimeOut)) : 'no time out';

    $logDetails = $actor . ' edited attendance for ' . $row['First_Name'] . ' ' . $row['Last_Name'] .
        ' - Old: ' . $oldInDate . ' - ' . $oldInTime . ' to ' . $oldOutTime .
        ' - New: ' . $newInDate . ' - ' . $newInTime . ' to ' . $newOutTime .
        ' - Reason: ' . $reason;
    $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, "Attendance Edit", ?, NOW())');
    $log->execute([$_SESSION['user_id'], $logDetails]);

    // Audit trail
    try {
        $audit = $conn->prepare('INSERT INTO edit_attendance_logs (Attendance_ID, Editor_User_ID, Editor_Name, Old_Time_In, New_Time_In, Old_Time_Out, New_Time_Out, Edit_Timestamp, IP_Address, Action_Description) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)');
        $audit->execute([$attendanceId, $_SESSION['user_id'], $actor, $row['Time_In'], $newTimeIn, $row['Time_Out'], $newTimeOut ?: null, $_SERVER['REMOTE_ADDR'] ?? '', 'Edited attendance - Reason: ' . $reason]);
    } catch (Exception $e) { /* ignore if table missing */ }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
}

function archiveAttendance(PDO $conn) {
    $attendanceId = intval($_POST['attendanceId'] ?? 0);
    $reason = $_POST['reason'] ?? '';

    if (!$attendanceId || $reason === '') {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        return;
    }

    $conn->beginTransaction();

    // Get record to archive
    $getStmt = $conn->prepare('SELECT a.User_ID, a.Time_In, a.Time_Out, u.First_Name, u.Last_Name FROM attendance a JOIN users u ON a.User_ID = u.User_ID WHERE a.ID = ?');
    $getStmt->execute([$attendanceId]);
    $attendanceData = $getStmt->fetch(PDO::FETCH_ASSOC);
    if (!$attendanceData) {
        echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
        return;
    }

    // Copy to archive table
    $archiveStmt = $conn->prepare('INSERT INTO archive_dtr_data (ID, User_ID, first_name, last_name, time_in, time_out) VALUES (?, ?, ?, ?, ?, ?)');
    $archiveStmt->execute([
        $attendanceId,
        $attendanceData['User_ID'],
        $attendanceData['First_Name'],
        $attendanceData['Last_Name'],
        $attendanceData['Time_In'],
        $attendanceData['Time_Out']
    ]);

    // Delete from main
    $deleteStmt = $conn->prepare('DELETE FROM attendance WHERE ID = ?');
    $deleteStmt->execute([$attendanceId]);

    // Log activity with friendly date/time
    $actor = currentUserName($conn);
    $inDate = $attendanceData['Time_In'] ? date('F j, Y', strtotime($attendanceData['Time_In'])) : '';
    $inTime = $attendanceData['Time_In'] ? date('g:i A', strtotime($attendanceData['Time_In'])) : '';
    if (!empty($attendanceData['Time_Out'])) {
        $outDate = date('F j, Y', strtotime($attendanceData['Time_Out']));
        $outTime = date('g:i A', strtotime($attendanceData['Time_Out']));
        $dateRange = ($inDate === $outDate) ? $inDate : ($inDate . ' to ' . $outDate);
        $timeRange = $inTime . ' to ' . $outTime;
    } else {
        $dateRange = $inDate;
        $timeRange = $inTime . ' to no time out';
    }
    $guardFullName = $attendanceData['First_Name'] . ' ' . $attendanceData['Last_Name'];
    $archiveDetails = $actor . ' archived attendance of ' . $guardFullName . ' - Date: ' . $dateRange . ' - Time: ' . $timeRange . ' - Reason: ' . $reason;

    $activityStmt = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())');
    $activityStmt->execute([
        $_SESSION['user_id'],
        'Attendance Archive',
        $archiveDetails
    ]);

    $conn->commit();
    echo json_encode(['success' => true]);
}
