<?php
/**
 * Unified Payroll Calculator
 * 
 * A comprehensive payroll calculation system that handles:
 * - Day and night shift calculations
 * - Holiday pay (based on actual database holidays)
 * - Rest day calculations
 * - Night differential
 * - Overtime calculations
 * - Location-specific rates
 */
class PayrollCalculator {
    private $conn;
    private $uniformAllowance = 50.00; // Default allowance amount
    private $defaultRate = 540.00;     // Default daily rate if no location is found
    
    // Cache for holidays
    private $holidayCache = [];
    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Main calculation function
     * 
     * @param int $userId User ID for the guard
     * @param string $startDate Start date of the payroll period (Y-m-d)
     * @param string $endDate End date of the payroll period (Y-m-d)
     * @return array Complete payroll calculation results
     */
    public function calculatePayroll($userId, $startDate, $endDate) {
        // Initialize result array with zeros
        $result = $this->getEmptyPayrollArray();
        
        // Get guard location and rate
        $locationData = $this->getGuardLocationRate($userId);
        $dailyRate = $locationData['daily_rate'] ?? $this->defaultRate;
        $hourlyRate = $dailyRate / 8; // Convert daily rate to hourly rate
        
        // Store the rates in the result for reference
        $result['hourly_rate'] = $hourlyRate;
        $result['daily_rate'] = $dailyRate;
        
        // Get attendance records for this period
        $attendance = $this->getAttendanceRecords($userId, $startDate, $endDate);
        if (empty($attendance)) {
            return $result; // Return zeros if no attendance
        }
        
        // Process each attendance record
        foreach ($attendance as $record) {
            $this->processAttendanceRecord($record, $hourlyRate, $result);
        }
        
        // Add uniform allowance
        $result['uniform_allowance'] = $this->uniformAllowance;
        
        // Calculate gross pay
        $result['gross_pay'] = 
            $result['regular_hours_pay'] + 
            $result['ot_pay'] + 
            $result['night_diff_pay'] + 
            $result['legal_holiday_pay'] +
            $result['holiday_ot_pay'] + 
            $result['special_holiday_pay'] + 
            $result['special_holiday_ot_pay'] + 
            $result['uniform_allowance'];
        
        // Calculate deductions
        $this->calculateDeductions($userId, $result, $startDate, $endDate);
        
        // Calculate net pay (gross minus deductions)
        $result['net_pay'] = $result['gross_pay'] - $result['total_deductions'];
        
        return $result;
    }
    
    /**
     * Process a single attendance record
     */
    private function processAttendanceRecord($record, $hourlyRate, &$result) {
        $timeIn = new DateTime($record['time_in']);
        $timeOut = $record['time_out'] ? new DateTime($record['time_out']) : null;
        
        // Skip if no time out
        if (!$timeOut) {
            return;
        }
        
        // Handle overnight shift (if time_out is earlier than time_in)
        if ($timeOut < $timeIn) {
            $timeOut->modify('+1 day');
        }
        
        // Get day of the week (1=Monday, 7=Sunday)
        $dayOfWeek = (int)$timeIn->format('N');
        $isRestDay = ($dayOfWeek == 6 || $dayOfWeek == 7); // Saturday or Sunday
        
        // Get holiday type from database for this date
        $date = $timeIn->format('Y-m-d');
        $holidayType = $this->getHolidayType($date);
        
        // Get hour of day for time in/out
        $hourIn = (int)$timeIn->format('H');
        
        // Calculate late deduction based on shift
        $this->calculateLateDeduction($timeIn, $hourlyRate, $result);
        
        // Determine shift type based on time in
        if ($hourIn >= 6 && $hourIn < 18) {
            // Day shift (6am to 6pm)
            $this->calculateDayShift($timeIn, $timeOut, $hourlyRate, $isRestDay, $holidayType, $result);
        } else {
            // Night shift (6pm to 6am)
            $this->calculateNightShift($timeIn, $timeOut, $hourlyRate, $isRestDay, $holidayType, $result);
        }
    }
    
