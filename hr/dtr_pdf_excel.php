<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/session_check.php';
if (!validateSession($conn, 3)) { exit; }

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$action = $_GET['action'] ?? 'excel';

$month = $_GET['month'] ?? date('m');
$year  = $_GET['year']  ?? date('Y');
$period = $_GET['period'] ?? 'all';
$locationFilter = $_GET['location'] ?? '';
$searchTerm = $_GET['guardSearch'] ?? '';

function computeDateRange($month, $year, $period) {
    if ($period === '1-15') {
        $startDate = "$year-$month-01";
        $endDate   = "$year-$month-15";
        $periodLabel = '1st - 15th';
    } elseif ($period === '16-31') {
        $startDate = "$year-$month-16";
        $lastDay = date('t', strtotime("$year-$month-01"));
        $endDate   = "$year-$month-$lastDay";
        $periodLabel = '16th - 31st';
    } else {
        $startDate = "$year-$month-01";
        $lastDay = date('t', strtotime("$year-$month-01"));
        $endDate   = "$year-$month-$lastDay";
        $periodLabel = 'Whole Month';
    }
    $monthName = date('F', strtotime("$year-$month-01"));
    return [$startDate, $endDate, $periodLabel, $monthName];
}

list($startDate, $endDate, $periodLabel, $monthName) = computeDateRange($month, $year, $period);

// Shared helpers
function buildSearchCondition(&$params, $searchTerm, $locationFilter) {
    $cond = '';
    if ($searchTerm !== '') {
        $cond .= " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR CONCAT(u.First_Name, ' ', u.Last_Name) LIKE ?)";
        $like = "%$searchTerm%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($locationFilter !== '') {
        $cond .= " AND gl.location_name = ?";
        $params[] = $locationFilter;
    }
    return $cond;
}

