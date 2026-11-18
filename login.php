<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/layout.php';

use App\Services\AuthService;

AuthService::startSession();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'admin';
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($role === 'admin') {
        if (AuthService::loginAdmin($email, $password)) {
            setFlash('success', 'Đăng nhập Phòng Đào tạo thành công.');
            header('Location: schedules.php');
            exit;
        }
        $error = 'Sai email hoặc mật khẩu Phòng Đào tạo.';
    } else {
        if (AuthService::loginTeacher($email, $password)) {
            setFlash('success', 'Đăng nhập Cổng thông tin Giảng viên thành công.');
            header('Location: portal.php');
            exit;
        }
        $error = 'Sai email giảng viên hoặc mật khẩu.';
    }
}

renderHeader('Đăng nhập Hệ thống', 'login');
?>

<div style="max-width: 480px; margin: 36px auto;">
    <div class="card">
        <h2>Đăng nhập HUIT Schedule Hub</h2>
        <p style="color:#64748b;font-size:14px;">Chọn vai trò để truy cập chức năng phân lịch hoặc xem lịch giảng dạy cá nhân.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'admin_required'): ?>
            <div class="alert alert-warning">Vui lòng đăng nhập bằng tài khoản Phòng Đào tạo để sử dụng chức năng quản trị.</div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off" style="display:grid;gap:14px;">
            <div class="form-group">
                <label for="role">Vai trò</label>
                <select name="role" id="role">
                    <option value="admin">Phòng Đào tạo (Quản trị &amp; Phân lịch)</option>
                    <option value="teacher">Giảng viên (Cổng thông tin cá nhân)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required placeholder="Nhập email tài khoản...">
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" name="password" id="password" required placeholder="Nhập mật khẩu...">
            </div>
            <button type="submit" class="btn btn-primary">Đăng nhập</button>
        </form>
    </div>
</div>

<?php renderFooter(); ?>
