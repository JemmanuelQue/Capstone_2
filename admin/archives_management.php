<?php
session_start();
require_once '../db_connection.php';

// Unified archive management (recover / delete) for any archived user.
// Expected POST parameters:
// action: 'recover' | 'delete'
// userId: target user id
// (optional) confirm: second-step confirmation for delete (value 'yes')
//
// Responses: sets session success/error messages then redirects back to archives.php

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Unauthorized access';
    header('Location: archives.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method';
    header('Location: archives.php');
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_POST['userId'] ?? '';
$attendanceId = $_POST['attendanceId'] ?? '';

try {
    if (empty($action)) {
        throw new Exception('Missing action');
    }

    // HR-only for attendance archive operations
    if (in_array($action, ['restore_attendance','delete_attendance'], true)) {
        if (($_SESSION['role_id'] ?? null) !== 3 && ($_SESSION['role_id'] ?? null) !== 1) {
            $_SESSION['error_message'] = 'Only HR can manage archived attendance records';
            header('Location: archives.php');
            exit;
        }
    }

    // Branch for attendance archive ops
    if ($action === 'restore_attendance' || $action === 'delete_attendance') {
        if (empty($attendanceId)) {
            throw new Exception('Missing attendanceId');
        }

        // Fetch archived attendance
        $attStmt = $conn->prepare('SELECT ID, User_ID, first_name, last_name, time_in, time_out FROM archive_dtr_data WHERE ID = ?');
        $attStmt->execute([$attendanceId]);
        $att = $attStmt->fetch(PDO::FETCH_ASSOC);
        if (!$att) {
            throw new Exception('Archived attendance not found');
        }

        if ($action === 'restore_attendance') {
            $conn->beginTransaction();
            $actorName = '';
            try {
                $u = $conn->prepare('SELECT First_Name, Last_Name FROM users WHERE User_ID = ?');
                $u->execute([$_SESSION['user_id']]);
                $uu = $u->fetch(PDO::FETCH_ASSOC);
                if ($uu) $actorName = $uu['First_Name'] . ' ' . $uu['Last_Name'];
            } catch (Exception $e) { /* ignore */ }

            // Try to re-insert into attendance with original ID; fallback without ID if duplicate key
            try {
                $ins = $conn->prepare('INSERT INTO attendance (ID, User_ID, Time_In, Time_Out, IP_Address, Created_At) VALUES (?, ?, ?, ?, ?, NOW())');
                $ins->execute([$att['ID'], $att['User_ID'], $att['time_in'], $att['time_out'], $_SERVER['REMOTE_ADDR'] ?? '']);
            } catch (Exception $e) {
                // Fallback insert without specifying ID
                $ins2 = $conn->prepare('INSERT INTO attendance (User_ID, Time_In, Time_Out, IP_Address, Created_At) VALUES (?, ?, ?, ?, NOW())');
                $ins2->execute([$att['User_ID'], $att['time_in'], $att['time_out'], $_SERVER['REMOTE_ADDR'] ?? '']);
            }

            // Remove from archive
            $del = $conn->prepare('DELETE FROM archive_dtr_data WHERE ID = ?');
            $del->execute([$attendanceId]);

            // Log recovery in activity_logs with friendly date/time
            $inDate = $att['time_in'] ? date('F j, Y', strtotime($att['time_in'])) : '';
            $inTime = $att['time_in'] ? date('g:i A', strtotime($att['time_in'])) : '';
            if (!empty($att['time_out'])) {
                $outDate = date('F j, Y', strtotime($att['time_out']));
                $outTime = date('g:i A', strtotime($att['time_out']));
                $dateRange = ($inDate === $outDate) ? $inDate : ($inDate . ' to ' . $outDate);
                $timeRange = $inTime . ' to ' . $outTime;
            } else {
                $dateRange = $inDate;
                $timeRange = $inTime . ' to no time out';
            }
            $guardFullName = $att['first_name'] . ' ' . $att['last_name'];
            $details = ($actorName ?: 'User') . ' restored attendance of ' . $guardFullName . ' - Date: ' . $dateRange . ' - Time: ' . $timeRange;
            $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())');
            $log->execute([$_SESSION['user_id'], 'Attendance Recovery', $details]);

            $conn->commit();
            $_SESSION['success_message'] = 'Attendance restored successfully';
        } else { // delete_attendance
            $conn->beginTransaction();
            $del = $conn->prepare('DELETE FROM archive_dtr_data WHERE ID = ?');
            $del->execute([$attendanceId]);

            // Build friendly actor name and date range
            $actorName = '';
            try {
                $u = $conn->prepare('SELECT First_Name, Last_Name FROM users WHERE User_ID = ?');
                $u->execute([$_SESSION['user_id']]);
                $uu = $u->fetch(PDO::FETCH_ASSOC);
                if ($uu) $actorName = $uu['First_Name'] . ' ' . $uu['Last_Name'];
            } catch (Exception $e) { /* ignore */ }

            $guardFullName = $att['first_name'] . ' ' . $att['last_name'];
            $inDate = $att['time_in'] ? date('F j, Y', strtotime($att['time_in'])) : '';
            $outDate = $att['time_out'] ? date('F j, Y', strtotime($att['time_out'])) : '';
            if ($outDate && $outDate !== $inDate) {
                $datePhrase = 'from ' . $inDate . ' to ' . $outDate;
            } else {
                $datePhrase = 'on ' . $inDate;
            }

            // Log permanent deletion with actor and human-friendly date(s)
            $details = ($actorName ?: 'User') . ' permanently deleted ' . $guardFullName . "'s attendance record " . $datePhrase;
            $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())');
            $log->execute([$_SESSION['user_id'], 'Attendance Delete Permanent', $details]);

            $conn->commit();
            $_SESSION['success_message'] = 'Archived attendance permanently deleted';
        }

        header('Location: archives.php');
        exit;
    }

    // From here on, original user archive recovery/deletion
    if (empty($userId)) {
        throw new Exception('Missing userId');
    }

    // Fetch user (any role) ensuring is archived for recover/delete safety
    $userStmt = $conn->prepare("SELECT User_ID, First_Name, Last_Name, Role_ID, archived_at FROM users WHERE User_ID = ?");
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        throw new Exception('User not found');
    }

    if ($action === 'recover') {
        if (!$userData['archived_at']) {
            throw new Exception('User is not archived');
        }
        $conn->beginTransaction();
        $recoverStmt = $conn->prepare("UPDATE users SET status = 'Active', archived_at = NULL, archived_by = NULL WHERE User_ID = ?");
        $recoverStmt->execute([$userId]);

        // Activity log
        $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, ?, ?)");
        $logStmt->execute([
            $_SESSION['user_id'],
            ($userData['Role_ID'] == 5 ? 'Guard Recovery' : 'User Recovery'),
            'Recovered user: ' . $userData['First_Name'] . ' ' . $userData['Last_Name']
        ]);

        // Audit log (if table exists). Fail silently if not.
        try {
            $auditStmt = $conn->prepare("INSERT INTO audit_logs (User_ID, Action, IP_Address) VALUES (?, ?, ?)");
            $auditStmt->execute([
                $_SESSION['user_id'],
                'Recovered user ID: ' . $userId,
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        } catch (Exception $e) { /* ignore */ }

        $conn->commit();
        $_SESSION['success_message'] = 'User successfully recovered';
    } elseif ($action === 'delete') {
        if (!$userData['archived_at']) {
            throw new Exception('Cannot delete a user that is not archived first');
        }
        $conn->beginTransaction();
        $fullName = $userData['First_Name'] . ' ' . $userData['Last_Name'];

        // Remove auxiliary / dependent rows (guard-specific + generic). Use try/catch for optional tables.
        $tables = [
            ['sql' => 'DELETE FROM guard_locations WHERE user_id = ?', 'guardOnly' => true],
            ['sql' => 'DELETE FROM guard_faces WHERE guard_id = ?', 'guardOnly' => true],
            ['sql' => 'DELETE FROM face_recognition_data WHERE user_id = ?', 'guardOnly' => true],
            ['sql' => 'DELETE FROM face_recognition_logs WHERE user_id = ?', 'guardOnly' => true],
            ['sql' => 'DELETE FROM attendance WHERE User_ID = ?', 'guardOnly' => true],
            ['sql' => 'DELETE FROM archived_guards WHERE user_id = ?', 'guardOnly' => true],
            // Generic (if exist) government details etc.
            ['sql' => 'DELETE FROM govt_details WHERE user_id = ?', 'guardOnly' => false]
        ];
        foreach ($tables as $t) {
            if ($t['guardOnly'] && $userData['Role_ID'] != 5) continue;
            try {
                $stmt = $conn->prepare($t['sql']);
                $stmt->execute([$userId]);
            } catch (Exception $e) { /* ignore if table missing */ }
        }

        // Finally delete user
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE User_ID = ?");
        $deleteStmt->execute([$userId]);

        // Log
        $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, ?, ?)");
        $logStmt->execute([
            $_SESSION['user_id'],
            ($userData['Role_ID'] == 5 ? 'Guard Deletion' : 'User Deletion'),
            'Permanently deleted user: ' . $fullName
        ]);
        try {
            $auditStmt = $conn->prepare("INSERT INTO audit_logs (User_ID, Action, IP_Address) VALUES (?, ?, ?)");
            $auditStmt->execute([
                $_SESSION['user_id'],
                'Deleted user ID: ' . $userId,
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        } catch (Exception $e) { /* ignore */ }

        $conn->commit();
        $_SESSION['success_message'] = 'User permanently deleted';
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

header('Location: archives.php');
exit;
