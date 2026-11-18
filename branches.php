<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/layout.php';

use App\Models\Branch;
use App\Services\AuthService;

AuthService::requireAdmin();

$editing = isset($_GET['edit']) ? Branch::find((int) $_GET['edit']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        Branch::delete((int) $_POST['id']);
        setFlash('success', 'Đã xóa cơ sở.');
        header('Location: branches.php');
        exit;
    }

    if ($action === 'save') {
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $name = (string) ($_POST['branch_name'] ?? '');
        $addr = (string) ($_POST['address'] ?? '');
        if ($id) {
            Branch::update($id, $name, $addr);
            setFlash('success', 'Đã cập nhật cơ sở.');
        } else {
            Branch::create($name, $addr);
            setFlash('success', 'Đã thêm cơ sở mới.');
        }
        header('Location: branches.php');
        exit;
    }
}

$branches = Branch::all();

renderHeader('Quản lý Cơ sở Đào tạo', 'branches');
?>

<div class="page-header">
    <div>
        <h1>Quản lý Cơ sở Đào tạo HUIT</h1>
        <p>Danh sách các cơ sở chính, phòng máy và trung tâm liên kết phục vụ phân lịch đa cơ sở.</p>
    </div>
</div>

<div class="card">
    <h3><?= $editing ? 'Cập nhật Cơ sở' : 'Thêm Cơ sở mới' ?></h3>
    <form method="POST" action="branches.php" class="form-grid" style="align-items:end;">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : '' ?>">
        <div class="form-group">
            <label for="branch_name">Tên cơ sở</label>
            <input type="text" name="branch_name" id="branch_name" required value="<?= e($editing['branch_name'] ?? '') ?>" placeholder="VD: Cơ sở chính Tân Phú">
        </div>
        <div class="form-group" style="grid-column: span 2;">
            <label for="address">Địa chỉ</label>
            <input type="text" name="address" id="address" required value="<?= e($editing['address'] ?? '') ?>" placeholder="VD: 140 Lê Trọng Tấn, Q. Tân Phú, TP.HCM">
        </div>
        <div class="form-group" style="flex-direction:row;gap:8px;">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Cập nhật' : 'Thêm Cơ sở' ?></button>
            <?php if ($editing): ?><a href="branches.php" class="btn btn-outline">Hủy</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h3>Danh sách Cơ sở (<?= count($branches) ?>)</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên cơ sở</th>
                    <th>Địa chỉ</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                    <tr>
                        <td>#<?= (int) $b['id'] ?></td>
                        <td><strong><?= e($b['branch_name']) ?></strong></td>
                        <td><?= e($b['address']) ?></td>
                        <td style="display:flex;gap:6px;">
                            <a href="branches.php?edit=<?= (int) $b['id'] ?>" class="btn btn-sm btn-outline">Sửa</a>
                            <form method="POST" action="branches.php" onsubmit="return confirm('Xóa cơ sở này?');" style="margin:0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
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
