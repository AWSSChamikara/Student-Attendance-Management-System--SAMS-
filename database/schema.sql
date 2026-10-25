
CREATE DATABASE IF NOT EXISTS `sams_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sams_db`;

CREATE TABLE IF NOT EXISTS `academic_batches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_name` VARCHAR(100) NOT NULL,
    `batch_code` VARCHAR(20) NOT NULL UNIQUE,
    `year_of_study` INT NOT NULL,
    `academic_year` VARCHAR(30) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `semesters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `semester_number` INT NOT NULL,
    `academic_year` VARCHAR(30) NOT NULL,
    `is_current` TINYINT(1) DEFAULT 1,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_semester_year` (`semester_number`, `academic_year`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `role` ENUM('hod', 'lecturer', 'student', 'dean', 'ar') NOT NULL,
    `staff_id` VARCHAR(50) NULL,
    `is_external_lecturer` TINYINT(1) DEFAULT 0,
    `student_id` INT NULL,
    `is_email_verified` TINYINT(1) DEFAULT 0,
    `verification_code` VARCHAR(10) NULL,
    `verification_token` VARCHAR(64) NULL,
    `verification_expires_at` DATETIME NULL,
    `avatar` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `password_reset_code` VARCHAR(10) NULL,
    `password_reset_expires_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reg_number` VARCHAR(50) NOT NULL UNIQUE,
    `index_number` VARCHAR(50) NOT NULL UNIQUE,
    `nic` VARCHAR(30) NULL,
    `name` VARCHAR(255) NOT NULL,
    `gender` ENUM('Male', 'Female') NOT NULL,
    `title` VARCHAR(10) NOT NULL,
    `batch_id` INT NOT NULL,
    `is_mahapola` TINYINT(1) DEFAULT 0,
    `is_bursary` TINYINT(1) DEFAULT 0,
    `email` VARCHAR(191) NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_student_batch` FOREIGN KEY (`batch_id`) REFERENCES `academic_batches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

ALTER TABLE `users` ADD CONSTRAINT `fk_user_student`
FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(30) NOT NULL UNIQUE,
    `course_name` VARCHAR(255) NOT NULL,
    `batch_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `has_practical` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_course_batch` FOREIGN KEY (`batch_id`) REFERENCES `academic_batches` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_course_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `course_offerings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL,
    `session_type` ENUM('lecture', 'practical') NOT NULL,
    `offering_code` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(255) NOT NULL,
    `eligibility_threshold` DECIMAL(5,2) DEFAULT 80.00,
    `is_locked` TINYINT(1) DEFAULT 0,
    `locked_at` DATETIME NULL,
    `locked_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_offering_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_offering_locked_by` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `course_lecturers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `offering_id` INT NOT NULL,
    `lecturer_id` INT NOT NULL,
    `is_coordinating` TINYINT(1) DEFAULT 0,
    `can_mark_attendance` TINYINT(1) DEFAULT 1,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_offering_lecturer` (`offering_id`, `lecturer_id`),
    CONSTRAINT `fk_cl_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cl_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `offering_weeks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `offering_id` INT NOT NULL,
    `week_number` INT NOT NULL,
    `session_date` DATE NULL,
    `topic_covered` VARCHAR(255) NULL,
    UNIQUE KEY `uk_offering_week` (`offering_id`, `week_number`),
    CONSTRAINT `fk_ow_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `course_enrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `offering_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `practical_qualification_status` ENUM('pending', 'qualified', 'disqualified') DEFAULT 'pending',
    `hod_qualification_remarks` TEXT NULL,
    `hod_evaluated_at` DATETIME NULL,
    `hod_evaluated_by` INT NULL,
    UNIQUE KEY `uk_offering_student` (`offering_id`, `student_id`),
    CONSTRAINT `fk_ce_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ce_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ce_hod` FOREIGN KEY (`hod_evaluated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `attendance_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `offering_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `week_number` INT NOT NULL,
    `status` ENUM('1', '0', 'MC', 'Ex', 'SRC') NOT NULL DEFAULT '0',
    `marked_by` INT NOT NULL,
    `marked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_attendance_entry` (`offering_id`, `student_id`, `week_number`),
    CONSTRAINT `fk_ar_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ar_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ar_marked_by` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `absence_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `offering_id` INT NOT NULL,
    `week_number` INT NOT NULL,
    `request_type` ENUM('MC', 'Ex', 'SRC') NOT NULL,
    `absence_date` DATE NOT NULL,
    `submission_date` DATE NOT NULL,
    `days_to_submission` INT NOT NULL,
    `is_within_14_days` TINYINT(1) DEFAULT 1,
    `reason` TEXT NOT NULL,
    `document_path` VARCHAR(255) NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `reviewed_by` INT NULL,
    `review_remarks` TEXT NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_req_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_req_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_req_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `eis_import_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `file_name` VARCHAR(255) NOT NULL,
    `batch_id` INT NOT NULL,
    `total_rows` INT DEFAULT 0,
    `imported_count` INT DEFAULT 0,
    `skipped_count` INT DEFAULT 0,
    `uploaded_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_eis_batch` FOREIGN KEY (`batch_id`) REFERENCES `academic_batches` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_eis_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;
