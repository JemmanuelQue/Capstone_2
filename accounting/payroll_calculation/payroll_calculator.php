<?php
require_once 'holiday_calculator.php';

class PayrollCalculator {
    private $hourlyRate;
    private $conn;
    private const UNIFORM_ALLOWANCE = 50.00;
    private $holidayCalculator;

    public function __construct($conn, $hourlyRate = 67.5) {
        $this->conn = $conn;
        $this->hourlyRate = $hourlyRate;
        $this->holidayCalculator = new HolidayCalculator($conn);
        // Initialize holidays table
        $this->holidayCalculator->initializeHolidaysTable();
    }

    public function calculatePayrollForGuard($userId, $month = null, $year = null, $startDate = null, $endDate = null) {
        if ($startDate && $endDate) {
            // Use date range
            $attendance_sql = "SELECT time_in, time_out, DAYOFWEEK(time_in) as day_of_week
                             FROM attendance 
                             WHERE User_ID = ? 
                             AND DATE(time_in) BETWEEN ? AND ?";
            $stmt = $this->conn->prepare($attendance_sql);
            $stmt->execute([$userId, $startDate, $endDate]);
        } else {
            // Use month/year fallback
            if (!$month) $month = date('m');
            if (!$year) $year = date('Y');
            $attendance_sql = "SELECT time_in, time_out, DAYOFWEEK(time_in) as day_of_week
                             FROM attendance 
                             WHERE User_ID = ? 
                             AND MONTH(time_in) = ? 
                             AND YEAR(time_in) = ?";
            $stmt = $this->conn->prepare($attendance_sql);
            $stmt->execute([$userId, $month, $year]);
        }

        // Check for attendance in the period
        $attendance_count = $stmt->rowCount();
        if ($attendance_count == 0) {
            // No attendance: show all zeros
            return [
                'regular_hours_pay' => 0,
                'ot_pay' => 0,
                'special_holiday_pay' => 0,
                'special_holiday_ot_pay' => 0,
                'legal_holiday_pay' => 0,
                'night_diff_pay' => 0,
                'uniform_allowance' => 0,
                'ctp_allowance' => 0,
                'retroactive_pay' => 0,
                'gross_pay' => 0,
                'tax' => 0,
                'sss' => 0,
                'philhealth' => 0,
                'pagibig' => 0,
                'sss_loan' => 0,
                'pagibig_loan' => 0,
                'late_undertime' => 0,
                'cash_advance' => 0,
                'cash_bond' => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_pay' => 0
            ];
        }

        // Get cash advance from payroll table
        $cash_advance = 0;
        if ($startDate && $endDate) {
            $cash_advance_sql = "SELECT Cash_Advances FROM payroll WHERE User_ID = ? AND Period_Start = ? AND Period_End = ?";
            $cash_advance_stmt = $this->conn->prepare($cash_advance_sql);
            $cash_advance_stmt->execute([$userId, $startDate, $endDate]);
            $cash_advance_result = $cash_advance_stmt->fetch(PDO::FETCH_ASSOC);
            if ($cash_advance_result) {
                $cash_advance = (float)$cash_advance_result['Cash_Advances'];
            }
        }

        // Calculate total cash bond paid so far
        $cash_bond_limit = 10000;
        $cash_bond_per_period = 100;
        $cash_bond_total_sql = "SELECT SUM(Cash_Bond) as total_paid FROM payroll WHERE User_ID = ?";
        $cash_bond_total_stmt = $this->conn->prepare($cash_bond_total_sql);
        $cash_bond_total_stmt->execute([$userId]);
        $cash_bond_total_result = $cash_bond_total_stmt->fetch(PDO::FETCH_ASSOC);
        $total_cash_bond_paid = $cash_bond_total_result && $cash_bond_total_result['total_paid'] ? (float)$cash_bond_total_result['total_paid'] : 0.0;

        // Determine if we should deduct this period
        if ($total_cash_bond_paid >= $cash_bond_limit) {
            $cash_bond = 0;
            $cash_bond_limit_reached = true;
        } else {
            $remaining = $cash_bond_limit - $total_cash_bond_paid;
            $cash_bond = min($cash_bond_per_period, $remaining);
            $cash_bond_limit_reached = ($total_cash_bond_paid + $cash_bond) >= $cash_bond_limit;
        }

        $regular_hours_pay = 0;
        $ot_pay = 0;
        $night_diff_pay = 0;
        $late_undertime = 0;
        $total_hours_worked = 0;
        $holiday_pay = 0;
        $holiday_ot_pay = 0;
        $special_holiday_pay = 0;
        $special_holiday_ot_pay = 0;

        while ($att = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($att['time_in'] && $att['time_out']) {
                $is_rest_day = in_array($att['day_of_week'], [1, 7]); // Sunday = 1, Saturday = 7
                $date = date('Y-m-d', strtotime($att['time_in']));
                
                $this->calculateShiftHoursManual(
                    $att['time_in'],
                    $att['time_out'],
                    $regular_hours_pay,
                    $ot_pay,
                    $night_diff_pay,
                    $late_undertime,
                    $total_hours_worked,
                    $holiday_pay,
                    $holiday_ot_pay,
                    $special_holiday_pay,
                    $special_holiday_ot_pay,
                    $is_rest_day
                );
            }
        }

        // Calculate total gross pay including allowance
        $total_gross_pay = $regular_hours_pay + $ot_pay + $night_diff_pay + $holiday_pay + 
                          $holiday_ot_pay + $special_holiday_pay + $special_holiday_ot_pay + 
                          self::UNIFORM_ALLOWANCE;

        // --- SSS deduction fix for bi-weekly payroll ---
        // Calculate monthly gross pay for SSS deduction
        if ($startDate && $endDate) {
            $month_start = date('Y-m-01', strtotime($startDate));
            $month_end = date('Y-m-t', strtotime($startDate));
            $monthly_gross_sql = "SELECT SUM(Gross_Pay) as monthly_gross FROM payroll WHERE User_ID = ? AND Period_Start >= ? AND Period_End <= ?";
            $monthly_gross_stmt = $this->conn->prepare($monthly_gross_sql);
            $monthly_gross_stmt->execute([$userId, $month_start, $month_end]);
            $monthly_gross_result = $monthly_gross_stmt->fetch(PDO::FETCH_ASSOC);
            $monthly_gross_pay = $monthly_gross_result && $monthly_gross_result['monthly_gross'] ? (float)$monthly_gross_result['monthly_gross'] : $total_gross_pay;
        } else {
            $monthly_gross_pay = $total_gross_pay;
        }
        // Use monthly gross pay to determine SSS deduction
        $full_month_sss = $this->calculateSSSDeduction($monthly_gross_pay);
        // For bi-weekly, divide by 2
        $sss_deduction = $full_month_sss / 2;
        // --- END SSS deduction fix ---
        
        // Calculate Philhealth (2.9166% of gross pay)
        $philhealth = $total_gross_pay * 0.029166;
        
        // Fixed Pag-IBIG deduction of ₱200
        $pagibig = 200.00;
        
        $total_deductions = $sss_deduction + $late_undertime + $philhealth + $pagibig + $cash_advance + $cash_bond;
        
        // Calculate final net pay
        $net_pay = $total_gross_pay - $total_deductions;

        return [
            'regular_hours_pay' => $regular_hours_pay,
            'ot_pay' => $ot_pay,
            'special_holiday_pay' => $special_holiday_pay,
            'special_holiday_ot_pay' => $special_holiday_ot_pay,
            'legal_holiday_pay' => $holiday_pay,
            'night_diff_pay' => $night_diff_pay,
            'uniform_allowance' => self::UNIFORM_ALLOWANCE,
            'ctp_allowance' => 0,
            'retroactive_pay' => 0,
            'gross_pay' => $total_gross_pay,
            'tax' => 0,
            'sss' => $sss_deduction,
            'philhealth' => $philhealth,
            'pagibig' => $pagibig,
            'sss_loan' => 0,
            'pagibig_loan' => 0,
            'late_undertime' => $late_undertime,
            'cash_advance' => $cash_advance,
            'cash_bond' => $cash_bond,
            'cash_bond_limit_reached' => $cash_bond_limit_reached,
            'other_deductions' => 0,
            'total_deductions' => $total_deductions,
            'net_pay' => $net_pay
        ];
    }

