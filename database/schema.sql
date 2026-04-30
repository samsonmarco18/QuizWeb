CREATE DATABASE IF NOT EXISTS `quizweb`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `quizweb`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL,
    `created_at` VARCHAR(40) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `classrooms` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `teacher_id` INT NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `subject` VARCHAR(190) NOT NULL,
    `description` TEXT NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `student_ids` LONGTEXT NOT NULL,
    `quizzes` LONGTEXT NOT NULL,
    `announcements` LONGTEXT NOT NULL,
    `created_at` VARCHAR(40) NOT NULL,
    `updated_at` VARCHAR(40) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_classrooms_code` (`code`),
    KEY `idx_classrooms_teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attempts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `student_id` INT NOT NULL,
    `classroom_id` INT NOT NULL,
    `quiz_id` INT NOT NULL,
    `quiz_title` VARCHAR(255) NOT NULL,
    `game_type` VARCHAR(100) NOT NULL,
    `answers` LONGTEXT NOT NULL,
    `score` INT NOT NULL,
    `max_score` INT NOT NULL,
    `elapsed_seconds` INT NOT NULL,
    `played_at` VARCHAR(40) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_attempts_student_id` (`student_id`),
    KEY `idx_attempts_classroom_id` (`classroom_id`),
    KEY `idx_attempts_quiz_id` (`quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
