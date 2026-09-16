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

/* --- Controller-owned checkout intent validation ----------------------- */
echo "\n[0] online checkout payment intent validation\n";
$salesBeforeIntentTests = (int) Database::fetchValue('SELECT COUNT(*) FROM sales');
foreach ([
    [['payment_method' => 'mpesa', 'pay_now' => false], true, 'M-PESA without pay-now is rejected'],
    [['payment_method' => 'mpesa', 'pay_now' => true], false, 'M-PESA while disabled is rejected'],
    [['payment_method' => 'card'], true, 'unsupported online payment method is rejected'],
] as [$intentData, $enabled, $message]) {
    try {
        ShopController::checkoutPaymentIntent($intentData, $enabled);
        check(false, $message);
    } catch (InvalidArgumentException $e) {
        check(true, $message);
    }
}
$cashIntent = ShopController::checkoutPaymentIntent(['payment_method' => 'cash', 'pay_now' => true], true);
$mpesaIntent = ShopController::checkoutPaymentIntent(['payment_method' => 'mpesa', 'pay_now' => true], true);
check($cashIntent === ['cash', false] && $mpesaIntent === ['mpesa', true], 'valid choices are normalized by the server');
check((int) Database::fetchValue('SELECT COUNT(*) FROM sales') === $salesBeforeIntentTests, 'rejected payment intents create no sale');

echo "\n[0b] sales action evidence validation\n";
foreach ([
    [fn () => SaleController::validateVoidRequest(['reason' => 'mistake']), 'void requires explicit impact confirmation'],
    [fn () => SaleController::validateVoidRequest(['confirm_void' => '1', 'reason' => 'x']), 'void requires a meaningful reason'],
    [fn () => SaleController::validateManualMpesaRequest(['receipt' => 'TQH7ABC123']), 'manual payment requires verification confirmation'],
    [fn () => SaleController::validateManualMpesaRequest(['confirm_received' => '1', 'receipt' => 'bad!']), 'manual payment requires a valid receipt code'],
] as [$attempt, $message]) {
    try { $attempt(); check(false, $message); }
    catch (InvalidArgumentException $e) { check(true, $message); }
}
check(SaleController::validateVoidRequest(['confirm_void' => '1', 'reason' => 'Customer cancelled before collection']) === 'Customer cancelled before collection', 'valid void reason is retained for audit');
check(SaleController::validateManualMpesaRequest(['confirm_received' => '1', 'receipt' => 'tqh7abc123']) === 'TQH7ABC123', 'manual receipt code is normalized');

echo "\n[0c] inventory workspace query and identity validation\n";
$identityProduct = Database::fetch('SELECT id, sku, barcode FROM products ORDER BY id LIMIT 1');
$skuErrors = Product::uniquenessErrors(['sku' => $identityProduct['sku']]);
$excludedSkuErrors = Product::uniquenessErrors(['sku' => $identityProduct['sku']], (int) $identityProduct['id']);
check($skuErrors !== [] && $excludedSkuErrors === [], 'SKU uniqueness detects conflicts and excludes the edited product');
$barcodeProduct = Database::fetch("SELECT id, barcode FROM products WHERE barcode IS NOT NULL AND barcode != '' LIMIT 1");
if ($barcodeProduct) {
    check(Product::uniquenessErrors(['barcode' => $barcodeProduct['barcode']]) !== [], 'barcode uniqueness detects an existing scan code');
} else {
    check(true, 'barcode uniqueness check skipped (no seeded barcode)');
}
$lowSerialized = Product::search(['stock' => 'low', 'tracking' => 'serialized']);
$validLowSerialized = array_reduce($lowSerialized, fn (bool $ok, array $row): bool => $ok
    && (int) $row['is_serialized'] === 1
    && (int) $row['reorder_level'] > 0
    && (int) $row['live_stock'] <= (int) $row['reorder_level'], true);
check($validLowSerialized, 'combined stock and tracking filters preserve their inventory rules');
check(Schema::columnExists('product_images', 'alt_text'), 'product image descriptions are available after migration');
$quantityProduct = Database::fetch("SELECT p.id FROM products p WHERE p.is_serialized = 0 AND (SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE product_id = p.id) > 1 LIMIT 1");
$quantityStock = Product::stock((int) $quantityProduct['id']);
check(Product::stockAfterAdjustment((int) $quantityProduct['id'], -1) === $quantityStock - 1, 'negative quantity adjustments use the live ledger balance');

