<?php

require_once __DIR__ . '/../config/app.php';

class EligibilityService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getOfferingAttendanceSheet(int $offeringId): array {

        $stmt = $this->db->prepare("
            SELECT co.*, c.course_code, c.course_name, c.has_practical,
                   b.batch_name, b.batch_code, b.year_of_study,
                   s.semester_number, s.academic_year,
                   u.full_name AS locked_by_name
            FROM course_offerings co
            JOIN courses c ON co.course_id = c.id
            JOIN academic_batches b ON c.batch_id = b.id
            JOIN semesters s ON c.semester_id = s.id
            LEFT JOIN users u ON co.locked_by = u.id
            WHERE co.id = ?
        ");
        $stmt->execute([$offeringId]);
        $offering = $stmt->fetch();

        if (!$offering) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT cl.*, u.full_name, u.email, u.is_external_lecturer
            FROM course_lecturers cl
            JOIN users u ON cl.lecturer_id = u.id
            WHERE cl.offering_id = ?
            ORDER BY cl.is_coordinating DESC, u.full_name ASC
        ");
        $stmt->execute([$offeringId]);
        $lecturers = $stmt->fetchAll();

        $stmt = $this->db->prepare("
            SELECT week_number, session_date, topic_covered
            FROM offering_weeks
            WHERE offering_id = ?
            ORDER BY week_number ASC
        ");
        $stmt->execute([$offeringId]);
        $weeksData = $stmt->fetchAll();
        $weeks = [];
        for ($w = 1; $w <= 15; $w++) {
            $weeks[$w] = [
                'week_number' => $w,
                'session_date' => null,
                'topic_covered' => null
            ];
        }
        foreach ($weeksData as $wd) {
            $weeks[(int)$wd['week_number']] = $wd;
        }

        $stmt = $this->db->prepare("
            SELECT ce.id AS enrollment_id, ce.practical_qualification_status, ce.hod_qualification_remarks,
                   st.id AS student_id, st.reg_number, st.index_number, st.name, st.gender, st.title,
                   st.is_mahapola, st.is_bursary, st.email
            FROM course_enrollments ce
            JOIN students st ON ce.student_id = st.id
            WHERE ce.offering_id = ?
            ORDER BY st.index_number ASC
        ");
        $stmt->execute([$offeringId]);
        $students = $stmt->fetchAll();

        $stmt = $this->db->prepare("
            SELECT student_id, week_number, status
            FROM attendance_records
            WHERE offering_id = ?
        ");
        $stmt->execute([$offeringId]);
        $attendanceRaw = $stmt->fetchAll();
        $attendanceMap = [];
        foreach ($attendanceRaw as $row) {
            $attendanceMap[$row['student_id']][$row['week_number']] = $row['status'];
        }

        $stmt = $this->db->prepare("
            SELECT student_id, request_type, COUNT(*) as count
            FROM absence_requests
            WHERE offering_id = ?
            GROUP BY student_id, request_type
        ");
        $stmt->execute([$offeringId]);
        $requestsRaw = $stmt->fetchAll();
        $requestCounts = [];
        foreach ($requestsRaw as $req) {
            $requestCounts[$req['student_id']][$req['request_type']] = (int)$req['count'];
        }

        $threshold = (float)$offering['eligibility_threshold'];
        $isPractical = ($offering['session_type'] === 'practical');

        $rows = [];
        $serialNo = 1;

        $maxRecordedWeek = 0;
        foreach ($attendanceRaw as $ar) {
            if ($ar['week_number'] > $maxRecordedWeek) {
                $maxRecordedWeek = (int)$ar['week_number'];
            }
        }

        $weeksEvaluated = max(1, min(15, $maxRecordedWeek > 0 ? $maxRecordedWeek : 15));

        foreach ($students as $st) {
            $sid = $st['student_id'];
            $stWeeks = [];
            $presentWeeks = 0;
            $absentWeeks = 0;
            $mcWeeks = 0;
            $exWeeks = 0;
            $srcWeeks = 0;

            for ($w = 1; $w <= 15; $w++) {
                $status = $attendanceMap[$sid][$w] ?? null;
                $stWeeks[$w] = $status;

                if ($status === '1') {
                    $presentWeeks++;
                } elseif ($status === 'SRC') {

                    $presentWeeks++;
                    $srcWeeks++;
                } elseif ($status === '0') {
                    $absentWeeks++;
                } elseif ($status === 'MC') {
                    $mcWeeks++;
                    $absentWeeks++;
                } elseif ($status === 'Ex') {
                    $exWeeks++;
                    $absentWeeks++;
                }
            }

            $pct = round(($presentWeeks / $weeksEvaluated) * 100, 1);
            $isEligibleByPercent = ($pct >= $threshold);

            if ($isPractical) {

                $hodStatus = $st['practical_qualification_status'];
                if ($hodStatus === 'qualified') {
                    $eligibilityStatus = 'Eligible (HoD Qualified)';
                } elseif ($hodStatus === 'disqualified') {
                    $eligibilityStatus = 'Not Eligible (HoD Disqualified)';
                } else {
                    $eligibilityStatus = $isEligibleByPercent ? 'Eligible (Pending HoD Review)' : 'Not Eligible (Pending HoD Review)';
                }
            } else {
                $eligibilityStatus = $isEligibleByPercent ? 'Eligible' : 'Not Eligible';
            }

            $mcSubmitted = $requestCounts[$sid]['MC'] ?? $mcWeeks;
            $exSubmitted = $requestCounts[$sid]['Ex'] ?? $exWeeks;

            $rows[] = [
                'no' => $serialNo++,
                'student_id' => $sid,
                'title' => $st['title'],
                'name' => $st['name'],
                'index_number' => $st['index_number'],
                'reg_number' => $st['reg_number'],
                'gender' => $st['gender'],
                'is_mahapola' => (bool)$st['is_mahapola'],
                'is_bursary' => (bool)$st['is_bursary'],
                'weeks' => $stWeeks,
                'present_weeks' => $presentWeeks,
                'absent_weeks' => $absentWeeks,
                'mc_submitted' => $mcSubmitted,
                'ex_submitted' => $exSubmitted,
                'src_weeks' => $srcWeeks,
                'attendance_percentage' => $pct,
                'is_eligible' => $isEligibleByPercent,
                'eligibility_status' => $eligibilityStatus,
                'practical_qualification_status' => $st['practical_qualification_status'],
                'hod_qualification_remarks' => $st['hod_qualification_remarks']
            ];
        }

        return [
            'offering' => $offering,
            'lecturers' => $lecturers,
            'weeks' => $weeks,
            'weeks_evaluated' => $weeksEvaluated,
            'students' => $rows
        ];
    }

    public function isLocked(int $offeringId): bool {
        $stmt = $this->db->prepare("SELECT is_locked FROM course_offerings WHERE id = ?");
        $stmt->execute([$offeringId]);
        $row = $stmt->fetch();
        return (bool)($row['is_locked'] ?? false);
    }

    public function lockOffering(int $offeringId, int $userId): bool {
        $stmt = $this->db->prepare("
            UPDATE course_offerings
            SET is_locked = 1, locked_at = NOW(), locked_by = ?
            WHERE id = ?
        ");
        return $stmt->execute([$userId, $offeringId]);
    }

    public function unlockOffering(int $offeringId): bool {
        $stmt = $this->db->prepare("
            UPDATE course_offerings
            SET is_locked = 0, locked_at = NULL, locked_by = NULL
            WHERE id = ?
        ");
        return $stmt->execute([$offeringId]);
    }

    public function updateEligibilityThreshold(int $offeringId, float $newThreshold): bool {
        $stmt = $this->db->prepare("
            UPDATE course_offerings
            SET eligibility_threshold = ?
            WHERE id = ?
        ");
        return $stmt->execute([$newThreshold, $offeringId]);
    }

    public function updatePracticalQualification(int $offeringId, int $studentId, string $status, ?string $remarks, int $hodUserId): bool {
        $stmt = $this->db->prepare("
            UPDATE course_enrollments
            SET practical_qualification_status = ?,
                hod_qualification_remarks = ?,
                hod_evaluated_at = NOW(),
                hod_evaluated_by = ?
            WHERE offering_id = ? AND student_id = ?
        ");
        return $stmt->execute([$status, $remarks, $hodUserId, $offeringId, $studentId]);
    }

    public function getDepartmentAttendanceAnalytics(int $batchId = 0, int $semesterId = 0): array {

        $where = [];
        $params = [];
        if ($batchId > 0) {
            $where[] = "c.batch_id = ?";
            $params[] = $batchId;
        }
        if ($semesterId > 0) {
            $where[] = "c.semester_id = ?";
            $params[] = $semesterId;
        }
        $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT st.id, st.reg_number, st.index_number, st.name, st.title, st.gender,
                   st.is_mahapola, st.is_bursary, b.batch_name,
                   COUNT(ar.id) as total_recorded,
                   SUM(CASE WHEN ar.status = '1' OR ar.status = 'SRC' THEN 1 ELSE 0 END) as total_attended,
                   SUM(CASE WHEN ar.status = '0' THEN 1 ELSE 0 END) as total_unexcused_absent,
                   SUM(CASE WHEN ar.status = 'MC' THEN 1 ELSE 0 END) as total_mc,
                   SUM(CASE WHEN ar.status = 'Ex' THEN 1 ELSE 0 END) as total_ex
            FROM students st
            JOIN academic_batches b ON st.batch_id = b.id
            JOIN course_enrollments ce ON st.id = ce.student_id
            JOIN course_offerings co ON ce.offering_id = co.id
            JOIN courses c ON co.course_id = c.id
            LEFT JOIN attendance_records ar ON (ar.offering_id = co.id AND ar.student_id = st.id)
            {$whereSql}
            GROUP BY st.id, b.batch_name
            ORDER BY st.index_number ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $all = $stmt->fetchAll();

        $studentsWithPct = [];
        foreach ($all as $item) {
            $total = (int)$item['total_recorded'];
            $attended = (int)$item['total_attended'];
            $pct = $total > 0 ? round(($attended / $total) * 100, 1) : 0.0;
            $item['overall_percentage'] = $pct;
            $item['is_eligible'] = ($pct >= 80.0);
            $studentsWithPct[] = $item;
        }

        $bestAttendance = $studentsWithPct;
        usort($bestAttendance, fn($a, $b) => $b['overall_percentage'] <=> $a['overall_percentage']);

        $poorAttendance = array_filter($studentsWithPct, fn($s) => $s['overall_percentage'] < 80.0 && $s['total_recorded'] > 0);
        usort($poorAttendance, fn($a, $b) => $a['overall_percentage'] <=> $b['overall_percentage']);

        return [
            'all' => $studentsWithPct,
            'best_attendance' => array_slice($bestAttendance, 0, 10),
            'poor_attendance' => array_values($poorAttendance)
        ];
    }

    public function getScholarshipVerification(int $batchId = 0): array {
        $where = ["(st.is_mahapola = 1 OR st.is_bursary = 1)"];
        $params = [];
        if ($batchId > 0) {
            $where[] = "st.batch_id = ?";
            $params[] = $batchId;
        }
        $whereSql = "WHERE " . implode(" AND ", $where);

        $sql = "
            SELECT st.id, st.reg_number, st.index_number, st.name, st.title, st.gender,
                   st.is_mahapola, st.is_bursary, b.batch_name,
                   COUNT(ar.id) as total_sessions,
                   SUM(CASE WHEN ar.status = '1' OR ar.status = 'SRC' THEN 1 ELSE 0 END) as total_present
            FROM students st
            JOIN academic_batches b ON st.batch_id = b.id
            LEFT JOIN course_enrollments ce ON st.id = ce.student_id
            LEFT JOIN attendance_records ar ON (ar.offering_id = ce.offering_id AND ar.student_id = st.id)
            {$whereSql}
            GROUP BY st.id, b.batch_name
            ORDER BY st.index_number ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        foreach ($records as &$rec) {
            $tot = (int)$rec['total_sessions'];
            $pres = (int)$rec['total_present'];
            $pct = $tot > 0 ? round(($pres / $tot) * 100, 1) : 0.0;
            $rec['attendance_percentage'] = $pct;
            $rec['is_80_satisfied'] = ($pct >= 80.0);
            $rec['scholarship_type'] = $rec['is_mahapola'] ? 'Mahapola' : 'Bursary';
        }
        unset($rec);

        return $records;
    }
}
