<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// Developer debug flag (pass ?debug=1 or include in POST)
$DEBUG_MODE = isset($_REQUEST['debug']) && $_REQUEST['debug'] === '1';

// Enumerate all possible import error codes for developer reference
$possibleImportErrors = [
    'missing_vendor_autoload',
    'missing_phpspreadsheet_class',
    'invalid_request_method',
    'upload_error',
    'spreadsheet_load_failure',
    'header_mismatch',
    'no_oic_locations',
    'unauthorized_guard',
    'guard_not_found',
    'missing_fields',
    'unauthorized_location',
    'invalid_date',
    'invalid_time_in',
    'invalid_time_out',
    'day_shift_cross_date',
    'night_shift_not_overnight',
    'duplicate',
    'db_error',
    'unknown_exception'
];

// Helper: send structured JSON with optional status and debug headers
function sendJson(array $payload, int $statusCode = 200, ?string $errorCode = null, bool $debugMode = false) : void {
    http_response_code($statusCode);
    if ($debugMode && $errorCode) {
        header('X-Debug-Error-Code: ' . $errorCode);
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
}

// Enforce OIC role (8) for all actions
if (!validateSession($conn, 8, false)) {
    if ($action === 'download_template') {
        http_response_code(403);
        exit('Unauthorized');
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Utility: fetch current OIC's assigned locations (active only)
function getOicLocations(PDO $conn): array {
    $stmt = $conn->prepare('SELECT location_name FROM oic_locations WHERE oic_user_id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

// Utility: check guard is managed by current OIC
function isGuardManagedByOIC(PDO $conn, int $guardId): bool {
    $stmt = $conn->prepare('
        SELECT 1
        FROM guard_locations gl
        INNER JOIN oic_locations ol ON ol.location_name = gl.location_name AND ol.is_active = 1 AND ol.oic_user_id = ?
        WHERE gl.user_id = ? AND gl.is_primary = 1
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id'], $guardId]);
    return (bool)$stmt->fetchColumn();
}

// Utility: find guardId from attendance id and verify ownership
function getAttendanceOwnerGuard(PDO $conn, int $attendanceId): ?int {
    $stmt = $conn->prepare('SELECT User_ID FROM attendance WHERE ID = ?');
    $stmt->execute([$attendanceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['User_ID'] : null;
}

// Common helpers from HR DTR logic
function normalizeDateTime(string $input): ?string {
    $input = trim($input);
    if ($input === '') return null;
    $patterns = [
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'd/m/Y h:i A',
        'm/d/Y h:i A',
        'd/m/Y g:i A',
        'm/d/Y g:i A',
        'd-m-Y H:i',
        'm-d-Y H:i',
    ];
    foreach ($patterns as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $input);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    $ts = strtotime($input);
    if ($ts !== false) return date('Y-m-d H:i:s', $ts);
    return null;
}

function currentUserName(PDO $conn): string {
    $stmt = $conn->prepare('SELECT First_Name, Last_Name FROM users WHERE User_ID = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    return $u ? ($u['First_Name'] . ' ' . $u['Last_Name']) : 'User';
}

function guardName(PDO $conn, $guardId): string {
    $stmt = $conn->prepare('SELECT First_Name, Last_Name, middle_name FROM users WHERE User_ID = ? AND Role_ID = 5');
    $stmt->execute([$guardId]);
    $g = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$g) return '';
    return $g['First_Name'] . ' ' . (!empty($g['middle_name']) ? $g['middle_name'] . ' ' : '') . $g['Last_Name'];
}

// Utility: parse Excel time cell (supports numeric serials and common string formats) -> returns 'H:i:s' or null
function parseExcelTime($value): ?string {
    if ($value === null) return null;
    // If PhpSpreadsheet gives numeric, interpret as Excel time fraction (or date+time, use fraction part)
    if (is_numeric($value)) {
        $f = (float)$value;
        $fraction = $f - floor($f);
        if ($f < 1) { $fraction = $f; }
        // Guard rails for negative or > 1 fractions
        if ($fraction < 0) { $fraction = 0; }
        if ($fraction >= 1) { $fraction = fmod($fraction, 1.0); }
        $totalSeconds = (int)round($fraction * 86400);
        // Normalize to 24h wrapping
        $totalSeconds = $totalSeconds % 86400;
        $h = (int)floor($totalSeconds / 3600);
        $m = (int)floor(($totalSeconds % 3600) / 60);
        $s = (int)($totalSeconds % 60);
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
    // Strings: try multiple formats
    $val = trim((string)$value);
    if ($val === '') return null;
    $fmts = ['g:i A', 'h:i A', 'H:i:s', 'H:i'];
    foreach ($fmts as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $val);
        if ($dt instanceof DateTime) {
            return $dt->format('H:i:s');
        }
    }
    // Try strtotime as last resort
    $ts = strtotime($val);
    if ($ts !== false) { return date('H:i:s', $ts); }
    return null;
}

    // Utility: get shift type for a guard on a specific date (returns 'Day' | 'Night' | null)
    function getShiftTypeForDate(PDO $conn, int $guardId, string $date): ?string {
        try {
            $stmt = $conn->prepare('SELECT shift_type FROM guard_schedules WHERE user_id = ? AND schedule_date = ? LIMIT 1');
            $stmt->execute([$guardId, $date]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['shift_type'])) return null;
            $v = strtolower(trim($row['shift_type']));
            if ($v === 'night' || $v === 'night shift' || $v === 'n') return 'Night';
            if ($v === 'day' || $v === 'day shift' || $v === 'd') return 'Day';
            return ucfirst($row['shift_type']);
        } catch (Exception $e) {
            return null;
        }
    }

// Route actions
if ($action === 'download_template' && $method === 'GET') {
    // Excel template download (migrated from download_attendance_template.php)
    if (!file_exists('../vendor/autoload.php')) {
        http_response_code(500);
        exit('PhpSpreadsheet library not installed. Please run: composer require phpoffice/phpspreadsheet');
    }
    require_once '../vendor/autoload.php';
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        http_response_code(500);
        exit('PhpSpreadsheet classes not found. Please reinstall with: composer update phpoffice/phpspreadsheet');
    }

    try {
        $oicLocations = getOicLocations($conn);
        // Optional: could enforce that if guard_id provided, it's under OIC
        $guardId = (isset($_GET['guard_id']) && ctype_digit($_GET['guard_id'])) ? (int)$_GET['guard_id'] : null;
        if ($guardId && !isGuardManagedByOIC($conn, $guardId)) {
            http_response_code(403);
            exit('Unauthorized: Guard not under your locations');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Template');

        $headers = [
            'A1' => 'Employee ID',
            'B1' => 'First Name',
            'C1' => 'Last Name',
            'D1' => 'Date (YYYY-MM-DD)',
            'E1' => 'Time In (HH:MM AM/PM)',
            'F1' => 'Time Out (HH:MM AM/PM)',
            'G1' => 'Location'
        ];
        foreach ($headers as $cell => $value) { $sheet->setCellValue($cell, $value); }
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2a7d4f']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
        ]);
        foreach (range('A', 'G') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

        $guardNameForTitle = '';
        if ($guardId) {
            $stmt = $conn->prepare("SELECT User_ID, COALESCE(Employee_ID, employee_id) AS EmpID, First_Name, Last_Name FROM users WHERE User_ID = ? AND Role_ID = 5 AND status = 'Active' LIMIT 1");
            $stmt->execute([$guardId]);
            $guard = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($guard) {
                $empId = $guard['EmpID'] ?: '';
                $guardFirst = $guard['First_Name'] ?? '';
                $guardLast = $guard['Last_Name'] ?? '';
                $guardNameForTitle = trim($guardFirst . ' ' . $guardLast);
                $sheet->setCellValue('A2', $empId);
                $sheet->setCellValue('B2', $guardFirst);
                $sheet->setCellValue('C2', $guardLast);
                $sheet->setCellValue('D2', date('Y-m-d'));
                $sheet->setCellValue('E2', '8:00 AM');
                $sheet->setCellValue('F2', '5:00 PM');
                $sheet->setCellValue('G2', '');
                $sheet->setCellValue('A3', $empId);
                $sheet->setCellValue('B3', $guardFirst);
                $sheet->setCellValue('C3', $guardLast);
                $sheet->setCellValue('D3', date('Y-m-d'));
                $sheet->setCellValue('E3', '6:00 PM');
                $sheet->setCellValue('F3', '6:00 AM');
                $sheet->setCellValue('G3', '');
            }
        }
        if ($guardNameForTitle === '') {
            $sheet->setCellValue('A2', '2024-001');
            $sheet->setCellValue('B2', 'Juan');
            $sheet->setCellValue('C2', 'Dela Cruz');
            $sheet->setCellValue('D2', date('Y-m-d'));
            $sheet->setCellValue('E2', '8:00 AM');
            $sheet->setCellValue('F2', '5:00 PM');
            $sheet->setCellValue('G2', 'Manila');
            $sheet->setCellValue('A3', '2024-002');
            $sheet->setCellValue('B3', 'Maria');
            $sheet->setCellValue('C3', 'Santos');
            $sheet->setCellValue('D3', date('Y-m-d'));
            $sheet->setCellValue('E3', '6:00 PM');
            $sheet->setCellValue('F3', '6:00 AM');
            $sheet->setCellValue('G3', 'Laguna');
        }
        $sheet->getStyle('A2:G3')->applyFromArray([
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
        ]);
        $instructionsSheet = $spreadsheet->createSheet();
        $instructionsSheet->setTitle('Instructions');
        $title = 'ATTENDANCE IMPORT TEMPLATE - INSTRUCTIONS' . ($guardNameForTitle ? (' (for ' . $guardNameForTitle . ')') : '');
        $instructions = [
            [$title], [''], ['Column Descriptions:'],
            ['Employee ID', 'The unique employee ID from the system'],
            ['First Name', 'Guard\'s first name'],
            ['Last Name', 'Guard\'s last name'],
            ['Date', 'Date of attendance in YYYY-MM-DD format (e.g., 2025-11-18)'],
            ['Time In', 'Clock in time in 12-hour format (e.g., 8:00 AM, 11:30 PM)'],
            ['Time Out', 'Clock out time in 12-hour format (e.g., 5:00 PM, 6:00 AM)'],
            ['Location', 'Guard\'s assigned location'],
            [''], ['Important Notes:'],
            ['1. Do NOT modify the header row (Row 1)'],
            ['2. Keep the prefilled Employee ID and Name as-is (guard-specific template)'],
                ['3. All fields are required for each row'],
                ['4. Date format must be YYYY-MM-DD (e.g., 2025-11-18)'],
                ['5. Time format must be HH:MM AM/PM (e.g., 8:00 AM, 11:30 PM)'],
                ['6. Night Shift: Time Out may be next calendar day (e.g., 6:00 PM → 6:00 AM)'],
                ['7. Day Shift: Time Out must be the same calendar day as Date'],
                ['8. Employee ID must match the selected guard in the system'],
                ['9. Location must match guard\'s assigned location'],
            [''], ['Examples:'],
            ['Day Shift: 8:00 AM to 5:00 PM (same date)'],
            ['Night Shift: 6:00 PM to 6:00 AM (next day\'s date for Time Out)'],
        ];
        $r = 1; foreach ($instructions as $row) { $c = 'A'; foreach ($row as $cell) { $instructionsSheet->setCellValue($c.$r, $cell); $c++; } $r++; }
        $instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instructionsSheet->getStyle('A3')->getFont()->setBold(true);
        $instructionsSheet->getStyle('A12')->getFont()->setBold(true);
        $instructionsSheet->getStyle('A20')->getFont()->setBold(true);
        $instructionsSheet->getColumnDimension('A')->setWidth(30);
        $instructionsSheet->getColumnDimension('B')->setWidth(60);
        $spreadsheet->setActiveSheetIndex(0);
        $filename = $guardNameForTitle ? (preg_replace('/[^A-Za-z0-9]+/', '_', trim($guardNameForTitle)) . '_Attendance_' . date('Y-m-d') . '.xlsx') : ('Attendance_Import_Template_' . date('Y-m-d') . '.xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        error_log('Template generation error: ' . $e->getMessage());
        http_response_code(500);
        exit('Error generating Excel template.');
    }
}

// For non-download actions, default to JSON
header('Content-Type: application/json');

try {
    switch ($action) {
        case 'import': {
            if ($method !== 'POST') { 
                sendJson([
                    'success' => false,
                    'message' => 'Invalid request method',
                    'debug' => $DEBUG_MODE ? ['code' => 'invalid_request_method', 'possibleErrors' => $possibleImportErrors] : null
                ], 400, 'invalid_request_method', $DEBUG_MODE); break; 
            }
            if (!file_exists('../vendor/autoload.php')) { 
                sendJson([
                    'success' => false,
                    'message' => 'PhpSpreadsheet not installed',
                    'debug' => $DEBUG_MODE ? ['code' => 'missing_vendor_autoload', 'possibleErrors' => $possibleImportErrors] : null
                ], 500, 'missing_vendor_autoload', $DEBUG_MODE); break; 
            }
            require_once '../vendor/autoload.php';
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) { 
                sendJson([
                    'success' => false,
                    'message' => 'PhpSpreadsheet classes not found',
                    'debug' => $DEBUG_MODE ? ['code' => 'missing_phpspreadsheet_class', 'possibleErrors' => $possibleImportErrors] : null
                ], 500, 'missing_phpspreadsheet_class', $DEBUG_MODE); break; 
            }
            if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
                sendJson([
                    'success' => false,
                    'message' => 'No file uploaded or upload error',
                    'debug' => $DEBUG_MODE ? ['code' => 'upload_error', 'fileError' => $_FILES['excelFile']['error'] ?? null, 'possibleErrors' => $possibleImportErrors] : null
                ], 400, 'upload_error', $DEBUG_MODE); break; 
            }
            $oicStmt = $conn->prepare('SELECT First_Name, Last_Name FROM users WHERE User_ID = ?');
            $oicStmt->execute([$_SESSION['user_id']]);
            $oicData = $oicStmt->fetch(PDO::FETCH_ASSOC);
            $oicName = ($oicData['First_Name'] ?? 'OIC') . ' ' . ($oicData['Last_Name'] ?? '');

            $file = $_FILES['excelFile']['tmp_name'];
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            } catch (Exception $e) {
                sendJson([
                    'success' => false,
                    'message' => 'Failed to read spreadsheet',
                    'debug' => $DEBUG_MODE ? ['code' => 'spreadsheet_load_failure', 'exception' => $e->getMessage(), 'possibleErrors' => $possibleImportErrors] : null
                ], 500, 'spreadsheet_load_failure', $DEBUG_MODE); break; 
            }
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            $expectedHeaders = ['Employee ID', 'First Name', 'Last Name', 'Date (YYYY-MM-DD)', 'Time In (HH:MM AM/PM)', 'Time Out (HH:MM AM/PM)', 'Location'];
            $actualHeaders = [];
            for ($col = 'A'; $col <= 'G'; $col++) { $actualHeaders[] = $sheet->getCell($col . '1')->getValue(); }
            $debug = [
                'expectedHeaders' => $expectedHeaders,
                'actualHeaders' => $actualHeaders,
                'rowErrors' => [],
                'context' => [ 'oicUserId' => $_SESSION['user_id'] ?? null, 'guardId' => (isset($_POST['guardId']) && ctype_digit($_POST['guardId'])) ? (int)$_POST['guardId'] : null ]
            ];
            if ($actualHeaders !== $expectedHeaders) {
                $debug['code'] = 'header_mismatch';
                if ($DEBUG_MODE) { $debug['possibleErrors'] = $possibleImportErrors; }
                sendJson([
                    'success' => false,
                    'message' => 'Invalid template format. Please download and use the correct template.',
                    'debug' => $DEBUG_MODE ? $debug : null
                ], 422, 'header_mismatch', $DEBUG_MODE); break; 
            }

            $oicLocations = getOicLocations($conn);
            if (empty($oicLocations)) { 
                $debug['code'] = 'no_oic_locations';
                if ($DEBUG_MODE) { $debug['possibleErrors'] = $possibleImportErrors; }
                sendJson([
                    'success' => false,
                    'message' => 'You are not assigned to any locations.',
                    'debug' => $DEBUG_MODE ? $debug : null
                ], 403, 'no_oic_locations', $DEBUG_MODE); break; 
            }

            $targetGuard = null;
            if (isset($_POST['guardId']) && ctype_digit($_POST['guardId'])) {
                $guardIdParam = (int)$_POST['guardId'];
                if (!isGuardManagedByOIC($conn, $guardIdParam)) {
                    sendJson([
                        'success' => false,
                        'message' => 'Unauthorized: Guard not under your locations',
                        'debug' => $DEBUG_MODE ? ['code' => 'unauthorized_guard', 'guardId' => $guardIdParam, 'possibleErrors' => $possibleImportErrors] : null
                    ], 403, 'unauthorized_guard', $DEBUG_MODE); break; 
                }
                $tgStmt = $conn->prepare('SELECT u.User_ID, COALESCE(u.Employee_ID, u.employee_id) AS EmpID, u.First_Name, u.Last_Name FROM users u WHERE u.User_ID = ? AND u.Role_ID = 5 AND u.status = "Active" LIMIT 1');
                $tgStmt->execute([$guardIdParam]);
                $targetGuard = $tgStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$targetGuard) { 
                    sendJson([
                        'success' => false,
                        'message' => 'Selected guard not found or inactive.',
                        'debug' => $DEBUG_MODE ? ['code' => 'guard_not_found', 'guardId' => $guardIdParam, 'possibleErrors' => $possibleImportErrors] : null
                    ], 404, 'guard_not_found', $DEBUG_MODE); break; 
                }
            }

            $successCount = 0; $errorCount = 0; $errors = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $employeeId = trim((string)$sheet->getCell('A' . $row)->getValue());
                $firstName  = trim((string)$sheet->getCell('B' . $row)->getValue());
                $lastName   = trim((string)$sheet->getCell('C' . $row)->getValue());
                $date       = $sheet->getCell('D' . $row)->getValue();
                $timeIn     = trim((string)$sheet->getCell('E' . $row)->getValue());
                $timeOut    = trim((string)$sheet->getCell('F' . $row)->getValue());
                $location   = trim((string)$sheet->getCell('G' . $row)->getValue());

                if ($employeeId === '' && $firstName === '' && $lastName === '') { continue; }

                $missing = [];
                if ($employeeId === '' || $employeeId === null) $missing[] = 'Employee ID';
                if ($firstName === '' || $firstName === null) $missing[] = 'First Name';
                if ($lastName === '' || $lastName === null) $missing[] = 'Last Name';
                if ($date === '' || $date === null) $missing[] = 'Date (YYYY-MM-DD)';
                if ($timeIn === '' || $timeIn === null) $missing[] = 'Time In (HH:MM AM/PM)';
                if ($timeOut === '' || $timeOut === null) $missing[] = 'Time Out (HH:MM AM/PM)';
                // Location is optional when a specific guard is targeted via guardId
                if (!$targetGuard && ($location === '' || $location === null)) $missing[] = 'Location';
                if (!empty($missing)) {
                    $errors[] = "Row $row: Missing required fields — " . implode(', ', $missing);
                    $debug['rowErrors'][] = ['row' => $row, 'code' => 'missing_fields', 'missing' => $missing];
                    $errorCount++; continue;
                }

                // When targeting a specific guard, we've already verified OIC manages the guard.
                // Only enforce location authorization for multi-guard imports.
                if (!$targetGuard && !in_array($location, $oicLocations)) {
                    $errors[] = "Row $row: You are not authorized to add attendance for location '$location'";
                    $debug['rowErrors'][] = ['row' => $row, 'code' => 'unauthorized_location', 'location' => $location, 'allowedLocations' => $oicLocations];
                    $errorCount++; continue;
                }

                $guard = null;
                if ($targetGuard) {
                    $expectedEmpId = (string)($targetGuard['EmpID'] ?? '');
                    if ($expectedEmpId === '' || $employeeId !== $expectedEmpId) {
                        $errors[] = "Row $row: Employee ID does not match the selected guard.";
                        $debug['rowErrors'][] = ['row' => $row, 'code' => 'guard_mismatch', 'expectedEmployeeId' => $expectedEmpId, 'actualEmployeeId' => $employeeId];
                        $errorCount++; continue;
                    }
                    $guard = ['User_ID' => $targetGuard['User_ID']];
                } else {
                    $guardStmt = $conn->prepare('
                        SELECT u.User_ID 
                        FROM users u
                        INNER JOIN guard_locations gl ON u.User_ID = gl.user_id
                        WHERE u.employee_id = ? 
                          AND u.First_Name = ? 
                          AND u.Last_Name = ? 
                          AND u.Role_ID = 5 
                          AND u.status = "Active"
                          AND gl.location_name = ?
                    ');
                    $guardStmt->execute([$employeeId, $firstName, $lastName, $location]);
                    $guard = $guardStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$guard) {
                        $errors[] = "Row $row: Guard '$firstName $lastName' (ID: $employeeId) not found or not assigned to '$location'";
                        $debug['rowErrors'][] = ['row' => $row, 'code' => 'guard_not_found'];
                        $errorCount++; continue;
                    }
                }

                if (is_numeric($date)) {
                    $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date);
                    $dateFormatted = $dateObj->format('Y-m-d');
                } else {
                    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
                    if (!$dateObj) {
                        $errors[] = "Row $row: Invalid date format '$date'. Use YYYY-MM-DD";
                        $debug['rowErrors'][] = ['row' => $row, 'code' => 'invalid_date'];
                        $errorCount++; continue;
                    }
                    $dateFormatted = $dateObj->format('Y-m-d');
                }

                $timeInHms = parseExcelTime($timeIn);
                if (!$timeInHms) { $errors[] = "Row $row: Invalid Time In format '" . htmlspecialchars((string)$timeIn) . "'. Use HH:MM AM/PM"; $debug['rowErrors'][] = ['row' => $row, 'code' => 'invalid_time_in', 'raw' => $timeIn]; $errorCount++; continue; }
                $timeOutHms = parseExcelTime($timeOut);
                if (!$timeOutHms) { $errors[] = "Row $row: Invalid Time Out format '" . htmlspecialchars((string)$timeOut) . "'. Use HH:MM AM/PM"; $debug['rowErrors'][] = ['row' => $row, 'code' => 'invalid_time_out', 'raw' => $timeOut]; $errorCount++; continue; }

                $timeInFull = $dateFormatted . ' ' . $timeInHms;
                $timeOutDate = $dateFormatted;
                // Compare as H:i for cross-date detection
                $timeInHi = substr($timeInHms, 0, 5);
                $timeOutHi = substr($timeOutHms, 0, 5);
                $shiftType = getShiftTypeForDate($conn, (int)$guard['User_ID'], $dateFormatted);
                if ($timeOutHi < $timeInHi) {
                    if ($shiftType === 'Day') {
                        $errors[] = "Row $row: Day Shift requires Time Out on the same date as Date ($dateFormatted)";
                        $debug['rowErrors'][] = ['row' => $row, 'code' => 'day_shift_cross_date'];
                        $errorCount++; continue;
                    }
                    $timeOutDate = (new DateTime($dateFormatted))->modify('+1 day')->format('Y-m-d');
                }
                // Night shift MUST be overnight: if times do not indicate crossing midnight (timeOut >= timeIn same date)
                if ($shiftType === 'Night' && $timeOutHi >= $timeInHi && $timeOutDate === $dateFormatted) {
                    $errors[] = "Row $row: Night Shift requires an overnight range (Time Out must be next day, e.g., 6:00 PM to 6:00 AM). Provided times suggest a day shift.";
                    $debug['rowErrors'][] = ['row' => $row, 'code' => 'night_shift_not_overnight'];
                    $errorCount++; continue;
                }
                $timeOutFull = $timeOutDate . ' ' . $timeOutHms;

                $dup = $conn->prepare('SELECT ID FROM attendance WHERE User_ID = ? AND DATE(Time_In) = ?');
                $dup->execute([$guard['User_ID'], $dateFormatted]);
                if ($dup->rowCount() > 0) { $errors[] = "Row $row: Duplicate attendance for $firstName $lastName on $dateFormatted"; $debug['rowErrors'][] = ['row' => $row, 'code' => 'duplicate']; $errorCount++; continue; }

                try {
                    $ins = $conn->prepare('INSERT INTO attendance (User_ID, Time_In, Time_Out, IP_Address, Created_At) VALUES (?, ?, ?, ?, NOW())');
                    $ins->execute([$guard['User_ID'], $timeInFull, $timeOutFull, $_SERVER['REMOTE_ADDR'] ?? '']);

                    $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, "Attendance Add", ?, NOW())');
                    // Render friendly 12-hour times for log
                    $logDetails = $oicName . ' added attendance record for ' . $firstName . ' ' . $lastName . ' - Date: ' . date('F j, Y', strtotime($dateFormatted)) . ' - Time: ' . date('g:i A', strtotime($timeInFull)) . ' to ' . date('g:i A', strtotime($timeOutFull)) . ' - Reason: bulk import via Excel';
                    $log->execute([$_SESSION['user_id'], $logDetails]);
                    $successCount++;
                } catch (PDOException $e) {
                    $errors[] = 'Row ' . $row . ': Database error - ' . $e->getMessage();
                    $debug['rowErrors'][] = ['row' => $row, 'code' => 'db_error', 'message' => $e->getMessage()];
                    $errorCount++;
                }
            }

            $message = '<strong>Import Summary:</strong><br>' . '✓ Successfully imported: <strong>' . $successCount . '</strong> record(s)<br>';
            if ($errorCount > 0) {
                $message .= '✗ Failed: <strong>' . $errorCount . '</strong> record(s)<br><br><strong>Errors:</strong><br><ul style="text-align: left; max-height: 200px; overflow-y: auto;">';
                foreach ($errors as $e) { $message .= '<li>' . htmlspecialchars($e) . '</li>'; }
                $message .= '</ul>';
            }
            if ($DEBUG_MODE) { $debug['possibleErrors'] = $possibleImportErrors; }
            sendJson([
                'success' => $successCount > 0,
                'message' => $message,
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'debug' => $DEBUG_MODE ? $debug : null
            ], 200, null, $DEBUG_MODE);
            break;
        }
        case 'add': {
            if ($method !== 'POST') { echo json_encode(['success' => false, 'message' => 'Invalid request method']); break; }
            $guardId = isset($_POST['guardId']) ? (int)$_POST['guardId'] : 0;
            $rawTimeIn = $_POST['timeIn'] ?? null;
            $rawTimeOut = $_POST['timeOut'] ?? null;
            $reason = $_POST['reason'] ?? '';
            if (!$guardId || !$rawTimeIn) { echo json_encode(['success' => false, 'message' => 'Missing guardId or timeIn']); break; }
            if (!isGuardManagedByOIC($conn, $guardId)) { echo json_encode(['success' => false, 'message' => 'Unauthorized guard (not under your locations)']); break; }

            $timeIn = normalizeDateTime($rawTimeIn);
            $timeOut = ($rawTimeOut !== null && $rawTimeOut !== '') ? normalizeDateTime($rawTimeOut) : null;
            if (!$timeIn) { echo json_encode(['success' => false, 'message' => 'Invalid timeIn format']); break; }
            if ($rawTimeOut && !$timeOut) { echo json_encode(['success' => false, 'message' => 'Invalid timeOut format']); break; }
            $gName = guardName($conn, $guardId);
            if (!$gName) { echo json_encode(['success' => false, 'message' => 'Guard not found or invalid']); break; }
            $today = date('Y-m-d');
            // Enforce month constraint: only current month attendance can be added
            $currentMonthKey = date('Y-m');
            $targetMonthKey = date('Y-m', strtotime($timeIn));
            if ($targetMonthKey !== $currentMonthKey) {
                echo json_encode(['success' => false, 'message' => 'Cannot add attendance outside the current month (' . $currentMonthKey . ').']);
                break;
            }
            if (date('Y-m-d', strtotime($timeIn)) > $today) { echo json_encode(['success' => false, 'message' => 'Cannot add attendance for a future date']); break; }
            if (!empty($timeOut) && strtotime($timeOut) <= strtotime($timeIn)) { echo json_encode(['success' => false, 'message' => 'Time out must be after time in']); break; }
            $dateCheck = date('Y-m-d', strtotime($timeIn));
            $dup = $conn->prepare('SELECT COUNT(*) FROM attendance WHERE User_ID = ? AND DATE(Time_In) = ?');
            $dup->execute([$guardId, $dateCheck]);
            if ($dup->fetchColumn() > 0) { echo json_encode(['success' => false, 'message' => 'Attendance already exists for ' . date('F j, Y', strtotime($dateCheck))]); break; }

            // Shift validation (require schedule)
            $shiftTypeAdd = getShiftTypeForDate($conn, $guardId, $dateCheck);
            if ($shiftTypeAdd === null) {
                echo json_encode(['success' => false, 'message' => 'Cannot add attendance: no schedule set for this guard on ' . date('F j, Y', strtotime($dateCheck)) . '. Create a schedule first.']);
                break;
            }
            if ($shiftTypeAdd === 'Rest Day') {
                echo json_encode(['success' => false, 'message' => 'Cannot add attendance on a Rest Day schedule']);
                break;
            }
            if ($shiftTypeAdd === 'Day' && $timeOut) {
                $inDateAdd = date('Y-m-d', strtotime($timeIn));
                $outDateAdd = date('Y-m-d', strtotime($timeOut));
                if ($inDateAdd !== $outDateAdd) { echo json_encode(['success' => false, 'message' => 'Day Shift requires Time Out on the same date as Time In']); break; }
            }
            if ($shiftTypeAdd === 'Night' && $timeOut) {
                $inDateAdd = date('Y-m-d', strtotime($timeIn));
                $outDateAdd = date('Y-m-d', strtotime($timeOut));
                // Night shift must cross midnight
                if ($inDateAdd === $outDateAdd) { echo json_encode(['success' => false, 'message' => 'Night Shift requires an overnight range (Time Out must be next day).']); break; }
            }

            $conn->beginTransaction();
            $ins = $conn->prepare('INSERT INTO attendance (User_ID, Time_In, Time_Out, IP_Address, Created_At) VALUES (?, ?, ?, ?, NOW())');
            $ins->execute([$guardId, $timeIn, $timeOut ?: null, $_SERVER['REMOTE_ADDR'] ?? '']);
            $attendanceId = $conn->lastInsertId();

            $actor = currentUserName($conn);
            $timeInDate = date('F j, Y', strtotime($timeIn));
            $timeInTime = date('g:i A', strtotime($timeIn));
            if ($timeOut) { $timeOutDate = date('F j, Y', strtotime($timeOut)); $timeOutTime = date('g:i A', strtotime($timeOut)); $dateRange = ($timeInDate === $timeOutDate) ? $timeInDate : ($timeInDate . ' to ' . $timeOutDate); $timeRange = $timeInTime . ' to ' . $timeOutTime; }
            else { $dateRange = $timeInDate; $timeRange = $timeInTime . ' (no time out)'; }
            $details = $actor . ' added attendance record for ' . $gName . ' - Date: ' . $dateRange . ' - Time: ' . $timeRange . ' - Reason: ' . $reason;
            $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, "Attendance Add", ?, NOW())');
            $log->execute([$_SESSION['user_id'], $details]);
            try {
                $audit = $conn->prepare('INSERT INTO edit_attendance_logs (Attendance_ID, Editor_User_ID, Editor_Name, Old_Time_In, New_Time_In, Old_Time_Out, New_Time_Out, Edit_Timestamp, IP_Address, Action_Description) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)');
                $audit->execute([$attendanceId, $_SESSION['user_id'], $actor, '1970-01-01 00:00:00', $timeIn, '1970-01-01 00:00:00', $timeOut ?: null, $_SERVER['REMOTE_ADDR'] ?? '', 'Added new attendance record - Reason: ' . $reason]);
            } catch (Exception $e) {}
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance record added successfully for ' . $gName, 'attendanceId' => $attendanceId]);
            break;
        }
        case 'edit': {
            if ($method !== 'POST') { echo json_encode(['success' => false, 'message' => 'Invalid request method']); break; }
            $attendanceId = isset($_POST['attendanceId']) ? (int)$_POST['attendanceId'] : 0;
            $rawNewIn = $_POST['newTimeIn'] ?? ($_POST['timeIn'] ?? null);
            $rawNewOut = $_POST['newTimeOut'] ?? ($_POST['timeOut'] ?? null);
            $reason = $_POST['reason'] ?? '';
            if (!$attendanceId || !$rawNewIn) { echo json_encode(['success' => false, 'message' => 'Missing attendanceId or timeIn']); break; }
            $guardId = getAttendanceOwnerGuard($conn, $attendanceId);
            if (!$guardId || !isGuardManagedByOIC($conn, $guardId)) { echo json_encode(['success' => false, 'message' => 'Unauthorized or attendance not found']); break; }

            $newTimeIn = normalizeDateTime($rawNewIn);
            $newTimeOut = ($rawNewOut !== null && $rawNewOut !== '') ? normalizeDateTime($rawNewOut) : null;
            if (!$newTimeIn) { echo json_encode(['success' => false, 'message' => 'Invalid timeIn format']); break; }
            if ($rawNewOut && !$newTimeOut) { echo json_encode(['success' => false, 'message' => 'Invalid timeOut format']); break; }
            $today = date('Y-m-d');
            // Enforce month constraint: only current month attendance can be edited/retimed
            $currentMonthKeyEdit = date('Y-m');
            $targetMonthKeyEdit = date('Y-m', strtotime($newTimeIn));
            if ($targetMonthKeyEdit !== $currentMonthKeyEdit) {
                echo json_encode(['success' => false, 'message' => 'Cannot edit attendance to a date outside the current month (' . $currentMonthKeyEdit . ').']);
                break;
            }
            if (date('Y-m-d', strtotime($newTimeIn)) > $today) { echo json_encode(['success' => false, 'message' => 'Cannot set Time In to a future date']); break; }
            if (!empty($newTimeOut) && strtotime($newTimeOut) <= strtotime($newTimeIn)) { echo json_encode(['success' => false, 'message' => 'Time out must be after time in']); break; }
            // Enforce shift rules (require schedule)
            $shiftTypeEdit = getShiftTypeForDate($conn, $guardId, date('Y-m-d', strtotime($newTimeIn)));
            if ($shiftTypeEdit === null) {
                echo json_encode(['success' => false, 'message' => 'Cannot edit attendance: no schedule exists for this guard on ' . date('F j, Y', strtotime($newTimeIn)) . '.']);
                break;
            }
            if ($shiftTypeEdit === 'Rest Day') {
                echo json_encode(['success' => false, 'message' => 'Cannot edit attendance on a Rest Day schedule']);
                break;
            }
            if ($shiftTypeEdit === 'Day' && !empty($newTimeOut)) {
                $inDateEdit = date('Y-m-d', strtotime($newTimeIn));
                $outDateEdit = date('Y-m-d', strtotime($newTimeOut));
                if ($inDateEdit !== $outDateEdit) { echo json_encode(['success' => false, 'message' => 'Day Shift requires Time Out on the same date as Time In']); break; }
            }
            if ($shiftTypeEdit === 'Night' && !empty($newTimeOut)) {
                $inDateEdit = date('Y-m-d', strtotime($newTimeIn));
                $outDateEdit = date('Y-m-d', strtotime($newTimeOut));
                if ($inDateEdit === $outDateEdit) { echo json_encode(['success' => false, 'message' => 'Night Shift requires an overnight range (Time Out must be next day).']); break; }
            }

            $get = $conn->prepare('SELECT a.*, u.First_Name, u.Last_Name FROM attendance a JOIN users u ON a.User_ID = u.User_ID WHERE a.ID = ?');
            $get->execute([$attendanceId]);
            $row = $get->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['success' => false, 'message' => 'Attendance record not found']); break; }

            $oldDate = $row['Time_In'] ? date('Y-m-d', strtotime($row['Time_In'])) : null;
            $newDate = date('Y-m-d', strtotime($newTimeIn));
            if ($oldDate !== $newDate) {
                $dup = $conn->prepare('SELECT COUNT(*) FROM attendance WHERE User_ID = ? AND DATE(Time_In) = ? AND ID <> ?');
                $dup->execute([$row['User_ID'], $newDate, $attendanceId]);
                if ($dup->fetchColumn() > 0) { echo json_encode(['success' => false, 'message' => 'Another attendance exists for this guard on ' . date('F j, Y', strtotime($newDate))]); break; }
            }

            $conn->beginTransaction();
            $upd = $conn->prepare('UPDATE attendance SET Time_In = ?, Time_Out = ?, IP_Address = ? WHERE ID = ?');
            $upd->execute([$newTimeIn, $newTimeOut ?: null, $_SERVER['REMOTE_ADDR'] ?? '', $attendanceId]);

            $actor = currentUserName($conn);
            $oldInDate = $row['Time_In'] ? date('F j, Y', strtotime($row['Time_In'])) : '';
            $oldInTime = $row['Time_In'] ? date('g:i A', strtotime($row['Time_In'])) : '';
            $oldOutTime = $row['Time_Out'] ? date('g:i A', strtotime($row['Time_Out'])) : 'no time out';
            $newInDate = date('F j, Y', strtotime($newTimeIn));
            $newInTime = date('g:i A', strtotime($newTimeIn));
            $newOutTime = $newTimeOut ? date('g:i A', strtotime($newTimeOut)) : 'no time out';
            $logDetails = $actor . ' edited attendance for ' . $row['First_Name'] . ' ' . $row['Last_Name'] . ' - Old: ' . $oldInDate . ' - ' . $oldInTime . ' to ' . $oldOutTime . ' - New: ' . $newInDate . ' - ' . $newInTime . ' to ' . $newOutTime . ' - Reason: ' . $reason;
            $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, "Attendance Edit", ?, NOW())');
            $log->execute([$_SESSION['user_id'], $logDetails]);
            try {
                $audit = $conn->prepare('INSERT INTO edit_attendance_logs (Attendance_ID, Editor_User_ID, Editor_Name, Old_Time_In, New_Time_In, Old_Time_Out, New_Time_Out, Edit_Timestamp, IP_Address, Action_Description) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)');
                $audit->execute([$attendanceId, $_SESSION['user_id'], $actor, $row['Time_In'], $newTimeIn, $row['Time_Out'], $newTimeOut ?: null, $_SERVER['REMOTE_ADDR'] ?? '', 'Edited attendance - Reason: ' . $reason]);
            } catch (Exception $e) {}
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
            break;
        }
        case 'archive': {
            if ($method !== 'POST') { echo json_encode(['success' => false, 'message' => 'Invalid request method']); break; }
            $attendanceId = isset($_POST['attendanceId']) ? (int)$_POST['attendanceId'] : 0;
            $reason = $_POST['reason'] ?? '';
            if (!$attendanceId || $reason === '') { echo json_encode(['success' => false, 'message' => 'Missing required data']); break; }
            $guardId = getAttendanceOwnerGuard($conn, $attendanceId);
            if (!$guardId || !isGuardManagedByOIC($conn, $guardId)) { echo json_encode(['success' => false, 'message' => 'Unauthorized or attendance not found']); break; }

            $conn->beginTransaction();
            $getStmt = $conn->prepare('SELECT a.User_ID, a.Time_In, a.Time_Out, u.First_Name, u.Last_Name FROM attendance a JOIN users u ON a.User_ID = u.User_ID WHERE a.ID = ?');
            $getStmt->execute([$attendanceId]);
            $attendanceData = $getStmt->fetch(PDO::FETCH_ASSOC);
            if (!$attendanceData) { echo json_encode(['success' => false, 'message' => 'Attendance record not found']); break; }

            $archiveStmt = $conn->prepare('INSERT INTO archive_dtr_data (ID, User_ID, first_name, last_name, time_in, time_out) VALUES (?, ?, ?, ?, ?, ?)');
            $archiveStmt->execute([$attendanceId, $attendanceData['User_ID'], $attendanceData['First_Name'], $attendanceData['Last_Name'], $attendanceData['Time_In'], $attendanceData['Time_Out']]);

            $deleteStmt = $conn->prepare('DELETE FROM attendance WHERE ID = ?');
            $deleteStmt->execute([$attendanceId]);

            $actor = currentUserName($conn);
            $inDate = $attendanceData['Time_In'] ? date('F j, Y', strtotime($attendanceData['Time_In'])) : '';
            $inTime = $attendanceData['Time_In'] ? date('g:i A', strtotime($attendanceData['Time_In'])) : '';
            if (!empty($attendanceData['Time_Out'])) {
                $outDate = date('F j, Y', strtotime($attendanceData['Time_Out']));
                $outTime = date('g:i A', strtotime($attendanceData['Time_Out']));
                $dateRange = ($inDate === $outDate) ? $inDate : ($inDate . ' to ' . $outDate);
                $timeRange = $inTime . ' to ' . $outTime;
            } else {
                $dateRange = $inDate;
                $timeRange = $inTime . ' to no time out';
            }
            $guardFullName = $attendanceData['First_Name'] . ' ' . $attendanceData['Last_Name'];
            // Include explicit record ID so archive listing (option A) can reliably join via pattern 'record ID <ID>'
            $archiveDetails = $actor . ' archived attendance record ID ' . $attendanceId . ' for ' . $guardFullName . ' - Date: ' . $dateRange . ' - Time: ' . $timeRange . ' - Reason: ' . $reason;
            $activityStmt = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())');
            $activityStmt->execute([$_SESSION['user_id'], 'Attendance Archive', $archiveDetails]);

            $conn->commit();
            echo json_encode(['success' => true]);
            break;
        }
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
