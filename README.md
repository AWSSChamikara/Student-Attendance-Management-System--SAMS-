# Student Attendance Management System (SAMS)
### Rajarata University of Sri Lanka (RUSL)
### Faculty of Applied Sciences (FAS) &bull; Department of Computing
### Team Syntax Errors
### Database System Project

---

## 1. University & Department Specifications

- **System Access URL**: **`http://localhost:8000`** (Local Development)
- **University Name**: **Rajarata University of Sri Lanka (RUSL)**
- **Faculty Name**: **Faculty of Applied Sciences (FAS)**
- **Department Name**: **Department of Computing**
- **Student Registration Number Format**: `ICT/YYYY/XXX` (Batch 2023/2024: `ICT/2024/020`)
- **Student Email Format**: `regno@as.rjt.ac.lk` where `regno` is lowercase without slashes (`ict2024020@as.rjt.ac.lk`)
- **Designated Institutional Staff Emails**:
  - **Dean**: `dean@as.rjt.ac.lk`
  - **Head of Department (HoD)**: `kule@as.rjt.ac.lk` (Prof. Kule)
  - **Assistant Registrar (AR)**: `ar@as.rjt.ac.lk`
  - **Lecturers / Academic Staff**: `username@as.rjt.ac.lk` (`chamikara@as.rjt.ac.lk`, `perera@as.rjt.ac.lk`, `silva@as.rjt.ac.lk`)
- **Staff Registration (`register.php`)**: Restricted exclusively to **Academic Staff** (Lecturers, Senior Lecturers, Visiting Lecturers, HoD, AR, Dean). Student accounts are auto-provisioned upon EIS roster import.
- **Email Verification**: **Mandatory email verification** via a 6-digit numeric OTP code or a direct one-click security link before account activation and sign-in.

---

## 2. Academic Architecture

- **Batches**: 4 Batches per year:
  - **Batch 2023/2024 (1st Year)**: Registration numbers formatted as `ICT/2024/XXX`
  - **Batch 2022/2023 (2nd Year)**: Registration numbers formatted as `ICT/2023/XXX`
  - **Batch 2021/2022 (3rd Year)**: Registration numbers formatted as `ICT/2022/XXX`
  - **Batch 2020/2021 (4th Year)**: Registration numbers formatted as `ICT/2021/XXX`
- **Semesters**: 2 Semesters per academic year (Semester 1 & Semester 2).
- **Session Types**:
  - Every course is taught via lectures.
  - Courses with lab components include practical sessions.
  - **Lectures and practical sessions generate separate attendance sheets** and are treated as distinct course records.
  - **The Head of Department (HoD) determines whether or not a student qualifies for examinations based on how the student participated in practical sessions**.

---

## 3. EIS Sheet Import & Auto Student Account Creation

- **Automated Roster Ingestion**: When the HoD imports an EIS Excel or CSV roster file:
  1. The system extracts student registration numbers, index numbers, names, gender, addresses, and **NIC numbers**.
  2. Institutional emails are generated in the format `regno@as.rjt.ac.lk`.
  3. Student accounts are **automatically created** in the system.
  4. The initial password for each auto-created student account is set to their **NIC Number** (`200388902345` for `ICT/2024/020`).
  5. Students are automatically enrolled in all active course offerings for their batch.

---

## 4. User Profile Customization & Password Change with Email Verification

Users across all roles (Dean, HoD, AR, Lecturers, and Students) can customize their profiles and securely manage their passwords:
1. **Edit Profile Details (`profile.php?tab=profile`)**:
   - **Profile Picture (Avatar)**: Upload custom JPG, PNG, or WebP photo avatars with preview.
   - **Full Name**: Update display name across dashboards and attendance matrices.
   - **Bio / Description**: Customize personal role statement and academic summary.
2. **Change Password with 6-Digit OTP Verification (`profile.php?tab=password`)**:
   - User requests a password change OTP.
   - A 6-digit numeric verification code is dispatched to their verified `@as.rjt.ac.lk` email.
   - User inputs their current password, new password, and the 6-digit verification code to confirm the update.
3. **Forgot Password Screen (`forgot_password.php`)**:
   - Unauthenticated users can reset forgotten passwords by entering their `@as.rjt.ac.lk` email and verifying a 6-digit OTP code dispatched to their inbox.

---

## 5. Security: 5-Minute Inactivity Auto Sign-Out

