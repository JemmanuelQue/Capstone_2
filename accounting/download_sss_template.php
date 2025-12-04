<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../db_connection.php';

// Require Accounting role (4)
if (!validateSession($conn, 4)) { exit(); }

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$headers = [
    'range_min','range_max','msc_regular_ss','msc_ec','msc_mpf','msc_total',
    'employer_regular_ss','employee_regular_ss','employer_mpf','employee_mpf',
    'employer_ec','employer_total','employee_total','total_contribution','effective_date'
];

$sampleRows = [
    [0, 5249.99, 5000, 0, 0, 5000, 500, 250, 0, 0, 10, 510, 250, 760, '2025-01-01'],
    [5250, 5749.99, 5500, 0, 0, 5500, 550, 275, 0, 0, 10, 560, 275, 835, '2025-01-01']
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('SSS Template');

// Header row
$col = 1;
foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($col, 1, $h);
    $col++;
}

// Sample data
$row = 2;
foreach ($sampleRows as $r) {
    $col = 1;
    foreach ($r as $val) {
        $sheet->setCellValueByColumnAndRow($col, $row, $val);
        $col++;
    }
    $row++;
}

// Style header
$sheet->getStyle('A1:O1')->getFont()->setBold(true);
$sheet->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0F7FA');
$sheet->getStyle('A1:O1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Autosize columns
foreach (range('A','O') as $colLetter) {
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

$filename = 'sss_contribution_template.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
