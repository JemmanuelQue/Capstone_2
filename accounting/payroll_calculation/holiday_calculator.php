<?php

class HolidayCalculator {
    private $conn;
    private $hourlyRate = 67.50; // Fixed rate for guards

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Create holidays table if it doesn't exist
    public function initializeHolidaysTable() {
        $sql = "CREATE TABLE IF NOT EXISTS holidays (
            holiday_id int(11) NOT NULL AUTO_INCREMENT,
            holiday_date date NOT NULL,
            holiday_name varchar(100) NOT NULL,
            holiday_type enum('Regular','Special Non-Working','Special Working','Double Holiday','Double Special Non-Working') NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (holiday_id),
            KEY idx_holiday_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

        $this->conn->exec($sql);

        // Insert 2025 holidays if table is empty
        $check = $this->conn->query("SELECT COUNT(*) FROM holidays")->fetchColumn();
        if ($check == 0) {
            $this->insertDefaultHolidays();
        }
    }

    private function insertDefaultHolidays() {
        $holidays = [
            // Regular Holidays
            ['2025-01-01', 'New Year\'s Day', 'Regular'],
            ['2025-04-01', 'Eidul-Fitar Holiday', 'Regular'],
            ['2025-04-09', 'The Day of Valor', 'Regular'],
            ['2025-04-17', 'Maundy Thursday', 'Regular'],
            ['2025-04-18', 'Good Friday', 'Regular'],
            ['2025-05-01', 'Labor Day', 'Regular'],
            ['2025-06-07', 'Eid al-Adha (Feast of the Sacrifice)', 'Regular'],
            ['2025-06-12', 'Independence Day', 'Regular'],
            ['2025-08-25', 'National Heroes Day', 'Regular'],
            ['2025-11-30', 'Bonifacio Day', 'Regular'],
            ['2025-12-25', 'Christmas Day', 'Regular'],
            ['2025-12-30', 'Rizal Day', 'Regular'],

            // Special Non-Working Holidays
            ['2025-01-29', 'Lunar New Year\'s Day', 'Special Non-Working'],
            ['2025-04-19', 'Black Saturday', 'Special Non-Working'],
            ['2025-08-21', 'Ninoy Aquino Day', 'Special Non-Working'],
            ['2025-10-31', 'Special Non-Working Day', 'Special Non-Working'],
            ['2025-11-01', 'All Saints\' Day', 'Special Non-Working'],
            ['2025-12-08', 'Feast of the Immaculate Conception', 'Special Non-Working'],
            ['2025-12-24', 'Christmas Eve', 'Special Non-Working'],
            ['2025-12-31', 'New Year\'s Eve', 'Special Non-Working'],

            // Special Working Days
            ['2025-01-23', 'First Philippine Republic Day', 'Special Working'],
            ['2025-09-03', 'Yamashita Surrender Day', 'Special Working'],
            ['2025-09-08', 'Feast of the Nativity of Mary', 'Special Working']
        ];

        $sql = "INSERT INTO holidays (holiday_date, holiday_name, holiday_type) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        foreach ($holidays as $holiday) {
            $stmt->execute($holiday);
        }
    }

    public function getHolidayType($date) {
        $sql = "SELECT holiday_type FROM holidays WHERE holiday_date = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['holiday_type'] : null;
    }

    public function calculateHolidayPay($date, $hours_worked, $is_rest_day = false, $is_night_shift = false) {
        $holiday_type = $this->getHolidayType($date);
        if (!$holiday_type) {
            if ($is_rest_day) {
                return $hours_worked * $this->hourlyRate * ($is_night_shift ? 1.43 : 1.3); // Rest day: 130%, with night shift: 143%
            }
            return $hours_worked * $this->hourlyRate * ($is_night_shift ? 1.1 : 1.0); // Regular day: 100%, with night shift: 110%
        }

        $base_pay = $hours_worked * $this->hourlyRate;
        $multiplier = 1.0;

        switch ($holiday_type) {
            case 'Regular':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 2.86; // 260% × 1.1 = 286%
                } elseif ($is_rest_day) {
                    $multiplier = 2.6; // 260%
                } elseif ($is_night_shift) {
                    $multiplier = 2.2; // 200% × 1.1 = 220%
                } else {
                    $multiplier = 2.0; // 200%
                }
                break;
            
            case 'Special Non-Working':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 1.65; // 150% × 1.1 = 165%
                } elseif ($is_rest_day) {
                    $multiplier = 1.5; // 150%
                } elseif ($is_night_shift) {
                    $multiplier = 1.43; // 130% × 1.1 = 143%
                } else {
                    $multiplier = 1.3; // 130%
                }
                break;

            case 'Double Holiday':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 4.29; // 390% × 1.1 = 429%
                } elseif ($is_rest_day) {
                    $multiplier = 3.9; // 390%
                } elseif ($is_night_shift) {
                    $multiplier = 3.3; // 300% × 1.1 = 330%
                } else {
                    $multiplier = 3.0; // 300%
                }
                break;

            case 'Double Special Non-Working':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 2.145; // 195% × 1.1 = 214.5%
                } elseif ($is_rest_day) {
                    $multiplier = 1.95; // 195%
                } elseif ($is_night_shift) {
                    $multiplier = 1.65; // 150% × 1.1 = 165%
                } else {
                    $multiplier = 1.5; // 150%
                }
                break;
            
            case 'Special Working':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 1.43; // 130% × 1.1 = 143%
                } elseif ($is_rest_day) {
                    $multiplier = 1.3; // 130%
                } elseif ($is_night_shift) {
                    $multiplier = 1.1; // 110%
                } else {
                    $multiplier = 1.0; // 100%
                }
                break;
        }

        return $base_pay * $multiplier;
    }

    public function calculateHolidayOvertimePay($date, $ot_hours, $is_rest_day = false, $is_night_shift = false) {
        $holiday_type = $this->getHolidayType($date);
        if (!$holiday_type) {
            if ($is_rest_day && $is_night_shift) {
                return $ot_hours * $this->hourlyRate * 1.859; // 130% × 1.1 × 1.3 = 185.9%
            } elseif ($is_rest_day) {
                return $ot_hours * $this->hourlyRate * 1.69; // 130% × 1.3 = 169%
            } elseif ($is_night_shift) {
                return $ot_hours * $this->hourlyRate * 1.375; // 1 × 1.1 × 1.25 = 137.5%
            } else {
                return $ot_hours * $this->hourlyRate * 1.25; // 125%
            }
        }

        $base_ot_pay = $ot_hours * $this->hourlyRate;
        $multiplier = 1.0;

        switch ($holiday_type) {
            case 'Regular':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 3.718; // 2.6 × 1.1 × 1.3 = 371.8%
                } elseif ($is_rest_day) {
                    $multiplier = 3.38; // 2.6 × 1.3 = 338%
                } elseif ($is_night_shift) {
                    $multiplier = 2.86; // 2.0 × 1.1 × 1.3 = 286%
                } else {
                    $multiplier = 2.6; // 2.0 × 1.3 = 260%
                }
                break;
            
            case 'Special Non-Working':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 2.145; // 1.5 × 1.1 × 1.3 = 214.5%
                } elseif ($is_rest_day) {
                    $multiplier = 1.95; // 1.5 × 1.3 = 195%
                } elseif ($is_night_shift) {
                    $multiplier = 1.859; // 1.3 × 1.1 × 1.3 = 185.9%
                } else {
                    $multiplier = 1.69; // 1.3 × 1.3 = 169%
                }
                break;

            case 'Double Holiday':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 5.577; // 3.9 × 1.1 × 1.3 = 557.7%
                } elseif ($is_rest_day) {
                    $multiplier = 5.07; // 3.9 × 1.3 = 507%
                } elseif ($is_night_shift) {
                    $multiplier = 4.29; // 3.0 × 1.1 × 1.3 = 429%
                } else {
                    $multiplier = 3.9; // 3.0 × 1.3 = 390%
                }
                break;

            case 'Double Special Non-Working':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 2.7885; // 1.95 × 1.1 × 1.3 = 278.85%
                } elseif ($is_rest_day) {
                    $multiplier = 2.535; // 1.95 × 1.3 = 253.5%
                } elseif ($is_night_shift) {
                    $multiplier = 2.145; // 1.5 × 1.1 × 1.3 = 214.5%
                } else {
                    $multiplier = 1.95; // 1.5 × 1.3 = 195%
                }
                break;

            case 'Special Working':
                if ($is_rest_day && $is_night_shift) {
                    $multiplier = 1.859; // 1.3 × 1.1 × 1.3 = 185.9%
                } elseif ($is_rest_day) {
                    $multiplier = 1.69; // 1.3 × 1.3 = 169%
                } elseif ($is_night_shift) {
                    $multiplier = 1.375; // 1.0 × 1.1 × 1.25 = 137.5%
                } else {
                    $multiplier = 1.25; // 1.0 × 1.25 = 125%
                }
                break;
        }

        return $base_ot_pay * $multiplier;
    }
} 