#!/usr/bin/env bash
# Route smoke test: every GET route should return the expected status.
set -u
BASE="${KC_BASE:-http://localhost:8081}"
ROOT="${KC_ROOT:-$(pwd)}"
RETURN_MARKER="route-smoke-return-$$-$RANDOM"
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "  PASS  $1"; }
bad(){ FAIL=$((FAIL+1)); echo "  FAIL  $1 ($2)"; }
return_marker_count(){ php -r 'require $argv[1]."/app/bootstrap.php"; echo Database::fetchValue("SELECT COUNT(*) FROM returns WHERE reason = ?",[$argv[2]]);' "$ROOT" "$RETURN_MARKER"; }
cleanup_return_marker(){ php -r 'require $argv[1]."/app/bootstrap.php"; foreach(Database::fetchAll("SELECT id FROM returns WHERE reason = ?",[$argv[2]]) as $r){$id=(int)$r["id"];Database::delete("return_items","return_id = ?",[$id]);Database::delete("returns","id = ?",[$id]);Database::delete("activity_log","action = ? AND details LIKE ?",["return.created","%\"return_id\":".$id."%"]);}' "$ROOT" "$RETURN_MARKER" >/dev/null 2>&1; }
trap cleanup_return_marker EXIT

# $1=desc $2=jar $3=expected_status $4=path
check(){ local desc="$1" jar="$2" exp="$3" path="$4";
  local code; code=$(curl -s -b "$jar" -o /dev/null -w "%{http_code}" "$BASE$path")
  if [ "$code" = "$exp" ]; then ok "$desc [$code]"; else bad "$desc" "got $code want $exp"; fi
}

contains(){ local desc="$1" jar="$2" path="$3" needle="$4" body;
  body=$(curl -s -b "$jar" "$BASE$path")
  if grep -Fq -- "$needle" <<<"$body"; then ok "$desc"; else bad "$desc" "missing expected text"; fi
}

absent(){ local desc="$1" jar="$2" path="$3" needle="$4" body;
  body=$(curl -s -b "$jar" "$BASE$path")
  if grep -Fq -- "$needle" <<<"$body"; then bad "$desc" "found restricted text"; else ok "$desc"; fi
}

