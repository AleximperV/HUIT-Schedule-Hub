<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/layout.php';

use App\Models\Teacher;
use App\Services\AuthService;

AuthService::requireAdmin();

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
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $expertise = trim((string) ($_POST['expertise'] ?? ''));

        $errors = [];
        if (!preg_match('/^[\p{L}\s\.]{3,120}$/u', $fullName)) {
            $errors[] = 'Họ và tên phải từ 3–120 ký tự, chỉ gồm chữ cái, khoảng trắng hoặc dấu chấm.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_contains(substr($email, strpos($email, '@') ?: 0), '.')) {
            $errors[] = 'Email không đúng định dạng hợp lệ (VD: gv@huit.edu.vn).';
        }
        if (!preg_match('/^(0|\+84)[0-9]{9,10}$/', $phone)) {
            $errors[] = 'Số điện thoại phải bắt đầu bằng 0 hoặc +84 và gồm 10–11 chữ số.';
        }
        if (mb_strlen($expertise, 'UTF-8') < 3 || mb_strlen($expertise, 'UTF-8') > 200) {
            $errors[] = 'Chuyên môn chính phải từ 3–200 ký tự.';
        }

        if (empty($errors)) {
            $existing = Teacher::findByEmail($email);
            if ($existing && (int) $existing['id'] !== (int) $id) {
                $errors[] = 'Email "' . $email . '" đã tồn tại trong hệ thống.';
            }
        }

        if (!empty($errors)) {
            setFlash('danger', implode(' ', $errors));
            header('Location: teachers.php');
            exit;
        }

        $data = [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'expertise' => $expertise,
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
$pgTeachers = paginateArray($teachers, 10);

renderHeader('Quản lý Giảng viên', 'teachers');
?>

<div class="page-header">
    <div>
        <h1>Quản lý Giảng viên &amp; Chuyên môn</h1>
        <p>Quản lý danh sách giảng viên, chuyên môn chính và đường dẫn truy cập Cổng thông tin Giảng viên.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openTeacherCreateModal()">+ Thêm Giảng viên</button>
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
                <?php foreach ($pgTeachers['items'] as $t): ?>
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
                            <button type="button" class="btn btn-sm btn-outline"
                                onclick='openTeacherEditModal(<?= json_encode([
                                    "id" => (int) $t["id"],
                                    "full_name" => $t["full_name"],
                                    "email" => $t["email"],
                                    "phone" => $t["phone"],
                                    "expertise" => $t["expertise"],
                                ], JSON_UNESCAPED_UNICODE) ?>)'>Sửa</button>
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
    <?php renderPagination($pgTeachers); ?>
</div>

<div class="modal-backdrop" id="teacher-modal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="teacher-modal-title">
        <div class="modal-header">
            <h3 id="teacher-modal-title">Thêm Giảng viên mới</h3>
            <button type="button" class="modal-close" data-close-modal aria-label="Đóng">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="teachers.php" id="teacher-form" style="display:grid;gap:14px;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="teacher_id_input" value="">

                <div class="form-group">
                    <label for="full_name">Họ và tên *</label>
                    <input type="text" name="full_name" id="full_name" required minlength="3" maxlength="120"
                        placeholder="VD: TS. Nguyễn Văn A"
                        title="Họ và tên từ 3–120 ký tự, không chứa số hoặc ký tự đặc biệt">
                </div>
                <div class="form-group">
                    <label for="email">Email HUIT *</label>
                    <input type="email" name="email" id="email" required maxlength="150"
                        placeholder="VD: nguyenvana@huit.edu.vn">
                </div>
                <div class="form-group">
                    <label for="phone">Số điện thoại *</label>
                    <input type="tel" name="phone" id="phone" required pattern="^(0|\+84)[0-9]{9,10}$"
                        placeholder="VD: 0901234567"
                        title="Số điện thoại Việt Nam gồm 10–11 số, bắt đầu bằng 0 hoặc +84">
                    <span class="field-hint">Bắt đầu bằng 0 hoặc +84, gồm 10–11 chữ số.</span>
                </div>
                <div class="form-group">
                    <label for="expertise">Chuyên môn chính *</label>
                    <input type="text" name="expertise" id="expertise" required minlength="3" maxlength="200"
                        placeholder="VD: Lập trình Web & PHP">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Hủy</button>
                    <button type="submit" class="btn btn-primary" id="teacher-submit-btn">Thêm Giảng viên</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openTeacherCreateModal() {
    document.getElementById('teacher-modal-title').textContent = 'Thêm Giảng viên mới';
    document.getElementById('teacher-submit-btn').textContent = 'Thêm Giảng viên';
    document.getElementById('teacher_id_input').value = '';
    document.getElementById('full_name').value = '';
    document.getElementById('email').value = '';
    document.getElementById('phone').value = '';
    document.getElementById('expertise').value = '';
    openModal('teacher-modal');
}

function openTeacherEditModal(data) {
    document.getElementById('teacher-modal-title').textContent = 'Cập nhật Giảng viên';
    document.getElementById('teacher-submit-btn').textContent = 'Lưu thay đổi';
    document.getElementById('teacher_id_input').value = data.id;
    document.getElementById('full_name').value = data.full_name;
    document.getElementById('email').value = data.email;
    document.getElementById('phone').value = data.phone;
    document.getElementById('expertise').value = data.expertise;
    openModal('teacher-modal');
}
</script>

<?php renderFooter(); ?>
