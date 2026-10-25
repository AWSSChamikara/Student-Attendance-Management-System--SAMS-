<?php
require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $roleInput = $_POST['staff_role'] ?? 'lecturer';
    $staffId = trim($_POST['staff_id'] ?? '');

    if (empty($fullName) || empty($email) || empty($password) || empty($staffId)) {
        $error = 'Please fill in all required fields.';
    } elseif (!isInstitutionalEmail($email)) {
        $error = 'You must use your official university email ending with @as.rjt.ac.lk.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'The passwords you entered do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $isExternal = ($roleInput === 'visiting') ? 1 : 0;
        $dbRole = 'lecturer';
        $defaultDesc = match ($roleInput) {
            'demonstrator' => 'Demonstrator, Department of Computing.',
            'visiting' => 'Visiting Academic / Lecturer, Department of Computing.',
            default => 'Lecturer, Department of Computing.'
        };
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $verificationCode = generateVerificationCode();
        $verificationToken = generateVerificationToken();
        $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $uStmt = $db->prepare("
            INSERT INTO users (email, password_hash, full_name, role, staff_id, is_external_lecturer, is_email_verified, verification_code, verification_token, verification_expires_at, description)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                password_hash = VALUES(password_hash),
                full_name = VALUES(full_name),
                role = VALUES(role),
                staff_id = VALUES(staff_id),
                is_external_lecturer = VALUES(is_external_lecturer),
                verification_code = VALUES(verification_code),
                verification_token = VALUES(verification_token),
                verification_expires_at = VALUES(verification_expires_at),
                description = VALUES(description)
        ");
        $uStmt->execute([$email, $passwordHash, $fullName, $dbRole, $staffId, $isExternal, $verificationCode, $verificationToken, $verificationExpires, $defaultDesc]);

        sendVerificationEmail($email, $verificationCode, $verificationToken, $fullName);
        setFlash('info', "Registration submitted successfully. A 6-digit verification code has been dispatched to {$email}. Please verify below to activate your account.");
        header('Location: verify_email.php?email=' . urlencode($email));
        exit;
    }
}

$pageTitle = 'Academic Staff Registration';
require_once __DIR__ . '/layout/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-8 col-lg-7">
        <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: var(--radius-xl);">
            <div class="position-relative text-white p-4 p-md-5" style="background: linear-gradient(135deg, #4a0000 0%, #800000 100%);">
                <div style="position: absolute; inset: 0; background-image: url('assets/images/university_hall.jpg'); background-size: cover; background-position: center; opacity: 0.22;"></div>
                <div class="position-relative z-1 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="badge bg-warning text-dark px-3 py-1 mb-2 font-mono" style="font-size: 0.75rem;">RUSL &bull; FAS</div>
                        <h3 class="fw-bold mb-1">Academic Staff Registration</h3>
                        <p class="text-white-50 small mb-0">Rajarata University of Sri Lanka &bull; Department of Computing</p>
                    </div>
                    <div class="text-end d-none d-sm-block">
                        <span class="badge bg-white bg-opacity-15 text-white border border-white border-opacity-25 px-3 py-2">
                            <i class="fa-solid fa-shield-halved me-1 text-warning"></i> Email Verification
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body p-4 p-md-5 bg-white">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2.5 px-3 small border-0 d-flex align-items-center mb-4" role="alert" style="background-color: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444 !important; border-radius: var(--radius-sm);">
                        <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Full Name (with initials)</label>
                            <input type="text" class="form-control" name="full_name" placeholder="Full Name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Academic Designation</label>
                            <select name="staff_role" class="form-select" required>
                                <option value="lecturer" <?= (($_POST['staff_role'] ?? '') === 'lecturer') ? 'selected' : '' ?>>Lecturer / Senior Lecturer</option>
                                <option value="demonstrator" <?= (($_POST['staff_role'] ?? '') === 'demonstrator') ? 'selected' : '' ?>>Demonstrator</option>
                                <option value="visiting" <?= (($_POST['staff_role'] ?? '') === 'visiting') ? 'selected' : '' ?>>Visiting Academic / Lecturer</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Staff ID Number</label>
                            <input type="text" name="staff_id" class="form-control font-mono" placeholder="Staff ID" value="<?= e($_POST['staff_id'] ?? '') ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Institutional Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" class="form-control border-start-0 font-mono" name="email"
                                       placeholder="Institutional Email Address" value="<?= e($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="form-text small text-muted mt-1">
                                Official university address ending with <code>@as.rjt.ac.lk</code>.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Create Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Password (minimum 6 characters)" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Confirm Password</label>
                            <input type="password" class="form-control" name="password_confirm" placeholder="Confirm Password" required>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary py-2.5 fw-bold shadow-sm">
                            <i class="fa-solid fa-user-check me-2"></i> Register Academic Staff Account
                        </button>
                    </div>

                    <div class="text-center small text-muted mt-3">
                        Already have an active account? <a href="index.php" class="text-decoration-none fw-bold text-primary">Sign In</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
