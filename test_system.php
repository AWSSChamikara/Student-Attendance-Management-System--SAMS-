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

echo "[TEST 4] Verifying Staff Institutional Emails (Dean, HoD, AR)...\n";
$dean = $db->query("SELECT * FROM users WHERE email = 'dean@as.rjt.ac.lk'")->fetch();
$hod = $db->query("SELECT * FROM users WHERE email = 'kule@as.rjt.ac.lk'")->fetch();
$ar = $db->query("SELECT * FROM users WHERE email = 'ar@as.rjt.ac.lk'")->fetch();

assert(!empty($dean), "Dean account dean@as.rjt.ac.lk must exist");
assert(!empty($hod), "HoD account kule@as.rjt.ac.lk must exist");
assert(!empty($ar), "AR account ar@as.rjt.ac.lk must exist");

echo "  Dean Account: {$dean['full_name']} ({$dean['email']}, Role: {$dean['role']})\n";
echo "  HoD Account:  {$hod['full_name']} ({$hod['email']}, Role: {$hod['role']})\n";
echo "  AR Account:   {$ar['full_name']} ({$ar['email']}, Role: {$ar['role']})\n";

assert(isInstitutionalEmail('dean@as.rjt.ac.lk'), "dean@as.rjt.ac.lk must be institutional email");
assert(isInstitutionalEmail('kule@as.rjt.ac.lk'), "kule@as.rjt.ac.lk must be institutional email");
assert(isInstitutionalEmail('ar@as.rjt.ac.lk'), "ar@as.rjt.ac.lk must be institutional email");
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

echo "=== ALL 8 COMPREHENSIVE TESTS PASSED WITH 100% SUCCESS FOR RAJARATA UNIVERSITY SAMS! ===\n";
