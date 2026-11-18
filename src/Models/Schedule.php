<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

class Schedule
{
    public static function all(array $filters = []): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT s.*, t.full_name AS teacher_name, t.expertise AS teacher_expertise, t.email AS teacher_email,
                       b.branch_name, b.address AS branch_address
                FROM schedules s
                INNER JOIN teachers t ON s.teacher_id = t.id
                INNER JOIN branches b ON s.branch_id = b.id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['branch_id'])) {
            $sql .= ' AND s.branch_id = :branch_id';
            $params['branch_id'] = (int) $filters['branch_id'];
        }
        if (!empty($filters['teacher_id'])) {
            $sql .= ' AND s.teacher_id = :teacher_id';
            $params['teacher_id'] = (int) $filters['teacher_id'];
        }
        if (!empty($filters['day_of_week'])) {
            $sql .= ' AND s.day_of_week = :day_of_week';
            $params['day_of_week'] = (int) $filters['day_of_week'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (s.subject_name LIKE :q OR s.room LIKE :q OR t.full_name LIKE :q)';
            $params['q'] = '%' . trim($filters['q']) . '%';
        }
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $sql .= ' AND s.start_date <= :date_to AND s.end_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' ORDER BY s.day_of_week ASC, s.shift_or_periods ASC, s.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT s.*, t.full_name AS teacher_name, b.branch_name, b.address AS branch_address
             FROM schedules s
             INNER JOIN teachers t ON s.teacher_id = t.id
             INNER JOIN branches b ON s.branch_id = b.id
             WHERE s.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO schedules (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods, start_date, end_date, notes)
             VALUES (:teacher_id, :branch_id, :subject_name, :room, :day_of_week, :shift_or_periods, :start_date, :end_date, :notes)'
        );
        $stmt->execute([
            'teacher_id' => (int) $data['teacher_id'],
            'branch_id' => (int) $data['branch_id'],
            'subject_name' => trim($data['subject_name']),
            'room' => trim($data['room']),
            'day_of_week' => (int) $data['day_of_week'],
            'shift_or_periods' => trim($data['shift_or_periods']),
            'start_date' => !empty($data['start_date']) ? $data['start_date'] : '2026-09-07',
            'end_date' => !empty($data['end_date']) ? $data['end_date'] : '2026-12-20',
            'notes' => isset($data['notes']) && $data['notes'] !== '' ? trim($data['notes']) : null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE schedules
             SET teacher_id = :teacher_id, branch_id = :branch_id, subject_name = :subject_name,
                 room = :room, day_of_week = :day_of_week, shift_or_periods = :shift_or_periods,
                 start_date = :start_date, end_date = :end_date, notes = :notes
             WHERE id = :id'
        );
        return $stmt->execute([
            'id' => $id,
            'teacher_id' => (int) $data['teacher_id'],
            'branch_id' => (int) $data['branch_id'],
            'subject_name' => trim($data['subject_name']),
            'room' => trim($data['room']),
            'day_of_week' => (int) $data['day_of_week'],
            'shift_or_periods' => trim($data['shift_or_periods']),
            'start_date' => !empty($data['start_date']) ? $data['start_date'] : '2026-09-07',
            'end_date' => !empty($data['end_date']) ? $data['end_date'] : '2026-12-20',
            'notes' => isset($data['notes']) && $data['notes'] !== '' ? trim($data['notes']) : null,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM schedules WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public static function statsByBranch(?int $teacherId = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT b.id, b.branch_name, COUNT(s.id) AS total_classes
                FROM branches b
                LEFT JOIN schedules s ON b.id = s.branch_id';
        $params = [];
        if ($teacherId) {
            $sql .= ' AND s.teacher_id = :teacher_id';
            $params['teacher_id'] = $teacherId;
        }
        $sql .= ' GROUP BY b.id, b.branch_name ORDER BY total_classes DESC, b.id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function statsByTeacher(?int $branchId = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT t.id, t.full_name, t.expertise, COUNT(s.id) AS total_classes
                FROM teachers t
                LEFT JOIN schedules s ON t.id = s.teacher_id';
        $params = [];
        if ($branchId) {
            $sql .= ' AND s.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }
        $sql .= ' GROUP BY t.id, t.full_name, t.expertise ORDER BY total_classes DESC, t.full_name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function statsByDay(?int $branchId = null, ?int $teacherId = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT day_of_week, COUNT(*) AS total_classes FROM schedules WHERE 1=1';
        $params = [];
        if ($branchId) {
            $sql .= ' AND branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }
        if ($teacherId) {
            $sql .= ' AND teacher_id = :teacher_id';
            $params['teacher_id'] = $teacherId;
        }
        $sql .= ' GROUP BY day_of_week ORDER BY day_of_week ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
