<?php

require_once __DIR__ . '/../config/app.php';

$db = Database::getConnection();
$error = '';
$success = '';

$email = trim($_GET['email'] ?? ($_POST['email'] ?? ''));
$token = trim($_GET['token'] ?? '');

if (!empty($token) && !empty($email)) {
    $stmt = $db->prepare("
        SELECT id, full_name, is_email_verified, verification_expires_at
        FROM users
        WHERE email = ? AND verification_token = ?
    ");
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch();

    if ($user) {
        $updateStmt = $db->prepare("
            UPDATE users
            SET is_email_verified = 1, verification_code = NULL, verification_token = NULL
            WHERE id = ?
        ");
        $updateStmt->execute([$user['id']]);
        setFlash('success', 'Email verified successfully! Your university account is now active. You may sign in.');
        header('Location: index.php');
        exit;
    } else {
        $error = 'The verification link is invalid or has expired. Please request a new verification code below.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $code = trim($_POST['code'] ?? '');

    if (empty($email) || empty($code)) {
        $error = 'Please enter both your institutional email address and the 6-digit verification code.';
    } else {
        $stmt = $db->prepare("
            SELECT id, full_name, verification_code, verification_expires_at
            FROM users
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'No user account found matching this institutional email address.';
        } elseif ($user['verification_code'] !== $code) {
            $error = 'The verification code you entered is incorrect. Please check your inbox or request a new code.';
        } else {

            if (!empty($user['verification_expires_at']) && strtotime($user['verification_expires_at']) < time()) {
                $error = 'This verification code has expired. Please click "Resend Code" to receive a new one.';
            } else {
                $updateStmt = $db->prepare("
                    UPDATE users
                    SET is_email_verified = 1, verification_code = NULL, verification_token = NULL
                    WHERE id = ?
                ");
                $updateStmt->execute([$user['id']]);
                setFlash('success', 'Email verification successful! Welcome to Rajarata University SAMS. You may now sign in.');
                header('Location: index.php');
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code'])) {
    if (empty($email)) {
        $error = 'Please enter your institutional email to resend your verification code.';
    } else {
        $stmt = $db->prepare("SELECT id, full_name, is_email_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'No university account was found with this email.';
        } elseif ($user['is_email_verified']) {
            setFlash('info', 'Your email is already verified. You can sign in directly.');
            header('Location: index.php');
            exit;
        } else {
            $newCode = generateVerificationCode();
            $newToken = generateVerificationToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $updateStmt = $db->prepare("
                UPDATE users
                SET verification_code = ?, verification_token = ?, verification_expires_at = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$newCode, $newToken, $expiresAt, $user['id']]);

            sendVerificationEmail($email, $newCode, $newToken, $user['full_name']);
            setFlash('success', 'A new 6-digit verification code has been dispatched to your institutional inbox.');
            header('Location: verify_email.php?email=' . urlencode($email));
            exit;
        }
    }
}

$simulatedCode = '';
$simulatedToken = '';
if (!empty($email)) {
    $stmt = $db->prepare("SELECT verification_code, verification_token FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $uRow = $stmt->fetch();
    if ($uRow && $uRow['verification_code']) {
        $simulatedCode = $uRow['verification_code'];
        $simulatedToken = $uRow['verification_token'];
    }
}

$pageTitle = 'Verify Institutional Email';
require_once __DIR__ . '/layout/header.php';
?>

<div class="row justify-content-center py-4 py-md-5">
    <div class="col-md-7 col-lg-6 col-xl-5">
        <div class="card border-0 shadow-lg" style="border-radius: var(--radius-xl);">
            <div class="card-body p-4 p-md-5">

                <div class="text-center mb-4">
                    <div class="bg-warning-subtle text-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 68px; height: 68px;">
                        <i class="fa-solid fa-envelope-circle-check fs-2 text-warning"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">Verify Your Email</h3>
                    <p class="text-muted small mb-0">
                        Rajarata University of Sri Lanka &bull; Department of Computing
                    </p>
                </div>

                <div class="bg-light p-3 rounded-3 mb-4 text-center">
                    <p class="small text-muted mb-0">
                        In accordance with FAS security policy, an activation code has been sent to your university inbox. Enter the 6-digit code below to confirm ownership.
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2.5 px-3 small border-0 d-flex align-items-center mb-3" role="alert" style="background-color: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444 !important; border-radius: var(--radius-sm);">
                        <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="verify_email.php">
                    <input type="hidden" name="verify_code" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark">Institutional Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" class="form-control border-start-0 font-mono" name="email" id="emailInput"
                                   placeholder="username@as.rjt.ac.lk"
                                   value="<?= e($email) ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-dark">6-Digit Security OTP Code</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-key"></i></span>
                            <input type="text" class="form-control form-control-lg border-start-0 font-mono text-center fw-extrabold text-primary"
                                   id="codeInput" name="code" placeholder="&bull; &bull; &bull; &bull; &bull; &bull;" maxlength="6" autofocus required
                                   style="letter-spacing: 0.35rem; font-size: 1.5rem;">
                        </div>
                        <div class="form-text small text-muted text-center mt-1">
                            Check your inbox or junk/spam folder for your verification message.
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary py-2.5 fw-bold shadow-sm">
                            <i class="fa-solid fa-circle-check me-2"></i> Confirm &amp; Activate Account
                        </button>
                    </div>
                </form>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top small">
                    <form method="POST" action="verify_email.php" class="m-0">
                        <input type="hidden" name="resend_code" value="1">
                        <input type="hidden" name="email" value="<?= e($email) ?>">
                        <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none text-muted fw-semibold">
                            <i class="fa-solid fa-rotate-right me-1"></i> Resend Code
                        </button>
                    </form>

                    <a href="index.php" class="text-decoration-none text-primary fw-semibold">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
