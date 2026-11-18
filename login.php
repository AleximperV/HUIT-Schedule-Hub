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

<div style="max-width: 520px; margin: 24px auto;">
    <div class="card">
        <h2>Đăng nhập HUIT Schedule Hub</h2>
        <p style="color:#64748b;font-size:14px;">Chọn vai trò để truy cập chức năng phân lịch hoặc xem lịch giảng dạy cá nhân.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'admin_required'): ?>
            <div class="alert alert-warning">Vui lòng đăng nhập bằng tài khoản Phòng Đào tạo để sử dụng chức năng quản trị.</div>
        <?php endif; ?>

        <form method="POST" action="login.php" style="display:grid;gap:14px;">
            <div class="form-group">
                <label for="role">Vai trò</label>
                <select name="role" id="role">
                    <option value="admin">Phòng Đào tạo (Quản trị &amp; Phân lịch)</option>
                    <option value="teacher">Giảng viên (Cổng thông tin cá nhân)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required value="daotao@huit.edu.vn">
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" name="password" id="password" required value="admin123">
            </div>
            <button type="submit" class="btn btn-primary">Đăng nhập</button>
        </form>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:18px 0;">
        <div style="font-size:13px;color:#475569;">
            <strong>Tài khoản dùng thử nhanh:</strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                <li><strong>Phòng Đào tạo:</strong> <code>daotao@huit.edu.vn</code> / <code>admin123</code></li>
                <li><strong>Giảng viên mẫu:</strong> <code>gv1@huit.edu.vn</code> / <code>huit2026</code></li>
            </ul>
        </div>
    </div>
</div>

<script>
document.getElementById('role').addEventListener('change', function () {
    const email = document.getElementById('email');
    const pass = document.getElementById('password');
    if (this.value === 'teacher') {
        email.value = 'gv1@huit.edu.vn';
        pass.value = 'huit2026';
    } else {
        email.value = 'daotao@huit.edu.vn';
        pass.value = 'admin123';
    }
});
</script>

<?php renderFooter(); ?>
