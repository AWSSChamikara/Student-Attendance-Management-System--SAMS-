<?php

require_once __DIR__ . '/../config/app.php';

requireHod();

$db = Database::getConnection();

$batches = $db->query("SELECT * FROM academic_batches ORDER BY year_of_study ASC")->fetchAll();
$semesters = $db->query("SELECT * FROM semesters ORDER BY semester_number ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
    $courseName = trim($_POST['course_name'] ?? '');
    $batchId = (int)$_POST['batch_id'];
    $semesterId = (int)$_POST['semester_id'];
    $hasPractical = isset($_POST['has_practical']) ? 1 : 0;

    if (empty($courseCode) || empty($courseName)) {
        setFlash('error', 'Please provide both course code and course name.');
    } else {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO courses (course_code, course_name, batch_id, semester_id, has_practical)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$courseCode, $courseName, $batchId, $semesterId, $hasPractical]);
            $courseId = (int)$db->lastInsertId();

            $offStmt = $db->prepare("
                INSERT INTO course_offerings (course_id, session_type, offering_code, display_name, eligibility_threshold)
                VALUES (?, 'lecture', ?, ?, 80.00)
            ");
            $offStmt->execute([$courseId, "{$courseCode}-LEC", "{$courseCode} - {$courseName} (Lecture)"]);
            $lecOfferingId = (int)$db->lastInsertId();

            $wStmt = $db->prepare("INSERT INTO offering_weeks (offering_id, week_number) VALUES (?, ?)");
            for ($w = 1; $w <= 15; $w++) {
                $wStmt->execute([$lecOfferingId, $w]);
            }

            $db->prepare("
                INSERT IGNORE INTO course_enrollments (offering_id, student_id, practical_qualification_status)
                SELECT ?, id, 'pending' FROM students WHERE batch_id = ?
            ")->execute([$lecOfferingId, $batchId]);

            if ($hasPractical) {
                $offStmt->execute([$courseId, "{$courseCode}-PRAC", "{$courseCode} - {$courseName} (Practical)"]);
                $pracOfferingId = (int)$db->lastInsertId();

                for ($w = 1; $w <= 15; $w++) {
                    $wStmt->execute([$pracOfferingId, $w]);
                }

                $db->prepare("
                    INSERT IGNORE INTO course_enrollments (offering_id, student_id, practical_qualification_status)
                    SELECT ?, id, 'pending' FROM students WHERE batch_id = ?
                ")->execute([$pracOfferingId, $batchId]);
            }

            $db->commit();
            setFlash('success', "Course {$courseCode} successfully created with distinct offering sheets.");
            header('Location: manage_courses.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', 'Error creating course: ' . $e->getMessage());
        }
    }
}

$courses = $db->query("
    SELECT c.*, b.batch_name, s.semester_number, s.academic_year,
           (SELECT COUNT(*) FROM course_offerings co WHERE co.course_id = c.id) as offerings_count
    FROM courses c
    JOIN academic_batches b ON c.batch_id = b.id
    JOIN semesters s ON c.semester_id = s.id
    ORDER BY b.year_of_study ASC, c.course_code ASC
")->fetchAll();

$pageTitle = 'Manage Courses & Practical Sessions';
require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-book-open text-primary me-2"></i> Course Catalog &amp; Practical Sessions Setup
        </h3>
        <p class="text-muted small mb-0">
            Specification: 2 semesters and 4 batches per year. Some courses include practical sessions, which generate separate attendance sheets and are treated as distinct course records.
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#newCourseModal">
            <i class="fa-solid fa-plus me-1"></i> Add New Course
        </button>
        <a href="hod_dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">Active Courses (<?= count($courses) ?>)</h5>
        <input type="text" class="form-control form-control-sm table-quick-search" placeholder="Search courses..." data-target="#coursesTable" style="width: 220px;">
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="coursesTable">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th style="width: 120px;">Course Code</th>
                    <th>Course Title</th>
                    <th>Academic Batch (Year)</th>
                    <th>Semester</th>
                    <th style="width: 150px;" class="text-center">Practical Session</th>
                    <th style="width: 130px;" class="text-center">Offerings</th>
                    <th style="width: 180px;" class="text-end">Manage</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No courses configured.</td></tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= e($c['course_code']) ?></td>
                            <td class="fw-semibold text-dark"><?= e($c['course_name']) ?></td>
                            <td><?= e($c['batch_name']) ?></td>
                            <td>Semester <?= e($c['semester_number']) ?> (<?= e($c['academic_year']) ?>)</td>
                            <td class="text-center">
                                <?php if ($c['has_practical']): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-flask-vial me-1"></i> Yes (Distinct Sheet)</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">No (Lecture Only)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary border">
                                    <?= $c['offerings_count'] ?> Sheet(s)
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="manage_lecturers.php?course_id=<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-user-plus me-1"></i> Appoint Lecturers
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="newCourseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_courses.php">
                <input type="hidden" name="create_course" value="1">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus me-2"></i> Add New Academic Course</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Course Code</label>
                        <input type="text" name="course_code" class="form-control" placeholder="Enter course code" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Course Title</label>
                        <input type="text" name="course_name" class="form-control" placeholder="Enter course name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Academic Batch</label>
                        <select name="batch_id" class="form-select" required>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= e($b['batch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Semester</label>
                        <select name="semester_id" class="form-select" required>
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?= $s['id'] ?>">Semester <?= e($s['semester_number']) ?> (<?= e($s['academic_year']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check form-switch p-3 bg-light rounded border mb-3">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="has_practical" id="hasPracticalCheck" value="1">
                        <label class="form-check-label fw-semibold" for="hasPracticalCheck">
                            Includes Practical Sessions
                        </label>
                        <div class="form-text small">Generates a distinct practical course offering and separate attendance sheet.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Create Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
