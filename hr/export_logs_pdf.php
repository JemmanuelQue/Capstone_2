<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Enforce HR role (3)
if (!validateSession($conn, 3)) { exit; }

// Collect filters from request
$activityType = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// HR-specific activity types
$hrActivityTypes = [
    'User Creation', 'Guard Creation',
    'User Update', 'Guard Update', 
    'User Archive', 'Archived Guards',
    'User Recovery', 'Guard Recovery',
    'User Deletion', 'Guard Deletion',
    'Leave Request Submitted',
    'Leave Request Approved',
    'Leave Request Rejected',
    'Performance Evaluation Started',
    'Performance Evaluation Completed',
    'Attendance Record Added',
    'Attendance Add',
    'Attendance Edit', 
    'Attendance Archive',
    'Attendance Delete Permanent',
    'Profile Update',
    'Password Reset'
];

$filters = [];
$params = [];

// Ensure only HR related activities
$placeholders = implode(',', array_fill(0, count($hrActivityTypes), '?'));
$filters[] = "al.Activity_Type IN ($placeholders)";
$params = array_merge($params, $hrActivityTypes);

// Optional specific activity filter (mapping to actual db values similar to logs.php)
if (!empty($activityType)) {
    $reverseActivityMapping = [
        'Guard Creation' => ['User Creation', 'Guard Creation'],
        'Guard Update' => ['User Update', 'Guard Update'],
        'Guard Archive' => ['User Archive', 'Archived Guards'],
        'Guard Recovery' => ['User Recovery', 'Guard Recovery'],
        'Guard Deletion' => ['User Deletion', 'Guard Deletion'],
        'Attendance Add' => ['Attendance Add', 'Attendance Record Added'],
        'Attendance Edit' => ['Attendance Edit'],
        'Attendance Archive' => ['Attendance Archive'],
        'Attendance Delete Permanent' => ['Attendance Delete Permanent'],
        'Profile Update' => ['Profile Update'],
        'Password Reset' => ['Password Reset'],
        'Leave Request Submitted' => ['Leave Request Submitted'],
        'Leave Request Approved' => ['Leave Request Approved'],
        'Leave Request Rejected' => ['Leave Request Rejected'],
        'Performance Evaluation Started' => ['Performance Evaluation Started'],
        'Performance Evaluation Completed' => ['Performance Evaluation Completed']
    ];

    $matchingTypes = [$activityType];
    foreach ($reverseActivityMapping as $displayType => $actualTypes) {
        if (in_array($activityType, $actualTypes)) { $matchingTypes = $actualTypes; break; }
    }

    $ph = implode(',', array_fill(0, count($matchingTypes), '?'));
    $filters[] = "al.Activity_Type IN ($ph)";
    $params = array_merge($params, $matchingTypes);
}

if (!empty($dateFrom)) {
    $filters[] = "DATE(Timestamp) >= ?";
    $params[] = date('Y-m-d', strtotime($dateFrom));
}

if (!empty($dateTo)) {
    $filters[] = "DATE(Timestamp) <= ?";
    $params[] = date('Y-m-d', strtotime($dateTo));
}

if (!empty($searchTerm)) {
    $filters[] = "(al.Activity_Details LIKE ? OR u.First_Name LIKE ? OR u.Last_Name LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

// Restrict to logs performed by HR users
$filters[] = "u.Role_ID = 3";

$whereSql = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';

$sql = "
    SELECT al.*, u.First_Name, u.Last_Name, r.Role_Name
    FROM activity_logs al
    LEFT JOIN users u ON al.User_ID = u.User_ID
    LEFT JOIN roles r ON u.Role_ID = r.Role_ID
    $whereSql
    ORDER BY al.Timestamp DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build HTML for PDF
$generatedAt = date('M d, Y g:i A');
$filterSummary = [];
if (!empty($activityType)) { $filterSummary[] = 'Activity: ' . htmlspecialchars($activityType); }
if (!empty($dateFrom)) { $filterSummary[] = 'From: ' . date('M d, Y', strtotime($dateFrom)); }
if (!empty($dateTo)) { $filterSummary[] = 'To: ' . date('M d, Y', strtotime($dateTo)); }
if (!empty($searchTerm)) { $filterSummary[] = 'Search: ' . htmlspecialchars($searchTerm); }

$summaryText = !empty($filterSummary) ? implode(' | ', $filterSummary) : 'All HR-related activities';

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
        <div class="title">HR Activity Logs</div>
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
                        <td><?= htmlspecialchars($r['Activity_Details']) ?></td>
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

$filename = 'hr_activity_logs_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
