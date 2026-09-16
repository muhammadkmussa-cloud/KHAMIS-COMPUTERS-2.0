<?php
declare(strict_types=1);

/** Returns, refunds, approval decisions, and stock outcomes. */
class SalesReturn
{
    public const REFUND_METHODS = ['original', 'cash', 'mpesa', 'bank', 'store_credit'];
    public const STOCK_OUTCOMES = ['restock', 'damaged', 'unavailable'];

    public static function nextNumber(): string
    {
        $prefix = 'R-' . date('Ymd') . '-';
        $last = (string) Database::fetchValue(
            'SELECT return_number FROM returns WHERE return_number LIKE ? ORDER BY return_number DESC LIMIT 1',
            [$prefix . '%']
        );
        $seq = $last !== '' && preg_match('/(\d+)$/', $last, $m) ? (int) $m[1] + 1 : 1;
        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public static function search(array $filters = []): array
    {
        $where = [];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(r.return_number LIKE :q OR s.sale_number LIKE :q OR s.customer_name LIKE :q OR s.customer_phone LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['pending', 'completed', 'rejected'], true)) {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $value = (string) ($filters[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $where[] = "DATE(r.created_at) {$operator} :{$key}";
                $params[$key] = $value;
            }
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return Database::fetchAll(
            "SELECT r.*, s.sale_number, s.channel, s.customer_name, s.customer_phone,
                    u.name AS user_name, du.name AS decision_user_name,
                    (SELECT COUNT(*) FROM return_items ri WHERE ri.return_id = r.id) AS item_count,
                    (SELECT COALESCE(SUM(ri.quantity), 0) FROM return_items ri WHERE ri.return_id = r.id) AS unit_count
               FROM returns r
               JOIN sales s ON s.id = r.sale_id
               LEFT JOIN users u ON u.id = r.user_id
               LEFT JOIN users du ON du.id = r.decision_user_id
               {$whereSql}
              ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.id DESC",
            $params
        );
    }

    public static function all(): array
    {
        return self::search();
    }

    public static function summary(): array
    {
        return [
            'pending' => (int) Database::fetchValue("SELECT COUNT(*) FROM returns WHERE status = 'pending'"),
            'completed' => (int) Database::fetchValue("SELECT COUNT(*) FROM returns WHERE status = 'completed'"),
            'rejected' => (int) Database::fetchValue("SELECT COUNT(*) FROM returns WHERE status = 'rejected'"),
            'refunded' => (float) Database::fetchValue("SELECT COALESCE(SUM(refund_amount), 0) FROM returns WHERE status = 'completed'"),
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT r.*, s.sale_number, s.channel, s.customer_name, s.customer_phone, s.payment_method,
                    u.name AS user_name, du.name AS decision_user_name
               FROM returns r
               JOIN sales s ON s.id = r.sale_id
               LEFT JOIN users u ON u.id = r.user_id
               LEFT JOIN users du ON du.id = r.decision_user_id
              WHERE r.id = ?",
            [$id]
        );
    }

    public static function items(int $returnId): array
    {
        return Database::fetchAll(
            "SELECT ri.*, p.id AS product_id, p.name AS product_name, p.sku, p.is_serialized,
                    si.unit_price, u.serial_number
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
            $row['returnable'] = max(0, (int) $row['quantity'] - $already);
            $row['max_refund'] = round((float) $row['unit_price'] * (int) $row['returnable'], 2);
        }
        unset($row);
        return $rows;
    }

    /** Validate a draft without writing returns, stock, or audit rows. */
    public static function validateDraft(int $saleId, array $items, string $reason, string $evidenceNote = '', string $refundMethod = 'original'): array
    {
        $errors = [];
        $sale = $saleId > 0 ? Sale::find($saleId) : null;
        if (!$sale) {
            $errors[] = 'Choose a valid sale.';
        } elseif ($sale['status'] !== 'completed') {
            $errors[] = 'Only completed sales can be returned. Order ' . $sale['sale_number'] . ' is ' . $sale['status'] . '.';
        }
        $reason = trim($reason);
        $evidenceNote = trim($evidenceNote);
        if ($reason === '') $errors[] = 'Enter the reason for this return.';
        if (mb_strlen($reason) > 500) $errors[] = 'Keep the return reason within 500 characters.';
        if (mb_strlen($evidenceNote) > 1000) $errors[] = 'Keep the evidence note within 1,000 characters.';
        if (!in_array($refundMethod, self::REFUND_METHODS, true)) $errors[] = 'Choose a valid refund method.';

        $available = [];
        if ($sale) foreach (self::availableItems($saleId) as $row) $available[(int) $row['sale_item_id']] = $row;
        $normalized = [];
        $seen = [];
        $total = 0.0;
        foreach ($items as $item) {
            $siId = (int) ($item['sale_item_id'] ?? 0);
            if ($siId <= 0 || isset($seen[$siId])) {
                $errors[] = 'A selected return item is invalid or duplicated.';
                continue;
            }
            $seen[$siId] = true;
            $row = $available[$siId] ?? null;
            if (!$row || (int) $row['returnable'] <= 0) {
                $errors[] = 'Nothing left to return for the selected item.';
                continue;
            }
            $rawQty = trim((string) ($item['quantity'] ?? ''));
            if (!preg_match('/^\d+$/', $rawQty)) {
                $errors[] = 'Quantity for "' . $row['product_name'] . '" must be a whole number.';
                continue;
            }
            $qty = (int) $rawQty;
            if ($qty < 1 || $qty > (int) $row['returnable']) {
                $errors[] = 'Quantity for "' . $row['product_name'] . '" must be between 1 and ' . (int) $row['returnable'] . '.';
                continue;
            }
            $rawAmount = trim((string) ($item['refund_amount'] ?? ''));
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $rawAmount)) {
                $errors[] = 'Refund for "' . $row['product_name'] . '" must be a valid amount with at most two decimals.';
                continue;
            }
            $amount = round((float) $rawAmount, 2);
            $maxRefund = round((float) $row['unit_price'] * $qty, 2);
            if ($amount > $maxRefund) {
                $errors[] = 'Refund for "' . $row['product_name'] . '" exceeds the item total (' . money($maxRefund) . ').';
                continue;
            }
            $normalized[] = [
                'sale_item_id' => $siId,
                'quantity' => $qty,
                'refund_amount' => $amount,
                'product_name' => $row['product_name'],
                'sku' => $row['sku'],
                'serial_number' => $row['serial_number'],
                'unit_price' => (float) $row['unit_price'],
                'max_refund' => $maxRefund,
            ];
            $total += $amount;
        }
        if (!$items) $errors[] = 'Select at least one item to return.';

        return [
            'errors' => array_values(array_unique($errors)),
            'draft' => [
                'sale_id' => $saleId,
                'sale' => $sale,
                'items' => $normalized,
                'reason' => $reason,
                'evidence_note' => $evidenceNote,
                'refund_method' => $refundMethod,
                'refund_total' => round($total, 2),
            ],
        ];
    }