    /**
     * Calculate late deduction based on shift time
     * Late formula: Late Minutes × (Location Rate ÷ 8 ÷ 60)
     */
    private function calculateLateDeduction($timeIn, $hourlyRate, &$result) {
        $hourOfDay = (int)$timeIn->format('H');
        $minuteOfHour = (int)$timeIn->format('i');
        $secondOfMinute = (int)$timeIn->format('s');
        
        // Determine expected start time based on shift
        if ($hourOfDay >= 6 && $hourOfDay < 18) {
            // Morning shift (6:00 AM - 6:00 PM)
            $expectedStartTime = new DateTime($timeIn->format('Y-m-d') . ' 06:00:00');
        } else {
            // Night shift (6:00 PM - 6:00 AM)
            $expectedStartTime = new DateTime($timeIn->format('Y-m-d') . ' 18:00:00');
        }
        
        // If employee is late (clock in after expected start time)
        if ($timeIn > $expectedStartTime) {
            // Calculate late minutes
            $lateSeconds = $timeIn->getTimestamp() - $expectedStartTime->getTimestamp();
            $lateMinutes = ceil($lateSeconds / 60); // Round up to the next minute
            
            // Calculate late deduction using the formula: Late Minutes × (Location Rate ÷ 8 ÷ 60)
            // hourlyRate is already Daily Rate ÷ 8, so we just divide by 60 for per minute rate
            $perMinuteRate = $hourlyRate / 60;
            $lateDeduction = $lateMinutes * $perMinuteRate;
            
            // Add late deduction to the result
            $result['late_undertime'] += $lateDeduction;
        }
    }
    
    /**
     * Calculate day shift (6am to 6pm)
     * - Regular time: 6am to 2pm (8 hours)
     * - Overtime: 2pm to 6pm (4 hours with OT)
     */
    private function calculateDayShift($timeIn, $timeOut, $hourlyRate, $isRestDay, $holidayType, &$result) {
        $date = $timeIn->format('Y-m-d');
        
        // Create timestamps for shift boundaries
        $regularEnd = new DateTime($date . ' 14:00:00'); // 2:00 PM
        $shiftEnd = new DateTime($date . ' 18:00:00');   // 6:00 PM
        $nightDiffStart = new DateTime($date . ' 22:00:00'); // 10:00 PM
        
        // Calculate regular hours (up to 8 hours or until regularEnd)
        $timeInTs = $timeIn->getTimestamp();
        $regularEndTs = $regularEnd->getTimestamp();
        $timeOutTs = $timeOut->getTimestamp();
        
        $regularHours = 0;
        if ($timeIn < $regularEnd) {
            $regularHours = min(8, ($regularEndTs - $timeInTs) / 3600);
            $regularHours = max(0, $regularHours); // Ensure non-negative
            
            // Apply appropriate pay based on holiday type
            if ($holidayType) {
                $this->applyHolidayPay($holidayType, $regularHours, $hourlyRate, $isRestDay, false, $result);
            } else {
                // Regular day pay
                $multiplier = $isRestDay ? 1.3 : 1.0;
                $result['regular_hours_pay'] += $regularHours * $hourlyRate * $multiplier;
            }
        }
        
        // Calculate overtime hours (after regularEnd)
        if ($timeOut > $regularEnd) {
            $otHours = min(($timeOutTs - $regularEndTs) / 3600, 4); // Max 4 hours
            $otHours = max(0, $otHours); // Ensure non-negative
            
            if ($holidayType) {
                $this->applyHolidayOvertimePay($holidayType, $otHours, $hourlyRate, $isRestDay, false, $result);
            } else {
                // Regular overtime pay
                $multiplier = $isRestDay ? 1.69 : 1.25; // Rest day OT: 1.3 × 1.3 = 1.69
                $result['ot_pay'] += $otHours * $hourlyRate * $multiplier;
            }
        }
        
        // Check for night differential (after 10pm)
        if ($timeOut > $nightDiffStart) {
            $nightDiffHours = ($timeOutTs - $nightDiffStart->getTimestamp()) / 3600;
            $nightDiffHours = max(0, $nightDiffHours);
            
            if ($holidayType) {
                $this->applyHolidayPay($holidayType, $nightDiffHours, $hourlyRate, $isRestDay, true, $result);
            } else {
                // Night differential (additional 10%)
                $result['night_diff_pay'] += $nightDiffHours * $hourlyRate * 0.1;
            }
        }
    }
    
