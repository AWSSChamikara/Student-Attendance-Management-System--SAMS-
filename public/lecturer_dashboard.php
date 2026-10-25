<?php

require_once __DIR__ . '/../config/app.php';

requireLecturer();

$user = getCurrentUser();
$userId = (int)$user['id'];
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT co.*, c.course_code, c.course_name, c.has_practical,
           b.batch_name, s.semester_number, s.academic_year,
           cl.is_coordinating, cl.can_mark_attendance,
           (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.offering_id = co.id) AS enrolled_students,
           (SELECT COUNT(DISTINCT week_number) FROM attendance_records ar WHERE ar.offering_id = co.id) AS weeks_conducted
    FROM course_lecturers cl
    JOIN course_offerings co ON cl.offering_id = co.id
    JOIN courses c ON co.course_id = c.id
    JOIN academic_batches b ON c.batch_id = b.id
    JOIN semesters s ON c.semester_id = s.id
    WHERE cl.lecturer_id = ?
    ORDER BY c.course_code ASC, co.session_type ASC
");
$stmt->execute([$userId]);
$assignedOfferings = $stmt->fetchAll();

$offeringIds = array_column($assignedOfferings, 'id');
$pendingCount = 0;
if (!empty($offeringIds)) {
    $inClause = implode(',', array_map('intval', $offeringIds));
    $pendingCount = (int)$db->query("
        SELECT COUNT(*) FROM absence_requests
        WHERE offering_id IN ($inClause) AND status = 'pending'
    ")->fetchColumn();
}

$pageTitle = 'Lecturer Dashboard';
$lecturerAvatar = 'assets/images/avatar_faculty.jpg';
if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])) {
    $lecturerAvatar = $user['avatar'];
}
require_once __DIR__ . '/layout/header.php';
?>

