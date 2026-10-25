<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/AbsenceService.php';

requireLogin();

$user = getCurrentUser();
$userId = (int)$user['id'];
$userRole = $user['role'];
$studentId = $user['student_id'] ? (int)$user['student_id'] : null;

$db = Database::getConnection();
$absenceService = new AbsenceService();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request']) && $userRole === 'student') {
    $offeringId = (int)$_POST['offering_id'];
    $weekNumber = (int)$_POST['week_number'];
    $requestType = $_POST['request_type'];
    $absenceDate = $_POST['absence_date'];
    $reason = trim($_POST['reason'] ?? '');
    $uploadedFile = $_FILES['document'] ?? null;

    $result = $absenceService->submitRequest(
        $studentId,
        $offeringId,
        $weekNumber,
        $requestType,
        $absenceDate,
        $reason,
        $uploadedFile
    );

    if ($result['success']) {
        setFlash('success', $result['message']);
    } else {
        setFlash('error', $result['message']);
    }
    header('Location: absence_requests.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_request']) && in_array($userRole, ['hod', 'lecturer'], true)) {
    $requestId = (int)$_POST['request_id'];
    $decision = $_POST['decision'];
    $remarks = trim($_POST['remarks'] ?? '');

    $result = $absenceService->reviewRequest($requestId, $decision, $remarks, $userId);
    if ($result['success']) {
        setFlash('success', $result['message']);
    } else {
        setFlash('error', $result['message']);
    }
    header('Location: absence_requests.php');
    exit;
}

