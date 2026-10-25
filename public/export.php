<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/AttendanceSheetExporter.php';

requireLogin();

$action = $_GET['action'] ?? 'excel';
$offeringId = (int)($_GET['id'] ?? 0);

if ($offeringId <= 0) {
    die("Invalid course offering ID.");
}

$exporter = new AttendanceSheetExporter();

if ($action === 'pdf') {
    $exporter->exportPdf($offeringId);
} else {
    $exporter->exportExcel($offeringId);
}
