<?php
declare(strict_types=1);
/**
 * Stock & sales data-integrity checker (CLI).
 * Reports inconsistencies between inventory_units, stock_movements,
 * sale_items, return_items, goods_received_items and the sale money math.
 * Read-only: never modifies data.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$fail = 0;
$warn = 0;
$info = 0;

function ok(string $m): void   { echo "  OK   $m\n"; }
function bad(string $m): void  { global $fail; $fail++; echo "  FAIL $m\n"; }
function warn(string $m): void { global $warn; $warn++; echo "  WARN $m\n"; }
function head(string $m): void { echo "\n=== $m ===\n"; }

/* ------------------------------------------------------------------ */
head('1. Money math on completed/pending sales');
$sales = Database::fetchAll("SELECT * FROM sales WHERE status IN ('completed','pending') ORDER BY id");
foreach ($sales as $s) {
    $sub = round((float) $s['subtotal'], 2);
    $disc = round((float) $s['discount'], 2);
    $tax = round((float) $s['tax_amount'], 2);
    $fee = round((float) $s['delivery_fee'], 2);
    $total = round((float) $s['total'], 2);

    $itemSum = (float) Database::fetchValue('SELECT COALESCE(SUM(line_total),0) FROM sale_items WHERE sale_id = ?', [$s['id']]);
    $itemSum = round($itemSum, 2);

    if (abs($itemSum - $sub) > 0.005) {
        bad("sale {$s['sale_number']}: subtotal={$sub} but sum(line_total)={$itemSum}");
    }
    if ($disc > $sub + 0.005) {
        bad("sale {$s['sale_number']}: discount {$disc} exceeds subtotal {$sub}");
    }
    $expectTax = round(($sub - $disc) * vat_rate() / 100, 2);
    if (abs($expectTax - $tax) > 0.005) {
        bad("sale {$s['sale_number']}: tax={$tax} but expected {$expectTax} (vat=" . vat_rate() . ")");
    }
    $expectTotal = round($sub - $disc + $tax + $fee, 2);
    if (abs($expectTotal - $total) > 0.005) {
        bad("sale {$s['sale_number']}: total={$total} but expected {$expectTotal}");
    }
    $lineCount = (int) Database::fetchValue('SELECT COUNT(*) FROM sale_items WHERE sale_id = ?', [$s['id']]);
    if ($lineCount === 0) {
        bad("sale {$s['sale_number']}: has zero sale_items");
    }
}
ok('money math checked for ' . count($sales) . ' sales');

/* ------------------------------------------------------------------ */
head('2. sale_items line math');
$badLines = Database::fetchAll(
    "SELECT si.*, s.sale_number FROM sale_items si JOIN sales s ON s.id = si.sale_id
      WHERE ABS(si.line_total - si.unit_price * si.quantity) > 0.005"
);
foreach ($badLines as $l) {
    bad("sale {$l['sale_number']} item #{$l['id']}: line_total={$l['line_total']} != unit_price*quantity");
}
ok('sale_items line math checked');

/* ------------------------------------------------------------------ */
head('3. Stock movements vs sale_items (sold)');
// Every completed/pending sale item must have a matching 'sold' movement
// (reference = sale_number) with quantity = -qty, and for serialized items
// a unit_id that matches.
$rows = Database::fetchAll(
    "SELECT si.id AS si_id, si.sale_id, si.unit_id, si.quantity, si.product_id, s.sale_number, s.status, p.is_serialized
       FROM sale_items si
       JOIN sales s ON s.id = si.sale_id
       JOIN products p ON p.id = si.product_id"
);
foreach ($rows as $r) {
    if ($r['status'] === 'cancelled') continue; // voided path handled below
    $m = Database::fetch(
        "SELECT * FROM stock_movements WHERE product_id = ? AND type = 'sold' AND reference = ?
         ORDER BY id DESC LIMIT 1",
        [$r['product_id'], $r['sale_number']]
    );
    if (!$m) {
        bad("sale {$r['sale_number']}: no 'sold' movement for sale_item #{$r['si_id']} (product {$r['product_id']})");
        continue;
    }
    if ((int) $m['quantity'] !== -(int) $r['quantity']) {
        bad("sale {$r['sale_number']}: 'sold' movement qty={$m['quantity']} but sale_item qty={$r['quantity']}");
    }
    if ((int) $r['is_serialized'] === 1 && (int) $m['unit_id'] !== (int) $r['unit_id']) {
        bad("sale {$r['sale_number']}: movement unit_id={$m['unit_id']} but sale_item unit_id={$r['unit_id']}");
    }
}
ok('sold movements checked');

