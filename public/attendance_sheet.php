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

$data = $eligibilityService->getOfferingAttendanceSheet($offeringId);
if (empty($data)) {
    setFlash('error', 'Course offering not found.');
    header('Location: dashboard.php');
    exit;
}

$offering = $data['offering'];
$lecturers = $data['lecturers'];
$weeks = $data['weeks'];
$weeksEvaluated = $data['weeks_evaluated'];
$students = $data['students'];
$isLocked = (bool)$offering['is_locked'];

$canEdit = false;
if ($userRole === 'hod') {
    $canEdit = true;
} else {
    foreach ($lecturers as $l) {
        if ((int)$l['lecturer_id'] === $userId && (bool)$l['can_mark_attendance']) {
            $canEdit = true;
            break;
        }
    }
}

if ($isLocked) {
    $canEdit = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (!$canEdit) {
        setFlash('error', 'Permission denied or attendance is locked.');
        header('Location: attendance_sheet.php?id=' . $offeringId);
        exit;
    }

    $attendanceInputs = $_POST['att'] ?? [];
    $savedCount = 0;

    $stmt = $db->prepare("
        INSERT INTO attendance_records (offering_id, student_id, week_number, status, marked_by)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)
    ");

    $validStatuses = ['1', '0', 'MC', 'Ex', 'SRC'];

    foreach ($attendanceInputs as $sid => $weeksPost) {
        $sid = (int)$sid;
        foreach ($weeksPost as $wNum => $status) {
            $wNum = (int)$wNum;
            if ($wNum >= 1 && $wNum <= 15 && in_array($status, $validStatuses, true)) {
                $stmt->execute([$offeringId, $sid, $wNum, $status, $userId]);
                $savedCount++;
            }
        }
    }

    setFlash('success', "Attendance updated successfully ({$savedCount} cell entries recorded).");
    header('Location: attendance_sheet.php?id=' . $offeringId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_week_dates'])) {
    if ($userRole !== 'hod' && !$canEdit) {
        setFlash('error', 'Permission denied.');
        header('Location: attendance_sheet.php?id=' . $offeringId);
        exit;
    }

    $dates = $_POST['dates'] ?? [];
    $wStmt = $db->prepare("
        INSERT INTO offering_weeks (offering_id, week_number, session_date)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE session_date = VALUES(session_date)
    ");

    for ($w = 1; $w <= 15; $w++) {
        $d = !empty($dates[$w]) ? $dates[$w] : null;
        $wStmt->execute([$offeringId, $w, $d]);
    }

    setFlash('success', 'Week dates updated successfully.');
    header('Location: attendance_sheet.php?id=' . $offeringId);
    exit;
}

$pageTitle = 'Attendance Sheet - ' . $offering['course_code'];
require_once __DIR__ . '/layout/header.php';
?>

<div class="card shadow-sm border-0 mb-3 bg-white">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="fs-4 fw-bold text-primary"><?= e($offering['course_code']) ?></span>
                    <span class="fs-4 fw-bold text-dark">&bull; <?= e($offering['course_name']) ?></span>
                    <?php if ($offering['session_type'] === 'practical'): ?>
                        <span class="badge bg-warning text-dark fs-6"><i class="fa-solid fa-flask-vial me-1"></i> Practical Session</span>
                    <?php else: ?>
                        <span class="badge bg-primary fs-6"><i class="fa-solid fa-person-chalkboard me-1"></i> Lecture</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small">
                    <span class="me-3"><i class="fa-solid fa-users me-1"></i> <?= e($offering['batch_name']) ?></span>
                    <span class="me-3"><i class="fa-solid fa-calendar me-1"></i> Semester <?= e($offering['semester_number']) ?> (<?= e($offering['academic_year']) ?>)</span>
                    <span class="me-3"><i class="fa-solid fa-gauge-high me-1 text-danger"></i> Required Criteria: <strong><?= e($offering['eligibility_threshold']) ?>%</strong></span>
                    <span><i class="fa-solid fa-user-tie me-1"></i> Lecturer(s): <strong>
                        <?= !empty($lecturers) ? implode(', ', array_map(fn($l) => e($l['full_name']) . ($l['is_coordinating'] ? ' (Coord.)' : ''), $lecturers)) : 'Not Assigned' ?>
                    </strong></span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="export.php?action=excel&id=<?= $offeringId ?>" class="btn btn-outline-success btn-sm shadow-sm" title="Export as password-protected Excel file">
                    <i class="fa-solid fa-file-excel me-1"></i> Export Excel (.xlsx)
                </a>
                <a href="export.php?action=pdf&id=<?= $offeringId ?>" class="btn btn-outline-danger btn-sm shadow-sm" title="Export as printable PDF report">
                    <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
                </a>
                <a href="eligibility_report.php?id=<?= $offeringId ?>" class="btn btn-outline-dark btn-sm shadow-sm">
                    <i class="fa-solid fa-clipboard-check me-1"></i> Eligibility Report
                </a>
                <?php if ($canEdit || $userRole === 'hod'): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#weekDatesModal">
                        <i class="fa-solid fa-calendar-days me-1"></i> Set Week Dates
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($isLocked): ?>
    <div class="locked-banner shadow-sm">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-lock fs-3 me-3"></i>
            <div>
                <h6 class="fw-bold mb-0">ATTENDANCE SHEET LOCKED &bull; EXAMINATION ELIGIBILITY RELEASED</h6>
                <small>Once the eligibility report has been released after week 14, attendance records cannot be entered or modified.</small>
            </div>
        </div>
        <?php if ($userRole === 'hod'): ?>
            <a href="eligibility_report.php?id=<?= $offeringId ?>&unlock=1" class="btn btn-light btn-sm text-danger fw-bold" onclick="return confirm('Unlock attendance modification for this course?');">
                <i class="fa-solid fa-unlock me-1"></i> Unlock Sheet (HoD)
            </a>
        <?php endif; ?>
    </div>
<?php elseif (!$canEdit): ?>
    <div class="alert alert-secondary py-2 small d-flex align-items-center mb-3">
        <i class="fa-solid fa-eye text-primary me-2 fs-5"></i>
        <div>You are viewing this sheet in <strong>Read-Only mode</strong>. Only appointed lecturers with recording privileges can mark attendance.</div>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <span class="small fw-bold text-muted me-2">5 ATTENDANCE OPTIONS:</span>
            <span class="legend-item"><span class="legend-box val-1">1</span> Present (1)</span>
            <span class="legend-item"><span class="legend-box val-0">0</span> Absent (0)</span>
            <span class="legend-item"><span class="legend-box val-MC">MC</span> Medical (14 Days)</span>
            <span class="legend-item"><span class="legend-box val-Ex">Ex</span> Excuse Letter</span>
            <span class="legend-item"><span class="legend-box val-SRC">SRC</span> SRC Granted</span>
        </div>
        <?php if ($canEdit && !$isLocked): ?>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Quick Action:</span>
                <div class="dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-check-double me-1"></i> Bulk Mark All Present
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <?php for ($w = 1; $w <= 15; $w++): ?>
                            <li>
                                <button type="button" class="dropdown-item small" onclick="markAllForWeek(<?= $w ?>, '1')">
                                    Week <?= $w ?> (<?= $weeks[$w]['session_date'] ? date('m/d', strtotime($weeks[$w]['session_date'])) : 'No Date' ?>)
                                </button>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="attendance_sheet.php?id=<?= $offeringId ?>" id="attendanceForm">
    <input type="hidden" name="save_attendance" value="1">

    <div class="attendance-table-wrapper mb-3 shadow-sm">
        <table class="table attendance-table table-bordered align-middle"
               data-weeks-evaluated="<?= $weeksEvaluated ?>"
               data-threshold="<?= $offering['eligibility_threshold'] ?>"
               data-is-practical="<?= $offering['session_type'] === 'practical' ? '1' : '0' ?>"
               id="attendanceTable">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 32px;">No</th>
                    <th rowspan="2" style="width: 40px;">Title</th>
                    <th rowspan="2" style="min-width: 150px;">Student Name</th>
                    <th rowspan="2" style="min-width: 85px;">Index No</th>
                    <th rowspan="2" style="min-width: 95px;">Reg No</th>

                    <?php for ($w = 1; $w <= 15; $w++): ?>
                        <th class="week-header">
                            <div>W<?= $w ?></div>
                            <span class="date-subhead">
                                <?= $weeks[$w]['session_date'] ? date('m/d', strtotime($weeks[$w]['session_date'])) : '-' ?>
                            </span>
                        </th>
                    <?php endfor; ?>
                    <th rowspan="2" style="width: 55px;" title="Total Number of Weeks Present">Pres.</th>
                    <th rowspan="2" style="width: 55px;" title="Total Number of Weeks Absent">Abs.</th>
                    <th rowspan="2" style="width: 48px;" title="Number of Medical Certificates Submitted">MC</th>
                    <th rowspan="2" style="width: 48px;" title="Number of Excuse Letters Submitted">Ex</th>
                    <th rowspan="2" style="width: 65px;" title="Attendance Percentage">Att %</th>
                    <th rowspan="2" style="min-width: 130px;" title="Examination Eligibility Status">Eligibility Status</th>
                </tr>
                <tr>

                    <?php for ($w = 1; $w <= 15; $w++): ?>
                        <th style="padding: 0; height: 0; border: none; font-size: 0;"></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="26" class="text-center py-4 text-muted">
                            No students enrolled in this course offering.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $st): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?= $st['no'] ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($st['title']) ?></span></td>
                            <td class="text-start">
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

                            <?php for ($w = 1; $w <= 15; $w++): ?>
                                <?php $currentVal = $st['weeks'][$w] ?? '0'; ?>
                                <td class="p-1">
                                    <?php if ($canEdit && !$isLocked): ?>
                                        <select name="att[<?= $st['student_id'] ?>][<?= $w ?>]"
                                                class="form-select form-select-sm att-select week-col-<?= $w ?>">
                                            <option value="1" <?= $currentVal === '1' ? 'selected' : '' ?>>1</option>
                                            <option value="0" <?= $currentVal === '0' ? 'selected' : '' ?>>0</option>
                                            <option value="MC" <?= $currentVal === 'MC' ? 'selected' : '' ?>>MC</option>
                                            <option value="Ex" <?= $currentVal === 'Ex' ? 'selected' : '' ?>>Ex</option>
                                            <option value="SRC" <?= $currentVal === 'SRC' ? 'selected' : '' ?>>SRC</option>
                                        </select>
                                    <?php else: ?>
                                        <span class="att-select d-inline-block val-<?= e($currentVal) ?> py-1" style="cursor: default;">
                                            <?= e($currentVal) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>

                            <td class="cell-present fw-bold text-primary"><?= $st['present_weeks'] ?></td>
                            <td class="cell-absent text-danger"><?= $st['absent_weeks'] ?></td>
                            <td class="text-muted"><?= $st['mc_submitted'] ?></td>
                            <td class="text-muted"><?= $st['ex_submitted'] ?></td>
                            <td class="cell-pct fw-bold fs-6 <?= $st['is_eligible'] ? 'text-success' : 'text-danger' ?>">
                                <?= $st['attendance_percentage'] ?>%
                            </td>
                            <td class="cell-eligibility">
                                <?php if ($st['is_eligible']): ?>
                                    <span class="badge-eligible"><?= e($st['eligibility_status']) ?></span>
                                <?php else: ?>
                                    <span class="badge-ineligible"><?= e($st['eligibility_status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canEdit && !$isLocked): ?>
        <div class="card shadow-sm border-0 bg-white sticky-bottom py-2 px-3 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    <i class="fa-solid fa-circle-info text-primary me-1"></i> Changes made above will be permanently saved to the database.
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Attendance Sheet
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</form>

<div class="modal fade" id="weekDatesModal" tabindex="-1" aria-labelledby="weekDatesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="attendance_sheet.php?id=<?= $offeringId ?>">
                <input type="hidden" name="save_week_dates" value="1">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="weekDatesModalLabel">
                        <i class="fa-solid fa-calendar-days me-2"></i> Configure Week Dates (Weeks 1 through 15)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Set the session calendar date indicated above each week column on the attendance sheet.
                    </p>
                    <div class="row g-2">
                        <?php for ($w = 1; $w <= 15; $w++): ?>
                            <div class="col-md-4 col-sm-6">
                                <label class="form-label small fw-semibold text-muted mb-1">Week <?= $w ?></label>
                                <input type="date" class="form-control form-control-sm" name="dates[<?= $w ?>]"
                                       value="<?= e($weeks[$w]['session_date'] ?? '') ?>">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Week Dates</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
