<?php
declare(strict_types=1);

/**
 * Minimal audit trail — writes to the `activity_log` table.
 * Used for sensitive actions (auth events, settings/staff changes, installs).
 * Never throws: logging must not break the application.
 */
class Activity
{
    public static function log(string $action, ?string $details = null, ?int $userId = null): void
    {
        try {
            Database::insert('activity_log', [
                'user_id'    => $userId ?? Auth::id(),
                'action'     => $action,
                'details'    => $details !== null && $details !== '' ? $details : null,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => Database::now(),
            ]);
        } catch (Throwable $e) {
            // Silently degrade — an audit write must never fail a request.
        }
    }

    public static function recent(int $limit = 20): array
    {
        try {
            return Database::fetchAll(
                'SELECT al.*, u.name AS user_name FROM activity_log al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT ?',
                [$limit]
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}