<div class="welcome-hero p-4 p-md-5 mb-4 position-relative">
    <div class="hero-bg-overlay" style="background-image: url('assets/images/university_hall.jpg');"></div>

    <div class="position-relative z-1">
        <div class="row align-items-center gy-4">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="<?= e($lecturerAvatar) ?>" alt="Lecturer Portrait" class="user-avatar-lg">
                    <div>
                        <div class="badge bg-warning text-dark px-3 py-1 mb-1 font-mono">
                            <?= !empty($user['is_external_lecturer']) ? 'Visiting Academic' : 'Academic Staff' ?>
                        </div>
                        <h2 class="fw-extrabold text-white mb-0">
                            <?= e($user['full_name']) ?>
                        </h2>
                        <div class="text-white-50 small mt-1 d-flex flex-wrap gap-3">
                            <span><i class="fa-solid fa-chalkboard-user me-1 text-warning"></i> <?= e(APP_DEPT) ?></span>
                            <span><i class="fa-solid fa-envelope me-1 text-success"></i> <?= e($user['email']) ?></span>
                            <span><i class="fa-solid fa-book-open me-1 text-info"></i> <?= count($assignedOfferings) ?> Assigned Courses</span>
                        </div>
                    </div>
                </div>
                <p class="text-white-50 small mb-0" style="max-width: 650px;">
                    <?= !empty($user['description']) ? e($user['description']) : 'Department of Computing Teaching Portal: Mark and monitor 15-week attendance registers for Lectures and Practical Labs, evaluate student absence requests (MC, Excused, SRC), and verify examination eligibility.' ?>
                </p>
            </div>

            <div class="col-lg-4 text-lg-end">
                <div class="d-inline-flex flex-wrap gap-2 justify-content-lg-end">
                    <?php if ($pendingCount > 0): ?>
                        <a href="absence_requests.php" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm">
                            <i class="fa-solid fa-bell me-1.5"></i> <?= $pendingCount ?> Pending MC Requests
                        </a>
                    <?php endif; ?>
                    <a href="student_search.php" class="btn btn-light btn-sm fw-bold shadow-sm">
                        <i class="fa-solid fa-magnifying-glass me-1.5 text-primary"></i> Search Student
                    </a>
                    <a href="profile.php" class="btn btn-outline-light btn-sm fw-bold shadow-sm">
                        <i class="fa-solid fa-user-pen me-1.5"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm p-3 mb-4" style="background-color: #f0fdf4; border-left: 4px solid #10b981 !important;">
    <div class="d-flex align-items-start gap-3">
        <div class="bg-success text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
            <i class="fa-solid fa-circle-info fs-5"></i>
        </div>
        <div class="small">
            <strong class="text-dark">Official Attendance Guidelines:</strong>
            <p class="mb-0 text-muted" style="line-height: 1.5;">
                Appointed lecturers with recording privileges (<span class="badge bg-success-subtle text-success border">Permitted</span>) can enter attendance for weeks 1 through 15.
                Lectures and practical sessions are evaluated separately.
                Once an end-of-semester eligibility report is obtained, the register is permanently locked against further edits.
            </p>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-book-open-reader text-primary me-2"></i> My Assigned Courses
            </h5>
            <small class="text-muted">Teaching assignments in the Department of Computing</small>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5">
            <?= count($assignedOfferings) ?> Course Offerings
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th style="width: 110px;">Course Code</th>
                    <th>Course Title &amp; Details</th>
                    <th style="width: 120px;">Session Type</th>
                    <th style="width: 130px;">Teaching Role</th>
                    <th style="width: 140px;" class="text-center">Marking Permission</th>
                    <th style="width: 110px;" class="text-center">Enrolled</th>
                    <th style="width: 120px;" class="text-center">Sheet Status</th>
                    <th style="width: 250px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignedOfferings)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-folder-open fa-2x mb-2 d-block opacity-50"></i>
                            No courses are currently assigned to your account. Please contact the Head of Department.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assignedOfferings as $off): ?>
                        <?php
                            $isPrac = ($off['session_type'] === 'practical');
                            $canMark = (bool)$off['can_mark_attendance'];
                            $isLocked = (bool)$off['is_locked'];
                        ?>
                        <tr>
                            <td class="fw-bold text-primary font-mono"><?= e($off['course_code']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($off['course_name']) ?></div>
                                <small class="text-muted"><?= e($off['batch_name']) ?> &bull; Semester <?= e($off['semester_number']) ?> (<?= e($off['academic_year']) ?>)</small>
                            </td>
                            <td>
                                <?php if ($isPrac): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-flask-vial me-1"></i> Practical</span>
                                <?php else: ?>
                                    <span class="badge bg-primary"><i class="fa-solid fa-person-chalkboard me-1"></i> Lecture</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($off['is_coordinating']): ?>
                                    <span class="badge bg-info text-dark"><i class="fa-solid fa-star me-1"></i> Coordinating</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">Co-Lecturer</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($canMark): ?>
                                    <span class="badge bg-success-subtle text-success border px-2.5 py-1">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Permitted
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2.5 py-1" title="Marked by coordinating lecturer">
                                        <i class="fa-solid fa-ban me-1"></i> Read-Only
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border px-2.5 py-1">
                                    <i class="fa-solid fa-users me-1 text-muted"></i> <?= $off['enrolled_students'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($isLocked): ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i> Locked</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-lock-open me-1"></i> Open</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="attendance_sheet.php?id=<?= $off['id'] ?>" class="btn <?= $canMark && !$isLocked ? 'btn-primary' : 'btn-outline-primary' ?>">
                                        <i class="fa-solid <?= $canMark && !$isLocked ? 'fa-pen-to-square' : 'fa-eye' ?> me-1"></i>
                                        <?= $canMark && !$isLocked ? 'Mark Sheet' : 'View Sheet' ?>
                                    </a>
                                    <a href="eligibility_report.php?id=<?= $off['id'] ?>" class="btn btn-outline-dark" title="View Eligibility">
                                        <i class="fa-solid fa-clipboard-check"></i>
                                    </a>
                                    <a href="export.php?action=excel&id=<?= $off['id'] ?>" class="btn btn-outline-success" title="Download Excel">
                                        <i class="fa-solid fa-file-excel"></i>
                                    </a>
                                    <a href="export.php?action=pdf&id=<?= $off['id'] ?>" class="btn btn-outline-danger" title="Download PDF">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