/* ------------------------------------------------------------------ */
head('4. Cancelled sales must have offsetting voided movements');
$cancelled = Database::fetchAll("SELECT * FROM sales WHERE status = 'cancelled'");
foreach ($cancelled as $s) {
    $soldQty = (float) Database::fetchValue(
        "SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE reference = ? AND type = 'sold'",
        [$s['sale_number']]
    );
    $voidQty = (float) Database::fetchValue(
        "SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE reference = ? AND type = 'voided'",
        [$s['sale_number']]
    );
    if (abs($soldQty + $voidQty) > 0.005) {
        bad("sale {$s['sale_number']}: sold={$soldQty} voided={$voidQty} do not cancel out");
    }
    // units sold on this voided sale must be back to in_stock (or later resold)
    $units = Database::fetchAll(
        "SELECT u.id, u.status FROM inventory_units u JOIN sale_items si ON si.unit_id = u.id WHERE si.sale_id = ?",
        [$s['id']]
    );
    foreach ($units as $u) {
        if ($u['status'] !== 'in_stock') {
            warn("sale {$s['sale_number']}: unit #{$u['id']} status={$u['status']} after void (may have been resold — checking later)");
        }
    }
}
ok('voided movements checked');

/* ------------------------------------------------------------------ */
head('5. Unit status vs current sale state');
// For each inventory_unit, find its latest sale_item. Expected status:
//  - referenced by a completed/pending sale (not cancelled): 'sold' (unless a return restored it)
//  - otherwise: not 'sold'
$units = Database::fetchAll("SELECT * FROM inventory_units");
foreach ($units as $u) {
    $refs = Database::fetchAll(
        "SELECT si.id, s.status FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.unit_id = ? ORDER BY si.id DESC",
        [$u['id']]
    );
    $liveSale = null;
    foreach ($refs as $ref) {
        if (in_array($ref['status'], ['completed', 'pending'], true)) {
            $liveSale = $ref;
            break;
        }
    }
    // Was the unit returned & approved? (restores to in_stock)
    $approvedReturn = (int) Database::fetchValue(
        "SELECT COUNT(*) FROM return_items ri JOIN returns r ON r.id = ri.return_id
          WHERE ri.unit_id = ? AND r.status = 'completed'",
        [$u['id']]
    ) > 0;

    if ($liveSale && !$approvedReturn) {
        if ($u['status'] !== 'sold') {
            bad("unit #{$u['id']} ({$u['serial_number']}) status={$u['status']} but is sold on live sale");
        }
    } else {
        if ($u['status'] === 'sold') {
            warn("unit #{$u['id']} ({$u['serial_number']}) is 'sold' but has no live sale reference" . ($approvedReturn ? ' (has approved return)' : ''));
        }
    }
}
ok('unit statuses checked');

/* ------------------------------------------------------------------ */
head('6. Returns: restock movements and refund caps');
$returns = Database::fetchAll("SELECT * FROM returns");
foreach ($returns as $r) {
    $items = Database::fetchAll(
        "SELECT ri.*, si.unit_price, si.quantity AS si_qty FROM return_items ri
           JOIN sale_items si ON si.id = ri.sale_item_id WHERE ri.return_id = ?",
        [$r['id']]
    );
    $cap = 0.0;
    foreach ($items as $it) {
        $cap += round((float) $it['unit_price'] * (int) $it['quantity'], 2);
    }
    $cap = round($cap, 2);
    if ((float) $r['refund_amount'] > $cap + 0.005) {
        bad("return {$r['return_number']}: refund {$r['refund_amount']} exceeds item cap {$cap}");
    }
    if ($r['status'] === 'completed') {
        foreach ($items as $it) {
            $m = Database::fetch(
                "SELECT * FROM stock_movements WHERE type = 'returned' AND reference = ? AND product_id = ? LIMIT 1",
                [$r['return_number'], (int) Database::fetchValue('SELECT product_id FROM sale_items WHERE id = ?', [$it['sale_item_id']])]
            );
            if (!$m) {
                bad("return {$r['return_number']}: no 'returned' movement for sale_item #{$it['sale_item_id']}");
            }
        }
    }
    if (!in_array($r['status'], ['pending', 'completed', 'rejected'], true)) {
        bad("return {$r['return_number']}: unknown status {$r['status']}");
    }
}
ok('returns checked');

