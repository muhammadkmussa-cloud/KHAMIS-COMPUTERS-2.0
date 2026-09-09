<?php
declare(strict_types=1);

/**
 * Khamis Computers — application configuration.
 *
 * Values can be overridden with a `.env` file (see .env.example) or by editing
 * the values below directly. This file is the single source of truth on cPanel.
 */

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

$script   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$basePath = rtrim(dirname($script), '/');
if ($basePath === '/') {
    $basePath = '';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

return [
    'app' => [
        'name'     => env('APP_NAME', 'Khamis Computers'),
        'tagline'  => env('APP_TAGLINE', 'POS & Online Shop'),
        'env'      => env('APP_ENV', 'production'),
        'debug'    => env('APP_DEBUG', 'false') === 'true',
        'base_url' => ($isHttps ? 'https' : 'http') . '://' . $host . $basePath,
        'base_path'=> $basePath,
        'currency' => env('CURRENCY', 'KSh'),
        'vat_rate' => (float) env('VAT_RATE', '16'),
        'timezone' => env('APP_TIMEZONE', 'Africa/Nairobi'),
    ],

    'db' => [
        // 'sqlite' for quick local testing, 'mysql' for cPanel production.
        'driver'      => env('DB_DRIVER', 'mysql'),
        'sqlite_path' => env('DB_SQLITE_PATH', BASE_PATH . '/storage/khamis.sqlite'),
        'mysql' => [
            'host'     => env('DB_HOST', 'localhost'),
            'port'     => (int) env('DB_PORT', '3306'),
            'database' => env('DB_NAME', 'khamis_db'),
            'username' => env('DB_USER', 'khamis_user'),
            'password' => env('DB_PASS', 'CHANGE_ME'),
            'charset'  => 'utf8mb4',
        ],
    ],

    'session' => [
        'name'     => env('SESSION_NAME', 'KC_SESSID'),
        'lifetime' => (int) env('SESSION_LIFETIME', '7200'),
    ],

    'security' => [
        'bcrypt_rounds'   => 12,
        'otp_ttl_seconds' => 300, // used by the discount OTP gate in a later piece
    ],
];