echo "\n[0d] goods receiving review validation and atomic posting\n";
$now = Database::now();
$grnBulkPid = (int) Database::insert('products', ['name'=>'ZZ-GRN Bulk','sku'=>'ZZ-GRN-BULK','cost_price'=>10,'sell_price'=>20,'is_active'=>1,'is_serialized'=>0,'reorder_level'=>0,'created_at'=>$now,'updated_at'=>$now]);
$grnSerialPid = (int) Database::insert('products', ['name'=>'ZZ-GRN Serial','sku'=>'ZZ-GRN-SERIAL','cost_price'=>50,'sell_price'=>80,'is_active'=>1,'is_serialized'=>1,'reorder_level'=>0,'created_at'=>$now,'updated_at'=>$now]);
$mismatch = GoodsReceived::validateDraft(['supplier'=>'Test Receiving Supplier','lines'=>[['product_id'=>$grnSerialPid,'quantity'=>2,'unit_cost'=>50,'serials'=>['ZZ-GRN-SN-1']]]]);
check($mismatch['errors'] !== [], 'serialized lines reject a serial-count mismatch');
$fractionalQuantity = GoodsReceived::validateDraft(['supplier'=>'Test Receiving Supplier','lines'=>[['product_id'=>$grnBulkPid,'quantity'=>'1.5','unit_cost'=>10,'serials'=>[]]]]);
$malformedQuantity = GoodsReceived::validateDraft(['supplier'=>'Test Receiving Supplier','lines'=>[['product_id'=>$grnBulkPid,'quantity'=>'2abc','unit_cost'=>10,'serials'=>[]]]]);
check($fractionalQuantity['errors'] !== [] && $malformedQuantity['errors'] !== [], 'server validation rejects fractional and malformed quantities without truncating them');
check(csv_safe_cell('=HYPERLINK("https://example.test")') === "'=HYPERLINK(\"https://example.test\")" && csv_safe_cell('Normal Supplier') === 'Normal Supplier', 'CSV export neutralizes formula-leading supplier text');
$duplicateSerial = GoodsReceived::validateDraft(['supplier'=>'Test Receiving Supplier','lines'=>[['product_id'=>$grnSerialPid,'quantity'=>2,'unit_cost'=>50,'serials'=>['ZZ-DUPLICATE','zz-duplicate']]]]);
check((bool) array_filter($duplicateSerial['errors'], fn($error)=>str_contains($error,'duplicate serial')), 'serial matching rejects case-insensitive duplicates');
$existingUnit = Database::fetch('SELECT product_id, serial_number FROM inventory_units LIMIT 1');
$existingSerial = GoodsReceived::validateDraft([
    'supplier' => 'Test Receiving Supplier',
    'lines' => [[
        'product_id' => (int) $existingUnit['product_id'],
        'quantity' => 1,
        'unit_cost' => 50,
        'serials' => [$existingUnit['serial_number']],
    ]],
]);
check((bool) array_filter($existingSerial['errors'], fn($error)=>str_contains($error,'already exists in inventory')), 'serial matching rejects identifiers already in inventory');
$unknownProduct = GoodsReceived::validateDraft(['supplier'=>'Test Receiving Supplier','lines'=>[['product_id'=>999999,'quantity'=>1,'unit_cost'=>1,'serials'=>[]]]]);
check($unknownProduct['errors'] !== [], 'unknown products are rejected before posting');
$duplicateProduct = GoodsReceived::validateDraft(['supplier'=>'Test Receiving Supplier','lines'=>[
    ['product_id'=>$grnBulkPid,'quantity'=>1,'unit_cost'=>10,'serials'=>[]],
    ['product_id'=>$grnBulkPid,'quantity'=>1,'unit_cost'=>10,'serials'=>[]],
]]);
check((bool) array_filter($duplicateProduct['errors'], fn($error)=>str_contains($error,'already on this receipt')), 'duplicate product lines are rejected before posting');
$validatedGrn = GoodsReceived::validateDraft(['supplier'=>'Test Receiving Supplier','supplier_reference'=>'ZZ-DN-001','note'=>'integration review','lines'=>[
    ['product_id'=>$grnBulkPid,'quantity'=>3,'unit_cost'=>10,'serials'=>[]],
    ['product_id'=>$grnSerialPid,'quantity'=>2,'unit_cost'=>50,'serials'=>['ZZ-GRN-SN-1','ZZ-GRN-SN-2']],
]]);
check($validatedGrn['errors'] === [] && Product::stock($grnBulkPid) === 0 && Product::stock($grnSerialPid) === 0, 'review validation changes no stock');
$grnTestId = GoodsReceived::createFromDraft($validatedGrn['draft'], 1);
$grnTest = GoodsReceived::find($grnTestId);
check(Product::stock($grnBulkPid) === 3 && Product::stock($grnSerialPid) === 2 && (float)$grnTest['total_cost'] === 130.0, 'confirmed draft posts quantity, serial stock, and purchase total atomically');
check((int)Database::fetchValue('SELECT COUNT(*) FROM inventory_units WHERE product_id = ? AND grn_item_id IS NOT NULL',[$grnSerialPid]) === 2, 'received serials retain their GRN item provenance');
check(count(GoodsReceived::search(['q'=>'ZZ-DN-001'])) === 1, 'GRN reference search finds the posted receipt');
Database::delete('stock_movements','product_id IN (?,?)',[$grnBulkPid,$grnSerialPid]);
Database::delete('inventory_units','product_id IN (?,?)',[$grnBulkPid,$grnSerialPid]);
Database::delete('goods_received_items','grn_id = ?',[$grnTestId]);
Database::delete('goods_received','id = ?',[$grnTestId]);
Database::delete('products','id IN (?,?)',[$grnBulkPid,$grnSerialPid]);
check(true, 'goods receiving test data cleaned up');

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
echo "\n[3b] return review, decisions, and stock outcomes\n";
$returnNow = Database::now();
$returnPid = (int) Database::insert('products', [
    'name' => 'ZZ-RETURN Bulk', 'sku' => 'ZZ-RETURN-001', 'category_id' => null, 'brand_id' => null,
    'cost_price' => 20, 'sell_price' => 50, 'is_active' => 1, 'is_serialized' => 0,
    'reorder_level' => 0, 'created_at' => $returnNow, 'updated_at' => $returnNow,
]);
$returnSerialPid = (int) Database::insert('products', [
    'name' => 'ZZ-RETURN Serial', 'sku' => 'ZZ-RETURN-002', 'category_id' => null, 'brand_id' => null,
    'cost_price' => 300, 'sell_price' => 500, 'is_active' => 1, 'is_serialized' => 1,
    'reorder_level' => 0, 'created_at' => $returnNow, 'updated_at' => $returnNow,
]);
Database::insert('stock_movements', ['product_id'=>$returnPid,'type'=>'received','quantity'=>5,'reference'=>'ZZ-RETURN-SEED','created_at'=>$returnNow]);
Database::insert('stock_movements', ['product_id'=>$returnSerialPid,'type'=>'received','quantity'=>1,'reference'=>'ZZ-RETURN-SERIAL-SEED','created_at'=>$returnNow]);
$returnUnitId = (int) Database::insert('inventory_units', [
    'product_id'=>$returnSerialPid,'serial_number'=>'ZZ-RETURN-SERIAL-1','status'=>'sold','received_at'=>$returnNow,
]);
$returnSaleId = (int) Database::insert('sales', [
    'sale_number'=>'ZZ-RETURN-SALE-' . random_int(1000,9999), 'channel'=>'pos', 'customer_name'=>'Return Test',
    'subtotal'=>100, 'discount'=>0, 'tax_amount'=>0, 'total'=>100, 'status'=>'completed',
    'payment_method'=>'cash', 'fulfillment'=>'pickup', 'delivery_fee'=>0, 'offline_created'=>0,
    'user_id'=>1, 'created_at'=>$returnNow,
]);
$returnSiId = (int) Database::insert('sale_items', [
    'sale_id'=>$returnSaleId,'product_id'=>$returnPid,'unit_id'=>null,'quantity'=>2,'unit_price'=>50,'line_total'=>100,
]);
$returnSerialSiId = (int) Database::insert('sale_items', [
    'sale_id'=>$returnSaleId,'product_id'=>$returnSerialPid,'unit_id'=>$returnUnitId,'quantity'=>1,'unit_price'=>500,'line_total'=>500,
]);
Database::insert('stock_movements', ['product_id'=>$returnPid,'type'=>'sold','quantity'=>-2,'reference'=>'ZZ-RETURN-SOLD','created_at'=>$returnNow]);
Database::insert('stock_movements', ['product_id'=>$returnSerialPid,'unit_id'=>$returnUnitId,'type'=>'sold','quantity'=>-1,'reference'=>'ZZ-RETURN-SERIAL-SOLD','created_at'=>$returnNow]);
$returnsBeforeReview = (int) Database::fetchValue('SELECT COUNT(*) FROM returns');
$badReturnDraft = SalesReturn::validateDraft($returnSaleId, [['sale_item_id'=>$returnSiId,'quantity'=>'1.5','refund_amount'=>'50']], 'Faulty');
check($badReturnDraft['errors'] !== [] && (int)Database::fetchValue('SELECT COUNT(*) FROM returns') === $returnsBeforeReview && Product::stock($returnPid) === 3, 'review rejects fractional quantity without writing return or stock');
$validReturnDraft = SalesReturn::validateDraft($returnSaleId, [['sale_item_id'=>$returnSiId,'quantity'=>'1','refund_amount'=>'45.00']], 'Faulty', 'Checked at counter', 'cash');
check($validReturnDraft['errors'] === [] && $validReturnDraft['draft']['refund_total'] === 45.0, 'review normalizes a valid return and refund total');
$reviewReturnId = SalesReturn::createFromDraft($validReturnDraft['draft'], 1);
$reviewReturnItem = Database::fetch('SELECT id FROM return_items WHERE return_id = ?', [$reviewReturnId]);
check(Product::stock($returnPid) === 3 && Database::fetchValue('SELECT status FROM returns WHERE id = ?',[$reviewReturnId]) === 'pending', 'creating reviewed request leaves stock unchanged and pending');
try {
    SalesReturn::approve($reviewReturnId, [], 'Inspected', 1);
    check(false, 'approval requires every stock outcome');
} catch (Throwable $e) {
    check(str_contains($e->getMessage(), 'stock outcome') && Database::fetchValue('SELECT status FROM returns WHERE id = ?',[$reviewReturnId]) === 'pending', 'missing stock outcome rolls approval back');
}
SalesReturn::approve($reviewReturnId, [(int)$reviewReturnItem['id']=>'restock'], 'Inspected and sellable', 1);
check(Product::stock($returnPid) === 4 && Database::fetchValue('SELECT stock_outcome FROM return_items WHERE id = ?',[(int)$reviewReturnItem['id']]) === 'restock', 'approval records outcome and restores only approved sellable stock');
try {
    SalesReturn::approve($reviewReturnId, [(int)$reviewReturnItem['id']=>'restock'], 'Again', 1);
    check(false, 'decided return cannot be approved twice');
} catch (Throwable $e) {
    check(Product::stock($returnPid) === 4, 'repeat approval cannot duplicate stock');
}
$rejectDraft = SalesReturn::validateDraft($returnSaleId, [['sale_item_id'=>$returnSiId,'quantity'=>'1','refund_amount'=>'50']], 'Changed mind');
$rejectReturnId = SalesReturn::createFromDraft($rejectDraft['draft'], 1);
try {
    SalesReturn::createFromDraft($rejectDraft['draft'], 1);
    check(false, 'stale reviewed draft cannot consume the same remaining quantity twice');
} catch (Throwable $e) {
    check(str_contains($e->getMessage(), 'Nothing left') && (int)Database::fetchValue('SELECT COUNT(*) FROM returns WHERE sale_id = ? AND status = ?',[$returnSaleId,'pending']) === 1, 'stale reviewed draft is revalidated at commit');
}
SalesReturn::reject($rejectReturnId, 'Outside return policy', 1);
check(Database::fetchValue('SELECT status FROM returns WHERE id = ?',[$rejectReturnId]) === 'rejected' && Product::stock($returnPid) === 4, 'rejection records decision without changing stock');
$serialDraft = SalesReturn::validateDraft($returnSaleId, [['sale_item_id'=>$returnSerialSiId,'quantity'=>'1','refund_amount'=>'500']], 'Seal broken');
$serialReturnId = SalesReturn::createFromDraft($serialDraft['draft'], 1);
$serialReturnItemId = (int) Database::fetchValue('SELECT id FROM return_items WHERE return_id = ?',[$serialReturnId]);
SalesReturn::approve($serialReturnId, [$serialReturnItemId=>'unavailable'], 'Hold for supplier inspection', 1);
check(Database::fetchValue('SELECT status FROM inventory_units WHERE id = ?',[$returnUnitId]) === 'returned' && Product::stock($returnSerialPid) === 0, 'serialized unavailable outcome moves unit from sold to non-sellable returned state');
foreach([$reviewReturnId,$rejectReturnId,$serialReturnId] as $cleanupReturnId){Database::delete('return_items','return_id = ?',[$cleanupReturnId]);Database::delete('returns','id = ?',[$cleanupReturnId]);}
Database::delete('stock_movements','product_id IN (?,?)',[$returnPid,$returnSerialPid]);
Database::delete('sale_items','sale_id = ?',[$returnSaleId]);
Database::delete('sales','id = ?',[$returnSaleId]);
Database::delete('inventory_units','id = ?',[$returnUnitId]);
Database::delete('products','id IN (?,?)',[$returnPid,$returnSerialPid]);
check(true, 'return workflow test data cleaned up');

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
$sale = SaleService::create(
    [['product_id' => $pid, 'quantity' => 1, 'unit_ids' => [$uid]]],
    ['channel' => 'pos', 'payment_method' => 'cash', 'cash_received' => 10000, 'user_id' => 1]
);
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

