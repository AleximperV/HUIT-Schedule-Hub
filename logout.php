<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\AuthService;

AuthService::logout();
header('Location: index.php');
exit;
