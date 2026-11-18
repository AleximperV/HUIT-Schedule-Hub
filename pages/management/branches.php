<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/Views/layout.php';

use App\Models\Branch;
use App\Services\AuthService;

AuthService::requireAdmin();

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
        $name = trim((string) ($_POST['branch_name'] ?? ''));
        $addr = trim((string) ($_POST['address'] ?? ''));

        $errors = [];
        if (mb_strlen($name, 'UTF-8') < 3 || mb_strlen($name, 'UTF-8') > 150) {
            $errors[] = 'Tên cơ sở phải từ 3–150 ký tự.';
        }
        if (mb_strlen($addr, 'UTF-8') < 8 || mb_strlen($addr, 'UTF-8') > 255) {
            $errors[] = 'Địa chỉ cơ sở phải từ 8–255 ký tự.';
        }

        if (!empty($errors)) {
            setFlash('danger', implode(' ', $errors));
            header('Location: branches.php');
            exit;
        }

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
$pgBranches = paginateArray($branches, 10);

renderHeader('Quản lý Cơ sở Đào tạo', 'branches');
?>

<div class="page-header">
    <div>
        <h1>Quản lý Cơ sở Đào tạo HUIT</h1>
        <p>Danh sách các cơ sở chính, phòng máy và trung tâm liên kết phục vụ phân lịch đa cơ sở.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openBranchCreateModal()">+ Thêm Cơ sở</button>
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
                <?php foreach ($pgBranches['items'] as $b): ?>
                    <tr>
                        <td>#<?= (int) $b['id'] ?></td>
                        <td><strong><?= e($b['branch_name']) ?></strong></td>
                        <td><?= e($b['address']) ?></td>
                        <td style="display:flex;gap:6px;">
                            <button type="button" class="btn btn-sm btn-outline"
                                onclick='openBranchEditModal(<?= json_encode([
                                    "id" => (int) $b["id"],
                                    "branch_name" => $b["branch_name"],
                                    "address" => $b["address"],
                                ], JSON_UNESCAPED_UNICODE) ?>)'>Sửa</button>
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
    <?php renderPagination($pgBranches); ?>
</div>

<div class="modal-backdrop" id="branch-modal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="branch-modal-title">
        <div class="modal-header">
            <h3 id="branch-modal-title">Thêm Cơ sở mới</h3>
            <button type="button" class="modal-close" data-close-modal aria-label="Đóng">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="branches.php" style="display:grid;gap:14px;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="branch_id_input" value="">

                <div class="form-group">
                    <label for="branch_name">Tên cơ sở *</label>
                    <input type="text" name="branch_name" id="branch_name" required minlength="3" maxlength="150"
                        placeholder="VD: Cơ sở chính Tân Phú">
                </div>
                <div class="form-group">
                    <label for="address">Địa chỉ *</label>
                    <input type="text" name="address" id="address" required minlength="8" maxlength="255"
                        placeholder="VD: 140 Lê Trọng Tấn, Q. Tân Phú, TP.HCM">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Hủy</button>
                    <button type="submit" class="btn btn-primary" id="branch-submit-btn">Thêm Cơ sở</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openBranchCreateModal() {
    document.getElementById('branch-modal-title').textContent = 'Thêm Cơ sở mới';
    document.getElementById('branch-submit-btn').textContent = 'Thêm Cơ sở';
    document.getElementById('branch_id_input').value = '';
    document.getElementById('branch_name').value = '';
    document.getElementById('address').value = '';
    openModal('branch-modal');
}

function openBranchEditModal(data) {
    document.getElementById('branch-modal-title').textContent = 'Cập nhật Cơ sở';
    document.getElementById('branch-submit-btn').textContent = 'Lưu thay đổi';
    document.getElementById('branch_id_input').value = data.id;
    document.getElementById('branch_name').value = data.branch_name;
    document.getElementById('address').value = data.address;
    openModal('branch-modal');
}
</script>

<?php renderFooter(); ?>
