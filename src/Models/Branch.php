<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Branch
{
    public static function all(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query('SELECT * FROM branches ORDER BY id ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM branches WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $address): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO branches (branch_name, address) VALUES (:name, :address)');
        $stmt->execute(['name' => trim($name), 'address' => trim($address)]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $address): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE branches SET branch_name = :name, address = :address WHERE id = :id');
        return $stmt->execute(['id' => $id, 'name' => trim($name), 'address' => trim($address)]);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM branches WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