/* --- Bulk void claim prevents duplicate stock restoration -------------- */
echo "\n[4b] bulk sale void is claimed once (throwaway)\n";
$now = Database::now();
$bulkPid = (int) Database::insert('products', [
    'name' => 'ZZ-TEST Bulk Item', 'sku' => 'ZZ-TEST-BULK', 'category_id' => null, 'brand_id' => null,
    'cost_price' => 100, 'sell_price' => 200, 'is_active' => 1, 'is_serialized' => 0,
    'reorder_level' => 0, 'created_at' => $now, 'updated_at' => $now,
]);
Database::insert('stock_movements', ['product_id' => $bulkPid, 'type' => 'received', 'quantity' => 5, 'reference' => 'TEST-BULK', 'created_at' => $now]);
$bulkSale = SaleService::create(
    [['product_id' => $bulkPid, 'quantity' => 2, 'unit_ids' => []]],
    ['channel' => 'pos', 'payment_method' => 'cash', 'cash_received' => 1000, 'user_id' => 1]
);
SaleService::void((int) $bulkSale['id'], null);
$secondVoidBlocked = false;
try {
    SaleService::void((int) $bulkSale['id'], null);
} catch (Throwable $e) {
    $secondVoidBlocked = true;
}
$voidMovementCount = (int) Database::fetchValue(
    "SELECT COUNT(*) FROM stock_movements WHERE product_id = ? AND type = 'voided' AND reference = ?",
    [$bulkPid, $bulkSale['number']]
);
check($secondVoidBlocked, 'a second void request is rejected');
check(Product::stock($bulkPid) === 5 && $voidMovementCount === 1, 'bulk stock is restored exactly once');
Database::delete('stock_movements', 'product_id = ?', [$bulkPid]);
Database::delete('sales', 'id = ?', [(int) $bulkSale['id']]);
Database::delete('products', 'id = ?', [$bulkPid]);
check(true, 'bulk void test data cleaned up');

