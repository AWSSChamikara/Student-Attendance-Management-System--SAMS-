<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$dir = __DIR__;
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('EIS_Students_Roster');

$headers = [
    'A1' => 'Registration Number',
    'B1' => 'Index Number',
    'C1' => 'NIC Number',
    'D1' => 'Phone Number',
    'E1' => 'Permanent Address',
    'F1' => 'Full Name',
    'G1' => 'Gender'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

$students2024 = [
    ['ICT/2024/001', 'ICT2024001', '200315401234', '0771234567', 'No. 45, Temple Road, Kandy', 'Amal Samantha Kumara', 'Male'],
    ['ICT/2024/002', 'ICT2024002', '200368204567', '0712345678', 'No. 12, Main Street, Kurunegala', 'Chathurika Maduwanthi', 'Female'],
    ['ICT/2024/003', 'ICT2024003', '200324109876', '0763456789', 'Main Street, Mihintale', 'Dulan Sandaruwan', 'Male'],
    ['ICT/2024/004', 'ICT2024004', '200371306543', '0784567890', 'Hospital Junction, Anuradhapura', 'Fathima Rizna', 'Female'],
    ['ICT/2024/005', 'ICT2024005', '200330501122', '0705678901', 'Station Road, Matale', 'Gayan Sandeepa', 'Male'],
    ['ICT/2024/006', 'ICT2024006', '200382403344', '0756789012', 'Circular Road, Kegalle', 'Hiruni Sewwandi', 'Female'],
    ['ICT/2024/007', 'ICT2024007', '200319805566', '0727890123', 'Lake Drive, Dambulla', 'Isuru Pramodh', 'Male'],
    ['ICT/2024/008', 'ICT2024008', '200365707788', '0778901234', 'Hill Street, Polonnaruwa', 'Janani Kavindya', 'Female'],
    ['ICT/2024/009', 'ICT2024009', '200328909900', '0719012345', 'Anuradhapura Road, Vavuniya', 'Kasun Chamara', 'Male'],
    ['ICT/2024/010', 'ICT2024010', '200377601212', '0760123456', 'Beach Road, Trincomalee', 'Lihini Amanda', 'Female'],
    ['ICT/2024/020', 'ICT2024020', '200388902345', '0779988776', 'Stage 2, Anuradhapura', 'Rashmi Anuradha', 'Female']
];

$row = 2;
foreach ($students2024 as $st) {
    $sheet->setCellValue('A' . $row, $st[0]);
    $sheet->setCellValue('B' . $row, $st[1]);
    $sheet->setCellValue('C' . $row, $st[2]);
    $sheet->setCellValue('D' . $row, $st[3]);
    $sheet->setCellValue('E' . $row, $st[4]);
    $sheet->setCellValue('F' . $row, $st[5]);
    $sheet->setCellValue('G' . $row, $st[6]);
    $row++;
}

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(28);

foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$outputFile = __DIR__ . '/EIS_Student_Export_Batch2024.xlsx';
$writer->save($outputFile);
echo "Created: {$outputFile}\n";

$outputCsv = __DIR__ . '/EIS_Student_Export_Batch2024.csv';
$csvWriter = new Csv($spreadsheet);
$csvWriter->save($outputCsv);
echo "Created: {$outputCsv}\n";
