-- HUIT Schedule Hub Database Schema
-- Database: huit_db

CREATE DATABASE IF NOT EXISTS `huit_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `huit_db`;

CREATE TABLE IF NOT EXISTS `branches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `branch_name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(25) NOT NULL,
    `expertise` VARCHAR(200) NOT NULL COMMENT 'Chuyên môn chính',
    `password_hash` VARCHAR(255) DEFAULT NULL,
    `portal_token` VARCHAR(64) DEFAULT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT UNSIGNED NOT NULL,
    `branch_id` INT UNSIGNED NOT NULL,
    `subject_name` VARCHAR(180) NOT NULL COMMENT 'Tên học phần',
    `room` VARCHAR(50) NOT NULL COMMENT 'Phòng học',
    `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '2=Thứ 2 ... 7=Thứ 7, 8=Chủ nhật',
    `shift_or_periods` VARCHAR(80) NOT NULL COMMENT 'Buổi hoặc Tiết học cụ thể',
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_schedules_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_schedules_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    INDEX `idx_teacher_day` (`teacher_id`, `day_of_week`),
    INDEX `idx_branch_day` (`branch_id`, `day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teacher_availabilities` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT UNSIGNED NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '2=Thứ 2 ... 7=Thứ 7, 8=Chủ nhật',
    `shift_or_periods` VARCHAR(80) NOT NULL COMMENT 'Khung tiết/buổi trống',
    `preferred_branch_id` INT UNSIGNED DEFAULT NULL,
    `note` VARCHAR(200) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_avail_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_avail_branch` FOREIGN KEY (`preferred_branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uq_teacher_slot` (`teacher_id`, `day_of_week`, `shift_or_periods`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
