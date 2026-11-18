# HUIT Schedule Hub

[![PHP Syntax Check (CI)](https://github.com/AleximperV/HUIT-Schedule-Hub/actions/workflows/php-lint.yml/badge.svg)](https://github.com/AleximperV/HUIT-Schedule-Hub/actions/workflows/php-lint.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

**HUIT Schedule Hub** là ứng dụng web quản lý và phân lịch giảng dạy đa cơ sở thông minh dành cho môi trường đại học (mô phỏng trường Đại học Công Thương TP.HCM - HUIT), hỗ trợ phòng đào tạo tối ưu hóa thời khóa biểu và giảng viên tra cứu lịch cá nhân trực tuyến.

## Tính năng nổi bật

- **Quản lý & Lọc tổng quan (`index.php`)**: Lọc nhanh theo **Cơ sở** hoặc **Giảng viên**, thống kê KPI và biểu đồ phân bổ thời gian giảng dạy (Chart.js).
- **Phân lịch theo tiết trống & Tối ưu hóa (`schedules.php`)**:
  - CRUD đầy đủ lịch dạy, kiểm tra xung đột trùng giờ giảng viên và trùng phòng học.
  - Phát hiện cảnh báo **di chuyển gấp giữa 2 cơ sở liên tiếp** và **khoảng trống chết (dead gap)** trong ngày.
  - Phân lịch nhanh trực tiếp từ danh sách **tiết trống giảng viên** (`availabilities.php`).
- **Gợi ý giảng viên phù hợp (`api.php`)**: Tự động chấm điểm và xếp hạng giảng viên theo chuyên môn phù hợp, không trùng lịch, có tiết trống đăng ký và ưu tiên cùng cơ sở trong ngày.
- **Cổng thông tin Giảng viên (`portal.php`)**:
  - Đăng nhập theo vai trò hoặc truy cập bằng đường dẫn riêng (`portal.php?token=...`).
  - Xem lịch dạy cá nhân theo **Tuần** và theo **Tháng**, nắm rõ cơ sở/địa chỉ cần đến dạy và tự đăng ký tiết trống.

## Yêu cầu hệ thống

- PHP >= 8.1 (khuyên dùng PHP 8.2 / 8.3+ trên Laragon)
- MySQL >= 8.0 / MariaDB
- Composer

## Hướng dẫn cài đặt & chạy dự án (Laragon)

### 1. Clone mã nguồn vào `laragon\www`
```bash
cd C:\laragon\www
git clone https://github.com/AleximperV/HUIT-Schedule-Hub.git huit-schedule
cd huit-schedule
```
> Sau khi khởi động/reload Laragon, truy cập tên miền cục bộ: `http://huit-schedule.test`

### 2. Cài đặt thư viện phụ thuộc qua Composer
```bash
composer install
```
Thư viện sử dụng:
- `vlucas/phpdotenv`: Quản lý biến môi trường kết nối Database an toàn.
- `fakerphp/faker`: Sinh dữ liệu mẫu (cơ sở, giảng viên, tiết trống và lịch dạy).

### 3. Cấu hình file `.env`
Sao chép `.env.example` thành `.env` và chỉnh thông số kết nối MySQL (mặc định Laragon dùng `root`, mật khẩu rỗng):
```bash
copy .env.example .env
```
Nội dung mẫu `.env`:
```env
APP_NAME="HUIT Schedule Hub"
APP_ENV=local
APP_URL=http://huit-schedule.test

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=huit_db
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

### 4. Khởi tạo Cơ sở dữ liệu (`huit_db`)
Chọn **1 trong 2 cách** sau:

- **Cách 1 (Import SQL mẫu qua HeidiSQL / phpMyAdmin):**
  Mở HeidiSQL, tạo/chọn database `huit_db` và import file [`database/huit_db.sql`](database/huit_db.sql).
- **Cách 2 (Tự động tạo bảng & sinh dữ liệu mẫu bằng Faker):**
  ```bash
  php database/seed.php
  ```

### 5. Tài khoản dùng thử mặc định
- **Phòng Đào tạo (Admin):** `daotao@huit.edu.vn` / `admin123`
- **Giảng viên (Portal):** `gv1@huit.edu.vn` / `huit2026`

## Kiểm tra cú pháp tự động (CI/CD)

Dự án tích hợp GitHub Actions tại [`.github/workflows/php-lint.yml`](.github/workflows/php-lint.yml) để tự động chạy `php -l` trên toàn bộ mã nguồn PHP mỗi khi có `git push` hoặc `pull_request`.

## Giấy phép

Phát hành theo giấy phép [MIT License](LICENSE).
