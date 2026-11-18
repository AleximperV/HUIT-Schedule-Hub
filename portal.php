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

AuthService::startSession();

// Hỗ trợ đăng nhập nhanh qua đường dẫn riêng (?token=...)
if (!empty($_GET['token'])) {
    $matched = AuthService::loginTeacherByToken((string) $_GET['token']);
    if (!$matched) {
        setFlash('danger', 'Đường dẫn Cổng Giảng viên không hợp lệ hoặc đã hết hạn.');
        header('Location: login.php');
        exit;
    }
}

$user = AuthService::user();
$teachers = Teacher::all();

// Xác định giảng viên đang xem
$activeTeacherId = null;
if ($user && $user['role'] === 'teacher') {
    $activeTeacherId = (int) $user['teacher_id'];
} elseif ($user && $user['role'] === 'admin') {
    $activeTeacherId = !empty($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : (!empty($teachers) ? (int) $teachers[0]['id'] : null);
} else {
    setFlash('info', 'Vui lòng đăng nhập bằng tài khoản Giảng viên hoặc truy cập qua đường dẫn riêng.');
    header('Location: login.php');
    exit;
}

$teacher = $activeTeacherId ? Teacher::find($activeTeacherId) : null;
if (!$teacher) {
    setFlash('danger', 'Không tìm thấy thông tin giảng viên.');
    header('Location: index.php');
    exit;
}

// Giảng viên tự đăng ký / xóa tiết trống
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_availability') {
        Availability::create([
            'teacher_id' => $activeTeacherId,
            'day_of_week' => (int) $_POST['day_of_week'],
            'shift_or_periods' => (string) $_POST['shift_or_periods'],
            'preferred_branch_id' => !empty($_POST['preferred_branch_id']) ? (int) $_POST['preferred_branch_id'] : null,
            'note' => 'GV tự đăng ký qua Portal',
        ]);
        setFlash('success', 'Đã cập nhật tiết trống cá nhân.');
    } elseif ($action === 'delete_availability') {
        Availability::delete((int) $_POST['id']);
        setFlash('success', 'Đã xóa tiết trống.');
    }
    header('Location: portal.php?teacher_id=' . $activeTeacherId . '&view=' . urlencode($_GET['view'] ?? 'week'));
    exit;
}

$viewMode = ($_GET['view'] ?? 'week') === 'month' ? 'month' : 'week';
$rawDate = trim((string) ($_GET['date'] ?? date('Y-m-d')));
$dt = DateTimeImmutable::createFromFormat('Y-m-d', $rawDate) ?: new DateTimeImmutable('today');
$selectedDate = $dt->format('Y-m-d');
$todayStr = date('Y-m-d');

// Tính ngày đầu tuần (Thứ 2) & danh sách 7 ngày trong tuần
$isoWeekday = (int) $dt->format('N'); // 1=Mon .. 7=Sun
$weekStart = $dt->modify('-' . ($isoWeekday - 1) . ' days');
$weekDates = [];
foreach (ScheduleOptimizer::DAYS as $dNum => $dName) {
    $offset = $dNum - 2; // 2->0 .. 8->6
    $dObj = $weekStart->modify("+{$offset} days");
    $weekDates[$dNum] = $dObj->format('Y-m-d');
}

$prevDate = $viewMode === 'month' ? $dt->modify('-1 month')->format('Y-m-d') : $dt->modify('-7 days')->format('Y-m-d');
$nextDate = $viewMode === 'month' ? $dt->modify('+1 month')->format('Y-m-d') : $dt->modify('+7 days')->format('Y-m-d');

$buildPortalUrl = static function (array $overrides = []) use ($activeTeacherId, $viewMode, $selectedDate): string {
    $params = array_merge([
        'teacher_id' => $activeTeacherId,
        'view' => $viewMode,
        'date' => $selectedDate,
    ], $overrides);
    return 'portal.php?' . http_build_query($params);
};

$schedules = Schedule::all(['teacher_id' => $activeTeacherId]);
$availabilities = Availability::all($activeTeacherId);
$branches = Branch::all();

// Gom lịch theo [day_of_week][slot]
$grid = [];
$campusSummary = [];
$dayWarnings = [];
foreach ($schedules as $s) {
    $d = (int) $s['day_of_week'];
    $grid[$d][$s['shift_or_periods']][] = $s;
    $campusSummary[$s['branch_id']] = [
        'branch_name' => $s['branch_name'],
        'address' => $s['branch_address'],
        'count' => ($campusSummary[$s['branch_id']]['count'] ?? 0) + 1,
    ];
    if (!isset($dayWarnings[$d])) {
        $analysis = ScheduleOptimizer::analyzeTeacherDay($activeTeacherId, $d);
        $dayWarnings[$d] = $analysis['warnings'];
    }
}

