<?php
declare(strict_types=1);

/* ---------------------------------------------------------------------------
 * Environment & config helpers
 * ------------------------------------------------------------------------ */

/**
 * Parse a simple KEY=VALUE .env file into the environment.
 */
function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if (strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"')
             || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env(string $key, $default = null)
{
    $v = $_ENV[$key] ?? null;
    if ($v === null || $v === '') {
        $v = getenv($key);
    }
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

/**
 * Read the application configuration (loaded lazily, cached statically).
 * Supports dot notation, e.g. config('db.mysql.host').
 */
function config(?string $key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require APP_PATH . '/config/config.php';
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/* ---------------------------------------------------------------------------
 * Output helpers
 * ------------------------------------------------------------------------ */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string) config('app.base_url', ''), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path, int $code = 302): void
{
    header('Location: ' . url($path), true, $code);
    exit;
}

function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Stream a CSV download. $rows is a list of row-arrays (associative is fine —
 * the header row is just the first row). Adds a UTF-8 BOM so Excel opens
 * KSh/symbol text correctly.
 */
function csv_response(array $rows, string $filename): void
{
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '', $filename) ?: 'export.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    foreach ($rows as $row) {
        $cells = array_map(
            static fn ($value) => is_string($value) ? csv_safe_cell($value) : $value,
            array_values($row)
        );
        fputcsv($out, $cells, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

/** Neutralize user-controlled text that spreadsheet apps could execute as a formula. */
function csv_safe_cell(string $value): string
{
    return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
}

function brand_mark(int $size = 32): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" fill="none" aria-hidden="true">'
        . '<rect x="3" y="6" width="42" height="28" rx="6" fill="#0071e3" fill-opacity="0.12" stroke="#0071e3" stroke-width="2.5"/>'
        . '<path d="M24 13 L15 24 L21 24 L17.5 33 L27 21.5 L20.5 21.5 Z" fill="#0071e3"/>'
        . '<path d="M17 40 h14" stroke="#0071e3" stroke-width="2.5" stroke-linecap="round"/>'
        . '</svg>';
}

/* ---------------------------------------------------------------------------
 * Product thumbnails (dependency-free, Apple-clean gradient tiles)
 * ------------------------------------------------------------------------ */

function thumb_palette(string $key): array
{
    $palettes = [
        ['#e8f1fc', '#cfe1fb'],  // blue
        ['#e7f8ee', '#c9f0db'],  // green
        ['#fff3e0', '#ffe0b8'],  // amber
        ['#f0ecff', '#ddd2ff'],  // violet
        ['#fdecea', '#f7cdca'],  // red
    ];
    $h = crc32($key);
    return $palettes[abs($h) % count($palettes)];
}

function product_monogram(string $name): string
{
    $m = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2) as $w) {
        if ($w !== '') {
            $m .= strtoupper(substr($w, 0, 1));
        }
    }
    return $m !== '' ? $m : 'KC';
}

function product_thumb(array $product, int $size = 300): string
{
    // Real uploaded image (primary) when available.
    if (!empty($product['image'])) {
        return '<img src="' . e(url('uploads/p/' . rawurlencode($product['image']))) . '"'
            . ' width="' . $size . '" height="' . $size . '"'
            . ' alt="' . e($product['name'] ?? '') . '" loading="lazy"'
            . ' style="object-fit:cover;border-radius:14px;display:block">';
    }

    static $uid = 0;
    $uid++;
    [$c1, $c2] = thumb_palette((string) ($product['name'] ?? 'x'));
    $mono = product_monogram((string) ($product['name'] ?? ''));
    $cat  = strtoupper((string) ($product['category_name'] ?? ''));
    $fs   = round($size * 0.24);
    $cs   = round($size * 0.055);
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '" xmlns="http://www.w3.org/2000/svg" role="img">'
        . '<defs><linearGradient id="tg' . $uid . '" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient></defs>'
        . '<rect width="' . $size . '" height="' . $size . '" fill="url(#tg' . $uid . ')"/>'
        . '<text x="50%" y="53%" font-family="system-ui, -apple-system, Segoe UI, sans-serif" font-size="' . $fs . '" font-weight="700" fill="#1d1d1f" text-anchor="middle" dominant-baseline="middle" letter-spacing="2">' . e($mono) . '</text>'
        . ($cat !== '' ? '<text x="50%" y="' . round($size * 0.82) . '" font-family="system-ui, sans-serif" font-size="' . $cs . '" font-weight="600" fill="#6e6e73" text-anchor="middle" letter-spacing="3">' . e($cat) . '</text>' : '')
        . '</svg>';
}

/* ---------------------------------------------------------------------------
 * Flash messages & form state
 * ------------------------------------------------------------------------ */

function flash(string $key, $value = null)
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $v;
}

function flash_has(string $key): bool
{
    return isset($_SESSION['_flash'][$key]);
}

function set_old(array $data): void
{
    $_SESSION['_old_input'] = $data;
}

function old(string $key, string $default = ''): string
{
    $v = $_SESSION['_old_input'][$key] ?? null;
    return e($v ?? $default);
}

/* ---------------------------------------------------------------------------
 * CSRF helpers
 * ------------------------------------------------------------------------ */

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(Csrf::token()) . '">';
}

/* ---------------------------------------------------------------------------
 * Money & VAT helpers  (prices are stored EXCLUSIVE of VAT)
 * ------------------------------------------------------------------------ */

function vat_rate(): float
{
    // Prefer the live setting (editable in Settings) over the config default.
    $fromSetting = Setting::get('vat_rate', (string) config('app.vat_rate', 16));
    return is_numeric($fromSetting) ? (float) $fromSetting : (float) config('app.vat_rate', 16);
}

function money($amount): string
{
    $amount = (float) $amount;
    return (string) config('app.currency', 'KSh') . ' ' . number_format($amount, 2, '.', ',');
}

function vat_on($net): float
{
    return round((float) $net * vat_rate() / 100, 2);
}

function gross_of($net): float
{
    return round((float) $net + vat_on($net), 2);
}

/* ---------------------------------------------------------------------------
 * Auth helpers
 * ------------------------------------------------------------------------ */

function current_user(): ?array
{
    return Auth::user();
}

function is_admin(): bool
{
    return Auth::isAdmin();
}

/* ---------------------------------------------------------------------------
 * Misc
 * ------------------------------------------------------------------------ */

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-');
}

/** Build a query string from the current $_GET with overrides (null removes a key). */
function query_string(array $overrides = []): string
{
    $p = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($p[$k]);
        } else {
            $p[$k] = $v;
        }
    }
    $out = http_build_query($p);
    return $out !== '' ? '?' . $out : '';
}

/** Human label + badge class for a sale/return status. */
function status_badge(string $status): array
{
    return match ($status) {
        'completed' => ['Completed', 'green'],
        'pending'   => ['Pending', 'orange'],
        'cancelled' => ['Cancelled', 'red'],
        'offline'   => ['Offline', 'blue'],
        'approved'  => ['Approved', 'green'],
        'rejected'  => ['Rejected', 'red'],
        default     => [ucfirst($status), 'gray'],
    };
}

function db_ready(): bool
{
    try {
        Database::pdo()->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
