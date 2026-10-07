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
