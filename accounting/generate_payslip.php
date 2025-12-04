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
if (preg_match('/^1-3[01]$/', $dateRange) || preg_match('/^1-2[89]$/', $dateRange)) {
    $dateRange = '1-' . $lastDayOfMonth;
} elseif (preg_match('/^16-3[01]$/', $dateRange) || preg_match('/^16-2[89]$/', $dateRange)) {
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

// Show and apply deductions only for second half (16 to last day)
$isSecondHalf = ($dateRange === '16-' . $lastDayOfMonth);

if ($isSecondHalf) {
    // Compute payroll for second half only
    $startDate = $month . '-16';
    $endDate = date('Y-m-t', strtotime($month));
    $payroll = $calculator->calculatePayrollForGuard($user_id, null, null, $startDate, $endDate);

    // Determine if there is any attendance in this period
    $hasAttendance = ((float)($payroll['total_hours_worked'] ?? 0) > 0);

    // Also compute first half to derive full-month gross for deduction brackets
    $firstStart = $month . '-01';
    $firstEnd = $month . '-15';
    $firstHalf = $calculator->calculatePayrollForGuard($user_id, null, null, $firstStart, $firstEnd);

    // Recompute gross pay from earnings (do NOT subtract late/undertime here; add as separate deduction)
    $payroll['gross_pay'] = 
        ($payroll['regular_hours_pay'] + 
        $payroll['ot_pay'] + 
        $payroll['night_diff_pay'] + 
        $payroll['legal_holiday_pay'] +
        $payroll['holiday_ot_pay'] + 
        $payroll['special_holiday_pay'] + 
        $payroll['special_holiday_ot_pay'] + 
        $payroll['uniform_allowance']);

    // Derive monthly gross by summing first-half and second-half earnings (late/undertime subtract once per half)
    $firstGross = (
        ($firstHalf['regular_hours_pay'] ?? 0) +
        ($firstHalf['ot_pay'] ?? 0) +
        ($firstHalf['night_diff_pay'] ?? 0) +
        ($firstHalf['legal_holiday_pay'] ?? 0) +
        ($firstHalf['holiday_ot_pay'] ?? 0) +
        ($firstHalf['special_holiday_pay'] ?? 0) +
        ($firstHalf['special_holiday_ot_pay'] ?? 0) +
        ($firstHalf['uniform_allowance'] ?? 0)
    );
    $monthlyGross = ($firstGross + ($payroll['gross_pay'] ?? 0));

    // Deductions (applied on second half payslip) — only if guard has attendance
    $locationRate = 0.0;
    if ($hasAttendance) {
        // Pag-IBIG premium: fixed at ₱200.00 regardless of income
        $payroll['pagibig'] = 200.00;

        // PhilHealth computation using location rate
        // Prefer canonical daily rate from DB (guards_locations.daily_rate), fallback to derived
        try {
            // Preferred: guards_locations with effective date range columns
            $rateStmt = $conn->prepare(
                "SELECT daily_rate 
                 FROM guards_locations 
                 WHERE User_ID = ? 
                   AND (COALESCE(assigned_from, '1900-01-01') <= ?) 
                   AND (COALESCE(assigned_to, '9999-12-31') >= ?) 
                 ORDER BY COALESCE(assigned_from, '1900-01-01') DESC 
                 LIMIT 1"
            );
            $rateStmt->execute([$user_id, $endDate, $startDate]);
            $rateRow = $rateStmt->fetch(PDO::FETCH_ASSOC);
            if ($rateRow && isset($rateRow['daily_rate']) && is_numeric($rateRow['daily_rate'])) {
                $locationRate = (float)$rateRow['daily_rate'];
            }
            // Fallback: join guard_assignments -> locations to retrieve locations.daily_rate
            if ($locationRate === 0.0) {
                $joinStmt = $conn->prepare(
                    "SELECT l.daily_rate 
                     FROM guard_assignments ga 
                     JOIN locations l ON l.location_id = ga.location_id 
                     WHERE ga.User_ID = ? 
                       AND (COALESCE(ga.assigned_from, '1900-01-01') <= ?) 
                       AND (COALESCE(ga.assigned_to, '9999-12-31') >= ?) 
                     ORDER BY COALESCE(ga.assigned_from, '1900-01-01') DESC 
                     LIMIT 1"
                );
                $joinStmt->execute([$user_id, $endDate, $startDate]);
                $joinRow = $joinStmt->fetch(PDO::FETCH_ASSOC);
                if ($joinRow && isset($joinRow['daily_rate']) && is_numeric($joinRow['daily_rate'])) {
                    $locationRate = (float)$joinRow['daily_rate'];
                }
            }
        } catch (Throwable $dbRateErr) {
            // ignore and fallback
        }
        if ($locationRate === 0.0) {
            // Derive location rate from regular pay and hours if not available in DB
            $regularHours = (float)($payroll['regular_hours'] ?? 0);
            $regularPay = (float)($payroll['regular_hours_pay'] ?? 0);
            if ($regularHours > 0) {
                // Convert hourly back to daily (assume 8 hours/day)
                $hourlyRate = $regularPay / $regularHours;
                $locationRate = $hourlyRate * 8.0;
            }
        }
        $philhealthBase = ($locationRate * 393.5) / 12.0;
        $payroll['philhealth'] = round(($philhealthBase * 0.05) / 2.0, 2);

        // LATE/UNDERTIME deduction: (daily_rate / shift_hours / 60) * total_late_minutes
        $shiftHours = 8.0;
        $perMinuteRate = ($locationRate > 0) ? ($locationRate / $shiftHours / 60.0) : 0.0;
        $lateMinutes = null;
        if (isset($payroll['late_minutes']) && is_numeric($payroll['late_minutes'])) {
            $lateMinutes = (float)$payroll['late_minutes'];
        } elseif (isset($firstHalf['late_minutes']) && is_numeric($firstHalf['late_minutes'])) {
            $lateMinutes = (float)$firstHalf['late_minutes'];
        } elseif (isset($payroll['late_undertime']) && $perMinuteRate > 0) {
            $lateMinutes = (float)$payroll['late_undertime'] / $perMinuteRate;
        }
        if ($lateMinutes === null) { $lateMinutes = 0.0; }
        $payroll['late_undertime_deduction'] = round($perMinuteRate * $lateMinutes, 2);

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
            if ($monthlyGross >= $bracket['min'] && $monthlyGross <= $bracket['max']) {
                $sssContribution = $bracket['contribution'];
                break;
            }
        }
        $payroll['sss'] = $sssContribution;
        // Total deductions
        $payroll['total_deductions'] = ($payroll['sss'] ?? 0) + ($payroll['philhealth'] ?? 0) + ($payroll['pagibig'] ?? 0) + ($payroll['late_undertime_deduction'] ?? 0) + ($payroll['cash_advance'] ?? 0) + ($payroll['cash_bond'] ?? 0);
        $payroll['net_pay'] = ($payroll['gross_pay'] ?? 0) - ($payroll['total_deductions'] ?? 0);
    } else {
        // No attendance: do not show or apply any deductions
        $payroll['sss'] = 0;
        $payroll['philhealth'] = 0;
        $payroll['pagibig'] = 0;
        $payroll['late_undertime_deduction'] = 0;
        $payroll['cash_advance'] = 0;
        $payroll['cash_bond'] = 0;
        $payroll['total_deductions'] = 0;
        $payroll['net_pay'] = 0;
    }
} else {
    // First half or other custom range: compute and show deductions section with govt lines blank
    $payroll = $calculator->calculatePayrollForGuard($user_id, null, null, $startDate, $endDate);

    // Determine if there is any attendance in this period
    $hasAttendance = ((float)($payroll['total_hours_worked'] ?? 0) > 0);

    // Recompute gross without subtracting late/undertime
    $payroll['gross_pay'] = 
        ($payroll['regular_hours_pay'] + 
        $payroll['ot_pay'] + 
        $payroll['night_diff_pay'] + 
        $payroll['legal_holiday_pay'] +
        $payroll['holiday_ot_pay'] + 
        $payroll['special_holiday_pay'] + 
        $payroll['special_holiday_ot_pay'] + 
        $payroll['uniform_allowance']);

    // Compute location rate for late deduction same as second half
    $locationRate = null;
    try {
        $rateStmt = $conn->prepare(
            "SELECT daily_rate 
             FROM guards_locations 
             WHERE User_ID = ? 
               AND (COALESCE(assigned_from, '1900-01-01') <= ?) 
               AND (COALESCE(assigned_to, '9999-12-31') >= ?) 
             ORDER BY COALESCE(assigned_from, '1900-01-01') DESC 
             LIMIT 1"
        );
        $rateStmt->execute([$user_id, $endDate, $startDate]);
        $rateRow = $rateStmt->fetch(PDO::FETCH_ASSOC);
        if ($rateRow && isset($rateRow['daily_rate']) && is_numeric($rateRow['daily_rate'])) {
            $locationRate = (float)$rateRow['daily_rate'];
        }
        if ($locationRate === null) {
            $joinStmt = $conn->prepare(
                "SELECT l.daily_rate 
                 FROM guard_assignments ga 
                 JOIN locations l ON l.location_id = ga.location_id 
                 WHERE ga.User_ID = ? 
                   AND (COALESCE(ga.assigned_from, '1900-01-01') <= ?) 
                   AND (COALESCE(ga.assigned_to, '9999-12-31') >= ?) 
                 ORDER BY COALESCE(ga.assigned_from, '1900-01-01') DESC 
                 LIMIT 1"
            );
            $joinStmt->execute([$user_id, $endDate, $startDate]);
            $joinRow = $joinStmt->fetch(PDO::FETCH_ASSOC);
            if ($joinRow && isset($joinRow['daily_rate']) && is_numeric($joinRow['daily_rate'])) {
                $locationRate = (float)$joinRow['daily_rate'];
            }
        }
    } catch (Throwable $dbRateErr) {
        // ignore and fallback
    }
    if ($locationRate === null) {
        $regularHours = (float)($payroll['regular_hours'] ?? 0);
        $regularPay = (float)($payroll['regular_hours_pay'] ?? 0);
        if ($regularHours > 0) {
            $hourlyRate = $regularPay / $regularHours;
            $locationRate = $hourlyRate * 8.0;
        } else {
            $locationRate = 0.0;
        }
    }
    $shiftHours = 8.0;
    $perMinuteRate = ($locationRate > 0) ? ($locationRate / $shiftHours / 60.0) : 0.0;
    $lateMinutes = null;
    if (isset($payroll['late_minutes']) && is_numeric($payroll['late_minutes'])) {
        $lateMinutes = (float)$payroll['late_minutes'];
    } elseif (isset($payroll['late_undertime']) && $perMinuteRate > 0) {
        $lateMinutes = (float)$payroll['late_undertime'] / $perMinuteRate;
    }
    if ($lateMinutes === null) { $lateMinutes = 0.0; }
    $payroll['late_undertime_deduction'] = round($perMinuteRate * $lateMinutes, 2);

    // Govt deductions remain blank for 1-15; if no attendance, hide deductions entirely
    $payroll['sss'] = null;
    $payroll['philhealth'] = null;
    $payroll['pagibig'] = null;
    if ($hasAttendance) {
        $payroll['total_deductions'] = ($payroll['late_undertime_deduction'] ?? 0) + ($payroll['cash_advance'] ?? 0) + ($payroll['cash_bond'] ?? 0);
        $payroll['net_pay'] = ($payroll['gross_pay'] ?? 0) - ($payroll['total_deductions'] ?? 0);
    } else {
        $payroll['late_undertime_deduction'] = 0;
        $payroll['cash_advance'] = 0;
        $payroll['cash_bond'] = 0;
        $payroll['total_deductions'] = 0;
        $payroll['net_pay'] = ($payroll['gross_pay'] ?? 0);
    }
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

$showDeductions = ($hasAttendance ?? false); // Only show deductions if there is attendance in the selected period

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
                    <tr><td class="label">REG. HOURS</td><td class="hrs">' . (int)floor($payroll['regular_hours'] ?? 0) . '</td><td class="value">₱ ' . number_format($payroll['regular_hours_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">REG. OT</td><td class="hrs">' . (int)floor($payroll['ot_hours'] ?? 0) . '</td><td class="value">₱ ' . number_format($payroll['ot_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">SUN/RD/SPCL. HOL.</td><td class="hrs">' . (int)floor($payroll['special_holiday_hours'] ?? 0) . '</td><td class="value">₱ ' . number_format($payroll['special_holiday_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">SPCL. HOL. OT</td><td class="hrs">' . (int)floor($payroll['special_holiday_ot_hours'] ?? 0) . '</td><td class="value">₱ ' . number_format($payroll['special_holiday_ot_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">LEGAL HOLIDAY</td><td class="hrs">' . (int)floor($payroll['legal_holiday_hours'] ?? 0) . '</td><td class="value">₱ ' . number_format($payroll['legal_holiday_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">LEGAL HOL. OT</td><td class="hrs">' . (int)floor($payroll['holiday_ot_hours'] ?? 0) . '</td><td class="value">₱ ' . number_format($payroll['holiday_ot_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">NIGHT DIFF</td><td class="hrs">' . (int)floor($payroll['night_diff_hours'] ?? 0) . '</td><td class="value">₱ ' . number_format($payroll['night_diff_pay'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">UNIFORM/OTHER ALLOW</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll['uniform_allowance'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">CTP ALLOWANCE</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll['ctp_allowance'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">RETROACTIVE</td><td class="hrs">0</td><td class="value">₱ ' . number_format($payroll['retroactive_pay'] ?? 0, 2) . '</td></tr>
                    <tr class="total-row"><td class="label">GROSS PAY</td><td class="hrs"></td><td class="value">₱ ' . number_format($payroll['gross_pay'] ?? 0, 2) . '</td></tr>
                </table>
                ' . ($showDeductions ? ('<div class="section-title">II. DEDUCTIONS</div>
                <table class="deductions-table">
                    <tr><td class="label">SSS</td><td class="value">' . ($isSecondHalf ? ('₱ ' . number_format($payroll['sss'] ?? 0, 2)) : '-') . '</td></tr>
                    <tr><td class="label">PHILHEALTH</td><td class="value">' . ($isSecondHalf ? ('₱ ' . number_format($payroll['philhealth'] ?? 0, 2)) : '-') . '</td></tr>
                    <tr><td class="label">PAG-IBIG</td><td class="value">' . ($isSecondHalf ? ('₱ ' . number_format($payroll['pagibig'] ?? 0, 2)) : '-') . '</td></tr>
                    <tr><td class="label">LATE/UNDERTIME</td><td class="value">₱ ' . number_format($payroll['late_undertime_deduction'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">CASH ADVANCES</td><td class="value">₱ ' . number_format($payroll['cash_advance'] ?? 0, 2) . '</td></tr>
                    <tr><td class="label">CASH BOND</td><td class="value">₱ ' . number_format($payroll['cash_bond'] ?? 0, 2) . '</td></tr>
                    <tr class="total-row"><td class="label">TOTAL DEDUCTIONS</td><td class="value">₱ ' . number_format($payroll['total_deductions'] ?? 0, 2) . '</td></tr>
                </table>') : '') . '
                <div class="netpay-row">NET PAY: ₱ ' . number_format(($showDeductions ? ($payroll['net_pay'] ?? 0) : ($payroll['gross_pay'] ?? 0)), 2) . '</div>
                <div class="divider"></div>
                <div class="agency">GREEN MEADOWS SECURITY AGENCY INC.</div>
                <div class="empname">' . htmlspecialchars($user['name']) . '</div>
                <table class="summary-table">
                    <tr><td class="label">Period</td><td class="value">' . $period . '</td></tr>
                    <tr><td class="label">Gross</td><td class="value">₱ ' . number_format($payroll['gross_pay'] ?? 0, 2) . '</td></tr>
                    ' . ($isSecondHalf && $showDeductions ? '<tr><td class="label">Deductions</td><td class="value">₱ ' . number_format($payroll['total_deductions'] ?? 0, 2) . '</td></tr>' : '') . '
                    <tr><td class="label big">NET PAY</td><td class="value big">₱ ' . number_format(($showDeductions ? ($payroll['net_pay'] ?? 0) : ($payroll['gross_pay'] ?? 0)), 2) . '</td></tr>
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