    /**
     * Calculate night shift (6pm to 6am)
     * - Regular hours: 6pm to 10pm (4 hours)
     * - Night differential: 10pm to 2am (4 hours with night diff)
     * - Night differential + OT: 2am to 6am (4 hours with night diff and OT)
     */
    private function calculateNightShift($timeIn, $timeOut, $hourlyRate, $isRestDay, $holidayType, &$result) {
        $date = $timeIn->format('Y-m-d');
        
        // Create timestamps for night shift boundaries
        $ndStart = new DateTime($date . ' 22:00:00'); // 10:00 PM
        $otStart = clone $ndStart;
        $otStart->modify('+4 hours'); // 2:00 AM next day
        $shiftEnd = clone $ndStart;
        $shiftEnd->modify('+8 hours'); // 6:00 AM next day
        
        // 1. Regular hours (6pm to 10pm)
        if ($timeIn < $ndStart) {
            $regHours = min(4, ($ndStart->getTimestamp() - $timeIn->getTimestamp()) / 3600);
            $regHours = max(0, $regHours);
            
            if ($holidayType) {
                $this->applyHolidayPay($holidayType, $regHours, $hourlyRate, $isRestDay, false, $result);
            } else {
                // Regular pay
                $multiplier = $isRestDay ? 1.3 : 1.0;
                $result['regular_hours_pay'] += $regHours * $hourlyRate * $multiplier;
            }
        }
        
        // 2. Night differential hours (10pm to 2am)
        if ($timeIn <= $ndStart && $timeOut > $ndStart) {
            $endPoint = min($timeOut, $otStart);
            $ndHours = ($endPoint->getTimestamp() - $ndStart->getTimestamp()) / 3600;
            $ndHours = max(0, $ndHours);
            
            if ($holidayType) {
                $this->applyHolidayPay($holidayType, $ndHours, $hourlyRate, $isRestDay, true, $result);
            } else {
                // Night differential pay (110%)
                $multiplier = $isRestDay ? 1.43 : 1.1; // Rest day with ND: 1.3 × 1.1 = 1.43
                $result['night_diff_pay'] += $ndHours * $hourlyRate * $multiplier;
            }
        }
        else if ($timeIn > $ndStart && $timeIn < $otStart) {
            $endPoint = min($timeOut, $otStart);
            $ndHours = ($endPoint->getTimestamp() - $timeIn->getTimestamp()) / 3600;
            $ndHours = max(0, $ndHours);
            
            if ($holidayType) {
                $this->applyHolidayPay($holidayType, $ndHours, $hourlyRate, $isRestDay, true, $result);
            } else {
                // Night differential pay (110%)
                $multiplier = $isRestDay ? 1.43 : 1.1;
                $result['night_diff_pay'] += $ndHours * $hourlyRate * $multiplier;
            }
        }
        
        // 3. Night differential + OT hours (2am to 6am)
        if ($timeOut > $otStart) {
            $endPoint = min($timeOut, $shiftEnd);
            $ndOtHours = ($endPoint->getTimestamp() - $otStart->getTimestamp()) / 3600;
            $ndOtHours = max(0, $ndOtHours);
            
            if ($holidayType) {
                $this->applyHolidayOvertimePay($holidayType, $ndOtHours, $hourlyRate, $isRestDay, true, $result);
            } else {
                // Night differential + OT
                if ($isRestDay) {
                    // Rest day, night shift, OT = 1.3 × 1.1 × 1.3 = 1.859
                    $multiplier = 1.859;
                } else {
                    // Ordinary day, night shift, OT = 1 × 1.1 × 1.25 = 1.375
                    $multiplier = 1.375;
                }
                $result['ot_pay'] += $ndOtHours * $hourlyRate * $multiplier;
            }
        }
    }
    
