<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/EligibilityService.php';

requireHod();

$db = Database::getConnection();
$eligibilityService = new EligibilityService();

$selectedBatchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 2;
$selectedSemesterId = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : 1;

$batches = $db->query("SELECT * FROM academic_batches ORDER BY year_of_study ASC")->fetchAll();
$semesters = $db->query("SELECT * FROM semesters ORDER BY semester_number ASC")->fetchAll();

$stmt = $db->prepare("
    SELECT co.*, c.course_code, c.course_name, c.has_practical,
           b.batch_name, s.semester_number, s.academic_year,
           (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.offering_id = co.id) AS enrolled_students,
           (SELECT COUNT(DISTINCT week_number) FROM attendance_records ar WHERE ar.offering_id = co.id) AS weeks_conducted
    FROM course_offerings co
    JOIN courses c ON co.course_id = c.id
    JOIN academic_batches b ON c.batch_id = b.id
    JOIN semesters s ON c.semester_id = s.id
    WHERE (b.id = ? OR ? = 0) AND (s.id = ? OR ? = 0)
    ORDER BY c.course_code ASC, co.session_type ASC
");
$stmt->execute([$selectedBatchId, $selectedBatchId, $selectedSemesterId, $selectedSemesterId]);
$offerings = $stmt->fetchAll();

$offeringLecturers = [];
if (!empty($offerings)) {
    $offeringIds = array_column($offerings, 'id');
    $inClause = implode(',', array_map('intval', $offeringIds));
    $lStmt = $db->query("
        SELECT cl.*, u.full_name, u.is_external_lecturer
        FROM course_lecturers cl
        JOIN users u ON cl.lecturer_id = u.id
        WHERE cl.offering_id IN ($inClause)
    ");
    while ($row = $lStmt->fetch()) {
        $offeringLecturers[$row['offering_id']][] = $row;
    }
}

$analytics = $eligibilityService->getDepartmentAttendanceAnalytics($selectedBatchId, $selectedSemesterId);
$bestAttendance = $analytics['best_attendance'];
$poorAttendance = $analytics['poor_attendance'];

$scholarshipStudents = $eligibilityService->getScholarshipVerification($selectedBatchId);
$mahapolaCount = 0;
$mahapolaSatisfied = 0;
foreach ($scholarshipStudents as $sch) {
    $mahapolaCount++;
    if ($sch['is_80_satisfied']) {
        $mahapolaSatisfied++;
    }
}

$pendingRequestsCount = (int)$db->query("SELECT COUNT(*) FROM absence_requests WHERE status = 'pending'")->fetchColumn();

$pendingPracticalCount = (int)$db->query("
    SELECT COUNT(*) FROM course_enrollments ce
    JOIN course_offerings co ON ce.offering_id = co.id
    WHERE co.session_type = 'practical' AND ce.practical_qualification_status = 'pending'
")->fetchColumn();

$pageTitle = 'Executive Portal';
$user = getCurrentUser();
$userRole = $user['role'] ?? 'hod';
$roleTitle = match ($userRole) {
    'dean' => 'Dean - Faculty of Applied Sciences (FAS)',
    'ar' => 'Assistant Registrar (AR)',
    default => 'Head of Department (HoD)'
};
$heroAvatar = 'assets/images/avatar_faculty.jpg';
if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])) {
    $heroAvatar = $user['avatar'];
} elseif ($userRole === 'dean') {
    $heroAvatar = 'assets/images/avatar_dean.jpg';
}
require_once __DIR__ . '/layout/header.php';
?>

