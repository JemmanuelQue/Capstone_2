<?php
/**
 * Unified Employer Contributions Calculator
 *
 * Computes government-mandated contributions (EE/ER) for SSS, PhilHealth, Pag-IBIG
 * per employee over a selected date range, using existing payroll records.
 *
 * Notes:
 * - EE formulas mirror those used in the payroll system.
 * - ER values: PhilHealth ER equals EE (50/50 split). Pag-IBIG ER mirrors EE (capped logic).
 * - SSS ER: Uses an employer contribution table. Update the table to match official schedules.
 */
class EmployerContributionsCalculator {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Entry point: compute contributions for all employees in range and optional filters
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param array $filters ['location_id'=>int|null, 'employee_type'=>string|null]
     * @return array { employees:[], totals:{} }
     */
    public function compute($startDate, $endDate, $filters = []) {
        $employees = $this->getEmployeesWithPayroll($startDate, $endDate, $filters);
        $results = [];
        $totals = [
            'sss_ee' => 0.0, 'sss_er' => 0.0, 'sss_total' => 0.0,
            'ph_ee' => 0.0,  'ph_er' => 0.0,  'ph_total' => 0.0,
            'hdmf_ee' => 0.0,'hdmf_er' => 0.0,'hdmf_total' => 0.0,
        ];

        foreach ($employees as $emp) {
            // Monthly gross: sum payroll gross within range; if half-month entries exist, sum both halves.
            $monthlyGross = $this->getMonthlyGrossForEmployee($emp['User_ID'], $startDate, $endDate);

            // Compute EE shares (reuse payroll formulas)
            $sssEE = $this->calculateSSSEE($monthlyGross);
            $phEE  = $this->calculatePhilHealthEE($monthlyGross);
            $hdmfEE= $this->calculatePagIbigEE($monthlyGross);

            // Compute ER shares
            $sssER = $this->calculateSSSER($monthlyGross);
            $phER  = $phEE; // 50/50 split as per payroll EE formula
            $hdmfER= $hdmfEE; // mirror EE (capped logic matches many setups)

            $results[] = [
                'user_id' => $emp['User_ID'],
                'name' => $emp['Full_Name'] ?? ($emp['First_Name'].' '.$emp['Last_Name']),
                'sss_ee' => round($sssEE, 2),
                'sss_er' => round($sssER, 2),
                'sss_total' => round($sssEE + $sssER, 2),
                'ph_ee' => round($phEE, 2),
                'ph_er' => round($phER, 2),
                'ph_total' => round($phEE + $phER, 2),
                'hdmf_ee' => round($hdmfEE, 2),
                'hdmf_er' => round($hdmfER, 2),
                'hdmf_total' => round($hdmfEE + $hdmfER, 2),
            ];

            $totals['sss_ee'] += $sssEE; $totals['sss_er'] += $sssER; $totals['sss_total'] += ($sssEE + $sssER);
            $totals['ph_ee']  += $phEE;  $totals['ph_er']  += $phER;  $totals['ph_total']  += ($phEE + $phER);
            $totals['hdmf_ee']+= $hdmfEE;$totals['hdmf_er']+= $hdmfER;$totals['hdmf_total']+= ($hdmfEE + $hdmfER);
        }

        // Round totals
        foreach ($totals as $k => $v) { $totals[$k] = round($v, 2); }
        return ['employees' => $results, 'totals' => $totals];
    }

