<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ScheduleOptimizer;

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    if ($action === 'recommend_teachers') {
        $subjectName = trim((string) ($_GET['subject_name'] ?? ''));
        $branchId = (int) ($_GET['branch_id'] ?? 0);
        $dayOfWeek = (int) ($_GET['day_of_week'] ?? 0);
        $shift = trim((string) ($_GET['shift_or_periods'] ?? ''));
        $excludeId = !empty($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : null;

        if ($branchId <= 0 || $dayOfWeek < 2 || $shift === '') {
            echo json_encode(['ok' => false, 'error' => 'Thiếu thông tin cơ sở, thứ hoặc buổi/tiết.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $recommendations = ScheduleOptimizer::recommendTeachers($subjectName, $branchId, $dayOfWeek, $shift, $excludeId);
        echo json_encode(['ok' => true, 'recommendations' => $recommendations], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Action không hợp lệ.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
