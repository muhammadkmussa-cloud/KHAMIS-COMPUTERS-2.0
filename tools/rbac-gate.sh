#!/usr/bin/env bash
# RBAC enforcement test: a cashier must be denied every admin-only screen and
# every admin-only action over HTTP, and a denied attempt must never change data.
#
# Covers (cashier, expecting 302 + zero data change):
#   GET  /reports, /reports/export, /reports/vat(+/export), /reports/purchases(+/export)
#   GET  /products/export, /sales/export            (bulk CSV exports, admin only)
#   POST /sales/{id}/void, /sales/{id}/mpesa-mark-paid|retry
#   POST /returns/{id}/approve|reject               (admin approval)
#   POST /expenses/{id}/delete
#
# Ordering matters: void/expense/M-PESA POST checks run BEFORE any fixture is
# created, and the void target is chosen to have no returns, so a pending return
# can never shield a void target via the SaleService "sale already has returns"
# business rule. A temporary pending return (created + cleaned via an EXIT trap)
# is used only for the approve/reject checks.
set -u
BASE="${KC_BASE:-http://localhost:8081}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
JAR="/tmp/kc_rbac.jar"; rm -f "$JAR"
RID_FILE="/tmp/kc_rbac_rid"; rm -f "$RID_FILE"
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "  PASS  $1"; }
bad(){ FAIL=$((FAIL+1)); echo "  FAIL  $1"; }
csrf_of(){ grep -o 'name="csrf_token" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{20,\}'; }

# If we die with a fixture parked in the DB, remove it (safe: pending returns
# never change stock; return_items are cascade-deleted).
cleanup(){
  if [ -f "$RID_FILE" ]; then
    local id; id=$(cat "$RID_FILE")
    if [ -n "$id" ] && [ "$id" != "0" ]; then
      php -r '
        require $argv[1] . "/app/bootstrap.php";
        Database::delete("return_items", "return_id = ?", [(int) $argv[2]]);
        Database::delete("returns", "id = ?", [(int) $argv[2]]);
      ' "$ROOT" "$id" 2>/dev/null
      echo "  (cleaned fixture return #$id)"
    fi
    rm -f "$RID_FILE"
  fi
}
trap cleanup EXIT

# login as a cashier; echo the session's csrf token (from an authed page)
login_cashier(){
  local T
  T=$(curl -s -c "$JAR" "$BASE/login" | csrf_of)
  curl -s -b "$JAR" -c "$JAR" -o /dev/null --data-urlencode "email=cashier@khamis.local" \
    --data-urlencode "password=cashier1234" --data-urlencode "csrf_token=$T" "$BASE/login"
  curl -s -b "$JAR" -c "$JAR" "$BASE/dashboard" | csrf_of
}

# fingerprint of all role-relevant state; compared before/after denied attempts
db_snap(){
  php -r '
    require $argv[1] . "/app/bootstrap.php";
    $p = [];
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM sales");
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM sales WHERE status = ?", ["cancelled"]);
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM sales WHERE status = ?", ["pending"]);
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM returns WHERE status = ?", ["pending"]);
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM returns WHERE status = ?", ["completed"]);
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM returns WHERE status = ?", ["rejected"]);
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM expenses");
    $p[] = Database::fetchValue("SELECT COALESCE(SUM(amount), 0) FROM expenses");
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM stock_movements");
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM inventory_units WHERE status = ?", ["sold"]);
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM mpesa_transactions");
    $p[] = Database::fetchValue("SELECT COUNT(*) FROM return_items");
    $p[] = Database::fetchValue("SELECT COALESCE(SUM(quantity), 0) FROM return_items");
    foreach (Database::fetchAll("SELECT id, status FROM returns ORDER BY id") as $r) { $p[] = $r["id"].":".$r["status"]; }
    foreach (Database::fetchAll("SELECT id, status FROM sales ORDER BY id") as $s) { $p[] = $s["id"].":".$s["status"]; }
    echo md5(implode("|", $p));
  ' "$ROOT"
}

# read a single scalar from the DB (integer/string values only via $1)
dbval(){
  php -r 'require $argv[1] . "/app/bootstrap.php"; echo (string) Database::fetchValue($argv[2]);' "$ROOT" "$1"
}

