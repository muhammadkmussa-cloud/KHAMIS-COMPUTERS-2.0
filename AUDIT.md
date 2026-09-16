# Khamis Computers — Code Audit & Polish Survey

**Scope:** full codebase review for placeholders, unimplemented features, security
weaknesses, data-integrity bugs, and UX polish. Issues are ordered by severity, each
with file references.

**Status legend:** ✅ = fixed · 🔜 = planned (Phase 3) · ⏸️ = deferred

- **Phase 0 + 1 (security):** A1, B1–B7 ✅
- **Phase 2 (data integrity):** C1–C9 ✅
- **Phase 3 (features):** D4 (sale void), D1 (discount PIN gate), D2 (warranty fields), D9 (CSV export) ✅
- **Deferred:** D5 (email), D6 (M-PESA), D7 (suppliers), D8 (images), D11 (delivery fees)

---

## A. CRITICAL — fix before going live

### A1 ✅. Installer can mint a new admin *after* installation
`SetupController::run()` (the POST `/install` handler) has **no `isInstalled()` guard**.
`form()` (GET) redirects once installed, but `run()` does not. A POST to `/install`
re-runs `Schema::apply()` (all `CREATE TABLE IF NOT EXISTS`, so it never fails) and then
**inserts a brand-new admin account with attacker-chosen email/password**.

CSRF does not protect against this: tokens are *session-scoped* (`Csrf::token()` is the
same value for every form in a session), and an unauthenticated visitor can obtain a
valid token + session simply by loading the public `/login` page.

**Impact:** total account takeover on any deployed instance that still has the route.

- `app/controllers/SetupController.php` — `run()`
- `public_html/index.php` — `install` route
- `app/core/Csrf.php` — session-scoped token

**Fix direction:** make `run()` refuse when `Schema::isInstalled()` is true, and/or
delete the route + controller + `tools/install.php` in production (already on the
Settings checklist, but the missing POST guard makes it urgent).

---

## B. HIGH — security hardening

| # | Issue | Where |
|---|---|---|
| B1 ✅ | **No login rate limiting / lockout** — brute-force is trivial. | `app/core/Auth.php::attempt()` |
| B2 ✅ | **Logout is a GET** — CSRF-forced logout; should be POST + token. | `public_html/index.php` (`logout` route), `AuthController::logout()` |
| B3 ✅ | **Demo credentials shown on the login page** — leaks `admin@khamis.local/admin1234` on production. | `app/views/auth/login.php` (`auth-hint`) |
| B4 ✅ | **Missing security headers** — no `Content-Security-Policy`, `Permissions-Policy`, or HSTS; no `Options -Indexes`. | `public_html/.htaccess` |
| B5 ✅ | **Session hardening gaps** — no `session.use_strict_mode`, no idle-timeout/UA/IP revalidation beyond cookie lifetime. | `app/bootstrap.php` |
| B6 ✅ | **No audit trail** for settings/staff changes (who changed what, when). | `SettingsController`, `StaffController` |
| B7 ✅ | **Error log destination** `storage/logs` may be unwritable on shared hosting → PHP errors silently vanish in production. | `app/bootstrap.php` |

---

## C. MEDIUM — correctness / data-integrity bugs

