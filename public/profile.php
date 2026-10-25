<?php

require_once __DIR__ . '/../config/app.php';

requireLogin();

$user = getCurrentUser();
$userId = (int)$user['id'];
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT u.*, s.reg_number, s.index_number, s.nic, s.title, s.gender, s.batch_id, s.is_mahapola, s.is_bursary,
           b.batch_name
    FROM users u
    LEFT JOIN students s ON u.student_id = s.id
    LEFT JOIN academic_batches b ON s.batch_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData) {
    setFlash('error', 'User account not found.');
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $activeTab = 'profile';
    $newName = trim($_POST['full_name'] ?? '');
    $newDescription = trim($_POST['description'] ?? '');

    if (empty($newName)) {
        $error = 'Full Name cannot be empty.';
    } else {
        $avatarPath = $userData['avatar'];

        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar_file'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);

            if (!in_array($mimeType, $allowedTypes, true)) {
                $error = 'Invalid image format. Please upload a JPG, PNG, or WebP picture.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Profile picture exceeds the maximum allowed size of 5MB.';
            } else {
                $uploadDir = __DIR__ . '/uploads/avatars';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $ext = match ($mimeType) {
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'jpg'
                };

                $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . '/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $avatarPath = 'uploads/avatars/' . $filename;
                } else {
                    $error = 'Failed to save the uploaded image. Please try again.';
                }
            }
        }

        if (empty($error)) {

            $upStmt = $db->prepare("
                UPDATE users
                SET full_name = ?, description = ?, avatar = ?
                WHERE id = ?
            ");
            $upStmt->execute([$newName, $newDescription, $avatarPath, $userId]);

            if (!empty($userData['student_id'])) {
                $stUp = $db->prepare("UPDATE students SET name = ? WHERE id = ?");
                $stUp->execute([$newName, $userData['student_id']]);
            }

            $_SESSION['user']['full_name'] = $newName;
            $_SESSION['user']['avatar'] = $avatarPath;
            $_SESSION['user']['description'] = $newDescription;

            setFlash('success', 'Your profile details and picture have been updated successfully!');
            header('Location: profile.php?tab=profile');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_password_otp'])) {
    $activeTab = 'password';
    $otp = generateVerificationCode();
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $upStmt = $db->prepare("
        UPDATE users
        SET password_reset_code = ?, password_reset_expires_at = ?
        WHERE id = ?
    ");
    $upStmt->execute([$otp, $expires, $userId]);

    sendPasswordResetEmail($userData['email'], $otp, $userData['full_name']);

    setFlash('info', "A 6-digit verification code has been dispatched to your institutional email ({$userData['email']}). Please check your inbox to proceed.");
    header('Location: profile.php?tab=password&code_sent=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $activeTab = 'password';
    $code = trim($_POST['verification_code'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($code) || empty($newPass) || empty($confirmPass)) {
        $error = 'Please fill in the 6-digit verification code and new password fields.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'The new passwords do not match. Please re-enter.';
    } elseif (strlen($newPass) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {

        if (empty($userData['password_reset_code']) || $userData['password_reset_code'] !== $code) {
            $error = 'Invalid verification code. Please check your email or request a fresh code.';
        } elseif (!empty($userData['password_reset_expires_at']) && strtotime($userData['password_reset_expires_at']) < time()) {
            $error = 'The verification code has expired. Please request a new code.';
        } else {

            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $upStmt = $db->prepare("
                UPDATE users
                SET password_hash = ?, password_reset_code = NULL, password_reset_expires_at = NULL
                WHERE id = ?
            ");
            $upStmt->execute([$newHash, $userId]);

            setFlash('success', 'Your password has been changed successfully! Your account is secure.');
            header('Location: profile.php?tab=password');
            exit;
        }
    }
}

$currentAvatar = 'assets/images/avatar_student.jpg';
if (!empty($userData['avatar']) && file_exists(__DIR__ . '/' . $userData['avatar'])) {
    $currentAvatar = $userData['avatar'];
} elseif ($userData['role'] === 'dean') {
    $currentAvatar = 'assets/images/avatar_dean.jpg';
} elseif ($userData['role'] === 'hod' || $userData['role'] === 'ar' || $userData['role'] === 'lecturer') {
    $currentAvatar = 'assets/images/avatar_faculty.jpg';
}

$pageTitle = 'My Profile & Account Security';
require_once __DIR__ . '/layout/header.php';
?>

<div class="row g-4 py-3">

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm text-center p-4">
            <div class="position-relative d-inline-block mx-auto mb-3">
                <img src="<?= e($currentAvatar) ?>" alt="Profile Picture" class="rounded-circle shadow-md border border-3 border-white" style="width: 120px; height: 120px; object-fit: cover;">
                <span class="position-absolute bottom-0 end-0 bg-success border border-white border-2 rounded-circle p-2" title="Online &amp; Active"></span>
            </div>

            <h4 class="fw-bold text-dark mb-1"><?= e($userData['full_name']) ?></h4>
            <div class="text-muted small mb-3 font-mono"><?= e($userData['email']) ?></div>

            <div class="mb-3">
                <?php
                    $roleBadgeClass = 'bg-primary';
                    $roleLabel = ucfirst($userData['role']);
                    if ($userData['role'] === 'dean') {
                        $roleBadgeClass = 'bg-danger';
                        $roleLabel = 'Dean (FAS)';
                    } elseif ($userData['role'] === 'hod') {
                        $roleBadgeClass = 'bg-warning text-dark';
                        $roleLabel = 'Head of Department (HoD)';
                    } elseif ($userData['role'] === 'ar') {
                        $roleBadgeClass = 'bg-info text-dark';
                        $roleLabel = 'Assistant Registrar (AR)';
                    } elseif ($userData['role'] === 'lecturer') {
                        $roleBadgeClass = 'bg-primary';
                        $roleLabel = 'Academic Staff';
                    } elseif ($userData['role'] === 'student') {
                        $roleBadgeClass = 'bg-success';
                        $roleLabel = 'Undergraduate Student';
                    }
                ?>
                <span class="badge <?= $roleBadgeClass ?> px-3 py-1.5 fs-6"><?= $roleLabel ?></span>
            </div>

            <?php if (!empty($userData['description'])): ?>
                <div class="bg-light p-3 rounded-3 text-muted small text-start mb-3">
                    <i class="fa-solid fa-quote-left text-muted me-1 opacity-50"></i>
                    <?= nl2br(e($userData['description'])) ?>
                </div>
            <?php endif; ?>

            <div class="border-top pt-3 text-start small">
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Department:</span>
                    <strong class="text-dark"><?= e(APP_DEPT) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Faculty:</span>
                    <strong class="text-dark"><?= e(APP_FACULTY) ?></strong>
                </div>
                <?php if (!empty($userData['reg_number'])): ?>
                    <div class="d-flex justify-content-between py-1 text-muted">
                        <span>Registration No:</span>
                        <strong class="text-dark font-mono"><?= e($userData['reg_number']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 text-muted">
                        <span>Index Number:</span>
                        <strong class="text-dark font-mono"><?= e($userData['index_number']) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($userData['nic'])): ?>
                    <div class="d-flex justify-content-between py-1 text-muted">
                        <span>National ID (NIC):</span>
                        <strong class="text-dark font-mono"><?= e($userData['nic']) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($userData['staff_id'])): ?>
                    <div class="d-flex justify-content-between py-1 text-muted">
                        <span>Staff ID:</span>
                        <strong class="text-dark font-mono"><?= e($userData['staff_id']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs card-header-tabs m-0 px-3 pt-2">
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'profile' ? 'active fw-bold text-primary' : 'text-muted' ?>" href="profile.php?tab=profile">
                            <i class="fa-solid fa-user-pen me-2"></i> Edit Profile Details
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'password' ? 'active fw-bold text-primary' : 'text-muted' ?>" href="profile.php?tab=password">
                            <i class="fa-solid fa-shield-halved me-2 text-warning"></i> Change Password with Email Verification
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4 p-md-5">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2.5 px-3 small border-0 d-flex align-items-center mb-4" role="alert" style="background-color: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444 !important; border-radius: var(--radius-sm);">
                        <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($activeTab === 'profile'): ?>
                    <form method="POST" action="profile.php?tab=profile" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">

                        <h5 class="fw-bold text-dark mb-3">Update Personal Information</h5>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-dark">Change Profile Picture</label>
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= e($currentAvatar) ?>" alt="Current Avatar" class="rounded-circle shadow-sm" style="width: 60px; height: 60px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" name="avatar_file" accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text small text-muted">Upload JPG, PNG, or WebP photo (Max 5MB).</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark">Full Name</label>
                            <input type="text" class="form-control" name="full_name" value="<?= e($userData['full_name']) ?>" required>
                            <div class="form-text small text-muted">Your display name shown on dashboards, attendance registers, and official transcripts.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark">Institutional Email (Verified)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                                <input type="email" class="form-control font-mono bg-light" value="<?= e($userData['email']) ?>" readonly>
                                <span class="input-group-text bg-success-subtle text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> Verified</span>
                            </div>
                            <div class="form-text small text-muted">Official Rajarata University email address ending with <code>@as.rjt.ac.lk</code>.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-dark">Profile Description / Biography</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Tell colleagues or lecturers about your academic interests, department duties, or specializations..."><?= e($userData['description'] ?? '') ?></textarea>
                            <div class="form-text small text-muted">Visible on your academic profile.</div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary fw-bold px-4 py-2 shadow-sm">
                                <i class="fa-solid fa-floppy-disk me-1.5"></i> Save Profile Changes
                            </button>
                        </div>
                    </form>

                <?php else: ?>
                    <div>
                        <div class="d-flex align-items-center gap-3 bg-light p-3 rounded-3 mb-4">
                            <div class="bg-warning text-dark rounded-circle p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                <i class="fa-solid fa-envelope-shield fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Email-Verified Password Change</h6>
                                <p class="text-muted small mb-0">
                                    To protect university accounts from unauthorized access, password updates require verification through an OTP code sent to your registered institutional email (<code><?= e($userData['email']) ?></code>).
                                </p>
                            </div>
                        </div>

                        <div class="card border border-warning-subtle bg-warning-subtle bg-opacity-25 p-3 mb-4 rounded-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-dark small"><i class="fa-solid fa-paper-plane me-1 text-warning"></i> Request Password Verification OTP</div>
                                    <div class="text-muted small">Click to send a fresh 6-digit code to <strong><?= e($userData['email']) ?></strong></div>
                                </div>
                                <form method="POST" action="profile.php?tab=password" class="m-0">
                                    <input type="hidden" name="request_password_otp" value="1">
                                    <button type="submit" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm">
                                        <i class="fa-solid fa-paper-plane me-1"></i> Send Verification Code
                                    </button>
                                </form>
                            </div>
                        </div>

                        <form method="POST" action="profile.php?tab=password">
                            <input type="hidden" name="change_password" value="1">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-dark">6-Digit Email Verification Code</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-key"></i></span>
                                    <input type="text" class="form-control font-mono fs-5 fw-bold text-primary" name="verification_code" id="verificationCodeInput" placeholder="Enter 6-digit OTP code" maxlength="6" required>
                                </div>
                                <div class="form-text small text-muted">Check your inbox for the code sent from <code>no-reply@as.rjt.ac.lk</code>.</div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-dark">New Password</label>
                                    <input type="password" class="form-control" name="new_password" placeholder="At least 6 characters" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-dark">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" placeholder="Re-type new password" required>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary fw-bold px-4 py-2 shadow-sm">
                                    <i class="fa-solid fa-shield-check me-1.5"></i> Verify &amp; Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
