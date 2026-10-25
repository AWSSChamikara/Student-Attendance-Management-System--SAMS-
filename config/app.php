<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

define('APP_NAME', 'Student Attendance Management System');
define('APP_SHORT_NAME', 'SAMS');
define('APP_DEPT', 'Department of Computing');
define('APP_FACULTY', 'Faculty of Applied Sciences (FAS)');
define('APP_UNIVERSITY', 'Rajarata University of Sri Lanka (RUSL)');
define('INSTITUTION_EMAIL_DOMAIN', 'as.rjt.ac.lk');
define('OFFICIAL_APP_URL', 'http://localhost:8000');
define('DEFAULT_ELIGIBILITY_PERCENT', 80.00);
define('MC_MAX_DAYS', 14);
define('SESSION_INACTIVITY_TIMEOUT', 300);

function getAppBaseUrl(): string {
    return OFFICIAL_APP_URL;
}

function isInstitutionalEmail(string $email): bool {
    $email = trim(strtolower($email));
    return str_ends_with($email, 'as.rjt.ac.lk') ||
           str_ends_with($email, 'rjt.ac.lk') ||
           str_ends_with($email, 'rjt.as.ac.lk') ||
           str_ends_with($email, 'as.ac.lk') ||
           str_ends_with($email, 'ac.lk');
}

function generateStudentEmailFromRegNo(string $regNo): string {
    $clean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $regNo));
    $emailDomain = ltrim(strtolower(trim(getSystemSetting('institution_email_domain', 'as.rjt.ac.lk'))), '@');
    if ($emailDomain === '' || filter_var('student@' . $emailDomain, FILTER_VALIDATE_EMAIL) === false) {
        $emailDomain = 'as.rjt.ac.lk';
    }
    return $clean . '@' . $emailDomain;
}

function generateVerificationCode(): string {
    return sprintf("%06d", mt_rand(100000, 999999));
}

function generateVerificationToken(): string {
    return bin2hex(random_bytes(24));
}

function sendVerificationEmail(string $email, string $code, string $token, string $name = 'User'): bool {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $verifyUrl = getAppBaseUrl() . "/verify_email.php?token={$token}&email=" . urlencode($email);
    $logEntry = "[{$timestamp}] Verification email dispatched to: {$email} (Code: {$code}, Link: {$verifyUrl})\n";
    file_put_contents($logDir . '/email_verifications.log', $logEntry, FILE_APPEND);

    $_SESSION['last_verification'] = [
        'email' => $email,
        'code' => $code,
        'token' => $token,
        'link' => $verifyUrl,
        'time' => $timestamp
    ];

    $subject = "Verify your Email - Rajarata University of Sri Lanka (RUSL)";
    $message = "Dear {$name},\r\n\r\n"
             . "Welcome to the Student Attendance Management System (SAMS) at the Department of Computing, Faculty of Applied Sciences (FAS), Rajarata University of Sri Lanka (RUSL).\r\n\r\n"
             . "Your 6-Digit Email Verification Code is: {$code}\r\n\r\n"
             . "Alternatively, you can verify your account directly by clicking this link:\r\n"
             . "{$verifyUrl}\r\n\r\n"
             . "This code is valid for 24 hours.\r\n\r\n"
             . "Department of Computing,\r\n"
             . "Faculty of Applied Sciences (FAS),\r\n"
             . "Rajarata University of Sri Lanka (RUSL)";
    $headers = "From: no-reply@as.rjt.ac.lk\r\n"
             . "Reply-To: helpdesk@as.rjt.ac.lk\r\n"
             . "X-Mailer: PHP/" . phpversion();

    @mail($email, $subject, $message, $headers);
    return true;
}

function sendPasswordResetEmail(string $email, string $code, string $name = 'User'): bool {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] Password Change Verification OTP dispatched to: {$email} (Code: {$code})\n";
    file_put_contents($logDir . '/email_verifications.log', $logEntry, FILE_APPEND);

    $_SESSION['last_password_reset_code'] = [
        'email' => $email,
        'code' => $code,
        'time' => $timestamp
    ];

    $resetUrl = getAppBaseUrl() . "/forgot_password.php?email=" . urlencode($email);
    $subject = "Password Change Verification OTP - Rajarata University of Sri Lanka";
    $message = "Dear {$name},\r\n\r\n"
             . "A request has been made to change the password for your account on the Student Attendance Management System (SAMS) at Rajarata University of Sri Lanka (RUSL).\r\n\r\n"
             . "Portal Link: " . OFFICIAL_APP_URL . "\r\n\r\n"
             . "Your 6-Digit Password Verification Code is: {$code}\r\n\r\n"
             . "Enter this code on the password update screen ({$resetUrl}) to confirm your change.\r\n\r\n"
             . "If you did not make this request, please contact the IT Help Desk (helpdesk@as.rjt.ac.lk) immediately.\r\n\r\n"
             . "Department of Computing,\r\n"
             . "Faculty of Applied Sciences (FAS),\r\n"
             . "Rajarata University of Sri Lanka (RUSL)";
    $headers = "From: no-reply@as.rjt.ac.lk\r\n"
             . "Reply-To: helpdesk@as.rjt.ac.lk\r\n"
             . "X-Mailer: PHP/" . phpversion();

    @mail($email, $subject, $message, $headers);
    return true;
}

function getSystemSetting(string $key, string $default = ''): string {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

function updateSystemSetting(string $key, string $value): bool {
    $db = Database::getConnection();
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    return $stmt->execute([$key, $value]);
}

function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user']['id']);
}

if (isLoggedIn()) {
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity'] > SESSION_INACTIVITY_TIMEOUT)) {

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
        setFlash('warning', 'For your account security, you have been automatically signed out after 5 minutes of inactivity.');
        header('Location: index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = $now;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Please log in to access this page.';
        header('Location: index.php');
        exit;
    }
}

function requireRole(array|string $roles): void {
    requireLogin();
    $roles = (array)$roles;
    $currentRole = $_SESSION['user']['role'] ?? '';

    if (($currentRole === 'dean' || $currentRole === 'ar') && (in_array('hod', $roles, true) || in_array('dean', $roles, true) || in_array('ar', $roles, true))) {
        return;
    }
    if (!in_array($currentRole, $roles, true)) {
        $_SESSION['flash_error'] = 'Access denied. You do not have permission for this section.';
        header('Location: dashboard.php');
        exit;
    }
}

function requireHod(): void {
    requireRole(['hod', 'dean', 'ar']);
}

function requireAr(): void {
    requireRole(['ar', 'hod', 'dean']);
}

function requireLecturer(): void {
    requireRole(['hod', 'dean', 'ar', 'lecturer']);
}

function requireStudent(): void {
    requireRole('student');
}

function generateTitleFromGender(string $gender): string {
    $g = trim(strtolower($gender));
    if ($g === 'male' || $g === 'm') {
        return 'Mr';
    }
    return 'Mrs';
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash_' . $type] = $message;
}

function getFlash(string $type): ?string {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}
