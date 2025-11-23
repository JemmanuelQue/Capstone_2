<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/payroll_calculation/unified_payroll_calculator.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Get parameters
$user_id = $_GET['user_id'] ?? null;
$month = $_GET['month'] ?? date('Y-m');
$dateRange = $_GET['dateRange'] ?? '1-15';
// Determine month last day for dynamic cutoff parsing
$lastDayOfMonth = date('t', strtotime($month . '-01'));

// Normalize legacy full/second-half patterns to actual month length
if (preg_match('/^1-3[01]$/', $dateRange) || preg_match('/^1-2[89]$/', $dateRange) || preg_match('/^1-30$/', $dateRange)) {
    $dateRange = '1-' . $lastDayOfMonth;
} elseif (preg_match('/^16-3[01]$/', $dateRange) || preg_match('/^16-2[89]$/', $dateRange) || preg_match('/^16-30$/', $dateRange)) {
    $dateRange = '16-' . $lastDayOfMonth;
}

if (!$user_id) {
    die('No user specified.');
}

// Parse month and year for payroll calculation
$parts = explode('-', $month);
$year = $parts[0];
$monthNum = $parts[1];

// Calculate the correct date range
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
    // Generic custom pattern fallback
    $dStart = (int)$m[1];
    $dEnd = (int)$m[2];
    if ($dStart < 1) $dStart = 1;
    if ($dEnd > (int)$lastDayOfMonth) $dEnd = (int)$lastDayOfMonth;
    $startDate = sprintf('%s-%02d', $month, $dStart);
    $endDate = sprintf('%s-%02d', $month, $dEnd);
} else {
    // Safety fallback
    $dateRange = '1-15';
    $startDate = "$month-01";
    $endDate = "$month-15";
}