function guardsQuery($conn, $searchTerm, $locationFilter) {
    $params = [];
    $cond = buildSearchCondition($params, $searchTerm, $locationFilter);
    $sql = "SELECT DISTINCT u.User_ID, u.First_Name, u.Last_Name, u.middle_name, gl.location_name
            FROM users u
            LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_primary = 1
            WHERE u.Role_ID = 5 AND u.status = 'Active' $cond
            ORDER BY u.Last_Name, u.First_Name";
    $stmt = $conn->prepare($sql);
    foreach ($params as $i => $p) { $stmt->bindValue($i+1, $p); }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateHours($timeIn, $timeOut) {
    if (!$timeOut) return 0;
    $in  = new DateTime($timeIn);
    $out = new DateTime($timeOut);
    if ($out < $in) { $out->modify('+1 day'); }
    $diff = $out->diff($in);
    return $diff->h + ($diff->days * 24);
}

switch ($action) {
    case 'pdf':
        $mpdf = new \Mpdf\Mpdf(['format' => 'A4-L']);
        $guards = guardsQuery($conn, $searchTerm, $locationFilter);
        ob_start();
        ?>
        <html>
        <head>
        <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 8px 0; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .table th, .table td { border: 1px solid #ccc; padding: 6px; }
        .table th { background: #f1f3f5; }
        .section-title { font-weight:bold; font-size: 13px; margin-top: 8px; }
        .meta { color:#555; text-align:center; margin-bottom: 8px; }
        .small { color:#666; font-size: 10px; }
        </style>
        </head>
        <body>
        <h1>Daily Time Records</h1>
        <div class="meta">Period: <?php echo htmlspecialchars($monthName . ' ' . $year . ' (' . $periodLabel . ')'); ?></div>
        <?php foreach ($guards as $g):
            $guardId = $g['User_ID'];
            $guardName = $g['First_Name'].' '.($g['middle_name']? $g['middle_name'].' ':'').$g['Last_Name'];
            $location = $g['location_name'] ?: 'Not Assigned';
        ?>
        <div class="section-title">Guard: <?php echo htmlspecialchars($guardName); ?> &nbsp; | &nbsp; Location: <?php echo htmlspecialchars($location); ?></div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 20%">Date</th>
                    <th style="width: 20%">Time In</th>
                    <th style="width: 20%">Time Out</th>
                    <th style="width: 15%">Hours</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $att = $conn->prepare("SELECT DATE(Time_In) d, Time_In, Time_Out FROM attendance WHERE User_ID=? AND DATE(Time_In) BETWEEN ? AND ? ORDER BY Time_In DESC");
            $att->execute([$guardId, $startDate, $endDate]);
            $hoursTotal = 0; $rows=0;
            while ($r = $att->fetch(PDO::FETCH_ASSOC)) {
                $timeIn = $r['Time_In'];
                $timeOut = $r['Time_Out'];
                $dateDisp = date('M j, Y', strtotime($r['d']));
                $hours = calculateHours($timeIn, $timeOut);
                $hoursTotal += $hours; $rows++;
                echo '<tr>';
                echo '<td>'.htmlspecialchars($dateDisp).'</td>';
                echo '<td>'.htmlspecialchars(date('h:i A', strtotime($timeIn))).'</td>';
                echo '<td>'.($timeOut ? htmlspecialchars(date('h:i A', strtotime($timeOut))) : '—').'</td>';
                echo '<td>'.htmlspecialchars($hours).'</td>';
                echo '<td></td>';
                echo '</tr>';
            }
            if ($rows === 0) {
                echo '<tr><td colspan="5" class="small">No attendance records for this period.</td></tr>';
            }
            ?>
            </tbody>
        </table>
        <div class="small"><strong>Total Hours:</strong> <?php echo htmlspecialchars($hoursTotal); ?></div>
        <?php endforeach; ?>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        $mpdf->WriteHTML($html);
        $filename = 'DTR_' . $year . '_' . $month . '_' . $period . '.pdf';
        $mpdf->Output($filename, 'I');
        exit;

    

    case 'excel':
    default:
        // Try to ensure AutoFilter Rule class is available (workaround for autoload edge-case)
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Worksheet\\AutoFilter\\Column\\Rule')) {
            $rulePath = __DIR__ . '/../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Worksheet/AutoFilter/Column/Rule.php';
            if (file_exists($rulePath)) {
                require_once $rulePath;
            }
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('DTR');

            // Header
            $header = "Daily Time Records - $monthName $year ($periodLabel)";
            $sheet->setCellValue('A1', $header);
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row = 3;
            $guards = guardsQuery($conn, $searchTerm, $locationFilter);
            foreach ($guards as $g) {
                $guardId = $g['User_ID'];
                $guardName = $g['First_Name'].' '.($g['middle_name']? $g['middle_name'].' ':'').$g['Last_Name'];
                $location = $g['location_name'] ?: 'Not Assigned';

                // Guard header
                $sheet->setCellValue("A$row", $guardName);
                $sheet->setCellValue("B$row", $location);
                $sheet->mergeCells("B$row:F$row");
                $sheet->getStyle("A$row:F$row")->getFont()->setBold(true);
                $row++;

                // Table header
                $sheet->fromArray(['Date', 'Time In', 'Time Out', 'Hours Worked', 'Notes'], NULL, "A$row");
                $sheet->getStyle("A$row:E$row")->getFont()->setBold(true);
                $sheet->getStyle("A$row:E$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');
                $row++;

                // Attendance rows
                $att = $conn->prepare("SELECT DATE(Time_In) d, Time_In, Time_Out FROM attendance WHERE User_ID=? AND DATE(Time_In) BETWEEN ? AND ? ORDER BY Time_In DESC");
                $att->execute([$guardId, $startDate, $endDate]);
                $hoursTotal = 0;
                while ($r = $att->fetch(PDO::FETCH_ASSOC)) {
                    $timeIn = $r['Time_In'];
                    $timeOut = $r['Time_Out'];
                    $dateDisp = date('M j, Y', strtotime($r['d']));
                    $hours = calculateHours($timeIn, $timeOut);
                    $hoursTotal += $hours;
                    $sheet->fromArray([
                        $dateDisp,
                        date('h:i A', strtotime($timeIn)),
                        $timeOut ? date('h:i A', strtotime($timeOut)) : '—',
                        $hours,
                        ''
                    ], NULL, "A$row");
                    $row++;
                }

                // Total row
                $sheet->setCellValue("A$row", 'Total Hours');
                $sheet->setCellValue("D$row", $hoursTotal);
                $sheet->getStyle("A$row:E$row")->getFont()->setBold(true);
                $row += 2; // spacer
            }

            // Autosize
            foreach (range('A','F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $filename = 'DTR_' . $year . '_' . $month . '_' . $period . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Throwable $e) {
            // Fallback to CSV export if PhpSpreadsheet fails
            $filename = 'DTR_' . $year . '_' . $month . '_' . $period . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $csvHandle = fopen('php://output', 'w');
            fputcsv($csvHandle, ["Daily Time Records - $monthName $year ($periodLabel)"]);
            $guards = guardsQuery($conn, $searchTerm, $locationFilter);
            foreach ($guards as $g) {
                $guardId = $g['User_ID'];
                $guardName = $g['First_Name'].' '.($g['middle_name']? $g['middle_name'].' ':'').$g['Last_Name'];
                $location = $g['location_name'] ?: 'Not Assigned';
                fputcsv($csvHandle, []);
                fputcsv($csvHandle, ['Guard', $guardName]);
                fputcsv($csvHandle, ['Location', $location]);
                fputcsv($csvHandle, ['Date', 'Time In', 'Time Out', 'Hours Worked']);
                $att = $conn->prepare("SELECT DATE(Time_In) d, Time_In, Time_Out FROM attendance WHERE User_ID=? AND DATE(Time_In) BETWEEN ? AND ? ORDER BY Time_In DESC");
                $att->execute([$guardId, $startDate, $endDate]);
                $hoursTotal = 0;
                while ($r = $att->fetch(PDO::FETCH_ASSOC)) {
                    $timeIn = $r['Time_In'];
                    $timeOut = $r['Time_Out'];
                    $dateDisp = date('M j, Y', strtotime($r['d']));
                    $hours = calculateHours($timeIn, $timeOut);
                    $hoursTotal += $hours;
                    fputcsv($csvHandle, [$dateDisp, date('h:i A', strtotime($timeIn)), $timeOut ? date('h:i A', strtotime($timeOut)) : '—', $hours]);
                }
                fputcsv($csvHandle, ['Total Hours', '', '', $hoursTotal]);
            }
            fclose($csvHandle);
            exit;
        }
}