# create a temp pending return (model-level fixture); echoes its id or 0
make_pending_return(){
  php -r '
    require $argv[1] . "/app/bootstrap.php";
    $adminId = (int) Database::fetchValue("SELECT id FROM users WHERE role = ? AND is_active = 1 LIMIT 1", ["admin"]);
    if (!$adminId) { echo "0"; exit(0); }
    $sales = Database::fetchAll("SELECT id FROM sales WHERE status = ? AND channel = ? ORDER BY id LIMIT 10", ["completed", "pos"]);
    foreach ($sales as $s) {
      foreach (SalesReturn::availableItems((int) $s["id"]) as $r) {
        if ((int) $r["returnable"] <= 0) { continue; }
        try {
          echo SalesReturn::create((int) $s["id"], [[
            "sale_item_id"  => (int) $r["sale_item_id"],
            "quantity"      => 1,
            "refund_amount" => round((float) $r["unit_price"], 2),
          ]], "rbac gate fixture", $adminId);
          exit(0);
        } catch (Throwable $e) { continue; }
      }
    }
    echo "0";
  ' "$ROOT"
}

# cashier GET must be redirected (302)
gcheck(){ local desc="$1" path="$2" code
  code=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" "$BASE$path")
  [ "$code" = "302" ] && ok "$desc [$code]" || bad "$desc got $code"
}

# cashier POST must be redirected (302) — data-change is checked via db_snap
pcheck(){ local desc="$1" url="$2" code
  code=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" -X POST \
    --data-urlencode "csrf_token=$T" "$BASE$url")
  [ "$code" = "302" ] && ok "$desc [$code]" || bad "$desc got $code"
}

echo "=== RBAC gate test (cashier denied) ==="

T=$(login_cashier)
[ -n "$T" ] && ok "logged in as cashier" || { bad "cashier login failed"; exit 1; }

# --- cashier GET must be blocked (reports / VAT / purchases / exports) ---
for p in "/reports" "/reports/export" "/reports/vat" "/reports/vat/export" \
         "/reports/purchases" "/reports/purchases/export" \
         "/products/export" "/sales/export"; do
  gcheck "cashier blocked GET $p" "$p"
done

# --- pick fixture targets ---
# void target: completed POS sale with no returns at all, so the SaleService
# "sale already has returns" rule can never shield an RBAC regression.
COMPLETED=$(dbval 'SELECT s.id FROM sales s WHERE s.status = "completed" AND s.channel = "pos" AND NOT EXISTS (SELECT 1 FROM returns r WHERE r.sale_id = s.id) ORDER BY s.id LIMIT 1')
EXPENSE=$(dbval 'SELECT id FROM expenses ORDER BY id LIMIT 1')
PENDING_SALE=$(dbval 'SELECT id FROM sales WHERE status = "pending" ORDER BY id LIMIT 1')

BEFORE=$(db_snap)

# --- group A: void / M-PESA / expense POSTs run with NO fixture parked ---
if [ -n "$COMPLETED" ] && [ "$COMPLETED" != "0" ]; then
  pcheck "cashier denied void sale #$COMPLETED" "/sales/$COMPLETED/void"
else
  echo "  SKIP  void (no completed POS sale without returns to target)"
fi
if [ -n "$PENDING_SALE" ] && [ "$PENDING_SALE" != "0" ]; then
  pcheck "cashier denied mpesa-mark-paid #$PENDING_SALE" "/sales/$PENDING_SALE/mpesa-mark-paid"
  pcheck "cashier denied mpesa-retry #$PENDING_SALE" "/sales/$PENDING_SALE/mpesa-retry"
else
  echo "  SKIP  M-PESA fallbacks (no pending sale in seed data)"
fi
if [ -n "$EXPENSE" ] && [ "$EXPENSE" != "0" ]; then
  pcheck "cashier denied expense delete #$EXPENSE" "/expenses/$EXPENSE/delete"
else
  echo "  SKIP  expense delete (no expense in seed data)"
fi

# --- group B: returns approve/reject (temp pending-return fixture) ---
RID=$(make_pending_return)
if [ -n "$RID" ] && [ "$RID" != "0" ]; then
  echo "$RID" > "$RID_FILE"
  pcheck "cashier denied approve return #$RID" "/returns/$RID/approve"
  pcheck "cashier denied reject return #$RID" "/returns/$RID/reject"
  # The status re-check is the primary detector for a reject regression
  # (a rejected row would be deleted by drop_pending_return before AFTER runs).
  ST=$(dbval "SELECT status FROM returns WHERE id = $RID")
  [ "$ST" = "pending" ] && ok "return #$RID still pending after cashier attempts" || bad "return #$RID status changed to $ST"
  cleanup   # drops the fixture; trap stays armed for an early exit
else
  echo "  SKIP  returns approve/reject (no returnable completed sale to fixture)"
fi

# --- no denied attempt may have changed any data ---
AFTER=$(db_snap)
[ "$BEFORE" = "$AFTER" ] && ok "no data changed by denied cashier attempts" || bad "DATA CHANGED by cashier attempts"

echo ""
echo "RESULT: $PASS passed, $FAIL failed"
exit $((FAIL > 0 ? 1 : 0))
