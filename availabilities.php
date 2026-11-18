<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/layout.php';

use App\Models\Availability;
use App\Models\Branch;
use App\Models\Teacher;
use App\Services\AuthService;
use App\Services\ScheduleOptimizer;

AuthService::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        Availability::create([
            'teacher_id' => (int) $_POST['teacher_id'],
            'day_of_week' => (int) $_POST['day_of_week'],
            'shift_or_periods' => (string) $_POST['shift_or_periods'],
            'preferred_branch_id' => !empty($_POST['preferred_branch_id']) ? (int) $_POST['preferred_branch_id'] : null,
            'note' => (string) ($_POST['note'] ?? ''),
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

renderHeader('Quản lý Tiết trống Giảng viên', 'availabilities');
?>

<div class="page-header">
    <div>
        <h1>Quản lý Tiết trống Giảng viên</h1>
        <p>Khai báo khung giờ rảnh của giảng viên để hệ thống phân lịch tự động ưu tiên và tối ưu di chuyển.</p>
    </div>
</div>

<div class="card">
    <h3>Đăng ký Tiết trống mới</h3>
    <form method="POST" action="availabilities.php" class="form-grid" style="align-items:end;">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label for="av_teacher">Giảng viên</label>
            <select name="teacher_id" id="av_teacher" required>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"><?= e($t['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="av_day">Thứ</label>
            <select name="day_of_week" id="av_day" required>
                <?php foreach (ScheduleOptimizer::DAYS as $dNum => $dName): ?>
                    <option value="<?= $dNum ?>"><?= e($dName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="av_slot">Buổi / Tiết trống</label>
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
            <button type="submit" class="btn btn-primary">+ Thêm tiết trống</button>
        </div>
    </form>
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
                <?php foreach ($availabilities as $av): ?>
                    <tr>
                        <td><strong><?= e($av['teacher_name']) ?></strong></td>
                        <td><?= e(ScheduleOptimizer::DAYS[(int) $av['day_of_week']] ?? '') ?></td>
                        <td><span class="badge badge-info"><?= e($av['shift_or_periods']) ?></span></td>
                        <td><?= e($av['preferred_branch_name'] ?? 'Linh hoạt các cơ sở') ?></td>
                        <td><?= e($av['note'] ?? '') ?></td>
                        <td>
                            <form method="POST" action="availabilities.php" style="margin:0;">
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
</div>

<?php renderFooter(); ?>
