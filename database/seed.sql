
USE `sams_db`;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('default_eligibility_threshold', '80.00', 'Default required attendance percentage for exam eligibility'),
('department_name', 'Department of Computing', 'Department name'),
('faculty_name', 'Faculty of Applied Sciences (FAS)', 'Faculty name'),
('university_name', 'Rajarata University of Sri Lanka (RUSL)', 'University name'),
('institution_email_domain', 'as.rjt.ac.lk', 'Institutional email domain for staff and students (as.rjt.ac.lk)'),
('official_system_url', 'http://localhost:8000', 'Local Development portal URL'),
('mc_submission_limit_days', '14', 'Maximum days allowed to submit medical certificate from absence date')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `academic_batches` (`id`, `batch_name`, `batch_code`, `year_of_study`, `academic_year`, `is_active`) VALUES
(1, 'Batch 2023/2024 (1st Year)', '2023/2024', 1, '2025/2026', 1),
(2, 'Batch 2022/2023 (2nd Year)', '2022/2023', 2, '2025/2026', 1),
(3, 'Batch 2021/2022 (3rd Year)', '2021/2022', 3, '2025/2026', 1),
(4, 'Batch 2020/2021 (4th Year)', '2020/2021', 4, '2025/2026', 1)
ON DUPLICATE KEY UPDATE `batch_name` = VALUES(`batch_name`);

INSERT INTO `semesters` (`id`, `semester_number`, `academic_year`, `is_current`, `start_date`, `end_date`) VALUES
(1, 1, '2025/2026', 1, '2026-07-06', '2026-10-23'),
(2, 2, '2025/2026', 0, '2026-11-09', '2027-03-05')
ON DUPLICATE KEY UPDATE `is_current` = VALUES(`is_current`);

INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `role`, `staff_id`, `is_external_lecturer`, `student_id`, `is_email_verified`, `description`) VALUES
(1, 'dean@as.rjt.ac.lk', '$2y$10$A4OMT.53UIQhamTOcWR0uOo1D.le7nEQCj8bgQkVtDqmkipXKsyCe', 'Prof. Dean', 'dean', 'STF-FAS-001', 0, NULL, 1, 'Dean, Faculty of Applied Sciences (FAS), Rajarata University of Sri Lanka.'),
(2, 'kule@as.rjt.ac.lk', '$2y$10$KOQjhrdCW/HMpbQRhHOdPeUj8EVZDZzRhMFnQYsuypATe3a/Fiegu', 'Prof. Kule', 'hod', 'STF-DOC-001', 0, NULL, 1, 'Head of Department (HoD), Department of Computing, Faculty of Applied Sciences.'),
(3, 'ar@as.rjt.ac.lk', '$2y$10$KOQjhrdCW/HMpbQRhHOdPeUj8EVZDZzRhMFnQYsuypATe3a/Fiegu', 'Assistant Registrar (AR)', 'ar', 'STF-FAS-AR', 0, NULL, 1, 'Assistant Registrar, Examination and Student Records, Faculty of Applied Sciences.'),
(4, 'chamikara@as.rjt.ac.lk', '$2y$10$PhAFf9uZ.mv/HTUsla5aTe61bCa0phlKxVfJDQlUHH7/pz57R34ce', 'Dr. A.W.S.S. Chamikara', 'lecturer', 'STF-DOC-012', 0, NULL, 1, 'Senior Lecturer in Computer Science & Coordinating Lecturer for DBMS.'),
(5, 'perera@as.rjt.ac.lk', '$2y$10$PhAFf9uZ.mv/HTUsla5aTe61bCa0phlKxVfJDQlUHH7/pz57R34ce', 'Dr. D.N. Perera', 'lecturer', 'STF-DOC-018', 0, NULL, 1, 'Senior Lecturer & Practical Course Coordinator.'),
(6, 'silva@as.rjt.ac.lk', '$2y$10$PhAFf9uZ.mv/HTUsla5aTe61bCa0phlKxVfJDQlUHH7/pz57R34ce', 'Mr. Rohan Silva', 'lecturer', 'EXT-DOC-005', 1, NULL, 1, 'Visiting Industry Academic in Software Engineering.')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `email` = VALUES(`email`), `is_email_verified` = VALUES(`is_email_verified`);

