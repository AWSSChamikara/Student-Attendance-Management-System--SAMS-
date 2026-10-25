<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/EligibilityService.php';

requireStudent();

$user = getCurrentUser();
$studentId = (int)($user['student_id'] ?? 0);
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT s.*, b.batch_name, b.batch_code, b.year_of_study
    FROM students s
    JOIN academic_batches b ON s.batch_id = b.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile record not found.");
}

$stmt = $db->prepare("
    SELECT co.id AS offering_id, co.session_type, co.offering_code, co.display_name, co.eligibility_threshold, co.is_locked,
           c.course_code, c.course_name, s.semester_number, s.academic_year,
           ce.practical_qualification_status, ce.hod_qualification_remarks
    FROM course_enrollments ce
    JOIN course_offerings co ON ce.offering_id = co.id
    JOIN courses c ON co.course_id = c.id
    JOIN semesters s ON c.semester_id = s.id
    WHERE ce.student_id = ?
    ORDER BY c.course_code ASC, co.session_type ASC
");
$stmt->execute([$studentId]);
$enrolledOfferings = $stmt->fetchAll();

$eligibilityService = new EligibilityService();
$courseReports = [];
$totalEligible = 0;
$totalCourses = count($enrolledOfferings);

foreach ($enrolledOfferings as $off) {
    $sheetData = $eligibilityService->getOfferingAttendanceSheet((int)$off['offering_id']);

    $myRow = null;
    if (!empty($sheetData['students'])) {
        foreach ($sheetData['students'] as $stRow) {
            if ($stRow['student_id'] === $studentId) {
                $myRow = $stRow;
                break;
            }
        }
    }

    if (!empty($myRow['is_eligible'])) {
        $totalEligible++;
    }

    $courseReports[] = [
        'offering' => $off,
        'weeks_data' => $sheetData['weeks'] ?? [],
        'student_data' => $myRow
    ];
}

$stmt = $db->prepare("
    SELECT ar.*, co.display_name, co.session_type
    FROM absence_requests ar
    JOIN course_offerings co ON ar.offering_id = co.id
    WHERE ar.student_id = ?
    ORDER BY ar.created_at DESC
");
$stmt->execute([$studentId]);
$myRequests = $stmt->fetchAll();

$pageTitle = 'Student Portal';
$studentAvatar = 'assets/images/avatar_student.jpg';
if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])) {
    $studentAvatar = $user['avatar'];
}
require_once __DIR__ . '/layout/header.php';
?>

