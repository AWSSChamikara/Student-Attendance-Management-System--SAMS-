<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/EligibilityService.php';

requireLogin();

$user = getCurrentUser();
$userId = (int)$user['id'];
$userRole = $user['role'];
$db = Database::getConnection();
$eligibilityService = new EligibilityService();

$offeringId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

if (isset($_GET['lock']) && $userRole === 'hod') {
    $eligibilityService->lockOffering($offeringId, $userId);
    setFlash('success', 'Examination Eligibility Report released. Attendance entry is now permanently locked.');
    header('Location: eligibility_report.php?id=' . $offeringId);
    exit;
}

if (isset($_GET['unlock']) && $userRole === 'hod') {
    $eligibilityService->unlockOffering($offeringId);
    setFlash('info', 'Course offering unlocked. Attendance records can now be modified.');
    header('Location: eligibility_report.php?id=' . $offeringId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_threshold']) && $userRole === 'hod') {
    $newThreshold = (float)($_POST['threshold'] ?? 80.00);
    if ($newThreshold >= 50 && $newThreshold <= 100) {
        $eligibilityService->updateEligibilityThreshold($offeringId, $newThreshold);
        setFlash('success', "Department eligibility threshold updated to {$newThreshold}%.");
    } else {
        setFlash('error', 'Threshold percentage must be between 50% and 100%.');
    }
    header('Location: eligibility_report.php?id=' . $offeringId);
    exit;
}

$data = $eligibilityService->getOfferingAttendanceSheet($offeringId);
if (empty($data)) {
    setFlash('error', 'Course offering not found.');
    header('Location: dashboard.php');
    exit;
}

$offering = $data['offering'];
$lecturers = $data['lecturers'];
$students = $data['students'];
$isLocked = (bool)$offering['is_locked'];

$totalStudents = count($students);
$eligibleCount = 0;
$ineligibleCount = 0;
foreach ($students as $st) {
    if ($st['is_eligible']) {
        $eligibleCount++;
    } else {
        $ineligibleCount++;
    }
}

$eligiblePct = $totalStudents > 0 ? round(($eligibleCount / $totalStudents) * 100, 1) : 0;

$pageTitle = 'Examination Eligibility Report - ' . $offering['course_code'];
require_once __DIR__ . '/layout/header.php';
?>

<div class="card shadow-sm border-0 mb-4 bg-white">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="fs-4 fw-bold text-primary"><?= e($offering['course_code']) ?></span>
                    <span class="fs-4 fw-bold text-dark">&bull; <?= e($offering['course_name']) ?></span>
                    <?php if ($offering['session_type'] === 'practical'): ?>
                        <span class="badge bg-warning text-dark fs-6"><i class="fa-solid fa-flask-vial me-1"></i> Practical</span>
                    <?php else: ?>
                        <span class="badge bg-primary fs-6"><i class="fa-solid fa-person-chalkboard me-1"></i> Lecture</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small">
                    <span class="me-3"><i class="fa-solid fa-users me-1"></i> <?= e($offering['batch_name']) ?></span>
                    <span class="me-3"><i class="fa-solid fa-calendar me-1"></i> Semester <?= e($offering['semester_number']) ?> (<?= e($offering['academic_year']) ?>)</span>
                    <span><i class="fa-solid fa-user-tie me-1"></i> Lecturer in Charge: <strong>
                        <?= !empty($lecturers) ? implode(', ', array_map(fn($l) => e($l['full_name']), $lecturers)) : 'Not Assigned' ?>
                    </strong></span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="attendance_sheet.php?id=<?= $offeringId ?>" class="btn btn-outline-primary btn-sm shadow-sm">
                    <i class="fa-solid fa-table-cells me-1"></i> View Attendance Sheet
                </a>
                <a href="export.php?action=excel&id=<?= $offeringId ?>" class="btn btn-outline-success btn-sm shadow-sm">
                    <i class="fa-solid fa-file-excel me-1"></i> Export Uneditable Excel
                </a>
                <a href="export.php?action=pdf&id=<?= $offeringId ?>" class="btn btn-outline-danger btn-sm shadow-sm">
                    <i class="fa-solid fa-file-pdf me-1"></i> Export Official PDF
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <?php if ($isLocked): ?>
            <div class="locked-banner shadow-sm h-100">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-lock fs-2 me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">OFFICIAL EXAMINATION ELIGIBILITY REPORT RELEASED (LOCKED)</h6>
                        <div class="small">
                            Released after 14th week by <?= e($offering['locked_by_name'] ?? 'Head of Department') ?>
                            on <?= e(date('F j, Y, g:i a', strtotime($offering['locked_at']))) ?>.
                            Attendance entry and editing are permanently locked.
                        </div>
                    </div>
                </div>
                <?php if ($userRole === 'hod'): ?>
                    <a href="eligibility_report.php?id=<?= $offeringId ?>&unlock=1" class="btn btn-light btn-sm text-danger fw-bold ms-3" onclick="return confirm('Unlock attendance for this course offering?');">
                        <i class="fa-solid fa-unlock me-1"></i> Unlock (HoD)
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card border-warning shadow-sm h-100">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-lock-open text-warning fs-3 me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Pre-Eligibility Draft Phase (Open for Modifications)</h6>
                                <small class="text-muted">Eligibility report is officially released after the 14th week. Once obtained, records become read-only.</small>
                            </div>
                        </div>
                    </div>
                    <?php if ($userRole === 'hod'): ?>
                        <a href="eligibility_report.php?id=<?= $offeringId ?>&lock=1" class="btn btn-danger btn-sm fw-semibold shadow-sm" onclick="return confirm('Are you sure you want to release the final eligibility report and lock all attendance records?');">
                            <i class="fa-solid fa-lock me-1"></i> Release &amp; Lock Eligibility
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-bold text-muted text-uppercase">Eligibility Threshold</span>
                    <span class="badge bg-primary fs-6 fw-bold"><?= e($offering['eligibility_threshold']) ?>%</span>
                </div>
                <?php if ($userRole === 'hod'): ?>
                    <form method="POST" action="eligibility_report.php?id=<?= $offeringId ?>" class="input-group input-group-sm">
                        <input type="hidden" name="update_threshold" value="1">
                        <span class="input-group-text bg-light">Min %</span>
                        <input type="number" step="1" min="50" max="100" name="threshold" class="form-control" value="<?= e($offering['eligibility_threshold']) ?>" required>
                        <button type="submit" class="btn btn-outline-primary" <?= $isLocked ? 'disabled title="Locked"' : '' ?>>
                            Update
                        </button>
                    </form>
                    <div class="form-text small" style="font-size: 0.7rem;">Default 80%. HoD can change based on departmental board decision.</div>
                <?php else: ?>
                    <div class="small text-muted">Minimum 80% attendance required to sit for the end-of-semester examination.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4 text-center">
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 bg-white p-3">
            <div class="text-muted small fw-semibold">Total Students Enrolled</div>
            <div class="fs-3 fw-bold text-dark"><?= $totalStudents ?></div>
            <small class="text-muted">In this course offering</small>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 bg-white p-3 border-start border-4 border-success">
            <div class="text-muted small fw-semibold">Qualified for Examination</div>
            <div class="fs-3 fw-bold text-success"><?= $eligibleCount ?> <span class="fs-6 text-muted">(<?= $eligiblePct ?>%)</span></div>
            <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> Met <?= $offering['eligibility_threshold'] ?>% Attendance Criteria</small>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card shadow-sm border-0 bg-white p-3 border-start border-4 border-danger">
            <div class="text-muted small fw-semibold">Ineligible for Examination</div>
            <div class="fs-3 fw-bold text-danger"><?= $ineligibleCount ?></div>
            <small class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Below <?= $offering['eligibility_threshold'] ?>% Threshold</small>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-list-ol text-primary me-2"></i> Official Examination Eligibility Roster
        </h5>
        <div class="d-flex gap-2">
            <input type="text" class="form-control form-control-sm table-quick-search" placeholder="Filter student..." data-target="#eligibilityTable" style="width: 200px;">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="eligibilityTable">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 50px;">Title</th>
                    <th>Student Name</th>
                    <th style="width: 120px;">Index Number</th>
                    <th style="width: 140px;">Registration Number</th>
                    <th style="width: 90px;" class="text-center">Present</th>
                    <th style="width: 90px;" class="text-center">Absent</th>
                    <th style="width: 70px;" class="text-center">MC</th>
                    <th style="width: 70px;" class="text-center">Ex</th>
                    <th style="width: 110px;" class="text-center">Attendance %</th>
                    <th style="width: 180px;" class="text-center">Eligibility Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="11" class="text-center py-4 text-muted">No students enrolled.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $st): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?= $st['no'] ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($st['title']) ?></span></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($st['name']) ?></div>
                                <?php if ($st['is_mahapola']): ?>
                                    <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">Mahapola</span>
                                <?php endif; ?>
                                <?php if ($st['is_bursary']): ?>
                                    <span class="badge bg-info text-dark" style="font-size: 0.65rem;">Bursary</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace small"><?= e($st['index_number']) ?></td>
                            <td class="font-monospace small text-muted"><?= e($st['reg_number']) ?></td>
                            <td class="text-center fw-bold text-success"><?= $st['present_weeks'] ?></td>
                            <td class="text-center text-danger"><?= $st['absent_weeks'] ?></td>
                            <td class="text-center text-muted"><?= $st['mc_submitted'] ?></td>
                            <td class="text-center text-muted"><?= $st['ex_submitted'] ?></td>
                            <td class="text-center">
                                <span class="fw-bold fs-6 <?= $st['is_eligible'] ? 'text-success' : 'text-danger' ?>">
                                    <?= $st['attendance_percentage'] ?>%
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($st['is_eligible']): ?>
                                    <span class="badge-eligible fs-6">
                                        <i class="fa-solid fa-circle-check me-1"></i> <?= e($st['eligibility_status']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge-ineligible fs-6">
                                        <i class="fa-solid fa-ban me-1"></i> <?= e($st['eligibility_status']) ?>
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

<?php require_once __DIR__ . '/layout/footer.php'; ?>
