<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/payroll_calculation/unified_payroll_calculator.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Inputs: month (YYYY-MM), dateRange (e.g., 1-15 or 16-30/31)
$month = $_GET['month'] ?? date('Y-m');
$dateRange = $_GET['dateRange'] ?? '1-15';

// Determine last day of month and normalize dateRange variants
$lastDayOfMonth = date('t', strtotime($month . '-01'));
if (preg_match('/^1-3[01]$/', $dateRange) || preg_match('/^1-2[89]$/', $dateRange)) {
    $dateRange = '1-' . $lastDayOfMonth;
} elseif (preg_match('/^16-3[01]$/', $dateRange) || preg_match('/^16-2[89]$/', $dateRange)) {
    $dateRange = '16-' . $lastDayOfMonth;
}

// Compute start and end dates from dateRange
if ($dateRange === '1-15') {
    $startDate = "$month-01";
    $endDate = "$month-15";
} elseif ($dateRange === '16-' . $lastDayOfMonth) {
    $startDate = "$month-16";
    $endDate = date('Y-m-t', strtotime($month));
} elseif ($dateRange === '1-' . $lastDayOfMonth) {
    $startDate = "$month-01";
    $endDate = date('Y-m-t', strtotime($month));
} elseif (preg_match('/^(\d{1,2})-(\d{1,2})$/', $dateRange, $m)) {
    $dStart = max(1, (int)$m[1]);
    $dEnd = min((int)$lastDayOfMonth, (int)$m[2]);
    $startDate = sprintf('%s-%02d', $month, $dStart);
    $endDate = sprintf('%s-%02d', $month, $dEnd);
} else {
    // Fallback to 1-15
    $dateRange = '1-15';
    $startDate = "$month-01";
    $endDate = "$month-15";
}

// Period label like generate_payslip (e.g., Dec 01-15, 2025)
$startDateObj = new DateTime($startDate);
$endDateObj = new DateTime($endDate);
$periodLabel = $startDateObj->format('M') . ' ' . $startDateObj->format('d') . '-' . $endDateObj->format('d') . ', ' . $startDateObj->format('Y');

// Load all active Security Guards
$sql = "SELECT 
            u.user_id,
            u.first_name,
            u.middle_name,
            u.last_name,
            CONCAT(u.first_name, ' ', 
                CASE WHEN u.middle_name IS NOT NULL AND u.middle_name != '' 
                    THEN CONCAT(UPPER(LEFT(u.middle_name, 1)), '. ') 
                    ELSE '' END, 
                u.last_name) AS name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE r.role_name = 'Security Guard' AND u.status = 'Active'
        ORDER BY u.last_name ASC, u.first_name ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$guards = $stmt->fetchAll(PDO::FETCH_ASSOC);

$calculator = new PayrollCalculator($conn);

// Prepare Dompdf (match options used by generate_payslip)
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Currency helpers
$peso = function ($n) { return '₱ ' . number_format((float)($n ?? 0), 2); };
$hours = function ($h) { return (int)floor((float)($h ?? 0)); };