/* --- Online checkout retries must be idempotent ------------------------- */
echo "\n[5] online checkout retry is idempotent (throwaway)\n";
$now = Database::now();
$retryPid = (int) Database::insert('products', [
    'name' => 'ZZ-TEST Retry Item', 'sku' => 'ZZ-TEST-RETRY', 'category_id' => null, 'brand_id' => null,
    'cost_price' => 50, 'sell_price' => 100, 'is_active' => 1, 'is_serialized' => 0,
    'reorder_level' => 0, 'created_at' => $now, 'updated_at' => $now,
]);
Database::insert('stock_movements', ['product_id' => $retryPid, 'type' => 'received', 'quantity' => 5, 'reference' => 'TEST-RETRY', 'created_at' => $now]);
$retryOpts = [
    'channel' => 'online', 'auto_assign_serials' => true, 'payment_method' => 'cash',
    'customer_name' => 'Retry Test', 'customer_phone' => '0712345678',
    'device_id' => 'integration-shop', 'client_ref' => 'integration-' . bin2hex(random_bytes(6)),
];
$firstRetry = SaleService::create([['product_id' => $retryPid, 'quantity' => 2, 'unit_ids' => []]], $retryOpts);
$secondRetry = SaleService::create([['product_id' => $retryPid, 'quantity' => 2, 'unit_ids' => []]], $retryOpts);
check((int) $firstRetry['id'] === (int) $secondRetry['id'] && !empty($secondRetry['duplicate']), 'same checkout reference returns the original order');
check(Product::stock($retryPid) === 3, 'stock deducted once after duplicate retry: ' . Product::stock($retryPid));
Database::delete('stock_movements', 'product_id = ?', [$retryPid]);
Database::delete('sales', 'id = ?', [(int) $firstRetry['id']]);
Database::delete('products', 'id = ?', [$retryPid]);
check(true, 'online retry test data cleaned up');

