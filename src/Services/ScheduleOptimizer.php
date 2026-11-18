<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\Availability;
use App\Models\Schedule;
use App\Models\Teacher;

class ScheduleOptimizer
{
    public const DAYS = [
        2 => 'Thứ 2',
        3 => 'Thứ 3',
        4 => 'Thứ 4',
        5 => 'Thứ 5',
        6 => 'Thứ 6',
        7 => 'Thứ 7',
        8 => 'Chủ nhật',
    ];

    public const SLOTS = [
        'Sáng (Tiết 1-3)' => ['start' => 1, 'end' => 3, 'order' => 1, 'session' => 'Sáng'],
        'Sáng (Tiết 4-6)' => ['start' => 4, 'end' => 6, 'order' => 2, 'session' => 'Sáng'],
        'Chiều (Tiết 7-9)' => ['start' => 7, 'end' => 9, 'order' => 3, 'session' => 'Chiều'],
        'Chiều (Tiết 10-12)' => ['start' => 10, 'end' => 12, 'order' => 4, 'session' => 'Chiều'],
        'Tối (Tiết 13-15)' => ['start' => 13, 'end' => 15, 'order' => 5, 'session' => 'Tối'],
    ];

    public static function parseSlotRange(string $shiftOrPeriods): array
    {
        $trimmed = trim($shiftOrPeriods);
        if (isset(self::SLOTS[$trimmed])) {
            return self::SLOTS[$trimmed];
        }

        if (preg_match('/(\d+)\s*[-–]\s*(\d+)/', $trimmed, $m)) {
            $start = (int) $m[1];
            $end = (int) $m[2];
            $order = (int) ceil($start / 3);
            $session = $start <= 6 ? 'Sáng' : ($start <= 12 ? 'Chiều' : 'Tối');
            return ['start' => $start, 'end' => $end, 'order' => $order, 'session' => $session];
        }

        $lower = mb_strtolower($trimmed, 'UTF-8');
        if (str_contains($lower, 'sáng')) {
            return ['start' => 1, 'end' => 6, 'order' => 1, 'session' => 'Sáng'];
        }
        if (str_contains($lower, 'chiều')) {
            return ['start' => 7, 'end' => 12, 'order' => 3, 'session' => 'Chiều'];
        }
        if (str_contains($lower, 'tối')) {
            return ['start' => 13, 'end' => 15, 'order' => 5, 'session' => 'Tối'];
        }

        return ['start' => 1, 'end' => 3, 'order' => 1, 'session' => 'Khác'];
    }

    public static function rangesOverlap(array $a, array $b): bool
    {
        return $a['start'] <= $b['end'] && $b['start'] <= $a['end'];
    }

