<?php
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/payroll_calculation/unified_payroll_calculator.php';

$month = $_GET['month'] ?? date('Y-m');
$dateRange = $_GET['dateRange'] ?? '1-15';
$selectedLocation = $_GET['location'] ?? '';
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

$calculator = new PayrollCalculator($conn);

$sql = "SELECT u.employee_id, u.user_id, u.first_name, u.middle_name, u.last_name,
        CONCAT(u.first_name, ' ', CASE WHEN u.middle_name IS NOT NULL AND u.middle_name != '' THEN CONCAT(UPPER(LEFT(u.middle_name,1)), '. ') ELSE '' END, u.last_name) AS name
        FROM users u JOIN roles r ON u.role_id = r.role_id";
if (!empty($selectedLocation)) {
    $sql .= " JOIN guard_locations gl ON u.user_id = gl.user_id AND gl.location_name = :location_name AND gl.is_active = 1";
}
$sql .= " WHERE r.role_name = 'Security Guard' AND u.status = 'Active' ORDER BY u.last_name ASC, u.first_name ASC";
$stmt = $conn->prepare($sql);
if (!empty($selectedLocation)) {
    $stmt->bindParam(':location_name', $selectedLocation);
}
$stmt->execute();
$guards = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { size: A4 landscape; margin: 12mm; }
  body { font-family: Arial, sans-serif; font-size: 12px; }
  h3 { text-align: center; margin: 0 0 10px; }
  .meta { text-align: center; margin-bottom: 12px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #ccc; padding: 6px; }
  thead th { background: #f0f6ff; }
</style>
</head>
<body>
<h3>Payroll Register</h3>
<div class="meta">Month: <?= htmlspecialchars(date('F Y', strtotime($month.'-01'))) ?> | Cutoff: <?= htmlspecialchars($dateRange) ?> | Location: <?= htmlspecialchars($selectedLocation ?: 'All') ?></div>
<table>
  <thead>
    <tr>
      <th colspan="2">Employee Information</th>
      <th colspan="18" style="background:#e6f9ed">Earnings</th>
      <th colspan="8" style="background:#fde6e6">Deductions</th>
      <th colspan="1">Summary</th>
    </tr>
    <tr>
      <th>Employee ID</th>
      <th>Employee Name</th>
      <th>Regular Hours</th>
      <th>Regular Pay</th>
      <th>Regular OT Hours</th>
      <th>Regular OT Pay</th>
      <th>Sun/RD/SPCL Hol Hours</th>
      <th>Sun/RD/SPCL Hol Pay</th>
      <th>Special Holiday OT Hours</th>
      <th>Special Holiday OT Pay</th>
      <th>Legal Holiday Hours</th>
      <th>Legal Holiday Pay</th>
      <th>Legal Holiday OT Hours</th>
      <th>Legal Holiday OT Pay</th>
      <th>Night Differential Hours</th>
      <th>Night Differential Pay</th>
      <th>Uniform/Other Allowance</th>
      <th>CTP Allowance</th>
      <th>Retroactive</th>
      <th>Gross Pay</th>
      <th>SSS</th>
      <th>PhilHealth</th>
      <th>Pag-IBIG</th>
      <th>Late / Undertime</th>
      <th>Cash Advance</th>
      <th>Cash Bond</th>
      <th>Others</th>
      <th>Total Deductions</th>
      <th>Net Pay</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($guards as $g): $p = $calculator->calculatePayroll($g['user_id'], $startDate, $endDate);
      $totalHours = 0.0;
      foreach (['regular_hours','ot_hours','night_diff_hours','legal_holiday_hours','holiday_ot_hours','special_holiday_hours','special_holiday_ot_hours'] as $hk) {
        if (isset($p[$hk])) { $totalHours += (float)$p[$hk]; }
      }
      $net = isset($p['net_pay']) ? (float)$p['net_pay'] : ((float)($p['gross_pay'] ?? 0) - (float)($p['total_deductions'] ?? 0));
      if ($totalHours <= 0 && $net <= 0) continue;
    ?>
    <tr>
      <td><?= htmlspecialchars($g['employee_id'] ?? '') ?></td>
      <td><?= htmlspecialchars($g['name']) ?></td>
      <td><?= number_format((float)($p['regular_hours'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['regular_hours_pay'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($p['ot_hours'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['ot_pay'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($p['special_holiday_hours'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['special_holiday_pay'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($p['special_holiday_ot_hours'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['special_holiday_ot_pay'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($p['legal_holiday_hours'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['legal_holiday_pay'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($p['holiday_ot_hours'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['holiday_ot_pay'] ?? 0), 2) ?></td>
      <td><?= number_format((float)($p['night_diff_hours'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['night_diff_pay'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['uniform_allowance'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['ctp_allowance'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['retroactive_pay'] ?? 0), 2) ?></td>
      <td class="fw-bold">₱ <?= number_format((float)($p['gross_pay'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['sss'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['philhealth'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['pagibig'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['late_undertime_deduction'] ?? ($p['late_undertime'] ?? 0)), 2) ?></td>
      <td>₱ <?= number_format((float)($p['cash_advance'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['cash_bond'] ?? 0), 2) ?></td>
      <td>₱ <?= number_format((float)($p['other_deductions'] ?? 0), 2) ?></td>
      <td class="fw-bold">₱ <?= number_format((float)($p['total_deductions'] ?? 0), 2) ?></td>
      <td class="fw-bold">₱ <?= number_format((float)$net, 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>
<?php
$html = ob_get_clean();

require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$filename = 'Payroll_Register_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
?>