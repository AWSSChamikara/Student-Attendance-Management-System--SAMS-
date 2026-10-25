<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/EligibilityService.php';

requireHod();

$db = Database::getConnection();
$eligibilityService = new EligibilityService();

$selectedBatchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$batches = $db->query("SELECT * FROM academic_batches ORDER BY year_of_study ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_scholarship'])) {
    $studentId = (int)$_POST['student_id'];
    $type = $_POST['type'];
    $status = (int)$_POST['status'];

    if ($type === 'mahapola') {
        $stmt = $db->prepare("UPDATE students SET is_mahapola = ? WHERE id = ?");
        $stmt->execute([$status, $studentId]);
    } elseif ($type === 'bursary') {
        $stmt = $db->prepare("UPDATE students SET is_bursary = ? WHERE id = ?");
        $stmt->execute([$status, $studentId]);
    }
    setFlash('success', 'Student scholarship recipient status updated.');
    header('Location: mahapola_verification.php?batch_id=' . $selectedBatchId);
    exit;
}

$recipients = $eligibilityService->getScholarshipVerification($selectedBatchId);

$totalRecipients = count($recipients);
$satisfiedCount = 0;
$unsatisfiedCount = 0;
foreach ($recipients as $r) {
    if ($r['is_80_satisfied']) {
        $satisfiedCount++;
    } else {
        $unsatisfiedCount++;
    }
}

$pageTitle = 'Mahapola & Bursary Verification';
require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-award text-warning me-2"></i> Mahapola &amp; Bursary Attendance Verification
        </h3>
        <p class="text-muted small mb-0">
            Specification: Regarding 'Mahapola' and other bursaries, verify whether relevant students have an 80% attendance rate. Not all students receive a bursary.
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Verification Report
        </button>
        <a href="hod_dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">Total Scholarship Recipients</div>
                <h4 class="fw-bold mb-0 text-dark"><?= $totalRecipients ?> Students</h4>
                <small class="text-muted">Mahapola &amp; Bursary Awardees</small>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card stat-success shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">Meeting 80% Attendance Requirement</div>
                <h4 class="fw-bold mb-0 text-success"><?= $satisfiedCount ?> Students</h4>
                <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> Eligible for Monthly Disbursement</small>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card stat-danger shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">Below 80% Requirement</div>
                <h4 class="fw-bold mb-0 text-danger"><?= $unsatisfiedCount ?> Students</h4>
                <small class="text-danger fw-semibold"><i class="fa-solid fa-circle-exclamation me-1"></i> Payment Subject to Suspension</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="mahapola_verification.php" class="row g-2 align-items-center">
            <div class="col-auto">
                <span class="fw-bold small text-muted"><i class="fa-solid fa-filter me-1"></i> Filter Batch:</span>
            </div>
            <div class="col-md-4">
                <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All Batches (1st to 4th Year)</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ($selectedBatchId == $b['id']) ? 'selected' : '' ?>>
                            <?= e($b['batch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto ms-auto">
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#awardModal">
                    <i class="fa-solid fa-user-tag me-1"></i> Manage Student Scholarship Status
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> Official Scholarship Compliance Roster
        </h5>
        <input type="text" class="form-control form-control-sm table-quick-search" placeholder="Search recipient..." data-target="#scholarshipTable" style="width: 200px;">
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="scholarshipTable">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 50px;">Title</th>
                    <th>Student Name</th>
                    <th style="width: 120px;">Index Number</th>
                    <th style="width: 130px;">Reg Number</th>
                    <th style="width: 120px;">Award Type</th>
                    <th style="width: 130px;" class="text-center">Attended Sessions</th>
                    <th style="width: 110px;" class="text-center">Attendance %</th>
                    <th style="width: 170px;" class="text-center">80% Compliance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recipients)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            No students flagged as Mahapola or Bursary recipients in this filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($recipients as $rec): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?= $no++ ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($rec['title']) ?></span></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($rec['name']) ?></div>
                                <small class="text-muted"><?= e($rec['batch_name']) ?></small>
                            </td>
                            <td class="font-monospace small"><?= e($rec['index_number']) ?></td>
                            <td class="font-monospace small text-muted"><?= e($rec['reg_number']) ?></td>
                            <td>
                                <?php if ($rec['is_mahapola']): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-award me-1"></i> Mahapola</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Bursary</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small">
                                <?= $rec['total_present'] ?> / <?= $rec['total_sessions'] ?>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold fs-6 <?= $rec['is_80_satisfied'] ? 'text-success' : 'text-danger' ?>">
                                    <?= $rec['attendance_percentage'] ?>%
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($rec['is_80_satisfied']): ?>
                                    <span class="badge-eligible fs-6">
                                        <i class="fa-solid fa-circle-check me-1"></i> 80% Satisfied
                                    </span>
                                <?php else: ?>
                                    <span class="badge-ineligible fs-6">
                                        <i class="fa-solid fa-circle-xmark me-1"></i> Below 80% (Suspended)
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="awardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="mahapola_verification.php?batch_id=<?= $selectedBatchId ?>">
                <input type="hidden" name="toggle_scholarship" value="1">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-tag me-2"></i> Update Student Scholarship</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <?php
                        $allStudents = $db->query("SELECT id, name, index_number, reg_number, is_mahapola, is_bursary FROM students ORDER BY index_number ASC")->fetchAll();
                    ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Student</label>
                        <select name="student_id" class="form-select" required>
                            <?php foreach ($allStudents as $st): ?>
                                <option value="<?= $st['id'] ?>">
                                    <?= e($st['index_number']) ?> - <?= e($st['name']) ?>
                                    (<?= $st['is_mahapola'] ? 'Mahapola' : ($st['is_bursary'] ? 'Bursary' : 'None') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Scholarship Program</label>
                        <select name="type" class="form-select" required>
                            <option value="mahapola">Mahapola Higher Education Scholarship</option>
                            <option value="bursary">University Bursary Scheme</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="1">Awarded / Active</option>
                            <option value="0">Not Awarded / Revoked</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
