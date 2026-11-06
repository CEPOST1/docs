<?php

declare(strict_types=1);

// PHP built-in dev server: let it serve real static files directly.
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($f !== __DIR__ . '/' && is_file($f) && !str_ends_with($f, '.php')) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

App\App::run();
