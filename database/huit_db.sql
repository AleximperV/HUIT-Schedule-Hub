-- HUIT Schedule Hub - Sample Database Dump (huit_db)
-- Import file này vào MySQL / HeidiSQL hoặc chạy: php database/seed.php

CREATE DATABASE IF NOT EXISTS `huit_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `huit_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `schedules`;
DROP TABLE IF EXISTS `teacher_availabilities`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `branches`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `branches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `branch_name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `teachers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(25) NOT NULL,
    `expertise` VARCHAR(200) NOT NULL COMMENT 'Chuyên môn chính',
    `password_hash` VARCHAR(255) DEFAULT NULL,
    `portal_token` VARCHAR(64) DEFAULT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT UNSIGNED NOT NULL,
    `branch_id` INT UNSIGNED NOT NULL,
    `subject_name` VARCHAR(180) NOT NULL COMMENT 'Tên học phần',
    `room` VARCHAR(50) NOT NULL COMMENT 'Phòng học',
    `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '2=Thứ 2 ... 7=Thứ 7, 8=Chủ nhật',
    `shift_or_periods` VARCHAR(80) NOT NULL COMMENT 'Buổi hoặc Tiết học cụ thể',
    `start_date` DATE NOT NULL DEFAULT '2026-09-07' COMMENT 'Ngày bắt đầu áp dụng',
    `end_date` DATE NOT NULL DEFAULT '2026-12-20' COMMENT 'Ngày kết thúc áp dụng',
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_schedules_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_schedules_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    INDEX `idx_teacher_day` (`teacher_id`, `day_of_week`),
    INDEX `idx_branch_day` (`branch_id`, `day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `teacher_availabilities` (
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

INSERT INTO `branches` (`id`, `branch_name`, `address`) VALUES
(1, 'Cơ sở chính Tân Phú', '140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM'),
(2, 'Cơ sở Phòng máy Chế Lan Viên', '31 Chế Lan Viên, P. Tây Thạnh, Q. Tân Phú, TP.HCM'),
(3, 'Trung tâm Thí nghiệm - Thực hành', '247 Tân Kỳ Tân Quý, Q. Tân Phú, TP.HCM'),
(4, 'Trung tâm Liên kết Đào tạo', '181 Lê Đức Thọ, Q. Gò Vấp, TP.HCM');

INSERT INTO `teachers` (`id`, `full_name`, `email`, `phone`, `expertise`, `portal_token`) VALUES
(1, 'TS. Nguyễn Minh Tuấn', 'gv1@huit.edu.vn', '0901234501', 'Công nghệ thông tin - Lập trình Web & PHP', 'huit_portal_gv1_token'),
(2, 'ThS. Trần Thị Lan Anh', 'gv2@huit.edu.vn', '0901234502', 'Hệ thống thông tin - Cơ sở dữ liệu', 'huit_portal_gv2_token'),
(3, 'ThS. Lê Hoàng Nam', 'gv3@huit.edu.vn', '0901234503', 'Trí tuệ nhân tạo & Khoa học dữ liệu', 'huit_portal_gv3_token'),
(4, 'TS. Phạm Quốc Bảo', 'gv4@huit.edu.vn', '0901234504', 'Mạng máy tính & An toàn thông tin', 'huit_portal_gv4_token'),
(5, 'ThS. Võ Thị Thu Hà', 'gv5@huit.edu.vn', '0901234505', 'Công nghệ thực phẩm & Vi sinh', 'huit_portal_gv5_token');

INSERT INTO `teacher_availabilities` (`teacher_id`, `day_of_week`, `shift_or_periods`, `preferred_branch_id`, `note`) VALUES
(1, 2, 'Sáng (Tiết 4-6)', 1, 'Ưu tiên dạy cùng cơ sở Tân Phú'),
(1, 4, 'Sáng (Tiết 1-3)', 2, 'Phòng máy thực hành'),
(2, 3, 'Chiều (Tiết 7-9)', 2, 'Phòng máy Chế Lan Viên'),
(3, 5, 'Sáng (Tiết 1-3)', 1, 'Linh hoạt'),
(4, 6, 'Chiều (Tiết 10-12)', 3, 'Phòng Lab mạng');

INSERT INTO `schedules` (`teacher_id`, `branch_id`, `subject_name`, `room`, `day_of_week`, `shift_or_periods`, `notes`) VALUES
(1, 1, 'Lập trình Web với PHP & MySQL', 'A201', 2, 'Sáng (Tiết 1-3)', 'Lớp 14DHTH01'),
(1, 2, 'Phát triển ứng dụng mã nguồn mở', 'PM01', 4, 'Sáng (Tiết 4-6)', 'Thực hành phòng máy'),
(2, 1, 'Hệ quản trị Cơ sở dữ liệu MySQL', 'B102', 3, 'Sáng (Tiết 1-3)', 'Lớp 14DHTH02'),
(3, 1, 'Nhập môn Trí tuệ nhân tạo', 'A304', 5, 'Sáng (Tiết 4-6)', 'Lớp 14DHTH03'),
(4, 3, 'Mạng máy tính căn bản', 'LAB-01', 6, 'Chiều (Tiết 7-9)', 'Thực hành Lab'),
(5, 3, 'Vi sinh vật học thực phẩm', 'TN-03', 2, 'Chiều (Tiết 7-9)', 'Thí nghiệm cơ bản');