login(){ # $1=jar $2=email $3=pass -> echoes token
  local jar="$1" email="$2" pass="$3" T
  T=$(curl -s -c "$jar" "$BASE/login" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{20,\}')
  curl -s -b "$jar" -c "$jar" -o /dev/null --data-urlencode "email=$email" --data-urlencode "password=$pass" --data-urlencode "csrf_token=$T" "$BASE/login"
  echo "$T"
}

echo "=== Route smoke test ==="

# --- public routes (no session) ---
for p in "/login" "/shop" "/shop/products" "/shop/product/1" "/shop/cart" "/shop/track"; do
  check "public $p" /tmp/kc_none.jar 200 "$p"
done
check "public /install (already installed -> redirect)" /tmp/kc_none.jar 302 "/install"
check "public /uploads/p/nonexistent.png (404)" /tmp/kc_none.jar 404 "/uploads/p/nonexistent.png"
check "public /uploads/h/nonexistent.png (404)" /tmp/kc_none.jar 404 "/uploads/h/nonexistent.png"

# --- admin session ---
A=/tmp/kc_admin.jar; rm -f "$A"
login "$A" "admin@khamis.local" "admin1234"
for p in "/dashboard" "/products" "/products/new" "/products/1" "/products/1/edit" "/products/1/labels" "/products/check-unique?field=sku&value=KC-LAP-001" "/products/export" \
         "/categories" "/brands" "/suppliers" "/suppliers?status=active" "/grn" "/grn/new" "/grn/1" "/grn/export" \
         "/pos" "/pos/search?q=hp" "/pos/catalog" "/pos/receipt/1" \
         "/sales" "/sales/1" "/sales/1/pdf" "/sales/export" \
         "/returns" "/returns/new" "/returns/1" "/expenses" \
         "/reports" "/reports/export" "/reports/z" "/reports/vat" "/reports/vat/export" "/reports/purchases" "/reports/purchases/export" "/settings" "/settings?tab=online-shop" "/staff"; do
  check "admin $p" "$A" 200 "$p"
done
contains "admin hero manager renders" "$A" "/settings?tab=online-shop" "Hero Carousel"
contains "admin hero manager shows the add form" "$A" "/settings?tab=online-shop" "Add Slide"
CODE=$(curl -s -b "$A" -o /dev/null -w "%{http_code}" --data-urlencode "headline=no csrf" "$BASE/settings/hero")
if [ "$CODE" = "419" ]; then ok "hero create without CSRF token is rejected [419]"; else bad "hero create without CSRF" "got $CODE want 419"; fi
contains "admin shelf label shows VAT-inclusive customer price" "$A" "/products/1/labels" "KSh 75,400.00"
contains "admin dashboard shows finance KPI" "$A" "/dashboard" "Gross profit"
contains "admin reports explain net result" "$A" "/reports" "Net result"
contains "close-of-day shows reconciliation checklist" "$A" "/reports/z?user=1" "Closing checklist"
check "admin filtered return history" "$A" 200 "/returns?status=completed&q=R-"
RT=$(curl -s -b "$A" "$BASE/returns/new?sale=1" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{20,\}')
CODE=$(curl -s -b "$A" -o /dev/null -w "%{http_code}" --data-urlencode "csrf_token=$RT" "$BASE/returns")
if [ "$CODE" = "302" ]; then ok "admin cannot create a return without review token [302]"; else bad "admin cannot create a return without review token" "got $CODE want 302"; fi
CODE=$(curl -s -b "$A" -o /tmp/kc_return_review.html -w "%{http_code}" --data-urlencode "csrf_token=$RT" --data-urlencode "sale_id=1" --data-urlencode "item_id[]=1" --data-urlencode "qty[1]=1.5" --data-urlencode "amount[1]=10" --data-urlencode "reason=Smoke test" --data-urlencode "refund_method=cash" "$BASE/returns/review")
if [ "$CODE" = "422" ] && grep -Fq "must be a whole number" /tmp/kc_return_review.html; then ok "return review rejects a fractional quantity [422]"; else bad "return review rejects a fractional quantity" "unexpected response"; fi
CODE=$(curl -s -b "$A" -c "$A" -o /tmp/kc_return_review_valid.html -w "%{http_code}" --data-urlencode "csrf_token=$RT" --data-urlencode "sale_id=1" --data-urlencode "item_id[]=1" --data-urlencode "qty[1]=1" --data-urlencode "amount[1]=10" --data-urlencode "reason=$RETURN_MARKER" --data-urlencode "refund_method=cash" "$BASE/returns/review")
RETURN_TOKEN=$(grep -o 'name="review_token" value="[a-f0-9]*"' /tmp/kc_return_review_valid.html | head -1 | grep -o '[a-f0-9]\{48\}')
if [ "$CODE" = "200" ] && [ -n "$RETURN_TOKEN" ] && [ "$(return_marker_count)" = "0" ]; then ok "valid return review writes no return row [200]"; else bad "valid return review" "missing token or wrote data"; fi
CODE=$(curl -s -b "$A" -c "$A" -o /dev/null -w "%{http_code}" --data-urlencode "csrf_token=$RT" --data-urlencode "review_token=$RETURN_TOKEN" "$BASE/returns")
if [ "$CODE" = "302" ] && [ "$(return_marker_count)" = "1" ]; then ok "review token creates one pending return [302]"; else bad "review token creates pending return" "unexpected response or row count"; fi
CODE=$(curl -s -b "$A" -c "$A" -o /dev/null -w "%{http_code}" --data-urlencode "csrf_token=$RT" --data-urlencode "review_token=$RETURN_TOKEN" "$BASE/returns")
if [ "$CODE" = "302" ] && [ "$(return_marker_count)" = "1" ]; then ok "consumed return review token cannot be reused [302]"; else bad "consumed return review token" "token created a duplicate"; fi
cleanup_return_marker
CODE=$(curl -s -b "$A" -c "$A" -o /tmp/kc_return_review_expired.html -w "%{http_code}" --data-urlencode "csrf_token=$RT" --data-urlencode "sale_id=1" --data-urlencode "item_id[]=1" --data-urlencode "qty[1]=1" --data-urlencode "amount[1]=10" --data-urlencode "reason=$RETURN_MARKER" --data-urlencode "refund_method=cash" "$BASE/returns/review")
EXPIRED_TOKEN=$(grep -o 'name="review_token" value="[a-f0-9]*"' /tmp/kc_return_review_expired.html | head -1 | grep -o '[a-f0-9]\{48\}')
SESSION_ID=$(awk '$6 == "KC_SESSID" {print $7}' "$A" | tail -1)
php -r 'session_name("KC_SESSID"); session_id($argv[1]); session_start(); if(isset($_SESSION["return_review"])) $_SESSION["return_review"]["expires"]=time()-1; session_write_close();' "$SESSION_ID"
CODE=$(curl -s -b "$A" -c "$A" -o /dev/null -w "%{http_code}" --data-urlencode "csrf_token=$RT" --data-urlencode "review_token=$EXPIRED_TOKEN" "$BASE/returns")
if [ "$CODE" = "302" ] && [ "$(return_marker_count)" = "0" ]; then ok "expired return review token is rejected [302]"; else bad "expired return review token" "unexpected response or row created"; fi
T=$(curl -s -b "$A" "$BASE/grn/new" | grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{20,\}')
CODE=$(curl -s -b "$A" -o /dev/null -w "%{http_code}" --data-urlencode "csrf_token=$T" "$BASE/grn")
if [ "$CODE" = "302" ]; then ok "admin cannot post a GRN without a review token [302]"; else bad "admin cannot post a GRN without a review token" "got $CODE want 302"; fi
CODE=$(curl -s -b "$A" -o /tmp/kc_grn_review.html -w "%{http_code}" --data-urlencode "csrf_token=$T" --data-urlencode "supplier=Smoke Supplier" --data-urlencode "product_id[]=1" --data-urlencode "qty[]=2" --data-urlencode "cost[]=52000" --data-urlencode "serials[]=ONLY-ONE" "$BASE/grn/review")
if [ "$CODE" = "422" ] && grep -Fq "enter exactly 2 unique serial number(s)" /tmp/kc_grn_review.html; then ok "GRN review rejects a serial-count mismatch [422]"; else bad "GRN review rejects a serial-count mismatch" "unexpected response"; fi

# --- cashier session ---
C=/tmp/kc_cashier.jar; rm -f "$C"
login "$C" "cashier@khamis.local" "cashier1234"
for p in "/dashboard" "/products" "/products/1" "/pos" "/pos/search?q=hp" "/sales" "/returns" "/expenses" "/reports/z"; do
  check "cashier $p" "$C" 200 "$p"
done
absent "cashier quantity detail hides stock value" "$C" "/products/6#stock" "Stock value"
contains "cashier dashboard shows personal shift" "$C" "/dashboard" "My shift"
absent "cashier dashboard hides gross profit" "$C" "/dashboard" "Gross profit"
# inventory management + admin pages must redirect a cashier (302)
for p in "/reports" "/reports/export" "/reports/vat" "/reports/vat/export" "/reports/purchases" "/reports/purchases/export" "/settings" "/staff" "/categories" "/brands" "/suppliers" "/grn" "/grn/new" "/grn/export" "/products/new" "/products/1/edit" "/products/1/labels" "/products/export" "/sales/export"; do
  check "cashier blocked from $p" "$C" 302 "$p"
done

echo ""
echo "RESULT: $PASS passed, $FAIL failed"
exit $((FAIL > 0 ? 1 : 0))