| # | Issue | Where |
|---|---|---|
| C1 ✅ | **Offline-sync dedup has no DB-level guarantee.** Dedup is an app-level `SELECT` on `device_id + client_ref`; two concurrent sync requests can both pass the check and double-insert. Add a `UNIQUE (device_id, client_ref)` index. | `app/models/SaleService.php`, `schema/schema.sql` |
| C2 ✅ | **Order numbers use `MAX(id)+1` / `COUNT(*)+1`** — race conditions with concurrent cashiers/web orders (two sales can compute the same `S-...` number). | `SaleService::nextNumber()`, `GoodsReceived::nextNumber()`, `SalesReturn::nextNumber()` |
| C3 ✅ | **Return form array misalignment.** `qty[]`/`amount[]` inputs are submitted for *every* row, but `item_id[]` only for checked rows. If a middle row is left unchecked, quantity/refund map to the wrong item. | `app/views/returns/form.php`, `ReturnController::store()` |
| C4 ✅ | **Return refund has no upper cap** — a cashier can enter a refund larger than the line total (only negatives are rejected). | `ReturnController::store()`, `SalesReturn::create()` |
| C5 ✅ | **Dead sale statuses.** `sales.status` values `pending`, `cancelled`, `offline` are never set by any code path (every sale is `completed`; offline is tracked by the separate `offline_created` flag). The Sales filter offers these options and always returns 0 rows; the "Offline" filter should map to `offline_created = 1`. | `Sale::search()`, `app/views/sales/index.php`, `SaleService::create()` |
| C6 ✅ | **Toggling `is_serialized` on an existing product** is allowed but doesn't migrate stock between `inventory_units` and `stock_movements` — can desync counts. | `ProductController::update()` |
| C7 ✅ | **Deleting a product with GRN history** (no sales) cascades `goods_received_items`, leaving GRN totals inconsistent. | `ProductController::delete()`, `schema/schema.sql` |
| C8 ✅ | **Service Worker paths are hardcoded to `/`** — the offline app-shell breaks when deployed in a cPanel subfolder (paths like `/assets/...` and `/pos` are wrong). Also `VERSION` must be bumped manually on updates. | `public_html/sw.js` |
| C9 ✅ | **POS catalogue caps serial units at 200 per product** — offline serial selection misses units beyond that. | `PosController::catalog()` |
| C10 | **Dead setting** `shop_currency` is seeded but never read (currency comes only from `config.php`). | `Schema::seedBaseline()`, `Setting` |

---

## D. PLACEHOLDERS & UNIMPLEMENTED FEATURES

