<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Config\Env;

class Teacher
{
    public static function all(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query('SELECT id, full_name, email, phone, expertise, portal_token, created_at FROM teachers ORDER BY full_name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, full_name, email, phone, expertise, portal_token, created_at FROM teachers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM teachers WHERE email = :email');
        $stmt->execute(['email' => trim($email)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, full_name, email, phone, expertise, portal_token FROM teachers WHERE portal_token = :token');
        $stmt->execute(['token' => trim($token)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $defaultPass = Env::get('DEFAULT_TEACHER_PASSWORD', 'huit2026');
        $password = !empty($data['password']) ? $data['password'] : $defaultPass;
        $token = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare(
            'INSERT INTO teachers (full_name, email, phone, expertise, password_hash, portal_token)
             VALUES (:full_name, :email, :phone, :expertise, :password_hash, :portal_token)'
        );
        $stmt->execute([
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'phone' => trim($data['phone']),
            'expertise' => trim($data['expertise']),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'portal_token' => $token,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE teachers SET full_name = :full_name, email = :email, phone = :phone, expertise = :expertise WHERE id = :id'
        );
        return $stmt->execute([
            'id' => $id,
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'phone' => trim($data['phone']),
            'expertise' => trim($data['expertise']),
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM teachers WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