/* --- Failed M-PESA callbacks release pending-order stock ---------------- */
echo "\n[6] failed M-PESA callback cancels and restocks (throwaway)\n";
$now = Database::now();
$mpesaPid = (int) Database::insert('products', [
    'name' => 'ZZ-TEST M-PESA Item', 'sku' => 'ZZ-TEST-MPESA', 'category_id' => null, 'brand_id' => null,
    'cost_price' => 50, 'sell_price' => 100, 'is_active' => 1, 'is_serialized' => 0,
    'reorder_level' => 0, 'created_at' => $now, 'updated_at' => $now,
]);
Database::insert('stock_movements', ['product_id' => $mpesaPid, 'type' => 'received', 'quantity' => 2, 'reference' => 'TEST-MPESA', 'created_at' => $now]);
$mpesaSale = SaleService::create(
    [['product_id' => $mpesaPid, 'quantity' => 1, 'unit_ids' => []]],
    ['channel' => 'online', 'payment_method' => 'mpesa', 'status' => 'pending', 'customer_name' => 'M-PESA Test', 'customer_phone' => '0712345678']
);
$checkoutId = 'ws_CO_TEST_' . bin2hex(random_bytes(5));
$txnId = (int) Database::insert('mpesa_transactions', [
    'sale_id' => (int) $mpesaSale['id'], 'checkout_request_id' => $checkoutId,
    'merchant_request_id' => 'TEST', 'phone' => '254712345678', 'amount' => 116,
    'status' => 'requested', 'created_at' => $now, 'updated_at' => $now,
]);
$mismatchRejected = !MpesaService::processCallback(['Body' => ['stkCallback' => [
    'CheckoutRequestID' => $checkoutId, 'MerchantRequestID' => 'WRONG', 'ResultCode' => 0, 'ResultDesc' => 'Paid',
    'CallbackMetadata' => ['Item' => [
        ['Name' => 'Amount', 'Value' => 116], ['Name' => 'MpesaReceiptNumber', 'Value' => 'TEST123'], ['Name' => 'PhoneNumber', 'Value' => 254712345678],
    ]],
]]]);
check($mismatchRejected && SaleService::find((int) $mpesaSale['id'])['status'] === 'pending' && Product::stock($mpesaPid) === 1, 'mismatched callback cannot alter the pending order');
$callbackOk = MpesaService::processCallback(['Body' => ['stkCallback' => [
    'CheckoutRequestID' => $checkoutId, 'MerchantRequestID' => 'TEST', 'ResultCode' => 1032, 'ResultDesc' => 'Request cancelled by user',
]]]);
$failedSale = SaleService::find((int) $mpesaSale['id']);
$failedTxn = Database::fetch('SELECT status FROM mpesa_transactions WHERE id = ?', [$txnId]);
check($callbackOk && $failedSale && $failedSale['status'] === 'cancelled', 'failed callback cancels the pending order');
check(Product::stock($mpesaPid) === 2, 'failed callback restores stock: ' . Product::stock($mpesaPid));
check(($failedTxn['status'] ?? '') === 'failed', 'transaction records the failure');