To safeguard student and staff privacy, an automatic session inactivity monitor is enforced across the application:
1. **Inactivity Detection**: Tracks user actions (mouse movements, clicks, keystrokes, scrolling, touch events).
2. **Warning Modal (at 4 min 30 sec)**: Displays a 30-second visual countdown modal giving the user the option to click *"Keep Me Signed In"* or simply move the mouse to resume.
3. **Automatic Sign-Out (at 5 minutes)**: If no activity occurs for 300 seconds, the active session is destroyed and the user is redirected to `index.php` with a security notice.
4. **Backend Enforcement**: Backed by server-side verification (`SESSION_INACTIVITY_TIMEOUT = 300`), ensuring sessions cannot remain active even if the browser tab was suspended.

---

## 6. 5 Attendance Marking Options & Calculation

1. **Present (`1`)**: Student attended the scheduled session.
2. **Absent (`0`)**: Unexcused absence.
3. **Absent due to Illness (`MC`)**: Medical Certificate. Must be submitted within **14 days** from the date of absence.
4. **Absent due to Serious Personal Reason or Sports Event (`Ex`)**: Excused absence. Excuse letter and evidence must be submitted before the eligibility report is released.
5. **Student Request Committee (`SRC`)**: Attendance granted due to valid reason or lecturer recording error. **Counts as present** towards attendance percentage.

$$\text{Attendance Percentage} = \left( \frac{\text{Present Weeks (1) } + \text{Approved SRC Weeks}}{\text{Total Weeks Evaluated}} \right) \times 100\%$$

- **Examination Eligibility**: If $\text{Percentage} \ge 80.00\%$ (or the department-configured threshold), the student is **Eligible**; otherwise, **Not Eligible**.

---

## 7. 15-Week Attendance Sheet Layout & Permanent Locking

### Sheet Columns:
1. `No`
2. `Title` (automatically `'Mr'` for Male, `'Mrs'` for Female based on gender)
3. `Name`
4. `Index Number` (`ICT2024020`)
5. `Registration Number` (`ICT/2024/020`)
6. `Weeks 1 through 15` (with calendar dates indicated above each week)
7. `Total number of weeks present`
8. `Total number of weeks absent`
9. `Number of medical certificates submitted`
10. `Number of excuse letters submitted`
11. `Attendance percentage for each student`
12. `Eligibility status` (Eligible / Not Eligible)
- **Header Displays**: Lecturer in charge, Course Code (`ICT1113`), Course Name, Session Type (Lecture / Practical), Academic Batch, Semester.

### Locking & Exports:
- Obtained after the 14th/15th week.
- **Once the eligibility report is obtained, attendance records cannot be entered or modified**.
- Exportable as a **password-protected uneditable Excel sheet (.xlsx)** or an **official PDF file (.pdf)** with faculty letterheads and signature blocks.

---

## 8. Pre-Configured Demonstration Accounts

All accounts use the `@as.rjt.ac.lk` domain:

| Role | Name | Institutional Email | Default Password | Verification Status |
|---|---|---|---|---|
| **Dean (Faculty of Applied Sciences)** | Prof. Dean (FAS) | `dean@as.rjt.ac.lk` | **`abc123`** | Verified |
| **Head of Department (HoD)** | Prof. Kule | `kule@as.rjt.ac.lk` | **`abc123`** | Verified |
| **Assistant Registrar (AR)** | Assistant Registrar | `ar@as.rjt.ac.lk` | **`abc123`** | Verified |
| **Coordinating Lecturer** | Dr. A.W.S.S. Chamikara | `chamikara@as.rjt.ac.lk` | **`abc123`** | Verified |
| **Lecturer (Practicals)** | Dr. D.N. Perera | `perera@as.rjt.ac.lk` | **`abc123`** | Verified |
| **Visiting Lecturer** | Mr. Rohan Silva | `silva@as.rjt.ac.lk` | **`abc123`** | Verified |
| **Student (ICT/2024/020)** | Mrs. Rashmi Anuradha | `ict2024020@as.rjt.ac.lk` | **`200388902345`** (NIC) | Verified |
| **Student (ICT/2024/001)** | Mr. Sunimal Perera | `ict2024001@as.rjt.ac.lk` | **`200315401234`** (NIC) | Verified |

> **Note on Student Passwords**: When the EIS Excel file is imported by the HoD, each student's login password is automatically set to their **NIC number**. Students can subsequently change their password after logging in via email OTP verification.

---

## 9. How to Launch and Access the System

### Access URL:
👉 **`http://localhost:8000`**

### Starting the Server:
1. Double-click `start_server.bat` or run:
   ```cmd
   start_server.bat
   ```
2. Open **`http://localhost:8000`** in your browser.

### Run Automated Verification Suite:
```cmd
php test_system.php
```
