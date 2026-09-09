<?php
declare(strict_types=1);

/**
 * Goods Received Notes (GRN) — stock receiving workflow.
 * A GRN adds stock: it writes stock_movements ('received') and, for serialized
 * products, creates inventory_units (one per serial number).
 */
class GoodsReceived
{
    public static function nextNumber(): string
    {
        $seq = (int) Database::fetchValue('SELECT COUNT(*) FROM goods_received') + 1;
        return 'GRN-' . date('Ymd') . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public static function all(): array
    {
        return Database::fetchAll(
            "SELECT g.*, u.name AS user_name,
                    (SELECT COUNT(*) FROM goods_received_items i WHERE i.grn_id = g.id) AS item_count
               FROM goods_received g
               LEFT JOIN users u ON u.id = g.user_id
              ORDER BY g.id DESC"
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM goods_received WHERE id = ?', [$id]);
    }

    public static function items(int $grnId): array
    {
        return Database::fetchAll(
            "SELECT i.*, p.name AS product_name, p.sku, p.is_serialized
               FROM goods_received_items i
               JOIN products p ON p.id = i.product_id
              WHERE i.grn_id = ?
              ORDER BY i.id",
            [$grnId]
        );
    }

    /**
     * Create a GRN with its items, apply stock movements and serial units.
     * $lines = [ ['product_id' => int, 'quantity' => int, 'unit_cost' => float,
     *             'serials' => string[] ], ... ]
     */
    public static function create(string $supplier, array $lines, ?int $userId, string $note = '', ?int $supplierId = null): int
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return self::commit($supplier, $lines, $userId, $note, $supplierId);
            } catch (Throwable $e) {
                if ($attempt === 4 || !Database::isDuplicateKey($e)) {
                    throw $e;
                }
            }
        }
        throw new Exception('Could not allocate a GRN number. Please try again.');
    }

    private static function commit(string $supplier, array $lines, ?int $userId, string $note, ?int $supplierId): int
    {
        $pdo    = Database::pdo();
        $number = self::nextNumber();
        $now    = Database::now();
        $total  = 0.0;

        $pdo->beginTransaction();
        try {
            $grnId = Database::insert('goods_received', [
                'grn_number'  => $number,
                'supplier'    => trim($supplier) !== '' ? trim($supplier) : 'Walk-in supplier',
                'supplier_id' => $supplierId,
                'total_cost'  => 0,
                'user_id'     => $userId,
                'note'        => trim($note),
                'created_at'  => $now,
            ]);

            foreach ($lines as $line) {
                $pid      = (int) $line['product_id'];
                $qty      = max(1, (int) $line['quantity']);
                $cost     = round((float) $line['unit_cost'], 2);
                $product  = Product::find($pid);
                if (!$product) {
                    continue;
                }
                $serialized = (int) $product['is_serialized'] === 1;

                Database::insert('goods_received_items', [
                    'grn_id' => $grnId, 'product_id' => $pid,
                    'quantity' => $qty, 'unit_cost' => $cost,
                ]);
                $total += $cost * $qty;

                Database::insert('stock_movements', [
                    'product_id' => $pid, 'unit_id' => null,
                    'type' => 'received', 'quantity' => $qty,
                    'reference' => $number, 'user_id' => $userId, 'created_at' => $now,
                ]);

                if ($serialized) {
                    $serials = array_values(array_filter(array_map('trim', (array) ($line['serials'] ?? []))));
                    // Pad with auto-generated serials if fewer were provided.
                    while (count($serials) < $qty) {
                        $serials[] = 'SN' . strtoupper(substr(md5($pid . '-' . uniqid('', true)), 0, 12));
                    }
                    $wm      = max(0, (int) ($product['warranty_months'] ?? 0));
                    $expires = $wm > 0 ? date('Y-m-d', strtotime($now . ' +' . $wm . ' months')) : null;
                    foreach (array_slice($serials, 0, $qty) as $sn) {
                        Database::insert('inventory_units', [
                            'product_id' => $pid, 'serial_number' => $sn,
                            'status' => 'in_stock', 'received_at' => $now,
                            'warranty_expires' => $expires,
                        ]);
                    }
                }
            }

            Database::update('goods_received', ['total_cost' => $total], 'id = :id', ['id' => $grnId]);
            $pdo->commit();
            return $grnId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
