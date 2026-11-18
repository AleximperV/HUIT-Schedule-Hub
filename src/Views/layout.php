<?php

declare(strict_types=1);

use App\Services\AuthService;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $message): void
{
    AuthService::startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    AuthService::startSession();
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function renderHeader(string $title, string $activeNav = 'dashboard'): void
{
    $user = AuthService::user();
    $isAdmin = AuthService::isAdmin();
    $flash = getFlash();
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | HUIT Schedule Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="mobile-topbar">
        <a href="index.php" class="brand">
            <span class="brand-badge">HUIT</span>
            <span><strong>Schedule Hub</strong></span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-light" id="sidebar-toggle">Menu</button>
    </div>

    <div class="app-shell">
        <aside class="sidebar" id="app-sidebar">
            <div class="sidebar-top">
                <a href="index.php" class="brand">
                    <span class="brand-badge">HUIT</span>
                    <span>
                        <strong>Schedule Hub</strong>
                        <small>Phân lịch đa cơ sở</small>
                    </span>
                </a>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-label">Điều hướng</div>
                <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Tổng quan</a>
                <?php if ($isAdmin): ?>
                    <a href="schedules.php" class="<?= $activeNav === 'schedules' ? 'active' : '' ?>">Phân lịch thông minh</a>
                    <a href="availabilities.php" class="<?= $activeNav === 'availabilities' ? 'active' : '' ?>">Tiết trống GV</a>
                    <a href="teachers.php" class="<?= $activeNav === 'teachers' ? 'active' : '' ?>">Giảng viên</a>
                    <a href="branches.php" class="<?= $activeNav === 'branches' ? 'active' : '' ?>">Cơ sở</a>
                <?php endif; ?>
                <a href="portal.php" class="<?= $activeNav === 'portal' ? 'active' : '' ?>">Cổng Giảng viên</a>
            </nav>

            <div class="sidebar-footer">
                <?php if ($user): ?>
                    <div class="sidebar-user">
                        <div class="sidebar-user-name"><?= e($user['name']) ?></div>
                        <span class="sidebar-role-badge"><?= $isAdmin ? 'Phòng Đào tạo' : 'Giảng viên' ?></span>
                    </div>
                    <a href="logout.php" class="btn btn-sm btn-outline-light" style="width:100%;">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-accent" style="width:100%;">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </aside>

        <div class="main-wrap">
            <main class="main-content">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?>">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>
    <?php
}

function renderFooter(): void
{
    ?>
            </main>
            <footer class="footer">
                <div class="footer-inner">
                    <span>&copy; <?= date('Y') ?> HUIT Schedule Hub — Quản lý &amp; Phân lịch giảng dạy đa cơ sở</span>
                    <span>MIT License &bull; PHP 8 + MySQL + phpdotenv + Faker</span>
                </div>
            </footer>
        </div>
    </div>
    <script>
    document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
        document.getElementById('app-sidebar')?.classList.toggle('open');
    });
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
    <?php
}
