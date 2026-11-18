<?php

declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = trim($requestPath, '/');

$routes = [
    '' => __DIR__ . '/pages/dashboard/index.php',
    'index.php' => __DIR__ . '/pages/dashboard/index.php',
    'dashboard' => __DIR__ . '/pages/dashboard/index.php',

    'login.php' => __DIR__ . '/pages/auth/login.php',
    'login' => __DIR__ . '/pages/auth/login.php',
    'logout.php' => __DIR__ . '/pages/auth/logout.php',
    'logout' => __DIR__ . '/pages/auth/logout.php',

    'schedules.php' => __DIR__ . '/pages/management/schedules.php',
    'schedules' => __DIR__ . '/pages/management/schedules.php',
    'availabilities.php' => __DIR__ . '/pages/management/availabilities.php',
    'availabilities' => __DIR__ . '/pages/management/availabilities.php',
    'teachers.php' => __DIR__ . '/pages/management/teachers.php',
    'teachers' => __DIR__ . '/pages/management/teachers.php',
    'branches.php' => __DIR__ . '/pages/management/branches.php',
    'branches' => __DIR__ . '/pages/management/branches.php',

    'portal.php' => __DIR__ . '/pages/portal/portal.php',
    'portal' => __DIR__ . '/pages/portal/portal.php',

    'api.php' => __DIR__ . '/pages/api/api.php',
    'api' => __DIR__ . '/pages/api/api.php',
];

if (isset($routes[$route]) && file_exists($routes[$route])) {
    require $routes[$route];
    exit;
}

http_response_code(404);
echo '404 Not Found';
