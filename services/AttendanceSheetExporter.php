<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/EligibilityService.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use Dompdf\Dompdf;
use Dompdf\Options;

class AttendanceSheetExporter {
    private EligibilityService $eligibilityService;

    public function __construct() {
        $this->eligibilityService = new EligibilityService();
    }

    public function exportExcel(int $offeringId): void {
        $data = $this->eligibilityService->getOfferingAttendanceSheet($offeringId);
        if (empty($data)) {
            die("Course offering not found.");
        }

        $offering = $data['offering'];
        $lecturers = $data['lecturers'];
        $weeks = $data['weeks'];
        $students = $data['students'];

        $lecturerNames = !empty($lecturers)
            ? implode(', ', array_map(fn($l) => $l['full_name'] . ($l['is_coordinating'] ? ' (Coordinating)' : ''), $lecturers))
            : 'Not Assigned';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance_Eligibility');

        $sheet->setCellValue('A1', APP_UNIVERSITY);
        $sheet->setCellValue('A2', APP_FACULTY . ' - ' . APP_DEPT);
        $sheet->setCellValue('A3', 'OFFICIAL ATTENDANCE & EXAMINATION ELIGIBILITY REPORT');

        $sheet->mergeCells('A1:AA1');
        $sheet->mergeCells('A2:AA2');
        $sheet->mergeCells('A3:AA3');

        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
        $sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true);
        $sheet->getStyle('A3')->getFont()->setSize(11)->setBold(true)->setUnderline(true);
        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A5', 'Course Code:');
        $sheet->setCellValue('C5', $offering['course_code']);
        $sheet->setCellValue('F5', 'Course Name:');
        $sheet->setCellValue('I5', $offering['course_name']);

        $sheet->setCellValue('A6', 'Session Type:');
        $sheet->setCellValue('C6', strtoupper($offering['session_type']));
        $sheet->setCellValue('F6', 'Lecturer in Charge:');
        $sheet->setCellValue('I6', $lecturerNames);

        $sheet->setCellValue('A7', 'Batch / Year:');
        $sheet->setCellValue('C7', $offering['batch_name']);
        $sheet->setCellValue('F7', 'Semester / Year:');
        $sheet->setCellValue('I7', 'Semester ' . $offering['semester_number'] . ' (' . $offering['academic_year'] . ')');

        $sheet->setCellValue('T5', 'Eligibility Criteria:');
        $sheet->setCellValue('W5', $offering['eligibility_threshold'] . '% Minimum Attendance');
        $sheet->setCellValue('T6', 'Report Status:');
        $sheet->setCellValue('W6', $offering['is_locked'] ? 'FINAL LOCKED REPORT' : 'DRAFT (Pre-Eligibility)');

        $sheet->getStyle('A5:A7')->getFont()->setBold(true);
        $sheet->getStyle('F5:F7')->getFont()->setBold(true);
        $sheet->getStyle('T5:T6')->getFont()->setBold(true);

        $sheet->setCellValue('A9', 'No');
        $sheet->setCellValue('B9', 'Title');
        $sheet->setCellValue('C9', 'Name');
        $sheet->setCellValue('D9', 'Index Number');
        $sheet->setCellValue('E9', 'Registration Number');

        $sheet->mergeCells('A9:A10');
        $sheet->mergeCells('B9:B10');
        $sheet->mergeCells('C9:C10');
        $sheet->mergeCells('D9:D10');
        $sheet->mergeCells('E9:E10');

        $weekCols = ['F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T'];
        for ($w = 1; $w <= 15; $w++) {
            $col = $weekCols[$w - 1];
            $sheet->setCellValue($col . '9', 'Wk ' . $w);
            $dateStr = $weeks[$w]['session_date'] ? date('m/d', strtotime($weeks[$w]['session_date'])) : '-';
            $sheet->setCellValue($col . '10', $dateStr);
        }