$year = (int) $dt->format('Y');
$month = (int) $dt->format('n');
$daysInMonth = (int) $dt->format('t');
$firstWeekday = (int) $dt->modify('first day of this month')->format('N'); // 1=Mon .. 7=Sun

renderHeader('Cổng thông tin Giảng viên', 'portal');
?>

<div class="page-header">
    <div>
        <h1>Cổng thông tin Giảng viên: <?= e($teacher['full_name']) ?></h1>
        <p>Chuyên môn: <strong><?= e($teacher['expertise']) ?></strong> &bull; Email: <?= e($teacher['email']) ?></p>
    </div>
    <div class="no-print" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?php if (AuthService::isAdmin()): ?>
            <form method="GET" action="portal.php" style="display:flex;gap:6px;margin:0;">
                <input type="hidden" name="view" value="<?= e($viewMode) ?>">
                <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                <select name="teacher_id" onchange="this.form.submit()">
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $activeTeacherId ? 'selected' : '' ?>>
                            <?= e($t['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <a href="<?= e($buildPortalUrl(['view' => 'week'])) ?>" class="btn btn-sm <?= $viewMode === 'week' ? 'btn-primary' : 'btn-outline' ?>">Lịch theo Tuần</a>
        <a href="<?= e($buildPortalUrl(['view' => 'month'])) ?>" class="btn btn-sm <?= $viewMode === 'month' ? 'btn-primary' : 'btn-outline' ?>">Lịch theo Tháng</a>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Cơ sở cần đến dạy trong tuần</h3>
        <?php if (empty($campusSummary)): ?>
            <p style="color:#64748b;">Hiện chưa có lịch giảng dạy nào được phân.</p>
        <?php else: ?>
            <div style="display:grid;gap:10px;">
                <?php foreach ($campusSummary as $c): ?>
                    <div style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <strong><?= e($c['branch_name']) ?></strong>
                            <div style="font-size:13px;color:#64748b;"><?= e($c['address']) ?></div>
                        </div>
                        <span class="badge badge-info"><?= (int) $c['count'] ?> ca/tuần</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Lưu ý di chuyển &amp; Tối ưu lịch cá nhân</h3>
        <?php
        $hasWarn = false;
        foreach ($dayWarnings as $d => $warns) {
            if (!empty($warns)) {
                $hasWarn = true;
                foreach ($warns as $w) {
                    echo '<div class="alert alert-' . ($w['severity'] === 'high' ? 'danger' : 'warning') . '" style="margin-bottom:8px;">';
                    echo '<strong>' . e(ScheduleOptimizer::DAYS[$d] ?? '') . ':</strong> ' . e($w['message']);
                    echo '</div>';
                }
            }
        }
        if (!$hasWarn): ?>
            <div class="alert alert-success" style="margin:0;">Lịch giảng dạy hiện tại tối ưu, không có xung đột hay di chuyển gấp giữa các cơ sở.</div>
        <?php endif; ?>
    </div>
</div>

<div class="schedule-toolbar no-print">
    <form method="GET" action="portal.php" style="display:inline-flex;margin:0;">
        <input type="hidden" name="teacher_id" value="<?= $activeTeacherId ?>">
        <input type="hidden" name="view" value="<?= e($viewMode) ?>">
        <input type="date" name="date" value="<?= e($selectedDate) ?>" onchange="this.form.submit()" aria-label="Chọn ngày">
    </form>
    <a href="<?= e($buildPortalUrl(['date' => $todayStr])) ?>" class="btn btn-sky">&#128197; Hiện tại</a>
    <a href="<?= e($buildPortalUrl(['date' => $prevDate])) ?>" class="btn btn-sky">&lsaquo; Trở về</a>
    <a href="<?= e($buildPortalUrl(['date' => $nextDate])) ?>" class="btn btn-sky">Tiếp &rsaquo;</a>
</div>

<?php if ($viewMode === 'week'): ?>
<div class="card">
    <h3>Thời khóa biểu theo Tuần (<?= date('d/m/Y', strtotime($weekDates[2])) ?> &ndash; <?= date('d/m/Y', strtotime($weekDates[8])) ?>)</h3>
    <div class="table-wrap">
        <table class="timetable-grid">
            <thead>
                <tr>
                    <th>Buổi / Tiết</th>
                    <?php foreach (ScheduleOptimizer::DAYS as $dNum => $dName):
                        $colDate = $weekDates[$dNum];
                        $isToday = $colDate === $todayStr;
                        ?>
                        <th class="<?= $isToday ? 'today-col' : '' ?>">
                            <?= e($dName) ?><br>
                            <small style="font-weight:500;color:#475569;"><?= date('d/m/Y', strtotime($colDate)) ?></small>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_keys(ScheduleOptimizer::SLOTS) as $slotName): ?>
                    <tr>
                        <td><strong><?= e($slotName) ?></strong></td>
                        <?php foreach (ScheduleOptimizer::DAYS as $dNum => $dName):
                            $isToday = $weekDates[$dNum] === $todayStr;
                            ?>
                            <td class="<?= $isToday ? 'today-col' : '' ?>">
                                <?php if (!empty($grid[$dNum][$slotName])): ?>
                                    <?php foreach ($grid[$dNum][$slotName] as $item): ?>
                                        <div class="slot-card">
                                            <strong><?= e($item['subject_name']) ?></strong>
                                            <div>Phòng: <?= e($item['room']) ?></div>
                                            <div style="color:#0b4f9e;"><?= e($item['branch_name']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card">
    <h3>Lịch giảng dạy Tháng <?= $month ?>/<?= $year ?></h3>
    <div class="calendar-grid">
        <?php foreach (['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'CN'] as $wd): ?>
            <div class="cal-head"><?= $wd ?></div>
        <?php endforeach; ?>
        <?php for ($i = 1; $i < $firstWeekday; $i++): ?>
            <div class="cal-cell empty"></div>
        <?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $cellDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $isoN = (int) date('N', strtotime($cellDate)); // 1..7
            $dbDay = $isoN + 1; // 2..8
            $daySlots = $grid[$dbDay] ?? [];
            $isToday = $cellDate === $todayStr;
            ?>
            <div class="cal-cell <?= $isToday ? 'today-col' : '' ?>">
                <div class="cal-date"><?= $day ?>/<?= $month ?></div>
                <?php foreach ($daySlots as $slotList): ?>
                    <?php foreach ($slotList as $item): ?>
                        <div class="slot-card" style="padding:4px 6px;margin-bottom:4px;">
                            <strong><?= e($item['subject_name']) ?></strong>
                            <span><?= e($item['shift_or_periods']) ?> &bull; <?= e($item['room']) ?></span>
                            <div style="color:#0b4f9e;"><?= e($item['branch_name']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h3>Tiết trống cá nhân đã đăng ký (<?= count($availabilities) ?>)</h3>
    <form method="POST" action="portal.php?teacher_id=<?= $activeTeacherId ?>&view=<?= e($viewMode) ?>" class="form-grid" style="align-items:end;margin-bottom:16px;">
        <input type="hidden" name="action" value="add_availability">
        <div class="form-group">
            <label>Thứ</label>
            <select name="day_of_week" required>
                <?php foreach (ScheduleOptimizer::DAYS as $dNum => $dName): ?>
                    <option value="<?= $dNum ?>"><?= e($dName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Buổi / Tiết trống</label>
            <select name="shift_or_periods" required>
                <?php foreach (array_keys(ScheduleOptimizer::SLOTS) as $slotName): ?>
                    <option value="<?= e($slotName) ?>"><?= e($slotName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Cơ sở ưu tiên</label>
            <select name="preferred_branch_id">
                <option value="">-- Linh hoạt --</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int) $b['id'] ?>"><?= e($b['branch_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">+ Đăng ký tiết trống</button>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Thứ</th>
                    <th>Buổi / Tiết</th>
                    <th>Cơ sở ưu tiên</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($availabilities as $av): ?>
                    <tr>
                        <td><?= e(ScheduleOptimizer::DAYS[(int) $av['day_of_week']] ?? '') ?></td>
                        <td><span class="badge badge-info"><?= e($av['shift_or_periods']) ?></span></td>
                        <td><?= e($av['preferred_branch_name'] ?? 'Linh hoạt') ?></td>
                        <td>
                            <form method="POST" action="portal.php?teacher_id=<?= $activeTeacherId ?>&view=<?= e($viewMode) ?>" style="margin:0;">
                                <input type="hidden" name="action" value="delete_availability">
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