// Fetch user and payroll data
$stmt = $conn->prepare("SELECT 
        CONCAT(First_Name, ' ', 
            CASE WHEN middle_name IS NOT NULL AND middle_name != '' 
                THEN CONCAT(UPPER(SUBSTRING(middle_name, 1, 1)), '. ') 
                ELSE '' END, 
        Last_Name) AS name 
        FROM users WHERE User_ID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die('User not found.');

$calculator = new PayrollCalculator($conn);

$isFullMonth = ($dateRange === '1-' . $lastDayOfMonth);

if ($isFullMonth) {
    // Always aggregate halves (even if one half has zero hours) then recompute monthly deductions
    $firstStart = $month . '-01';
    $firstEnd = $month . '-15';
    $secondStart = $month . '-16';
    $secondEnd = date('Y-m-t', strtotime($month));
    $firstHalf = $calculator->calculatePayrollForGuard($user_id, null, null, $firstStart, $firstEnd);
    $secondHalf = $calculator->calculatePayrollForGuard($user_id, null, null, $secondStart, $secondEnd);

    // Sum numeric fields
    $payroll = $firstHalf;
    foreach ($secondHalf as $key => $value) {
        if (is_numeric($value) && !in_array($key, ['hourly_rate','daily_rate'])) {
            if (!isset($payroll[$key])) { $payroll[$key] = 0; }
            $payroll[$key] += $value;
        }
    }

    // Recompute gross pay from summed earnings (late_undertime subtract)
    $payroll['gross_pay'] = 
        ($payroll['regular_hours_pay'] + 
        $payroll['ot_pay'] + 
        $payroll['night_diff_pay'] + 
        $payroll['legal_holiday_pay'] +
        $payroll['holiday_ot_pay'] + 
        $payroll['special_holiday_pay'] + 
        $payroll['special_holiday_ot_pay'] + 
        $payroll['uniform_allowance']) -
        $payroll['late_undertime'];

    // Monthly Deductions (only shown/applied on full month payslip)
    // Pag-IBIG: 2% of gross, capped at 200 if gross >= 10,000
    $pagibig = $payroll['gross_pay'] * 0.02;
    if ($payroll['gross_pay'] >= 10000 && $pagibig > 200) { $pagibig = 200.00; }
    $payroll['pagibig'] = round($pagibig, 2);

    // PhilHealth: (Gross * 5%) / 2 (employee share)
    $payroll['philhealth'] = round(($payroll['gross_pay'] * 0.05) / 2, 2);

    // SSS: determine bracket based on gross pay using table from calculator
    $sssTable = [
        ['min' => 0.00,      'max' => 5249.99,  'contribution' => 250.00],
        ['min' => 5250.00,   'max' => 5749.99,  'contribution' => 275.00],
        ['min' => 5750.00,   'max' => 6249.99,  'contribution' => 300.00],
        ['min' => 6250.00,   'max' => 6749.99,  'contribution' => 325.00],
        ['min' => 6750.00,   'max' => 7249.99,  'contribution' => 350.00],
        ['min' => 7250.00,   'max' => 7749.99,  'contribution' => 375.00],
        ['min' => 7750.00,   'max' => 8249.99,  'contribution' => 400.00],
        ['min' => 8250.00,   'max' => 8749.99,  'contribution' => 425.00],
        ['min' => 8750.00,   'max' => 9249.99,  'contribution' => 450.00],
        ['min' => 9250.00,   'max' => 9749.99,  'contribution' => 475.00],
        ['min' => 9750.00,   'max' => 10249.99, 'contribution' => 500.00],
        ['min' => 10250.00,  'max' => 10749.99, 'contribution' => 525.00],
        ['min' => 10750.00,  'max' => 11249.99, 'contribution' => 550.00],
        ['min' => 11250.00,  'max' => 11749.99, 'contribution' => 575.00],
        ['min' => 11750.00,  'max' => 12249.99, 'contribution' => 600.00],
        ['min' => 12250.00,  'max' => 12749.99, 'contribution' => 625.00],
        ['min' => 12750.00,  'max' => 13249.99, 'contribution' => 650.00],
        ['min' => 13250.00,  'max' => 13749.99, 'contribution' => 675.00],
        ['min' => 13750.00,  'max' => 14249.99, 'contribution' => 700.00],
        ['min' => 14250.00,  'max' => 14749.99, 'contribution' => 725.00],
        ['min' => 14750.00,  'max' => 15249.99, 'contribution' => 750.00],
        ['min' => 15250.00,  'max' => 15749.99, 'contribution' => 775.00],
        ['min' => 15750.00,  'max' => 16249.99, 'contribution' => 800.00],
        ['min' => 16250.00,  'max' => 16749.99, 'contribution' => 825.00],
        ['min' => 16750.00,  'max' => 17249.99, 'contribution' => 850.00],
        ['min' => 17250.00,  'max' => 17749.99, 'contribution' => 875.00],
        ['min' => 17750.00,  'max' => 18249.99, 'contribution' => 900.00],
        ['min' => 18250.00,  'max' => 18749.99, 'contribution' => 925.00],
        ['min' => 18750.00,  'max' => 19249.99, 'contribution' => 950.00],
        ['min' => 19250.00,  'max' => 19749.99, 'contribution' => 975.00],
        ['min' => 19750.00,  'max' => 999999999, 'contribution' => 1000.00]
    ];
    $sssContribution = 0;
    foreach ($sssTable as $bracket) {
        if ($payroll['gross_pay'] >= $bracket['min'] && $payroll['gross_pay'] <= $bracket['max']) {
            $sssContribution = $bracket['contribution'];
            break;
        }
    }
    $payroll['sss'] = $sssContribution;

    // Total deductions
    $payroll['total_deductions'] = $payroll['sss'] + $payroll['philhealth'] + $payroll['pagibig'] + $payroll['cash_advance'] + $payroll['cash_bond'];
    $payroll['net_pay'] = $payroll['gross_pay'] - $payroll['total_deductions'];
} else {
    // Single cutoff computation (no deductions displayed)
    $payroll = $calculator->calculatePayrollForGuard($user_id, null, null, $startDate, $endDate);
}

// Get cash advance from database
$cash_advance_sql = "SELECT Cash_Advances FROM payroll WHERE User_ID = ? AND Period_Start = ? AND Period_End = ?";
$cash_advance_stmt = $conn->prepare($cash_advance_sql);
$cash_advance_stmt->execute([$user_id, $startDate, $endDate]);
$cash_advance_result = $cash_advance_stmt->fetch(PDO::FETCH_ASSOC);
$saved_cash_advance = $cash_advance_result ? $cash_advance_result['Cash_Advances'] : 0;
$payroll["cash_advance"] = $saved_cash_advance;

// Format period for display
$startDateObj = new DateTime($startDate);
$endDateObj = new DateTime($endDate);
$monthLabel = $startDateObj->format('M');
$yearLabel = $startDateObj->format('Y');
$period = $monthLabel . ' ' . $startDateObj->format('d') . '-' . $endDateObj->format('d') . ', ' . $yearLabel;

$html = '
<style>
    /* Compact A4 layout: quarter-width left aligned */
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 5px; margin: 8px; padding: 0; }
    .payslip-wrapper { width: 25%; min-width: 140px; }
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
</style>
<div class="payslip-wrapper">
    <div class="header">
        GREEN MEADOWS SECURITY AGENCY INC.<br>
        #348 Torres Street, Brgy. Mayapa, Calamba City<br>
        PAYSLIP<br>
        CUT OFF PERIOD: ' . $period . '
    </div>
    <table class="main-table">
        <tr><td>
                <div class="section-title">I. EARNINGS</div>
                <table class="earnings-table">
                    <tr><th class="label"></th><th class="hrs">HRS</th><th class="value">EARNINGS</th></tr>
                    <tr><td class="label">REG. HOURS</td><td class="hrs">' . number_format($payroll['regular_hours'] ?? 0, 2) . '</td><td class="value">₱ ' . number_format($payroll['regular_hours_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">REG. OT</td><td class="hrs">' . number_format($payroll['ot_hours'] ?? 0, 2) . '</td><td class="value">₱ ' . number_format($payroll['ot_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">SUN/RD/SPCL. HOL.</td><td class="hrs">' . number_format($payroll['special_holiday_hours'] ?? 0, 2) . '</td><td class="value">₱ ' . number_format($payroll['special_holiday_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">SPCL. HOL. OT</td><td class="hrs">' . number_format($payroll['special_holiday_ot_hours'] ?? 0, 2) . '</td><td class="value">₱ ' . number_format($payroll['special_holiday_ot_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">LEGAL HOLIDAY</td><td class="hrs">' . number_format($payroll['legal_holiday_hours'] ?? 0, 2) . '</td><td class="value">₱ ' . number_format($payroll['legal_holiday_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">NIGHT DIFF</td><td class="hrs">' . number_format($payroll['night_diff_hours'] ?? 0, 2) . '</td><td class="value">₱ ' . number_format($payroll['night_diff_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">UNIFORM/OTHER ALLOW</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll['uniform_allowance'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">CTP ALLOWANCE</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll['ctp_allowance'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">RETROACTIVE</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll['retroactive_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">TOTAL HOURS</td><td class="hrs">' . number_format($payroll['total_hours_worked'] ?? 0, 2) . '</td><td class="value"></td></tr>
                    <tr class="total-row"><td class="label">GROSS PAY</td><td class="hrs"></td><td class="value">₱ ' . number_format($payroll['gross_pay'] ?? 0, 2) . '</td></tr>
                </table>
                ' . ($isFullMonth ? '<div class="section-title">II. DEDUCTIONS</div>
                <table class="deductions-table">
                    <tr><td class="label">SSS</td><td class="value">₱ ' . number_format($payroll['sss'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">PHILHEALTH</td><td class="value">₱ ' . number_format($payroll['philhealth'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">PAG-IBIG</td><td class="value">₱ ' . number_format($payroll['pagibig'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">CASH ADVANCES</td><td class="value">₱ ' . number_format($payroll['cash_advance'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">CASH BOND</td><td class="value">₱ ' . number_format($payroll['cash_bond'] ?? 0, 2) . '</td></tr>
                    <tr class="total-row"><td class="label">TOTAL DEDUCTIONS</td><td class="value">₱ ' . number_format($payroll['total_deductions'] ?? 0, 2) . '</td></tr>
                </table>' : '') . '
                <div class="netpay-row">NET PAY: ₱ ' . number_format($isFullMonth ? ($payroll['net_pay'] ?? 0) : ($payroll['gross_pay'] ?? 0), 2) . '</div>
                <div class="divider"></div>
                <div class="agency">GREEN MEADOWS SECURITY AGENCY INC.</div>
                <div class="empname">' . htmlspecialchars($user['name']) . '</div>
                <table class="summary-table">
                    <tr><td class="label">Period</td><td class="value">' . $period . '</td></tr>
                    <tr><td class="label">Gross</td><td class="value">₱ ' . number_format($payroll['gross_pay'] ?? 0, 2) . '</td></tr>
                    ' . ($isFullMonth ? '<tr><td class="label">Deductions</td><td class="value">₱ ' . number_format($payroll['total_deductions'] ?? 0, 2) . '</td></tr>' : '') . '
                    <tr><td class="label big">NET PAY</td><td class="value big">₱ ' . number_format($isFullMonth ? ($payroll['net_pay'] ?? 0) : ($payroll['gross_pay'] ?? 0), 2) . '</td></tr>
                </table>
            </td></tr>
    </table>
</div>
';

// Configure dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
// Back to A4 paper, compact content stays at left quarter
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Set filename: Guards [Name] - [Period], 2025.pdf
$filename = 'Guard ' . preg_replace('/[^a-zA-Z0-9 ]/', '', $user['name']) . ' - ' . $period . '.pdf';
$dompdf->stream($filename, ['Attachment' => 0]);
exit;