        $sheet->setCellValue('U9', "Total Present\nWeeks");
        $sheet->setCellValue('V9', "Total Absent\nWeeks");
        $sheet->setCellValue('W9', "MC\nSubmitted");
        $sheet->setCellValue('X9', "Excuse\nSubmitted");
        $sheet->setCellValue('Y9', "Attendance\nPercentage");
        $sheet->setCellValue('Z9', "Eligibility\nStatus");

        $sheet->mergeCells('U9:U10');
        $sheet->mergeCells('V9:V10');
        $sheet->mergeCells('W9:W10');
        $sheet->mergeCells('X9:X10');
        $sheet->mergeCells('Y9:Y10');
        $sheet->mergeCells('Z9:Z10');

        $headerRange = 'A9:Z10';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');

        $currentRow = 11;
        foreach ($students as $st) {
            $sheet->setCellValue('A' . $currentRow, $st['no']);
            $sheet->setCellValue('B' . $currentRow, $st['title']);
            $sheet->setCellValue('C' . $currentRow, $st['name']);
            $sheet->setCellValue('D' . $currentRow, $st['index_number']);
            $sheet->setCellValue('E' . $currentRow, $st['reg_number']);

            for ($w = 1; $w <= 15; $w++) {
                $col = $weekCols[$w - 1];
                $val = $st['weeks'][$w] ?? '-';
                $sheet->setCellValue($col . $currentRow, $val);
            }

            $sheet->setCellValue('U' . $currentRow, $st['present_weeks']);
            $sheet->setCellValue('V' . $currentRow, $st['absent_weeks']);
            $sheet->setCellValue('W' . $currentRow, $st['mc_submitted']);
            $sheet->setCellValue('X' . $currentRow, $st['ex_submitted']);
            $sheet->setCellValue('Y' . $currentRow, $st['attendance_percentage'] . '%');
            $sheet->setCellValue('Z' . $currentRow, $st['eligibility_status']);

            $sheet->getStyle('A' . $currentRow . ':B' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $currentRow . ':E' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $currentRow . ':T' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('U' . $currentRow . ':Y' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $isEligible = $st['is_eligible'];
            $statusCell = 'Z' . $currentRow;
            if ($isEligible) {
                $sheet->getStyle($statusCell)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('006600'))->setBold(true);
            } else {
                $sheet->getStyle($statusCell)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('CC0000'))->setBold(true);
            }
            $sheet->getStyle($statusCell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $currentRow++;
        }

        $tableRange = 'A9:Z' . ($currentRow - 1);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sigRow = $currentRow + 3;
        $sheet->setCellValue('C' . $sigRow, '..........................................................');
        $sheet->setCellValue('T' . $sigRow, '..........................................................');
        $sheet->setCellValue('C' . ($sigRow + 1), 'Lecturer in Charge');
        $sheet->setCellValue('T' . ($sigRow + 1), 'Head of Department (HoD)');
        $sheet->getStyle('C' . ($sigRow + 1) . ':T' . ($sigRow + 1))->getFont()->setBold(true);

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        foreach ($weekCols as $col) {
            $sheet->getColumnDimension($col)->setWidth(5.5);
        }
        foreach (['U','V','W','X','Y'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(11);
        }
        $sheet->getColumnDimension('Z')->setWidth(26);

        $sheet->getProtection()->setPassword('SAMS_SECURE_2026');
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setSort(false);
        $sheet->getProtection()->setInsertRows(false);
        $sheet->getProtection()->setFormatCells(false);

        $filename = sprintf('Attendance_Eligibility_%s_%s_%s.xlsx',
            $offering['course_code'],
            $offering['session_type'],
            date('Ymd_His')
        );

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function exportPdf(int $offeringId): void {
        $data = $this->eligibilityService->getOfferingAttendanceSheet($offeringId);
        if (empty($data)) {
            die("Course offering not found.");
        }

        $offering = $data['offering'];
        $lecturers = $data['lecturers'];
        $weeks = $data['weeks'];
        $students = $data['students'];

        $lecturerNames = !empty($lecturers)
            ? implode(', ', array_map(fn($l) => $l['full_name'] . ($l['is_coordinating'] ? ' (Coordinating)' : ''), $lecturers))
            : 'Not Assigned';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Attendance & Eligibility - <?= e($offering['course_code']) ?></title>
            <style>
                @page { margin: 12mm 10mm; size: landscape; }
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 9px; color: #111; line-height: 1.2; }
                .header-table { width: 100%; border-bottom: 2px solid #1E3A8A; margin-bottom: 8px; padding-bottom: 4px; }
                .header-table td { vertical-align: top; }
                .title-main { font-size: 14px; font-weight: bold; color: #1E3A8A; text-transform: uppercase; margin: 0; }
                .title-sub { font-size: 11px; font-weight: bold; color: #333; margin: 2px 0; }
                .meta-table { width: 100%; margin-bottom: 10px; font-size: 9.5px; border-collapse: collapse; }
                .meta-table td { padding: 2px 4px; }
                .meta-label { font-weight: bold; color: #1E3A8A; }
                .badge-locked { background-color: #DC2626; color: #fff; padding: 2px 6px; border-radius: 3px; font-weight: bold; }
                .badge-draft { background-color: #D97706; color: #fff; padding: 2px 6px; border-radius: 3px; font-weight: bold; }

                table.data-table { width: 100%; border-collapse: collapse; font-size: 8px; }
                table.data-table th, table.data-table td { border: 0.5px solid #666; padding: 3px 2px; text-align: center; }
                table.data-table th { background-color: #1E3A8A; color: #ffffff; font-weight: bold; }
                table.data-table tr:nth-child(even) { background-color: #F8FAFC; }
                .text-left { text-align: left !important; padding-left: 4px !important; }
                .status-eligible { color: #047857; font-weight: bold; }
                .status-ineligible { color: #B91C1C; font-weight: bold; }

                .footer-signatures { width: 100%; margin-top: 30px; }
                .footer-signatures td { text-align: center; vertical-align: top; padding-top: 15px; }
                .sig-line { width: 220px; border-top: 1px dashed #333; margin: 0 auto 5px auto; }
            </style>
        </head>
        <body>
            <table class="header-table">
                <tr>
                    <td style="width: 70%;">
                        <div class="title-main"><?= e(APP_UNIVERSITY) ?></div>
                        <div class="title-sub"><?= e(APP_FACULTY) ?> - <?= e(APP_DEPT) ?></div>
                        <div style="font-weight: bold; color: #4B5563;">OFFICIAL ATTENDANCE & EXAMINATION ELIGIBILITY REPORT</div>
                    </td>
                    <td style="width: 30%; text-align: right;">
                        <?php if ($offering['is_locked']): ?>
                            <span class="badge-locked">FINAL LOCKED REPORT</span><br>
                            <small style="color: #666;">Locked: <?= e(date('Y-m-d H:i', strtotime($offering['locked_at']))) ?></small>
                        <?php else: ?>
                            <span class="badge-draft">DRAFT REPORT</span><br>
                            <small style="color: #666;">Attendance can still be modified</small>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <table class="meta-table">
                <tr>
                    <td style="width: 12%;" class="meta-label">Course:</td>
                    <td style="width: 38%;"><?= e($offering['course_code']) ?> - <?= e($offering['course_name']) ?></td>
                    <td style="width: 15%;" class="meta-label">Lecturer in Charge:</td>
                    <td style="width: 35%;"><?= e($lecturerNames) ?></td>
                </tr>
                <tr>
                    <td class="meta-label">Session Type:</td>
                    <td style="text-transform: uppercase; font-weight: bold;"><?= e($offering['session_type']) ?></td>
                    <td class="meta-label">Batch & Semester:</td>
                    <td><?= e($offering['batch_name']) ?> | Semester <?= e($offering['semester_number']) ?> (<?= e($offering['academic_year']) ?>)</td>
                </tr>
                <tr>
                    <td class="meta-label">Eligibility Threshold:</td>
                    <td style="color: #B91C1C; font-weight: bold;"><?= e($offering['eligibility_threshold']) ?>% Minimum Attendance Required</td>
                    <td class="meta-label">Date Generated:</td>
                    <td><?= date('Y-m-d H:i:s') ?></td>
                </tr>
            </table>

            <table class="data-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 18px;">No</th>
                        <th rowspan="2" style="width: 22px;">Title</th>
                        <th rowspan="2" style="width: 110px;">Name</th>
                        <th rowspan="2" style="width: 55px;">Index No</th>
                        <th rowspan="2" style="width: 65px;">Reg No</th>
                        <?php for ($w = 1; $w <= 15; $w++): ?>
                            <th style="width: 18px;">W<?= $w ?></th>
                        <?php endfor; ?>
                        <th rowspan="2" style="width: 26px;">Pres.</th>
                        <th rowspan="2" style="width: 26px;">Abs.</th>
                        <th rowspan="2" style="width: 26px;">MC</th>
                        <th rowspan="2" style="width: 26px;">Ex</th>
                        <th rowspan="2" style="width: 38px;">Att %</th>
                        <th rowspan="2" style="width: 75px;">Eligibility</th>
                    </tr>
                    <tr>
                        <?php for ($w = 1; $w <= 15; $w++): ?>
                            <th style="font-size: 7px; background-color: #2563EB;">
                                <?= $weeks[$w]['session_date'] ? date('m/d', strtotime($weeks[$w]['session_date'])) : '-' ?>
                            </th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $st): ?>
                        <tr>
                            <td><?= $st['no'] ?></td>
                            <td><?= e($st['title']) ?></td>
                            <td class="text-left"><?= e($st['name']) ?></td>
                            <td><?= e($st['index_number']) ?></td>
                            <td><?= e($st['reg_number']) ?></td>
                            <?php for ($w = 1; $w <= 15; $w++):
                                $stt = $st['weeks'][$w] ?? '-';
                                $bg = '';
                                if ($stt === '1') $bg = 'background-color:#E0F2FE;';
                                elseif ($stt === '0') $bg = 'background-color:#FEE2E2; color:#B91C1C; font-weight:bold;';
                                elseif ($stt === 'MC') $bg = 'background-color:#FEF3C7; font-weight:bold;';
                                elseif ($stt === 'Ex') $bg = 'background-color:#EDE9FE; font-weight:bold;';
                                elseif ($stt === 'SRC') $bg = 'background-color:#DCFCE7; font-weight:bold;';
                            ?>
                                <td style="<?= $bg ?>"><?= e($stt) ?></td>
                            <?php endfor; ?>
                            <td style="font-weight: bold;"><?= $st['present_weeks'] ?></td>
                            <td><?= $st['absent_weeks'] ?></td>
                            <td><?= $st['mc_submitted'] ?></td>
                            <td><?= $st['ex_submitted'] ?></td>
                            <td style="font-weight: bold;"><?= $st['attendance_percentage'] ?>%</td>
                            <td class="<?= $st['is_eligible'] ? 'status-eligible' : 'status-ineligible' ?>">
                                <?= e($st['eligibility_status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="footer-signatures">
                <tr>
                    <td style="width: 50%;">
                        <div class="sig-line"></div>
                        <strong>Lecturer in Charge of Course</strong><br>
                        <?= e($lecturerNames) ?>
                    </td>
                    <td style="width: 50%;">
                        <div class="sig-line"></div>
                        <strong>Head of Department (HoD)</strong><br>
                        <?= e(APP_DEPT) ?>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf('Attendance_Eligibility_%s_%s_%s.pdf',
            $offering['course_code'],
            $offering['session_type'],
            date('Ymd_His')
        );

        $dompdf->stream($filename, ['Attachment' => 1]);
        exit;
    }
}
