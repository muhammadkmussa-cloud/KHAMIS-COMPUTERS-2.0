<?php
declare(strict_types=1);

/**
 * Shop settings key/value store (from the shop_settings table).
 */
class Setting
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::fetchAll('SELECT setting_key, setting_value FROM shop_settings') as $row) {
                self::$cache[$row['setting_key']] = (string) $row['setting_value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
    }

    public static function set(string $key, string $value): void
    {
        $exists = Database::fetchValue('SELECT COUNT(*) FROM shop_settings WHERE setting_key = ?', [$key]);
        if ((int) $exists > 0) {
            Database::update('shop_settings', ['setting_value' => $value, 'updated_at' => Database::now()], 'setting_key = :k', ['k' => $key]);
        } else {
            Database::insert('shop_settings', ['setting_key' => $key, 'setting_value' => $value, 'updated_at' => Database::now()]);
        }
        self::$cache = null;
    }
}