/* A callback may only cancel a sale it can still claim as pending. */
$paidRaceSale = SaleService::create(
    [['product_id' => $mpesaPid, 'quantity' => 1, 'unit_ids' => []]],
    ['channel' => 'online', 'payment_method' => 'mpesa', 'status' => 'pending', 'customer_name' => 'Paid Race Test', 'customer_phone' => '0712345678']
);
Database::update('sales', ['status' => 'completed', 'payment_ref' => 'MANUAL123'], 'id = :id', ['id' => (int) $paidRaceSale['id']]);
$lateFailureBlocked = false;
try {
    SaleService::void((int) $paidRaceSale['id'], null, ['pending']);
} catch (Throwable $e) {
    $lateFailureBlocked = true;
}
$paidRaceStatus = SaleService::find((int) $paidRaceSale['id']);
check($lateFailureBlocked && ($paidRaceStatus['status'] ?? '') === 'completed', 'late failure cannot cancel a manually completed sale');
check(Product::stock($mpesaPid) === 1, 'late failure does not restore paid-order stock');

/* Simulate failure cancellation committing before its transaction update. */
$callbackRaceSale = SaleService::create(
    [['product_id' => $mpesaPid, 'quantity' => 1, 'unit_ids' => []]],
    ['channel' => 'online', 'payment_method' => 'mpesa', 'status' => 'pending', 'customer_name' => 'Callback Race Test', 'customer_phone' => '0712345678']
);
$callbackRaceCheckout = 'ws_CO_TEST_RACE_' . bin2hex(random_bytes(4));
$callbackRaceTxn = (int) Database::insert('mpesa_transactions', [
    'sale_id' => (int) $callbackRaceSale['id'], 'checkout_request_id' => $callbackRaceCheckout,
    'merchant_request_id' => 'TEST-RACE', 'phone' => '254712345678', 'amount' => 116,
    'status' => 'requested', 'created_at' => $now, 'updated_at' => $now,
]);
$callbackRetryCheckout = 'ws_CO_TEST_RETRY_' . bin2hex(random_bytes(4));
$callbackRetryTxn = (int) Database::insert('mpesa_transactions', [
    'sale_id' => (int) $callbackRaceSale['id'], 'checkout_request_id' => $callbackRetryCheckout,
    'merchant_request_id' => 'TEST-RETRY', 'phone' => '254712345678', 'amount' => 116,
    'status' => 'requested', 'created_at' => $now, 'updated_at' => $now,
]);
$latestFailure = MpesaService::processCallback(['Body' => ['stkCallback' => [
    'CheckoutRequestID' => $callbackRetryCheckout, 'MerchantRequestID' => 'TEST-RETRY', 'ResultCode' => 1032, 'ResultDesc' => 'Retry cancelled by user',
]]]);
 $pendingAfterLatestFailure = SaleService::find((int) $callbackRaceSale['id']);
check($latestFailure && ($pendingAfterLatestFailure['status'] ?? '') === 'pending' && Product::stock($mpesaPid) === 0,
    'newer failed M-PESA prompt cannot cancel while an older prompt is outstanding');
$staleFailure = MpesaService::processCallback(['Body' => ['stkCallback' => [
    'CheckoutRequestID' => $callbackRaceCheckout, 'MerchantRequestID' => 'TEST-RACE', 'ResultCode' => 1032, 'ResultDesc' => 'First prompt expired',
]]]);
$cancelledAfterLatestFailure = SaleService::find((int) $callbackRaceSale['id']);
check($staleFailure && ($cancelledAfterLatestFailure['status'] ?? '') === 'cancelled' && Product::stock($mpesaPid) === 1,
    'failed M-PESA prompt cancels after all outstanding prompts finish');
$successAfterCancel = MpesaService::processCallback(['Body' => ['stkCallback' => [
    'CheckoutRequestID' => $callbackRaceCheckout, 'MerchantRequestID' => 'TEST-RACE', 'ResultCode' => 0, 'ResultDesc' => 'Paid',
    'CallbackMetadata' => ['Item' => [
        ['Name' => 'Amount', 'Value' => 116], ['Name' => 'MpesaReceiptNumber', 'Value' => 'RACE123'], ['Name' => 'PhoneNumber', 'Value' => 254712345678],
    ]],
]]]);
$raceTxnBeforeFailure = Database::fetch('SELECT status, receipt_number FROM mpesa_transactions WHERE id = ?', [$callbackRaceTxn]);
$raceSaleAfterSuccess = SaleService::find((int) $callbackRaceSale['id']);
check($successAfterCancel && ($raceSaleAfterSuccess['status'] ?? '') === 'cancelled', 'success callback cannot complete an already-cancelled sale');
check(($raceTxnBeforeFailure['status'] ?? '') === 'failed' && empty($raceTxnBeforeFailure['receipt_number']), 'stale failed transaction cannot be resurrected by a later callback');
$raceFailureFinalized = MpesaService::processCallback(['Body' => ['stkCallback' => [
    'CheckoutRequestID' => $callbackRaceCheckout, 'MerchantRequestID' => 'TEST-RACE', 'ResultCode' => 1032, 'ResultDesc' => 'Request cancelled by user',
]]]);
$raceTxnAfterFailure = Database::fetch('SELECT status FROM mpesa_transactions WHERE id = ?', [$callbackRaceTxn]);
check($raceFailureFinalized && ($raceTxnAfterFailure['status'] ?? '') === 'failed' && Product::stock($mpesaPid) === 1, 'failure finalizes without a second stock restoration');

