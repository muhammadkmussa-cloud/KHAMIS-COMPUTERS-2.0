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

// Emulate Apache's front-controller: production routes every request through
// index.php, so SCRIPT_NAME is always "/index.php" and base_path resolves to "".
// The built-in server instead sets SCRIPT_NAME to the request path, which
// corrupts base_path for nested routes (e.g. /shop/product/1 -> base "/shop/product").
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';

require __DIR__ . '/index.php';

