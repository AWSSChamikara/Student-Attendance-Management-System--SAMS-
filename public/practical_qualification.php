<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/EligibilityService.php';

requireHod();

$user = getCurrentUser();
$hodUserId = (int)$user['id'];
$db = Database::getConnection();
$eligibilityService = new EligibilityService();

$stmt = $db->query("
    SELECT co.id, co.offering_code, co.display_name, co.eligibility_threshold,
           c.course_code, c.course_name, b.batch_name, s.semester_number, s.academic_year
    FROM course_offerings co
    JOIN courses c ON co.course_id = c.id
    JOIN academic_batches b ON c.batch_id = b.id
    JOIN semesters s ON c.semester_id = s.id
    WHERE co.session_type = 'practical'
    ORDER BY c.course_code ASC
");
$practicalOfferings = $stmt->fetchAll();

$selectedOfferingId = isset($_GET['id']) ? (int)$_GET['id'] : (!empty($practicalOfferings) ? (int)$practicalOfferings[0]['id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qualification'])) {
    $studentId = (int)$_POST['student_id'];
    $status = $_POST['status'];
    $remarks = trim($_POST['remarks'] ?? '');

    if (in_array($status, ['qualified', 'disqualified', 'pending'], true)) {
        $eligibilityService->updatePracticalQualification($selectedOfferingId, $studentId, $status, $remarks, $hodUserId);
        setFlash('success', 'Student practical qualification updated successfully.');
    } else {
        setFlash('error', 'Invalid status selected.');
    }
    header('Location: practical_qualification.php?id=' . $selectedOfferingId);
    exit;
}

$sheetData = $selectedOfferingId > 0 ? $eligibilityService->getOfferingAttendanceSheet($selectedOfferingId) : [];
$students = $sheetData['students'] ?? [];
$offering = $sheetData['offering'] ?? null;

$pageTitle = 'Practical Session Qualification';
require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-flask-vial text-warning me-2"></i> Practical Session Examination Qualification
        </h3>
        <p class="text-muted small mb-0">
            Specification: The HOD determines whether or not a student qualifies for examinations, based on how the student has participated in practical sessions.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="hod_dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="practical_qualification.php" class="row g-2 align-items-center">
            <div class="col-auto">
                <span class="fw-bold small text-muted"><i class="fa-solid fa-flask me-1"></i> Select Practical Course:</span>
            </div>
            <div class="col-md-6">
                <select name="id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($practicalOfferings as $po): ?>
                        <option value="<?= $po['id'] ?>" <?= ($selectedOfferingId == $po['id']) ? 'selected' : '' ?>>
                            <?= e($po['course_code']) ?> - <?= e($po['course_name']) ?> (Practical) [<?= e($po['batch_name']) ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($offering): ?>
                <div class="col-auto ms-auto">
                    <a href="attendance_sheet.php?id=<?= $selectedOfferingId ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-table-cells me-1"></i> View Practical Attendance Sheet
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($offering): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <?= e($offering['course_code']) ?> &bull; <?= e($offering['course_name']) ?> (Practical Session)
                </h5>
                <small class="text-muted"><?= e($offering['batch_name']) ?> &bull; Semester <?= e($offering['semester_number']) ?></small>
            </div>
            <span class="badge bg-warning text-dark fs-6"><i class="fa-solid fa-user-shield me-1"></i> HoD Determination Required</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th style="width: 50px;">Title</th>
                        <th>Student Name</th>
                        <th style="width: 120px;">Index Number</th>
                        <th style="width: 130px;">Reg Number</th>
                        <th style="width: 100px;" class="text-center">Lab Attendance</th>
                        <th style="width: 150px;" class="text-center">HoD Decision</th>
                        <th>Remarks / Assessment Notes</th>
                        <th style="width: 160px;" class="text-end">Update Decision</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No students enrolled.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $st): ?>
                            <?php $qStatus = $st['practical_qualification_status'] ?? 'pending'; ?>
                            <tr>
                                <td class="text-muted fw-bold"><?= $st['no'] ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($st['title']) ?></span></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($st['name']) ?></div>
                                </td>
                                <td class="font-monospace small"><?= e($st['index_number']) ?></td>
                                <td class="font-monospace small text-muted"><?= e($st['reg_number']) ?></td>
                                <td class="text-center">
                                    <span class="fw-bold <?= $st['attendance_percentage'] >= 80 ? 'text-success' : 'text-danger' ?>">
                                        <?= $st['attendance_percentage'] ?>%
                                    </span>
                                    <div class="small text-muted">(<?= $st['present_weeks'] ?> / <?= $sheetData['weeks_evaluated'] ?>)</div>
                                </td>
                                <td class="text-center">
                                    <?php if ($qStatus === 'qualified'): ?>
                                        <span class="badge bg-success fs-6"><i class="fa-solid fa-circle-check me-1"></i> Qualified</span>
                                    <?php elseif ($qStatus === 'disqualified'): ?>
                                        <span class="badge bg-danger fs-6"><i class="fa-solid fa-circle-xmark me-1"></i> Disqualified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark fs-6"><i class="fa-solid fa-hourglass-half me-1"></i> Pending Review</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= e($st['hod_qualification_remarks'] ?: 'No evaluation remarks recorded yet.') ?></small>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#evalModal<?= $st['student_id'] ?>">
                                        <i class="fa-solid fa-gavel me-1"></i> Evaluate
                                    </button>

                                    <div class="modal fade" id="evalModal<?= $st['student_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start">
                                                <form method="POST" action="practical_qualification.php?id=<?= $selectedOfferingId ?>">
                                                    <input type="hidden" name="update_qualification" value="1">
                                                    <input type="hidden" name="student_id" value="<?= $st['student_id'] ?>">

                                                    <div class="modal-header bg-dark text-white">
                                                        <h6 class="modal-title fw-bold">
                                                            <i class="fa-solid fa-flask-vial me-2 text-warning"></i> Practical Qualification Evaluation
                                                        </h6>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="mb-3">
                                                            <strong>Student:</strong> <?= e($st['title']) ?>. <?= e($st['name']) ?> (<?= e($st['index_number']) ?>)<br>
                                                            <strong>Course:</strong> <?= e($offering['course_code']) ?> Practical Session<br>
                                                            <strong>Attendance Rate:</strong> <span class="fw-bold <?= $st['attendance_percentage'] >= 80 ? 'text-success' : 'text-danger' ?>"><?= $st['attendance_percentage'] ?>%</span>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">HoD Qualification Decision</label>
                                                            <select name="status" class="form-select" required>
                                                                <option value="qualified" <?= $qStatus === 'qualified' ? 'selected' : '' ?>>Qualified for Examination</option>
                                                                <option value="disqualified" <?= $qStatus === 'disqualified' ? 'selected' : '' ?>>Disqualified from Examination</option>
                                                                <option value="pending" <?= $qStatus === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Assessment Remarks / Reason</label>
                                                            <textarea name="remarks" class="form-control" rows="3" placeholder="Enter evaluation remarks regarding lab work, submissions, and participation..."><?= e($st['hod_qualification_remarks'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Qualification</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
