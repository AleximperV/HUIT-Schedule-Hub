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

function paginateArray(array $items, int $defaultPerPage = 10, string $pageParam = 'page', string $perPageParam = 'per_page'): array
{
    $allowed = [5, 10, 20, 50];
    $perPage = isset($_GET[$perPageParam]) ? (int) $_GET[$perPageParam] : $defaultPerPage;
    if (!in_array($perPage, $allowed, true)) {
        $perPage = $defaultPerPage;
    }

    $total = count($items);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = isset($_GET[$pageParam]) ? (int) $_GET[$pageParam] : 1;
    $page = max(1, min($page, $totalPages));

    return [
        'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'page_param' => $pageParam,
        'per_page_param' => $perPageParam,
    ];
}

function renderPagination(array $pg): void
{
    $total = $pg['total'];
    $page = $pg['page'];
    $perPage = $pg['per_page'];
    $totalPages = $pg['total_pages'];
    $pageParam = $pg['page_param'];
    $perPageParam = $pg['per_page_param'];

    $from = $total === 0 ? 0 : (($page - 1) * $perPage + 1);
    $to = min($total, $page * $perPage);

    $buildUrl = static function (int $targetPage, ?int $targetPerPage = null) use ($pageParam, $perPageParam, $perPage): string {
        $params = $_GET;
        $params[$pageParam] = $targetPage;
        $params[$perPageParam] = $targetPerPage ?? $perPage;
        return '?' . http_build_query($params);
    };
    ?>
    <div class="pagination-bar">
        <div class="pagination-left">
            <span>Hiển thị <strong><?= $from ?>–<?= $to ?></strong> / <strong><?= $total ?></strong> mục</span>
            <form method="GET" class="inline-flex items-center gap-1.5 m-0">
                <?php foreach ($_GET as $k => $v):
                    if ($k === $pageParam || $k === $perPageParam || is_array($v)) {
                        continue;
                    }
                    ?>
                    <input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="<?= e($pageParam) ?>" value="1">
                <label for="per_page_select_<?= e($perPageParam) ?>">Hiển thị:</label>
                <select name="<?= e($perPageParam) ?>" id="per_page_select_<?= e($perPageParam) ?>" onchange="this.form.submit()">
                    <?php foreach ([5, 10, 20, 50] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?> / trang</option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="page-links">
            <a href="<?= e($buildUrl(max(1, $page - 1))) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">&laquo;</a>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= e($buildUrl($i)) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= e($buildUrl(min($totalPages, $page + 1))) ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">&raquo;</a>
        </div>
        <?php endif; ?>
    </div>
    <?php
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        huit: { DEFAULT: '#0b4f9e', dark: '#073670', accent: '#f59e0b' }
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased">
    <div class="app-shell flex min-h-screen">
        <aside id="app-sidebar" class="sidebar w-64 shrink-0 bg-gradient-to-b from-huit-dark to-huit text-white flex flex-col sticky top-0 h-screen shadow-xl z-50 transition-all duration-200 overflow-hidden">
            <div class="p-4 border-b border-white/15 flex items-center justify-between gap-2">
                <a href="index.php" class="flex items-center gap-2.5 text-white no-underline min-w-0">
                    <span class="bg-huit-accent text-slate-900 font-extrabold px-2.5 py-1.5 rounded-lg text-xs tracking-wide shrink-0">HUIT</span>
                    <span class="truncate">
                        <strong class="block text-sm font-bold leading-tight">Schedule Hub</strong>
                        <small class="block text-[11px] text-white/80">Phân lịch đa cơ sở</small>
                    </span>
                </a>
                <button type="button" id="sidebar-close-btn" class="lg:hidden text-white/80 hover:text-white p-1.5 rounded-md hover:bg-white/10" aria-label="Đóng menu">&times;</button>
            </div>

            <nav class="flex-1 px-3 py-4 flex flex-col gap-1 overflow-y-auto">
                <div class="text-[11px] uppercase tracking-wider text-white/55 px-3 pb-2 font-semibold">Điều hướng</div>
                <a href="index.php" class="sidebar-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">Tổng quan</a>
                <?php if ($isAdmin): ?>
                    <a href="schedules.php" class="sidebar-link <?= $activeNav === 'schedules' ? 'active' : '' ?>">Phân lịch thông minh</a>
                    <a href="availabilities.php" class="sidebar-link <?= $activeNav === 'availabilities' ? 'active' : '' ?>">Tiết trống GV</a>
                    <a href="teachers.php" class="sidebar-link <?= $activeNav === 'teachers' ? 'active' : '' ?>">Giảng viên</a>
                    <a href="branches.php" class="sidebar-link <?= $activeNav === 'branches' ? 'active' : '' ?>">Cơ sở</a>
                <?php endif; ?>
                <a href="portal.php" class="sidebar-link <?= $activeNav === 'portal' ? 'active' : '' ?>">Cổng Giảng viên</a>
            </nav>

            <div class="p-4 border-t border-white/15 flex flex-col gap-2.5">
                <?php if ($user): ?>
                    <div class="bg-white/10 px-3 py-2.5 rounded-lg">
                        <div class="text-xs font-semibold truncate"><?= e($user['name']) ?></div>
                        <span class="inline-block mt-1 bg-huit-accent text-slate-900 font-bold text-[11px] px-2 py-0.5 rounded-full">
                            <?= $isAdmin ? 'Phòng Đào tạo' : 'Giảng viên' ?>
                        </span>
                    </div>
                    <a href="logout.php" class="btn btn-sm btn-outline-light w-full">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-accent w-full">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </aside>

        <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden lg:hidden"></div>

        <div class="main-wrap flex-1 min-w-0 flex flex-col">
            <div class="no-print bg-white border-b border-slate-200 px-5 py-2.5 flex items-center justify-between gap-3 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <button type="button" id="sidebar-toggle" class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 cursor-pointer" title="Đóng / Mở Sidebar" aria-label="Đóng hoặc mở Sidebar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <span class="text-sm font-semibold text-slate-700"><?= e($title) ?></span>
                </div>
                <span class="text-xs text-slate-500 hidden sm:inline">HUIT Schedule Hub &bull; Quản lý phân lịch đa cơ sở</span>
            </div>

            <div class="toast-container" id="toast-container">
                <?php if ($flash): ?>
                    <div class="toast toast-<?= e($flash['type']) ?>" role="alert">
                        <div class="toast-body"><?= e($flash['message']) ?></div>
                        <button type="button" class="toast-close" aria-label="Đóng">&times;</button>
                    </div>
                <?php endif; ?>
            </div>

            <main class="main-content flex-1 p-6 max-w-[1360px] w-full">
    <?php
}

function renderFooter(): void
{
    ?>
            </main>
            <footer class="footer border-t border-slate-200 bg-white px-7 py-4 text-xs text-slate-500">
                <div class="flex justify-between flex-wrap gap-2">
                    <span>&copy; <?= date('Y') ?> HUIT Schedule Hub — Quản lý &amp; Phân lịch giảng dạy đa cơ sở</span>
                    <span>MIT License &bull; PHP 8 + MySQL + Tailwind CSS</span>
                </div>
            </footer>
        </div>
    </div>
    <script>
    (() => {
        const sidebar = document.getElementById('app-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const toggleBtn = document.getElementById('sidebar-toggle');
        const closeBtn = document.getElementById('sidebar-close-btn');
        const STORAGE_KEY = 'huit_sidebar_collapsed';

        const applyDesktopState = (collapsed) => {
            if (window.innerWidth >= 1024) {
                sidebar?.classList.toggle('collapsed', collapsed);
                sidebar?.classList.remove('mobile-open');
                overlay?.classList.add('hidden');
            }
        };

        if (localStorage.getItem(STORAGE_KEY) === '1') {
            applyDesktopState(true);
        }

        toggleBtn?.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                const isOpen = sidebar?.classList.toggle('mobile-open');
                overlay?.classList.toggle('hidden', !isOpen);
            } else {
                const collapsed = sidebar?.classList.toggle('collapsed');
                localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
            }
        });

        const closeMobile = () => {
            sidebar?.classList.remove('mobile-open');
            overlay?.classList.add('hidden');
        };
        closeBtn?.addEventListener('click', closeMobile);
        overlay?.addEventListener('click', closeMobile);
    })();
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
    <?php
}