INSERT INTO `students` (`id`, `reg_number`, `index_number`, `nic`, `name`, `gender`, `title`, `batch_id`, `is_mahapola`, `is_bursary`, `email`) VALUES
(1, 'ICT/2024/001', 'ICT2024001', '200315401234', 'Sunimal Perera', 'Male', 'Mr', 1, 1, 0, 'ict2024001@as.rjt.ac.lk'),
(2, 'ICT/2024/002', 'ICT2024002', '200368204567', 'Nisansala Silva', 'Female', 'Mrs', 1, 0, 1, 'ict2024002@as.rjt.ac.lk'),
(3, 'ICT/2024/003', 'ICT2024003', '200324109876', 'Kasun Fernando', 'Male', 'Mr', 1, 1, 0, 'ict2024003@as.rjt.ac.lk'),
(4, 'ICT/2024/004', 'ICT2024004', '200371306543', 'Dinithi Jayawardena', 'Female', 'Mrs', 1, 0, 0, 'ict2024004@as.rjt.ac.lk'),
(5, 'ICT/2024/005', 'ICT2024005', '200330501122', 'Kavinda Bandara', 'Male', 'Mr', 1, 1, 0, 'ict2024005@as.rjt.ac.lk'),
(6, 'ICT/2024/006', 'ICT2024006', '200382403344', 'Sachini Wickramasinghe', 'Female', 'Mrs', 1, 0, 1, 'ict2024006@as.rjt.ac.lk'),
(7, 'ICT/2024/007', 'ICT2024007', '200319805566', 'Nuwan Priyashantha', 'Male', 'Mr', 1, 0, 0, 'ict2024007@as.rjt.ac.lk'),
(8, 'ICT/2024/008', 'ICT2024008', '200365707788', 'Hansani Tharuka', 'Female', 'Mrs', 1, 1, 0, 'ict2024008@as.rjt.ac.lk'),
(9, 'ICT/2024/009', 'ICT2024009', '200328909900', 'Sajith Jayalath', 'Male', 'Mr', 1, 0, 0, 'ict2024009@as.rjt.ac.lk'),
(10, 'ICT/2024/010', 'ICT2024010', '200377601212', 'Dilani Madhushika', 'Female', 'Mrs', 1, 0, 1, 'ict2024010@as.rjt.ac.lk'),
(11, 'ICT/2024/011', 'ICT2024011', '200322303434', 'Chamath Senarath', 'Male', 'Mr', 1, 0, 0, 'ict2024011@as.rjt.ac.lk'),
(12, 'ICT/2024/020', 'ICT2024020', '200388902345', 'Rashmi Anuradha', 'Female', 'Mrs', 1, 1, 0, 'ict2024020@as.rjt.ac.lk')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `email` = VALUES(`email`), `title` = VALUES(`title`), `nic` = VALUES(`nic`);

