#!/usr/bin/env bash
# End-to-end checkout flows: POS (serialized + bulk), shop guest, offline sync.
set -u
BASE="http://localhost:8081"
JAR="/tmp/kc_flow.jar"; rm -f "$JAR"
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "  PASS  $1"; }
bad(){ FAIL=$((FAIL+1)); echo "  FAIL  $1"; }
csrf_of(){ grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{20,\}'; }

T=$(curl -s -c "$JAR" "$BASE/login" | csrf_of)
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "email=admin@khamis.local" --data-urlencode "password=admin1234" --data-urlencode "csrf_token=$T" "$BASE/login"
T=$(curl -s -b "$JAR" -c "$JAR" "$BASE/dashboard" | csrf_of)

SALES_BEFORE=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; echo (int)Database::fetchValue("SELECT COUNT(*) FROM sales");')

# ---- 1. POS bulk checkout (product 6 = Logitech mouse, bulk, price 2200) ----
U1=$(curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/pos/checkout" -H "Content-Type: application/json" -H "X-CSRF-Token: $T" \
  -d '{"lines":[{"product_id":6,"quantity":2,"unit_ids":[]}],"discount":0,"payment_method":"cash","customer_name":"Flow Test"}')
echo "$U1" | grep -q '"ok":true' && ok "POS bulk checkout ok" || bad "POS bulk checkout failed: $U1"
S1=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; $s=Database::fetch("SELECT * FROM sales WHERE customer_name=\"Flow Test\" AND channel=\"pos\" ORDER BY id DESC LIMIT 1"); if(!$s) exit(1); $exp=round(2200*2*1.16,2); echo $s["id"]." ".$s["total"];' 2>/dev/null)
SID1=${S1%% *}; TOT1=${S1##* }
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit(abs((float)Database::fetchValue("SELECT total FROM sales WHERE id=?",[(int)$argv[1]]) - 5104.0) < 0.005 ? 0 : 1);' "$SID1" \
  && ok "POS bulk total = 5104 (2200x2 +16% VAT)" || bad "POS bulk total wrong: $TOT1"

# ---- 2. POS serialized checkout (product 1, unit 1) ----
U2=$(curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/pos/checkout" -H "Content-Type: application/json" -H "X-CSRF-Token: $T" \
  -d '{"lines":[{"product_id":1,"quantity":1,"unit_ids":[1]}],"discount":0,"payment_method":"mpesa","customer_name":"Flow Serial"}')
echo "$U2" | grep -q '"ok":true' && ok "POS serialized checkout ok" || bad "POS serialized checkout failed: $U2"
S2=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; $s=Database::fetch("SELECT * FROM sales WHERE customer_name=\"Flow Serial\" ORDER BY id DESC LIMIT 1"); echo $s["id"];')
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit((int)Database::fetchValue("SELECT COUNT(*) FROM inventory_units WHERE id=1 AND status=\"sold\"")===1?0:1);' \
  && ok "unit 1 marked sold" || bad "unit 1 not sold"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit(abs((float)Database::fetchValue("SELECT total FROM sales WHERE id=?",[(int)$argv[1]]) - round(65000*1.16,2)) < 0.005 ? 0 : 1);' "$S2" \
  && ok "POS serialized total = 75400 (65000 + 16%)" || bad "POS serialized total wrong"

# ---- 3. Shop guest checkout (product 7 = SSD, bulk, pickup, cash) ----
SHOP_T=$(curl -s -c /tmp/kc_shop.jar "$BASE/shop/cart" | grep -o "csrf: '[a-f0-9]\{20,\}'" | grep -o '[a-f0-9]\{20,\}')
U3=$(curl -s -b /tmp/kc_shop.jar -c /tmp/kc_shop.jar -X POST "$BASE/shop/checkout" -H "Content-Type: application/json" -H "X-CSRF-Token: $SHOP_T" \
  -d '{"lines":[{"product_id":7,"quantity":1}],"name":"Guest Buyer","phone":"0722000111","email":"guest@example.com","fulfillment":"pickup","payment_method":"cash"}')
echo "$U3" | grep -q '"ok":true' && ok "shop guest checkout ok" || bad "shop guest checkout failed: $U3"
S3=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; $s=Database::fetch("SELECT * FROM sales WHERE customer_name=\"Guest Buyer\" ORDER BY id DESC LIMIT 1"); echo $s["id"]." ".$s["channel"]." ".$s["status"];')
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; $s=Database::fetch("SELECT * FROM sales WHERE id=?",[(int)$argv[1]]); exit(($s["channel"]==="online" && $s["status"]==="completed")?0:1);' "${S3%% *}" \
  && ok "shop sale is online + completed" || bad "shop sale wrong: $S3"

# ---- 4. Offline sync (product 8 = router, bulk) ----
U4=$(curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/pos/sync" -H "Content-Type: application/json" -H "X-CSRF-Token: $T" \
  -d '{"items":[{"client_ref":"flow-off-1","device_id":"flow-dev-1","created_at":"2026-09-09 10:00:00","payload":{"lines":[{"product_id":8,"quantity":1}],"discount":0,"payment_method":"cash","customer_name":"Offline Flow"}}]}')
echo "$U4" | grep -q '"ok":true' && ok "offline sync ok" || bad "offline sync failed: $U4"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; $s=Database::fetch("SELECT * FROM sales WHERE device_id=\"flow-dev-1\" AND client_ref=\"flow-off-1\""); exit($s && (int)$s["offline_created"]===1 ? 0:1);' \
  && ok "offline sale stored with offline_created=1" || bad "offline sale missing"
# idempotency: sync again, must not duplicate
U5=$(curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/pos/sync" -H "Content-Type: application/json" -H "X-CSRF-Token: $T" \
  -d '{"items":[{"client_ref":"flow-off-1","device_id":"flow-dev-1","created_at":"2026-09-09 10:00:00","payload":{"lines":[{"product_id":8,"quantity":1}],"discount":0,"payment_method":"cash","customer_name":"Offline Flow"}}]}')
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit((int)Database::fetchValue("SELECT COUNT(*) FROM sales WHERE device_id=\"flow-dev-1\" AND client_ref=\"flow-off-1\"")===1?0:1);' \
  && ok "offline sync idempotent (1 row)" || bad "offline sync duplicated rows"
echo "$U5" | grep -q '"duplicate":true' && ok "sync returned duplicate:true" || bad "sync did not report duplicate"

# ---- cleanup: void + delete all flow sales, restore exact prior state ----
php -r '
require "/home/user/khamis-computers/app/bootstrap.php";
$refs = [];
foreach (["Flow Test","Flow Serial","Guest Buyer","Offline Flow"] as $n) {
  foreach (Database::fetchAll("SELECT * FROM sales WHERE customer_name = ?", [$n]) as $s) {
    // void first to restore serialized units, then delete movements + sale
    try { SaleService::void((int)$s["id"], null); } catch (Throwable $e) {}
    Database::delete("stock_movements", "reference = ?", [$s["sale_number"]]);
    Database::delete("sale_items", "sale_id = ?", [$s["id"]]);
    Database::delete("sales", "id = ?", [$s["id"]]);
  }
}
echo "flow sales cleaned\n";'

SALES_AFTER=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; echo (int)Database::fetchValue("SELECT COUNT(*) FROM sales");')
[ "$SALES_BEFORE" = "$SALES_AFTER" ] && ok "sale count restored ($SALES_BEFORE)" || bad "sale count changed: $SALES_BEFORE -> $SALES_AFTER"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit((int)Database::fetchValue("SELECT COUNT(*) FROM inventory_units WHERE id=1 AND status=\"in_stock\"")===1?0:1);' \
  && ok "unit 1 back in_stock after cleanup" || bad "unit 1 not restored"

echo ""
echo "RESULT: $PASS passed, $FAIL failed"
exit $((FAIL > 0 ? 1 : 0))