    /** Fetch employees who have payroll entries in range, with optional filters */
    private function getEmployeesWithPayroll($startDate, $endDate, $filters) {
        $sql = "SELECT DISTINCT p.User_ID, u.First_Name, u.Last_Name, CONCAT(u.First_Name, ' ', u.Last_Name) AS Full_Name
                FROM payroll p
                JOIN users u ON u.User_ID = p.User_ID
                WHERE p.Period_Start >= ? AND p.Period_End <= ?";
        $params = [$startDate, $endDate];

        if (!empty($filters['location_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM guard_locations gl WHERE gl.user_id = p.User_ID AND gl.location_id = ? AND gl.is_active = 1)";
            $params[] = $filters['location_id'];
        }
        if (!empty($filters['employee_type'])) {
            $sql .= " AND u.Employee_Type = ?";
            $params[] = $filters['employee_type'];
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Sum gross pay across payroll entries within the selected month/range */
    private function getMonthlyGrossForEmployee($userId, $startDate, $endDate) {
        $sql = "SELECT SUM(Gross_Pay) AS gross_sum FROM payroll
                WHERE User_ID = ? AND Period_Start >= ? AND Period_End <= ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $startDate, $endDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && $row['gross_sum'] ? (float)$row['gross_sum'] : 0.0;
    }

    /** EE: SSS deduction reused from payroll brackets */
    private function calculateSSSEE($monthlyCompensation) {
        $table = $this->getSSSEmployeeTable();
        foreach ($table as $b) {
            if ($monthlyCompensation >= $b['min'] && $monthlyCompensation <= $b['max']) {
                return $b['ee'];
            }
        }
        return end($table)['ee'];
    }

    /** ER: SSS employer contribution table (update to official schedule as needed) */
    private function calculateSSSER($monthlyCompensation) {
        $table = $this->getSSSEmployerTable();
        foreach ($table as $b) {
            if ($monthlyCompensation >= $b['min'] && $monthlyCompensation <= $b['max']) {
                return $b['er'];
            }
        }
        return end($table)['er'];
    }

    /** PhilHealth EE: (Monthly Gross × 5%) ÷ 2; ER = EE */
    private function calculatePhilHealthEE($monthlyGross) {
        $monthlyEmployeeShare = ($monthlyGross * 0.05) / 2;
        return round($monthlyEmployeeShare, 2);
    }

    /** Pag-IBIG EE: 2% of monthly gross, capped at 200 if monthly >= 10,000; ER mirrors EE */
    private function calculatePagIbigEE($monthlyGross) {
        $ee = $monthlyGross * 0.02;
        if ($monthlyGross >= 10000 && $ee > 200) { $ee = 200.00; }
        return round($ee, 2);
    }

    /** SSS Employee bracket table (mirror from payroll system) */
    private function getSSSEmployeeTable() {
        return [
            ['min'=>0.00,'max'=>5249.99,'ee'=>250.00],
            ['min'=>5250.00,'max'=>5749.99,'ee'=>275.00],
            ['min'=>5750.00,'max'=>6249.99,'ee'=>300.00],
            ['min'=>6250.00,'max'=>6749.99,'ee'=>325.00],
            ['min'=>6750.00,'max'=>7249.99,'ee'=>350.00],
            ['min'=>7250.00,'max'=>7749.99,'ee'=>375.00],
            ['min'=>7750.00,'max'=>8249.99,'ee'=>400.00],
            ['min'=>8250.00,'max'=>8749.99,'ee'=>425.00],
            ['min'=>8750.00,'max'=>9249.99,'ee'=>450.00],
            ['min'=>9250.00,'max'=>9749.99,'ee'=>475.00],
            ['min'=>9750.00,'max'=>10249.99,'ee'=>500.00],
            ['min'=>10250.00,'max'=>10749.99,'ee'=>525.00],
            ['min'=>10750.00,'max'=>11249.99,'ee'=>550.00],
            ['min'=>11250.00,'max'=>11749.99,'ee'=>575.00],
            ['min'=>11750.00,'max'=>12249.99,'ee'=>600.00],
            ['min'=>12250.00,'max'=>12749.99,'ee'=>625.00],
            ['min'=>12750.00,'max'=>13249.99,'ee'=>650.00],
            ['min'=>13250.00,'max'=>13749.99,'ee'=>675.00],
            ['min'=>13750.00,'max'=>14249.99,'ee'=>700.00],
            ['min'=>14250.00,'max'=>14749.99,'ee'=>725.00],
            ['min'=>14750.00,'max'=>15249.99,'ee'=>750.00],
            ['min'=>15250.00,'max'=>15749.99,'ee'=>775.00],
            ['min'=>15750.00,'max'=>16249.99,'ee'=>800.00],
            ['min'=>16250.00,'max'=>16749.99,'ee'=>825.00],
            ['min'=>16750.00,'max'=>17249.99,'ee'=>850.00],
            ['min'=>17250.00,'max'=>17749.99,'ee'=>875.00],
            ['min'=>17750.00,'max'=>18249.99,'ee'=>900.00],
            ['min'=>18250.00,'max'=>18749.99,'ee'=>925.00],
            ['min'=>18750.00,'max'=>19249.99,'ee'=>950.00],
            ['min'=>19250.00,'max'=>19749.99,'ee'=>975.00],
            ['min'=>19750.00,'max'=>99999999,'ee'=>1000.00],
        ];
    }

    /** Placeholder SSS Employer bracket table; update with official ER values */
    private function getSSSEmployerTable() {
        // These ER values are placeholders proportional to EE. Replace with official ER schedule.
        $eeTable = $this->getSSSEmployeeTable();
        $erTable = [];
        foreach ($eeTable as $b) {
            $erTable[] = ['min'=>$b['min'], 'max'=>$b['max'], 'er'=>round($b['ee'] * 2.0, 2)];
        }
        return $erTable;
    }
}

// Example usage (server-side include):
// require_once '../db_connection.php';
// require_once 'unified_employer_calculator.php';
// $calc = new EmployerContributionsCalculator($conn);
// $data = $calc->compute('2025-12-01','2025-12-31', ['location_id'=>null, 'employee_type'=>null]);
// echo json_encode($data);
