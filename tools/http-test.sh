#!/usr/bin/env bash
# HTTP-level integration tests for the fixes (controller guards).
set -u
BASE="http://localhost:8081"
JAR="/tmp/kc_http_test.jar"
rm -f "$JAR" /tmp/kc_body.html
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "  PASS  $1"; }
bad(){ FAIL=$((FAIL+1)); echo "  FAIL  $1"; }
csrf_of(){ grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{20,\}'; }
expect(){ local desc="$1"; local needle="$2"; if grep -q "$needle" /tmp/kc_body.html; then ok "$desc"; else bad "missing: $desc"; fi; }

echo "=== HTTP integration tests (admin) ==="

# 1. Login
T=$(curl -s -c "$JAR" "$BASE/login" | csrf_of)
[ -n "$T" ] || { bad "no CSRF on login page"; exit 1; }
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "email=admin@khamis.local" \
  --data-urlencode "password=admin1234" --data-urlencode "csrf_token=$T" "$BASE/login"
T=$(curl -s -b "$JAR" -c "$JAR" "$BASE/dashboard" | csrf_of)
[ -n "$T" ] && ok "logged in as admin" || bad "login failed"

# 2. Secrets not leaked into HTML (Fix 7)
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; Setting::set("smtp_password","TOPSECRET_SMTP"); Setting::set("mpesa_consumer_secret","TOPSECRET_MPESA");'
H=$(curl -s -b "$JAR" "$BASE/settings")
echo "$H" | grep -q 'TOPSECRET_SMTP' && bad "SMTP password leaked into HTML" || ok "SMTP password not in HTML"
echo "$H" | grep -q 'TOPSECRET_MPESA' && bad "M-PESA secret leaked into HTML" || ok "M-PESA secret not in HTML"
echo "$H" | grep -q 'name="smtp_password" value=""' && ok "smtp_password input value blanked" || bad "smtp_password input has value"
echo "$H" | grep -q 'leave blank to keep' && ok "leave-blank-to-keep placeholder shown" || bad "placeholder missing"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; Setting::set("smtp_password",""); Setting::set("mpesa_consumer_secret","");'

# 3. setUnitStatus on a sold unit (16) must be blocked
curl -s -b "$JAR" -c "$JAR" -L --data-urlencode "csrf_token=$T" --data-urlencode "status=in_stock" --data-urlencode "note=x" \
  "$BASE/products/9/units/16/status" -o /tmp/kc_body.html
expect "sold-unit status change blocked" "process a return or void"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit((int)Database::fetchValue("SELECT COUNT(*) FROM inventory_units WHERE id=16 AND status=\"sold\"")===1?0:1);' \
  && ok "unit 16 still sold" || bad "unit 16 no longer sold"

# 4. deleteUnit on a sold unit must be blocked
curl -s -b "$JAR" -c "$JAR" -L --data-urlencode "csrf_token=$T" "$BASE/products/9/units/16/delete" -o /tmp/kc_body.html
expect "deleteUnit on sold unit blocked" "sales or returns history"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit((int)Database::fetchValue("SELECT COUNT(*) FROM inventory_units WHERE id=16")===1?0:1);' \
  && ok "unit 16 still exists" || bad "unit 16 was deleted"

# 5. Manually marking an in_stock unit (19) as sold must be blocked
curl -s -b "$JAR" -c "$JAR" -L --data-urlencode "csrf_token=$T" --data-urlencode "status=sold" \
  "$BASE/products/9/units/19/status" -o /tmp/kc_body.html
expect "manual 'sold' status blocked" "not allowed"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit((int)Database::fetchValue("SELECT COUNT(*) FROM inventory_units WHERE id=19 AND status=\"in_stock\"")===1?0:1);' \
  && ok "unit 19 still in_stock" || bad "unit 19 changed"

# 6. addUnits with a duplicate serial writes a movement for inserted count only
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "csrf_token=$T" --data-urlencode "name=ZZ HTTP Test" \
  --data-urlencode "sku=ZZ-HTTP-001" --data-urlencode "cost_price=100" --data-urlencode "sell_price=200" \
  --data-urlencode "is_serialized=1" --data-urlencode "is_active=1" "$BASE/products"
NEWID=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; echo (int)Database::fetchValue("SELECT id FROM products WHERE sku=\"ZZ-HTTP-001\"");')
[ "$NEWID" != "0" ] && ok "scratch serialized product created (#$NEWID)" || bad "could not create scratch product"
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "csrf_token=$T" \
  --data-urlencode $'serials=ZZ-HTTP-SN-1\nM3-SN-001' "$BASE/products/$NEWID/units"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; $id=(int)$argv[1]; $u=(int)Database::fetchValue("SELECT COUNT(*) FROM inventory_units WHERE product_id=?",[$id]); $m=(int)Database::fetchValue("SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE product_id=? AND reference=\"MANUAL\"",[$id]); exit(($u===1 && $m===1)?0:1);' "$NEWID" \
  && ok "addUnits: 1 unit inserted, movement qty=1" || bad "addUnits unit/movement mismatch"

# 7. Deleting a product with stock history must be blocked
curl -s -b "$JAR" -c "$JAR" -L --data-urlencode "csrf_token=$T" "$BASE/products/$NEWID/delete" -o /tmp/kc_body.html
expect "product delete blocked (has stock history)" "stock history"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit((int)Database::fetchValue("SELECT COUNT(*) FROM products WHERE id=?",[(int)$argv[1]])===1?0:1);' "$NEWID" \
  && ok "scratch product still exists" || bad "scratch product was deleted"

# 8. Brand rename to a duplicate must be blocked
BID=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; echo (int)Database::fetchValue("SELECT id FROM brands WHERE name=\"Apple\"");')
if [ "$BID" != "0" ]; then
  curl -s -b "$JAR" -c "$JAR" -L --data-urlencode "csrf_token=$T" --data-urlencode "name=Samsung" "$BASE/brands/$BID" -o /tmp/kc_body.html
  expect "duplicate brand rename blocked" "already exists"
else
  bad "could not find Apple brand"
fi

# 9. Category delete nulls product references
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "csrf_token=$T" --data-urlencode "name=ZZ HTTP Cat" --data-urlencode "description=x" "$BASE/categories"
CID=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; echo (int)Database::fetchValue("SELECT id FROM categories WHERE name=\"ZZ HTTP Cat\"");')
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "csrf_token=$T" --data-urlencode "name=ZZ HTTP Cat Product" \
  --data-urlencode "sku=ZZ-HTTP-CAT-001" --data-urlencode "cost_price=50" --data-urlencode "sell_price=100" --data-urlencode "category_id=$CID" "$BASE/products"
PID=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; echo (int)Database::fetchValue("SELECT id FROM products WHERE sku=\"ZZ-HTTP-CAT-001\"");')
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "csrf_token=$T" "$BASE/categories/$CID/delete"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; $c=Database::fetchValue("SELECT category_id FROM products WHERE id=?",[(int)$argv[1]]); exit(($c===null||(int)$c===0)?0:1);' "$PID" \
  && ok "category delete nulled product.category_id" || bad "category_id not nulled"
curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "csrf_token=$T" "$BASE/products/$PID/delete"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit((int)Database::fetchValue("SELECT COUNT(*) FROM products WHERE id=?",[(int)$argv[1]])===0?0:1);' "$PID" \
  && ok "uncategorised scratch product deleted" || bad "scratch product not deleted"

# 10. Expense with invalid date rejected
N=$(php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; echo (int)Database::fetchValue("SELECT COUNT(*) FROM expenses");')
curl -s -b "$JAR" -c "$JAR" -L --data-urlencode "csrf_token=$T" --data-urlencode "description=zz bad date" \
  --data-urlencode "amount=10" --data-urlencode "expense_date=not-a-date" "$BASE/expenses" -o /tmp/kc_body.html
expect "invalid expense date rejected" "valid date"
php -r 'require "/home/user/khamis-computers/app/bootstrap.php"; exit((int)Database::fetchValue("SELECT COUNT(*) FROM expenses")===(int)$argv[1]?0:1);' "$N" \
  && ok "expense count unchanged" || bad "expense row created"

# cleanup scratch serialized product
php -r '
require "/home/user/khamis-computers/app/bootstrap.php";
$pid = (int) Database::fetchValue("SELECT id FROM products WHERE sku=\"ZZ-HTTP-001\"");
if ($pid) { Database::delete("stock_movements","product_id=?",[$pid]); Database::delete("inventory_units","product_id=?",[$pid]); Database::delete("products","id=?",[$pid]); }
echo "scratch cleaned\n";'

echo ""
echo "RESULT: $PASS passed, $FAIL failed"
exit $((FAIL > 0 ? 1 : 0))
