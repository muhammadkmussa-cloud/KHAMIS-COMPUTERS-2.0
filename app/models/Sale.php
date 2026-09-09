<?php
declare(strict_types=1);

/**
 * Sale model — listing/search for the orders screens.
 * (The checkout engine lives in SaleService; find()/items() are there too.)
 */
class Sale
{
    /** Build the shared WHERE clause + params for the search filters. */
    private static function buildWhere(array $f): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[]  = '(s.sale_number LIKE :q OR s.customer_name LIKE :q OR s.customer_phone LIKE :q OR s.payment_ref LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $channel = (string) ($f['channel'] ?? '');
        if (in_array($channel, ['pos', 'online'], true)) {
            $where[] = 's.channel = :channel';
            $params['channel'] = $channel;
        }
        $status = (string) ($f['status'] ?? '');
        if ($status === 'offline') {
            // Offline sales are stored with status 'completed' + offline_created=1.
            $where[] = 's.offline_created = 1';
        } elseif (in_array($status, ['completed', 'cancelled'], true)) {
            $where[] = 's.status = :status';
            $params['status'] = $status;
        }
        $payment = (string) ($f['payment'] ?? '');
        if (in_array($payment, ['cash', 'mpesa', 'card', 'bank'], true)) {
            $where[] = 's.payment_method = :payment';
            $params['payment'] = $payment;
        }
        $from = (string) ($f['from'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'DATE(s.created_at) >= :from';
            $params['from'] = $from;
        }
        $to = (string) ($f['to'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'DATE(s.created_at) <= :to';
            $params['to'] = $to;
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public static function search(array $f = []): array
    {
        [$whereSql, $params] = self::buildWhere($f);

        $total    = (int) Database::fetchValue("SELECT COUNT(*) FROM sales s {$whereSql}", $params);

        $per    = 20;
        $pages  = max(1, (int) ceil($total / $per));
        $page   = min($pages, max(1, (int) ($f['page'] ?? 1)));
        $offset = ($page - 1) * $per;

        $rows = Database::fetchAll(
            "SELECT s.*, u.name AS user_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count,
                    (SELECT COUNT(*) FROM returns r WHERE r.sale_id = s.id) AS return_count
               FROM sales s
               LEFT JOIN users u ON u.id = s.user_id
               {$whereSql}
              ORDER BY s.id DESC
              LIMIT {$per} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per];
    }

    /** All rows matching the filters (no pagination) — for CSV export. */
    public static function exportAll(array $f = []): array
    {
        [$whereSql, $params] = self::buildWhere($f);
        return Database::fetchAll(
            "SELECT s.*, u.name AS user_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
               FROM sales s
               LEFT JOIN users u ON u.id = s.user_id
               {$whereSql}
              ORDER BY s.id DESC",
            $params
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT s.*, u.name AS user_name FROM sales s LEFT JOIN users u ON u.id = s.user_id WHERE s.id = ?",
            [$id]
        );
    }

    /**
     * Look up an online order by its number and the customer's phone number.
     * The phone is matched on its last 9 digits so formatting differences
     * (07xx / +2547xx) don't matter, while still preventing blind enumeration.
     */
    public static function findByNumberAndPhone(string $number, string $phone): ?array
    {
        $number = strtoupper(trim($number));
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($number === '' || strlen($digits) < 9) {
            return null;
        }
        $tail = substr($digits, -9);
        $row  = Database::fetch(
            "SELECT s.*, u.name AS user_name
               FROM sales s LEFT JOIN users u ON u.id = s.user_id
              WHERE s.channel = 'online' AND UPPER(s.sale_number) = ? AND s.customer_phone IS NOT NULL
              LIMIT 1",
            [$number]
        );
        if (!$row) {
            return null;
        }
        $rowDigits = preg_replace('/\D+/', '', (string) $row['customer_phone']) ?? '';
        if (strlen($rowDigits) >= 9 && substr($rowDigits, -9) !== $tail) {
            return null;
        }
        return $row;
    }

    public static function items(int $saleId): array
    {
        return Database::fetchAll(
            "SELECT si.*, p.name AS product_name, p.sku, u.serial_number, u.warranty_expires
               FROM sale_items si
               JOIN products p ON p.id = si.product_id
               LEFT JOIN inventory_units u ON u.id = si.unit_id
              WHERE si.sale_id = ?
              ORDER BY si.id",
            [$saleId]
        );
    }
}
