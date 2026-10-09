-- CHALK: complete local MySQL / XAMPP MariaDB setup file.
-- Create/select a NEW database named quizweb in phpMyAdmin, then import this file.
-- No DROP statements; repeated imports preserve existing records and credentials.
-- JSON values are supplied by the app (no version-specific JSON defaults).
-- This file defines the current structure; it does not upgrade older MySQL schemas.
-- PostgreSQL production continues to use database/schema.sql.

-- USERS: accounts, roles, status, and profile
CREATE TABLE IF NOT EXISTS users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    account_status VARCHAR(20) NOT NULL DEFAULT 'active',
    profile JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- CLASSROOMS: quizzes, enrollments, announcements, and chat are JSON
CREATE TABLE IF NOT EXISTS classrooms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    teacher_id BIGINT NOT NULL,
    name VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    student_ids JSON NOT NULL,
    quizzes JSON NOT NULL,
    announcements JSON NOT NULL,
    chat_messages JSON NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    INDEX idx_classrooms_teacher_id (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- ATTEMPTS: scores, submitted answers, and historical quiz snapshots
CREATE TABLE IF NOT EXISTS attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT NOT NULL,
    classroom_id BIGINT NOT NULL,
    quiz_id BIGINT NOT NULL,
    quiz_title VARCHAR(255) NOT NULL,
    game_type VARCHAR(100) NOT NULL,
    answers JSON NOT NULL,
    score INT NOT NULL,
    max_score INT NOT NULL,
    elapsed_seconds INT NOT NULL,
    played_at VARCHAR(40) NOT NULL,
    INDEX idx_attempts_student_id (student_id),
    INDEX idx_attempts_classroom_id (classroom_id),
    INDEX idx_attempts_quiz_id (quiz_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;


-- AUDIT LOGS: account and operational events
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT,
    target_id BIGINT,
    action VARCHAR(80) NOT NULL,
    occurred_at VARCHAR(40) NOT NULL,
    INDEX idx_audit_logs_action (action, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- GRADEBOOKS: classroom grading configuration and published results
CREATE TABLE IF NOT EXISTS gradebooks (
    classroom_id BIGINT PRIMARY KEY,
    data JSON NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    CONSTRAINT fk_gradebooks_classroom_id FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- GRADE CHANGES: grading audit history
CREATE TABLE IF NOT EXISTS grade_changes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    classroom_id BIGINT NOT NULL,
    student_id BIGINT,
    item_id VARCHAR(80),
    actor_id BIGINT NOT NULL,
    action VARCHAR(80) NOT NULL,
    previous_value JSON,
    new_value JSON,
    created_at VARCHAR(40) NOT NULL,
    INDEX idx_grade_changes_classroom (classroom_id, id),
    CONSTRAINT fk_grade_changes_classroom_id FOREIGN KEY (classroom_id) REFERENCES gradebooks(classroom_id) ON DELETE RESTRICT,
    CONSTRAINT fk_grade_changes_student_id FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_grade_changes_actor_id FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- LONGTEXT accommodates base64-encoded attachments up to 10 MB.
CREATE TABLE IF NOT EXISTS uploaded_files (
    scope VARCHAR(20) NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    data LONGTEXT NOT NULL,
    PRIMARY KEY (scope, stored_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS classroom_academics (classroom_id BIGINT PRIMARY KEY, data LONGTEXT NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS academic_settings (id VARCHAR(40) PRIMARY KEY, data LONGTEXT NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS quiz_runs (
    run_token VARCHAR(48) PRIMARY KEY, classroom_id BIGINT NOT NULL, quiz_id BIGINT NOT NULL,
    student_id BIGINT NOT NULL, started_at VARCHAR(40) NOT NULL, last_seen VARCHAR(40) NOT NULL,
    completed_at VARCHAR(40), attempt_id BIGINT,
    INDEX idx_quiz_runs_classroom (classroom_id, quiz_id, student_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS academic_reminders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY, classroom_id BIGINT NOT NULL,
    quiz_id BIGINT NOT NULL, student_id BIGINT NOT NULL, teacher_id BIGINT NOT NULL,
    body TEXT NOT NULL, created_at VARCHAR(40) NOT NULL,
    INDEX idx_academic_reminders_student (classroom_id, student_id)
) ENGINE=InnoDB;

-- DEMO ADMIN: explicit import only
-- Same sample administrator as PostgreSQL: admin@chalk.local / ChalkAdmin!2026
-- Existing emails, passwords, roles, and status are preserved.
START TRANSACTION;
INSERT INTO users (name, email, password, role, created_at, profile, account_status)
SELECT 'Administrator', 'admin@chalk.local',
       '$2y$10$x/Z24ak.NUHsnwh8n9A/V.1igvVSEOp.HREJmUwIJvWF/btZ3a4T.',
       'admin', CURRENT_TIMESTAMP, '{}', 'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@chalk.local');
SET @created_admin_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
INSERT INTO audit_logs (actor_id, target_id, action, occurred_at)
SELECT @created_admin_id, @created_admin_id, 'admin_created', CURRENT_TIMESTAMP
WHERE @created_admin_id IS NOT NULL;
COMMIT;

-- TEST CLASSROOM: one teacher and two enrolled students, explicit import only.
-- All three accounts use password ChalkAdmin!2026. Reimports preserve credentials.
START TRANSACTION;
INSERT INTO users (name, email, password, role, created_at, profile, account_status)
SELECT seed.name, seed.email,
       '$2y$10$x/Z24ak.NUHsnwh8n9A/V.1igvVSEOp.HREJmUwIJvWF/btZ3a4T.',
       seed.role, CURRENT_TIMESTAMP, '{}', 'active'
FROM (
    SELECT 'Test Teacher' AS name, 'teacher@chalk.test' AS email, 'teacher' AS role
    UNION ALL SELECT 'Test Student One', 'student1@chalk.test', 'student'
    UNION ALL SELECT 'Test Student Two', 'student2@chalk.test', 'student'
) seed
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = seed.email);

INSERT INTO classrooms (teacher_id, name, subject, description, code,
                        student_ids, quizzes, announcements, chat_messages, created_at, updated_at)
SELECT teacher.id, 'Test Classroom', 'General Education',
       'Shared classroom for testing teacher and student logins.', 'TESTCLASS',
       JSON_ARRAY(student1.id, student2.id), '[]', '[]', '[]', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM users teacher, users student1, users student2
WHERE teacher.email = 'teacher@chalk.test' AND teacher.role = 'teacher'
  AND student1.email = 'student1@chalk.test' AND student1.role = 'student'
  AND student2.email = 'student2@chalk.test' AND student2.role = 'student'
  AND NOT EXISTS (SELECT 1 FROM classrooms WHERE code = 'TESTCLASS');
COMMIT;

-- Give the test classroom an editable Balanced grading structure.
INSERT INTO gradebooks (classroom_id, data, updated_at)
SELECT classroom.id, '{"config":{"method":"weighted_categories","category_method":"points","categories":[{"id":"quiz","name":"Quizzes","weight":30},{"id":"work","name":"Assignments / Projects","weight":30},{"id":"exam","name":"Exams","weight":40}],"scale":[{"min":0,"label":"Below passing"},{"min":75,"label":"Passed"},{"min":90,"label":"Excellent"}],"passing":75,"missing_policy":"exclude","periods":[{"id":"prelim","name":"Prelim","weight":30},{"id":"midterm","name":"Midterm","weight":30},{"id":"finals","name":"Finals","weight":40}]},"items":{},"scores":{},"overrides":{},"revision":0,"next_item_id":1,"published":null}', CURRENT_TIMESTAMP
FROM classrooms classroom JOIN users teacher ON teacher.id = classroom.teacher_id
WHERE classroom.code = 'TESTCLASS' AND teacher.email = 'teacher@chalk.test' AND teacher.role = 'teacher'
  AND NOT EXISTS (SELECT 1 FROM gradebooks WHERE classroom_id = classroom.id);

INSERT INTO classroom_academics (classroom_id, data)
SELECT classroom.id, JSON_OBJECT('subject_code', 'GEN101', 'section', 'Test Section',
    'academic_year', CONCAT(YEAR(CURRENT_DATE), '–', YEAR(CURRENT_DATE) + 1),
    'semester', 'First Semester', 'credit_units', 3, 'required', JSON_EXTRACT('true', '$'))
FROM classrooms classroom JOIN users teacher ON teacher.id = classroom.teacher_id
WHERE classroom.code = 'TESTCLASS' AND teacher.email = 'teacher@chalk.test'
  AND NOT EXISTS (SELECT 1 FROM classroom_academics WHERE classroom_id = classroom.id);
