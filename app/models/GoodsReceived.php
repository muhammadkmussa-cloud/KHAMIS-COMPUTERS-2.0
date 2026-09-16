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
        $prefix = 'GRN-' . date('Ymd') . '-';
        $max = 0;
        foreach (Database::fetchAll('SELECT grn_number FROM goods_received WHERE grn_number LIKE ?', [$prefix . '%']) as $row) {
            $suffix = substr((string) $row['grn_number'], strlen($prefix));
            if (ctype_digit($suffix)) $max = max($max, (int) $suffix);
        }
        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public static function all(): array
    {
        return self::search();
    }

    public static function search(array $filters = []): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        $from = (string) ($filters['date_from'] ?? '');
        $to = (string) ($filters['date_to'] ?? '');
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(g.grn_number LIKE :q OR g.supplier_reference LIKE :q OR g.supplier LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($supplierId > 0) {
            $where[] = 'g.supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'g.created_at >= :date_from';
            $params['date_from'] = $from . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'g.created_at <= :date_to';
            $params['date_to'] = $to . ' 23:59:59';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return Database::fetchAll(
            "SELECT g.*, u.name AS user_name,
                    (SELECT COUNT(*) FROM goods_received_items i WHERE i.grn_id = g.id) AS item_count,
                    (SELECT COALESCE(SUM(i.quantity), 0) FROM goods_received_items i WHERE i.grn_id = g.id) AS total_quantity,
                    'posted' AS status
               FROM goods_received g
               LEFT JOIN users u ON u.id = g.user_id
               {$whereSql}
              ORDER BY g.id DESC"
            , $params
        );
    }

    public static function summary(array $rows): array
    {
        return [
            'records' => count($rows),
            'items' => array_sum(array_map(fn (array $row): int => (int) $row['total_quantity'], $rows)),
            'total' => array_sum(array_map(fn (array $row): float => (float) $row['total_cost'], $rows)),
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT g.*, u.name AS user_name, s.contact_name AS supplier_contact,
                    s.phone AS supplier_phone, s.email AS supplier_email, s.address AS supplier_address,
                    'posted' AS status
               FROM goods_received g
               LEFT JOIN users u ON u.id = g.user_id
               LEFT JOIN suppliers s ON s.id = g.supplier_id
              WHERE g.id = ?",
            [$id]
        );
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

    public static function serialsByItem(int $grnId): array
    {
        $grouped = [];
        $rows = Database::fetchAll(
            'SELECT iu.grn_item_id, iu.serial_number, iu.warranty_expires
               FROM inventory_units iu
               JOIN goods_received_items gi ON gi.id = iu.grn_item_id
              WHERE gi.grn_id = ? ORDER BY iu.id',
            [$grnId]
        );
        foreach ($rows as $row) $grouped[(int) $row['grn_item_id']][] = $row;
        return $grouped;
    }

    /** Validate and normalize a receiving draft before any stock is changed. */
    public static function validateDraft(array $input): array
    {
        $errors = [];
        $supplierId = (int) ($input['supplier_id'] ?? 0) ?: null;
        $supplierName = trim((string) ($input['supplier'] ?? ''));
        if ($supplierId !== null) {
            $supplier = Supplier::find($supplierId);
            if (!$supplier || (int) $supplier['is_active'] !== 1) $errors[] = 'Choose an active supplier.';
            else $supplierName = $supplier['name'];
        }
        if ($supplierName === '') $errors[] = 'Choose or enter a supplier.';
        if (mb_strlen($supplierName) > 190) $errors[] = 'Supplier name is too long.';

        $reference = trim((string) ($input['supplier_reference'] ?? ''));
        if (mb_strlen($reference) > 120) $errors[] = 'Supplier reference is too long.';
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 2000) $errors[] = 'Receiving note is too long.';

        $normalized = [];
        $seenProducts = [];
        $seenSerials = [];
        foreach ((array) ($input['lines'] ?? []) as $index => $line) {
            $lineNo = $index + 1;
            $productId = (int) ($line['product_id'] ?? 0);
            if ($productId <= 0) continue;
            $product = Product::find($productId);
            if (!$product) { $errors[] = "Line {$lineNo}: product no longer exists."; continue; }
            if (isset($seenProducts[$productId])) { $errors[] = "Line {$lineNo}: {$product['name']} is already on this receipt."; continue; }
            $seenProducts[$productId] = true;
            $quantityRaw = trim((string) ($line['quantity'] ?? ''));
            $quantityIsInteger = preg_match('/^\d+$/', $quantityRaw) === 1;
            $quantity = $quantityIsInteger ? (int) $quantityRaw : 0;
            $costRaw = $line['unit_cost'] ?? null;
            if (!$quantityIsInteger || $quantity < 1 || $quantity > 10000) $errors[] = "Line {$lineNo}: quantity must be a whole number between 1 and 10,000.";
            if (!is_numeric($costRaw) || (float) $costRaw < 0) $errors[] = "Line {$lineNo}: enter a valid unit cost.";
            $serials = array_values(array_filter(array_map('trim', (array) ($line['serials'] ?? [])), fn (string $value): bool => $value !== ''));
            if ((int) $product['is_serialized'] === 1) {
                if (count($serials) !== $quantity) $errors[] = "Line {$lineNo}: enter exactly {$quantity} unique serial number(s).";
                foreach ($serials as $serial) {
                    if (mb_strlen($serial) > 120) { $errors[] = "Line {$lineNo}: a serial number is too long."; continue; }
                    $key = mb_strtolower($serial);
                    if (isset($seenSerials[$key])) $errors[] = "Line {$lineNo}: duplicate serial {$serial}.";
                    $seenSerials[$key] = true;
                    if ((int) Database::fetchValue('SELECT COUNT(*) FROM inventory_units WHERE LOWER(serial_number) = LOWER(?)', [$serial]) > 0) {
                        $errors[] = "Line {$lineNo}: serial {$serial} already exists in inventory.";
                    }
                }
            } elseif ($serials) {
                $errors[] = "Line {$lineNo}: serials are not used for quantity-tracked products.";
                $serials = [];
            }
            $normalized[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'sku' => $product['sku'],
                'is_serialized' => (int) $product['is_serialized'],
                'quantity' => $quantity,
                'unit_cost' => round((float) $costRaw, 2),
                'serials' => $serials,
            ];
        }
        if (!$normalized) $errors[] = 'Add at least one product to receive.';

        return ['errors' => array_values(array_unique($errors)), 'draft' => [
            'supplier_id' => $supplierId,
            'supplier' => $supplierName,
            'supplier_reference' => $reference,
            'note' => $note,
            'lines' => $normalized,
            'total_cost' => array_sum(array_map(fn (array $line): float => $line['quantity'] * $line['unit_cost'], $normalized)),
            'total_quantity' => array_sum(array_map(fn (array $line): int => $line['quantity'], $normalized)),
        ]];
    }

    /**
     * Create a GRN with its items, apply stock movements and serial units.
     * $lines = [ ['product_id' => int, 'quantity' => int, 'unit_cost' => float,
     *             'serials' => string[] ], ... ]
     */
    public static function create(string $supplier, array $lines, ?int $userId, string $note = '', ?int $supplierId = null, string $supplierReference = ''): int
    {
        $validated = self::validateDraft(['supplier' => $supplier, 'supplier_id' => $supplierId, 'supplier_reference' => $supplierReference, 'note' => $note, 'lines' => $lines]);
        if ($validated['errors']) throw new InvalidArgumentException(implode(' ', $validated['errors']));
        return self::createFromDraft($validated['draft'], $userId);
    }

    public static function createFromDraft(array $draft, ?int $userId): int
    {
        $validated = self::validateDraft($draft);
        if ($validated['errors']) throw new InvalidArgumentException(implode(' ', $validated['errors']));
        $draft = $validated['draft'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return self::commit($draft, $userId);
            } catch (Throwable $e) {
                $message = mb_strtolower($e->getMessage());
                $grnNumberCollision = Database::isDuplicateKey($e) && str_contains($message, 'grn_number');
                if ($attempt === 4 || !$grnNumberCollision) {
                    throw $e;
                }
            }
        }
        throw new Exception('Could not allocate a GRN number. Please try again.');
    }

    private static function commit(array $draft, ?int $userId): int
    {
        $pdo    = Database::pdo();
        $number = self::nextNumber();
        $now    = Database::now();
        $total  = 0.0;

        $pdo->beginTransaction();
        try {
            $grnId = Database::insert('goods_received', [
                'grn_number'  => $number,
                'supplier'    => $draft['supplier'],
                'supplier_id' => $draft['supplier_id'],
                'supplier_reference' => $draft['supplier_reference'] ?: null,
                'total_cost'  => 0,
                'user_id'     => $userId,
                'note'        => $draft['note'],
                'created_at'  => $now,
            ]);

            foreach ($draft['lines'] as $line) {
                $pid      = (int) $line['product_id'];
                $qty      = (int) $line['quantity'];
                $cost     = round((float) $line['unit_cost'], 2);
                $product  = Product::find($pid);
                if (!$product) throw new RuntimeException('A reviewed product no longer exists. Start the receipt again.');
                $serialized = (int) $product['is_serialized'] === 1;

                $itemId = Database::insert('goods_received_items', [
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
                    $serials = $line['serials'];
                    if (count($serials) !== $qty) throw new RuntimeException('Reviewed serial counts changed. Start the receipt again.');
                    $wm      = max(0, (int) ($product['warranty_months'] ?? 0));
                    $expires = $wm > 0 ? date('Y-m-d', strtotime($now . ' +' . $wm . ' months')) : null;
                    foreach (array_slice($serials, 0, $qty) as $sn) {
                        Database::insert('inventory_units', [
                            'product_id' => $pid, 'serial_number' => $sn,
                            'status' => 'in_stock', 'received_at' => $now,
                            'warranty_expires' => $expires,
                            'grn_item_id' => $itemId,
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