    public static function checkConflicts(
        int $teacherId,
        int $branchId,
        string $room,
        int $dayOfWeek,
        string $shiftOrPeriods,
        ?int $excludeScheduleId = null
    ): array {
        $pdo = Database::getConnection();
        $targetRange = self::parseSlotRange($shiftOrPeriods);
        $conflicts = [];

        // 1. Kiểm tra trùng lịch giảng viên
        $stmt = $pdo->prepare(
            'SELECT s.*, b.branch_name
             FROM schedules s
             INNER JOIN branches b ON s.branch_id = b.id
             WHERE s.teacher_id = :teacher_id AND s.day_of_week = :day'
        );
        $stmt->execute(['teacher_id' => $teacherId, 'day' => $dayOfWeek]);
        foreach ($stmt->fetchAll() as $row) {
            if ($excludeScheduleId && (int) $row['id'] === $excludeScheduleId) {
                continue;
            }
            $rowRange = self::parseSlotRange($row['shift_or_periods']);
            if (self::rangesOverlap($targetRange, $rowRange)) {
                $conflicts[] = [
                    'type' => 'teacher_conflict',
                    'message' => sprintf(
                        'Giảng viên đã có lịch dạy "%s" tại %s (Phòng %s, %s).',
                        $row['subject_name'],
                        $row['branch_name'],
                        $row['room'],
                        $row['shift_or_periods']
                    ),
                ];
            }
        }

        // 2. Kiểm tra trùng phòng học tại cùng cơ sở
        $stmtRoom = $pdo->prepare(
            'SELECT s.*, t.full_name AS teacher_name
             FROM schedules s
             INNER JOIN teachers t ON s.teacher_id = t.id
             WHERE s.branch_id = :branch_id AND LOWER(s.room) = LOWER(:room) AND s.day_of_week = :day'
        );
        $stmtRoom->execute([
            'branch_id' => $branchId,
            'room' => trim($room),
            'day' => $dayOfWeek,
        ]);
        foreach ($stmtRoom->fetchAll() as $row) {
            if ($excludeScheduleId && (int) $row['id'] === $excludeScheduleId) {
                continue;
            }
            $rowRange = self::parseSlotRange($row['shift_or_periods']);
            if (self::rangesOverlap($targetRange, $rowRange)) {
                $conflicts[] = [
                    'type' => 'room_conflict',
                    'message' => sprintf(
                        'Phòng %s đã được sử dụng cho học phần "%s" (GV: %s, %s).',
                        $row['room'],
                        $row['subject_name'],
                        $row['teacher_name'],
                        $row['shift_or_periods']
                    ),
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Phân tích lịch trong ngày của giảng viên: phát hiện chuyển cơ sở gấp & khoảng trống chết.
     */
    public static function analyzeTeacherDay(
        int $teacherId,
        int $dayOfWeek,
        ?array $candidate = null,
        ?int $excludeScheduleId = null
    ): array {
        $existing = Schedule::all(['teacher_id' => $teacherId, 'day_of_week' => $dayOfWeek]);
        $items = [];

        foreach ($existing as $row) {
            if ($excludeScheduleId && (int) $row['id'] === $excludeScheduleId) {
                continue;
            }
            $range = self::parseSlotRange($row['shift_or_periods']);
            $items[] = [
                'branch_id' => (int) $row['branch_id'],
                'branch_name' => $row['branch_name'],
                'shift_or_periods' => $row['shift_or_periods'],
                'subject_name' => $row['subject_name'],
                'start' => $range['start'],
                'end' => $range['end'],
                'order' => $range['order'],
            ];
        }

        $sameBranchCount = 0;
        if ($candidate !== null) {
            $candRange = self::parseSlotRange($candidate['shift_or_periods']);
            foreach ($items as $it) {
                if ($it['branch_id'] === (int) $candidate['branch_id']) {
                    $sameBranchCount++;
                }
            }
            $items[] = [
                'branch_id' => (int) $candidate['branch_id'],
                'branch_name' => $candidate['branch_name'] ?? ('Cơ sở #' . $candidate['branch_id']),
                'shift_or_periods' => $candidate['shift_or_periods'],
                'subject_name' => $candidate['subject_name'] ?? 'Học phần mới',
                'start' => $candRange['start'],
                'end' => $candRange['end'],
                'order' => $candRange['order'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        $warnings = [];
        $branches = [];
        for ($i = 0; $i < count($items); $i++) {
            $branches[$items[$i]['branch_id']] = $items[$i]['branch_name'];
            if ($i === 0) {
                continue;
            }
            $prev = $items[$i - 1];
            $curr = $items[$i];
            $periodGap = $curr['start'] - $prev['end'] - 1;
            $orderGap = $curr['order'] - $prev['order'];

            // Chuyển cơ sở giữa 2 ca liền kề hoặc cách nhau <= 1 tiết
            if ($prev['branch_id'] !== $curr['branch_id'] && $periodGap <= 1) {
                $warnings[] = [
                    'type' => 'travel_tight',
                    'severity' => 'high',
                    'message' => sprintf(
                        'Di chuyển gấp giữa 2 cơ sở: %s (%s) sang %s (%s). Nên ưu tiên gom cùng cơ sở.',
                        $prev['branch_name'],
                        $prev['shift_or_periods'],
                        $curr['branch_name'],
                        $curr['shift_or_periods']
                    ),
                ];
            }

            // Khoảng trống chết: cách nhau từ 2 ca (từ 4 tiết trống) trở lên trong cùng ngày
            if ($orderGap >= 3 || $periodGap >= 6) {
                $warnings[] = [
                    'type' => 'dead_gap',
                    'severity' => 'medium',
                    'message' => sprintf(
                        'Khoảng trống chết giữa %s và %s (nghỉ giữa buổi quá dài).',
                        $prev['shift_or_periods'],
                        $curr['shift_or_periods']
                    ),
                ];
            }
        }

        if (count($branches) > 1 && empty(array_filter($warnings, static fn ($w) => $w['type'] === 'travel_tight'))) {
            $warnings[] = [
                'type' => 'campus_switch',
                'severity' => 'low',
                'message' => sprintf(
                    'Trong %s giảng viên phải di chuyển qua %d cơ sở (%s).',
                    self::DAYS[$dayOfWeek] ?? ('Thứ ' . $dayOfWeek),
                    count($branches),
                    implode(' → ', array_values($branches))
                ),
            ];
        }

        return [
            'warnings' => $warnings,
            'same_branch_match' => $sameBranchCount > 0,
            'total_classes_day' => count($items),
        ];
    }

    /**
     * Gợi ý giảng viên phù hợp nhất cho môn học & khung giờ.
     */
    public static function recommendTeachers(
        string $subjectName,
        int $branchId,
        int $dayOfWeek,
        string $shiftOrPeriods,
        ?int $excludeScheduleId = null
    ): array {
        $teachers = Teacher::all();
        $availabilities = Availability::all();
        $availMap = [];
        foreach ($availabilities as $av) {
            if ((int) $av['day_of_week'] === $dayOfWeek && trim($av['shift_or_periods']) === trim($shiftOrPeriods)) {
                $availMap[(int) $av['teacher_id']] = $av;
            }
        }

        $results = [];
        foreach ($teachers as $teacher) {
            $tid = (int) $teacher['id'];
            $conflicts = self::checkConflicts($tid, $branchId, '__CHECK__', $dayOfWeek, $shiftOrPeriods, $excludeScheduleId);
            $teacherConflicts = array_values(array_filter($conflicts, static fn ($c) => $c['type'] === 'teacher_conflict'));
            $isAvailable = empty($teacherConflicts);

            $score = 0;
            $reasons = [];

            // 1. Độ phù hợp chuyên môn
            $expertiseScore = self::calculateExpertiseScore($subjectName, $teacher['expertise']);
            if ($expertiseScore > 0) {
                $score += $expertiseScore;
                $reasons[] = ['label' => 'Đúng chuyên môn', 'type' => 'success'];
            }

            // 2. Có đăng ký tiết trống ở khung giờ này
            if (isset($availMap[$tid])) {
                $score += 25;
                $reasons[] = ['label' => 'Có tiết trống', 'type' => 'info'];
                if ((int) ($availMap[$tid]['preferred_branch_id'] ?? 0) === $branchId) {
                    $score += 10;
                    $reasons[] = ['label' => 'Đúng cơ sở ưu tiên', 'type' => 'info'];
                }
            }

            // 3. Phân tích tối ưu di chuyển & khoảng trống chết
            $analysis = self::analyzeTeacherDay(
                $tid,
                $dayOfWeek,
                ['branch_id' => $branchId, 'shift_or_periods' => $shiftOrPeriods, 'subject_name' => $subjectName],
                $excludeScheduleId
            );

            if ($analysis['same_branch_match']) {
                $score += 20;
                $reasons[] = ['label' => 'Cùng cơ sở trong ngày', 'type' => 'success'];
            }

            foreach ($analysis['warnings'] as $w) {
                if ($w['type'] === 'travel_tight') {
                    $score -= 25;
                    $reasons[] = ['label' => 'Chuyển cơ sở gấp', 'type' => 'warning'];
                } elseif ($w['type'] === 'dead_gap') {
                    $score -= 15;
                    $reasons[] = ['label' => 'Có khoảng trống chết', 'type' => 'warning'];
                }
            }

            if (!$isAvailable) {
                $score -= 100;
                $reasons[] = ['label' => 'Trùng lịch dạy', 'type' => 'danger'];
            }

            $results[] = [
                'teacher_id' => $tid,
                'full_name' => $teacher['full_name'],
                'email' => $teacher['email'],
                'expertise' => $teacher['expertise'],
                'available' => $isAvailable,
                'score' => $score,
                'reasons' => $reasons,
                'warnings' => $analysis['warnings'],
                'conflicts' => $teacherConflicts,
            ];
        }

        usort($results, static function (array $a, array $b): int {
            if ($a['available'] !== $b['available']) {
                return $a['available'] ? -1 : 1;
            }
            return $b['score'] <=> $a['score'];
        });

        return $results;
    }

    public static function calculateExpertiseScore(string $subjectName, string $expertise): int
    {
        $subj = self::normalizeText($subjectName);
        $exp = self::normalizeText($expertise);

        if ($subj === '' || $exp === '') {
            return 0;
        }
        if (str_contains($exp, $subj) || str_contains($subj, $exp)) {
            return 45;
        }

        $domainKeywords = [
            ['lap trinh', 'php', 'web', 'java', 'python', 'c#', 'phan mem', 'cong nghe thong tin', 'cntt', 'co so du lieu', 'sql', 'mang may tinh'],
            ['tri tue nhan tao', 'ai', 'hoc may', 'machine learning', 'du lieu lon', 'data', 'khoa hoc du lieu'],
            ['kinh te', 'ke toan', 'tai chinh', 'quan tri', 'marketing', 'thuong mai', 'logistics'],
            ['thuc pham', 'cong nghe thuc pham', 'hoa hoc', 'sinh hoc', 'vi sinh', 'dinh duong'],
            ['co khi', 'dien', 'dien tu', 'tu dong hoa', 'iot', 'nhung'],
            ['tieng anh', 'ngoai ngu', 'anh van', 'toeic', 'ielts'],
            ['toan', 'xac suat', 'thong ke', 'giai tich', 'dai so'],
        ];

        $subjTokens = array_filter(explode(' ', $subj), static fn ($t) => strlen($t) >= 2);
        $expTokens = array_filter(explode(' ', $exp), static fn ($t) => strlen($t) >= 2);
        $overlap = array_intersect($subjTokens, $expTokens);
        if (count($overlap) >= 2) {
            return 40;
        }

        foreach ($domainKeywords as $group) {
            $subjHit = false;
            $expHit = false;
            foreach ($group as $kw) {
                if (str_contains($subj, $kw)) {
                    $subjHit = true;
                }
                if (str_contains($exp, $kw)) {
                    $expHit = true;
                }
            }
            if ($subjHit && $expHit) {
                return 35;
            }
        }

        return count($overlap) === 1 ? 20 : 0;
    }

    private static function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $map = [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'đ' => 'd',
        ];
        return strtr($text, $map);
    }
}
