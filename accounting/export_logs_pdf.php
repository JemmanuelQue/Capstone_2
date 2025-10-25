<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Enforce Accounting role (4)
if (!validateSession($conn, 4)) { exit; }

// Collect filters from request
$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$recordsPerPage = isset($_GET['limit']) ? intval($_GET['limit']) : 10; // used only for display summary

// Define accounting-related activity types
$accountingActivityTypes = [
    'Rate Update',
    'Payroll Generation',
    'Attendance Archive',
    'Attendance Edit',
    'Holiday Management',
    'Holiday System',
    'Cash Advance',
    'Cash Bond',
    'Salary Disbursement',
    'Financial Update',
    'Attendance Add',
    'Attendance Delete Permanent'
];

// Build base queries mirroring accounting/logs.php (without pagination)
$activityLogsBaseQuery = "
    SELECT 
        al.Log_ID,
        al.Activity_Type,
        al.Activity_Details,
        al.Timestamp,
        al.User_ID,
        u.First_Name,
        u.Last_Name,
        u.Role_ID,
        r.Role_Name,
        NULL as Old_Time_In,
        NULL as New_Time_In,
        NULL as Old_Time_Out,
        NULL as New_Time_Out,
        'activity_logs' as source_table
    FROM activity_logs al
    LEFT JOIN users u ON al.User_ID = u.User_ID
    LEFT JOIN roles r ON u.Role_ID = r.Role_ID
    WHERE ((u.Role_ID = 4 AND al.Activity_Type IN ('" . implode("', '", $accountingActivityTypes) . "'))
    OR al.Activity_Type IN ('" . implode("', '", $accountingActivityTypes) . "'))
    AND al.Activity_Type NOT LIKE '%Login%'
    AND al.Activity_Type NOT LIKE '%Logout%'
    AND al.Activity_Type NOT LIKE '%User Creation%'
    AND al.Activity_Type NOT LIKE '%User Update%'
    AND al.Activity_Type NOT LIKE '%User Archive%'
    AND al.Activity_Type NOT LIKE '%User Recovery%'
    AND al.Activity_Type NOT LIKE '%Guard%'
    AND al.Activity_Type NOT LIKE '%Password Reset%'
";

$editLogsBaseQuery = "
    SELECT DISTINCT
        eal.Log_ID,
        'Attendance Edit' as Activity_Type,
        CONCAT('Edited attendance record ID ', eal.Attendance_ID, 
               IF(eal.Action_Description LIKE '%Reason:%', 
                  CONCAT(' - Reason: ', SUBSTRING_INDEX(eal.Action_Description, 'Reason:', -1)), 
                  IF(eal.Action_Description LIKE '%reason:%',
                     CONCAT(' - Reason: ', SUBSTRING_INDEX(eal.Action_Description, 'reason:', -1)),
                     ''))) as Activity_Details,
        eal.Edit_Timestamp as Timestamp,
        eal.Editor_User_ID as User_ID,
        u.First_Name,
        u.Last_Name,
        u.Role_ID,
        r.Role_Name,
        eal.Old_Time_In,
        eal.New_Time_In,
        eal.Old_Time_Out,
        eal.New_Time_Out,
        'edit_attendance_logs' as source_table
    FROM edit_attendance_logs eal
    LEFT JOIN users u ON eal.Editor_User_ID = u.User_ID
    LEFT JOIN roles r ON u.Role_ID = r.Role_ID
    WHERE u.Role_ID = 4
    AND (
        (eal.Old_Time_In IS NOT NULL AND eal.New_Time_In IS NOT NULL AND eal.Old_Time_In != eal.New_Time_In)
        OR 
        (eal.Old_Time_Out IS NOT NULL AND eal.New_Time_Out IS NOT NULL AND eal.Old_Time_Out != eal.New_Time_Out)
    )
";

// Apply date filters
if (!empty($dateFrom)) {
    $formattedDateFrom = date('Y-m-d', strtotime($dateFrom));
    $activityLogsBaseQuery .= " AND DATE(al.Timestamp) >= '$formattedDateFrom'";
    $editLogsBaseQuery .= " AND DATE(eal.Edit_Timestamp) >= '$formattedDateFrom'";
}

if (!empty($dateTo)) {
    $formattedDateTo = date('Y-m-d', strtotime($dateTo));
    $activityLogsBaseQuery .= " AND DATE(al.Timestamp) <= '$formattedDateTo'";
    $editLogsBaseQuery .= " AND DATE(eal.Edit_Timestamp) <= '$formattedDateTo'";
}

