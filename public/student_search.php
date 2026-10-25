<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/EligibilityService.php';

requireLogin();

$user = getCurrentUser();
$userRole = $user['role'];
$studentId = $user['student_id'] ? (int)$user['student_id'] : null;

$db = Database::getConnection();
$eligibilityService = new EligibilityService();

$query = trim($_GET['query'] ?? '');
$studentResult = null;
$enrolledCoursesData = [];

if ($userRole === 'student') {

    $stmt = $db->prepare("
        SELECT s.*, b.batch_name, b.year_of_study
        FROM students s
        JOIN academic_batches b ON s.batch_id = b.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
    $studentResult = $stmt->fetch();
    $query = $studentResult ? $studentResult['index_number'] : '';
} elseif (!empty($query)) {

    $stmt = $db->prepare("
        SELECT s.*, b.batch_name, b.year_of_study
        FROM students s
        JOIN academic_batches b ON s.batch_id = b.id
        WHERE s.index_number LIKE ? OR s.reg_number LIKE ? OR s.name LIKE ?
        LIMIT 1
    ");
    $searchTerm = "%{$query}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $studentResult = $stmt->fetch();
}

if ($studentResult) {

    $stmt = $db->prepare("
        SELECT co.id AS offering_id, co.session_type, co.display_name, co.eligibility_threshold,
               c.course_code, c.course_name, s.semester_number, s.academic_year,
               ce.practical_qualification_status, ce.hod_qualification_remarks
        FROM course_enrollments ce
        JOIN course_offerings co ON ce.offering_id = co.id
        JOIN courses c ON co.course_id = c.id
        JOIN semesters s ON c.semester_id = s.id
        WHERE ce.student_id = ?
        ORDER BY c.course_code ASC, co.session_type ASC
    ");
    $stmt->execute([(int)$studentResult['id']]);
    $courses = $stmt->fetchAll();

    foreach ($courses as $c) {
        $sheet = $eligibilityService->getOfferingAttendanceSheet((int)$c['offering_id']);
        $myRow = null;
        if (!empty($sheet['students'])) {
            foreach ($sheet['students'] as $stRow) {
                if ($stRow['student_id'] === (int)$studentResult['id']) {
                    $myRow = $stRow;
                    break;
                }
            }
        }
        $enrolledCoursesData[] = [
            'course' => $c,
            'stats' => $myRow
        ];
    }
}

$pageTitle = 'Student Search';
require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-magnifying-glass text-primary me-2"></i> Student Attendance Record Search
        </h3>
        <p class="text-muted small mb-0">
            Specification: Search using registration number or index number. Students can only view their own details.
        </p>
    </div>
    <div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<?php if ($userRole !== 'student'): ?>
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body p-4">
            <form method="GET" action="student_search.php" class="row g-2 align-items-center justify-content-center">
                <div class="col-md-7 col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-id-card text-muted"></i></span>
                        <input type="text" name="query" class="form-control form-control-lg fs-6"
                               placeholder="Enter a registration or index number"
                               value="<?= e($query) ?>" autofocus required>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="fa-solid fa-search me-1"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info py-2 small mb-4">
        <i class="fa-solid fa-lock me-1"></i> As a student user, your view is restricted to your own university profile.
    </div>
<?php endif; ?>

<?php if ($studentResult): ?>

    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary-subtle text-primary rounded-circle p-3 me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-user-graduate fs-3"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1">
                                <?= e($studentResult['title']) ?>. <?= e($studentResult['name']) ?>
                            </h4>
                            <div class="text-muted small">
                                <span class="me-3"><i class="fa-solid fa-id-card me-1"></i> Index: <strong><?= e($studentResult['index_number']) ?></strong></span>
                                <span class="me-3"><i class="fa-solid fa-hashtag me-1"></i> Reg: <strong><?= e($studentResult['reg_number']) ?></strong></span>
                                <span class="me-3"><i class="fa-solid fa-venus-mars me-1"></i> Gender: <?= e($studentResult['gender']) ?></span>
                                <span><i class="fa-solid fa-graduation-cap me-1"></i> <?= e($studentResult['batch_name']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="d-inline-flex gap-2">
                        <?php if ($studentResult['is_mahapola']): ?>
                            <span class="badge bg-warning text-dark py-2 px-3 fs-6">
                                <i class="fa-solid fa-award me-1"></i> Mahapola
                            </span>
                        <?php endif; ?>
                        <?php if ($studentResult['is_bursary']): ?>
                            <span class="badge bg-info text-dark py-2 px-3 fs-6">
                                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Bursary
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-table-list text-primary me-2"></i> Attendance &amp; Examination Eligibility Breakdown
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th style="width: 120px;">Session Type</th>
                        <th style="width: 100px;" class="text-center">Present Wks</th>
                        <th style="width: 100px;" class="text-center">Absent Wks</th>
                        <th style="width: 80px;" class="text-center">MC</th>
                        <th style="width: 80px;" class="text-center">Ex</th>
                        <th style="width: 110px;" class="text-center">Attendance %</th>
                        <th style="width: 180px;" class="text-center">Exam Eligibility</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($enrolledCoursesData)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No course enrollments found for this student.</td></tr>
                    <?php else: ?>
                        <?php foreach ($enrolledCoursesData as $item): ?>
                            <?php
                                $c = $item['course'];
                                $st = $item['stats'];
                                $isPrac = ($c['session_type'] === 'practical');
                                $pct = $st['attendance_percentage'] ?? 0.0;
                                $isEligible = $st['is_eligible'] ?? false;
                            ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= e($c['course_code']) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($c['course_name']) ?></div>
                                    <small class="text-muted">Semester <?= e($c['semester_number']) ?> (<?= e($c['academic_year']) ?>)</small>
                                </td>
                                <td>
                                    <?php if ($isPrac): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-flask-vial me-1"></i> Practical</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary"><i class="fa-solid fa-chalkboard-user me-1"></i> Lecture</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold text-success"><?= $st['present_weeks'] ?? 0 ?></td>
                                <td class="text-center text-danger"><?= $st['absent_weeks'] ?? 0 ?></td>
                                <td class="text-center text-muted"><?= $st['mc_submitted'] ?? 0 ?></td>
                                <td class="text-center text-muted"><?= $st['ex_submitted'] ?? 0 ?></td>
                                <td class="text-center">
                                    <span class="fw-bold fs-6 <?= $isEligible ? 'text-success' : 'text-danger' ?>">
                                        <?= $pct ?>%
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($isEligible): ?>
                                        <span class="badge-eligible fs-6"><i class="fa-solid fa-circle-check me-1"></i> Eligible</span>
                                    <?php else: ?>
                                        <span class="badge-ineligible fs-6"><i class="fa-solid fa-ban me-1"></i> Not Eligible</span>
                                    <?php endif; ?>
                                    <?php if ($isPrac): ?>
                                        <div class="small text-muted mt-1">Lab: <?= ucfirst($c['practical_qualification_status']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif (!empty($query)): ?>
    <div class="alert alert-warning py-3 text-center">
        <i class="fa-solid fa-circle-exclamation me-1 fs-5"></i> No student found matching query: <strong><?= e($query) ?></strong>.
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
