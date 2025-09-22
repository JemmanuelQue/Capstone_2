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
} else {
    $startDate = "$month-16";
    $endDate = date('Y-m-t', strtotime($month));
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
$payroll = $calculator->calculatePayrollForGuard($user_id, null, null, $startDate, $endDate);

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
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 6px; margin: 0; padding: 0; }
    .header { text-align: center; font-weight: bold; font-size: 6.5px; margin-bottom: 1px; }
    .main-table { width: 100%; border-collapse: collapse; }
    .main-table td { vertical-align: top; padding: 0 2px 0 0; }
    .left-col { width: 65%; }
    .right-col { width: 35%; }
    .section-title { font-weight: bold; margin: 1px 0 1px 0; font-size: 6px; }
    .earnings-table, .deductions-table { width: 100%; border-collapse: collapse; margin-bottom: 1px; }
    .earnings-table th, .earnings-table td, .deductions-table th, .deductions-table td { padding: 0.3px 0.5px; font-size: 5.5px; }
    .earnings-table th, .deductions-table th { border-bottom: 1px solid #000; }
    .earnings-table .label, .deductions-table .label { width: 50%; }
    .earnings-table .hrs { width: 10%; text-align: center; }
    .earnings-table .value, .deductions-table .value { width: 40%; text-align: right; }
    .total-row { font-weight: bold; border-top: 1px solid #000; }
    .netpay-row { font-weight: bold; font-size: 6px; border-top: 2px solid #000; margin-top: 1px; }
    .summary-table { width: 100%; margin-top: 3px; }
    .summary-table td { font-size: 5.5px; padding: 0.3px 0.5px; }
    .summary-table .label { width: 60%; }
    .summary-table .value { width: 40%; text-align: right; }
    .big { font-size: 6px; font-weight: bold; }
    .hr { border-bottom: 1px solid #000; margin: 1px 0; }
    .agency { font-size:5.5px; margin-bottom: 1px; font-weight: bold; text-align: right; }
    .empname { font-size:6px; font-weight:bold; margin-bottom: 1px; text-align: right; }
    .netpay-row { margin-top: 2px; }
    .container { width: 50%; margin: 0; padding: 0; }
    .wrapper { width: 100%; }
    .left-position { text-align: left; }
    .right { text-align: right; }
    .small { font-size: 5.5px; }
</style>
<div class="container left-position">
<div class="header">
    GREEN MEADOWS SECURITY AGENCY INC.<br>
    #348 Torres Street, Brgy. Mayapa, Calamba City<br>
    PAYSLIP<br>
    CUT OFF PERIOD: ' . $period . '
</div>
<table class="main-table">
    <tr>
        <td class="left-col">
            <div class="section-title">I. EARNINGS</div>
            <table class="earnings-table">
                <tr><th class="label">&nbsp;</th><th class="hrs">HRS</th><th class="value">EARNINGS</th></tr>
                <tr><td class="label">REG. HOURS</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll["regular_hours_pay"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">REG. OT</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll["ot_pay"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">SUN/RD/SPCL. HOL.</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll["special_holiday_pay"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">SPCL. HOL. OT</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll["special_holiday_ot_pay"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">LEGAL HOLIDAY</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll["legal_holiday_pay"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">NIGHT DIFF</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll["night_diff_pay"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">UNIFORM/OTHER ALLOW</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll["uniform_allowance"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">CTP ALLOWANCE</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll["ctp_allowance"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">RETROACTIVE</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll["retroactive_pay"] ?? 0, 2) . '</td></tr>
                <tr class="total-row"><td class="label">GROSS PAY</td><td class="hrs"></td><td class="value">₱ ' . number_format($payroll["gross_pay"] ?? 0, 2) . '</td></tr>
            </table>
            <div class="section-title">II. DEDUCTIONS</div>
            <table class="deductions-table">
                <tr><td class="label">TAX W/HELD</td><td class="value">₱ ' . number_format($payroll["tax"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">SSS</td><td class="value">₱ ' . number_format($payroll["sss"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">PHILHEALTH</td><td class="value">₱ ' . number_format($payroll["philhealth"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">PAG-IBIG</td><td class="value">₱ ' . number_format($payroll["pagibig"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">SSS LOAN</td><td class="value">₱ ' . number_format($payroll["sss_loan"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">PAG-IBIG LOAN</td><td class="value">₱ ' . number_format($payroll["pagibig_loan"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">LATE/UNDERTIME</td><td class="value">₱ ' . number_format($payroll["late_undertime"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">CASH ADVANCES</td><td class="value">₱ ' . number_format($payroll["cash_advance"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">CASH BOND</td><td class="value">₱ ' . number_format($payroll["cash_bond"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">OTHERS</td><td class="value">₱ ' . number_format($payroll["other_deductions"] ?? 0, 2) . '</td></tr>
                <tr class="total-row"><td class="label">TOTAL DEDUCTIONS</td><td class="value">₱ ' . number_format($payroll["total_deductions"] ?? 0, 2) . '</td></tr>
            </table>
            <div class="netpay-row">NET PAY: ₱ ' . number_format($payroll["net_pay"] ?? 0, 2) . '</div>
        </td>
        <td class="right-col">
            <div class="agency">GREEN MEADOWS SECURITY AGENCY INC.<br>#348 Torres Street, Brgy. Mayapa, Calamba City</div>
            <div class="empname">' . htmlspecialchars($user['name']) . '</div>
            <table class="summary-table">
                <tr><td class="label">Period Covered</td><td class="value">' . $period . '</td></tr>
                <tr><td class="label">Gross Salary</td><td class="value">₱ ' . number_format($payroll["gross_pay"] ?? 0, 2) . '</td></tr>
                <tr><td class="label">Less: Total Deductions</td><td class="value">₱ ' . number_format($payroll["total_deductions"] ?? 0, 2) . '</td></tr>
                <tr><td colspan="2" class="hr"></td></tr>
                <tr><td class="label big">TOTAL NET SALARY</td><td class="value big">₱ ' . number_format($payroll["net_pay"] ?? 0, 2) . '</td></tr>
            </table>
        </td>
    </tr>
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
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Set filename: Guards [Name] - [Period], 2025.pdf
$filename = 'Guard ' . preg_replace('/[^a-zA-Z0-9 ]/', '', $user['name']) . ' - ' . $period . '.pdf';
$dompdf->stream($filename, ['Attachment' => 0]);
exit;