<div class="welcome-hero p-4 p-md-5 mb-4 position-relative">
    <div class="hero-bg-overlay" style="background-image: url('assets/images/campus_hero.jpg');"></div>

    <div class="position-relative z-1">
        <div class="row align-items-center gy-4">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="<?= e($studentAvatar) ?>" alt="Student Photo" class="user-avatar-lg">
                    <div>
                        <div class="badge bg-warning text-dark px-3 py-1 mb-1 font-mono">
                            <?= e($student['batch_name']) ?>
                        </div>
                        <h2 class="fw-extrabold text-white mb-0">
                            <?= e($student['title']) ?>. <?= e($student['name']) ?>
                        </h2>
                        <div class="text-white-50 small mt-1 d-flex flex-wrap gap-3">
                            <span><i class="fa-solid fa-id-card me-1 text-warning"></i> Reg No: <strong class="text-white font-mono"><?= e($student['reg_number']) ?></strong></span>
                            <span><i class="fa-solid fa-hashtag me-1 text-info"></i> Index: <strong class="text-white font-mono"><?= e($student['index_number']) ?></strong></span>
                            <?php if (!empty($student['nic'])): ?>
                                <span><i class="fa-solid fa-address-card me-1 text-light"></i> NIC: <strong class="text-white font-mono"><?= e($student['nic']) ?></strong></span>
                            <?php endif; ?>
                            <span><i class="fa-solid fa-envelope me-1 text-success"></i> <?= e($student['email']) ?></span>
                        </div>
                    </div>
                </div>
                <p class="text-white-50 small mb-0" style="max-width: 650px;">
                    <?= !empty($user['description']) ? e($user['description']) : 'Department of Computing &bull; Faculty of Applied Sciences, Rajarata University of Sri Lanka. Monitor your weekly attendance, review examination eligibility thresholds (80%), and submit medical certificates seamlessly.' ?>
                </p>
            </div>

            <div class="col-lg-4 text-lg-end">
                <div class="d-inline-flex flex-column gap-2 text-start bg-white bg-opacity-10 backdrop-blur p-3 rounded-4 border border-white border-opacity-20 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <span class="small text-white-50">Exam Eligibility Health:</span>
                        <?php if ($totalEligible === $totalCourses && $totalCourses > 0): ?>
                            <span class="badge bg-success px-2.5 py-1">All <?= $totalCourses ?> Courses Eligible</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark px-2.5 py-1"><?= $totalEligible ?> of <?= $totalCourses ?> Eligible</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($student['is_mahapola']): ?>
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <span class="small text-white-50">Scholarship:</span>
                            <span class="badge bg-warning text-dark px-2.5 py-1">
                                <i class="fa-solid fa-award me-1"></i> Mahapola Recipient (80% Req)
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <a href="absence_requests.php" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm">
                            <i class="fa-solid fa-file-medical me-1.5"></i> Submit MC
                        </a>
                        <a href="profile.php" class="btn btn-outline-light btn-sm fw-bold shadow-sm">
                            <i class="fa-solid fa-user-pen me-1.5"></i> Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm p-3" style="background-color: #f0fdf4; border-left: 4px solid #10b981 !important;">
            <div class="d-flex align-items-start gap-3">
                <div class="bg-success text-white rounded-circle p-2.5 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa-solid fa-circle-check fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">80% Examination Eligibility Rule</h6>
                    <p class="small text-muted mb-0" style="line-height: 1.5;">
                        To sit for semester-end examinations, you must maintain at least <strong>80.00%</strong> attendance in both Lectures and Practical sessions. Sessions marked as <code>1</code> (Present) and <code>SRC</code> (Approved Committee Request) count toward this percentage.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm p-3" style="background-color: #fffbeb; border-left: 4px solid #f59e0b !important;">
            <div class="d-flex align-items-start gap-3">
                <div class="bg-warning text-dark rounded-circle p-2.5 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa-solid fa-clock-rotate-left fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">14-Day Medical Certificate Window</h6>
                    <p class="small text-muted mb-0" style="line-height: 1.5;">
                        If you miss a session due to illness, you must upload a government medical certificate within <strong>14 calendar days</strong> from the absence date. Sports or official excused absences (<code>Ex</code>) must be submitted before eligibility reports are released.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2.5 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="small fw-bold text-dark"><i class="fa-solid fa-tags text-primary me-1.5"></i> Attendance Key:</span>
        <div class="d-flex flex-wrap align-items-center gap-3 small">
            <span><span class="att-badge att-1 me-1">1</span> <strong>Present</strong> (Counted)</span>
            <span><span class="att-badge att-0 me-1">0</span> <strong>Absent</strong> (Unexcused)</span>
            <span><span class="att-badge att-mc me-1">MC</span> <strong>Medical</strong> (Within 14 Days)</span>
            <span><span class="att-badge att-ex me-1">Ex</span> <strong>Excused</strong> (Personal/Sports)</span>
            <span><span class="att-badge att-src me-1">SRC</span> <strong>Committee Approved</strong> (Counted)</span>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-book-bookmark text-primary me-2"></i> My Course Attendance &amp; Eligibility
            </h5>
            <small class="text-muted">Separate sheets for Theory Lectures and Practical Lab sessions</small>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5">
            <?= count($courseReports) ?> Active Offerings
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($courseReports)): ?>
            <div class="p-5 text-center text-muted">
                <i class="fa-solid fa-folder-open fs-1 text-muted opacity-50 mb-3 d-block"></i>
                <p class="mb-0">You are not currently enrolled in any course offerings for this semester.</p>
            </div>
        <?php else: ?>
            <div class="accordion accordion-flush" id="coursesAccordion">
                <?php foreach ($courseReports as $idx => $report): ?>
                    <?php
                        $off = $report['offering'];
                        $st = $report['student_data'];
                        $weeks = $report['weeks_data'];
                        $isPrac = ($off['session_type'] === 'practical');
                        $pct = $st['attendance_percentage'] ?? 0.0;
                        $isEligible = $st['is_eligible'] ?? false;
                    ?>
                    <div class="accordion-item border-bottom">
                        <h2 class="accordion-header" id="heading<?= $idx ?>">
                            <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?> py-3 px-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $idx ?>" aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>">
                                <div class="d-flex flex-wrap align-items-center justify-content-between w-100 me-3 gy-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-dark font-mono px-2 py-1"><?= e($off['course_code']) ?></span>
                                        <span class="fw-bold text-dark fs-6"><?= e($off['course_name']) ?></span>
                                        <?php if ($isPrac): ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-flask-vial me-1"></i> Practical Lab</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary"><i class="fa-solid fa-chalkboard-user me-1"></i> Theory Lecture</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="d-none d-md-block text-end" style="min-width: 140px;">
                                            <div class="small text-muted">Attendance: <strong><?= $pct ?>%</strong></div>
                                            <div class="progress-modern mt-1" style="width: 130px;">
                                                <div class="progress-bar <?= $isEligible ? 'bg-success' : 'bg-danger' ?>" style="width: <?= min(100, $pct) ?>%"></div>
                                            </div>
                                        </div>
                                        <?php if ($isEligible): ?>
                                            <span class="badge-eligible"><i class="fa-solid fa-circle-check"></i> Eligible (<?= $pct ?>%)</span>
                                        <?php else: ?>
                                            <span class="badge-ineligible"><i class="fa-solid fa-triangle-exclamation"></i> Below 80% (<?= $pct ?>%)</span>
                                        <?php endif; ?>

                                        <?php if ($isPrac): ?>
                                            <span class="badge bg-light text-dark border">
                                                HoD Lab Status: <strong><?= ucfirst($off['practical_qualification_status']) ?></strong>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?= $idx ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#coursesAccordion">
                            <div class="accordion-body bg-light p-4">

                                <div class="row g-2 mb-3 text-center">
                                    <div class="col-6 col-sm-2">
                                        <div class="p-2.5 bg-white rounded-3 border shadow-sm">
                                            <div class="small text-muted">Weeks Present</div>
                                            <div class="fw-bold text-success fs-5"><?= $st['present_weeks'] ?? 0 ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-2">
                                        <div class="p-2.5 bg-white rounded-3 border shadow-sm">
                                            <div class="small text-muted">Weeks Absent</div>
                                            <div class="fw-bold text-danger fs-5"><?= $st['absent_weeks'] ?? 0 ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-2">
                                        <div class="p-2.5 bg-white rounded-3 border shadow-sm">
                                            <div class="small text-muted">Medical (MC)</div>
                                            <div class="fw-bold text-warning fs-5"><?= $st['mc_submitted'] ?? 0 ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-2">
                                        <div class="p-2.5 bg-white rounded-3 border shadow-sm">
                                            <div class="small text-muted">Excused (Ex)</div>
                                            <div class="fw-bold text-info fs-5"><?= $st['ex_submitted'] ?? 0 ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-2">
                                        <div class="p-2.5 bg-white rounded-3 border shadow-sm">
                                            <div class="small text-muted">Attendance Rate</div>
                                            <div class="fw-bold <?= $isEligible ? 'text-success' : 'text-danger' ?> fs-5"><?= $pct ?>%</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-2">
                                        <div class="p-2.5 bg-white rounded-3 border shadow-sm">
                                            <div class="small text-muted">Exam Eligibility</div>
                                            <div class="fw-bold <?= $isEligible ? 'text-success' : 'text-danger' ?> fs-6 mt-1">
                                                <?= e($st['eligibility_status'] ?? 'Pending') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="fw-bold small text-muted text-uppercase mb-0">
                                        <i class="fa-solid fa-calendar-days text-primary me-1"></i> 15-Week Semester Timeline
                                    </h6>
                                    <span class="small text-muted">Lecturer in charge marked attendance</span>
                                </div>
                                <div class="table-attendance-responsive p-2 shadow-sm">
                                    <table class="table table-bordered table-sm text-center mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <?php for ($w = 1; $w <= 15; $w++): ?>
                                                    <th>
                                                        <div>Wk <?= $w ?></div>
                                                        <div class="text-white-50" style="font-size: 0.65rem; font-weight: normal;">
                                                            <?= !empty($weeks[$w]['session_date']) ? date('m/d', strtotime($weeks[$w]['session_date'])) : '-' ?>
                                                        </div>
                                                    </th>
                                                <?php endfor; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <?php for ($w = 1; $w <= 15; $w++): ?>
                                                    <?php
                                                        $val = $st['weeks'][$w] ?? '-';
                                                        $badgeClass = 'att-none';
                                                        if ($val === '1') $badgeClass = 'att-1';
                                                        elseif ($val === '0') $badgeClass = 'att-0';
                                                        elseif ($val === 'MC') $badgeClass = 'att-mc';
                                                        elseif ($val === 'Ex') $badgeClass = 'att-ex';
                                                        elseif ($val === 'SRC') $badgeClass = 'att-src';
                                                    ?>
                                                    <td class="p-1.5">
                                                        <span class="att-badge <?= $badgeClass ?>"><?= e($val) ?></span>
                                                    </td>
                                                <?php endfor; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-notes-medical text-primary me-2"></i> Absence Claims &amp; Medical Submissions
            </h5>
            <small class="text-muted">Track the approval progress of your medical certificates and excuse letters</small>
        </div>
        <a href="absence_requests.php" class="btn btn-primary btn-sm fw-bold">
            <i class="fa-solid fa-plus me-1"></i> New Absence Claim
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th>Claim Type</th>
                    <th>Course</th>
                    <th>Session Week</th>
                    <th>Absence Date</th>
                    <th>Submitted On</th>
                    <th>14-Day Limit</th>
                    <th>Review Status</th>
                    <th>Lecturer / HoD Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($myRequests)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-circle-check text-success fs-4 mb-2 d-block"></i>
                            You have not submitted any absence claims yet. All current attendance is logged directly by your course lecturers.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($myRequests as $req): ?>
                        <tr>
                            <td>
                                <?php if ($req['request_type'] === 'MC'): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-file-medical me-1"></i> Medical (MC)</span>
                                <?php elseif ($req['request_type'] === 'Ex'): ?>
                                    <span class="badge bg-info text-dark"><i class="fa-solid fa-envelope-open-text me-1"></i> Excused (Ex)</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-handshake me-1"></i> SRC Request</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold text-dark small"><?= e($req['display_name']) ?></td>
                            <td>Week <?= $req['week_number'] ?></td>
                            <td><?= e($req['absence_date']) ?></td>
                            <td><?= e($req['submission_date']) ?></td>
                            <td>
                                <?php if ($req['request_type'] === 'MC'): ?>
                                    <?php if ($req['is_within_14_days']): ?>
                                        <span class="badge bg-success-subtle text-success border"><i class="fa-solid fa-check me-1"></i> Valid (Within 14 Days)</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border"><i class="fa-solid fa-clock me-1"></i> Exceeded 14 Days</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'approved'): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Approved</span>
                                <?php elseif ($req['status'] === 'rejected'): ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i> Under Review</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e($req['review_remarks'] ?? 'Awaiting department review') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