    /** Compatibility entry point used by model callers and integration tests. */
    public static function create(int $saleId, array $items, string $reason, ?int $userId): int
    {
        $validated = self::validateDraft($saleId, $items, $reason);
        if ($validated['errors']) throw new Exception(implode(' ', $validated['errors']));
        return self::createFromDraft($validated['draft'], $userId);
    }

    public static function createFromDraft(array $draft, ?int $userId): int
    {
        $validated = self::validateDraft(
            (int) ($draft['sale_id'] ?? 0),
            (array) ($draft['items'] ?? []),
            (string) ($draft['reason'] ?? ''),
            (string) ($draft['evidence_note'] ?? ''),
            (string) ($draft['refund_method'] ?? 'original')
        );
        if ($validated['errors']) throw new Exception(implode(' ', $validated['errors']));
        $draft = $validated['draft'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return self::commit($draft, $userId);
            } catch (Throwable $e) {
                if ($attempt === 4 || !Database::isDuplicateKey($e) || !str_contains(strtolower($e->getMessage()), 'return_number')) throw $e;
            }
        }
        throw new Exception('Could not allocate a return number. Please try again.');
    }

    private static function commit(array $draft, ?int $userId): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Serialize return creation per sale. This no-op update acquires a
            // row/write lock in MySQL and SQLite before quantities are checked.
            Database::run('UPDATE sales SET status = status WHERE id = ?', [(int) $draft['sale_id']]);
            $validated = self::validateDraft((int) $draft['sale_id'], (array) $draft['items'], (string) $draft['reason'], (string) $draft['evidence_note'], (string) $draft['refund_method']);
            if ($validated['errors']) throw new Exception(implode(' ', $validated['errors']));
            $draft = $validated['draft'];
            $retId = Database::insert('returns', [
                'return_number' => self::nextNumber(), 'sale_id' => (int) $draft['sale_id'],
                'reason' => $draft['reason'], 'evidence_note' => $draft['evidence_note'] ?: null,
                'status' => 'pending', 'refund_amount' => (float) $draft['refund_total'],
                'refund_method' => $draft['refund_method'], 'user_id' => $userId, 'created_at' => Database::now(),
            ]);
            foreach ($draft['items'] as $item) {
                $unitId = Database::fetchValue('SELECT unit_id FROM sale_items WHERE id = ?', [(int) $item['sale_item_id']]);
                Database::insert('return_items', [
                    'return_id' => $retId, 'sale_item_id' => (int) $item['sale_item_id'],
                    'unit_id' => $unitId ? (int) $unitId : null, 'quantity' => (int) $item['quantity'],
                    'refund_amount' => (float) $item['refund_amount'], 'stock_outcome' => null,
                ]);
            }
            $pdo->commit();
            return $retId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function approve(int $id, array $outcomes = [], string $note = '', ?int $userId = null): void
    {
        $note = trim($note);
        if ($note === '') throw new Exception('Enter an approval note.');
        if (mb_strlen($note) > 500) throw new Exception('Keep the approval note within 500 characters.');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $r = self::find($id);
            if (!$r || $r['status'] !== 'pending') throw new Exception('This return is no longer pending approval.');
            $items = self::items($id);
            foreach ($items as $item) {
                $outcome = (string) ($outcomes[(int) $item['id']] ?? '');
                if (!in_array($outcome, self::STOCK_OUTCOMES, true)) throw new Exception('Choose a stock outcome for every returned item.');
            }
            $claimed = Database::update('returns', ['status' => 'processing'], "id = :id AND status = 'pending'", ['id' => $id]);
            if ($claimed !== 1) throw new Exception('This return was already decided by another user.');
            foreach ($items as $item) {
                $outcome = (string) $outcomes[(int) $item['id']];
                Database::update('return_items', ['stock_outcome' => $outcome], 'id = :id', ['id' => (int) $item['id']]);
                if ($outcome === 'restock') {
                    Database::insert('stock_movements', [
                        'product_id' => (int) $item['product_id'], 'unit_id' => $item['unit_id'] ? (int) $item['unit_id'] : null,
                        'type' => 'returned', 'quantity' => (int) $item['quantity'], 'reference' => $r['return_number'],
                        'user_id' => $userId, 'created_at' => Database::now(),
                    ]);
                    if ($item['unit_id']) Database::update('inventory_units', ['status' => 'in_stock', 'note' => 'Returned from ' . $r['sale_number']], 'id = :id', ['id' => (int) $item['unit_id']]);
                } elseif ($outcome === 'damaged' && $item['unit_id']) {
                    Database::update('inventory_units', ['status' => 'damaged', 'note' => 'Returned damaged from ' . $r['sale_number']], 'id = :id', ['id' => (int) $item['unit_id']]);
                } elseif ($outcome === 'unavailable' && $item['unit_id']) {
                    Database::update('inventory_units', ['status' => 'returned', 'note' => 'Returned and held unavailable from ' . $r['sale_number']], 'id = :id', ['id' => (int) $item['unit_id']]);
                }
            }
            Database::update('returns', [
                'status' => 'completed', 'decision_note' => $note, 'decision_user_id' => $userId, 'decided_at' => Database::now(),
            ], "id = :id AND status = 'processing'", ['id' => $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function reject(int $id, string $note = '', ?int $userId = null): void
    {
        $note = trim($note);
        if ($note === '') throw new Exception('Enter a rejection note.');
        if (mb_strlen($note) > 500) throw new Exception('Keep the rejection note within 500 characters.');
        $updated = Database::update('returns', [
            'status' => 'rejected', 'decision_note' => $note, 'decision_user_id' => $userId, 'decided_at' => Database::now(),
        ], "id = :id AND status = 'pending'", ['id' => $id]);
        if ($updated !== 1) throw new Exception('This return is no longer pending approval.');
    }
}
