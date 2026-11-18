<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Env;
use App\Models\Teacher;

class AuthService
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function loginAdmin(string $email, string $password): bool
    {
        self::startSession();
        $adminEmail = Env::get('ADMIN_EMAIL', 'daotao@huit.edu.vn');
        $adminPass = Env::get('ADMIN_PASSWORD', 'admin123');

        if (strcasecmp(trim($email), $adminEmail) === 0 && hash_equals($adminPass, $password)) {
            $_SESSION['auth'] = [
                'role' => 'admin',
                'name' => 'Phòng Đào tạo HUIT',
                'email' => $adminEmail,
                'teacher_id' => null,
            ];
            return true;
        }
        return false;
    }

    public static function loginTeacher(string $email, string $password): bool
    {
        self::startSession();
        $teacher = Teacher::findByEmail($email);
        if (!$teacher) {
            return false;
        }
        $defaultPass = Env::get('DEFAULT_TEACHER_PASSWORD', 'huit2026');
        $valid = !empty($teacher['password_hash'])
            ? password_verify($password, $teacher['password_hash'])
            : hash_equals($defaultPass, $password);

        if ($valid) {
            $_SESSION['auth'] = [
                'role' => 'teacher',
                'name' => $teacher['full_name'],
                'email' => $teacher['email'],
                'teacher_id' => (int) $teacher['id'],
            ];
            return true;
        }
        return false;
    }

    public static function loginTeacherByToken(string $token): ?array
    {
        self::startSession();
        $teacher = Teacher::findByToken($token);
        if (!$teacher) {
            return null;
        }
        $_SESSION['auth'] = [
            'role' => 'teacher',
            'name' => $teacher['full_name'],
            'email' => $teacher['email'],
            'teacher_id' => (int) $teacher['id'],
        ];
        return $teacher;
    }

    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION['auth'] ?? null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && $u['role'] === 'admin';
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            header('Location: login.php?error=admin_required');
            exit;
        }
    }

    public static function logout(): void
    {
        self::startSession();
        unset($_SESSION['auth']);
        session_destroy();
    }
}
