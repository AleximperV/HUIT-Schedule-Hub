<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Views/layout.php';

use App\Models\Branch;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Services\AuthService;
use App\Services\ScheduleOptimizer;

AuthService::startSession();
if (!AuthService::user()) {
    header('Location: login.php');
    exit;
}

$branchId = !empty($_GET['branch_id']) ? (int) $_GET['branch_id'] : null;
$teacherId = !empty($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : null;
$search = trim((string) ($_GET['q'] ?? ''));

$branches = Branch::all();
$teachers = Teacher::all();
$schedules = Schedule::all([
    'branch_id' => $branchId,
    'teacher_id' => $teacherId,
    'q' => $search,
]);
$pgSchedules = paginateArray($schedules, 10);

$branchStats = Schedule::statsByBranch($teacherId);
$teacherStats = Schedule::statsByTeacher($branchId);
$dayStatsRaw = Schedule::statsByDay($branchId, $teacherId);

$dayLabels = [];
$dayCounts = [];
$dayMap = [];
foreach ($dayStatsRaw as $row) {
    $dayMap[(int) $row['day_of_week']] = (int) $row['total_classes'];
}
foreach (ScheduleOptimizer::DAYS as $dNum => $dName) {
    $dayLabels[] = $dName;
    $dayCounts[] = $dayMap[$dNum] ?? 0;
}

// Đếm số cảnh báo tối ưu (chuyển cơ sở gấp / khoảng trống chết) trên tập lịch hiện tại
$optimizationWarnings = [];
$checkedPairs = [];
foreach ($schedules as $s) {
    $key = $s['teacher_id'] . '_' . $s['day_of_week'];
    if (isset($checkedPairs[$key])) {
        continue;
    }
    $checkedPairs[$key] = true;
    $analysis = ScheduleOptimizer::analyzeTeacherDay((int) $s['teacher_id'], (int) $s['day_of_week']);
    foreach ($analysis['warnings'] as $w) {
        if ($w['severity'] !== 'low') {
            $optimizationWarnings[] = [
                'teacher_name' => $s['teacher_name'],
                'day' => ScheduleOptimizer::DAYS[(int) $s['day_of_week']] ?? ('Thứ ' . $s['day_of_week']),
                'message' => $w['message'],
                'severity' => $w['severity'],
            ];
        }
    }
}

renderHeader('Tổng quan Lịch giảng dạy', 'dashboard');
?>

<div class="page-header">
    <div>
        <h1>Tổng quan Phân lịch Giảng dạy Đa cơ sở</h1>
        <p>Theo dõi thời khóa biểu toàn trường, lọc nhanh theo cơ sở hoặc giảng viên và kiểm tra tối ưu hóa di chuyển.</p>
    </div>
    <?php if (AuthService::isAdmin()): ?>
        <a href="schedules.php" class="btn btn-primary">+ Phân lịch mới</a>
    <?php else: ?>
        <a href="portal.php" class="btn btn-accent">Vào Cổng Giảng viên</a>
    <?php endif; ?>
</div>

<div class="grid-4">
    <div class="kpi-card">
        <div class="kpi-label">Lịch dạy đang hiển thị</div>
        <div class="kpi-value"><?= count($schedules) ?></div>
    </div>
    <div class="kpi-card accent">
        <div class="kpi-label">Cơ sở đào tạo</div>
        <div class="kpi-value"><?= count($branches) ?></div>
    </div>
    <div class="kpi-card success">
        <div class="kpi-label">Giảng viên</div>
        <div class="kpi-value"><?= count($teachers) ?></div>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-label">Cảnh báo di chuyển / khoảng trống</div>
        <div class="kpi-value"><?= count($optimizationWarnings) ?></div>
    </div>
</div>

<div class="card">
    <form method="GET" action="index.php" class="form-grid" style="align-items: end;">
        <div class="form-group">
            <label for="filter_branch">Lọc theo Cơ sở</label>
            <select name="branch_id" id="filter_branch">
                <option value="">-- Tất cả cơ sở --</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= $branchId === (int) $b['id'] ? 'selected' : '' ?>>
                        <?= e($b['branch_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="filter_teacher">Lọc theo Giảng viên</label>
            <select name="teacher_id" id="filter_teacher">
                <option value="">-- Tất cả giảng viên --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $teacherId === (int) $t['id'] ? 'selected' : '' ?>>
                        <?= e($t['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="filter_q">Tìm học phần / phòng</label>
            <input type="text" name="q" id="filter_q" value="<?= e($search) ?>" placeholder="VD: PHP, A201, MySQL...">
        </div>
        <div class="form-group" style="flex-direction: row; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="flex:1;">Lọc dữ liệu</button>
            <a href="index.php" class="btn btn-outline">Đặt lại</a>
        </div>
    </form>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Phân bổ lịch dạy theo Cơ sở</h3>
        <canvas id="branchChart" height="190"></canvas>
    </div>
    <div class="card">
        <h3>Phân bổ thời gian theo Thứ trong tuần</h3>
        <canvas id="dayChart" height="190"></canvas>
    </div>
</div>

<div class="card">
    <h3>Phân bổ số buổi dạy theo Giảng viên</h3>
    <canvas id="teacherChart" height="110"></canvas>
</div>

<?php if (!empty($optimizationWarnings)): ?>
<div class="card">
    <h3>Cảnh báo Tối ưu hóa Lịch trình</h3>
    <div style="display: grid; gap: 8px;">
        <?php foreach ($optimizationWarnings as $w): ?>
            <div class="alert alert-<?= $w['severity'] === 'high' ? 'danger' : 'warning' ?>" style="margin-bottom:0;">
                <strong><?= e($w['teacher_name']) ?> (<?= e($w['day']) ?>):</strong> <?= e($w['message']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="page-header" style="margin-bottom: 12px;">
        <h3 style="margin:0;">Danh sách Thời khóa biểu</h3>
        <span class="badge badge-info"><?= count($schedules) ?> lớp học phần</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Thứ</th>
                    <th>Buổi / Tiết</th>
                    <th>Học phần</th>
                    <th>Giảng viên</th>
                    <th>Cơ sở</th>
                    <th>Phòng</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgSchedules['items'])): ?>
                    <tr><td colspan="6" style="text-align:center;color:#64748b;">Không có lịch dạy phù hợp với bộ lọc.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgSchedules['items'] as $row): ?>
                        <tr>
                            <td><strong><?= e(ScheduleOptimizer::DAYS[(int) $row['day_of_week']] ?? ('Thứ ' . $row['day_of_week'])) ?></strong></td>
                            <td><span class="badge badge-info"><?= e($row['shift_or_periods']) ?></span></td>
                            <td>
                                <strong><?= e($row['subject_name']) ?></strong>
                                <?php if (!empty($row['notes'])): ?>
                                    <div style="font-size:12px;color:#64748b;"><?= e($row['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($row['teacher_name']) ?>
                                <div style="font-size:12px;color:#64748b;"><?= e($row['teacher_expertise']) ?></div>
                            </td>
                            <td>
                                <?= e($row['branch_name']) ?>
                                <div style="font-size:12px;color:#64748b;"><?= e($row['branch_address']) ?></div>
                            </td>
                            <td><span class="badge badge-secondary"><?= e($row['room']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php renderPagination($pgSchedules); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const branchLabels = <?= json_encode(array_column($branchStats, 'branch_name'), JSON_UNESCAPED_UNICODE) ?>;
    const branchData = <?= json_encode(array_map('intval', array_column($branchStats, 'total_classes'))) ?>;

    new Chart(document.getElementById('branchChart'), {
        type: 'doughnut',
        data: {
            labels: branchLabels,
            datasets: [{
                data: branchData,
                backgroundColor: ['#0b4f9e', '#f59e0b', '#16a34a', '#0284c7', '#8b5cf6']
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });

    const dayLabels = <?= json_encode($dayLabels, JSON_UNESCAPED_UNICODE) ?>;
    const dayData = <?= json_encode($dayCounts) ?>;

    new Chart(document.getElementById('dayChart'), {
        type: 'bar',
        data: {
            labels: dayLabels,
            datasets: [{
                label: 'Số ca giảng dạy',
                data: dayData,
                backgroundColor: '#0b4f9e',
                borderRadius: 6
            }]
        },
        options: {
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });

    const teacherLabels = <?= json_encode(array_column($teacherStats, 'full_name'), JSON_UNESCAPED_UNICODE) ?>;
    const teacherData = <?= json_encode(array_map('intval', array_column($teacherStats, 'total_classes'))) ?>;

    new Chart(document.getElementById('teacherChart'), {
        type: 'bar',
        data: {
            labels: teacherLabels,
            datasets: [{
                label: 'Số lớp đang phụ trách',
                data: teacherData,
                backgroundColor: '#f59e0b',
                borderRadius: 6
            }]
        },
        options: {
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>

<?php renderFooter(); ?>
