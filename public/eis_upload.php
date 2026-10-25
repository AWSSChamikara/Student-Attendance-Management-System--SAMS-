<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/EisImporter.php';

requireHod();

$user = getCurrentUser();
$userId = (int)$user['id'];
$db = Database::getConnection();

$batches = $db->query("SELECT * FROM academic_batches WHERE is_active = 1 ORDER BY year_of_study ASC")->fetchAll();

$importResult = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['eis_file'])) {
    $batchId = (int)($_POST['batch_id'] ?? 0);
    $file = $_FILES['eis_file'];

    if ($batchId <= 0) {
        $error = 'Please select a target academic batch.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error (Code: ' . $file['error'] . ').';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            $error = 'Invalid file type. Please upload an Excel (.xlsx, .xls) or CSV file.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/eis_files/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $targetPath = $uploadDir . 'eis_' . date('Ymd_His') . '_' . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                try {
                    $importer = new EisImporter();
                    $importResult = $importer->importFile($targetPath, $batchId, $userId);
                    setFlash('success', "EIS Import successful: {$importResult['imported']} new students imported, {$importResult['updated']} existing students updated.");
                } catch (Exception $e) {
                    $error = 'Failed to process EIS file: ' . $e->getMessage();
                }
            } else {
                $error = 'Failed to save uploaded file.';
            }
        }
    }
}

$logs = $db->query("
    SELECT l.*, b.batch_name, u.full_name as uploader_name
    FROM eis_import_logs l
    JOIN academic_batches b ON l.batch_id = b.id
    JOIN users u ON l.uploaded_by = u.id
    ORDER BY l.created_at DESC
    LIMIT 10
")->fetchAll();

$pageTitle = 'EIS Excel Import';
require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h3 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-file-excel text-success me-2"></i> Examination Information System (EIS) Import
        </h3>
        <p class="text-muted small mb-0">
            Specification: Student details are downloaded from EIS as an Excel file and uploaded into this system. Only Registration Number, Index Number, Name, and Gender are extracted.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="download_sample_eis.php" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-download me-1"></i> Download Sample EIS Excel File
        </a>
        <a href="hod_dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation me-1"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="row g-4">

    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-success text-white py-3">
                <h5 class="fw-bold mb-0">
                    <i class="fa-solid fa-cloud-arrow-up me-2"></i> Upload EIS Student Roster
                </h5>
            </div>
            <div class="card-body p-4 bg-white">
                <div class="alert alert-light border small text-muted mb-3">
                    <strong class="text-dark"><i class="fa-solid fa-info-circle text-primary me-1"></i> Automatic Field Extraction:</strong>
                    <ul class="mb-0 ps-3 mt-1">
                        <li>EIS contains: Reg No, Index No, NIC, Phone, Address, Name, Gender.</li>
                        <li><strong>Extracted by SAMS:</strong> Registration No, Index No, Name, Gender.</li>
                        <li><strong>Auto Title:</strong> Automatically sets 'Mr' for Male, 'Mrs' for Female.</li>
                        <li>Enrolls students into the courses for the selected batch.</li>
                        <li>Students complete registration to set their own passwords before signing in.</li>
                    </ul>
                </div>

                <form method="POST" action="eis_upload.php" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target Academic Batch</label>
                        <select name="batch_id" class="form-select" required>
                            <option value="">-- Select Batch --</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?= $b['id'] ?>">
                                    <?= e($b['batch_name']) ?> (Year <?= $b['year_of_study'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">EIS Excel File (.xlsx, .xls, .csv)</label>
                        <input type="file" name="eis_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text small">Upload an EIS export containing registration number, index number, name, and gender columns.</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success py-2 fw-semibold">
                            <i class="fa-solid fa-file-import me-1"></i> Process &amp; Import Student Roster
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if ($importResult): ?>
            <div class="card shadow-sm border-0 mb-4 bg-light">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-success mb-3">
                        <i class="fa-solid fa-circle-check me-2"></i> Import Summary
                    </h5>
                    <div class="row g-2 text-center mb-3">
                        <div class="col-3">
                            <div class="p-2 bg-white rounded border">
                                <small class="text-muted">Total Rows</small>
                                <div class="fs-5 fw-bold text-dark"><?= $importResult['total_rows'] ?></div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 bg-white rounded border">
                                <small class="text-muted">New Students</small>
                                <div class="fs-5 fw-bold text-success"><?= $importResult['imported'] ?></div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 bg-white rounded border">
                                <small class="text-muted">Updated</small>
                                <div class="fs-5 fw-bold text-primary"><?= $importResult['updated'] ?></div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 bg-white rounded border">
                                <small class="text-muted">Skipped</small>
                                <div class="fs-5 fw-bold text-danger"><?= $importResult['skipped'] ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($importResult['errors'])): ?>
                        <div class="alert alert-warning small py-2">
                            <strong>Warnings / Errors encountered:</strong>
                            <ul class="mb-0 ps-3">
                                <?php foreach (array_slice($importResult['errors'], 0, 5) as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-clock-rotate-left text-muted me-2"></i> EIS Upload History Logs
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-muted">
                        <tr>
                            <th>File Name</th>
                            <th>Target Batch</th>
                            <th class="text-center">Imported</th>
                            <th>Uploaded By</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">No EIS imports logged yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><i class="fa-regular fa-file-excel text-success me-1"></i> <?= e($l['file_name']) ?></td>
                                    <td><?= e($l['batch_name']) ?></td>
                                    <td class="text-center fw-bold text-success"><?= $l['imported_count'] ?> / <?= $l['total_rows'] ?></td>
                                    <td><?= e($l['uploader_name']) ?></td>
                                    <td class="text-muted"><?= e(date('M d, Y H:i', strtotime($l['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
