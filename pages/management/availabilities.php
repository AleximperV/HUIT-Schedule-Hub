<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/Views/layout.php';

use App\Models\Availability;
use App\Models\Branch;
use App\Models\Teacher;
use App\Services\AuthService;
use App\Services\ScheduleOptimizer;

AuthService::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $dayOfWeek = (int) ($_POST['day_of_week'] ?? 0);
        $slot = trim((string) ($_POST['shift_or_periods'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($teacherId <= 0 || !isset(ScheduleOptimizer::DAYS[$dayOfWeek]) || !isset(ScheduleOptimizer::SLOTS[$slot])) {
            setFlash('danger', 'Vui lòng chọn đầy đủ Giảng viên, Thứ và Buổi/Tiết hợp lệ.');
            header('Location: availabilities.php');
            exit;
        }
        if (mb_strlen($note, 'UTF-8') > 200) {
            setFlash('danger', 'Ghi chú tối đa 200 ký tự.');
            header('Location: availabilities.php');
            exit;
        }

        Availability::create([
            'teacher_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'shift_or_periods' => $slot,
            'preferred_branch_id' => !empty($_POST['preferred_branch_id']) ? (int) $_POST['preferred_branch_id'] : null,
            'note' => $note,
        ]);
        setFlash('success', 'Đã lưu tiết trống của giảng viên.');
    } elseif ($action === 'delete') {
        Availability::delete((int) $_POST['id']);
        setFlash('success', 'Đã xóa tiết trống.');
    }
    header('Location: availabilities.php');
    exit;
}

$teachers = Teacher::all();
$branches = Branch::all();
$availabilities = Availability::all();
$pgAvail = paginateArray($availabilities, 10);

renderHeader('Quản lý Tiết trống Giảng viên', 'availabilities');
?>

<div class="page-header">
    <div>
        <h1>Quản lý Tiết trống Giảng viên</h1>
        <p>Khai báo khung giờ rảnh của giảng viên để hệ thống phân lịch tự động ưu tiên và tối ưu di chuyển.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openModal('availability-modal')">+ Thêm Tiết trống</button>
</div>

<div class="card">
    <h3>Danh sách Tiết trống đã đăng ký (<?= count($availabilities) ?>)</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Giảng viên</th>
                    <th>Thứ</th>
                    <th>Buổi / Tiết</th>
                    <th>Cơ sở ưu tiên</th>
                    <th>Ghi chú</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pgAvail['items'] as $av): ?>
                    <tr>
                        <td><strong><?= e($av['teacher_name']) ?></strong></td>
                        <td><?= e(ScheduleOptimizer::DAYS[(int) $av['day_of_week']] ?? '') ?></td>
                        <td><span class="badge badge-info"><?= e($av['shift_or_periods']) ?></span></td>
                        <td><?= e($av['preferred_branch_name'] ?? 'Linh hoạt các cơ sở') ?></td>
                        <td><?= e($av['note'] ?? '') ?></td>
                        <td>
                            <form method="POST" action="availabilities.php" onsubmit="return confirm('Xóa tiết trống này?');" style="margin:0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $av['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderPagination($pgAvail); ?>
</div>

<div class="modal-backdrop" id="availability-modal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="avail-modal-title">
        <div class="modal-header">
            <h3 id="avail-modal-title">Đăng ký Tiết trống mới</h3>
            <button type="button" class="modal-close" data-close-modal aria-label="Đóng">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="availabilities.php" style="display:grid;gap:14px;">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="av_teacher">Giảng viên *</label>
                    <select name="teacher_id" id="av_teacher" required>
                        <option value="">-- Chọn giảng viên --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= e($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="av_day">Thứ *</label>
                    <select name="day_of_week" id="av_day" required>
                        <?php foreach (ScheduleOptimizer::DAYS as $dNum => $dName): ?>
                            <option value="<?= $dNum ?>"><?= e($dName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="av_slot">Buổi / Tiết trống *</label>
                    <select name="shift_or_periods" id="av_slot" required>
                        <?php foreach (array_keys(ScheduleOptimizer::SLOTS) as $slotName): ?>
                            <option value="<?= e($slotName) ?>"><?= e($slotName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="av_branch">Cơ sở ưu tiên</label>
                    <select name="preferred_branch_id" id="av_branch">
                        <option value="">-- Linh hoạt --</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int) $b['id'] ?>"><?= e($b['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="av_note">Ghi chú</label>
                    <input type="text" name="note" id="av_note" maxlength="200" placeholder="VD: Ưu tiên dạy buổi sáng">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu tiết trống</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
