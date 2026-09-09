<?php
declare(strict_types=1);

/**
 * Lightweight rate limiter backed by the `throttle` table.
 * Used for login brute-force protection. Degrades gracefully (no-op) if the
 * table is missing, so an un-migrated database never breaks login.
 */
class Throttle
{
    public static function loginKey(string $email): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return 'login:' . hash('sha256', strtolower(trim($email)) . '|' . $ip);
    }

    private static function row(string $key): ?array
    {
        try {
            return Database::fetch('SELECT * FROM throttle WHERE throttle_key = ?', [$key]);
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function tooManyAttempts(string $key, int $max, int $decaySeconds): bool
    {
        $row = self::row($key);
        if (!$row) {
            return false;
        }
        return !empty($row['banned_until'])
            && strtotime((string) $row['banned_until']) > time();
    }

    public static function hit(string $key, int $max, int $decaySeconds): void
    {
        try {
            $now = Database::now();
            $row = self::row($key);
            if (!$row) {
                Database::insert('throttle', [
                    'throttle_key' => $key, 'attempts' => 1,
                    'banned_until' => null, 'updated_at' => $now,
                ]);
                return;
            }
            $attempts = (int) $row['attempts'];
            // Reset the counter once the decay window has fully passed.
            if ((time() - strtotime((string) $row['updated_at'])) > $decaySeconds) {
                $attempts = 0;
            }
            $attempts++;
            $banned = $attempts >= $max
                ? date('Y-m-d H:i:s', time() + $decaySeconds)
                : null;
            Database::update('throttle', [
                'attempts'     => $attempts,
                'banned_until' => $banned,
                'updated_at'   => $now,
            ], 'throttle_key = :k', ['k' => $key]);
        } catch (Throwable $e) {
            // Fail open — never block legitimate logins because of a throttle error.
        }
    }

    public static function clear(string $key): void
    {
        try {
            Database::delete('throttle', 'throttle_key = ?', [$key]);
        } catch (Throwable $e) {
        }
    }
}
