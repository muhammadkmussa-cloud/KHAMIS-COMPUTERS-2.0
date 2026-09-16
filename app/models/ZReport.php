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
        $returnWhere  = 'DATE(r.created_at) = ?';
        $returnParams = [$date];
        if ($userId !== null) {
            $returnWhere .= ' AND r.user_id = ?';
            $returnParams[] = $userId;
        }
        $r = Database::fetch(
            "SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(r.refund_amount), 0) AS total,
                    COALESCE(SUM(CASE
                        WHEN r.refund_method = 'cash' THEN r.refund_amount
                        WHEN r.refund_method = 'original' AND s.payment_method = 'cash' THEN r.refund_amount
                        ELSE 0 END), 0) AS cash_total
               FROM returns r
               JOIN sales s ON s.id = r.sale_id
              WHERE r.status = 'completed' AND {$returnWhere}",
            $returnParams
        );

        $cash    = round((float) $s['cash'], 2);
        $refunds    = round((float) $r['total'], 2);
        $cashRefunds = round((float) $r['cash_total'], 2);

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
            'cash_refunds'  => $cashRefunds,
            'expected_cash' => round($cash - $cashRefunds, 2),
        ];
    }

    /** Create one immutable snapshot for a cashier and date. */
    public static function close(string $date, int $userId, float $countedCash, ?string $notes = null, ?int $closedBy = null): int
    {
        $m = self::summary($date, $userId);
        $existing = Database::fetch(
            'SELECT id FROM z_reports WHERE user_id = ? AND report_date = ?',
            [$userId, $date]
        );

        if ($existing) {
            throw new Exception('This day is already closed and its snapshot is immutable.');
        }
        $variance = round($countedCash - (float) $m['expected_cash'], 2);
        if (abs($variance) >= 0.01 && trim((string) $notes) === '') {
            throw new Exception('Explain the cash variance before closing the day.');
        }
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
            'counted_cash'  => round($countedCash, 2),
            'variance'      => $variance,
            'closed_by'     => $closedBy,
            'notes'         => trim((string) $notes) !== '' ? trim((string) $notes) : null,
            'created_at'    => Database::now(),
        ];

        return (int) Database::insert('z_reports', $data + [
            'user_id'     => $userId,
            'report_date' => $date,
        ]);
    }

    /**
     * Saved day-closing snapshots, newest first. Pass $userId to restrict the
     * result to a single staff member (cashiers only see their own day).
     */
    public static function all(int $limit = 60, ?int $userId = null): array
    {
        $where = '';
        $args  = [];
        if ($userId !== null) {
            $where  = ' WHERE z.user_id = ?';
            $args[] = $userId;
        }
        return Database::fetchAll(
            "SELECT z.*, u.name AS user_name, cu.name AS closed_by_name
               FROM z_reports z
               LEFT JOIN users u ON u.id = z.user_id
               LEFT JOIN users cu ON cu.id = z.closed_by
              {$where}
              ORDER BY z.report_date DESC, z.id DESC
              LIMIT " . max(1, min(500, $limit)),
            $args
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
