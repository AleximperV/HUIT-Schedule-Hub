<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Database;
use App\Config\Env;
use App\Services\ScheduleOptimizer;
use Faker\Factory;

Env::load();

$host = Env::get('DB_HOST', '127.0.0.1');
$port = Env::get('DB_PORT', '3306');
$dbname = Env::get('DB_NAME', 'huit_db');
$user = Env::get('DB_USER', 'root');
$pass = Env::get('DB_PASS', '');

// Kết nối tạo DB & bảng nếu chưa tồn tại
$rootPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$schemaSql = file_get_contents(__DIR__ . '/schema.sql');
$rootPdo->exec($schemaSql);

$pdo = Database::getConnection();
$faker = Factory::create('vi_VN');
$faker->seed(2026);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE schedules');
$pdo->exec('TRUNCATE TABLE teacher_availabilities');
$pdo->exec('TRUNCATE TABLE teachers');
$pdo->exec('TRUNCATE TABLE branches');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// 1. Seed Cơ sở (Branches)
$branchesData = [
    ['Cơ sở chính Tân Phú', '140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM'],
    ['Cơ sở Phòng máy Chế Lan Viên', '31 Chế Lan Viên, P. Tây Thạnh, Q. Tân Phú, TP.HCM'],
    ['Trung tâm Thí nghiệm - Thực hành', '247 Tân Kỳ Tân Quý, Q. Tân Phú, TP.HCM'],
    ['Trung tâm Liên kết Đào tạo', '181 Lê Đức Thọ, Q. Gò Vấp, TP.HCM'],
];

$branchIds = [];
$stmtBranch = $pdo->prepare('INSERT INTO branches (branch_name, address) VALUES (?, ?)');
foreach ($branchesData as $b) {
    $stmtBranch->execute($b);
    $branchIds[] = (int) $pdo->lastInsertId();
}

// 2. Seed Giảng viên (Teachers) bằng Faker + Chuyên môn HUIT
$expertiseCatalog = [
    'Công nghệ thông tin - Lập trình Web & PHP' => ['Lập trình Web với PHP & MySQL', 'Phát triển ứng dụng mã nguồn mở', 'Thiết kế giao diện Web'],
    'Hệ thống thông tin - Cơ sở dữ liệu' => ['Hệ quản trị Cơ sở dữ liệu MySQL', 'Phân tích thiết kế hệ thống', 'Kho dữ liệu'],
    'Trí tuệ nhân tạo & Khoa học dữ liệu' => ['Nhập môn Trí tuệ nhân tạo', 'Học máy ứng dụng (Python)', 'Khai phá dữ liệu'],
    'Mạng máy tính & An toàn thông tin' => ['Mạng máy tính căn bản', 'An toàn và Bảo mật hệ thống', 'Quản trị mạng Linux'],
    'Công nghệ thực phẩm & Vi sinh' => ['Công nghệ chế biến thực phẩm', 'Vi sinh vật học thực phẩm', 'Kiểm nghiệm chất lượng thực phẩm'],
    'Kế toán - Tài chính doanh nghiệp' => ['Nguyên lý Kế toán', 'Kế toán tài chính doanh nghiệp', 'Phân tích báo cáo tài chính'],
    'Quản trị kinh doanh & Logistics' => ['Quản trị học đại cương', 'Quản trị chuỗi cung ứng', 'Marketing căn bản'],
    'Điện - Điện tử & Tự động hóa IoT' => ['Kỹ thuật điện tử số', 'Lập trình nhúng và IoT', 'Điều khiển tự động'],
    'Ngôn ngữ Anh - Tiếng Anh chuyên ngành' => ['Tiếng Anh giao tiếp quốc tế', 'Tiếng Anh chuyên ngành CNTT', 'Kỹ năng thuyết trình tiếng Anh'],
    'Toán ứng dụng & Xác suất thống kê' => ['Xác suất thống kê', 'Toán rời rạc', 'Phương pháp tính'],
];

$defaultPassHash = password_hash(Env::get('DEFAULT_TEACHER_PASSWORD', 'huit2026'), PASSWORD_DEFAULT);
$stmtTeacher = $pdo->prepare(
    'INSERT INTO teachers (full_name, email, phone, expertise, password_hash, portal_token)
     VALUES (?, ?, ?, ?, ?, ?)'
);

