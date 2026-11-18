<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/layout.php';

use App\Models\Branch;
use App\Services\AuthService;

AuthService::startSession();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (AuthService::loginAdmin($email, $password)) {
        setFlash('success', 'Đăng nhập Phòng Đào tạo thành công.');
        header('Location: schedules.php');
        exit;
    }

    if (AuthService::loginTeacher($email, $password)) {
        setFlash('success', 'Đăng nhập Cổng thông tin Giảng viên thành công.');
        header('Location: portal.php');
        exit;
    }

    $error = 'Email hoặc mật khẩu không chính xác.';
}

$branches = [];
try {
    $branches = Branch::all();
} catch (Throwable $e) {
    $branches = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Hệ thống | HUIT Schedule Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --huit-blue: #0057b8;
            --huit-blue-dark: #003e85;
            --huit-orange: #f97316;
            --huit-orange-hover: #ea580c;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f4f7fb;
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .huit-banner {
            background: linear-gradient(90deg, var(--huit-blue-dark), var(--huit-blue));
            color: #fff;
            padding: 14px 24px;
            box-shadow: 0 2px 10px rgba(0, 62, 133, 0.25);
        }
        .huit-banner-inner {
            max-width: 1220px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .huit-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: #fff;
        }
        .huit-logo-box {
            background: #fff;
            color: var(--huit-blue);
            font-weight: 800;
            font-size: 20px;
            padding: 8px 14px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }
        .huit-titles small {
            display: block;
            font-size: 11px;
            letter-spacing: 0.8px;
            opacity: 0.9;
            text-transform: uppercase;
        }
        .huit-titles strong {
            display: block;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .huit-banner-link {
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 7px 14px;
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 6px;
        }
        .huit-banner-link:hover {
            background: rgba(255, 255, 255, 0.14);
        }

        .login-container {
            max-width: 1220px;
            width: 100%;
            margin: 26px auto;
            padding: 0 20px;
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 24px;
            align-items: start;
        }

        /* Khung thông tin bên trái / giữa */
        .info-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }
        .info-tabs {
            display: flex;
            border-bottom: 2px solid var(--border);
            background: #f8fafc;
            flex-wrap: wrap;
        }
        .info-tab {
            padding: 13px 20px;
            font-size: 14px;
            font-weight: 700;
            color: var(--huit-blue);
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            cursor: pointer;
            text-transform: uppercase;
        }
        .info-tab.active {
            background: #fff;
            border-bottom-color: var(--huit-blue);
            color: var(--huit-blue-dark);
        }
        .tab-pane {
            display: none;
            padding: 10px 22px 22px;
        }
        .tab-pane.active {
            display: block;
        }
        .news-item {
            display: flex;
            gap: 18px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
            align-items: flex-start;
        }
        .news-item:last-child {
            border-bottom: none;
        }
        .date-badge {
            width: 68px;
            flex-shrink: 0;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            text-align: center;
            background: #fff;
        }
        .date-badge .month {
            background: var(--huit-blue);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 0;
        }
        .date-badge .day {
            font-size: 22px;
            font-weight: 700;
            color: var(--huit-blue);
            padding: 6px 0;
        }
        .news-body h4 {
            margin: 0 0 6px;
            font-size: 15px;
            color: var(--huit-blue);
            line-height: 1.4;
        }
        .news-body p {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }
        .news-tag {
            display: inline-block;
            margin-top: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #dc2626;
        }

        /* Khung đăng nhập bên phải */
        .login-card {
            background: linear-gradient(180deg, #eef6ff 0%, #ffffff 45%, #fff7ed 100%);
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 26px 24px;
            box-shadow: 0 6px 20px rgba(0, 62, 133, 0.1);
        }
        .login-card-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-card-header .portal-sub {
            color: var(--huit-blue);
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .login-card-header h2 {
            margin: 6px 0 0;
            font-size: 18px;
            color: var(--huit-blue-dark);
            font-weight: 800;
        }
        .login-form {
            display: grid;
            gap: 14px;
        }
        .login-form input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
            background: #fff;
        }
        .login-form input:focus {
            outline: none;
            border-color: var(--huit-blue);
            box-shadow: 0 0 0 3px rgba(0, 87, 184, 0.14);
        }
        .btn-login-orange {
            width: 100%;
            padding: 12px;
            background: var(--huit-orange);
            color: #fff;
            border: none;
            border-radius: 7px;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-login-orange:hover {
            background: var(--huit-orange-hover);
        }
        .alert {
            padding: 10px 12px;
            border-radius: 7px;
            font-size: 13px;
            margin-bottom: 14px;
        }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .login-note {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
            font-size: 12.5px;
            color: var(--muted);
            text-align: center;
            line-height: 1.5;
        }

        .login-footer {
            background: #fff;
            border-top: 1px solid var(--border);
            padding: 14px 24px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }

        @media (max-width: 900px) {
            .login-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="huit-banner">
        <div class="huit-banner-inner">
            <a href="index.php" class="huit-brand">
                <div class="huit-logo-box">HUIT</div>
                <div class="huit-titles">
                    <small>Trường Đại học Công Thương TP. Hồ Chí Minh</small>
                    <strong>HUIT SCHEDULE HUB &mdash; PHÂN LỊCH ĐA CƠ SỞ</strong>
                </div>
            </a>
            <a href="index.php" class="huit-banner-link">Xem Tổng quan Lịch</a>
        </div>
    </header>

    <main class="login-container">
        <section class="info-panel">
            <div class="info-tabs">
                <button type="button" class="info-tab active" data-tab="tab-general">Thông báo chung</button>
                <button type="button" class="info-tab" data-tab="tab-branches">Cơ sở đào tạo</button>
                <button type="button" class="info-tab" data-tab="tab-guide">Hướng dẫn sử dụng</button>
            </div>

            <div class="tab-pane active" id="tab-general">
                <div class="news-item">
                    <div class="date-badge">
                        <div class="month">Tháng 11</div>
                        <div class="day">18</div>
                    </div>
                    <div class="news-body">
                        <h4>Thông báo triển khai hệ thống phân lịch giảng dạy đa cơ sở thông minh (HUIT Schedule Hub)</h4>
                        <p>Hỗ trợ Phòng Đào tạo sắp xếp thời khóa biểu tối ưu, hạn chế di chuyển gấp giữa các cơ sở và tránh khoảng trống chết.</p>
                        <span class="news-tag">Mới cập nhật</span>
                    </div>
                </div>
                <div class="news-item">
                    <div class="date-badge">
                        <div class="month">Tháng 11</div>
                        <div class="day">15</div>
                    </div>
                    <div class="news-body">
                        <h4>Giảng viên cập nhật khung tiết trống cá nhân cho Học kỳ 1 năm học 2026 - 2027</h4>
                        <p>Quý thầy/cô đăng nhập vào Cổng thông tin Giảng viên để khai báo các buổi/tiết trống và cơ sở ưu tiên giảng dạy.</p>
                    </div>
                </div>
                <div class="news-item">
                    <div class="date-badge">
                        <div class="month">Tháng 11</div>
                        <div class="day">12</div>
                    </div>
                    <div class="news-body">
                        <h4>Tính năng gợi ý giảng viên phù hợp theo chuyên môn và lịch trống</h4>
                        <p>Hệ thống tự động đề xuất giảng viên đúng chuyên môn, không trùng giờ và tối ưu quãng đường di chuyển trong ngày.</p>
                    </div>
                </div>
                <div class="news-item">
                    <div class="date-badge">
                        <div class="month">Tháng 11</div>
                        <div class="day">08</div>
                    </div>
                    <div class="news-body">
                        <h4>Tra cứu lịch dạy cá nhân theo Tuần và Tháng trên Cổng Giảng viên</h4>
                        <p>Giảng viên có thể theo dõi chi tiết phòng học, địa chỉ cơ sở cần đến dạy và các lưu ý chuyển cơ sở trong ngày.</p>
                    </div>
                </div>
            </div>

            <div class="tab-pane" id="tab-branches">
                <?php if (empty($branches)): ?>
                    <p style="padding:16px 0;color:#64748b;">Đang cập nhật danh sách cơ sở đào tạo.</p>
                <?php else: ?>
                    <?php foreach ($branches as $idx => $b): ?>
                        <div class="news-item">
                            <div class="date-badge">
                                <div class="month">Cơ sở</div>
                                <div class="day">0<?= $idx + 1 ?></div>
                            </div>
                            <div class="news-body">
                                <h4><?= e($b['branch_name']) ?></h4>
                                <p>Địa chỉ: <?= e($b['address']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="tab-pane" id="tab-guide">
                <div class="news-item">
                    <div class="date-badge">
                        <div class="month">Bước</div>
                        <div class="day">01</div>
                    </div>
                    <div class="news-body">
                        <h4>Đăng nhập bằng tài khoản được cấp</h4>
                        <p>Sử dụng email và mật khẩu cá nhân. Hệ thống tự động nhận diện vai trò Phòng Đào tạo hoặc Giảng viên để điều hướng phù hợp.</p>
                    </div>
                </div>
                <div class="news-item">
                    <div class="date-badge">
                        <div class="month">Bước</div>
                        <div class="day">02</div>
                    </div>
                    <div class="news-body">
                        <h4>Truy cập nhanh bằng đường dẫn riêng (dành cho Giảng viên)</h4>
                        <p>Giảng viên có thể mở trực tiếp đường dẫn Cổng thông tin riêng do Phòng Đào tạo cung cấp mà không cần nhập lại mật khẩu.</p>
                    </div>
                </div>
            </div>
        </section>

        <aside class="login-card">
            <div class="login-card-header">
                <div class="portal-sub">Cổng thông tin Phân lịch</div>
                <h2>ĐĂNG NHẬP HỆ THỐNG</h2>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'admin_required'): ?>
                <div class="alert alert-warning">Vui lòng đăng nhập bằng tài khoản Phòng Đào tạo để truy cập chức năng quản trị.</div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="off" class="login-form">
                <input type="email" name="email" id="email" required placeholder="Nhập email tài khoản">
                <input type="password" name="password" id="password" required placeholder="Nhập mật khẩu">
                <button type="submit" class="btn-login-orange">Đăng nhập</button>
            </form>

            <div class="login-note">
                Hệ thống tự động phân quyền Phòng Đào tạo hoặc Giảng viên theo tài khoản đăng nhập.
            </div>
        </aside>
    </main>

    <footer class="login-footer">
        &copy; <?= date('Y') ?> HUIT Schedule Hub &mdash; Hệ thống Quản lý &amp; Phân lịch giảng dạy đa cơ sở
    </footer>

    <script>
    document.querySelectorAll('.info-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.info-tab').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.dataset.tab)?.classList.add('active');
        });
    });
    </script>
</body>
</html>
