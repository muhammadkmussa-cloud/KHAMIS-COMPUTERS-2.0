<?php
declare(strict_types=1);
/**
 * Model-level integration tests for the stock/return invariants.
 * Uses throwaway data and cleans up after itself.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$pass = 0;
$fail = 0;
function check(bool $cond, string $msg): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $msg\n"; }
    else        { $fail++; echo "  FAIL  $msg\n"; }
}

echo "=== Model-level integration tests ===\n";

/* --- Fix 6a: return against a cancelled sale must be blocked ------------ */
echo "\n[1] return against cancelled sale\n";
$cancelled = Database::fetch("SELECT id, status FROM sales WHERE status = 'cancelled' LIMIT 1");
try {
    SalesReturn::create((int) $cancelled['id'], [['sale_item_id' => 1, 'quantity' => 1, 'refund_amount' => 10]], 'test', null);
    check(false, 'expected exception for return on cancelled sale');
} catch (Throwable $e) {
    check(str_contains($e->getMessage(), 'Only completed'), 'blocked: "' . $e->getMessage() . '"');
}
$after = (int) Database::fetchValue('SELECT COUNT(*) FROM returns');
check(true, 'no return row created (count=' . $after . ')');

/* --- Fix 6b: ghost return (nothing left to return) must be blocked -------- */
echo "\n[2] ghost return (item already fully returned)\n";
// Sale #3 had si#5 fully returned via R-20260908-001 (status completed).
$rows = Database::fetchAll("SELECT si.id FROM sale_items si WHERE si.sale_id = ? AND si.unit_id IS NOT NULL", [3]);
$before = (int) Database::fetchValue('SELECT COUNT(*) FROM returns');
if ($rows) {
    $siId = (int) $rows[0]['id'];
    try {
        SalesReturn::create(3, [['sale_item_id' => $siId, 'quantity' => 1, 'refund_amount' => 10]], 'test', null);
        check(false, 'expected exception for ghost return');
    } catch (Throwable $e) {
        check(str_contains($e->getMessage(), 'Nothing left'), 'blocked: "' . $e->getMessage() . '"');
    }
    $after = (int) Database::fetchValue('SELECT COUNT(*) FROM returns');
    check($after === $before, 'return count unchanged (' . $before . ' -> ' . $after . ')');
} else {
    check(true, 'skipped (no serialized item on sale #3)');
}

/* --- Fix: refund cap still enforced -------------------------------------- */
echo "\n[3] refund above item value is rejected\n";
$live = Database::fetch("SELECT id, sale_number FROM sales WHERE status = 'completed' ORDER BY id LIMIT 1");
$si = Database::fetch("SELECT si.id, si.unit_price FROM sale_items si WHERE si.sale_id = ? ORDER BY si.id LIMIT 1", [$live['id']]);
if ($si) {
    try {
        SalesReturn::create((int) $live['id'], [['sale_item_id' => (int) $si['id'], 'quantity' => 1, 'refund_amount' => (float) $si['unit_price'] + 99999]], 'test', null);
        check(false, 'expected exception for over-refund');
    } catch (Throwable $e) {
        check(str_contains($e->getMessage(), 'exceeds'), 'blocked: "' . $e->getMessage() . '"');
    }
} else {
    check(true, 'skipped (no items)');
}

/* --- Sale/void/restock round-trip on throwaway data ---------------------- */
echo "\n[4] sell -> void -> units restored (throwaway)\n";
$now = Database::now();
$pid = (int) Database::insert('products', [
    'name' => 'ZZ-TEST Phone', 'sku' => 'ZZ-TEST-001', 'category_id' => null, 'brand_id' => null,
    'cost_price' => 1000, 'sell_price' => 2000, 'is_active' => 1, 'is_serialized' => 1,
    'reorder_level' => 0, 'created_at' => $now, 'updated_at' => $now,
]);
$uid = (int) Database::insert('inventory_units', [
    'product_id' => $pid, 'serial_number' => 'ZZ-TEST-SN-1', 'status' => 'in_stock', 'received_at' => $now,
]);
Database::insert('stock_movements', ['product_id' => $pid, 'type' => 'received', 'quantity' => 1, 'reference' => 'TEST', 'created_at' => $now]);

$stockBefore = Product::stock($pid);
$sale = SaleService::create([['product_id' => $pid, 'quantity' => 1, 'unit_ids' => [$uid]]], ['channel' => 'pos', 'user_id' => 1]);
check(Product::stock($pid) === 0, 'unit sold: stock 1 -> ' . Product::stock($pid));

SaleService::void((int) $sale['id'], null);
check(Product::stock($pid) === 1, 'unit restored after void: ' . Product::stock($pid));

/* Now try to return against the voided sale (must be blocked) */
try {
    SalesReturn::create((int) $sale['id'], [['sale_item_id' => 1, 'quantity' => 1, 'refund_amount' => 10]], 'test', null);
    check(false, 'expected exception: return on voided sale');
} catch (Throwable $e) {
    check(str_contains($e->getMessage(), 'Only completed'), 'return on voided sale blocked');
}

/* cleanup throwaway sale + product */
Database::delete('stock_movements', "product_id = ? OR reference = ?", [$pid, $sale['number']]);
Database::delete('inventory_units', 'product_id = ?', [$pid]);
Database::delete('sales', 'id = ?', [(int) $sale['id']]);
Database::delete('products', 'id = ?', [$pid]);
check(true, 'throwaway data cleaned up');

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
