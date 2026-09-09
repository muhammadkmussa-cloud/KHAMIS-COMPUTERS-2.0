<?php
/**
 * Dev-only router for the PHP built-in server (php -S).
 * On cPanel, Apache + .htaccess handles routing instead — this file is unused.
 */

// Never serve requests through the dev router outside development.
// (Read APP_ENV straight from .env — index.php will bootstrap for real below.)
$env = [];
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim(trim($v), "\"'");
        }
    }
}
if (($env['APP_ENV'] ?? 'production') !== 'development') {
    http_response_code(404);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false; // let the built-in server serve the static file
}

require __DIR__ . '/index.php';

