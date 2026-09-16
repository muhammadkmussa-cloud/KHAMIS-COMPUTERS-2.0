<?php
declare(strict_types=1);

/**
 * Supplier model — directory of vendors used on goods-received notes.
 */
class Supplier
{
    public static function all(array $filters = []): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(s.name LIKE :q OR s.contact_name LIKE :q OR s.phone LIKE :q OR s.email LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 's.is_active = :active';
            $params['active'] = $status === 'active' ? 1 : 0;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return Database::fetchAll(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM goods_received g WHERE g.supplier_id = s.id) AS grn_count,
                    (SELECT COALESCE(SUM(g.total_cost), 0) FROM goods_received g WHERE g.supplier_id = s.id) AS total_received,
                    (SELECT MAX(g.created_at) FROM goods_received g WHERE g.supplier_id = s.id) AS last_received_at,
                    (SELECT g2.grn_number FROM goods_received g2 WHERE g2.supplier_id = s.id ORDER BY g2.id DESC LIMIT 1) AS last_grn_number,
                    (SELECT g2.id FROM goods_received g2 WHERE g2.supplier_id = s.id ORDER BY g2.id DESC LIMIT 1) AS last_grn_id
               FROM suppliers s
              {$whereSql}
              ORDER BY s.is_active DESC, s.name ASC"
            , $params
        );
    }

    public static function summary(): array
    {
        return [
            'total' => (int) Database::fetchValue('SELECT COUNT(*) FROM suppliers'),
            'active' => (int) Database::fetchValue('SELECT COUNT(*) FROM suppliers WHERE is_active = 1'),
            'deliveries' => (int) Database::fetchValue('SELECT COUNT(*) FROM goods_received'),
            'spend' => (float) Database::fetchValue('SELECT COALESCE(SUM(total_cost), 0) FROM goods_received'),
        ];
    }

    public static function recentGrns(int $id, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        return Database::fetchAll(
            "SELECT id, grn_number, supplier_reference, total_cost, created_at
               FROM goods_received WHERE supplier_id = ? ORDER BY id DESC LIMIT {$limit}",
            [$id]
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
        if (mb_strlen(trim((string) ($d['name'] ?? ''))) > 190) $errors[] = 'Supplier name is too long.';
        if (mb_strlen(trim((string) ($d['phone'] ?? ''))) > 60) $errors[] = 'Supplier phone is too long.';
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
            'updated_at'   => Database::now(),
        ], 'id = :id', ['id' => $id]);
    }

    /** Number of GRNs referencing this supplier. */
    public static function grnCount(int $id): int
    {
        return (int) Database::fetchValue('SELECT COUNT(*) FROM goods_received WHERE supplier_id = ?', [$id]);
    }
}