    private function calculateShiftHoursManual($time_in, $time_out, &$regular_hours_pay, &$ot_pay, &$night_diff_pay, 
                                             &$late_undertime, &$total_hours_worked, &$holiday_pay, &$holiday_ot_pay,
                                             &$special_holiday_pay, &$special_holiday_ot_pay, $is_rest_day = false) {
        $in = new DateTime($time_in);
        $out = new DateTime($time_out);
        if ($out <= $in) $out->modify('+1 day');
        
        $date = $in->format('Y-m-d');
        $holiday_type = $this->holidayCalculator->getHolidayType($date);
    
        // Calculate shift duration
        $shift_hours = ($out->getTimestamp() - $in->getTimestamp()) / 3600;
        $total_hours_worked += $shift_hours;
    
        // Morning Shift (6:00 AM - 2:00 PM)
        if ($in->format('H:i') <= '14:00') {
            $standard_start = new DateTime($in->format('Y-m-d') . ' 06:00:00');
            $normal_end = new DateTime($in->format('Y-m-d') . ' 14:00:00');
            
            // Handle late arrival (only penalize if arriving AFTER 6:00 AM)
            if ($in > $standard_start) {
                $late_hours = ($in->getTimestamp() - $standard_start->getTimestamp()) / 3600;
                $late_undertime += $late_hours * $this->hourlyRate;
                
                // For late arrivals, calculate remaining regular hours
                $regular_hours = max(0, ($normal_end->getTimestamp() - $in->getTimestamp()) / 3600);
                $regular_hours = min(8, $regular_hours);
            } else {
                // For on-time or early arrivals, give full 8 hours
                $regular_hours = 8;
            }
            
            if ($holiday_type == 'Regular') {
                $holiday_pay += $this->holidayCalculator->calculateHolidayPay($date, $regular_hours, $is_rest_day);
            } elseif ($holiday_type == 'Special Non-Working') {
                $special_holiday_pay += $this->holidayCalculator->calculateHolidayPay($date, $regular_hours, $is_rest_day);
            } else {
                $regular_hours_pay += $regular_hours * $this->hourlyRate;
            }
            
            // Overtime hours (after 2:00 PM)
            if ($out > $normal_end) {
                // Calculate overtime hours, but only count full hours
                $overtime_seconds = $out->getTimestamp() - $normal_end->getTimestamp();
                $overtime_hours = floor($overtime_seconds / 3600); // Only count full hours
                
                if ($overtime_hours > 0) {
                    if ($holiday_type == 'Regular') {
                        $holiday_ot_pay += $this->holidayCalculator->calculateHolidayOvertimePay($date, $overtime_hours, $is_rest_day, false);
                    } elseif ($holiday_type == 'Special Non-Working') {
                        $special_holiday_ot_pay += $this->holidayCalculator->calculateHolidayOvertimePay($date, $overtime_hours, $is_rest_day, false);
                    } else {
                        $ot_pay += $overtime_hours * $this->hourlyRate * 1.25;
                    }
                }
            }
        }
        // Night Shift (6:00 PM - 6:00 AM)
        elseif ($in->format('H:i') >= '18:00') {
            $nd_start = new DateTime($in->format('Y-m-d') . ' 22:00:00');
            $nd_end = clone $nd_start;
            $nd_end->modify('+4 hours'); // 02:00 AM
            
            // Regular hours (6:00 PM - 10:00 PM)
            if ($in < $nd_start) {
                $regular_hours = min(4, ($nd_start->getTimestamp() - $in->getTimestamp()) / 3600);
                if ($holiday_type == 'Regular') {
                    $holiday_pay += $this->holidayCalculator->calculateHolidayPay($date, $regular_hours, $is_rest_day);
                } elseif ($holiday_type == 'Special Non-Working') {
                    $special_holiday_pay += $this->holidayCalculator->calculateHolidayPay($date, $regular_hours, $is_rest_day);
                } else {
                    $regular_hours_pay += $regular_hours * $this->hourlyRate;
                }
            }
            
            // Night differential hours (10:00 PM - 2:00 AM)
            if ($out > $nd_start) {
                $nd_hours = min(4, ($out > $nd_end ? 4 : ($out->getTimestamp() - $nd_start->getTimestamp()) / 3600));
                if ($holiday_type == 'Regular') {
                    $holiday_pay += $this->holidayCalculator->calculateHolidayPay($date, $nd_hours, $is_rest_day);
                } elseif ($holiday_type == 'Special Non-Working') {
                    $special_holiday_pay += $this->holidayCalculator->calculateHolidayPay($date, $nd_hours, $is_rest_day);
                } else {
                    $night_diff_pay += $nd_hours * $this->hourlyRate * 1.1;
                }
            }
            
            // Night differential overtime (2:00 AM - 6:00 AM)
            if ($out > $nd_end) {
                $nd_ot_hours = min(4, ($out->getTimestamp() - $nd_end->getTimestamp()) / 3600);
                if ($holiday_type == 'Regular') {
                    $holiday_ot_pay += $this->holidayCalculator->calculateHolidayOvertimePay($date, $nd_ot_hours, $is_rest_day, true);
                } elseif ($holiday_type == 'Special Non-Working') {
                    $special_holiday_ot_pay += $this->holidayCalculator->calculateHolidayOvertimePay($date, $nd_ot_hours, $is_rest_day, true);
                } else {
                    $night_diff_pay += $nd_ot_hours * $this->hourlyRate * 1.375;
                }
            }
        }
    }