    /**
     * Apply holiday pay with proper multipliers
     */
    private function applyHolidayPay($holidayType, $hours, $hourlyRate, $isRestDay, $isNightShift, &$result) {
        $amount = $hours * $hourlyRate;
        $multiplier = 1.0;
        
        switch ($holidayType) {
            case 'Regular':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 2.86; // Regular holiday, rest day, night shift = 2.6 × 1.1 = 2.86
                } elseif ($isRestDay) {
                    $multiplier = 2.6;  // Regular holiday on rest day = 2.6
                } elseif ($isNightShift) {
                    $multiplier = 2.2;  // Regular holiday, night shift = 2 × 1.1 = 2.2
                } else {
                    $multiplier = 2.0;  // Regular holiday = 2.0
                }
                $result['legal_holiday_pay'] += $amount * $multiplier;
                break;
                
            case 'Special Non-Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 1.65; // Special non-working, rest day, night shift = 1.5 × 1.1 = 1.65
                } elseif ($isRestDay) {
                    $multiplier = 1.5;  // Special non-working on rest day = 1.5
                } elseif ($isNightShift) {
                    $multiplier = 1.43; // Special non-working, night shift = 1.3 × 1.1 = 1.43
                } else {
                    $multiplier = 1.3;  // Special non-working = 1.3
                }
                $result['special_holiday_pay'] += $amount * $multiplier;
                break;
                
