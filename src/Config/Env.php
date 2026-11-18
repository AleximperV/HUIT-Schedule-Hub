<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;

class Env
{
    private static bool $loaded = false;

    public static function load(?string $basePath = null): void
    {
        if (self::$loaded) {
            return;
        }

        $root = $basePath ?? dirname(__DIR__, 2);
        if (file_exists($root . DIRECTORY_SEPARATOR . '.env')) {
            $dotenv = Dotenv::createImmutable($root);
            $dotenv->safeLoad();
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($val === false || $val === null || $val === '') {
            return $default;
        }
        return (string) $val;
    }
}
