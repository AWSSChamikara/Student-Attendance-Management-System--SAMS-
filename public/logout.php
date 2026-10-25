<?php

require_once __DIR__ . '/../config/app.php';

$reason = $_GET['reason'] ?? '';

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
session_start();

if ($reason === 'timeout') {
    setFlash('warning', 'For your account security, you have been automatically signed out after 5 minutes of inactivity.');
} else {
    setFlash('info', 'You have been successfully signed out.');
}

header('Location: index.php');
exit;