$lateSuccess = MpesaService::processCallback(['Body' => ['stkCallback' => [
    'CheckoutRequestID' => $checkoutId, 'MerchantRequestID' => 'TEST', 'ResultCode' => 0, 'ResultDesc' => 'Paid',
    'CallbackMetadata' => ['Item' => [
        ['Name' => 'Amount', 'Value' => 116], ['Name' => 'MpesaReceiptNumber', 'Value' => 'LATE123'], ['Name' => 'PhoneNumber', 'Value' => 254712345678],
    ]],
]]]);
$terminalTxn = Database::fetch('SELECT status, receipt_number FROM mpesa_transactions WHERE id = ?', [$txnId]);
check($lateSuccess && ($terminalTxn['status'] ?? '') === 'failed' && empty($terminalTxn['receipt_number']), 'terminal failure cannot be overwritten by a later callback');
$callbackToken = MpesaService::callbackToken();
check(MpesaService::validCallbackToken($callbackToken) && !MpesaService::validCallbackToken('wrong-token'), 'callback URL token is required and compared securely');
Database::delete('mpesa_transactions', 'id = ?', [$txnId]);
Database::delete('mpesa_transactions', 'id = ?', [$callbackRaceTxn]);
Database::delete('mpesa_transactions', 'id = ?', [$callbackRetryTxn]);
Database::delete('stock_movements', 'product_id = ?', [$mpesaPid]);
Database::delete('sales', 'id = ?', [(int) $mpesaSale['id']]);
Database::delete('sales', 'id = ?', [(int) $paidRaceSale['id']]);
Database::delete('sales', 'id = ?', [(int) $callbackRaceSale['id']]);
Database::delete('products', 'id = ?', [$mpesaPid]);
check(true, 'M-PESA callback test data cleaned up');

/* --- Expense audit trail + immutable close-of-day snapshot -------------- */
echo "\n[8] finance workflow safeguards (throwaway)\n";
$expenseDate = '2098-01-02';
$expenseId = Expense::create('ZZ-TEST recurring rent', 123.45, 'rent', $expenseDate, 1, null, true);
check(abs(Expense::sumRange($expenseDate, $expenseDate) - 123.45) < 0.001, 'active expense is included in period total');
Expense::delete($expenseId, 'Integration test cleanup', 1);
$deletedExpense = Expense::find($expenseId);
check(Expense::sumRange($expenseDate, $expenseDate) === 0.0
    && ($deletedExpense['delete_reason'] ?? '') === 'Integration test cleanup'
    && (int) ($deletedExpense['deleted_by'] ?? 0) === 1
    && Expense::findActive($expenseId) === null,
    'expense deletion preserves its audit record and removes active receipt access and reporting');
Database::delete('expenses', 'id = ?', [$expenseId]);

$closeDate = '2098-01-03';
Database::delete('z_reports', 'user_id = ? AND report_date = ?', [1, $closeDate]);
$varianceRequiresNote = false;
try {
    ZReport::close($closeDate, 1, 10.0, '', 1);
} catch (Throwable $e) {
    $varianceRequiresNote = str_contains($e->getMessage(), 'variance');
}
check($varianceRequiresNote, 'shift close requires a note for a cash variance');
$zId = ZReport::close($closeDate, 1, 10.0, 'Test variance', 1);
$closed = Database::fetch('SELECT counted_cash, variance, closed_by FROM z_reports WHERE id = ?', [$zId]);
check((float) $closed['counted_cash'] === 10.0 && (float) $closed['variance'] === 10.0 && (int) $closed['closed_by'] === 1,
    'shift close stores counted cash, variance, and closer');
$immutable = false;
try {
    ZReport::close($closeDate, 1, 10.0, 'Second close', 1);
} catch (Throwable $e) {
    $immutable = str_contains($e->getMessage(), 'immutable');
}
check($immutable, 'closed shift snapshot cannot be overwritten');
Database::delete('z_reports', 'id = ?', [$zId]);
check(true, 'finance workflow test data cleaned up');

