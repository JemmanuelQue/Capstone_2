<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../accounting/payroll_calculation/unified_payroll_calculator.php';
if (!validateSession($conn, 5)) { die('User not logged in or session expired. Please log in again.'); }

use Dompdf\Dompdf;
use Dompdf\Options;

// Get parameters from URL and session
$user_id = $_SESSION['user_id'] ?? null;
$start_date = $_GET['start'] ?? null;
$end_date = $_GET['end'] ?? null;

// Validate required parameters
if (!$user_id) {
    die('User not logged in or session expired. Please log in again.');
}

if (!$start_date || !$end_date) {
    die('Missing date parameters. Please provide start and end dates.');
}

// Always get guard's latest active (prefer primary) location and daily rate for display/fallbacks
$rate_stmt = $conn->prepare("SELECT gl.daily_rate, gl.location_name 
                                FROM guard_locations gl 
                                WHERE gl.user_id = ? AND gl.is_active = 1 
                                ORDER BY gl.is_primary DESC LIMIT 1");
$rate_stmt->execute([$user_id]);
$rate_info = $rate_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$daily_rate = isset($rate_info['daily_rate']) ? (float)$rate_info['daily_rate'] : 540.0; // sensible default
$default_location = $rate_info['location_name'] ?? 'Not assigned';

// Fetch user and payroll data
$stmt = $conn->prepare("SELECT 
    CONCAT(First_Name, ' ', 
      CASE WHEN middle_name IS NOT NULL AND middle_name != '' 
    THEN CONCAT(UPPER(SUBSTRING(middle_name, 1, 1)), '. ') 
        ELSE '' END, 
    Last_Name) AS name,
    phone_number,
    Email
    FROM users WHERE User_ID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die('User not found.');

// Format period for display
$startDateObj = new DateTime($start_date);
$endDateObj = new DateTime($end_date);
$monthLabel = $startDateObj->format('M');
$yearLabel = $startDateObj->format('Y');
$period = $monthLabel . ' ' . $startDateObj->format('d') . '-' . $endDateObj->format('d') . ', ' . $yearLabel;

// Initialize the payroll calculator (same as accounting version)
$calculator = new PayrollCalculator($conn);

// Try to get saved payroll data first
$payroll_stmt = $conn->prepare("SELECT * FROM payroll 
                              WHERE User_ID = ? 
                              AND Period_Start = ? 
                              AND Period_End = ?");
$payroll_stmt->execute([$user_id, $start_date, $end_date]);
$saved_payroll = $payroll_stmt->fetch(PDO::FETCH_ASSOC);

// If saved payroll exists, prefer it, but only if it has meaningful values
if ($saved_payroll) {
    $payroll = [
        "regular_hours_pay" => (float)($saved_payroll['Reg_Earnings'] ?? 0),
        "ot_pay" => (float)($saved_payroll['OT_Earnings'] ?? 0),
        "special_holiday_pay" => (float)($saved_payroll['Holiday_Earnings'] ?? 0),
        "special_holiday_ot_pay" => 0, // Not in table
        "legal_holiday_pay" => 0, // Not in table
        "night_diff_pay" => 0, // Not in table
        "uniform_allowance" => (float)($saved_payroll['Uniform_Allowance'] ?? 0),
        "ctp_allowance" => 0, // Not in table
        "retroactive_pay" => 0, // Not in table
        "gross_pay" => (float)($saved_payroll['Gross_Pay'] ?? 0),
        "tax" => (float)($saved_payroll['Tax'] ?? 0),
        "sss" => (float)($saved_payroll['SSS'] ?? 0),
        "philhealth" => (float)($saved_payroll['PhilHealth'] ?? 0),
        "pagibig" => (float)($saved_payroll['PagIbig'] ?? 0),
        "sss_loan" => (float)($saved_payroll['SSS_Loan'] ?? 0),
        "pagibig_loan" => (float)($saved_payroll['PagIbig_Loan'] ?? 0),
        "late_undertime" => (float)($saved_payroll['Late_Undertime'] ?? 0),
        "cash_advance" => (float)($saved_payroll['Cash_Advances'] ?? 0),
        "cash_bond" => (float)($saved_payroll['Cash_Bond'] ?? 0),
        "other_deductions" => 0, // Not in table
        "total_deductions" => (float)($saved_payroll['Total_Deductions'] ?? 0),
        "net_pay" => (float)($saved_payroll['Net_Salary'] ?? 0),
        "hours_worked" => (float)($saved_payroll['Reg_Hours'] ?? 0),
        "location" => $default_location
    ];

    // If saved payroll is effectively zero, fall back to calculator
    $allZero = ($payroll['gross_pay'] == 0) && ($payroll['regular_hours_pay'] == 0) && ($payroll['ot_pay'] == 0) && ($payroll['net_pay'] == 0);
    if ($allZero) {
        $payroll = $calculator->calculatePayrollForGuard($user_id, null, null, $start_date, $end_date) ?: [];
    }
} else {
    // If no saved payroll, calculate it (same as accounting version)
    $payroll = $calculator->calculatePayrollForGuard($user_id, null, null, $start_date, $end_date) ?: [];
}

// If calculator returned something, ensure location/hours are present
if (!empty($payroll)) {
    if (!isset($payroll['location'])) {
        $payroll['location'] = $default_location;
    }
}

// If still no data or zero gross, generate an estimated payslip based on attendance (hours-based)
if (empty($payroll) || (!empty($payroll) && (float)($payroll['gross_pay'] ?? 0) == 0)) {
    // Calculate hours worked from attendance records with better precision
    $hours_stmt = $conn->prepare("SELECT 
                               SUM(
                                 CASE WHEN Time_Out IS NOT NULL THEN 
                                   TIMESTAMPDIFF(SECOND, Time_In, Time_Out)
                                 ELSE 0 END
                               ) as total_seconds
                               FROM attendance
                               WHERE User_ID = ? AND DATE(Time_In) BETWEEN ? AND ?");
    $hours_stmt->execute([$user_id, $start_date, $end_date]);
    $hours_data = $hours_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total_seconds = (int)($hours_data['total_seconds'] ?? 0);
    $total_hours = $total_seconds > 0 ? round($total_seconds / 3600, 2) : 0;

    // Simple calculation (8 hours = 1 day)
    $hourly_rate = $daily_rate / 8.0;
    $regular_pay = $total_hours * $hourly_rate;

    // Create estimated payroll
    $payroll = [
        "regular_hours_pay" => $regular_pay,
        "ot_pay" => 0,
        "special_holiday_pay" => 0,
        "special_holiday_ot_pay" => 0,
        "legal_holiday_pay" => 0,
        "night_diff_pay" => 0,
        "uniform_allowance" => 0,
        "ctp_allowance" => 0,
        "retroactive_pay" => 0,
        "gross_pay" => $regular_pay,
        "tax" => 0,
        "sss" => 0,
        "philhealth" => 0,
        "pagibig" => 0,
        "sss_loan" => 0,
        "pagibig_loan" => 0,
        "late_undertime" => 0,
        "cash_advance" => 0,
        "cash_bond" => 0,
        "other_deductions" => 0,
        "total_deductions" => 0,
        "net_pay" => $regular_pay,
        "is_estimated" => true,
        "location" => $default_location,
        "hours_worked" => $total_hours
    ];
}

// For calculator/saved paths without hours_worked, compute it for display
if (!isset($payroll['hours_worked'])) {
    $hours_stmt2 = $conn->prepare("SELECT 
                               SUM(
                                 CASE WHEN Time_Out IS NOT NULL THEN 
                                   TIMESTAMPDIFF(SECOND, Time_In, Time_Out)
                                 ELSE 0 END
                               ) as total_seconds
                               FROM attendance
                               WHERE User_ID = ? AND DATE(Time_In) BETWEEN ? AND ?");
    $hours_stmt2->execute([$user_id, $start_date, $end_date]);
    $hours_data2 = $hours_stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
    $total_seconds2 = (int)($hours_data2['total_seconds'] ?? 0);
    $payroll['hours_worked'] = $total_seconds2 > 0 ? round($total_seconds2 / 3600, 2) : 0;
}

// Generate HTML content with watermark for estimated payslips
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
    .container {
        width: 50%;
        margin: 0;
        padding: 0;
    }
</style>
<div class="container">
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
                <tr><td class="label">REG. HOURS</td><td class="hrs">' . ($payroll["hours_worked"] ?? 0) . '</td><td class="value">₱ ' . number_format($payroll["regular_hours_pay"] ?? 0, 2) . '</td></tr>
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
                <tr><td class="label">Contact Number</td><td class="value">' . htmlspecialchars($user['phone_number'] ?? 'N/A') . '</td></tr>
                <tr><td class="label">Location</td><td class="value">' . htmlspecialchars($payroll["location"] ?? $default_location) . '</td></tr>
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

// Set filename with guard name and period
$filename = 'Guard_Payslip_' . preg_replace('/[^a-zA-Z0-9]/', '_', $user['name']) . '_' . $start_date . '_to_' . $end_date . '.pdf';
$dompdf->stream($filename, ['Attachment' => 0]);
exit;