<div class="welcome-hero p-4 p-md-5 mb-4 position-relative">
    <div class="hero-bg-overlay" style="background-image: url('assets/images/computer_lab.jpg');"></div>

    <div class="position-relative z-1">
        <div class="row align-items-center gy-4">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="<?= e($heroAvatar) ?>" alt="User Portrait" class="user-avatar-lg">
                    <div>
                        <div class="badge bg-warning text-dark px-3 py-1 mb-1 font-mono">
                            <?= e($roleTitle) ?>
                        </div>
                        <h2 class="fw-extrabold text-white mb-0">
                            <?= e($user['full_name'] ?? 'Prof. Kule') ?>
                        </h2>
                        <div class="text-white-50 small mt-1 d-flex flex-wrap gap-3">
                            <span><i class="fa-solid fa-building-columns me-1 text-warning"></i> <?= e(APP_DEPT) ?></span>
                            <span><i class="fa-solid fa-graduation-cap me-1 text-info"></i> <?= e(APP_FACULTY) ?></span>
                            <span><i class="fa-solid fa-envelope me-1 text-success"></i> <?= e($user['email'] ?? 'kule@as.rjt.ac.lk') ?></span>
                        </div>
                    </div>
                </div>
                <p class="text-white-50 small mb-0" style="max-width: 650px;">
                    <?= !empty($user['description']) ? e($user['description']) : 'Faculty of Applied Sciences &amp; Department of Computing Academic Administration: Manage 15-week lecture and lab attendance registers, review practical qualification requirements, certify Mahapola 80% scholarship eligibility, and issue locked examination eligibility lists.' ?>
                </p>
            </div>

            <div class="col-lg-4 text-lg-end">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="eis_upload.php" class="btn btn-success btn-sm fw-bold shadow-sm">
                        <i class="fa-solid fa-file-excel me-1.5"></i> Import EIS Roster
                    </a>
                    <a href="mahapola_verification.php" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm">
                        <i class="fa-solid fa-award me-1.5"></i> Mahapola (80%)
                    </a>
                    <a href="manage_courses.php" class="btn btn-primary btn-sm fw-bold shadow-sm">
                        <i class="fa-solid fa-plus me-1.5"></i> Add Course
                    </a>
                    <a href="profile.php" class="btn btn-outline-light btn-sm fw-bold shadow-sm">
                        <i class="fa-solid fa-user-pen me-1.5"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card-modern shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Academic Batches</div>
                    <h3 class="fw-extrabold mb-0 text-dark"><?= count($batches) ?> Batches</h3>
                    <small class="text-primary fw-semibold"><i class="fa-solid fa-calendar-days me-1"></i> 1st to 4th Year</small>
                </div>
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="fa-solid fa-users-rectangle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card-modern shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Top Attendance (>90%)</div>
                    <h3 class="fw-extrabold mb-0 text-success"><?= count($bestAttendance) ?> Students</h3>
                    <small class="text-success fw-semibold"><i class="fa-solid fa-star me-1"></i> High Consistency</small>
                </div>
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-award"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card-modern shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">At Risk (&lt;80%)</div>
                    <h3 class="fw-extrabold mb-0 text-danger"><?= count($poorAttendance) ?> Students</h3>
                    <small class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Ineligibility Risk</small>
                </div>
                <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card-modern shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Mahapola Meeting 80%</div>
                    <h3 class="fw-extrabold mb-0 text-dark"><?= $mahapolaSatisfied ?> / <?= $mahapolaCount ?></h3>
                    <small class="text-warning fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> <?= $mahapolaCount > 0 ? round(($mahapolaSatisfied/$mahapolaCount)*100, 1) : 0 ?>% Qualified</small>
                </div>
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="hod_dashboard.php" class="row g-2 align-items-center">
            <div class="col-auto">
                <span class="fw-bold small text-dark"><i class="fa-solid fa-sliders text-primary me-1.5"></i> Academic Filter:</span>
            </div>
            <div class="col-md-3">
                <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All Batches (1st to 4th Year)</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ($selectedBatchId == $b['id']) ? 'selected' : '' ?>>
                            <?= e($b['batch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All Semesters</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($selectedSemesterId == $s['id']) ? 'selected' : '' ?>>
                            Semester <?= e($s['semester_number']) ?> (<?= e($s['academic_year']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <a href="hod_dashboard.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <?php if ($pendingPracticalCount > 0): ?>
                    <a href="practical_qualification.php" class="btn btn-sm btn-outline-danger">
                        <i class="fa-solid fa-flask me-1"></i> <?= $pendingPracticalCount ?> Pending Practical Reviews
                    </a>
                <?php endif; ?>
                <?php if ($pendingRequestsCount > 0): ?>
                    <a href="absence_requests.php" class="btn btn-sm btn-outline-warning text-dark">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> <?= $pendingRequestsCount ?> Pending Claims
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-list-check text-primary me-2"></i> Course Offerings &amp; 15-Week Registers
            </h5>
            <small class="text-muted">Distinct sheets for Theory Lectures and Practical Lab components</small>
        </div>
        <div class="d-flex gap-2">
            <input type="text" class="form-control form-control-sm table-quick-search" placeholder="Quick search course..." data-target="#offeringsTable" style="width: 220px;">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="offeringsTable">
            <thead class="table-light">
                <tr class="small text-muted text-uppercase">
                    <th style="width: 100px;">Course Code</th>
                    <th>Course Name</th>
                    <th style="width: 120px;">Session Type</th>
                    <th>Appointed Lecturer(s)</th>
                    <th style="width: 110px;" class="text-center">Enrolled</th>
                    <th style="width: 100px;" class="text-center">Threshold</th>
                    <th style="width: 130px;" class="text-center">Register Status</th>
                    <th style="width: 260px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($offerings)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-folder-open fa-2x mb-2 d-block opacity-50"></i>
                            No course offerings found for the selected academic filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($offerings as $off): ?>
                        <?php
                            $isPrac = ($off['session_type'] === 'practical');
                            $lects = $offeringLecturers[$off['id']] ?? [];
                        ?>
                        <tr>
                            <td class="fw-bold text-primary font-mono"><?= e($off['course_code']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($off['course_name']) ?></div>
                                <small class="text-muted"><?= e($off['batch_name']) ?> &bull; Sem <?= e($off['semester_number']) ?></small>
                            </td>
                            <td>
                                <?php if ($isPrac): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-flask-vial me-1"></i> Practical</span>
                                <?php else: ?>
                                    <span class="badge bg-primary"><i class="fa-solid fa-person-chalkboard me-1"></i> Lecture</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($lects)): ?>
                                    <span class="text-muted small fst-italic">None appointed</span>
                                <?php else: ?>
                                    <?php foreach ($lects as $l): ?>
                                        <div class="small">
                                            <i class="fa-solid fa-user-tie text-secondary me-1"></i>
                                            <?= e($l['full_name']) ?>
                                            <?php if ($l['is_coordinating']): ?>
                                                <span class="badge bg-info text-dark" style="font-size: 0.65rem;">Coord.</span>
                                            <?php endif; ?>
                                            <?php if ($l['is_external_lecturer']): ?>
                                                <span class="badge bg-secondary" style="font-size: 0.65rem;">External</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border px-2.5 py-1">
                                    <i class="fa-solid fa-user-group me-1 text-muted"></i> <?= $off['enrolled_students'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-primary border fw-bold">
                                    <?= e($off['eligibility_threshold']) ?>%
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($off['is_locked']): ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i> Locked</span>
                                    <div style="font-size: 0.65rem;" class="text-muted mt-0.5">Eligibility Released</div>
                                <?php else: ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-lock-open me-1"></i> Open</span>
                                    <div style="font-size: 0.65rem;" class="text-muted mt-0.5">Wk 1-15 Modifiable</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="attendance_sheet.php?id=<?= $off['id'] ?>" class="btn btn-primary" title="Record or View Attendance">
                                        <i class="fa-solid fa-table-cells me-1"></i> Register
                                    </a>
                                    <a href="eligibility_report.php?id=<?= $off['id'] ?>" class="btn btn-outline-dark" title="Examination Eligibility Report">
                                        <i class="fa-solid fa-clipboard-check me-1"></i> Eligibility
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">Toggle Dropdown</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                                        <li><h6 class="dropdown-header">Export Reports</h6></li>
                                        <li>
                                            <a class="dropdown-item" href="export.php?action=excel&id=<?= $off['id'] ?>">
                                                <i class="fa-solid fa-file-excel text-success me-2"></i> Uneditable Excel (.xlsx)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="export.php?action=pdf&id=<?= $off['id'] ?>">
                                                <i class="fa-solid fa-file-pdf text-danger me-2"></i> Official PDF Report
                                            </a>
                                        </li>
                                        <?php if ($isPrac): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item" href="practical_qualification.php?id=<?= $off['id'] ?>">
                                                    <i class="fa-solid fa-flask-vial text-warning me-2"></i> HoD Practical Evaluation
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if ($off['is_locked']): ?>
                                            <li>
                                                <a class="dropdown-item text-warning" href="eligibility_report.php?id=<?= $off['id'] ?>&unlock=1" onclick="return confirm('Unlock this course offering to allow attendance modifications?');">
                                                    <i class="fa-solid fa-unlock me-2"></i> Unlock Register
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li>
                                                <a class="dropdown-item text-danger" href="eligibility_report.php?id=<?= $off['id'] ?>&lock=1" onclick="return confirm('Obtain eligibility report and permanently lock attendance entry for this course?');">
                                                    <i class="fa-solid fa-lock me-2"></i> Lock &amp; Release Report
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-4" id="analytics">

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-success">
                    <i class="fa-solid fa-award text-warning me-2"></i> Highest Attendance Consistency
                </h6>
                <span class="badge bg-success-subtle text-success">Exemplary Standing</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>Student</th>
                            <th>Index No</th>
                            <th>Batch</th>
                            <th class="text-center">Attended</th>
                            <th class="text-center">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bestAttendance)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bestAttendance as $st): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($st['title']) ?>. <?= e($st['name']) ?></div>
                                        <small class="text-muted font-mono"><?= e($st['reg_number']) ?></small>
                                    </td>
                                    <td class="font-mono small"><?= e($st['index_number']) ?></td>
                                    <td class="small text-muted"><?= e($st['batch_name']) ?></td>
                                    <td class="text-center small"><?= $st['total_attended'] ?> / <?= $st['total_recorded'] ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-success fs-6 fw-bold"><?= $st['overall_percentage'] ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-danger">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Attendance Under 80% (Intervention Required)
                </h6>
                <span class="badge bg-danger-subtle text-danger">At Risk of Exam Ineligibility</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>Student</th>
                            <th>Index No</th>
                            <th class="text-center">Attended</th>
                            <th class="text-center">Rate</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($poorAttendance)): ?>
                            <tr><td colspan="5" class="text-center text-success py-3"><i class="fa-solid fa-circle-check me-1"></i> Great! All students are currently meeting the 80% attendance requirement.</td></tr>
                        <?php else: ?>
                            <?php foreach ($poorAttendance as $st): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($st['title']) ?>. <?= e($st['name']) ?></div>
                                        <small class="text-muted font-mono"><?= e($st['reg_number']) ?></small>
                                    </td>
                                    <td class="font-mono small"><?= e($st['index_number']) ?></td>
                                    <td class="text-center small text-danger fw-bold"><?= $st['total_attended'] ?> / <?= $st['total_recorded'] ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-danger fs-6 fw-bold"><?= $st['overall_percentage'] ?>%</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="student_search.php?query=<?= urlencode($st['index_number']) ?>" class="btn btn-outline-danger btn-sm py-0" title="View Full Academic Profile">
                                            <i class="fa-solid fa-chart-line"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
