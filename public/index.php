<?php

require_once __DIR__ . '/../config/app.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your institutional email and password.';
    } elseif (!isInstitutionalEmail($email)) {
        $error = 'Access restricted: Please enter your Rajarata University institutional email ending with @as.rjt.ac.lk.';
    } else {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, s.reg_number, s.index_number, s.title, s.batch_id, s.nic
            FROM users u
            LEFT JOIN students s ON u.student_id = s.id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {

            if ((int)($user['is_email_verified'] ?? 0) === 0) {
                $code = generateVerificationCode();
                $token = generateVerificationToken();
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

                $upStmt = $db->prepare("
                    UPDATE users
                    SET verification_code = ?, verification_token = ?, verification_expires_at = ?
                    WHERE id = ?
                ");
                $upStmt->execute([$code, $token, $expires, $user['id']]);

                sendVerificationEmail($email, $code, $token, $user['full_name']);

                setFlash('error', 'Your institutional email is pending verification. A fresh 6-digit verification code has been dispatched. Please verify below to sign in.');
                header('Location: verify_email.php?email=' . urlencode($email));
                exit;
            }

            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'staff_id' => $user['staff_id'],
                'is_external_lecturer' => (bool)$user['is_external_lecturer'],
                'student_id' => $user['student_id'] ? (int)$user['student_id'] : null,
                'reg_number' => $user['reg_number'] ?? null,
                'index_number' => $user['index_number'] ?? null,
                'title' => $user['title'] ?? null,
                'batch_id' => $user['batch_id'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'description' => $user['description'] ?? null,
                'nic' => $user['nic'] ?? null
            ];
            setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid institutional email or password. Please verify your credentials and try again.';
        }
    }
}

$pageTitle = 'Institutional Sign In';
require_once __DIR__ . '/layout/header.php';
?>

<div class="row justify-content-center align-items-stretch py-3 py-lg-4 g-4">

    <div class="col-lg-7 d-none d-lg-block">
        <div class="card h-100 border-0 shadow-lg text-white overflow-hidden position-relative" style="border-radius: var(--radius-xl); background: #0f172a;">

            <div style="
                position: absolute;
                inset: 0;
                background-image: url('assets/images/campus_hero.jpg');
                background-size: cover;
                background-position: center;
                opacity: 0.35;
                filter: saturate(1.2);
            "></div>

            <div style="
                position: absolute;
                inset: 0;
                background: linear-gradient(180deg, rgba(15, 23, 42, 0.5) 0%, rgba(30, 58, 138, 0.85) 100%);
            "></div>

            <div class="card-body p-5 d-flex flex-column justify-content-between position-relative z-1">
                <div>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-10 backdrop-blur px-3 py-1.5 rounded-pill border border-white border-opacity-20">
                            <i class="fa-solid fa-graduation-cap text-warning"></i>
                            <span class="small fw-semibold text-white">Rajarata University of Sri Lanka</span>
                        </div>
                        <div class="d-inline-flex align-items-center gap-1.5 bg-primary bg-opacity-25 backdrop-blur px-3 py-1.5 rounded-pill border border-primary border-opacity-40">
                            <i class="fa-solid fa-laptop-code text-warning" style="font-size: 0.75rem;"></i>
                            <span class="small font-mono fw-bold text-white">http://localhost:8000</span>
                        </div>
                    </div>

                    <h1 class="display-6 fw-extrabold mb-3 text-white">
                        Student Attendance &amp; <br>Examination Eligibility Portal
                    </h1>

                    <p class="lead text-white-50 mb-4" style="font-size: 1.05rem;">
                        Department of Computing &bull; Faculty of Applied Sciences (FAS)
                    </p>

                    <p class="text-white text-opacity-75 mb-4" style="max-width: 500px; font-size: 0.95rem; line-height: 1.6;">
                        Welcome to the official institutional portal for tracking academic session attendance, practical evaluations, medical certificate submissions, and semester examination qualifications.
                    </p>

                    <div class="row g-3 my-2" style="max-width: 520px;">
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3 bg-white bg-opacity-10 p-2.5 rounded-3 border border-white border-opacity-10">
                                <div class="bg-warning text-dark rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="fa-solid fa-chart-line"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small text-white">Automated 80% Threshold Calculation</div>
                                    <div class="text-white-50" style="font-size: 0.775rem;">Calculates eligibility for Lectures and Practical sessions separately.</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3 bg-white bg-opacity-10 p-2.5 rounded-3 border border-white border-opacity-10">
                                <div class="bg-info text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="fa-solid fa-file-medical"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small text-white">14-Day Medical Certificate Submission</div>
                                    <div class="text-white-50" style="font-size: 0.775rem;">Submit and track valid medical certificates (MC) and excused absences online.</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3 bg-white bg-opacity-10 p-2.5 rounded-3 border border-white border-opacity-10">
                                <div class="bg-success text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="fa-solid fa-award"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small text-white">Mahapola &amp; Bursary Verification</div>
                                    <div class="text-white-50" style="font-size: 0.775rem;">Direct department export and verification for scholarship disbursements.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-top border-white border-opacity-20 d-flex align-items-center justify-content-between text-white-50 small">
                    <div><i class="fa-solid fa-location-dot me-1 text-warning"></i> Mihintale Campus, Anuradhapura</div>
                    <div>Department of Computing</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8 col-lg-5">
        <div class="card border-0 shadow-lg h-100" style="border-radius: var(--radius-xl);">
            <div class="card-body p-4 p-md-5 d-flex flex-column justify-content-between">
                <div>

                    <div class="text-center mb-4">
                        <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-shield-halved fs-3 text-primary"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Institutional Sign In</h3>
                        <p class="text-muted small mb-0">
                            Enter your official university email credentials to continue
                        </p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2.5 px-3 small border-0 d-flex align-items-center mb-4" role="alert" style="background-color: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444 !important; border-radius: var(--radius-sm);">
                            <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php" id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label small fw-semibold text-dark">
                                Institutional Email Address
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" class="form-control border-start-0 font-mono" id="email" name="email"
                                       placeholder="username@as.rjt.ac.lk"
                                       value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                            </div>
                            <div class="form-text small text-muted">
                                Students: <code>regno@as.rjt.ac.lk</code> &bull; Staff: <code>username@as.rjt.ac.lk</code>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="form-label small fw-semibold text-dark mb-0">Password</label>
                                <a href="forgot_password.php" class="text-decoration-none small text-warning fw-semibold">Forgot Password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Password" required>
                                <button class="btn btn-outline-light border text-muted" type="button" onclick="togglePasswordVisibility()">
                                    <i class="fa-solid fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="small text-muted" style="font-size: 0.75rem;">Imported students: initial password is NIC</span>
                                <a href="verify_email.php" class="text-decoration-none small text-primary fw-semibold" style="font-size: 0.775rem;">Verify Email</a>
                            </div>
                        </div>

                        <div class="d-grid mb-3 mt-4">
                            <button type="submit" class="btn btn-primary py-2.5 fw-bold shadow-sm">
                                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Sign In to SAMS
                            </button>
                        </div>

                        <div class="text-center pt-2 border-top">
                            <span class="text-muted small">Academic Staff Member?</span>
                            <a href="register.php" class="text-decoration-none fw-bold small text-primary ms-1">
                                Staff Registration <i class="fa-solid fa-arrow-right small ms-0.5"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    var pwd = document.getElementById('password');
    var icon = document.getElementById('togglePasswordIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
