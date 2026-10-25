<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/services/EligibilityService.php';
require_once __DIR__ . '/services/EisImporter.php';
require_once __DIR__ . '/services/AbsenceService.php';
require_once __DIR__ . '/services/AttendanceSheetExporter.php';

echo "=== STARTING RAJARATA UNIVERSITY SAMS VERIFICATION SUITE ===\n\n";

$db = Database::getConnection();

echo "[TEST 1] Verifying University, Faculty, Department, and Email Domain Settings...\n";
$univName = getSystemSetting('university_name');
$facultyName = getSystemSetting('faculty_name');
$deptName = getSystemSetting('department_name');
$emailDomain = getSystemSetting('institution_email_domain');
$officialUrl = getSystemSetting('official_system_url', OFFICIAL_APP_URL);

echo "  University: {$univName}\n";
echo "  Faculty: {$facultyName}\n";
echo "  Department: {$deptName}\n";
echo "  Email Domain: {$emailDomain}\n";
echo "  System URL: {$officialUrl}\n";

assert($univName === 'Rajarata University of Sri Lanka (RUSL)', "University name must match RUSL");
assert($facultyName === 'Faculty of Applied Sciences (FAS)', "Faculty name must match FAS");
assert($deptName === 'Department of Computing', "Department name must match Department of Computing");
assert($emailDomain === 'as.rjt.ac.lk', "Email domain must be as.rjt.ac.lk");
assert($officialUrl === 'http://localhost:8000', "System URL must match http://localhost:8000");
assert(OFFICIAL_APP_URL === 'http://localhost:8000', "OFFICIAL_APP_URL constant must match http://localhost:8000");
echo "  -> PASSED\n\n";

echo "[TEST 2] Verifying Academic Batches & Semesters...\n";
$batchCount = (int)$db->query("SELECT COUNT(*) FROM academic_batches")->fetchColumn();
$semesterCount = (int)$db->query("SELECT COUNT(*) FROM semesters")->fetchColumn();
echo "  Batches found: {$batchCount} (Requirement: 4 batches per year)\n";
echo "  Semesters found: {$semesterCount} (Requirement: 2 semesters per year)\n";
assert($batchCount >= 4, "Must have at least 4 batches");
assert($semesterCount >= 2, "Must have at least 2 semesters");
echo "  -> PASSED\n\n";

echo "[TEST 3] Verifying Student Reg No, Email (@as.rjt.ac.lk), NIC & Password...\n";
$testEmailGen = generateStudentEmailFromRegNo('ICT/2024/020');
echo "  generateStudentEmailFromRegNo('ICT/2024/020') = {$testEmailGen}\n";
assert($testEmailGen === 'ict2024020@as.rjt.ac.lk', "Student email must be ict2024020@as.rjt.ac.lk");

$sampleStudent = $db->query("SELECT * FROM students WHERE reg_number = 'ICT/2024/020'")->fetch();
assert(!empty($sampleStudent), "Student ICT/2024/020 must exist in database");
echo "  Student record: {$sampleStudent['name']} (Reg: {$sampleStudent['reg_number']}, Email: {$sampleStudent['email']}, NIC: {$sampleStudent['nic']})\n";
assert($sampleStudent['email'] === 'ict2024020@as.rjt.ac.lk', "Database email must match regno@as.rjt.ac.lk");
assert(!empty($sampleStudent['nic']), "Student NIC must be populated");

$studentUser = $db->query("SELECT * FROM users WHERE email = 'ict2024020@as.rjt.ac.lk'")->fetch();
assert(!empty($studentUser), "User account for ict2024020@as.rjt.ac.lk must exist");
assert(password_verify($sampleStudent['nic'], $studentUser['password_hash']), "Student default password must verify with their NIC number");
echo "  Student password matches NIC ({$sampleStudent['nic']}): YES\n";
echo "  -> PASSED\n\n";

echo "[TEST 4] Verifying Pre-Seeded Staff (HoD, Dean, AR) & Student Accounts Do NOT Require Email Verification...\n";
$dean = $db->query("SELECT * FROM users WHERE email = 'dean@as.rjt.ac.lk'")->fetch();
$hod = $db->query("SELECT * FROM users WHERE email = 'kule@as.rjt.ac.lk'")->fetch();
$ar = $db->query("SELECT * FROM users WHERE email = 'ar@as.rjt.ac.lk'")->fetch();

assert(!empty($dean), "Dean account dean@as.rjt.ac.lk must exist");
assert(!empty($hod), "HoD account kule@as.rjt.ac.lk must exist");
assert(!empty($ar), "AR account ar@as.rjt.ac.lk must exist");

echo "  Dean Account: {$dean['full_name']} ({$dean['email']}, Role: {$dean['role']}, Verified: {$dean['is_email_verified']})\n";
echo "  HoD Account:  {$hod['full_name']} ({$hod['email']}, Role: {$hod['role']}, Verified: {$hod['is_email_verified']})\n";
echo "  AR Account:   {$ar['full_name']} ({$ar['email']}, Role: {$ar['role']}, Verified: {$ar['is_email_verified']})\n";