    private function calculateSSSDeduction($gross_pay) {
        $sss_guards = [
            ['min' => 0.00,      'max' => 5249.99,  'employee' => 500.00],
            ['min' => 5250.00,   'max' => 5749.99,  'employee' => 550.00],
            ['min' => 5750.00,   'max' => 6249.99,  'employee' => 600.00],
            ['min' => 6250.00,   'max' => 6749.99,  'employee' => 650.00],
            ['min' => 6750.00,   'max' => 7249.99,  'employee' => 700.00],
            ['min' => 7250.00,   'max' => 7749.99,  'employee' => 750.00],
            ['min' => 7750.00,   'max' => 8249.99,  'employee' => 800.00],
            ['min' => 8250.00,   'max' => 8749.99,  'employee' => 850.00],
            ['min' => 8750.00,   'max' => 9249.99,  'employee' => 900.00],
            ['min' => 9250.00,   'max' => 9749.99,  'employee' => 950.00],
            ['min' => 9750.00,   'max' => 10249.99, 'employee' => 1000.00],
            ['min' => 10250.00,  'max' => 10749.99, 'employee' => 1050.00],
            ['min' => 10750.00,  'max' => 11249.99, 'employee' => 1100.00],
            ['min' => 11250.00,  'max' => 11749.99, 'employee' => 1150.00],
            ['min' => 11750.00,  'max' => 12249.99, 'employee' => 1200.00],
            ['min' => 12250.00,  'max' => 12749.99, 'employee' => 1250.00],
            ['min' => 12750.00,  'max' => 13249.99, 'employee' => 1300.00],
            ['min' => 13250.00,  'max' => 13749.99, 'employee' => 1350.00],
            ['min' => 13750.00,  'max' => 14249.99, 'employee' => 1400.00],
            ['min' => 14250.00,  'max' => 14749.99, 'employee' => 1450.00],
            ['min' => 14750.00,  'max' => 15249.99, 'employee' => 1500.00],
            ['min' => 15250.00,  'max' => 15749.99, 'employee' => 1550.00],
            ['min' => 15750.00,  'max' => 16249.99, 'employee' => 1600.00],
            ['min' => 16250.00,  'max' => 16749.99, 'employee' => 1650.00],
            ['min' => 16750.00,  'max' => 17249.99, 'employee' => 1700.00],
            ['min' => 17250.00,  'max' => 17749.99, 'employee' => 1750.00],
            ['min' => 17750.00,  'max' => 18249.99, 'employee' => 1800.00],
            ['min' => 18250.00,  'max' => 18749.99, 'employee' => 1850.00],
            ['min' => 18750.00,  'max' => 19249.99, 'employee' => 1900.00],
            ['min' => 19250.00,  'max' => 19749.99, 'employee' => 1950.00],
            ['min' => 19750.00,  'max' => 20249.99, 'employee' => 2000.00],
            ['min' => 20250.00,  'max' => PHP_INT_MAX, 'employee' => 2000.00]
        ];

        foreach ($sss_guards as $sss) {
            if ($gross_pay >= $sss['min'] && $gross_pay <= $sss['max']) {
                return $sss['employee'];
            }
        }
        return 2000.00; // Maximum deduction
    }
}