INSERT INTO `users` (`email`, `password_hash`, `full_name`, `role`, `student_id`, `is_email_verified`, `description`) VALUES
('ict2024001@as.rjt.ac.lk', '$2y$10$SwAKcNZjMzHQgEwQvCKEJu8UKNZjR9lLXprWzWn38BE6XfSiIDhPm', 'Sunimal Perera', 'student', 1, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024002@as.rjt.ac.lk', '$2y$10$ZEmko6cccKb3rFofBKSBse7WNUjkJmwX995XI1N8RUlR0ZprKbk7W', 'Nisansala Silva', 'student', 2, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024003@as.rjt.ac.lk', '$2y$10$hiNIyRBRjgY0Zh.ew222lueArWgktR7EbU.4NWzEkwAJN4Vm9L1bC', 'Kasun Fernando', 'student', 3, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024004@as.rjt.ac.lk', '$2y$10$MtOpytFD972Aof73t56isuoxbE49WJUqArnO5wQDf1ODJ3bX1SIv.', 'Dinithi Jayawardena', 'student', 4, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024005@as.rjt.ac.lk', '$2y$10$PDrVmPS7vtx820IGDUBK6e.4trvFkn2URS3ordNKOW.fJDn9qfZYK', 'Kavinda Bandara', 'student', 5, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024006@as.rjt.ac.lk', '$2y$10$acKxjt31..eP9ZSFiWlvku.KAN7st7tnj2SVnyTC9EIR7wdBBNKuS', 'Sachini Wickramasinghe', 'student', 6, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024007@as.rjt.ac.lk', '$2y$10$NOAWvBPJyMmtWqR1zTOhhuyI8Zja7k0nqG9xxmWFbp8d1xfVxHeMG', 'Nuwan Priyashantha', 'student', 7, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024008@as.rjt.ac.lk', '$2y$10$UalmrYiWAOgzeA7zr0UmhedKDtQagwqR83kQPxZpeRE58cc7Wsmf.', 'Hansani Tharuka', 'student', 8, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024009@as.rjt.ac.lk', '$2y$10$t16d/bxc4xbeyUTbykvSBuJ3/eRnAnEYPlQnf5I0ORCUhyVi/G2BG', 'Sajith Jayalath', 'student', 9, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024010@as.rjt.ac.lk', '$2y$10$1QQf2eIP.4KDzXKhYsdfh.NoI/b/huMWjnlmWe3y7SrvrTX5FWK/e', 'Dilani Madhushika', 'student', 10, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024011@as.rjt.ac.lk', '$2y$10$WLMtoWLMfgxB6chRLKNaWeJSQLdgrGfieiffORGUOS9RuwNeW1ate', 'Chamath Senarath', 'student', 11, 1, '1st Year ICT Undergraduate, Department of Computing.'),
('ict2024020@as.rjt.ac.lk', '$2y$10$6uVh7/aMQIJJ9liuC3pS9elWPWfVy18rbk7hP9F/pnEBEq6aBqmCe', 'Rashmi Anuradha', 'student', 12, 1, '1st Year ICT Undergraduate (Batch 2023/2024), Department of Computing.')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `is_email_verified` = VALUES(`is_email_verified`), `password_hash` = VALUES(`password_hash`);

INSERT INTO `courses` (`id`, `course_code`, `course_name`, `batch_id`, `semester_id`, `has_practical`) VALUES
(1, 'ICT1113', 'Programming Concepts & Applications', 1, 1, 1),
(2, 'ICT1123', 'Database Management Systems', 1, 1, 1),
(3, 'ICT1133', 'Computer Systems & Architecture', 1, 1, 0)
ON DUPLICATE KEY UPDATE `course_name` = VALUES(`course_name`);