assert((int)$dean['is_email_verified'] === 1, "Dean must have is_email_verified = 1 (no email verification needed)");
assert((int)$hod['is_email_verified'] === 1, "HoD must have is_email_verified = 1 (no email verification needed)");
assert((int)$ar['is_email_verified'] === 1, "AR must have is_email_verified = 1 (no email verification needed)");

assert(password_verify('abc123', $dean['password_hash']), "Dean password must verify with abc123");
assert(password_verify('abc123', $hod['password_hash']), "HoD password must verify with abc123");
assert(password_verify('abc123', $ar['password_hash']), "AR password must verify with abc123");
echo "  Staff default passwords match 'abc123': YES\n";
echo "  -> PASSED\n\n";

echo "[TEST 5] Verifying 6-Digit OTP Email Verification & Password Reset Workflow...\n";
$code = generateVerificationCode();
$token = generateVerificationToken();
echo "  Generated 6-digit OTP code: {$code}\n";
echo "  Generated verification token: {$token}\n";
assert(strlen($code) === 6 && ctype_digit($code), "Code must be 6 digits");
assert(strlen($token) === 48, "Token must be 48 hex characters");

$testEmail = 'test_verify@as.rjt.ac.lk';
sendVerificationEmail($testEmail, $code, $token, 'Test Scholar');
$logContent = file_get_contents(__DIR__ . '/logs/email_verifications.log');
assert(str_contains($logContent, $testEmail), "Email verification log must contain test email entry");

$resetCode = generateVerificationCode();
sendPasswordResetEmail('dean@as.rjt.ac.lk', $resetCode, 'Prof. Dean');
$emailLogContent = file_get_contents(__DIR__ . '/logs/email_verifications.log');
assert(str_contains($emailLogContent, 'dean@as.rjt.ac.lk'), "Email verification log must contain dean@as.rjt.ac.lk");
echo "  Password reset OTP code logged successfully to logs/email_verifications.log\n";
echo "  -> PASSED\n\n";

echo "[TEST 6] Verifying 15-Week Attendance Matrix, 5 Options & 80% Threshold...\n";
$eligibilityService = new EligibilityService();
$sheetData = $eligibilityService->getOfferingAttendanceSheet(1);

assert(!empty($sheetData), "Offering sheet data must not be empty");
echo "  Course: {$sheetData['offering']['course_code']} - {$sheetData['offering']['course_name']}\n";
echo "  Enrolled Students: " . count($sheetData['students']) . "\n";
echo "  Weeks: " . count($sheetData['weeks']) . " (Weeks 1 to 15 with dates)\n";
echo "  Threshold: {$sheetData['offering']['eligibility_threshold']}%\n";

$optionsFound = ['1' => false, '0' => false, 'MC' => false, 'Ex' => false, 'SRC' => false];
foreach ($sheetData['students'] as $st) {
    foreach ($st['weeks'] as $w => $v) {
        if (isset($optionsFound[$v])) $optionsFound[$v] = true;
    }
}
foreach ($optionsFound as $opt => $f) {
    echo "  Option '{$opt}' verified: " . ($f ? 'YES' : 'NO') . "\n";
    assert($f, "Option {$opt} must be demonstrated in attendance records");
}
echo "  -> PASSED\n\n";

echo "[TEST 7] Verifying EIS Excel & CSV Import and Auto Account Creation...\n";
$importer = new EisImporter();
$eisFile = __DIR__ . '/sample_data/EIS_Student_Export_Batch2024.csv';
assert(file_exists($eisFile), "Sample EIS CSV file must exist");
$importResult = $importer->importFile($eisFile, 1, 2);
echo "  EIS Import Rows Processed: {$importResult['total_rows']}\n";
echo "  EIS Students Created/Updated: {$importResult['imported']}\n";
assert($importResult['total_rows'] >= 10, "Must process at least 10 student rows");

$st1 = $db->query("SELECT * FROM students WHERE reg_number = 'ICT/2024/001'")->fetch();
assert(!empty($st1), "Student ICT/2024/001 must exist");
assert(!empty($st1['nic']), "Student ICT/2024/001 must have NIC number");
$u1 = $db->query("SELECT * FROM users WHERE email = 'ict2024001@as.rjt.ac.lk'")->fetch();
assert(!empty($u1), "User account ict2024001@as.rjt.ac.lk must be auto-created");
assert(password_verify($st1['nic'], $u1['password_hash']), "Auto-created account password must be the student's NIC number");
echo "  Auto-created user for ICT/2024/001 with password = NIC ({$st1['nic']}): VERIFIED\n";
echo "  -> PASSED\n\n";

echo "[TEST 8] Verifying Profile Customization Schema & Updates...\n";
$userCols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
assert(in_array('avatar', $userCols, true), "users table must contain avatar column");
assert(in_array('description', $userCols, true), "users table must contain description column");
assert(in_array('password_reset_code', $userCols, true), "users table must contain password_reset_code column");

