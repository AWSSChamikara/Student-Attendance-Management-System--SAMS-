<?php

require_once __DIR__ . '/../config/app.php';
requireLogin();

$role = $_SESSION['user']['role'] ?? '';
if (in_array($role, ['hod', 'dean', 'ar'], true)) {
    header('Location: hod_dashboard.php');
} elseif ($role === 'lecturer') {
    header('Location: lecturer_dashboard.php');
} elseif ($role === 'student') {
    header('Location: student_dashboard.php');
} else {
    header('Location: index.php');
}
exit;
