<?php
declare(strict_types=1);

/**
 * Khamis Computers — bootstrap. Loaded once per request by public_html/index.php
 * (and by CLI tools). Sets up constants, helpers, .env, autoloading, error
 * handling and the session.
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);
define('PUBLIC_PATH', BASE_PATH . '/public_html');

require APP_PATH . '/helpers/functions.php';

load_env(BASE_PATH . '/.env');

// All timestamps are recorded in the shop's timezone (Kenya by default) so
// "today" stats, dashboard KPIs and receipt times match local time even when
// the server runs on UTC.
date_default_timezone_set((string) config('app.timezone', 'Africa/Nairobi'));

// Simple PSR-0-ish autoloader for app/core, app/controllers, app/models, app/services.
spl_autoload_register(function (string $class): void {
    foreach ([APP_PATH . '/core/', APP_PATH . '/controllers/', APP_PATH . '/models/', APP_PATH . '/services/'] as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// Error handling -------------------------------------------------------------
if (config('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    // Use a custom log only if we can actually write to it; otherwise leave
    // PHP's default (on cPanel that routes to the host's error log).
    $logDir = BASE_PATH . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/php-error.log');
    }
}

// Session --------------------------------------------------------------------
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name((string) config('session.name', 'KC_SESSID'));
    $secure = str_starts_with((string) config('app.base_url', ''), 'https');
    ini_set('session.use_strict_mode', '1');   // reject uninitialized session ids (session-fixation)
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => (int) config('session.lifetime', 7200),
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
}