$studentCols = $db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
assert(in_array('nic', $studentCols, true), "students table must contain nic column");
echo "  Avatar, description, password_reset_code, and nic columns: VERIFIED\n";
echo "  -> PASSED\n\n";

echo "[TEST 9] Verifying Registration Strictly Restricted to Academic Staff...\n";
$registerContent = file_get_contents(__DIR__ . '/public/register.php');
assert(!str_contains($registerContent, 'value="hod"'), "register.php must NOT have hod option");
assert(!str_contains($registerContent, 'value="dean"'), "register.php must NOT have dean option");
assert(!str_contains($registerContent, 'value="ar"'), "register.php must NOT have ar option");
assert(str_contains($registerContent, 'value="lecturer"'), "register.php must have lecturer option");
assert(str_contains($registerContent, 'value="demonstrator"'), "register.php must have demonstrator option");
assert(str_contains($registerContent, 'value="visiting"'), "register.php must have visiting option");
assert(!str_contains($registerContent, 'Verify Pending Email'), "register.php must NOT contain Verify Pending Email link");
echo "  Registration restricted to Academic Staff (Lecturers / Demonstrator / Visiting): VERIFIED\n";
echo "  -> PASSED\n\n";

echo "[TEST 10] Verifying No Standalone Email Verification Buttons in Navigation & Layouts...\n";
$headerContent = file_get_contents(__DIR__ . '/public/layout/header.php');
assert(!str_contains($headerContent, 'verify_email.php'), "header.php must NOT contain any verify_email.php button/link");

$footerContent = file_get_contents(__DIR__ . '/public/layout/footer.php');
assert(!str_contains($footerContent, 'verify_email.php'), "footer.php must NOT contain any verify_email.php button/link");

$indexContent = file_get_contents(__DIR__ . '/public/index.php');
assert(!str_contains($indexContent, '<a href="verify_email.php"'), "index.php must NOT contain standalone Verify Email link");

$profileContent = file_get_contents(__DIR__ . '/public/profile.php');
assert(!str_contains($profileContent, 'name="request_password_otp"'), "profile.php must NOT contain separate Request OTP button");
echo "  No standalone Verify Email buttons in header, footer, index, or profile: VERIFIED\n";
echo "  -> PASSED\n\n";

echo "[TEST 11] Verifying Theme Color (#800000 Maroon) & Security Information Removal...\n";
$cssContent = file_get_contents(__DIR__ . '/public/assets/css/style.css');
assert(str_contains($cssContent, '--primary: #800000;'), "style.css must have --primary: #800000 (Maroon)");

assert(!str_contains($indexContent, 'initial password is NIC'), "index.php must NOT show 'initial password is NIC'");
assert(!str_contains($indexContent, 'Students: <code>regno@as.rjt.ac.lk'), "index.php must NOT show 'Students: regno@as.rjt.ac.lk'");
assert(!str_contains($indexContent, 'localhost:8000'), "index.php must NOT show 'localhost:8000'");
assert(!str_contains($headerContent, 'localhost:8000'), "header.php must NOT show 'localhost:8000'");
assert(!str_contains($footerContent, 'localhost:8000'), "footer.php must NOT show 'localhost:8000'");
assert(!str_contains($headerContent, 'institutional-topbar'), "header.php must NOT contain institutional-topbar");
assert(!str_contains($footerContent, 'System Online'), "footer.php must NOT contain System Online badge");
$logoXml = simplexml_load_file(__DIR__ . '/public/assets/images/rusl_logo_white.svg');
assert($logoXml !== false, "rusl_logo_white.svg must be valid XML");
echo "  Maroon theme (#800000), topbar/footer cleanup, and valid RUSL logo: VERIFIED\n";
echo "  -> PASSED\n\n";

echo "[TEST 12] Verifying 'Add Course' Option Exclusively for HoD (Not for Dean or AR)...\n";
$hodDashContent = file_get_contents(__DIR__ . '/public/hod_dashboard.php');
assert(str_contains($hodDashContent, "if (\$userRole === 'hod')"), "hod_dashboard.php must check for hod role before rendering Add Course button");

$manageCoursesContent = file_get_contents(__DIR__ . '/public/manage_courses.php');
assert(str_contains($manageCoursesContent, "\$isHod = (\$currentRole === 'hod');"), "manage_courses.php must define isHod strictly for hod role");
assert(str_contains($manageCoursesContent, "if (!\$isHod)"), "manage_courses.php must protect create_course handler for hod only");
assert(str_contains($manageCoursesContent, "<?php if (\$isHod): ?>"), "manage_courses.php must only render Add New Course button & modal for HoD");
echo "  'Add Course' option restricted strictly to Head of Department (HoD): VERIFIED\n";
echo "  -> PASSED\n\n";

echo "=== ALL 12 COMPREHENSIVE TESTS PASSED WITH 100% SUCCESS FOR RAJARATA UNIVERSITY SAMS! ===\n";