// Apply activity type filter
if (!empty($activityType)) {
    if ($activityType === 'Attendance Edit') {
        $activityLogsBaseQuery .= " AND al.Activity_Type = '$activityType'";
        // keep edit logs as is
    } else {
        $activityLogsBaseQuery .= " AND al.Activity_Type = '$activityType'";
        // Empty result set for edit logs to keep UNION structure
        $editLogsBaseQuery = "
            SELECT 
                eal.Log_ID,
                'Attendance Edit' as Activity_Type,
                '' as Activity_Details,
                eal.Edit_Timestamp as Timestamp,
                eal.Editor_User_ID as User_ID,
                u.First_Name,
                u.Last_Name,
                u.Role_ID,
                r.Role_Name,
                eal.Old_Time_In,
                eal.New_Time_In,
                eal.Old_Time_Out,
                eal.New_Time_Out,
                'edit_attendance_logs' as source_table
            FROM edit_attendance_logs eal
            LEFT JOIN users u ON eal.Editor_User_ID = u.User_ID
            LEFT JOIN roles r ON u.Role_ID = r.Role_ID
            WHERE 1=0
        ";
    }
}

// Apply search filter
if (!empty($searchTerm)) {
    $searchTermEsc = str_replace(["%","_"], ["\\%","\\_"], $searchTerm);
    $activityLogsBaseQuery .= " AND (
        al.Activity_Details LIKE '%$searchTermEsc%'
        OR u.First_Name LIKE '%$searchTermEsc%'
        OR u.Last_Name LIKE '%$searchTermEsc%'
    )";
    $editLogsBaseQuery .= " AND (
        eal.Action_Description LIKE '%$searchTermEsc%'
        OR u.First_Name LIKE '%$searchTermEsc%'
        OR u.Last_Name LIKE '%$searchTermEsc%'
        OR eal.Attendance_ID LIKE '%$searchTermEsc%'
    )";
}

// Fetch combined logs
$logsQuery = "
    SELECT * FROM (
        $activityLogsBaseQuery
        UNION ALL
        $editLogsBaseQuery
    ) as combined_logs
    ORDER BY Timestamp DESC
";
$logsStmt = $conn->query($logsQuery);
$rows = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Build HTML for PDF
$generatedAt = date('M d, Y g:i A');
$filterSummary = [];
if (!empty($activityType)) { $filterSummary[] = 'Activity: ' . htmlspecialchars($activityType); }
if (!empty($dateFrom)) { $filterSummary[] = 'From: ' . date('M d, Y', strtotime($dateFrom)); }
if (!empty($dateTo)) { $filterSummary[] = 'To: ' . date('M d, Y', strtotime($dateTo)); }
if (!empty($searchTerm)) { $filterSummary[] = 'Search: ' . htmlspecialchars($searchTerm); }
$summaryText = !empty($filterSummary) ? implode(' | ', $filterSummary) : 'All accounting-related activities';

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; }
        .meta { color: #555; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; }
        th { background: #f2f2f2; text-align: left; }
        .small { color: #666; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Accounting Activity Logs</div>
        <div class="meta">Generated: <?= htmlspecialchars($generatedAt) ?></div>
        <div class="small"><?= htmlspecialchars($summaryText) ?></div>
        <div class="small">Total records: <?= count($rows) ?></div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 18%">Date & Time</th>
                <th style="width: 18%">User</th>
                <th style="width: 15%">Role</th>
                <th style="width: 18%">Activity Type</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5" style="text-align:center;">No activity logs found</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= date('M d, Y g:i A', strtotime($r['Timestamp'])) ?></td>
                        <td><?= htmlspecialchars(trim(($r['First_Name'] ?? '') . ' ' . ($r['Last_Name'] ?? '')) ?: 'System') ?></td>
                        <td><?= htmlspecialchars($r['Role_Name'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($r['Activity_Type']) ?></td>
                        <td>
                            <?= htmlspecialchars($r['Activity_Details']) ?>
                            <?php if ($r['Activity_Type'] === 'Attendance Edit' && (isset($r['Old_Time_In']) || isset($r['Old_Time_Out']))): ?>
                                <br><small class="text-muted"><strong>Changes:</strong><br>
                                <?php if (isset($r['Old_Time_In']) && isset($r['New_Time_In']) && $r['Old_Time_In'] !== $r['New_Time_In']): ?>
                                    Time In: <?= htmlspecialchars($r['Old_Time_In'] ?? 'None') ?> → <?= htmlspecialchars($r['New_Time_In'] ?? 'None') ?><br>
                                <?php endif; ?>
                                <?php if (isset($r['Old_Time_Out']) && isset($r['New_Time_Out']) && $r['Old_Time_Out'] !== $r['New_Time_Out']): ?>
                                    Time Out: <?= htmlspecialchars($r['Old_Time_Out'] ?? 'None') ?> → <?= htmlspecialchars($r['New_Time_Out'] ?? 'None') ?>
                                <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'accounting_activity_logs_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
