<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('TokLens requires PHP 8.1 or newer.');
}
foreach (['curl', 'json', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) {
        http_response_code(500);
        exit("TokLens requires the PHP '$ext' extension.");
    }
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = ROOT . '/app/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require ROOT . '/app/helpers.php';

App\Config::load(ROOT . '/app/config.php');

error_reporting(E_ALL);
ini_set('display_errors', App\Config::get('debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');
ini_set('expose_php', '0');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

foreach (['cache', 'ratelimit', 'logs', 'media'] as $dir) {
    $p = ROOT . '/storage/' . $dir;
    if (!is_dir($p)) {
        @mkdir($p, 0750, true);
    }
}

set_exception_handler([App\App::class, 'handleException']);

// Local development only: TOKLENS_MOCK=1 php -S 127.0.0.1:8080 -t public public/index.php
if (getenv('TOKLENS_MOCK') === '1') {
    App\Config::set('mock', true);
    if (getenv('TOKLENS_GUARD') === '1') {
        App\Config::set('guard_force', true);
    }
    if (getenv('TOKLENS_ADMIN') === '1') {
        App\Config::set('admin_path', 'cp-test-panel');
        App\Config::set('admin_password', 'test-password-123');
    }
}