/* ------------------------------------------------------------------ */
head('7. Serialized stock: units vs movement journal (informational)');
$prods = Database::fetchAll("SELECT * FROM products WHERE is_serialized = 1");
foreach ($prods as $p) {
    $units = (int) Database::fetchValue("SELECT COUNT(*) FROM inventory_units WHERE product_id = ? AND status = 'in_stock'", [$p['id']]);
    $journal = (int) Database::fetchValue("SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE product_id = ?", [$p['id']]);
    if ($units !== $journal) {
        echo "  INFO {$p['sku']}: in_stock units={$units}, movement journal={$journal} (expected divergence if units were status-changed/removed manually)\n";
        $info++;
    }
}
ok('serialized journal divergence reported');

/* ------------------------------------------------------------------ */
head('8. Negative bulk stock');
$neg = Database::fetchAll(
    "SELECT product_id, SUM(quantity) AS q FROM stock_movements GROUP BY product_id HAVING SUM(quantity) < 0"
);
foreach ($neg as $n) {
    $p = Product::find((int) $n['product_id']);
    bad('product ' . ($p['name'] ?? $n['product_id']) . ' has negative stock ' . $n['q']);
}
ok('bulk stock negativity checked');

/* ------------------------------------------------------------------ */
head('9. Orphaned references');
$orphanSi = (int) Database::fetchValue(
    "SELECT COUNT(*) FROM sale_items si LEFT JOIN inventory_units u ON u.id = si.unit_id
      WHERE si.unit_id IS NOT NULL AND u.id IS NULL"
);
if ($orphanSi > 0) bad("$orphanSi sale_items reference deleted units");

$orphanMov = (int) Database::fetchValue(
    "SELECT COUNT(*) FROM stock_movements m LEFT JOIN sales s ON s.sale_number = m.reference
      WHERE m.type IN ('sold','voided') AND s.id IS NULL"
);
if ($orphanMov > 0) warn("$orphanMov sold/voided movements reference unknown sale numbers");

$orphanRi = (int) Database::fetchValue(
    "SELECT COUNT(*) FROM return_items ri LEFT JOIN inventory_units u ON u.id = ri.unit_id
      WHERE ri.unit_id IS NOT NULL AND u.id IS NULL"
);
if ($orphanRi > 0) bad("$orphanRi return_items reference deleted units");

$dupSerials = (int) Database::fetchValue(
    "SELECT COUNT(*) FROM (SELECT serial_number, COUNT(*) c FROM inventory_units GROUP BY serial_number HAVING c > 1)"
);
if ($dupSerials > 0) bad("$dupSerials duplicate serial numbers exist");

ok('orphan / duplicate checks done');

/* ------------------------------------------------------------------ */
head('10. GRN totals');
$grns = Database::fetchAll("SELECT * FROM goods_received");
foreach ($grns as $g) {
    $sum = (float) Database::fetchValue(
        'SELECT COALESCE(SUM(unit_cost * quantity),0) FROM goods_received_items WHERE grn_id = ?',
        [$g['id']]
    );
    if (abs(round($sum, 2) - round((float) $g['total_cost'], 2)) > 0.005) {
        bad("GRN {$g['grn_number']}: total_cost={$g['total_cost']} but items sum to {$sum}");
    }
    // serialized GRN items should have created units
    foreach (GoodsReceived::items((int) $g['id']) as $it) {
        if ((int) $it['is_serialized'] === 1) {
            $units = (int) Database::fetchValue(
                "SELECT COUNT(*) FROM inventory_units WHERE product_id = ? AND received_at >= ?",
                [$it['product_id'], $g['created_at']]
            );
            // loose check: at least quantity units exist for this product
            $totalUnits = (int) Database::fetchValue('SELECT COUNT(*) FROM inventory_units WHERE product_id = ?', [$it['product_id']]);
            if ($totalUnits < (int) $it['quantity']) {
                bad("GRN {$g['grn_number']}: serialized product #{$it['product_id']} qty={$it['quantity']} but only {$totalUnits} units exist");
            }
        }
    }
}
ok('GRN totals checked');

/* ------------------------------------------------------------------ */
echo "\nRESULT: " . ($fail === 0 ? "NO FAILURES" : "$fail FAILURES") .
     " | $warn warnings | $info informational\n";
exit($fail === 0 ? 0 : 1);
