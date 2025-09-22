<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/**
 * Calculate Easter date for a given year
 * @param int $year
 * @return string date in Y-m-d format
 */
function calculateEaster($year) {
    $a = $year % 19;
    $b = floor($year / 100);
    $c = $year % 100;
    $d = floor($b / 4);
    $e = $b % 4;
    $f = floor(($b + 8) / 25);
    $g = floor(($b - $f + 1) / 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = floor($c / 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = floor(($a + 11 * $h + 22 * $l) / 451);
    $month = floor(($h + $l - 7 * $m + 114) / 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    return sprintf("%04d-%02d-%02d", $year, $month, $day);
}

/**
 * Get last Monday of a month
 * @param int $year
 * @param int $month
 * @return string date in Y-m-d format
 */
function getLastMondayOfMonth($year, $month) {
    $lastDay = date("t", strtotime("$year-$month-01"));
    $date = new DateTime("$year-$month-$lastDay");
    $date->modify('last monday');
    return $date->format('Y-m-d');
}

/**
 * Populate Philippine holidays for a year
 * @param PDO $conn
 * @param int $year
 * @return bool
 */
function populatePhilippineHolidays($conn, $year) {
    // Check if we already have holidays for this year
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM holidays WHERE YEAR(holiday_date) = ?");
    $checkStmt->execute([$year]);
    $count = $checkStmt->fetchColumn();
    
    // If we have more than 10 holidays, consider it populated
    if ($count >= 10) {
        return false;
    }
    
    try {
        $conn->beginTransaction();
        
        // Fixed regular holidays
        $regularHolidays = [
            ['01-01', "New Year's Day"],
            ['04-09', "Day of Valor (Araw ng Kagitingan)"],
            ['05-01', "Labor Day"],
            ['06-12', "Independence Day"],
            ['08-30', "National Heroes Day"], // Last Monday of August, will be adjusted
            ['11-30', "Bonifacio Day"],
            ['12-25', "Christmas Day"],
            ['12-30', "Rizal Day"]
        ];
        
        // Fixed special non-working holidays
        $specialNonWorkingHolidays = [
            ['02-25', "EDSA People Power Revolution Anniversary"],
            ['08-21', "Ninoy Aquino Day"],
            ['11-01', "All Saints' Day"],
            ['12-08', "Feast of the Immaculate Conception"],
            ['12-31', "Last Day of the Year"]
        ];
        
        // Calculate Easter-based holidays
        $easterDate = calculateEaster($year);
        $easterTime = strtotime($easterDate);
        
        $holyThursday = date('Y-m-d', strtotime('-3 days', $easterTime));
        $goodFriday = date('Y-m-d', strtotime('-2 days', $easterTime));
        
        // Calculate National Heroes Day (last Monday of August)
        $heroesDay = getLastMondayOfMonth($year, 8);
        
        // Insert fixed regular holidays
        foreach ($regularHolidays as $holiday) {
            // Skip National Heroes Day as it will be inserted separately
            if ($holiday[1] == "National Heroes Day") continue;
            
            $date = "$year-{$holiday[0]}";
            insertHoliday($conn, $date, $holiday[1], 'Regular');
        }
        
        // Insert National Heroes Day
        insertHoliday($conn, $heroesDay, "National Heroes Day", 'Regular');
        
        // Insert Holy Thursday and Good Friday
        insertHoliday($conn, $holyThursday, "Maundy Thursday", 'Regular');
        insertHoliday($conn, $goodFriday, "Good Friday", 'Regular');
        
        // Insert fixed special non-working holidays
        foreach ($specialNonWorkingHolidays as $holiday) {
            $date = "$year-{$holiday[0]}";
            insertHoliday($conn, $date, $holiday[1], 'Special Non-Working');
        }
        
        // Insert Eid holidays (these vary by year - just adding placeholders for 2025)
        if ($year == 2025) {
            insertHoliday($conn, '2025-06-06', "Eid al-Adha (Feast of Sacrifice)", 'Regular');
            insertHoliday($conn, '2025-04-11', "Eid al-Fitr (End of Ramadan)", 'Regular');
        }
        
        $conn->commit();
        
        // Log this activity
        $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) 
                                  VALUES (?, 'Holiday System', ?, NOW())");
        $logStmt->execute([$_SESSION['user_id'], "Auto-populated Philippine holidays for $year"]);
        
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        return false;
    }
}

/**
 * Insert a holiday if it doesn't already exist
 * @param PDO $conn
 * @param string $date
 * @param string $name
 * @param string $type
 */
function insertHoliday($conn, $date, $name, $type) {
    // Check if holiday already exists
    $checkStmt = $conn->prepare("SELECT holiday_id FROM holidays WHERE holiday_date = ?");
    $checkStmt->execute([$date]);
    
    if ($checkStmt->rowCount() == 0) {
        $stmt = $conn->prepare("INSERT INTO holidays (holiday_date, holiday_name, holiday_type, created_at) 
                              VALUES (?, ?, ?, NOW())");
        $stmt->execute([$date, $name, $type]);
    }
}

// Handle AJAX requests
if (isset($_POST['check_year'])) {
    $year = (int)$_POST['check_year'];
    $populated = populatePhilippineHolidays($conn, $year);
    
    echo json_encode([
        'success' => true,
        'populated' => $populated,
        'year' => $year
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);