<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/layout.php';

use App\Models\Availability;
use App\Models\Branch;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Services\AuthService;
use App\Services\ScheduleOptimizer;

AuthService::requireAdmin();

$errors = [];
$editing = null;

if (isset($_GET['edit'])) {
    $editing = Schedule::find((int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && Schedule::delete($id)) {
            setFlash('success', 'Đã xóa lịch dạy #' . $id . '.');
        }
        header('Location: schedules.php');
        exit;
    }

    if ($action === 'save') {
        $scheduleId = !empty($_POST['schedule_id']) ? (int) $_POST['schedule_id'] : null;
        $data = [
            'teacher_id' => (int) ($_POST['teacher_id'] ?? 0),
            'branch_id' => (int) ($_POST['branch_id'] ?? 0),
            'subject_name' => trim((string) ($_POST['subject_name'] ?? '')),
            'room' => trim((string) ($_POST['room'] ?? '')),
            'day_of_week' => (int) ($_POST['day_of_week'] ?? 2),
            'shift_or_periods' => trim((string) ($_POST['shift_or_periods'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];

        if ($data['teacher_id'] <= 0 || $data['branch_id'] <= 0 || $data['subject_name'] === '' || $data['room'] === '' || $data['shift_or_periods'] === '') {
            $errors[] = 'Vui lòng điền đầy đủ thông tin học phần, giảng viên, cơ sở, phòng và khung giờ.';
        } else {
            $conflicts = ScheduleOptimizer::checkConflicts(
                $data['teacher_id'],
                $data['branch_id'],
                $data['room'],
                $data['day_of_week'],
                $data['shift_or_periods'],
                $scheduleId
            );

            if (!empty($conflicts)) {
                foreach ($conflicts as $c) {
                    $errors[] = $c['message'];
                }
            } else {
                $branchInfo = Branch::find($data['branch_id']);
                $analysis = ScheduleOptimizer::analyzeTeacherDay(
                    $data['teacher_id'],
                    $data['day_of_week'],
                    [
                        'branch_id' => $data['branch_id'],
                        'branch_name' => $branchInfo['branch_name'] ?? '',
                        'shift_or_periods' => $data['shift_or_periods'],
                        'subject_name' => $data['subject_name'],
                    ],
                    $scheduleId
                );

                if ($scheduleId) {
                    Schedule::update($scheduleId, $data);
                    $msg = 'Đã cập nhật lịch dạy thành công.';
                } else {
                    Schedule::create($data);
                    $msg = 'Đã phân lịch dạy mới thành công.';
                }

                $highWarns = array_filter($analysis['warnings'], static fn ($w) => $w['severity'] !== 'low');
                if (!empty($highWarns)) {
                    $warnText = implode(' | ', array_column($highWarns, 'message'));
                    setFlash('warning', $msg . ' Lưu ý tối ưu: ' . $warnText);
                } else {
                    setFlash('success', $msg);
                }
                header('Location: schedules.php');
                exit;
            }
        }
    }
}

$branches = Branch::all();
$teachers = Teacher::all();
$schedules = Schedule::all();
$availabilities = Availability::all();

// Lọc ra các tiết trống chưa bị xếp lịch
$occupiedKeys = [];
foreach ($schedules as $s) {
    $occupiedKeys[$s['teacher_id'] . '_' . $s['day_of_week'] . '_' . $s['shift_or_periods']] = true;
}
$freeAvailabilities = array_values(array_filter($availabilities, static function ($a) use ($occupiedKeys) {
    $k = $a['teacher_id'] . '_' . $a['day_of_week'] . '_' . $a['shift_or_periods'];
    return !isset($occupiedKeys[$k]);
}));

renderHeader('Phân lịch Thông minh', 'schedules');
?>

<div class="page-header">
    <div>
        <h1>Phân lịch Giảng dạy &amp; Gợi ý Thông minh</h1>
        <p>Tự động kiểm tra trùng lịch, gợi ý giảng viên đúng chuyên môn và tối ưu di chuyển giữa các cơ sở.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php foreach ($errors as $err): ?>
            showToast(<?= json_encode($err, JSON_UNESCAPED_UNICODE) ?>, 'danger');
        <?php endforeach; ?>
    });
    </script>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3><?= $editing ? 'Cập nhật Lịch dạy #' . (int) $editing['id'] : 'Thêm & Phân lịch mới' ?></h3>
        <form method="POST" action="schedules.php" style="display:grid;gap:12px;">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="schedule_id" id="schedule_id" value="<?= $editing ? (int) $editing['id'] : '' ?>">

            <div class="form-group">
                <label for="subject_name">Tên học phần</label>
                <input type="text" name="subject_name" id="subject_name" required
                    value="<?= e($editing['subject_name'] ?? ($_POST['subject_name'] ?? '')) ?>"
                    placeholder="VD: Lập trình Web với PHP & MySQL">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="branch_id">Cơ sở giảng dạy</label>
                    <select name="branch_id" id="branch_id" required>
                        <option value="">-- Chọn cơ sở --</option>
                        <?php $selBranch = (int) ($editing['branch_id'] ?? ($_POST['branch_id'] ?? 0)); ?>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= $selBranch === (int) $b['id'] ? 'selected' : '' ?>>
                                <?= e($b['branch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="room">Phòng học</label>
                    <input type="text" name="room" id="room" required
                        value="<?= e($editing['room'] ?? ($_POST['room'] ?? '')) ?>"
                        placeholder="VD: A201, PM02">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="day_of_week">Thứ</label>
                    <select name="day_of_week" id="day_of_week" required>
                        <?php $selDay = (int) ($editing['day_of_week'] ?? ($_POST['day_of_week'] ?? 2)); ?>
                        <?php foreach (ScheduleOptimizer::DAYS as $dNum => $dName): ?>
                            <option value="<?= $dNum ?>" <?= $selDay === $dNum ? 'selected' : '' ?>><?= e($dName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="shift_or_periods">Buổi / Tiết học</label>
                    <select name="shift_or_periods" id="shift_or_periods" required>
                        <?php $selSlot = $editing['shift_or_periods'] ?? ($_POST['shift_or_periods'] ?? ''); ?>
                        <?php foreach (array_keys(ScheduleOptimizer::SLOTS) as $slotName): ?>
                            <option value="<?= e($slotName) ?>" <?= $selSlot === $slotName ? 'selected' : '' ?>><?= e($slotName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="teacher_id">Giảng viên phụ trách</label>
                <div style="display:flex;gap:8px;">
                    <select name="teacher_id" id="teacher_id" required style="flex:1;">
                        <option value="">-- Chọn giảng viên --</option>
                        <?php $selTeacher = (int) ($editing['teacher_id'] ?? ($_POST['teacher_id'] ?? 0)); ?>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= $selTeacher === (int) $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['full_name']) ?> (<?= e($t['expertise']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="btn-recommend" class="btn btn-accent">Gợi ý GV</button>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Ghi chú (tùy chọn)</label>
                <input type="text" name="notes" id="notes" value="<?= e($editing['notes'] ?? '') ?>" placeholder="VD: Lớp 14DHTH01 - Tuần 1-15">
            </div>

            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?= $editing ? 'Cập nhật lịch' : 'Lưu phân lịch' ?></button>
                <?php if ($editing): ?>
                    <a href="schedules.php" class="btn btn-outline">Hủy sửa</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Gợi ý Giảng viên Phù hợp &amp; Tối ưu</h3>
        <p style="font-size:13px;color:#64748b;margin-top:0;">
            Nhập tên học phần, chọn cơ sở &amp; khung giờ bên trái rồi nhấn <strong>Gợi ý GV</strong> để xếp hạng giảng viên theo chuyên môn, tiết trống và quãng đường di chuyển.
        </p>
        <div id="recommend-results" class="recommend-list">
            <div class="alert alert-info" style="margin:0;">Chưa chạy phân tích. Nhấn nút <strong>Gợi ý GV</strong> để xem danh sách đề xuất.</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="page-header" style="margin-bottom: 10px;">
        <h3 style="margin:0;">Phân lịch nhanh từ Tiết trống Giảng viên</h3>
        <span class="badge badge-success"><?= count($freeAvailabilities) ?> khung giờ khả dụng</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Giảng viên</th>
                    <th>Chuyên môn</th>
                    <th>Thứ</th>
                    <th>Buổi / Tiết trống</th>
                    <th>Cơ sở ưu tiên</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($freeAvailabilities)): ?>
                    <tr><td colspan="6" style="color:#64748b;text-align:center;">Tất cả tiết trống đã được xếp lịch hoặc chưa có dữ liệu.</td></tr>
                <?php else: ?>
                    <?php foreach (array_slice($freeAvailabilities, 0, 12) as $av): ?>
                        <tr>
                            <td><strong><?= e($av['teacher_name']) ?></strong></td>
                            <td style="font-size:13px;color:#475569;"><?= e($av['expertise']) ?></td>
                            <td><?= e(ScheduleOptimizer::DAYS[(int) $av['day_of_week']] ?? '') ?></td>
                            <td><span class="badge badge-info"><?= e($av['shift_or_periods']) ?></span></td>
                            <td><?= e($av['preferred_branch_name'] ?? 'Linh hoạt') ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary"
                                    onclick="fillSlotFromAvailability(<?= (int) $av['teacher_id'] ?>, <?= (int) $av['day_of_week'] ?>, '<?= e($av['shift_or_periods']) ?>', <?= (int) ($av['preferred_branch_id'] ?? 0) ?>)">
                                    Điền vào form
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>Danh sách Lịch dạy hiện tại (<?= count($schedules) ?>)</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Thứ / Tiết</th>
                    <th>Học phần</th>
                    <th>Giảng viên</th>
                    <th>Cơ sở &amp; Phòng</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $s): ?>
                    <tr>
                        <td>#<?= (int) $s['id'] ?></td>
                        <td>
                            <strong><?= e(ScheduleOptimizer::DAYS[(int) $s['day_of_week']] ?? '') ?></strong><br>
                            <span class="badge badge-info"><?= e($s['shift_or_periods']) ?></span>
                        </td>
                        <td><strong><?= e($s['subject_name']) ?></strong></td>
                        <td><?= e($s['teacher_name']) ?></td>
                        <td>
                            <?= e($s['branch_name']) ?> &mdash; <span class="badge badge-secondary"><?= e($s['room']) ?></span>
                        </td>
                        <td style="display:flex;gap:6px;">
                            <a href="schedules.php?edit=<?= (int) $s['id'] ?>" class="btn btn-sm btn-outline">Sửa</a>
                            <form method="POST" action="schedules.php" onsubmit="return confirm('Xóa lịch dạy này?');" style="margin:0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
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
