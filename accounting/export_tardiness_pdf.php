<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn, 4);
require_once __DIR__ . '/../db_connection.php';

$month = $_GET['month'] ?? date('Y-m');
$dateRange = $_GET['dateRange'] ?? '1-15';
$selectedLocation = isset($_GET['location']) ? $_GET['location'] : '';

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
    $startDate = "$month-01";
    $endDate = date('Y-m-t', strtotime($month));
}

$sql = "SELECT 
            DATE(a.time_in) AS date,
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
        JOIN roles r ON u.role_id = r.role_id";
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
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: convert minutes to HH:MM for PDF
function minutesToHHMM($minutes) {
    $minutes = max(0, (int)$minutes);
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d', $h, $m);
}

$totalLate = 0;
$htmlRows = '';
foreach ($rows as $row) {
    $lateMin = (int)($row['late_minutes'] ?? 0);
    $totalLate += $lateMin;
    $htmlRows .= '<tr>'
        . '<td>' . htmlspecialchars(date('M d, Y', strtotime($row['date']))) . '</td>'
        . '<td>' . htmlspecialchars($row['name']) . '</td>'
        . '<td>' . htmlspecialchars($row['time_in'] ?? '') . '</td>'
        . '<td>' . htmlspecialchars($row['time_out'] ?? '') . '</td>'
        . '<td>' . htmlspecialchars(minutesToHHMM($lateMin)) . '</td>'
        . '</tr>';
}

$locationTitle = !empty($selectedLocation) ? ('Location: ' . htmlspecialchars($selectedLocation)) : 'All Locations';
$periodTitle = 'Period: ' . htmlspecialchars(date('M Y', strtotime($month))) . ' (' . htmlspecialchars($dateRange) . ')';

$html = "<html><head><style>
    body { font-family: sans-serif; }
    h2 { margin: 0 0 10px 0; }
    .meta { margin-bottom: 10px; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { border: 1px solid #333; padding: 6px; }
    thead th { background: #e3f2fd; }
    tfoot td { background: #eee; font-weight: bold; }
</style></head><body>
    <h2>Tardiness Report</h2>
    <div class=\"meta\">" . $locationTitle . "<br>" . $periodTitle . "</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Late (HH:MM)</th>
            </tr>
        </thead>
        <tbody>" . $htmlRows . "</tbody>
        <tfoot>
            <tr>
                <td colspan=\"4\" style=\"text-align:right\">TOTAL LATE (HH:MM)</td>
                <td>" . htmlspecialchars(minutesToHHMM($totalLate)) . "</td>
            </tr>
        </tfoot>
    </table>
</body></html>";

// Use Dompdf for PDF rendering
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Tardiness_Report_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
