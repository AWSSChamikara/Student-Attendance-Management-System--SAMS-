<?php

require_once __DIR__ . '/../config/app.php';

requireHod();

$db = Database::getConnection();

$lecturers = $db->query("
    SELECT id, full_name, email, role, is_external_lecturer, staff_id
    FROM users
    WHERE role IN ('hod', 'lecturer')
    ORDER BY is_external_lecturer ASC, full_name ASC
")->fetchAll();

$offerings = $db->query("
    SELECT co.id, co.session_type, co.offering_code, co.display_name,
           c.course_code, c.course_name, b.batch_name
    FROM course_offerings co
    JOIN courses c ON co.course_id = c.id
    JOIN academic_batches b ON c.batch_id = b.id
    ORDER BY c.course_code ASC, co.session_type ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_lecturer'])) {
    $offeringId = (int)$_POST['offering_id'];
    $lecturerId = (int)$_POST['lecturer_id'];
    $isCoordinating = isset($_POST['is_coordinating']) ? 1 : 0;
    $canMark = isset($_POST['can_mark_attendance']) ? 1 : 0;

    $stmt = $db->prepare("
        INSERT INTO course_lecturers (offering_id, lecturer_id, is_coordinating, can_mark_attendance)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_coordinating = VALUES(is_coordinating),
            can_mark_attendance = VALUES(can_mark_attendance)
    ");
    $stmt->execute([$offeringId, $lecturerId, $isCoordinating, $canMark]);

    setFlash('success', 'Lecturer appointment and attendance recording permissions updated.');
    header('Location: manage_lecturers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_assignment'])) {
    $assignmentId = (int)$_POST['assignment_id'];
    $stmt = $db->prepare("DELETE FROM course_lecturers WHERE id = ?");
    $stmt->execute([$assignmentId]);

    setFlash('info', 'Lecturer assignment removed.');
    header('Location: manage_lecturers.php');
    exit;
}

$assignments = $db->query("
    SELECT cl.*, co.display_name, co.session_type, c.course_code, b.batch_name,
           u.full_name, u.email, u.is_external_lecturer, u.staff_id
    FROM course_lecturers cl
    JOIN course_offerings co ON cl.offering_id = co.id
    JOIN courses c ON co.course_id = c.id
    JOIN academic_batches b ON c.batch_id = b.id
    JOIN users u ON cl.lecturer_id = u.id
    ORDER BY c.course_code ASC, co.session_type ASC, cl.is_coordinating DESC
")->fetchAll();

$pageTitle = 'Appoint Lecturers & Recording Rights';
require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-chalkboard-user text-primary me-2"></i> Appoint Lecturers &amp; Attendance Permissions
        </h3>
        <p class="text-muted small mb-0">
            Specification: Only appointed persons can record attendance. For external lecturers, determine whether attendance is recorded by the coordinating or external lecturer.
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#assignModal">
            <i class="fa-solid fa-user-plus me-1"></i> Appoint Lecturer to Course
        </button>
        <a href="hod_dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            Active Teaching Appointments (<?= count($assignments) ?>)
        </h5>
        <input type="text" class="form-control form-control-sm table-quick-search" placeholder="Search appointment..." data-target="#assignmentsTable" style="width: 220px;">
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="assignmentsTable">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th style="width: 110px;">Course Code</th>
                    <th>Course Offering</th>
                    <th>Appointed Lecturer</th>
                    <th>Type</th>
                    <th style="width: 140px;" class="text-center">Coordinating</th>
                    <th style="width: 160px;" class="text-center">Can Mark Attendance</th>
                    <th style="width: 100px;" class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No lecturer appointments recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= e($a['course_code']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($a['display_name']) ?></div>
                                <small class="text-muted"><?= e($a['batch_name']) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($a['full_name']) ?></div>
                                <small class="text-muted"><?= e($a['email']) ?></small>
                            </td>
                            <td>
                                <?php if ($a['is_external_lecturer']): ?>
                                    <span class="badge bg-secondary">External / Visiting</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Internal Staff</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($a['is_coordinating']): ?>
                                    <span class="badge bg-info text-dark"><i class="fa-solid fa-star me-1"></i> Coordinating</span>
                                <?php else: ?>
                                    <span class="text-muted small">Co-Lecturer</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($a['can_mark_attendance']): ?>
                                    <span class="badge bg-success-subtle text-success border px-2 py-1">
                                        <i class="fa-solid fa-circle-check me-1"></i> Yes (Marks Sheet)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border px-2 py-1">
                                        <i class="fa-solid fa-circle-xmark me-1"></i> No (Read-Only)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="manage_lecturers.php" onsubmit="return confirm('Remove this lecturer appointment?');">
                                    <input type="hidden" name="remove_assignment" value="1">
                                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0" title="Remove Appointment">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_lecturers.php">
                <input type="hidden" name="assign_lecturer" value="1">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i> Appoint Lecturer to Course</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Course Offering</label>
                        <select name="offering_id" class="form-select" required>
                            <?php foreach ($offerings as $off): ?>
                                <option value="<?= $off['id'] ?>">
                                    <?= e($off['display_name']) ?> (<?= e($off['batch_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Lecturer</label>
                        <select name="lecturer_id" class="form-select" required>
                            <?php foreach ($lecturers as $l): ?>
                                <option value="<?= $l['id'] ?>">
                                    <?= e($l['full_name']) ?> (<?= e($l['email']) ?>)
                                    <?= $l['is_external_lecturer'] ? '[External/Visiting]' : '[Internal]' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check form-switch p-3 bg-light rounded border mb-3">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="is_coordinating" id="coordCheck" value="1">
                        <label class="form-check-label fw-semibold" for="coordCheck">
                            Designate as Coordinating Lecturer
                        </label>
                    </div>

                    <div class="form-check form-switch p-3 bg-light rounded border mb-3">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="can_mark_attendance" id="markCheck" value="1" checked>
                        <label class="form-check-label fw-semibold" for="markCheck">
                            Grant Attendance Recording Permission
                        </label>
                        <div class="form-text small">Specification: In instances with external lecturers, choose whether coordinating or external lecturer marks attendance.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
