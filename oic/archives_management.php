<?php
session_start();
require_once '../db_connection.php';

// OIC archive management: recover/delete guards the OIC archived and restore/delete attendance.
// POST params:
// action: recover | delete | restore_attendance | delete_attendance
// userId (for guard actions) | attendanceId (for attendance actions)

if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] ?? null) != 8) {
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
    if (!$action) throw new Exception('Missing action');

    if (in_array($action, ['restore_attendance','delete_attendance'], true)) {
        if (!$attendanceId) throw new Exception('Missing attendanceId');
        $attStmt = $conn->prepare('SELECT ID, User_ID, first_name, last_name, time_in, time_out FROM archive_dtr_data WHERE ID = ?');
        $attStmt->execute([$attendanceId]);
        $att = $attStmt->fetch(PDO::FETCH_ASSOC);
        if (!$att) throw new Exception('Archived attendance not found');

        if ($action === 'restore_attendance') {
            $conn->beginTransaction();
            try {
                $ins = $conn->prepare('INSERT INTO attendance (ID, User_ID, Time_In, Time_Out, IP_Address, Created_At) VALUES (?, ?, ?, ?, ?, NOW())');
                $ins->execute([$att['ID'], $att['User_ID'], $att['time_in'], $att['time_out'], $_SERVER['REMOTE_ADDR'] ?? '']);
            } catch (Exception $e) {
                $ins2 = $conn->prepare('INSERT INTO attendance (User_ID, Time_In, Time_Out, IP_Address, Created_At) VALUES (?, ?, ?, ?, NOW())');
                $ins2->execute([$att['User_ID'], $att['time_in'], $att['time_out'], $_SERVER['REMOTE_ADDR'] ?? '']);
            }
            $del = $conn->prepare('DELETE FROM archive_dtr_data WHERE ID = ?');
            $del->execute([$attendanceId]);
            $guardName = $att['first_name'].' '.$att['last_name'];
            $inDate = $att['time_in'] ? date('F j, Y', strtotime($att['time_in'])) : '';
            $inTime = $att['time_in'] ? date('g:i A', strtotime($att['time_in'])) : '';
            if ($att['time_out']) {
                $outDate = date('F j, Y', strtotime($att['time_out']));
                $outTime = date('g:i A', strtotime($att['time_out']));
                $dateRange = ($inDate === $outDate) ? $inDate : ($inDate.' to '.$outDate);
                $timeRange = $inTime.' to '.$outTime;
            } else {
                $dateRange = $inDate; $timeRange = $inTime.' to no time out';
            }
            $logDetails = 'Erwin Mendoza restored attendance of '.$guardName.' - Date: '.$dateRange.' - Time: '.$timeRange; // Replace actor name dynamically
            try {
                $actorStmt = $conn->prepare('SELECT First_Name, Last_Name FROM users WHERE User_ID = ?');
                $actorStmt->execute([$_SESSION['user_id']]);
                if ($a = $actorStmt->fetch(PDO::FETCH_ASSOC)) {
                    $actorName = $a['First_Name'].' '.$a['Last_Name'];
                    $logDetails = $actorName.' restored attendance of '.$guardName.' - Date: '.$dateRange.' - Time: '.$timeRange;
                }
            } catch (Exception $e) { }
            $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())');
            $log->execute([$_SESSION['user_id'], 'Attendance Recovery', $logDetails]);
            $conn->commit();
            $_SESSION['success_message'] = 'Attendance restored successfully';
        } else { // delete_attendance
            $conn->beginTransaction();
            $del = $conn->prepare('DELETE FROM archive_dtr_data WHERE ID = ?');
            $del->execute([$attendanceId]);
            $guardName = $att['first_name'].' '.$att['last_name'];
            $inDate = $att['time_in'] ? date('F j, Y', strtotime($att['time_in'])) : '';
            $outDate = $att['time_out'] ? date('F j, Y', strtotime($att['time_out'])) : '';
            $datePhrase = ($outDate && $outDate !== $inDate) ? ('from '.$inDate.' to '.$outDate) : ('on '.$inDate);
            $logDetails = 'Permanently deleted '.$guardName."'s attendance record ".$datePhrase;
            try {
                $actorStmt = $conn->prepare('SELECT First_Name, Last_Name FROM users WHERE User_ID = ?');
                $actorStmt->execute([$_SESSION['user_id']]);
                if ($a = $actorStmt->fetch(PDO::FETCH_ASSOC)) {
                    $actorName = $a['First_Name'].' '.$a['Last_Name'];
                    $logDetails = $actorName.' permanently deleted '.$guardName."'s attendance record ".$datePhrase;
                }
            } catch (Exception $e) { }
            $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())');
            $log->execute([$_SESSION['user_id'], 'Attendance Delete Permanent', $logDetails]);
            $conn->commit();
            $_SESSION['success_message'] = 'Archived attendance permanently deleted';
        }
        header('Location: archives.php');
        exit;
    }

    // Guard archive actions
    if (in_array($action, ['recover','delete'], true)) {
        if (!$userId) throw new Exception('Missing userId');
        $uStmt = $conn->prepare('SELECT User_ID, First_Name, Last_Name, Role_ID, archived_at, archived_by FROM users WHERE User_ID = ?');
        $uStmt->execute([$userId]);
        $u = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) throw new Exception('User not found');
        if ($u['Role_ID'] != 5) throw new Exception('Only guard archives manageable here');
        if (!$u['archived_at']) throw new Exception('User is not archived');
        if ((int)$u['archived_by'] !== (int)$_SESSION['user_id']) throw new Exception('You did not archive this guard');

        if ($action === 'recover') {
            $conn->beginTransaction();
            $rec = $conn->prepare("UPDATE users SET status='Active', archived_at=NULL, archived_by=NULL WHERE User_ID = ?");
            $rec->execute([$userId]);
            $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())');
            $log->execute([$_SESSION['user_id'], 'Guard Recovery', 'Recovered guard: '.$u['First_Name'].' '.$u['Last_Name']]);
            $conn->commit();
            $_SESSION['success_message'] = 'Guard successfully recovered';
        } else { // delete
            $conn->beginTransaction();
            $tables = [
                'guard_locations'=>'user_id',
                'guard_faces'=>'guard_id',
                'face_recognition_data'=>'user_id',
                'face_recognition_logs'=>'user_id',
                'attendance'=>'User_ID',
                'archived_guards'=>'user_id'
            ];
            foreach ($tables as $t=>$col) {
                try { $del = $conn->prepare("DELETE FROM $t WHERE $col = ?"); $del->execute([$userId]); } catch (Exception $e) { }
            }
            $delUser = $conn->prepare('DELETE FROM users WHERE User_ID = ?');
            $delUser->execute([$userId]);
            $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())');
            $log->execute([$_SESSION['user_id'], 'Guard Deletion', 'Permanently deleted guard: '.$u['First_Name'].' '.$u['Last_Name']]);
            $conn->commit();
            $_SESSION['success_message'] = 'Guard permanently deleted';
        }
        header('Location: archives.php');
        exit;
    }

    throw new Exception('Invalid action');
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $_SESSION['error_message'] = 'Error: '.$e->getMessage();
    header('Location: archives.php');
    exit;
}
?>