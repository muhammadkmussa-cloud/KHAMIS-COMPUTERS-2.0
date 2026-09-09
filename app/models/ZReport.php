<?php
declare(strict_types=1);

/**
 * Z-report (close-of-day) — end-of-day till reconciliation.
 *
 * summary() computes live figures for a date (and optionally a single
 * cashier); close() snapshots them into the z_reports table (one per
 * cashier per day) so the numbers survive later edits.
 */
class ZReport
{
    /**
     * @param string    $date   Y-m-d
     * @param int|null  $userId null = all staff (incl. online sales)
     */
    public static function summary(string $date, ?int $userId): array
    {
        $where  = 'DATE(created_at) = ?';
        $params = [$date];
        if ($userId !== null) {
            $where  .= ' AND user_id = ?';
            $params[] = $userId;
        }

        $s = Database::fetch(
            "SELECT COUNT(*)                                  AS cnt,
                    COALESCE(SUM(total), 0)                   AS total,
                    COALESCE(SUM(discount), 0)                AS discounts,
                    COALESCE(SUM(CASE WHEN payment_method = 'cash'  THEN total ELSE 0 END), 0) AS cash,
                    COALESCE(SUM(CASE WHEN payment_method = 'mpesa' THEN total ELSE 0 END), 0) AS mpesa,
                    COALESCE(SUM(CASE WHEN payment_method = 'card'  THEN total ELSE 0 END), 0) AS card,
                    COALESCE(SUM(CASE WHEN payment_method = 'bank'  THEN total ELSE 0 END), 0) AS bank
               FROM sales
              WHERE status = 'completed' AND {$where}",
            $params
        );
        $v = Database::fetch(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total
               FROM sales
              WHERE status = 'cancelled' AND {$where}",
            $params
        );
        $r = Database::fetch(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(refund_amount), 0) AS total
               FROM returns
              WHERE status = 'completed' AND {$where}",
            $params
        );

        $cash    = round((float) $s['cash'], 2);
        $refunds = round((float) $r['total'], 2);

        return [
            'sales_count'   => (int) $s['cnt'],
            'total_sales'   => round((float) $s['total'], 2),
            'cash_sales'    => $cash,
            'mpesa_sales'   => round((float) $s['mpesa'], 2),
            'card_sales'    => round((float) $s['card'], 2),
            'bank_sales'    => round((float) $s['bank'], 2),
            'discounts'     => round((float) $s['discounts'], 2),
            'voids_count'   => (int) $v['cnt'],
            'voids_total'   => round((float) $v['total'], 2),
            'refunds_count' => (int) $r['cnt'],
            'refunds_total' => $refunds,
            'expected_cash' => round($cash - $refunds, 2),
        ];
    }

    /** Snapshot a day for a specific cashier (upsert). */
    public static function close(string $date, int $userId, ?string $notes = null): int
    {
        $m = self::summary($date, $userId);
        $existing = Database::fetch(
            'SELECT id FROM z_reports WHERE user_id = ? AND report_date = ?',
            [$userId, $date]
        );

        $data = [
            'sales_count'   => $m['sales_count'],
            'total_sales'   => $m['total_sales'],
            'cash_sales'    => $m['cash_sales'],
            'mpesa_sales'   => $m['mpesa_sales'],
            'card_sales'    => $m['card_sales'],
            'bank_sales'    => $m['bank_sales'],
            'discounts'     => $m['discounts'],
            'voids_count'   => $m['voids_count'],
            'voids_total'   => $m['voids_total'],
            'refunds_count' => $m['refunds_count'],
            'refunds_total' => $m['refunds_total'],
            'expected_cash' => $m['expected_cash'],
            'notes'         => trim((string) $notes) !== '' ? trim((string) $notes) : null,
            'created_at'    => Database::now(),
        ];

        if ($existing) {
            $id = (int) $existing['id'];
            Database::update('z_reports', $data, 'id = :id', ['id' => $id]);
            return $id;
        }
        return (int) Database::insert('z_reports', $data + [
            'user_id'     => $userId,
            'report_date' => $date,
        ]);
    }

    public static function all(int $limit = 60): array
    {
        return Database::fetchAll(
            "SELECT z.*, u.name AS user_name
               FROM z_reports z
               LEFT JOIN users u ON u.id = z.user_id
              ORDER BY z.report_date DESC, z.id DESC
              LIMIT " . max(1, min(500, $limit))
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT z.*, u.name AS user_name FROM z_reports z LEFT JOIN users u ON u.id = z.user_id WHERE z.id = ?",
            [$id]
        );
    }
}
