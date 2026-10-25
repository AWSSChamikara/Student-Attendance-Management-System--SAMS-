<?php

require_once __DIR__ . '/../../config/app.php';
$user = getCurrentUser();
$role = $user['role'] ?? null;
$isAdmin = ($role === 'hod' || $role === 'dean' || $role === 'ar');

$avatarImg = 'assets/images/avatar_student.jpg';
if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])) {
    $avatarImg = $user['avatar'];
} elseif ($role === 'dean') {
    $avatarImg = 'assets/images/avatar_dean.jpg';
} elseif ($role === 'hod' || $role === 'ar' || $role === 'lecturer') {
    $avatarImg = 'assets/images/avatar_faculty.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e(APP_SHORT_NAME) ?> &bull; <?= e(APP_DEPT) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <script>
        window.SAMS_USER_LOGGED_IN = <?= isLoggedIn() ? 'true' : 'false' ?>;
        window.SAMS_INACTIVITY_LIMIT = <?= defined('SESSION_INACTIVITY_TIMEOUT') ? SESSION_INACTIVITY_TIMEOUT : 300 ?>;
        window.SAMS_LOGOUT_URL = 'logout.php?reason=timeout';
    </script>
</head>
<body>

    <div class="institutional-topbar py-1 px-3 d-none d-md-block">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <span>
                    <i class="fa-solid fa-graduation-cap me-1 text-warning"></i>
                    <strong><?= e(APP_UNIVERSITY) ?></strong> &bull; <?= e(APP_FACULTY) ?>
                </span>
                <span class="text-white-50">&bull;</span>
                <span class="text-white-50"><i class="fa-solid fa-code me-1 text-info"></i> <?= e(APP_DEPT) ?></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="http://localhost:8000" class="badge bg-primary text-white text-decoration-none px-2.5 py-1 font-mono shadow-sm" title="Local Development: http://localhost:8000">
                    <i class="fa-solid fa-laptop-code me-1 text-warning"></i> localhost:8000
                </a>
                <span class="text-white-50">&bull;</span>
                <a href="mailto:helpdesk@as.rjt.ac.lk" class="small"><i class="fa-solid fa-headset me-1 text-warning"></i> FAS IT Help Desk</a>
                <span class="text-white-50">&bull;</span>
                <span class="badge bg-warning text-dark font-mono px-2 py-0.5" style="font-size: 0.675rem;">EIS v2.4</span>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-sams sticky-top">
        <div class="container-fluid px-3">
            <a class="navbar-brand d-flex align-items-center py-0" href="dashboard.php">
                <img src="assets/images/rusl_logo_white.svg" alt="RUSL" height="38" class="me-2 d-none d-sm-inline-block">
                <div class="d-sm-none d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary rounded p-1 me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="fa-solid fa-graduation-cap fs-5"></i>
                    </div>
                    <div>
                        <span class="fs-6 fw-bold text-white"><?= e(APP_SHORT_NAME) ?></span>
                        <div class="text-white-50" style="font-size: 0.65rem;">Rajarata University</div>
                    </div>
                </div>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#samsNavbar" aria-controls="samsNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="samsNavbar">
                <?php if (isLoggedIn()): ?>
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                        <li class="nav-item">
                            <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php' || basename($_SERVER['PHP_SELF']) == $role . '_dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                                <i class="fa-solid fa-house-chimney me-1 text-info"></i> Portal Home
                            </a>
                        </li>

                        <?php if ($isAdmin): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'practical_qualification.php' ? 'active' : '' ?>" href="practical_qualification.php">
                                    <i class="fa-solid fa-flask-vial me-1 text-warning"></i> Practical Evaluation
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'mahapola_verification.php' ? 'active' : '' ?>" href="mahapola_verification.php">
                                    <i class="fa-solid fa-award me-1 text-warning"></i> Mahapola (80%)
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'eis_upload.php' ? 'active' : '' ?>" href="eis_upload.php">
                                    <i class="fa-solid fa-file-excel me-1 text-success"></i> EIS Import
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-sliders me-1"></i> Academic Setup
                                </a>
                                <ul class="dropdown-menu dropdown-menu-dark shadow-lg">
                                    <li><a class="dropdown-item py-2" href="manage_courses.php"><i class="fa-solid fa-book-open me-2 text-info"></i>Courses &amp; Modules</a></li>
                                    <li><a class="dropdown-item py-2" href="manage_lecturers.php"><i class="fa-solid fa-chalkboard-user me-2 text-warning"></i>Lecturer Assignments</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2" href="manage_settings.php"><i class="fa-solid fa-gear me-2 text-secondary"></i>Eligibility Threshold (80%)</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if ($isAdmin || $role === 'lecturer'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'absence_requests.php' ? 'active' : '' ?>" href="absence_requests.php">
                                    <i class="fa-solid fa-notes-medical me-1 text-danger"></i> Medical Claims
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ($role === 'student'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'absence_requests.php' ? 'active' : '' ?>" href="absence_requests.php">
                                    <i class="fa-solid fa-file-circle-plus me-1 text-warning"></i> Submit Medical Certificate
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'student_search.php' ? 'active' : '' ?>" href="student_search.php">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Student Search
                            </a>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center ms-auto">
                        <div class="dropdown">
                            <button class="btn btn-dark d-flex align-items-center gap-2 py-1 px-2 border-0 bg-transparent text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?= e($avatarImg) ?>" alt="Avatar" class="user-avatar-img">
                                <div class="text-start d-none d-md-block">
                                    <div class="fw-bold" style="font-size: 0.825rem; line-height: 1.1;"><?= e($user['full_name']) ?></div>
                                    <div class="text-white-50" style="font-size: 0.7rem;">
                                        <?php
                                            $badgeClass = 'bg-success';
                                            if ($role === 'dean') $badgeClass = 'bg-danger';
                                            elseif ($role === 'hod') $badgeClass = 'bg-warning text-dark';
                                            elseif ($role === 'ar') $badgeClass = 'bg-info text-dark';
                                            elseif ($role === 'lecturer') $badgeClass = 'bg-primary';
                                        ?>
                                        <span class="badge <?= $badgeClass ?> px-1.5 py-0.5 text-uppercase" style="font-size: 0.65rem;">
                                            <?= e($role) ?>
                                        </span>
                                        <?= e($user['email']) ?>
                                    </div>
                                </div>
                                <i class="fa-solid fa-chevron-down text-white-50 small ms-1"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg py-2">
                                <li class="px-3 py-2 border-bottom">
                                    <div class="fw-bold text-dark"><?= e($user['full_name']) ?></div>
                                    <div class="text-muted small"><?= e($user['email']) ?></div>
                                    <?php if (!empty($user['reg_number'])): ?>
                                        <div class="badge bg-light text-dark border mt-1">Reg: <?= e($user['reg_number']) ?></div>
                                    <?php endif; ?>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="dashboard.php">
                                        <i class="fa-solid fa-gauge me-2 text-primary"></i> My Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="profile.php">
                                        <i class="fa-solid fa-user-pen me-2 text-info"></i> Edit Profile &amp; Picture
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="profile.php?tab=password">
                                        <i class="fa-solid fa-key me-2 text-warning"></i> Change Password
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item py-2 text-danger fw-semibold" href="logout.php">
                                        <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Sign Out
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item">
                            <a class="nav-link" href="register.php"><i class="fa-solid fa-user-plus me-1 text-warning"></i> Staff Registration</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="verify_email.php"><i class="fa-solid fa-envelope-circle-check me-1 text-info"></i> Verify Email</a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-warning btn-sm text-dark fw-bold px-3 shadow-sm" href="index.php">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 pt-3">
        <?php if ($msg = getFlash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert" style="background-color: #ecfdf5; color: #065f46; border-left: 4px solid #10b981 !important;">
                <i class="fa-solid fa-circle-check fs-5 me-3 text-success"></i>
                <div class="flex-grow-1"><?= e($msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($msg = getFlash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert" style="background-color: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444 !important;">
                <i class="fa-solid fa-circle-exclamation fs-5 me-3 text-danger"></i>
                <div class="flex-grow-1"><?= e($msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($msg = getFlash('info')): ?>
            <div class="alert alert-info alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert" style="background-color: #f0f9ff; color: #075985; border-left: 4px solid #0ea5e9 !important;">
                <i class="fa-solid fa-circle-info fs-5 me-3 text-info"></i>
                <div class="flex-grow-1"><?= e($msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($msg = getFlash('warning')): ?>
            <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert" style="background-color: #fffbeb; color: #92400e; border-left: 4px solid #f59e0b !important;">
                <i class="fa-solid fa-clock-rotate-left fs-5 me-3 text-warning"></i>
                <div class="flex-grow-1"><?= e($msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>

    <main class="main-content py-3">
        <div class="container-fluid px-3">
