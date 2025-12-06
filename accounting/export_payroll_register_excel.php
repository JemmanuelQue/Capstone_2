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

$filename = 'Payroll_Register_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// Output an HTML table compatible with Excel
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  table { border-collapse: collapse; }
  th, td { border: 1px solid #000; padding: 4px; }
  thead th { background: #dfefff; }
  .group-earn { background:#e6f9ed; }
  .group-ded { background:#fde6e6; }
</style>
</head>
<body>
<table>
  <thead>
    <tr>
      <th colspan="2">Employee Information</th>
      <th colspan="18" class="group-earn">Earnings</th>
      <th colspan="8" class="group-ded">Deductions</th>
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
    <?php
    $tot_regular_hours = 0; $tot_regular_hours_pay = 0; $tot_ot_hours = 0; $tot_ot_pay = 0;
    $tot_sun_rd_spcl_hours = 0; $tot_sun_rd_spcl_pay = 0;
    $tot_spcl_hol_ot_hours = 0; $tot_spcl_hol_ot_pay = 0;
    $tot_legal_hol_hours = 0; $tot_legal_hol_pay = 0;
    $tot_legal_hol_ot_hours = 0; $tot_legal_hol_ot_pay = 0;
    $tot_nd_hours = 0; $tot_nd_pay = 0;
    $tot_uniform_allow = 0; $tot_ctp_allow = 0; $tot_retro = 0;
    $tot_gross = 0;
    $tot_sss = 0; $tot_philhealth = 0; $tot_pagibig = 0; $tot_late_und = 0;
    $tot_cash_advance = 0; $tot_cash_bond = 0; $tot_others = 0; $tot_total_deductions = 0; $tot_net = 0;
    foreach ($guards as $g) {
        $p = $calculator->calculatePayroll($g['user_id'], $startDate, $endDate);
        $totalHours = 0.0;
        foreach (['regular_hours','ot_hours','night_diff_hours','legal_holiday_hours','holiday_ot_hours','special_holiday_hours','special_holiday_ot_hours'] as $hk) {
            if (isset($p[$hk])) { $totalHours += (float)$p[$hk]; }
        }
        $net = isset($p['net_pay']) ? (float)$p['net_pay'] : ((float)($p['gross_pay'] ?? 0) - (float)($p['total_deductions'] ?? 0));
        if ($totalHours <= 0 && $net <= 0) { continue; }
        ?>
        <tr>
          <td><?= htmlspecialchars($g['employee_id'] ?? '') ?></td>
          <td><?= htmlspecialchars($g['name']) ?></td>
          <td><?= number_format((float)($p['regular_hours'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['regular_hours_pay'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['ot_hours'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['ot_pay'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['special_holiday_hours'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['special_holiday_pay'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['special_holiday_ot_hours'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['special_holiday_ot_pay'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['legal_holiday_hours'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['legal_holiday_pay'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['holiday_ot_hours'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['holiday_ot_pay'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['night_diff_hours'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['night_diff_pay'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['uniform_allowance'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['ctp_allowance'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['retroactive_pay'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['gross_pay'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['sss'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['philhealth'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['pagibig'] ?? 0), 2) ?></td>
          <?php $lateUnd = isset($p['late_undertime_deduction']) ? $p['late_undertime_deduction'] : ($p['late_undertime'] ?? 0); ?>
          <td><?= number_format((float)$lateUnd, 2) ?></td>
          <td><?= number_format((float)($p['cash_advance'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['cash_bond'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['other_deductions'] ?? 0), 2) ?></td>
          <td><?= number_format((float)($p['total_deductions'] ?? 0), 2) ?></td>
          <td><?= number_format((float)$net, 2) ?></td>
        </tr>
        <?php
        $tot_regular_hours += (float)($p['regular_hours'] ?? 0);
        $tot_regular_hours_pay += (float)($p['regular_hours_pay'] ?? 0);
        $tot_ot_hours += (float)($p['ot_hours'] ?? 0);
        $tot_ot_pay += (float)($p['ot_pay'] ?? 0);
        $tot_sun_rd_spcl_hours += (float)($p['special_holiday_hours'] ?? 0);
        $tot_sun_rd_spcl_pay += (float)($p['special_holiday_pay'] ?? 0);
        $tot_spcl_hol_ot_hours += (float)($p['special_holiday_ot_hours'] ?? 0);
        $tot_spcl_hol_ot_pay += (float)($p['special_holiday_ot_pay'] ?? 0);
        $tot_legal_hol_hours += (float)($p['legal_holiday_hours'] ?? 0);
        $tot_legal_hol_pay += (float)($p['legal_holiday_pay'] ?? 0);
        $tot_legal_hol_ot_hours += (float)($p['holiday_ot_hours'] ?? 0);
        $tot_legal_hol_ot_pay += (float)($p['holiday_ot_pay'] ?? 0);
        $tot_nd_hours += (float)($p['night_diff_hours'] ?? 0);
        $tot_nd_pay += (float)($p['night_diff_pay'] ?? 0);
        $tot_uniform_allow += (float)($p['uniform_allowance'] ?? 0);
        $tot_ctp_allow += (float)($p['ctp_allowance'] ?? 0);
        $tot_retro += (float)($p['retroactive_pay'] ?? 0);
        $tot_gross += (float)($p['gross_pay'] ?? 0);
        $tot_sss += (float)($p['sss'] ?? 0);
        $tot_philhealth += (float)($p['philhealth'] ?? 0);
        $tot_pagibig += (float)($p['pagibig'] ?? 0);
        $tot_late_und += (float)$lateUnd;
        $tot_cash_advance += (float)($p['cash_advance'] ?? 0);
        $tot_cash_bond += (float)($p['cash_bond'] ?? 0);
        $tot_others += (float)($p['other_deductions'] ?? 0);
        $tot_total_deductions += (float)($p['total_deductions'] ?? 0);
        $tot_net += (float)$net;
    }
    ?>
  </tbody>
  <tfoot>
    <tr>
      <td></td>
      <td><strong>TOTAL</strong></td>
      <td><?= number_format($tot_regular_hours, 2) ?></td>
      <td><?= number_format($tot_regular_hours_pay, 2) ?></td>
      <td><?= number_format($tot_ot_hours, 2) ?></td>
      <td><?= number_format($tot_ot_pay, 2) ?></td>
      <td><?= number_format($tot_sun_rd_spcl_hours, 2) ?></td>
      <td><?= number_format($tot_sun_rd_spcl_pay, 2) ?></td>
      <td><?= number_format($tot_spcl_hol_ot_hours, 2) ?></td>
      <td><?= number_format($tot_spcl_hol_ot_pay, 2) ?></td>
      <td><?= number_format($tot_legal_hol_hours, 2) ?></td>
      <td><?= number_format($tot_legal_hol_pay, 2) ?></td>
      <td><?= number_format($tot_legal_hol_ot_hours, 2) ?></td>
      <td><?= number_format($tot_legal_hol_ot_pay, 2) ?></td>
      <td><?= number_format($tot_nd_hours, 2) ?></td>
      <td><?= number_format($tot_nd_pay, 2) ?></td>
      <td><?= number_format($tot_uniform_allow, 2) ?></td>
      <td><?= number_format($tot_ctp_allow, 2) ?></td>
      <td><?= number_format($tot_retro, 2) ?></td>
      <td><?= number_format($tot_gross, 2) ?></td>
      <td><?= number_format($tot_sss, 2) ?></td>
      <td><?= number_format($tot_philhealth, 2) ?></td>
      <td><?= number_format($tot_pagibig, 2) ?></td>
      <td><?= number_format($tot_late_und, 2) ?></td>
      <td><?= number_format($tot_cash_advance, 2) ?></td>
      <td><?= number_format($tot_cash_bond, 2) ?></td>
      <td><?= number_format($tot_others, 2) ?></td>
      <td><?= number_format($tot_total_deductions, 2) ?></td>
      <td><?= number_format($tot_net, 2) ?></td>
    </tr>
  </tfoot>
</table>
</body>
</html>
