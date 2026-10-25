<?php

require_once __DIR__ . '/../config/app.php';

requireLogin();

$sampleFile = __DIR__ . '/../sample_data/EIS_Student_Export_Batch2024.xlsx';
if (!file_exists($sampleFile)) {
    die("Sample EIS file not found.");
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="EIS_Student_Export_Batch2024_RUSL.xlsx"');
header('Content-Length: ' . filesize($sampleFile));
header('Cache-Control: max-age=0');

readfile($sampleFile);
exit;