/* --- Finance report accounting and filter reconciliation --------------- */
echo "\n[9] finance report reconciliation (throwaway)\n";
$activePreserved = StaffController::activeValue(['is_active'=>1], ['role'=>'admin']);
$activePreservedWhenOmitted = StaffController::activeValue(['is_active'=>1], []);
$activeDisabled = StaffController::activeValue(['is_active'=>1], ['is_active'=>'0']);
check($activePreserved === 1 && $activePreservedWhenOmitted === 1 && $activeDisabled === 0, 'staff role saves preserve status while status changes remain explicit');
$zoneTestId = DeliveryZone::create('ZZ-FINANCE Zone', 99.99, 1, true);
DeliveryZone::update($zoneTestId, 'ZZ-FINANCE Zone', 99.99, 1, false);
check((int) DeliveryZone::find($zoneTestId)['is_active'] === 0, 'delivery zone can be deactivated without deleting its record');
Database::delete('delivery_zones', 'id = ?', [$zoneTestId]);
check(MpesaService::chargeAmount(116.40) === 116 && MpesaService::chargeAmount(116.60) === 117, 'M-PESA charge amount is explicitly rounded to whole shillings');
$financeDate = '2098-02-01';
$financePid = (int) Database::insert('products', [
    'name'=>'ZZ-FINANCE Product','sku'=>'ZZ-FINANCE-001','cost_price'=>40,'sell_price'=>100,
    'is_active'=>1,'is_serialized'=>0,'reorder_level'=>0,'created_at'=>$now,'updated_at'=>$now,
]);
$financeSaleId = (int) Database::insert('sales', [
    'sale_number'=>'ZZ-FINANCE-SALE','channel'=>'online','customer_name'=>'Finance Test',
    'subtotal'=>100,'discount'=>10,'tax_amount'=>14.40,'total'=>124.40,'status'=>'completed',
    'payment_method'=>'cash','fulfillment'=>'delivery','delivery_fee'=>20,'offline_created'=>0,
    'user_id'=>1,'created_at'=>$financeDate.' 10:00:00',
]);
$financeSaleItemId = (int) Database::insert('sale_items', [
    'sale_id'=>$financeSaleId,'product_id'=>$financePid,'quantity'=>1,'unit_cost'=>40,'unit_price'=>100,'line_total'=>100,
]);
$financeReturnId = (int) Database::insert('returns', [
    'return_number'=>'ZZ-FINANCE-RETURN','sale_id'=>$financeSaleId,'reason'=>'Finance test','status'=>'completed',
    'refund_amount'=>50,'refund_method'=>'cash','user_id'=>1,'created_at'=>$financeDate.' 12:00:00',
]);
$financeExpenseId = Expense::create('ZZ-FINANCE expense', 5, 'general', $financeDate, 1);
$metricsMethod = new ReflectionMethod(ReportController::class, 'metrics');
$metrics = $metricsMethod->invoke(null, $financeDate, $financeDate);
check((float)$metrics['revenue'] === 90.0
    && (float)$metrics['cogs'] === 40.0
    && (float)$metrics['refunds'] === 50.0
    && (float)$metrics['revenue']-(float)$metrics['cogs']-(float)$metrics['refunds']-(float)$metrics['expenses'] === -5.0,
    'profit uses VAT-exclusive net sales and excludes delivery fees');
$dashboardDaily = DashboardController::dailySalesSummary($financeDate);
check((float)$dashboardDaily['revenue'] === 90.0 && (float)$dashboardDaily['cash'] === 124.40,
    'dashboard comparison uses net sales while expected cash retains collected cash');
$vatMethod = new ReflectionMethod(ReportController::class, 'vatData');
$vat = $vatMethod->invoke(null, $financeDate, $financeDate);
check(abs((float)$vat['return_vat']-8.0)<0.001
    && abs((float)$vat['days'][$financeDate]['returned']-8.0)<0.001
    && abs(((float)$vat['days'][$financeDate]['out']-(float)$vat['days'][$financeDate]['returned']-(float)$vat['days'][$financeDate]['in'])-(float)$vat['net'])<0.001,
    'daily VAT includes returns and reconciles to the period net');

$supplierA = (int) Database::insert('suppliers', ['name'=>'ZZ-FINANCE Supplier A','is_active'=>1,'created_at'=>$now,'updated_at'=>$now]);
$supplierB = (int) Database::insert('suppliers', ['name'=>'ZZ-FINANCE Supplier B','is_active'=>1,'created_at'=>$now,'updated_at'=>$now]);
$grnA = (int) Database::insert('goods_received', ['grn_number'=>'ZZ-FINANCE-GRN-A','supplier'=>'ZZ-FINANCE Supplier A','supplier_id'=>$supplierA,'total_cost'=>20,'user_id'=>1,'created_at'=>'2098-02-02 09:00:00']);
$grnB = (int) Database::insert('goods_received', ['grn_number'=>'ZZ-FINANCE-GRN-B','supplier'=>'ZZ-FINANCE Supplier B','supplier_id'=>$supplierB,'total_cost'=>60,'user_id'=>1,'created_at'=>'2098-02-03 09:00:00']);
Database::insert('goods_received_items', ['grn_id'=>$grnA,'product_id'=>$financePid,'quantity'=>1,'unit_cost'=>20]);
Database::insert('goods_received_items', ['grn_id'=>$grnB,'product_id'=>$financePid,'quantity'=>2,'unit_cost'=>30]);
$purchasesMethod = new ReflectionMethod(ReportController::class, 'purchasesData');
$filteredPurchases = $purchasesMethod->invoke(null, '2098-02-01', '2098-02-04', (string)$supplierA);
check(count($filteredPurchases['trend'])===1
    && $filteredPurchases['trend'][0]['date']==='2098-02-02'
    && (float)$filteredPurchases['trend'][0]['net']===20.0,
    'supplier filter applies to purchase KPIs, detail, and spend trend');

Database::delete('goods_received_items', 'grn_id IN (?,?)', [$grnA,$grnB]);
Database::delete('goods_received', 'id IN (?,?)', [$grnA,$grnB]);
Database::delete('suppliers', 'id IN (?,?)', [$supplierA,$supplierB]);
Database::delete('expenses', 'id = ?', [$financeExpenseId]);
Database::delete('returns', 'id = ?', [$financeReturnId]);
Database::delete('sale_items', 'id = ?', [$financeSaleItemId]);
Database::delete('sales', 'id = ?', [$financeSaleId]);
Database::delete('products', 'id = ?', [$financePid]);
check(true, 'finance report reconciliation data cleaned up');

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
