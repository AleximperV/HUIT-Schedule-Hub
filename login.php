<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/layout.php';

use App\Models\Availability;
use App\Models\Branch;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Services\AuthService;
use App\Services\ScheduleOptimizer;

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
$branchStats = [];

try {
    $branches = Branch::all();
    $branchStats = Schedule::statsByBranch();
} catch (Throwable $e) {
    // Bỏ qua nếu DB chưa khởi tạo
}

$branchCountMap = [];
foreach ($branchStats as $bs) {
    $branchCountMap[(int) $bs['id']] = (int) $bs['total_classes'];
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
            align-items: stretch;
        }

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
            font-size: 13.5px;
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
            padding: 18px 22px 22px;
        }
        .tab-pane.active {
            display: block;
        }

        .mini-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }
        .mini-stat {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-top: 3px solid var(--huit-blue);
            border-radius: 8px;
            padding: 10px 12px;
        }
        .mini-stat span {
            display: block;
            font-size: 12px;
            color: var(--muted);
        }
        .mini-stat strong {
            display: block;
            font-size: 22px;
            color: var(--huit-blue-dark);
            margin-top: 2px;
        }

        .news-item {
            display: flex;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            align-items: flex-start;
        }
        .news-item:last-child {
            border-bottom: none;
        }
        .slot-badge {
            width: 82px;
            flex-shrink: 0;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            text-align: center;
            background: #fff;
        }
        .slot-badge .top {
            background: var(--huit-blue);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 4px;
        }
        .slot-badge .bottom {
            font-size: 13px;
            font-weight: 700;
            color: var(--huit-blue-dark);
            padding: 7px 4px;
        }
        .news-body h4 {
            margin: 0 0 5px;
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
        .meta-pill {
            display: inline-block;
            margin-top: 6px;
            margin-right: 6px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            background: #e0f2fe;
            color: #075985;
        }

        .login-card {
            background: linear-gradient(180deg, #eef6ff 0%, #ffffff 45%, #fff7ed 100%);
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 32px 24px;
            box-shadow: 0 6px 20px rgba(0, 62, 133, 0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
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
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 380px;
            width: calc(100vw - 32px);
        }
        .toast {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            background: #fff;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.18);
            border-left: 4px solid #0284c7;
            animation: toastIn 0.25s ease-out;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .toast.hiding { opacity: 0; transform: translateX(20px); }
        .toast-danger  { border-left-color: #dc2626; background: #fef2f2; color: #991b1b; }
        .toast-warning { border-left-color: #d97706; background: #fffbeb; color: #92400e; }
        .toast-success { border-left-color: #16a34a; background: #f0fdf4; color: #166534; }
        .toast-body { flex: 1; line-height: 1.45; }
        .toast-close {
            background: transparent;
            border: none;
            color: inherit;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            opacity: 0.65;
            padding: 0 2px;
        }
        .toast-close:hover { opacity: 1; }
        @keyframes toastIn {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
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
            .login-container { grid-template-columns: 1fr; }
            .mini-stats { grid-template-columns: repeat(2, 1fr); }
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
            <?php if (AuthService::user()): ?>
                <a href="index.php" class="huit-banner-link">Vào Hệ thống</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="login-container">
        <section class="info-panel">
            <div class="info-tabs">
                <button type="button" class="info-tab active" data-tab="tab-branches">Cơ sở đào tạo (<?= count($branches) ?>)</button>
                <button type="button" class="info-tab" data-tab="tab-features">Tính năng thông minh</button>
            </div>

            <div class="tab-pane active" id="tab-branches">
                <?php if (empty($branches)): ?>
                    <p style="color:#64748b;margin:0;">Đang cập nhật danh sách cơ sở đào tạo.</p>
                <?php else: ?>
                    <?php foreach ($branches as $idx => $b): ?>
                        <div class="news-item">
                            <div class="slot-badge">
                                <div class="top">Cơ sở</div>
                                <div class="bottom">0<?= $idx + 1 ?></div>
                            </div>
                            <div class="news-body">
                                <h4><?= e($b['branch_name']) ?></h4>
                                <p>Địa chỉ: <?= e($b['address']) ?></p>
                                <span class="meta-pill"><?= $branchCountMap[(int) $b['id']] ?? 0 ?> ca giảng dạy/tuần</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="tab-pane" id="tab-features">
                <div class="news-item">
                    <div class="slot-badge">
                        <div class="top">Tính năng</div>
                        <div class="bottom">01</div>
                    </div>
                    <div class="news-body">
                        <h4>Phân lịch theo tiết trống &amp; Tối ưu di chuyển đa cơ sở</h4>
                        <p>Phát hiện xung đột giờ dạy, trùng phòng học, cảnh báo chuyển cơ sở gấp giữa các tiết liền kề và khoảng trống chết trong ngày.</p>
                    </div>
                </div>
                <div class="news-item">
                    <div class="slot-badge">
                        <div class="top">Tính năng</div>
                        <div class="bottom">02</div>
                    </div>
                    <div class="news-body">
                        <h4>Gợi ý giảng viên phù hợp theo chuyên môn</h4>
                        <p>Tự động xếp hạng giảng viên đúng chuyên môn, không trùng lịch, có tiết trống đăng ký và ưu tiên giảng viên đang dạy cùng cơ sở.</p>
                    </div>
                </div>
                <div class="news-item">
                    <div class="slot-badge">
                        <div class="top">Tính năng</div>
                        <div class="bottom">03</div>
                    </div>
                    <div class="news-body">
                        <h4>Cổng thông tin Giảng viên (Tuần / Tháng)</h4>
                        <p>Giảng viên tra cứu lịch dạy cá nhân theo tuần/tháng, xem địa chỉ cơ sở cần đến dạy và tự đăng ký khung tiết trống.</p>
                    </div>
                </div>
            </div>
        </section>

        <aside class="login-card">
            <div class="login-card-header">
                <div class="portal-sub">Cổng thông tin Phân lịch</div>
                <h2>ĐĂNG NHẬP HỆ THỐNG</h2>
            </div>

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

    <div class="toast-container" id="toast-container">
        <?php if ($error !== ''): ?>
            <div class="toast toast-danger" role="alert">
                <div class="toast-body"><?= e($error) ?></div>
                <button type="button" class="toast-close" aria-label="Đóng">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'admin_required'): ?>
            <div class="toast toast-warning" role="alert">
                <div class="toast-body">Vui lòng đăng nhập bằng tài khoản Phòng Đào tạo để truy cập chức năng quản trị.</div>
                <button type="button" class="toast-close" aria-label="Đóng">&times;</button>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.querySelectorAll('#toast-container .toast').forEach(toast => {
        const dismiss = () => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 260);
        };
        toast.querySelector('.toast-close')?.addEventListener('click', dismiss);
        setTimeout(dismiss, 4500);
    });
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
