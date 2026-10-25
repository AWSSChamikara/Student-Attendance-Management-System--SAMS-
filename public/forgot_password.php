<?php

require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
$error = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$email = trim($_GET['email'] ?? ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_code'])) {
    if (empty($email)) {
        $error = 'Please enter your institutional email address.';
    } elseif (!isInstitutionalEmail($email)) {
        $error = 'Please enter a valid Rajarata University institutional email ending with @as.rjt.ac.lk.';
    } else {
        $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = generateVerificationCode();
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $upStmt = $db->prepare("
                UPDATE users
                SET password_reset_code = ?, password_reset_expires_at = ?
                WHERE id = ?
            ");
            $upStmt->execute([$otp, $expires, $user['id']]);

            sendPasswordResetEmail($user['email'], $otp, $user['full_name']);

            setFlash('info', "A 6-digit verification code has been dispatched to {$user['email']}. Please enter it below to set your new password.");
            header('Location: forgot_password.php?step=2&email=' . urlencode($email));
            exit;
        } else {
            $error = 'No university account was found matching this email address.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $step = 2;
    $code = trim($_POST['code'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($code) || empty($newPass)) {
        $error = 'Please complete all required fields.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $db->prepare("SELECT id, password_reset_code, password_reset_expires_at FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'User account not found.';
        } elseif (empty($user['password_reset_code']) || $user['password_reset_code'] !== $code) {
            $error = 'Invalid verification code. Please check your email and try again.';
        } elseif (!empty($user['password_reset_expires_at']) && strtotime($user['password_reset_expires_at']) < time()) {
            $error = 'This verification code has expired. Please request a new code.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $upStmt = $db->prepare("
                UPDATE users
                SET password_hash = ?, password_reset_code = NULL, password_reset_expires_at = NULL
                WHERE id = ?
            ");
            $upStmt->execute([$newHash, $user['id']]);

            setFlash('success', 'Your password has been reset successfully! You can now sign in with your new password.');
            header('Location: index.php');
            exit;
        }
    }
}

$simulatedCode = '';
if (!empty($email) && $step === 2) {
    $stmt = $db->prepare("SELECT password_reset_code FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $uRow = $stmt->fetch();
    if ($uRow && $uRow['password_reset_code']) {
        $simulatedCode = $uRow['password_reset_code'];
    }
}

$pageTitle = 'Reset Password with Email Verification';
require_once __DIR__ . '/layout/header.php';
?>

<div class="row justify-content-center py-4 py-md-5">
    <div class="col-md-7 col-lg-6 col-xl-5">
        <div class="card border-0 shadow-lg" style="border-radius: var(--radius-xl);">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="bg-warning-subtle text-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 68px; height: 68px;">
                        <i class="fa-solid fa-key fs-2 text-warning"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">Reset Account Password</h3>
                    <p class="text-muted small mb-0">
                        Email-Verified Password Recovery &bull; Rajarata University
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2.5 px-3 small border-0 d-flex align-items-center mb-4" role="alert" style="background-color: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444 !important; border-radius: var(--radius-sm);">
                        <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($step === 1): ?>

                    <form method="POST" action="forgot_password.php?step=1">
                        <input type="hidden" name="request_code" value="1">

                        <p class="small text-muted mb-3">
                            Enter your registered institutional university email. A 6-digit verification code will be dispatched to confirm ownership.
                        </p>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-dark">Institutional Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" class="form-control font-mono" name="email" value="<?= e($email) ?>" placeholder="Institutional Email Address" required autofocus>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary py-2.5 fw-bold shadow-sm">
                                <i class="fa-solid fa-paper-plane me-2"></i> Send Verification Code
                            </button>
                        </div>
                    </form>

                <?php else: ?>

                    <form method="POST" action="forgot_password.php?step=2">
                        <input type="hidden" name="reset_password" value="1">
                        <input type="hidden" name="email" value="<?= e($email) ?>">

                        <div class="bg-light p-2.5 rounded-3 mb-3 small text-muted">
                            Verification code sent to: <strong class="text-dark font-mono"><?= e($email) ?></strong>
                            <a href="forgot_password.php?step=1" class="float-end text-primary text-decoration-none">Change</a>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark">6-Digit Verification Code</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-key"></i></span>
                                <input type="text" class="form-control font-mono fs-5 fw-bold text-primary text-center" id="otpCodeInput" name="code" placeholder="------" maxlength="6" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark">New Password</label>
                            <input type="password" class="form-control" name="new_password" placeholder="At least 6 characters" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-dark">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="Re-type new password" required>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary py-2.5 fw-bold shadow-sm">
                                <i class="fa-solid fa-shield-check me-2"></i> Confirm &amp; Reset Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="text-center pt-3 border-top small">
                    <a href="index.php" class="text-decoration-none text-primary fw-semibold">
                        <i class="fa-solid fa-arrow-left me-1"></i> Return to Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
