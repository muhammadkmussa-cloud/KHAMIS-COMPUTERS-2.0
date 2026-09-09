<?php
declare(strict_types=1);

/**
 * Supplier model — directory of vendors used on goods-received notes.
 */
class Supplier
{
    public static function all(): array
    {
        return Database::fetchAll(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM goods_received g WHERE g.supplier_id = s.id) AS grn_count
               FROM suppliers s
              ORDER BY s.is_active DESC, s.name ASC"
        );
    }

    public static function active(): array
    {
        return Database::fetchAll('SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM suppliers WHERE id = ?', [$id]);
    }

    /** @return string[] validation errors (empty when valid) */
    public static function validate(array $d): array
    {
        $errors = [];
        if (trim((string) ($d['name'] ?? '')) === '') {
            $errors[] = 'Supplier name is required.';
        }
        if (trim((string) ($d['email'] ?? '')) !== '' && !filter_var(trim($d['email']), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Supplier email is not valid.';
        }
        return $errors;
    }

    public static function create(array $d): int
    {
        $now = Database::now();
        return Database::insert('suppliers', [
            'name'         => trim((string) $d['name']),
            'contact_name' => trim((string) ($d['contact_name'] ?? '')) ?: null,
            'phone'        => trim((string) ($d['phone'] ?? '')) ?: null,
            'email'        => trim((string) ($d['email'] ?? '')) ?: null,
            'address'      => trim((string) ($d['address'] ?? '')) ?: null,
            'notes'        => trim((string) ($d['notes'] ?? '')) ?: null,
            'is_active'    => 1, // new suppliers start active; toggled via edit form
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    public static function update(int $id, array $d): void
    {
        Database::update('suppliers', [
            'name'         => trim((string) $d['name']),
            'contact_name' => trim((string) ($d['contact_name'] ?? '')) ?: null,
            'phone'        => trim((string) ($d['phone'] ?? '')) ?: null,
            'email'        => trim((string) ($d['email'] ?? '')) ?: null,
            'address'      => trim((string) ($d['address'] ?? '')) ?: null,
            'notes'        => trim((string) ($d['notes'] ?? '')) ?: null,
            'is_active'    => isset($d['is_active']) ? 1 : 0,
            'updated_at'   => Database::now(),
        ], 'id = :id', ['id' => $id]);
    }

    /** Number of GRNs referencing this supplier. */
    public static function grnCount(int $id): int
    {
        return (int) Database::fetchValue('SELECT COUNT(*) FROM goods_received WHERE supplier_id = ?', [$id]);
    }
}