INSERT INTO `course_offerings` (`id`, `course_id`, `session_type`, `offering_code`, `display_name`, `eligibility_threshold`, `is_locked`) VALUES
(1, 1, 'lecture', 'ICT1113-LEC', 'ICT1113 - Programming Concepts & Applications (Lecture)', 80.00, 0),
(2, 1, 'practical', 'ICT1113-PRAC', 'ICT1113 - Programming Concepts & Applications (Practical)', 80.00, 0),
(3, 2, 'lecture', 'ICT1123-LEC', 'ICT1123 - Database Management Systems (Lecture)', 80.00, 0),
(4, 2, 'practical', 'ICT1123-PRAC', 'ICT1123 - Database Management Systems (Practical)', 80.00, 0),
(5, 3, 'lecture', 'ICT1133-LEC', 'ICT1133 - Computer Systems & Architecture (Lecture)', 80.00, 0)
ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`);

INSERT INTO `course_lecturers` (`offering_id`, `lecturer_id`, `is_coordinating`, `can_mark_attendance`) VALUES
(1, 2, 1, 1),
(1, 6, 0, 0),
(2, 5, 1, 1),
(3, 4, 1, 1),
(4, 4, 1, 1),
(5, 2, 1, 1)
ON DUPLICATE KEY UPDATE `can_mark_attendance` = VALUES(`can_mark_attendance`);

INSERT INTO `offering_weeks` (`offering_id`, `week_number`, `session_date`, `topic_covered`) VALUES
(1, 1, '2026-07-07', 'Introduction to Programming & C/C++ Syntax'),
(1, 2, '2026-07-14', 'Variables, Data Types and Operators'),
(1, 3, '2026-07-21', 'Control Flow: Conditionals and Loops'),
(1, 4, '2026-07-28', 'Modular Programming & Functions'),
(1, 5, '2026-08-04', 'Arrays and Multi-dimensional Arrays'),
(1, 6, '2026-08-11', 'Strings and String Manipulation Functions'),
(1, 7, '2026-08-18', 'Pointers and Memory Addressing'),
(1, 8, '2026-08-25', 'Mid-Semester Break / Assessment Review'),
(1, 9, '2026-09-01', 'Structures and User-defined Data Types'),
(1, 10, '2026-09-08', 'Dynamic Memory Allocation (malloc, free)'),
(1, 11, '2026-09-15', 'File Input/Output Operations'),
(1, 12, '2026-09-22', 'Introduction to Object-Oriented Principles'),
(1, 13, '2026-09-29', 'Classes, Objects and Encapsulation'),
(1, 14, '2026-10-06', 'Constructors, Destructors and Inheritance'),
(1, 15, '2026-10-13', 'Course Wrap-up & Examination Preparation')
ON DUPLICATE KEY UPDATE `session_date` = VALUES(`session_date`);

INSERT INTO `offering_weeks` (`offering_id`, `week_number`, `session_date`, `topic_covered`) VALUES
(2, 1, '2026-07-09', 'Lab 1: IDE Setup and First C++ Programs'),
(2, 2, '2026-07-16', 'Lab 2: Arithmetic and Logical Expressions'),
(2, 3, '2026-07-23', 'Lab 3: Implementing Decision Control Structures'),
(2, 4, '2026-07-30', 'Lab 4: Loops and Pattern Generation'),
(2, 5, '2026-08-06', 'Lab 5: Writing and Debugging User Functions'),
(2, 6, '2026-08-13', 'Lab 6: Array Manipulations and Searching'),
(2, 7, '2026-08-20', 'Lab 7: Pointer Operations and Array Traversals'),
(2, 8, '2026-08-27', 'Lab Evaluation 1'),
(2, 9, '2026-09-03', 'Lab 8: Struct Implementation for Student Records'),
(2, 10, '2026-09-10', 'Lab 9: Dynamic Memory Allocation Lab Exercises'),
(2, 11, '2026-09-17', 'Lab 10: File Handling: Reading and Writing Files'),
(2, 12, '2026-09-24', 'Lab 11: Creating Classes and Instantiating Objects'),
(2, 13, '2026-10-01', 'Lab 12: Inheritance and Method Overriding Exercises'),
(2, 14, '2026-10-08', 'Lab Evaluation 2 / Final Practical Exam'),
(2, 15, '2026-10-15', 'Final Lab Assessment & Code Review')
ON DUPLICATE KEY UPDATE `session_date` = VALUES(`session_date`);

INSERT INTO `course_enrollments` (`offering_id`, `student_id`, `practical_qualification_status`)
SELECT 1, id, 'pending' FROM `students` WHERE `batch_id` = 1
ON DUPLICATE KEY UPDATE `offering_id` = `offering_id`;

INSERT INTO `course_enrollments` (`offering_id`, `student_id`, `practical_qualification_status`)
SELECT 2, id, 'pending' FROM `students` WHERE `batch_id` = 1
ON DUPLICATE KEY UPDATE `offering_id` = `offering_id`;

INSERT INTO `course_enrollments` (`offering_id`, `student_id`, `practical_qualification_status`)
SELECT 3, id, 'pending' FROM `students` WHERE `batch_id` = 1
ON DUPLICATE KEY UPDATE `offering_id` = `offering_id`;

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 1, 1, '1', 2), (1, 1, 2, '1', 2), (1, 1, 3, '1', 2), (1, 1, 4, '1', 2),
(1, 1, 5, '1', 2), (1, 1, 6, '1', 2), (1, 1, 7, '1', 2), (1, 1, 8, '1', 2),
(1, 1, 9, '1', 2), (1, 1, 10, '1', 2), (1, 1, 11, '1', 2), (1, 1, 12, '1', 2),
(1, 1, 13, '1', 2), (1, 1, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 2, 1, '1', 2), (1, 2, 2, '1', 2), (1, 2, 3, 'MC', 2), (1, 2, 4, '1', 2),
(1, 2, 5, '1', 2), (1, 2, 6, 'SRC', 2), (1, 2, 7, '1', 2), (1, 2, 8, '1', 2),
(1, 2, 9, '1', 2), (1, 2, 10, '1', 2), (1, 2, 11, '1', 2), (1, 2, 12, '1', 2),
(1, 2, 13, '1', 2), (1, 2, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 3, 1, '1', 2), (1, 3, 2, '1', 2), (1, 3, 3, '1', 2), (1, 3, 4, 'Ex', 2),
(1, 3, 5, '1', 2), (1, 3, 6, '1', 2), (1, 3, 7, '1', 2), (1, 3, 8, '1', 2),
(1, 3, 9, '1', 2), (1, 3, 10, '1', 2), (1, 3, 11, '1', 2), (1, 3, 12, '1', 2),
(1, 3, 13, '1', 2), (1, 3, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 4, 1, '1', 2), (1, 4, 2, '1', 2), (1, 4, 3, '1', 2), (1, 4, 4, '0', 2),
(1, 4, 5, '1', 2), (1, 4, 6, '1', 2), (1, 4, 7, '1', 2), (1, 4, 8, '1', 2),
(1, 4, 9, '0', 2), (1, 4, 10, '1', 2), (1, 4, 11, '1', 2), (1, 4, 12, '1', 2),
(1, 4, 13, '1', 2), (1, 4, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 5, 1, '1', 2), (1, 5, 2, '0', 2), (1, 5, 3, '1', 2), (1, 5, 4, '1', 2),
(1, 5, 5, 'MC', 2), (1, 5, 6, '1', 2), (1, 5, 7, '0', 2), (1, 5, 8, '1', 2),
(1, 5, 9, '1', 2), (1, 5, 10, '1', 2), (1, 5, 11, '1', 2), (1, 5, 12, '0', 2),
(1, 5, 13, '1', 2), (1, 5, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 6, 1, '1', 2), (1, 6, 2, '1', 2), (1, 6, 3, '1', 2), (1, 6, 4, '1', 2),
(1, 6, 5, '1', 2), (1, 6, 6, '0', 2), (1, 6, 7, '1', 2), (1, 6, 8, '1', 2),
(1, 6, 9, '1', 2), (1, 6, 10, '1', 2), (1, 6, 11, '1', 2), (1, 6, 12, '1', 2),
(1, 6, 13, '1', 2), (1, 6, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 7, 1, '1', 2), (1, 7, 2, '0', 2), (1, 7, 3, '0', 2), (1, 7, 4, '1', 2),
(1, 7, 5, '0', 2), (1, 7, 6, '1', 2), (1, 7, 7, '0', 2), (1, 7, 8, '1', 2),
(1, 7, 9, '0', 2), (1, 7, 10, '1', 2), (1, 7, 11, '0', 2), (1, 7, 12, '1', 2),
(1, 7, 13, '0', 2), (1, 7, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 8, 1, '1', 2), (1, 8, 2, '1', 2), (1, 8, 3, '1', 2), (1, 8, 4, '1', 2),
(1, 8, 5, '1', 2), (1, 8, 6, '1', 2), (1, 8, 7, '1', 2), (1, 8, 8, '1', 2),
(1, 8, 9, '1', 2), (1, 8, 10, '1', 2), (1, 8, 11, '1', 2), (1, 8, 12, '1', 2),
(1, 8, 13, '1', 2), (1, 8, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 9, 1, '1', 2), (1, 9, 2, '1', 2), (1, 9, 3, '1', 2), (1, 9, 4, '1', 2),
(1, 9, 5, 'Ex', 2), (1, 9, 6, '1', 2), (1, 9, 7, '1', 2), (1, 9, 8, '0', 2),
(1, 9, 9, '1', 2), (1, 9, 10, '1', 2), (1, 9, 11, '1', 2), (1, 9, 12, '1', 2),
(1, 9, 13, '1', 2), (1, 9, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 10, 1, '1', 2), (1, 10, 2, '1', 2), (1, 10, 3, '1', 2), (1, 10, 4, '1', 2),
(1, 10, 5, '1', 2), (1, 10, 6, '1', 2), (1, 10, 7, '1', 2), (1, 10, 8, '1', 2),
(1, 10, 9, '1', 2), (1, 10, 10, '0', 2), (1, 10, 11, '1', 2), (1, 10, 12, '1', 2),
(1, 10, 13, '1', 2), (1, 10, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 11, 1, '1', 2), (1, 11, 2, '1', 2), (1, 11, 3, '0', 2), (1, 11, 4, '1', 2),
(1, 11, 5, '1', 2), (1, 11, 6, '0', 2), (1, 11, 7, '1', 2), (1, 11, 8, '1', 2),
(1, 11, 9, '0', 2), (1, 11, 10, '1', 2), (1, 11, 11, '1', 2), (1, 11, 12, '0', 2),
(1, 11, 13, '1', 2), (1, 11, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(1, 12, 1, '1', 2), (1, 12, 2, '1', 2), (1, 12, 3, '1', 2), (1, 12, 4, '1', 2),
(1, 12, 5, '1', 2), (1, 12, 6, '1', 2), (1, 12, 7, '1', 2), (1, 12, 8, '1', 2),
(1, 12, 9, '1', 2), (1, 12, 10, '1', 2), (1, 12, 11, '1', 2), (1, 12, 12, '1', 2),
(1, 12, 13, '1', 2), (1, 12, 14, '1', 2);

INSERT INTO `attendance_records` (`offering_id`, `student_id`, `week_number`, `status`, `marked_by`) VALUES
(2, 1, 1, '1', 5), (2, 1, 2, '1', 5), (2, 1, 3, '1', 5), (2, 1, 4, '1', 5), (2, 1, 5, '1', 5),
(2, 2, 1, '1', 5), (2, 2, 2, '1', 5), (2, 2, 3, '1', 5), (2, 2, 4, '1', 5), (2, 2, 5, '1', 5),
(2, 5, 1, '0', 5), (2, 5, 2, '0', 5), (2, 5, 3, '1', 5), (2, 5, 4, '0', 5), (2, 5, 5, '1', 5),
(2, 7, 1, '0', 5), (2, 7, 2, '0', 4), (2, 7, 3, '0', 5), (2, 7, 4, '1', 5), (2, 7, 5, '0', 5),
(2, 12, 1, '1', 5), (2, 12, 2, '1', 5), (2, 12, 3, '1', 5), (2, 12, 4, '1', 5), (2, 12, 5, '1', 5);

INSERT INTO `absence_requests` (`id`, `student_id`, `offering_id`, `week_number`, `request_type`, `absence_date`, `submission_date`, `days_to_submission`, `is_within_14_days`, `reason`, `document_path`, `status`, `reviewed_by`, `review_remarks`, `reviewed_at`) VALUES
(1, 2, 1, 3, 'MC', '2026-07-21', '2026-07-28', 7, 1, 'Viral fever with medical advice for rest from Anuradhapura Teaching Hospital.', 'sample_mc_nisansala.pdf', 'approved', 2, 'Valid medical certificate from Government Teaching Hospital submitted on time.', '2026-07-29 10:00:00'),
(2, 3, 1, 4, 'Ex', '2026-07-28', '2026-08-01', 4, 1, 'Representing Rajarata University in Inter-University Badminton Tournament.', 'sample_sports_excuse_kasun.pdf', 'approved', 2, 'Verified with Physical Education Unit letter.', '2026-08-02 11:30:00'),
(3, 5, 1, 5, 'MC', '2026-08-04', '2026-08-25', 21, 0, 'Severe migraine.', 'sample_mc_kavinda.pdf', 'rejected', 2, 'Submitted after 21 days (exceeds 14 days deadline limit).', '2026-08-26 14:00:00'),
(4, 2, 1, 6, 'SRC', '2026-08-11', '2026-08-14', 3, 1, 'Attendance was not recorded due to technical error during session.', 'src_request_letter.pdf', 'approved', 2, 'Approved by Department Student Request Committee.', '2026-08-16 09:15:00');