| # | Feature | Status / evidence |
|---|---|---|
| D1 ✅ | **Discount OTP gate** | Explicit placeholder: `config.php` keeps `security.otp_ttl_seconds` commented *"used by the discount OTP gate in a later piece"* — nothing implements it. Large discounts are approved with no owner PIN (a TechBill feature). |
| D2 ✅ | **Warranty fields** | Serial/IMEI tracking exists, but there is no warranty period/expiry/claim tracking — only receipt footer text ("warranty claims require this receipt"). |
| D3 ✅ | **Cash reconciliation / Z-report / close-of-day** | `z_reports` table + `ZReport` model + `/reports/z` page. Live per-date/per-cashier summary (sales, cash/M-PESA/card/bank, discounts, voids, refunds, expected cash) with a "Close day" snapshot (upserted per cashier per date). Cashiers see and close only their own day; admins can view any staff or all. |
| D4 ✅ | **Sale void / cancel** | `status` enum supports `cancelled` but there is no void workflow — mistakes can only be unwound via returns. |
| D5 ✅ | **Email notifications** | `Mailer` service (PHP `mail()` + dependency-free SMTP with STARTTLS/AUTH LOGIN). Online checkout sends an HTML **order-confirmation** email; stock decreases below `low_stock_threshold` send a **low-stock alert** (at most once per product per 24h, recorded in `low_stock_alerts`). Config in Settings → Email notifications. |
| D6 ✅ | **M-PESA STK push** | `MpesaService` (Daraja STK push + OAuth token cache + callback). Online "Pay now with M-PESA" creates the sale as `pending`, fires the STK prompt, and the public `/mpesa/callback` route flips it to `completed` with the receipt number. Admin fallbacks: **Mark paid** and **Re-send prompt**. Config in Settings → M-PESA. |
| D7 ✅ | **Supplier management** | `suppliers` table + CRUD (`/suppliers`). GRN supplier select resolves a saved supplier (name snapshotted onto the GRN); delete deactivates when GRN history exists, otherwise hard-deletes. |
| D8 ✅ | **Product images** | `product_images` gallery + `products.image` primary filename. Upload/make-primary/remove on the product page; served publicly at `uploads/p/{filename}` (path-traversal-safe streamer); shop shows the gallery with a thumbnail picker; `product_thumb()` renders the uploaded image everywhere. |
| D9 ✅ | **CSV export** | No export for sales, reports, products, or expenses. |
| D10 ✅ | **Customer order lookup** | Public `/shop/track` page — a customer enters their order number + phone (matched on the last 9 digits) to re-fetch their order summary. Online orders only; session rate-limited (10 tries / 10 min) to slow enumeration. |
| D11 ✅ | **Delivery/shipping fee** | `delivery_zones` table + admin CRUD (Settings → Delivery zones). Shop checkout shows a zone picker, the fee is resolved **server-side** (client can't set its own price) and snapshotted (name + fee) onto the sale; shown on order confirmation, sales detail, receipts and the confirmation email. |
| D12 | **Dead files** | `app/views/partials/sidebar.php` **deleted** (0 references). Remaining: `app/views/coming-soon.php` unreferenced; `app.js` header still says "added in later pieces". |

---

## E. POLISH & UX

| # | Issue | Where |
|---|---|---|
| E1 | **POS does not reset customer name/phone, discount, payment after a sale** (offline path clears only the cart) — next sale inherits stale values. | `app/views/pos/index.php` (`offlineCheckout`) |
| E2 | **Cashier can't look up a *sold* serial** — search only matches `in_stock` units, so warranty/status checks by serial are impossible. | `PosController::search()` |
| E3 | **Shop: serialized quantity can exceed stock** in the cart (no cap applied for serialized lines; server rejects at checkout with a generic message). | `public_html/assets/js/shop.js` |
| E4 | **No print button on the customer order confirmation** (and no customer-facing PDF, though admin/POS have one). | `app/views/shop/order.php` |
| E5 ✅ | **Cashier sees admin-only buttons** (Return *Approve/Reject*, Expense *Delete*) that then error "Only admins…" — views should hide them by role, like the nav already does. | Reject/Approve and Expense Delete are now role-hidden in `returns/show.php` + `expenses/index.php`; server-side admin guards unchanged. |
| E6 | **Broken link** on Settings → "Full guide: docs/cpanel-deployment.md" (docs/ is outside the web root → 404). | `app/views/settings/index.php` |
| E7 ✅ | **No timezone config** — all timestamps use the server's PHP timezone (likely not `Africa/Nairobi`), so "today" stats and receipt times can be wrong. | `app.timezone` config + `date_default_timezone_set()` in bootstrap; defaults to `Africa/Nairobi`, overridable via `APP_TIMEZONE`. |
| E8 | **Sales list has no totals row** for the filtered set; Returns/Expenses lists have **no pagination**. | `sales/index.php`, `returns/index.php` |
| E9 | **Accessibility** — modals lack `role="dialog"`/`aria-modal`/focus trap; nav "icons" are raw glyphs; some inputs lack labels/autocomplete. | POS modal, layouts |
| E10 | **i18n** — every string is hardcoded English; no language layer. | throughout |

---

## F. DEPLOYMENT HARDENING (partly documented, not enforced)

| # | Issue | Where |
|---|---|---|
| F1 ✅ | If everything is uploaded *inside* `public_html/` (common cPanel habit), `app/`, `schema/`, `storage/`, `tools/`, and `.env` become web-accessible — the shipped `.htaccess` only denies `storage/`. | Added `Require all denied` `.htaccess` to `app/`, `schema/`, `tools/` (alongside the existing `storage/` one) plus a dotfile-deny (`<FilesMatch "^\.">`) in `public_html/.htaccess` to hide `.env`. |
| F2 ✅ | `.htaccess` sets `display_errors off` only under `mod_php` — ignored on PHP-FPM hosts. No `RewriteBase` (subfolder flakiness), no `Options -Indexes`. | `Options -Indexes` + `ServerSignature Off` present; relative rewrite works in subfolders without `RewriteBase`; PHP-FPM error display is controlled by `APP_DEBUG`/`display_errors` in bootstrap (already correct). |
| F3 ✅ | Dev artifacts ship in the repo: `tools/install.php`, `public_html/router-dev.php`, installer route. | `router-dev.php` now refuses to serve when `APP_ENV != development`; `tools/` is web-denied; the `/install` route already refuses once installed (A1). |
| F4 | No backup/migration tooling in-app (relies on cPanel Backups). | — |

---

## What is already solid ✅
- **Prepared statements everywhere** (PDO) — no SQL injection found.
- **Output escaping** on all server-rendered data (`e()`, and `esc()` in JS-built HTML).
- **CSRF on every state-changing route** except `logout` (GET) — incl. the JSON POS/shop/sync endpoints.
- **bcrypt** passwords with cost-12 + automatic rehash on login; `session_regenerate_id(true)` on login.
- **Transactions** around sales, returns, and GRNs, with server-side re-validation of stock/prices.
- **Idempotent sync design** (dedup by `device_id`+`client_ref`), preserved offline timestamps, serial-by-number matching at sync.
- **Role gates** on all admin routes (router-level) and self-protection rules in staff management.
- Schema portability (MySQL/SQLite) and a lightweight column-migration path for upgrades.

---

*Generated by a code survey — nothing in this document has been implemented.*

---

# Integration Audit Round (2026-09-09) — feature-by-feature, end-to-end

A full re-read of every controller, model, service, core class, view and JS asset,
followed by a new automated verification harness (`tools/stock-check.php`,
`tools/integration-test.php`, `tools/http-test.sh`, `tools/route-smoke.sh`,
`tools/flow-test.sh`). Every bug found was fixed and re-verified; no fix was applied
on assumption. All tests were run against the live dev database and the data was
returned to its exact prior state afterwards.

## Bugs found & fixed this round

| # | Bug | Fix |
|---|-----|-----|
| I1 | **Web installer skipped delivery zones.** `SetupController::run()` never called `Schema::seedDeliveryZones()` (only the CLI installer did), so a fresh install via `/install` had zero delivery zones and delivery checkout could not work. | Added `Schema::seedDeliveryZones()` to the installer sequence. Verified: fresh-DB run of the exact installer sequence yields the 4 seeded zones. |
| I2 | **`addUnits()` wrote a `received` movement for the requested count, not the inserted count.** Adding serials where some already exist desynced the serialized-product journal from the real unit count. | Movement quantity now equals the number actually inserted; a fully-duplicate batch errors cleanly instead of writing a phantom movement. Verified via HTTP (1 new + 1 duplicate → 1 unit, movement qty 1). |
| I3 | **`setUnitStatus()` allowed double-selling.** An admin could flip a sold unit back to `in_stock` (it would then be re-sold) or mark an in-stock unit `sold` by hand (stock vanishes with no sale record). | A unit sold on a live order can no longer be status-changed by hand (must use Returns/Void); a unit can no longer be hand-marked `sold`. Warranty/note edits on sold units still work. Verified via HTTP. |
| I4 | **`deleteUnit()` had no history guard.** Deleting a unit referenced by a sale or return would orphan receipts/return trails. | Blocked when the unit appears in `sale_items` or `return_items`. Verified via HTTP. |
| I5 | **Product delete orphaned child rows on MySQL.** MySQL ignores the inline `REFERENCES … ON DELETE` clauses in `schema.sql`, so deleting a product could orphan `inventory_units`, `stock_movements`, `product_images`, `low_stock_alerts`. | Deletion now (a) refuses any product with stock history (units/movements), and (b) explicitly deletes `product_images` and `low_stock_alerts` rows. Verified: block + clean delete of a history-free product. |
| I6 | **Category/brand delete left dangling product ids on MySQL** (same ignored-FK cause; UI even promised "products become uncategorised"). | Both delete handlers now `SET NULL` the referencing `products.category_id`/`brand_id` before deleting. Verified via HTTP. |
| I7 | **Returns could be created against non-completed sales.** Returning a *voided* sale would re-restock stock that the void already restored (double stock), and returning a *pending* M-PESA order made no sense. | `SalesReturn::commit()` now requires `status = 'completed'`; the return picker only lists completed sales. Verified at model level (cancelled + voided sales rejected). |
| I8 | **Ghost returns.** If every requested line clamped to zero returnable quantity (already fully returned), a return with no items and a zero refund was still recorded. | Throws "Nothing left to return" and rolls back. Verified at model level. |
| I9 | **Settings page leaked secrets in HTML.** The stored SMTP password and M-PESA consumer secret were echoed into `value="…"`. | Both inputs are blanked and show "leave blank to keep". Verified: setting real secrets and loading the page no longer leaks them. |
| I10 | **Low-stock dedup suppressed retries.** A failed email still recorded the 24h dedup row, so a transient SMTP failure silenced alerts for a full day. | The dedup row is only written on a successful send. |
| I11 | **Brand rename to a duplicate crashed (500).** `brands.name` is UNIQUE and `BrandController::update()` didn't check, so a duplicate rename threw a raw PDO exception. | Duplicate rename is caught with a friendly error. Verified via HTTP. |
| I12 | **Expenses accepted malformed dates**, which would crash the DATE column on MySQL strict mode. | Date format is validated before insert. Verified via HTTP. |
| I13 | **Dashboard showed the static VAT default**, not the live editable rate. | Uses `vat_rate()`. |
| I14 | **Order-confirmation first-name fallback never worked** (`explode()[0] ?? 'friend'` yields an empty string, not "friend") and broke on multi-space names. | Extracted a proper first name. |

## Design decisions (documented, intentionally unchanged)

- **Refunds are capped at the item's pre-VAT price** (`unit_price × qty`). The customer
  paid VAT-inclusive, so a full refund may be less than the gross paid. Changing this
  would ripple into the Z-report `expected_cash` math; flagged for the shop owner to
  decide, not silently altered.
- **Z-report refunds are dated by the return's creation date**, not its approval date.

## Verification harness (kept in `tools/`)

- `tools/stock-check.php` — cross-table integrity checker (money math, sold/voided/
  returned movements, unit status vs sale state, negative stock, orphans, GRN totals).
- `tools/integration-test.php` — model-level invariants (return-on-cancelled, ghost
  return, refund cap, sell→void→restock).
- `tools/http-test.sh` — controller guards over HTTP (unit status/delete, product
  delete, brand dup, category nulling, expense date, secret leakage).
- `tools/route-smoke.sh` — every GET route's status code as admin, cashier and guest.
- `tools/flow-test.sh` — end-to-end POS bulk/serialized checkout, shop guest checkout,
  offline sync + idempotency, with full data restore.

**Final result:** PHP lint clean · stock integrity NO FAILURES · model invariants
9/9 · HTTP guards 20/20 · route smoke 52/52 · checkout flows 13/13. Database returned
to baseline (10 sales, 3 completed, 2 returns, 19 units, 33 movements).

---

## VAT report + barcode labels (2026-09-09)

Two requested features, built piece-by-piece and verified.

### VAT report (KRA) — `/reports/vat` (admin only)

- **Output VAT** = Σ `sales.tax_amount` over completed sales in the window (the
  VAT actually charged, stored per sale; voided/pending excluded).
- **VAT on returns** = Σ `return.refund_amount × (sale.tax_amount ÷ sale.taxable
  base)` for completed returns — re-taxed at the *parent sale's effective rate*,
  so it stays correct even if the configured VAT rate changed between sale and
  return.
- **Input VAT** = Σ `goods_received_items.unit_cost × quantity` × current rate
  (purchase costs are stored VAT-exclusive; stated as an assumption).
- Daily breakdown table + sales/purchases/returns detail + KRA-friendly CSV
  export (`/reports/vat/export`). Assumptions listed on-page and in the CSV.

**Bugs found & fixed while building:**
1. **SQLite integer division** — `tax_amount / subtotal` evaluated as integer
   division (both stored as integers), silently returning `0` VAT on returns.
   Fixed with `(s.tax_amount * 1.0) / NULLIF(...)` in both the aggregate and the
   detail query. Verified: S-…-0003 full refund now reverses KSh 19,552.
2. **View data collision** — `View::render` uses `extract($data, EXTR_SKIP)`, so a
   `'data'` key can never reach the view (the parameter `$data` shadows it).
   Renamed the payload key to `'vat'`.

Verified figures (baseline fixtures, 2026-08-01 → 2026-09-09): output 59,200 ·
returns 19,552 · input 29,280 · net 10,368 — match the DB exactly. Access: admin
200, cashier → dashboard, guest → login.

### Barcode labels — `/products/{id}/labels`

- `app/core/Barcode.php` — dependency-free **Code 128 (set B)** encoder → inline
  SVG (no GD, no CDN). Patterns taken from the ISO/IEC 15417 symbol table;
  checksum verified against the published worked example (PJJ123C → 54) plus an
  independent hand-computed vector (HI345678 → 26). Structure tests: 107 symbols,
  11 modules each, 6-run alternation, quiet zones, stop bar.
- `ProductController::labels` + `products/labels.php` (standalone `print` layout):
  shelf labels (name/SKU/price/barcode, qty copies) and serial/IMEI stickers
  (one per in-stock unit). A4 sheet grid by default; `&thermal=1` → 60×40mm roll
  label (one per page). "Print labels" / "Serial labels" buttons on the product page.
- Barcode value = product's barcode field, else SKU; serial stickers encode the
  serial/IMEI.

**Verification after both features:** PHP lint clean · stock integrity NO
FAILURES · model invariants 9/9 · HTTP guards 20/20 · route smoke 52/52 ·
checkout flows 13/13.