// Build combined HTML using the same compact layout as generate_payslip
$html = '<style>
    /* Compact A4 layout mirroring generate_payslip */
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 5px; margin: 8px; padding: 0; }
    .payslip-wrapper { width: 25%; min-width: 140px; margin: 0; }
    .header { text-align: center; font-weight: bold; font-size: 5.3px; margin-bottom: 2px; }
    .main-table { width: 100%; border-collapse: collapse; }
    .section-title { font-weight: bold; margin: 2px 0 1px 0; font-size: 5px; }
    .earnings-table, .deductions-table, .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
    .earnings-table th, .earnings-table td, .deductions-table th, .deductions-table td, .summary-table td { padding: 0.6px 1px; font-size: 4.8px; }
    .earnings-table th, .deductions-table th { border-bottom: 1px solid #000; }
    .earnings-table .label, .deductions-table .label { width: 60%; }
    .earnings-table .hrs { width: 15%; text-align: center; }
    .earnings-table .value, .deductions-table .value { width: 25%; text-align: right; }
    .total-row { font-weight: bold; border-top: 1px solid #000; }
    .netpay-row { font-weight: bold; font-size: 5.2px; border-top: 2px solid #000; margin-top: 2px; padding-top: 2px; }
    .summary-table .label { width: 55%; }
    .summary-table .value { width: 45%; text-align: right; }
    .big { font-size: 5.2px; font-weight: bold; }
    .agency { font-size:4.8px; margin-bottom: 2px; font-weight: bold; text-align: center; }
    .empname { font-size:5px; font-weight:bold; margin-bottom: 2px; text-align: center; }
    .divider { border-top: 1px solid #000; margin: 2px 0; }
    .page-break { page-break-after: always; }
</style>';

foreach ($guards as $g) {
    // Compute payroll for the period (keep existing calculator behaviour)
    $pay = $calculator->calculatePayroll($g['user_id'], $startDate, $endDate);

    // Determine whether to show deductions (only if has any hours worked)
    $totalHours = 0.0;
    foreach (['regular_hours','ot_hours','night_diff_hours','legal_holiday_hours','holiday_ot_hours','special_holiday_hours','special_holiday_ot_hours'] as $hk) {
        if (isset($pay[$hk])) { $totalHours += (float)$pay[$hk]; }
    }
    $showDeductions = ($totalHours > 0);

    // Skip employees with no attendance or zero net/gross pay
    $computedNet = null;
    if ($showDeductions) {
        $computedNet = ($pay['net_pay'] ?? (($pay['gross_pay'] ?? 0) - ($pay['total_deductions'] ?? 0)));
    } else {
        $computedNet = ($pay['gross_pay'] ?? 0);
    }
    if (($totalHours <= 0) || (float)$computedNet <= 0) {
        continue;
    }

    $html .= '
    <div class="payslip-wrapper">
        <div class="header">
            GREEN MEADOWS SECURITY AGENCY INC.<br>
            #348 Torres Street, Brgy. Mayapa, Calamba City<br>
            PAYSLIP<br>
            CUT OFF PERIOD: ' . htmlspecialchars($periodLabel) . '
        </div>
        <table class="main-table">
            <tr><td>
                    <div class="section-title">I. EARNINGS</div>
                    <table class="earnings-table">
                        <tr><th class="label"></th><th class="hrs">HRS</th><th class="value">EARNINGS</th></tr>
                        <tr><td class="label">REG. HOURS</td><td class="hrs">' . $hours($pay['regular_hours'] ?? 0) . '</td><td class="value">' . $peso($pay['regular_hours_pay'] ?? 0) . '</td></tr>
                        <tr><td class="label">REG. OT</td><td class="hrs">' . $hours($pay['ot_hours'] ?? 0) . '</td><td class="value">' . $peso($pay['ot_pay'] ?? 0) . '</td></tr>
                        <tr><td class="label">SUN/RD/SPCL. HOL.</td><td class="hrs">' . $hours($pay['special_holiday_hours'] ?? 0) . '</td><td class="value">' . $peso($pay['special_holiday_pay'] ?? 0) . '</td></tr>
                        <tr><td class="label">SPCL. HOL. OT</td><td class="hrs">' . $hours($pay['special_holiday_ot_hours'] ?? 0) . '</td><td class="value">' . $peso($pay['special_holiday_ot_pay'] ?? 0) . '</td></tr>
                        <tr><td class="label">LEGAL HOLIDAY</td><td class="hrs">' . $hours($pay['legal_holiday_hours'] ?? 0) . '</td><td class="value">' . $peso($pay['legal_holiday_pay'] ?? 0) . '</td></tr>
                        <tr><td class="label">LEGAL HOL. OT</td><td class="hrs">' . $hours($pay['holiday_ot_hours'] ?? 0) . '</td><td class="value">' . $peso($pay['holiday_ot_pay'] ?? 0) . '</td></tr>
                        <tr><td class="label">NIGHT DIFF</td><td class="hrs">' . $hours($pay['night_diff_hours'] ?? 0) . '</td><td class="value">' . $peso($pay['night_diff_pay'] ?? 0) . '</td></tr>
                        <tr><td class="label">UNIFORM/OTHER ALLOW</td><td class="hrs">0</td><td class="value">' . $peso($pay['uniform_allowance'] ?? 0) . '</td></tr>
                        <tr><td class="label">CTP ALLOWANCE</td><td class="hrs">0</td><td class="value">' . $peso($pay['ctp_allowance'] ?? 0) . '</td></tr>
                        <tr><td class="label">RETROACTIVE</td><td class="hrs">0</td><td class="value">' . $peso($pay['retroactive_pay'] ?? 0) . '</td></tr>
                        <tr class="total-row"><td class="label">GROSS PAY</td><td class="hrs"></td><td class="value">' . $peso($pay['gross_pay'] ?? 0) . '</td></tr>
                    </table>';

    if ($showDeductions) {
        $html .= '
                    <div class="section-title">II. DEDUCTIONS</div>
                    <table class="deductions-table">
                        <tr><td class="label">SSS</td><td class="value">' . $peso($pay['sss'] ?? 0) . '</td></tr>
                        <tr><td class="label">PHILHEALTH</td><td class="value">' . $peso($pay['philhealth'] ?? 0) . '</td></tr>
                        <tr><td class="label">PAG-IBIG</td><td class="value">' . $peso($pay['pagibig'] ?? 0) . '</td></tr>
                        <tr><td class="label">LATE/UNDERTIME</td><td class="value">' . $peso(($pay['late_undertime_deduction'] ?? $pay['late_undertime'] ?? 0)) . '</td></tr>
                        <tr><td class="label">CASH ADVANCE</td><td class="value">' . $peso($pay['cash_advance'] ?? 0) . '</td></tr>
                        <tr><td class="label">CASH BOND</td><td class="value">' . $peso($pay['cash_bond'] ?? 0) . '</td></tr>
                        <tr><td class="label">OTHERS</td><td class="value">' . $peso($pay['other_deductions'] ?? 0) . '</td></tr>
                        <tr class="total-row"><td class="label">TOTAL DEDUCTIONS</td><td class="value">' . $peso($pay['total_deductions'] ?? 0) . '</td></tr>
                    </table>';
    }

    $net = (float)$computedNet;

    $html .= '
                    <div class="netpay-row">NET PAY: ' . $peso($net) . '</div>
                    <div class="divider"></div>
                    <div class="agency">GREEN MEADOWS SECURITY AGENCY INC.</div>
                    <div class="empname">' . htmlspecialchars($g['name']) . '</div>
                    <table class="summary-table">
                        <tr><td class="label">PERIOD</td><td class="value">' . htmlspecialchars($periodLabel) . '</td></tr>
                        <tr><td class="label">NET PAY</td><td class="value">' . '<b>' . $peso($net) . '</b>' . '</td></tr>
                    </table>
                </td></tr>
        </table>
    </div><div class="page-break"></div>';
}

$dompdf->loadHtml($html);
// A4 portrait to match generate_payslip
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = 'Payslips_' . str_replace('-', '', $month) . '_' . str_replace([' ', ':'], '', $dateRange) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
