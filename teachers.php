<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/layout.php';

use App\Models\Teacher;
use App\Services\AuthService;

AuthService::requireAdmin();

$editing = isset($_GET['edit']) ? Teacher::find((int) $_GET['edit']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        Teacher::delete((int) $_POST['id']);
        setFlash('success', 'Đã xóa giảng viên.');
        header('Location: teachers.php');
        exit;
    }

    if ($action === 'save') {
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $data = [
            'full_name' => (string) ($_POST['full_name'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
            'phone' => (string) ($_POST['phone'] ?? ''),
            'expertise' => (string) ($_POST['expertise'] ?? ''),
        ];
        if ($id) {
            Teacher::update($id, $data);
            setFlash('success', 'Đã cập nhật thông tin giảng viên.');
        } else {
            Teacher::create($data);
            setFlash('success', 'Đã thêm giảng viên mới.');
        }
        header('Location: teachers.php');
        exit;
    }
}

$teachers = Teacher::all();

renderHeader('Quản lý Giảng viên', 'teachers');
?>

<div class="page-header">
    <div>
        <h1>Quản lý Giảng viên &amp; Chuyên môn</h1>
        <p>Quản lý danh sách giảng viên, chuyên môn chính và đường dẫn truy cập Cổng thông tin Giảng viên.</p>
    </div>
</div>

<div class="card">
    <h3><?= $editing ? 'Cập nhật Giảng viên' : 'Thêm Giảng viên mới' ?></h3>
    <form method="POST" action="teachers.php" class="form-grid" style="align-items:end;">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : '' ?>">
        <div class="form-group">
            <label for="full_name">Họ và tên</label>
            <input type="text" name="full_name" id="full_name" required value="<?= e($editing['full_name'] ?? '') ?>" placeholder="VD: TS. Nguyễn Văn A">
        </div>
        <div class="form-group">
            <label for="email">Email HUIT</label>
            <input type="email" name="email" id="email" required value="<?= e($editing['email'] ?? '') ?>" placeholder="gv@huit.edu.vn">
        </div>
        <div class="form-group">
            <label for="phone">Số điện thoại</label>
            <input type="text" name="phone" id="phone" required value="<?= e($editing['phone'] ?? '') ?>" placeholder="09xxxxxxxx">
        </div>
        <div class="form-group">
            <label for="expertise">Chuyên môn chính</label>
            <input type="text" name="expertise" id="expertise" required value="<?= e($editing['expertise'] ?? '') ?>" placeholder="VD: Lập trình Web & PHP">
        </div>
        <div class="form-group" style="flex-direction:row;gap:8px;">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Cập nhật' : 'Thêm GV' ?></button>
            <?php if ($editing): ?><a href="teachers.php" class="btn btn-outline">Hủy</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h3>Danh sách Giảng viên (<?= count($teachers) ?>)</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Họ và tên</th>
                    <th>Email / SĐT</th>
                    <th>Chuyên môn chính</th>
                    <th>Link Cổng Giảng viên</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $t): ?>
                    <tr>
                        <td><strong><?= e($t['full_name']) ?></strong></td>
                        <td>
                            <?= e($t['email']) ?><br>
                            <small style="color:#64748b;"><?= e($t['phone']) ?></small>
                        </td>
                        <td><span class="badge badge-info"><?= e($t['expertise']) ?></span></td>
                        <td>
                            <?php if (!empty($t['portal_token'])): ?>
                                <a href="portal.php?token=<?= e($t['portal_token']) ?>" class="btn btn-sm btn-outline" target="_blank">Mở Portal riêng</a>
                            <?php endif; ?>
                        </td>
                        <td style="display:flex;gap:6px;">
                            <a href="teachers.php?edit=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline">Sửa</a>
                            <form method="POST" action="teachers.php" onsubmit="return confirm('Xóa giảng viên này?');" style="margin:0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
