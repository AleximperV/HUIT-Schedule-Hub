<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class Availability
{
    public static function all(?int $teacherId = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT a.*, t.full_name AS teacher_name, t.expertise, b.branch_name AS preferred_branch_name
                FROM teacher_availabilities a
                INNER JOIN teachers t ON a.teacher_id = t.id
                LEFT JOIN branches b ON a.preferred_branch_id = b.id
                WHERE 1=1';
        $params = [];
        if ($teacherId) {
            $sql .= ' AND a.teacher_id = :teacher_id';
            $params['teacher_id'] = $teacherId;
        }
        $sql .= ' ORDER BY a.day_of_week ASC, a.shift_or_periods ASC, t.full_name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO teacher_availabilities (teacher_id, day_of_week, shift_or_periods, preferred_branch_id, note)
             VALUES (:teacher_id, :day_of_week, :shift_or_periods, :preferred_branch_id, :note)
             ON DUPLICATE KEY UPDATE preferred_branch_id = VALUES(preferred_branch_id), note = VALUES(note)'
        );
        $stmt->execute([
            'teacher_id' => (int) $data['teacher_id'],
            'day_of_week' => (int) $data['day_of_week'],
            'shift_or_periods' => trim($data['shift_or_periods']),
            'preferred_branch_id' => !empty($data['preferred_branch_id']) ? (int) $data['preferred_branch_id'] : null,
            'note' => !empty($data['note']) ? trim($data['note']) : null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM teacher_availabilities WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
