#!/usr/bin/env bash
# Route smoke test: every GET route should return the expected status.
set -u
BASE="http://localhost:8081"
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "  PASS  $1"; }
bad(){ FAIL=$((FAIL+1)); echo "  FAIL  $1 ($2)"; }

# $1=desc $2=jar $3=expected_status $4=path
check(){ local desc="$1" jar="$2" exp="$3" path="$4";
  local code; code=$(curl -s -b "$jar" -o /dev/null -w "%{http_code}" "$BASE$path")
  if [ "$code" = "$exp" ]; then ok "$desc [$code]"; else bad "$desc" "got $code want $exp"; fi
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

# --- admin session ---
A=/tmp/kc_admin.jar; rm -f "$A"
login "$A" "admin@khamis.local" "admin1234"
for p in "/dashboard" "/products" "/products/new" "/products/1" "/products/1/edit" "/products/export" \
         "/categories" "/brands" "/suppliers" "/grn" "/grn/new" "/grn/1" \
         "/pos" "/pos/search?q=hp" "/pos/catalog" "/pos/receipt/1" \
         "/sales" "/sales/1" "/sales/1/pdf" "/sales/export" \
         "/returns" "/returns/new" "/returns/1" "/expenses" \
         "/reports" "/reports/export" "/reports/z" "/reports/vat" "/reports/purchases" "/settings" "/staff"; do
  check "admin $p" "$A" 200 "$p"
done

# --- cashier session ---
C=/tmp/kc_cashier.jar; rm -f "$C"
login "$C" "cashier@khamis.local" "cashier1234"
for p in "/dashboard" "/products" "/products/1" "/pos" "/pos/search?q=hp" "/sales" "/returns" "/expenses" "/reports/z"; do
  check "cashier $p" "$C" 200 "$p"
done
# inventory management + admin pages must redirect a cashier (302)
for p in "/reports" "/reports/vat" "/reports/purchases" "/settings" "/staff" "/categories" "/brands" "/suppliers" "/grn" "/grn/new" "/products/new" "/products/1/edit" "/products/1/labels"; do
  check "cashier blocked from $p" "$C" 302 "$p"
done

echo ""
echo "RESULT: $PASS passed, $FAIL failed"
exit $((FAIL > 0 ? 1 : 0))
