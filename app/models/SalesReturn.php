<?php
declare(strict_types=1);

/**
 * SalesReturn — returns / refunds workflow.
 * Lifecycle: pending → approved (completed, stock restored) | rejected.
 */
class SalesReturn
{
    public static function nextNumber(): string
    {
        $seq = (int) Database::fetchValue('SELECT COALESCE(MAX(id), 0) FROM returns') + 1;
        return 'R-' . date('Ymd') . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public static function all(): array
    {
        return Database::fetchAll(
            "SELECT r.*, s.sale_number, s.channel, s.customer_name, u.name AS user_name,
                    (SELECT COUNT(*) FROM return_items ri WHERE ri.return_id = r.id) AS item_count
               FROM returns r
               JOIN sales s ON s.id = r.sale_id
               LEFT JOIN users u ON u.id = r.user_id
              ORDER BY r.id DESC"
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT r.*, s.sale_number, s.channel, s.customer_name, s.customer_phone
               FROM returns r
               JOIN sales s ON s.id = r.sale_id
              WHERE r.id = ?",
            [$id]
        );
    }

    public static function items(int $returnId): array
    {
        return Database::fetchAll(
            "SELECT ri.*, p.name AS product_name, p.sku, u.serial_number
               FROM return_items ri
               JOIN sale_items si ON si.id = ri.sale_item_id
               JOIN products p ON p.id = si.product_id
               LEFT JOIN inventory_units u ON u.id = ri.unit_id
              WHERE ri.return_id = ?
              ORDER BY ri.id",
            [$returnId]
        );
    }

    /** Sale items that still have returnable quantity (rejected returns excluded). */
    public static function availableItems(int $saleId): array
    {
        $rows = Database::fetchAll(
            "SELECT si.id AS sale_item_id, si.product_id, si.unit_id, si.quantity, si.unit_price, si.line_total,
                    p.name AS product_name, p.sku, p.is_serialized, u.serial_number
               FROM sale_items si
               JOIN products p ON p.id = si.product_id
               LEFT JOIN inventory_units u ON u.id = si.unit_id
              WHERE si.sale_id = ?
              ORDER BY si.id",
            [$saleId]
        );
        foreach ($rows as &$row) {
            $already = (int) Database::fetchValue(
                "SELECT COALESCE(SUM(ri.quantity), 0) FROM return_items ri
                  JOIN returns r ON r.id = ri.return_id
                 WHERE ri.sale_item_id = ? AND r.status != 'rejected'",
                [$row['sale_item_id']]
            );
            $row['returned_qty'] = $already;
            $row['returnable']   = max(0, (int) $row['quantity'] - $already);
        }
        unset($row);
        return $rows;
    }

    /**
     * @param array $items [ ['sale_item_id'=>int, 'quantity'=>int, 'refund_amount'=>float], ... ]
     */
    public static function create(int $saleId, array $items, string $reason, ?int $userId): int
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return self::commit($saleId, $items, $reason, $userId);
            } catch (Throwable $e) {
                if ($attempt === 4 || !Database::isDuplicateKey($e)) {
                    throw $e;
                }
            }
        }
        throw new Exception('Could not allocate a return number. Please try again.');
    }

    private static function commit(int $saleId, array $items, string $reason, ?int $userId): int
    {
        // A return restores stock — it is only valid against a completed sale.
        // (A voided sale already restored its stock; a pending one was never
        // completed, so returning it would double-restock.)
        $sale = Database::fetch('SELECT id, status, sale_number FROM sales WHERE id = ?', [$saleId]);
        if (!$sale) {
            throw new Exception('Sale not found.');
        }
        if ($sale['status'] !== 'completed') {
            throw new Exception('Only completed sales can be returned. Order ' . $sale['sale_number'] . ' is ' . $sale['status'] . '.');
        }

        $pdo    = Database::pdo();
        $number = self::nextNumber();
        $now    = Database::now();
        $refund = 0.0;
        $inserted = 0;

        $pdo->beginTransaction();
        try {
            $retId = Database::insert('returns', [
                'return_number' => $number,
                'sale_id'       => $saleId,
                'reason'        => trim($reason),
                'status'        => 'pending',
                'refund_amount' => 0,
                'user_id'       => $userId,
                'created_at'    => $now,
            ]);

            foreach ($items as $it) {
                $siId = (int) ($it['sale_item_id'] ?? 0);
                $qty  = max(1, (int) ($it['quantity'] ?? 1));
                $amt  = round((float) ($it['refund_amount'] ?? 0), 2);

                $si = Database::fetch(
                    "SELECT si.*, p.is_serialized, p.name AS product_name
                       FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.id = ?",
                    [$siId]
                );
                if (!$si || (int) $si['sale_id'] !== $saleId) {
                    continue;
                }
                $already = (int) Database::fetchValue(
                    "SELECT COALESCE(SUM(ri.quantity), 0) FROM return_items ri
                      JOIN returns r ON r.id = ri.return_id
                     WHERE ri.sale_item_id = ? AND r.status != 'rejected'",
                    [$siId]
                );
                $maxQty = (int) $si['quantity'] - $already;
                if ($qty > $maxQty) {
                    $qty = $maxQty;
                }
                if ($qty <= 0) {
                    continue;
                }
                // Refunds cannot exceed the value of the items being returned.
                $maxRefund = round((float) $si['unit_price'] * $qty, 2);
                if ($amt > $maxRefund) {
                    throw new Exception('Refund for "' . $si['product_name'] . '" exceeds the item total (' . money($maxRefund) . ').');
                }
                Database::insert('return_items', [
                    'return_id'     => $retId,
                    'sale_item_id'  => $siId,
                    'unit_id'       => $si['unit_id'] ? (int) $si['unit_id'] : null,
                    'quantity'      => $qty,
                    'refund_amount' => $amt,
                ]);
                $refund += $amt;
                $inserted++;
            }

            if ($inserted === 0) {
                throw new Exception('Nothing left to return — the selected items may already be fully returned.');
            }

            Database::update('returns', ['refund_amount' => round($refund, 2)], 'id = :id', ['id' => $retId]);
            $pdo->commit();
            return $retId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Approve → restock returned units/quantities and mark completed. */
    public static function approve(int $id): void
    {
        $r = self::find($id);
        if (!$r || $r['status'] !== 'pending') {
            return;
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $items = Database::fetchAll(
                "SELECT ri.*, si.product_id FROM return_items ri
                  JOIN sale_items si ON si.id = ri.sale_item_id
                 WHERE ri.return_id = ?",
                [$id]
            );
            foreach ($items as $it) {
                Database::insert('stock_movements', [
                    'product_id' => (int) $it['product_id'],
                    'unit_id'    => $it['unit_id'] ? (int) $it['unit_id'] : null,
                    'type'       => 'returned',
                    'quantity'   => (int) $it['quantity'],
                    'reference'  => $r['return_number'],
                    'user_id'    => null,
                    'created_at' => Database::now(),
                ]);
                if ($it['unit_id']) {
                    Database::update('inventory_units',
                        ['status' => 'in_stock', 'note' => 'Returned from ' . $r['sale_number']],
                        'id = :id', ['id' => (int) $it['unit_id']]);
                }
            }
            Database::update('returns', ['status' => 'completed'], 'id = :id', ['id' => $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function reject(int $id): void
    {
        Database::update('returns', ['status' => 'rejected'], 'id = :id', ['id' => $id]);
    }
}
