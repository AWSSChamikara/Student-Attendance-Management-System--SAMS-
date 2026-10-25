<?php

require_once __DIR__ . '/../config/app.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class EisImporter {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function importFile(string $filePath, int $batchId, int $uploadedByUserId): array {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . $filePath);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            throw new Exception("Uploaded file is empty.");
        }

        $headerRow = array_shift($rows);
        $colMap = [
            'reg_no' => null,
            'index_no' => null,
            'nic' => null,
            'name' => null,
            'gender' => null
        ];

        foreach ($headerRow as $colLetter => $headerText) {
            $h = strtolower(trim((string)$headerText));
            if (str_contains($h, 'reg') || str_contains($h, 'registration')) {
                $colMap['reg_no'] = $colLetter;
            } elseif (str_contains($h, 'index')) {
                $colMap['index_no'] = $colLetter;
            } elseif (str_contains($h, 'nic') || str_contains($h, 'identity') || str_contains($h, 'national')) {
                $colMap['nic'] = $colLetter;
            } elseif (str_contains($h, 'name') && !str_contains($h, 'batch')) {
                $colMap['name'] = $colLetter;
            } elseif (str_contains($h, 'gender') || str_contains($h, 'sex')) {
                $colMap['gender'] = $colLetter;
            }
        }

        if (!$colMap['reg_no']) $colMap['reg_no'] = 'A';
        if (!$colMap['index_no']) $colMap['index_no'] = 'B';
        if (!$colMap['nic']) $colMap['nic'] = 'C';
        if (!$colMap['name']) $colMap['name'] = 'F';
        if (!$colMap['gender']) $colMap['gender'] = 'G';

        $totalRows = 0;
        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($rows as $rowIndex => $row) {
            $regNo = strtoupper(trim((string)($row[$colMap['reg_no']] ?? '')));
            $indexNo = strtoupper(trim((string)($row[$colMap['index_no']] ?? '')));
            $nic = strtoupper(trim((string)($row[$colMap['nic']] ?? '')));
            $name = trim((string)($row[$colMap['name']] ?? ''));
            $genderRaw = trim((string)($row[$colMap['gender']] ?? ''));

            if (empty($regNo) && empty($indexNo) && empty($name)) {
                continue;
            }

            $totalRows++;

            if (empty($regNo) || empty($indexNo) || empty($name)) {
                $skippedCount++;
                $errors[] = "Row {$rowIndex}: Missing required fields (Registration No, Index No, or Name).";
                continue;
            }

            $genderLower = strtolower($genderRaw);
            $gender = (str_starts_with($genderLower, 'f')) ? 'Female' : 'Male';

            $title = generateTitleFromGender($gender);

            $studentEmail = generateStudentEmailFromRegNo($regNo);

            $rawPassword = !empty($nic) ? $nic : 'Student@123';
            $studentPasswordHash = password_hash($rawPassword, PASSWORD_BCRYPT);

            try {

                $stmt = $this->db->prepare("
                    INSERT INTO students (reg_number, index_number, nic, name, gender, title, batch_id, email)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        nic = IF(VALUES(nic) != '', VALUES(nic), nic),
                        name = VALUES(name),
                        gender = VALUES(gender),
                        title = VALUES(title),
                        batch_id = VALUES(batch_id),
                        email = VALUES(email)
                ");
                $stmt->execute([$regNo, $indexNo, $nic, $name, $gender, $title, $batchId, $studentEmail]);

                $studentId = (int)$this->db->lastInsertId();
                if ($studentId === 0) {
                    $idStmt = $this->db->prepare("SELECT id FROM students WHERE index_number = ?");
                    $idStmt->execute([$indexNo]);
                    $studentId = (int)$idStmt->fetchColumn();
                    $updatedCount++;
                } else {
                    $importedCount++;
                }

                $userStmt = $this->db->prepare("
                    INSERT INTO users (email, password_hash, full_name, role, student_id, is_email_verified, description)
                    VALUES (?, ?, ?, 'student', ?, 1, 'Undergraduate student, Department of Computing.')
                    ON DUPLICATE KEY UPDATE
                        password_hash = VALUES(password_hash),
                        full_name = VALUES(full_name),
                        student_id = VALUES(student_id),
                        is_email_verified = 1
                ");
                $userStmt->execute([$studentEmail, $studentPasswordHash, $name, $studentId]);

                $enrollStmt = $this->db->prepare("
                    INSERT IGNORE INTO course_enrollments (offering_id, student_id, practical_qualification_status)
                    SELECT co.id, ?, 'pending'
                    FROM course_offerings co
                    JOIN courses c ON co.course_id = c.id
                    WHERE c.batch_id = ?
                ");
                $enrollStmt->execute([$studentId, $batchId]);

            } catch (Exception $ex) {
                $skippedCount++;
                $errors[] = "Row {$rowIndex} ({$indexNo}): " . $ex->getMessage();
            }
        }

        $logStmt = $this->db->prepare("
            INSERT INTO eis_import_logs (file_name, batch_id, total_rows, imported_count, skipped_count, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            basename($filePath),
            $batchId,
            $totalRows,
            $importedCount + $updatedCount,
            $skippedCount,
            $uploadedByUserId
        ]);

        return [
            'total_rows' => $totalRows,
            'imported' => $importedCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'errors' => $errors
        ];
    }
}
