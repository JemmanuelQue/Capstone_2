<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../includes/session_check.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Require Accounting role (4)
if (!validateSession($conn, 4)) {
    header('Location: employee_share.php?import=error&reason=unauthorized');
    exit();
}

function sanitize_num($v) {
    if ($v === null || $v === '') return 0.0;
    if (is_string($v)) { $v = preg_replace('/[^0-9.\-]/', '', $v); }
    return is_numeric($v) ? round((float)$v, 2) : 0.0;
}

try {
    if (!isset($_FILES['sss_file']) || $_FILES['sss_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error.');
    }
    $tmp = $_FILES['sss_file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['sss_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') {
        throw new Exception('Only .xlsx files are supported.');
    }

    $effectiveOverride = isset($_POST['effective_date_override']) && $_POST['effective_date_override'] !== ''
        ? $_POST['effective_date_override'] : null;
    $updateExisting = isset($_POST['update_existing']);

    $spreadsheet = IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestDataRow();
    $highestCol = $sheet->getHighestDataColumn();

    // Read header map
    $headersRequired = [
        'range_min','range_max','msc_regular_ss','msc_ec','msc_mpf','msc_total',
        'employer_regular_ss','employee_regular_ss','employer_mpf','employee_mpf',
        'employer_ec','employer_total','employee_total','total_contribution','effective_date'
    ];
    $headerMap = [];
    $colIndex = 1;
    foreach (range('A', $highestCol) as $colLetter) {
        $name = strtolower(trim((string)$sheet->getCell($colLetter.'1')->getValue()));
        if ($name !== '') { $headerMap[$name] = $colLetter; }
        $colIndex++;
    }
    foreach ($headersRequired as $h) {
        if (!isset($headerMap[$h])) {
            throw new Exception('Invalid template. Missing header: ' . $h);
        }
    }

    $inserted = 0; $updated = 0; $skipped = 0;
    $conn->beginTransaction();

    for ($row = 2; $row <= $highestRow; $row++) {
        $rangeMin = $sheet->getCell($headerMap['range_min'].$row)->getCalculatedValue();
        $rangeMax = $sheet->getCell($headerMap['range_max'].$row)->getCalculatedValue();
        if ($rangeMin === null || $rangeMin === '' || $rangeMax === null || $rangeMax === '') {
            $skipped++; continue;
        }

        $data = [
            'range_min' => sanitize_num($rangeMin),
            'range_max' => sanitize_num($rangeMax),
            'msc_regular_ss' => sanitize_num($sheet->getCell($headerMap['msc_regular_ss'].$row)->getCalculatedValue()),
            'msc_ec' => sanitize_num($sheet->getCell($headerMap['msc_ec'].$row)->getCalculatedValue()),
            'msc_mpf' => sanitize_num($sheet->getCell($headerMap['msc_mpf'].$row)->getCalculatedValue()),
            'msc_total' => sanitize_num($sheet->getCell($headerMap['msc_total'].$row)->getCalculatedValue()),
            'employer_regular_ss' => sanitize_num($sheet->getCell($headerMap['employer_regular_ss'].$row)->getCalculatedValue()),
            'employee_regular_ss' => sanitize_num($sheet->getCell($headerMap['employee_regular_ss'].$row)->getCalculatedValue()),
            'employer_mpf' => sanitize_num($sheet->getCell($headerMap['employer_mpf'].$row)->getCalculatedValue()),
            'employee_mpf' => sanitize_num($sheet->getCell($headerMap['employee_mpf'].$row)->getCalculatedValue()),
            'employer_ec' => sanitize_num($sheet->getCell($headerMap['employer_ec'].$row)->getCalculatedValue()),
            'employer_total' => sanitize_num($sheet->getCell($headerMap['employer_total'].$row)->getCalculatedValue()),
            'employee_total' => sanitize_num($sheet->getCell($headerMap['employee_total'].$row)->getCalculatedValue()),
            'total_contribution' => sanitize_num($sheet->getCell($headerMap['total_contribution'].$row)->getCalculatedValue()),
            'effective_date' => (string)$sheet->getCell($headerMap['effective_date'].$row)->getFormattedValue(),
        ];

        if (empty($data['effective_date'])) { $data['effective_date'] = $effectiveOverride; }
        if (empty($data['effective_date'])) { $skipped++; continue; }

        // Upsert logic
        $check = $conn->prepare("SELECT id FROM sss_contribution_table WHERE range_min = ? AND range_max = ? AND effective_date = ? LIMIT 1");
        $check->execute([$data['range_min'], $data['range_max'], $data['effective_date']]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing && $updateExisting) {
            $upd = $conn->prepare("UPDATE sss_contribution_table SET 
                msc_regular_ss=?, msc_ec=?, msc_mpf=?, msc_total=?,
                employer_regular_ss=?, employee_regular_ss=?, employer_mpf=?, employee_mpf=?,
                employer_ec=?, employer_total=?, employee_total=?, total_contribution=?
                WHERE id = ?");
            $upd->execute([
                $data['msc_regular_ss'], $data['msc_ec'], $data['msc_mpf'], $data['msc_total'],
                $data['employer_regular_ss'], $data['employee_regular_ss'], $data['employer_mpf'], $data['employee_mpf'],
                $data['employer_ec'], $data['employer_total'], $data['employee_total'], $data['total_contribution'],
                $existing['id']
            ]);
            $updated++;
        } elseif (!$existing) {
            $ins = $conn->prepare("INSERT INTO sss_contribution_table
                (range_min, range_max, msc_regular_ss, msc_ec, msc_mpf, msc_total,
                 employer_regular_ss, employee_regular_ss, employer_mpf, employee_mpf,
                 employer_ec, employer_total, employee_total, total_contribution, effective_date)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([
                $data['range_min'], $data['range_max'], $data['msc_regular_ss'], $data['msc_ec'], $data['msc_mpf'], $data['msc_total'],
                $data['employer_regular_ss'], $data['employee_regular_ss'], $data['employer_mpf'], $data['employee_mpf'],
                $data['employer_ec'], $data['employer_total'], $data['employee_total'], $data['total_contribution'], $data['effective_date']
            ]);
            $inserted++;
        } else {
            $skipped++;
        }
    }

    $conn->commit();
    header('Location: employee_share.php?import=success&inserted=' . $inserted . '&updated=' . $updated);
    exit();

} catch (Throwable $e) {
    if ($conn && $conn->inTransaction()) { $conn->rollBack(); }
    $reason = urlencode($e->getMessage());
    header('Location: employee_share.php?import=error&reason=' . $reason);
    exit();
}
