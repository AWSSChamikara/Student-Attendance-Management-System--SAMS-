<?php

require_once __DIR__ . '/../config/app.php';

requireHod();

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $threshold = (float)($_POST['default_eligibility_threshold'] ?? 80.00);
    $mcLimit = (int)($_POST['mc_submission_limit_days'] ?? 14);
    $deptName = trim($_POST['department_name'] ?? 'Department of Computer Science');
    $facultyName = trim($_POST['faculty_name'] ?? 'Faculty of Applied Sciences');
    $univName = trim($_POST['university_name'] ?? 'University of Computer Studies');

    updateSystemSetting('default_eligibility_threshold', (string)$threshold);
    updateSystemSetting('mc_submission_limit_days', (string)$mcLimit);
    updateSystemSetting('department_name', $deptName);
    updateSystemSetting('faculty_name', $facultyName);
    updateSystemSetting('university_name', $univName);

    if (isset($_POST['apply_to_all_courses'])) {
        $stmt = $db->prepare("UPDATE course_offerings SET eligibility_threshold = ? WHERE is_locked = 0");
        $stmt->execute([$threshold]);
    }

    setFlash('success', 'System settings and examination criteria successfully updated.');
    header('Location: manage_settings.php');
    exit;
}

$currentThreshold = getSystemSetting('default_eligibility_threshold', '80.00');
$currentMcLimit = getSystemSetting('mc_submission_limit_days', '14');
$currentDept = getSystemSetting('department_name', APP_DEPT);
$currentFaculty = getSystemSetting('faculty_name', APP_FACULTY);
$currentUniv = getSystemSetting('university_name', APP_UNIVERSITY);

$pageTitle = 'Department Policies & System Settings';
require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-gear text-primary me-2"></i> Department Settings &amp; Examination Criteria
        </h3>
        <p class="text-muted small mb-0">
            Configure examination eligibility percentage, medical certificate submission deadlines, and university letterhead details.
        </p>
    </div>
    <div>
        <a href="hod_dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-sliders text-primary me-2"></i> Examination &amp; Attendance Policy Controls
                </h5>
            </div>
            <div class="card-body p-4 bg-white">
                <form method="POST" action="manage_settings.php">
                    <input type="hidden" name="save_settings" value="1">

                    <div class="mb-4 p-3 bg-light rounded border">
                        <label class="form-label fw-bold text-dark mb-1">
                            Examination Eligibility Threshold Percentage
                        </label>
                        <div class="input-group" style="max-width: 250px;">
                            <input type="number" step="0.5" min="50" max="100" name="default_eligibility_threshold" class="form-control form-control-lg fw-bold text-primary" value="<?= e($currentThreshold) ?>" required>
                            <span class="input-group-text fw-bold">%</span>
                        </div>
                        <div class="form-text small text-muted mt-2">
                            Specification: "Eligibility percentage is 80%. It can be changed based on a decision made by the department. If someone haven’t 80% attendance, he will not eligible for exams."
                        </div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="apply_to_all_courses" id="applyAll" value="1" checked>
                            <label class="form-check-label small fw-semibold" for="applyAll">
                                Apply this threshold to all currently unlocked course offerings
                            </label>
                        </div>
                    </div>

                    <div class="mb-4 p-3 bg-light rounded border">
                        <label class="form-label fw-bold text-dark mb-1">
                            Medical Certificate (MC) Submission Deadline
                        </label>
                        <div class="input-group" style="max-width: 250px;">
                            <input type="number" step="1" min="1" max="60" name="mc_submission_limit_days" class="form-control form-control-lg fw-bold text-warning" value="<?= e($currentMcLimit) ?>" required>
                            <span class="input-group-text fw-bold">Days</span>
                        </div>
                        <div class="form-text small text-muted mt-2">
                            Specification: "Medical certificate must be submitted within 14 days from the date of absence."
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Department Name</label>
                        <input type="text" name="department_name" class="form-control" value="<?= e($currentDept) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Faculty Name</label>
                        <input type="text" name="faculty_name" class="form-control" value="<?= e($currentFaculty) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">University Name</label>
                        <input type="text" name="university_name" class="form-control" value="<?= e($currentUniv) ?>" required>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Policy Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