$teachers = [];
$expertises = array_keys($expertiseCatalog);
$sampleNames = [
    'TS. Nguyễn Minh Tuấn',
    'ThS. Trần Thị Lan Anh',
    'ThS. Lê Hoàng Nam',
    'TS. Phạm Quốc Bảo',
    'ThS. Võ Thị Thu Hà',
    'ThS. Đặng Quang Huy',
    'TS. Huỳnh Ngọc Mai',
    'ThS. Bùi Văn Khánh',
    'ThS. Ngô Thanh Thảo',
    'TS. Đỗ Đức Thịnh',
];

foreach ($expertises as $idx => $exp) {
    $name = $sampleNames[$idx] ?? ('ThS. ' . $faker->name());
    $emailSlug = 'gv' . ($idx + 1) . '@huit.edu.vn';
    $phone = '09' . $faker->numerify('########');
    $token = bin2hex(random_bytes(16));

    $stmtTeacher->execute([$name, $emailSlug, $phone, $exp, $defaultPassHash, $token]);
    $teachers[] = [
        'id' => (int) $pdo->lastInsertId(),
        'full_name' => $name,
        'email' => $emailSlug,
        'expertise' => $exp,
        'subjects' => $expertiseCatalog[$exp],
    ];
}

// 3. Seed Tiết trống (teacher_availabilities) bằng Faker
$slotNames = array_keys(ScheduleOptimizer::SLOTS);
$days = [2, 3, 4, 5, 6, 7];
$stmtAvail = $pdo->prepare(
    'INSERT IGNORE INTO teacher_availabilities (teacher_id, day_of_week, shift_or_periods, preferred_branch_id, note)
     VALUES (?, ?, ?, ?, ?)'
);

foreach ($teachers as $t) {
    $numSlots = $faker->numberBetween(4, 7);
    for ($i = 0; $i < $numSlots; $i++) {
        $day = $faker->randomElement($days);
        $slot = $faker->randomElement($slotNames);
        $prefBranch = $faker->boolean(70) ? $faker->randomElement($branchIds) : null;
        $note = $prefBranch ? 'Ưu tiên dạy tại cơ sở đã chọn' : 'Linh hoạt các cơ sở';
        $stmtAvail->execute([$t['id'], $day, $slot, $prefBranch, $note]);
    }
}

// 4. Seed Lịch dạy mẫu (schedules) không trùng phòng & giảng viên
$stmtSched = $pdo->prepare(
    'INSERT INTO schedules (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);

$roomsByBranch = [
    $branchIds[0] => ['A201', 'A304', 'B102', 'B205', 'C401'],
    $branchIds[1] => ['PM01', 'PM02', 'PM03', 'PM04'],
    $branchIds[2] => ['LAB-01', 'LAB-02', 'TN-03'],
    $branchIds[3] => ['LK-101', 'LK-102', 'LK-203'],
];

$usedTeacherSlots = [];
$usedRoomSlots = [];
$insertedCount = 0;

foreach ($teachers as $idx => $t) {
    $classesCount = $faker->numberBetween(2, 4);
    for ($c = 0; $c < $classesCount; $c++) {
        $day = $faker->randomElement($days);
        $slot = $faker->randomElement($slotNames);
        $branchId = $faker->randomElement($branchIds);
        $room = $faker->randomElement($roomsByBranch[$branchId]);

        $tKey = $t['id'] . '_' . $day . '_' . $slot;
        $rKey = $branchId . '_' . $room . '_' . $day . '_' . $slot;
        if (isset($usedTeacherSlots[$tKey]) || isset($usedRoomSlots[$rKey])) {
            continue;
        }

        $usedTeacherSlots[$tKey] = true;
        $usedRoomSlots[$rKey] = true;

        $subject = $faker->randomElement($t['subjects']);
        $stmtSched->execute([
            $t['id'],
            $branchId,
            $subject,
            $room,
            $day,
            $slot,
            'Học kỳ 1 - Năm học 2026-2027',
        ]);
        $insertedCount++;
    }
}

if (PHP_SAPI === 'cli') {
    echo "Seeded successfully: " . count($branchIds) . " branches, " . count($teachers) . " teachers, {$insertedCount} schedules.\n";
}
