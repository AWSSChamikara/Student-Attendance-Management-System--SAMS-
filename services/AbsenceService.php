<?php

require_once __DIR__ . '/../config/app.php';

class AbsenceService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function submitRequest(
        int $studentId,
        int $offeringId,
        int $weekNumber,
        string $requestType,
        string $absenceDate,
        string $reason,
        ?array $uploadedFile = null
    ): array {

        $stmt = $this->db->prepare("SELECT is_locked, display_name FROM course_offerings WHERE id = ?");
        $stmt->execute([$offeringId]);
        $offering = $stmt->fetch();

        if (!$offering) {
            return ['success' => false, 'message' => 'Course offering not found.'];
        }

        if ($offering['is_locked'] && in_array($requestType, ['Ex', 'SRC', 'MC'], true)) {
            return [
                'success' => false,
                'message' => 'Cannot submit request. The examination eligibility report for this course has already been released and locked.'
            ];
        }

        $submissionDate = date('Y-m-d');
        $absenceTimestamp = strtotime($absenceDate);
        $submissionTimestamp = strtotime($submissionDate);
        $diffDays = (int)floor(($submissionTimestamp - $absenceTimestamp) / (60 * 60 * 24));
        if ($diffDays < 0) {
            $diffDays = 0;
        }

        $isWithin14Days = 1;
        if ($requestType === 'MC' && $diffDays > MC_MAX_DAYS) {
            $isWithin14Days = 0;
        }

        $savedFilePath = null;
        if ($uploadedFile && !empty($uploadedFile['name']) && $uploadedFile['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/' . ($requestType === 'MC' ? 'medicals/' : 'excuses/');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $newFileName = sprintf('doc_%d_%d_W%d_%s_%s.%s',
                $studentId,
                $offeringId,
                $weekNumber,
                $requestType,
                date('Ymd_His'),
                $extension
            );
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($uploadedFile['tmp_name'], $destination)) {
                $savedFilePath = $newFileName;
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO absence_requests
            (student_id, offering_id, week_number, request_type, absence_date, submission_date, days_to_submission, is_within_14_days, reason, document_path, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $studentId,
            $offeringId,
            $weekNumber,
            $requestType,
            $absenceDate,
            $submissionDate,
            $diffDays,
            $isWithin14Days,
            $reason,
            $savedFilePath
        ]);

        $requestId = (int)$this->db->lastInsertId();

        $warning = '';
        if ($requestType === 'MC' && $isWithin14Days === 0) {
            $warning = " Warning: This Medical Certificate was submitted {$diffDays} days after the absence date, which exceeds the university's 14-day limit. It will be flagged for review.";
        }

        return [
            'success' => true,
            'request_id' => $requestId,
            'message' => 'Request submitted successfully.' . $warning
        ];
    }

    public function reviewRequest(int $requestId, string $decision, ?string $remarks, int $reviewerUserId): array {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['success' => false, 'message' => 'Invalid review decision.'];
        }

        $stmt = $this->db->prepare("SELECT * FROM absence_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            return ['success' => false, 'message' => 'Request not found.'];
        }

        $updateStmt = $this->db->prepare("
            UPDATE absence_requests
            SET status = ?, review_remarks = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$decision, $remarks, $reviewerUserId, $requestId]);

        if ($decision === 'approved') {
            $newStatus = $req['request_type'];
            $attStmt = $this->db->prepare("
                INSERT INTO attendance_records (offering_id, student_id, week_number, status, marked_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)
            ");
            $attStmt->execute([
                $req['offering_id'],
                $req['student_id'],
                $req['week_number'],
                $newStatus,
                $reviewerUserId
            ]);
        }

        return ['success' => true, 'message' => "Request #{$requestId} marked as " . ucfirst($decision) . "."];
    }

    public function getRequests(?int $studentId = null, ?int $offeringId = null): array {
        $where = [];
        $params = [];

        if ($studentId !== null) {
            $where[] = "ar.student_id = ?";
            $params[] = $studentId;
        }
        if ($offeringId !== null) {
            $where[] = "ar.offering_id = ?";
            $params[] = $offeringId;
        }

        $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT ar.*, st.reg_number, st.index_number, st.name AS student_name, st.title,
                   co.display_name AS course_display, co.session_type, co.is_locked,
                   u.full_name AS reviewer_name
            FROM absence_requests ar
            JOIN students st ON ar.student_id = st.id
            JOIN course_offerings co ON ar.offering_id = co.id
            LEFT JOIN users u ON ar.reviewed_by = u.id
            {$whereSql}
            ORDER BY ar.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
