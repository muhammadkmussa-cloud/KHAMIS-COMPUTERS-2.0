<?php
declare(strict_types=1);

/**
 * Delivery zones for the online shop.
 *
 * A zone is a delivery area (e.g. "Nairobi CBD") with a flat fee in KSh.
 * The chosen zone's name and fee are snapshotted onto each online order,
 * so historic orders keep the fee that applied at purchase time even if a
 * zone is later edited or removed.
 */
class DeliveryZone
{
    public static function all(): array
    {
        return Database::fetchAll(
            'SELECT * FROM delivery_zones ORDER BY sort_order ASC, id ASC'
        );
    }

    public static function active(): array
    {
        return Database::fetchAll(
            'SELECT * FROM delivery_zones WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        );
    }

    public static function find(int $id): ?array
    {
        $row = Database::fetch('SELECT * FROM delivery_zones WHERE id = ?', [$id]);
        return $row ?: null;
    }

    /** Look up a zone by name (used to keep fees consistent). */
    public static function findByName(string $name): ?array
    {
        $row = Database::fetch('SELECT * FROM delivery_zones WHERE name = ? LIMIT 1', [trim($name)]);
        return $row ?: null;
    }

    /** @return array|null List of field errors, or null when valid. */
    public static function validate(string $name, float $fee, ?int $ignoreId = null): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return ['name' => 'Zone name is required.'];
        }
        if (mb_strlen($name) > 120) {
            return ['name' => 'Zone name must be 120 characters or fewer.'];
        }
        if ($fee < 0) {
            return ['fee' => 'Delivery fee cannot be negative.'];
        }
        if (round($fee, 2) > 1000000) {
            return ['fee' => 'Delivery fee is unreasonably large.'];
        }

        $rows = Database::fetchAll(
            'SELECT id, name FROM delivery_zones WHERE LOWER(name) = LOWER(?)' . ($ignoreId ? ' AND id != ?' : ''),
            $ignoreId ? [mb_strtolower($name), $ignoreId] : [mb_strtolower($name)]
        );
        if ($rows) {
            return ['name' => 'A delivery zone with this name already exists.'];
        }

        return null;
    }

    public static function create(string $name, float $fee, int $sortOrder = 0, bool $isActive = true): int
    {
        return (int) Database::insert('delivery_zones', [
            'name'       => trim($name),
            'fee'        => round($fee, 2),
            'is_active'  => $isActive ? 1 : 0,
            'sort_order' => $sortOrder,
            'created_at' => Database::now(),
        ]);
    }

    public static function update(int $id, string $name, float $fee, ?int $sortOrder = null, ?bool $isActive = null): void
    {
        $fields = [
            'name' => trim($name),
            'fee'  => round($fee, 2),
        ];
        if ($sortOrder !== null) {
            $fields['sort_order'] = $sortOrder;
        }
        if ($isActive !== null) {
            $fields['is_active'] = $isActive ? 1 : 0;
        }
        Database::update('delivery_zones', $fields, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('delivery_zones', 'id = ?', [$id]);
    }
}