if ($userRole === 'student') {
    $requests = $absenceService->getRequests($studentId);

    $stmt = $db->prepare("
        SELECT co.id, co.display_name, co.session_type, co.is_locked, c.course_code
        FROM course_enrollments ce
        JOIN course_offerings co ON ce.offering_id = co.id
        JOIN courses c ON co.course_id = c.id
        WHERE ce.student_id = ?
        ORDER BY c.course_code ASC
    ");
    $stmt->execute([$studentId]);
    $myOfferings = $stmt->fetchAll();
} else {

    $requests = $absenceService->getRequests();
}

$pageTitle = 'Absence Requests (MC / Excuse / SRC)';
require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-notes-medical text-primary me-2"></i> Absence Claims &amp; Evidence Review (MC / Ex / SRC)
        </h3>
        <p class="text-muted small mb-0">
            Medical Certificates (14-day limit), Excuses for university events, and Student Request Committee (SRC) attendance concessions.
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($userRole === 'student'): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#submitModal">
                <i class="fa-solid fa-plus me-1"></i> New Absence Submission
            </button>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 bg-warning-subtle text-dark">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-warning text-dark me-2">MC</span>
                    <strong class="small">Absence Due to Illness (14 Days)</strong>
                </div>
                <p class="small mb-0 text-muted">
                    Must submit a valid medical certificate within <strong>14 days</strong> from the absence date. Late submissions are flagged.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 bg-info-subtle text-dark">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-info text-dark me-2">Ex</span>
                    <strong class="small">Serious Personal Reason / Sports Event</strong>
                </div>
                <p class="small mb-0 text-muted">
                    Requires relevant excuse letter and evidence. Must be submitted <strong>before</strong> the eligibility report is released.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 bg-success-subtle text-dark">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-success text-white me-2">SRC</span>
                    <strong class="small">Student Request Committee (SRC)</strong>
                </div>
                <p class="small mb-0 text-muted">
                    Grants attendance in cases of valid reason or lecturer recording error. Letter + evidence required before report release.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-list-check text-primary me-2"></i> Absence Applications &amp; Review Queue
        </h5>
        <input type="text" class="form-control form-control-sm table-quick-search" placeholder="Filter requests..." data-target="#requestsTable" style="width: 200px;">
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="requestsTable">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th style="width: 70px;">Type</th>
                    <th>Student</th>
                    <th>Course &amp; Week</th>
                    <th>Absence Date</th>
                    <th>Submission Date</th>
                    <th class="text-center">14-Day Rule</th>
                    <th>Reason / Evidence</th>
                    <th class="text-center">Status</th>
                    <th style="width: 140px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No absence requests found.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>
                                <?php if ($req['request_type'] === 'MC'): ?>
                                    <span class="badge bg-warning text-dark fs-6">MC</span>
                                <?php elseif ($req['request_type'] === 'Ex'): ?>
                                    <span class="badge bg-info text-dark fs-6">Ex</span>
                                <?php else: ?>
                                    <span class="badge bg-success fs-6">SRC</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($req['title']) ?>. <?= e($req['student_name']) ?></div>
                                <small class="text-muted"><?= e($req['index_number']) ?> &bull; <?= e($req['reg_number']) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= e($req['course_display']) ?></div>
                                <span class="badge bg-light text-dark border">Week <?= $req['week_number'] ?></span>
                            </td>
                            <td class="small"><?= e($req['absence_date']) ?></td>
                            <td class="small">
                                <?= e($req['submission_date']) ?><br>
                                <small class="text-muted">(<?= $req['days_to_submission'] ?> days elapsed)</small>
                            </td>
                            <td class="text-center">
                                <?php if ($req['request_type'] === 'MC'): ?>
                                    <?php if ($req['is_within_14_days']): ?>
                                        <span class="badge bg-success-subtle text-success border">
                                            <i class="fa-solid fa-check me-1"></i> Valid (&le; 14d)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Late (&gt; 14d)
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small text-dark" style="max-width: 220px;"><?= e($req['reason']) ?></div>
                                <?php if ($req['document_path']): ?>
                                    <a href="../uploads/<?= $req['request_type'] === 'MC' ? 'medicals' : 'excuses' ?>/<?= urlencode($req['document_path']) ?>" target="_blank" class="small text-primary text-decoration-none">
                                        <i class="fa-solid fa-paperclip me-1"></i> View Attached Document
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($req['status'] === 'approved'): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Approved</span>
                                <?php elseif ($req['status'] === 'rejected'): ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (in_array($userRole, ['hod', 'lecturer'], true) && $req['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $req['id'] ?>">
                                        <i class="fa-solid fa-clipboard-check me-1"></i> Review
                                    </button>

                                    <div class="modal fade" id="reviewModal<?= $req['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start">
                                                <form method="POST" action="absence_requests.php">
                                                    <input type="hidden" name="review_request" value="1">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h6 class="modal-title fw-bold">
                                                            Review Absence Claim: <?= e($req['request_type']) ?> #<?= $req['id'] ?>
                                                        </h6>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="mb-3 small">
                                                            <strong>Student:</strong> <?= e($req['student_name']) ?> (<?= e($req['index_number']) ?>)<br>
                                                            <strong>Course:</strong> <?= e($req['course_display']) ?> - Week <?= $req['week_number'] ?><br>
                                                            <strong>Absence Date:</strong> <?= e($req['absence_date']) ?><br>
                                                            <strong>Days to Submission:</strong> <?= $req['days_to_submission'] ?> days
                                                            (<?= $req['is_within_14_days'] ? 'Within 14-day limit' : '<span class="text-danger fw-bold">Exceeded 14-day limit</span>' ?>)<br>
                                                            <strong>Stated Reason:</strong> <?= e($req['reason']) ?>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Review Decision</label>
                                                            <select name="decision" class="form-select" required>
                                                                <option value="approved">Approve (Grants Attendance / Approves Concession)</option>
                                                                <option value="rejected">Reject (Marked as Unexcused)</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Evaluation Remarks</label>
                                                            <textarea name="remarks" class="form-control" rows="2" placeholder="Enter review remarks"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Review Decision</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted"><?= e($req['review_remarks'] ?: 'Done') ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($userRole === 'student'): ?>
    <div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="absence_requests.php" enctype="multipart/form-data">
                    <input type="hidden" name="submit_request" value="1">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="fa-solid fa-file-arrow-up me-2"></i> Submit Absence Justification Request
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Course Offering</label>
                                <select name="offering_id" class="form-select" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($myOfferings as $mo): ?>
                                        <option value="<?= $mo['id'] ?>" <?= $mo['is_locked'] ? 'disabled' : '' ?>>
                                            <?= e($mo['display_name']) ?> <?= $mo['is_locked'] ? '(Locked)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Week Number</label>
                                <select name="week_number" class="form-select" required>
                                    <?php for ($w = 1; $w <= 15; $w++): ?>
                                        <option value="<?= $w ?>">Week <?= $w ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Request Type</label>
                                <select name="request_type" class="form-select" required>
                                    <option value="MC">Medical Certificate (MC) - Must be within 14 days</option>
                                    <option value="Ex">Excuse Letter (Ex) - Serious personal reason / Event / Sports</option>
                                    <option value="SRC">Student Request Committee (SRC) - Error or valid appeal</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Date of Absence</label>
                                <input type="date" name="absence_date" class="form-control" max="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Reason &amp; Detailed Explanation</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="Provide accurate reason and circumstance for absence..." required></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Attach Proof Document / Evidence (PDF or Image)</label>
                                <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="form-text small">Upload scanned medical certificate, official sports department recommendation, or SRC petition.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">Submit for Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