            case 'Special Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 1.43; // Rest day, night shift = 1.3 × 1.1 = 1.43
                } elseif ($isRestDay) {
                    $multiplier = 1.3;  // Rest day = 1.3
                } elseif ($isNightShift) {
                    $multiplier = 1.1;  // Night shift = 1.1
                } else {
                    $multiplier = 1.0;  // Regular day = 1.0
                }
                $result['regular_hours_pay'] += $amount * $multiplier; // Special working days use regular pay category
                break;
                
            case 'Double Holiday':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 4.29; // Double holiday, rest day, night shift = 3.9 × 1.1 = 4.29
                } elseif ($isRestDay) {
                    $multiplier = 3.9;  // Double holiday on rest day = 3.9
                } elseif ($isNightShift) {
                    $multiplier = 3.3;  // Double holiday, night shift = 3 × 1.1 = 3.3
                } else {
                    $multiplier = 3.0;  // Double holiday = 3.0
                }
                $result['legal_holiday_pay'] += $amount * $multiplier; // Double holidays go into legal holidays category
                break;
                
            case 'Double Special Non-Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 2.145; // Double special, rest day, night shift = 1.95 × 1.1 = 2.145
                } elseif ($isRestDay) {
                    $multiplier = 1.95;  // Double special on rest day = 1.95
                } elseif ($isNightShift) {
                    $multiplier = 1.65;  // Double special, night shift = 1.5 × 1.1 = 1.65
                } else {
                    $multiplier = 1.5;   // Double special = 1.5
                }
                $result['special_holiday_pay'] += $amount * $multiplier;
                break;
        }
    }
    
    /**
     * Apply holiday overtime pay with proper multipliers
     */
    private function applyHolidayOvertimePay($holidayType, $hours, $hourlyRate, $isRestDay, $isNightShift, &$result) {
        $amount = $hours * $hourlyRate;
        $multiplier = 1.0;
        
        switch ($holidayType) {
            case 'Regular':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 3.718; // Regular holiday, rest day, night shift, OT = 2.6 × 1.1 × 1.3 = 3.718
                } elseif ($isRestDay) {
                    $multiplier = 3.38;  // Regular holiday, rest day, OT = 2.6 × 1.3 = 3.38
                } elseif ($isNightShift) {
                    $multiplier = 2.86;  // Regular holiday, night shift, OT = 2 × 1.1 × 1.3 = 2.86
                } else {
                    $multiplier = 2.6;   // Regular holiday, OT = 2 × 1.3 = 2.6
                }
                $result['holiday_ot_pay'] += $amount * $multiplier;
                break;
                
            case 'Special Non-Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 2.145; // Special, rest day, night shift, OT = 1.5 × 1.1 × 1.3 = 2.145
                } elseif ($isRestDay) {
                    $multiplier = 1.95;  // Special, rest day, OT = 1.5 × 1.3 = 1.95
                } elseif ($isNightShift) {
                    $multiplier = 1.859; // Special, night shift, OT = 1.3 × 1.1 × 1.3 = 1.859
                } else {
                    $multiplier = 1.69;  // Special, OT = 1.3 × 1.3 = 1.69
                }
                $result['special_holiday_ot_pay'] += $amount * $multiplier;
                break;
                
            case 'Special Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 1.859; // Rest day, night shift, OT = 1.3 × 1.1 × 1.3 = 1.859
                } elseif ($isRestDay) {
                    $multiplier = 1.69;  // Rest day, OT = 1.3 × 1.3 = 1.69
                } elseif ($isNightShift) {
                    $multiplier = 1.375; // Night shift, OT = 1.0 × 1.1 × 1.25 = 1.375
                } else {
                    $multiplier = 1.25;  // OT = 1.25
                }
                $result['ot_pay'] += $amount * $multiplier;
                break;
                
            case 'Double Holiday':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 5.577; // Double, rest day, night shift, OT = 3.9 × 1.1 × 1.3 = 5.577
                } elseif ($isRestDay) {
                    $multiplier = 5.07;  // Double, rest day, OT = 3.9 × 1.3 = 5.07
                } elseif ($isNightShift) {
                    $multiplier = 4.29;  // Double, night shift, OT = 3 × 1.1 × 1.3 = 4.29
                } else {
                    $multiplier = 3.9;   // Double, OT = 3 × 1.3 = 3.9
                }
                $result['holiday_ot_pay'] += $amount * $multiplier;
                break;
                
            case 'Double Special Non-Working':
                if ($isRestDay && $isNightShift) {
                    $multiplier = 2.7885; // Double special, rest day, night shift, OT = 1.95 × 1.1 × 1.3 = 2.7885
                } elseif ($isRestDay) {
                    $multiplier = 2.535;  // Double special, rest day, OT = 1.95 × 1.3 = 2.535
                } elseif ($isNightShift) {
                    $multiplier = 2.145;  // Double special, night shift, OT = 1.5 × 1.1 × 1.3 = 2.145
                } else {
                    $multiplier = 1.95;   // Double special, OT = 1.5 × 1.3 = 1.95
                }
                $result['special_holiday_ot_pay'] += $amount * $multiplier;
                break;
        }
    }
    
    /**
     * Get guard's location and rate
     */
    private function getGuardLocationRate($userId) {
        $sql = "SELECT location_name, daily_rate 
                FROM guard_locations 
                WHERE user_id = ? AND is_active = 1 AND is_primary = 1
                LIMIT 1";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: ['daily_rate' => $this->defaultRate];
    }
    
    /**
     * Get attendance records for a user in a date range
     */
    private function getAttendanceRecords($userId, $startDate, $endDate) {
        $sql = "SELECT time_in, time_out, DAYOFWEEK(time_in) as day_of_week
                FROM attendance 
                WHERE User_ID = ? 
                AND DATE(time_in) BETWEEN ? AND ?
                AND time_out IS NOT NULL
                ORDER BY time_in ASC";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $startDate, $endDate]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get the holiday type for a specific date
     */
    private function getHolidayType($date) {
        // Check cache first
        if (isset($this->holidayCache[$date])) {
            return $this->holidayCache[$date];
        }
        
        $sql = "SELECT holiday_type FROM holidays WHERE holiday_date = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $holidayType = $result ? $result['holiday_type'] : null;
        
        // Cache the result
        $this->holidayCache[$date] = $holidayType;
        
        return $holidayType;
    }
    
    /**
     * Calculate deductions based on gross pay and settings
     */
    private function calculateDeductions($userId, &$result, $startDate, $endDate) {
        // Calculate Philhealth (2.9166% of gross pay)
        $result['philhealth'] = $result['gross_pay'] * 0.029166;
        
        // Fixed Pag-IBIG deduction of ₱200
        $result['pagibig'] = 200.00;
        
        // Calculate SSS deduction based on monthly gross pay
        $sssDeduction = $this->calculateSSSDeduction($result['gross_pay']);
        // If this is a half-month period, divide SSS by 2
        if ($this->isHalfMonthPeriod($startDate, $endDate)) {
            $sssDeduction = $sssDeduction / 2;
        }
        $result['sss'] = $sssDeduction;
        
        // Get cash advance from payroll table
        $cashAdvance = $this->getCashAdvance($userId, $startDate, $endDate);
        $result['cash_advance'] = $cashAdvance;
        
        // Calculate cash bond (with limit check)
        $cashBondResult = $this->calculateCashBond($userId);
        $result['cash_bond'] = $cashBondResult['amount'];
        $result['cash_bond_limit_reached'] = $cashBondResult['limit_reached'];
        
        // Calculate total deductions (including late_undertime)
        $result['total_deductions'] = 
            $result['sss'] + 
            $result['philhealth'] + 
            $result['pagibig'] + 
            $result['cash_advance'] + 
            $result['cash_bond'] + 
            $result['late_undertime'];
    }
    
    /**
     * Get cash advance for a period
     */
    private function getCashAdvance($userId, $startDate, $endDate) {
        $sql = "SELECT Cash_Advances 
                FROM payroll 
                WHERE User_ID = ? AND Period_Start = ? AND Period_End = ?";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $startDate, $endDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['Cash_Advances'] ? (float)$result['Cash_Advances'] : 0.0;
    }
    
    /**
     * Calculate cash bond with limits
     */
    private function calculateCashBond($userId) {
        // Get cash bond settings
        $settingsSQL = "SELECT cash_bond_per_period, cash_bond_limit 
                      FROM guard_settings LIMIT 1";
        $settingsStmt = $this->conn->prepare($settingsSQL);
        $settingsStmt->execute();
        $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        
        $cashBondPerPeriod = $settings && $settings['cash_bond_per_period'] ? 
                            (float)$settings['cash_bond_per_period'] : 100.0;
        $cashBondLimit = $settings && $settings['cash_bond_limit'] ? 
                        (float)$settings['cash_bond_limit'] : 2000.0;
        
        // Check total paid so far
        $totalPaidSQL = "SELECT SUM(cash_bond) AS total_paid 
                       FROM payroll WHERE User_ID = ?";
        $totalPaidStmt = $this->conn->prepare($totalPaidSQL);
        $totalPaidStmt->execute([$userId]);
        $totalPaid = $totalPaidStmt->fetch(PDO::FETCH_ASSOC);
        $totalPaid = $totalPaid && $totalPaid['total_paid'] ? (float)$totalPaid['total_paid'] : 0.0;
        
        // Check if limit reached or calculate remaining
        if ($totalPaid >= $cashBondLimit) {
            return [
                'amount' => 0, 
                'limit_reached' => true
            ];
        } else {
            $remaining = $cashBondLimit - $totalPaid;
            return [
                'amount' => min($cashBondPerPeriod, $remaining),
                'limit_reached' => false
            ];
        }
    }
    
    /**
     * Calculate SSS deduction based on monthly compensation table
     */
    private function calculateSSSDeduction($monthlyCompensation) {
        $sssTable = [
            ['min' => 0.00,      'max' => 5249.99,  'contribution' => 500.00],
            ['min' => 5250.00,   'max' => 5749.99,  'contribution' => 550.00],
            ['min' => 5750.00,   'max' => 6249.99,  'contribution' => 600.00],
            ['min' => 6250.00,   'max' => 6749.99,  'contribution' => 650.00],
            ['min' => 6750.00,   'max' => 7249.99,  'contribution' => 700.00],
            ['min' => 7250.00,   'max' => 7749.99,  'contribution' => 750.00],
            ['min' => 7750.00,   'max' => 8249.99,  'contribution' => 800.00],
            ['min' => 8250.00,   'max' => 8749.99,  'contribution' => 850.00],
            ['min' => 8750.00,   'max' => 9249.99,  'contribution' => 900.00],
            ['min' => 9250.00,   'max' => 9749.99,  'contribution' => 950.00],
            ['min' => 9750.00,   'max' => 10249.99, 'contribution' => 1000.00],
            ['min' => 10250.00,  'max' => 10749.99, 'contribution' => 1050.00],
            ['min' => 10750.00,  'max' => 11249.99, 'contribution' => 1100.00],
            ['min' => 11250.00,  'max' => 11749.99, 'contribution' => 1150.00],
            ['min' => 11750.00,  'max' => 12249.99, 'contribution' => 1200.00],
            ['min' => 12250.00,  'max' => 12749.99, 'contribution' => 1250.00],
            ['min' => 12750.00,  'max' => 13249.99, 'contribution' => 1300.00],
            ['min' => 13250.00,  'max' => 13749.99, 'contribution' => 1350.00],
            ['min' => 13750.00,  'max' => 14249.99, 'contribution' => 1400.00],
            ['min' => 14250.00,  'max' => 14749.99, 'contribution' => 1450.00],
            ['min' => 14750.00,  'max' => 15249.99, 'contribution' => 1500.00],
            ['min' => 15250.00,  'max' => 15749.99, 'contribution' => 1550.00],
            ['min' => 15750.00,  'max' => 16249.99, 'contribution' => 1600.00],
            ['min' => 16250.00,  'max' => 16749.99, 'contribution' => 1650.00],
            ['min' => 16750.00,  'max' => 17249.99, 'contribution' => 1700.00],
            ['min' => 17250.00,  'max' => 17749.99, 'contribution' => 1750.00],
            ['min' => 17750.00,  'max' => 18249.99, 'contribution' => 1800.00],
            ['min' => 18250.00,  'max' => 18749.99, 'contribution' => 1850.00],
            ['min' => 18750.00,  'max' => 19249.99, 'contribution' => 1900.00],
            ['min' => 19250.00,  'max' => 19749.99, 'contribution' => 1950.00],
            ['min' => 19750.00,  'max' => PHP_FLOAT_MAX, 'contribution' => 2000.00]
        ];
        
        foreach ($sssTable as $bracket) {
            if ($monthlyCompensation >= $bracket['min'] && $monthlyCompensation <= $bracket['max']) {
                return $bracket['contribution'];
            }
        }
        
        return 2000.00; // Maximum contribution
    }
    
    /**
     * Check if a date range is a half-month period
     */
    private function isHalfMonthPeriod($startDate, $endDate) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        // Check if range is 1st-15th or 16th-end
        $firstDay = $start->format('j');
        $lastDay = $end->format('j');
        
        return ($firstDay == 1 && $lastDay == 15) || 
               ($firstDay == 16 && $lastDay > 25);
    }
    
    /**
     * Get empty payroll array with zeros
     */
    private function getEmptyPayrollArray() {
        return [
            'regular_hours_pay' => 0,
            'ot_pay' => 0,
            'night_diff_pay' => 0,
            'legal_holiday_pay' => 0,
            'holiday_ot_pay' => 0,
            'special_holiday_pay' => 0,
            'special_holiday_ot_pay' => 0,
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
            'cash_bond_limit_reached' => false,
            'other_deductions' => 0,
            'total_deductions' => 0,
            'net_pay' => 0,
            'hourly_rate' => 0,
            'daily_rate' => 0
        ];
    }
    
    // Add this method to the PayrollCalculator class

    /**
     * Compatibility method to support existing code
     * @deprecated Use calculatePayroll() instead
     */
    public function calculatePayrollForGuard($userId, $month = null, $year = null, $startDate = null, $endDate = null) {
        // Ignore month/year parameters and use start/end dates
        if ($startDate && $endDate) {
            return $this->calculatePayroll($userId, $startDate, $endDate);
        }
        
        // If no start/end dates but month/year provided, calculate dates
        if ($month && $year) {
            // Format dates for the period
            $startDate = "$year-$month-01";
            $endDate = date('Y-m-t', strtotime("$year-$month-01"));
            return $this->calculatePayroll($userId, $startDate, $endDate);
        }
        
        // Default to current month
        $currentMonth = date('m');
        $currentYear = date('Y');
        $startDate = "$currentYear-$currentMonth-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        return $this->calculatePayroll($userId, $startDate, $endDate);
    }
}

// Usage example:
// require_once '../db_connection.php';
// $calculator = new PayrollCalculator($conn);
// $result = $calculator->calculatePayroll(123, '2025-06-01', '2025-06-15');