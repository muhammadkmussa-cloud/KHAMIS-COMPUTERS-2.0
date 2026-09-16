# Khamis Computers — Feature Inventory (Backend ↔ Frontend)

Complete list of every feature in the system and exactly where it is implemented.
**Backend** = PHP under `app/` (+ `schema/`, `tools/`, routing in `public_html/index.php`).
**Frontend** = HTML/JS/CSS under `app/views/` and `public_html/assets/`.

> Stack: vanilla PHP 8 (PDO, no Composer) + MySQL (SQLite for dev) · plain HTML/CSS/JS · Apple-inspired blue & white UI.
> All state-changing routes require a CSRF token. Admin-only routes are marked `admin` in the router.

---

## 0. Cross-cutting foundation

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 0.1 | Config & environment (.env loader, defaults, base URL auto-detect, timezone) | `app/config/config.php`, `app/bootstrap.php`, `app/helpers/functions.php` (`load_env`, `env`, `config`), `.env` / `.env.example` | — |
| 0.2 | Autoloader (core/controllers/models/services) | `app/bootstrap.php` (`spl_autoload_register`) | — |
| 0.3 | Database layer (PDO; MySQL + SQLite, prepared statements, insert/update/delete/fetch, dup-key detection) | `app/core/Database.php` | — |
| 0.4 | Schema, migrations & seeding (idempotent `CREATE TABLE IF NOT EXISTS`, `migrateColumns()` for upgrades, baseline settings, delivery-zone seeds) | `schema/schema.sql`, `app/core/Schema.php`, `tools/install.php` | — |
| 0.5 | Front controller + routing (`{param}` matching, method, `auth`/`admin` guards, 404) | `public_html/index.php`, `app/core/Router.php` | `app/views/errors/404.php`, `app/views/shop/404.php` |
| 0.6 | View system (layouts `app` / `auth` / `shop`, partials, page render) | `app/core/View.php` | `app/views/layouts/{app,auth,shop}.php`, `app/views/partials/{topnav,flash,reports-tabs}.php` |
| 0.7 | Auth — login/logout, roles (admin/cashier), bcrypt + auto-rehash, `requireLogin`/`requireAdmin` | `app/core/Auth.php`, `app/controllers/AuthController.php` | `app/views/auth/login.php`, logout button in `app/views/layouts/app.php` |
| 0.8 | Session hardening (strict mode, cookie-only, regenerate on login, HttpOnly/SameSite) | `app/bootstrap.php` | — |
| 0.9 | CSRF protection (session-scoped token, `checkOrFail`) | `app/core/Csrf.php`, `app/helpers/functions.php` (`csrf_field`) | `csrf_field()` in every form |
| 0.10 | Login rate limiting / lockout (5 tries → 15-min ban per email+IP) | `app/core/Throttle.php`, `AuthController::login` | error on login form |
| 0.11 | Activity / audit log (logins, sales, settings, staff, M-PESA, Z-report…) | `app/core/Activity.php` (+ `activity_log` table) | — |
| 0.12 | PDF receipt generation (dependency-free) | `app/core/Pdf.php`, `SaleController::pdf` | download button in `app/views/sales/show.php` |
| 0.13 | Helpers — money (KSh), VAT, `url()`, CSV streaming, thumbnails/monograms, status badges, flash | `app/helpers/functions.php` | used across all views |
| 0.14 | Flash messages (success/error/info) | `app/helpers/functions.php` (`flash`), `app/views/partials/flash.php` | auto-dismiss in `app.js` |
| 0.15 | Security headers + web hardening (CSP, HSTS, dotfile deny, `Options -Indexes`, deny `app/`/`schema/`/`tools/`/`storage/`) | `public_html/.htaccess`, `app/.htaccess`, `schema/.htaccess`, `tools/.htaccess`, `storage/.htaccess` | — |
| 0.16 | Installer — web wizard (refuses to run once installed) + CLI | `app/controllers/SetupController.php`, `tools/install.php` | `app/views/setup/install.php` |
| 0.17 | Production guard on dev router | `public_html/router-dev.php` | — |
| 0.18 | Design system / styles (nav, cards, KPIs, badges, buttons, tables, receipt) | — | `public_html/assets/css/app.css`, `public_html/assets/css/shop.css` |

---

## 1. Inventory & suppliers

> **Admin only** (cashiers get read-only product view; all create/edit/stock-in
> routes are `admin`-gated and hidden in the UI).

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 1.1 | **Categories** CRUD | `CategoryController`, `app/models/Category.php` | `app/views/categories/index.php` |
| 1.2 | **Brands** CRUD | `BrandController`, `app/models/Brand.php` | `app/views/brands/index.php` |
| 1.3 | **Products** — list, create/edit, delete, activate/deactivate, warranty months | `ProductController` (`index/create/store/show/edit/update/delete`), `app/models/Product.php` | `app/views/products/index.php`, `app/views/products/form.php` |
| 1.4 | Product detail — stock ledger, serial-unit editor | `ProductController::show`, `Product::units/movements/stockOf` | `app/views/products/show.php` |
| 1.5 | Serial / IMEI unit lifecycle — add units, set status (in-stock/sold/returned/defective), delete | `ProductController::addUnits/setUnitStatus/deleteUnit`, `inventory_units` table | `app/views/products/show.php` |
| 1.6 | Stock adjustment (bulk products, reason, low-stock hook) | `ProductController::adjust`, `LowStock::maybeAlert` | `app/views/products/show.php` |
| 1.7 | **Product image gallery** — upload (multi), make-primary, remove, safe streaming | `ProductController::image/uploadImages/setPrimaryImage/deleteImage`, `product_images` table, `products.image`, `product_thumb()` helper | `app/views/products/show.php`; shop cards/detail |
| 1.8 | Products CSV export (incl. barcode column) | `ProductController::export`, `csv_response()` | Export button in `app/views/products/index.php` |
| 1.9 | **Suppliers** CRUD (duplicate check; deactivate-instead-of-delete when GRN history) | `SupplierController`, `app/models/Supplier.php`, `suppliers` table | `app/views/suppliers/index.php` |
| 1.10 | **Goods Received (GRN)** — create with line items + serial capture, supplier linkage (snapshot), list, detail | `GrnController`, `app/models/GoodsReceived.php`, `goods_received`/`goods_received_items` tables | `app/views/grn/{form,index,show}.php` (dynamic line/serial JS) |
| 1.11 | Stock movements ledger (single source of truth for stock) | `stock_movements` table via `SaleService`, `GoodsReceived`, `SalesReturn`, `ProductController` | shown in `app/views/products/show.php` |
| 1.12 | **Barcode labels** — shelf price labels + serial/IMEI stickers, Code 128 in inline SVG, A4 sheet grid or 60×40mm thermal | `ProductController::labels`, `app/core/Barcode.php` (dependency-free Code 128) | `app/views/products/labels.php`, Print labels / Serial labels buttons in `app/views/products/show.php` |
| 1.13 | **Barcode generation** — "Generate" a unique product barcode on the form; auto-generate serials when adding units; "print labels after saving/adding" redirects straight to the label sheet | `ProductController::generateBarcode/generateSerials`, route `products/barcode/generate` | `app/views/products/form.php` (Generate + print checkbox), `app/views/products/show.php` (generate serials + print checkbox) |

---

## 2. POS terminal (physical shop)

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 2.1 | POS screen — cart, customer fields, discount, payment method | `PosController::index` | `app/views/pos/index.php` |
| 2.2 | Product / serial / IMEI search | `PosController::search` (JSON) | `app/views/pos/index.php` (search box) |
| 2.3 | Catalog feed for the cart | `PosController::catalog` (JSON) | `app/views/pos/index.php` |
| 2.4 | **Checkout** — serial selection, VAT (16%), discounts with manager-PIN gate, payment methods (cash/mpesa/card/bank), stock decrement | `PosController::checkout`, `app/models/SaleService.php` | `app/views/pos/index.php` (PIN-gate JS) |
| 2.5 | **Tax receipt** — serial + warranty lines, VAT breakdown, print | `PosController::receipt` | `app/views/pos/receipt.php` |
| 2.6 | **Offline POS** — Service Worker shell caching | `public_html/sw.js` (frontend), served from `public_html/` | `public_html/sw.js` |
| 2.7 | Offline catalogue cache + sale queue (IndexedDB → localStorage → memory), serial matching offline | `PosController::sync` (JSON, in-controller auth), idempotent dedup | `public_html/assets/js/pos-offline.js` |
| 2.8 | Offline sale sync with `UNIQUE(device_id, client_ref)` dedup | `PosController::sync`, `SaleService`, index in `Schema::createIndexes` | `pos-offline.js` |

---

## 3. Online shop (guest checkout)

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 3.1 | Storefront home (featured products, categories) | `ShopController::home` | `app/views/shop/home.php` |
| 3.2 | Catalog / browse with category filter | `ShopController::browse` | `app/views/shop/browse.php` |
| 3.3 | Product detail with image gallery + thumbnail picker | `ShopController::product`, `Product::images`, `Product::related` | `app/views/shop/product.php` |
| 3.4 | Cart (localStorage lines, server validation via API) | `ShopController::apiCart` (JSON) | `public_html/assets/js/shop.js` + `app/views/shop/cart.php` |
| 3.5 | Guest checkout — name/phone/email, pickup vs delivery, address | `ShopController::checkout`, `SaleService` | `app/views/shop/cart.php`, `shop.js` |
| 3.6 | **Zone-based delivery fees** (server-resolved, snapshotted) | `DeliveryZoneController`, `app/models/DeliveryZone.php`, `delivery_zones` table, `ShopController::checkout` | `app/views/shop/cart.php` (zone select), `shop.js` |
| 3.7 | Order confirmation page (session-gated) | `ShopController::order` | `app/views/shop/order.php` |
| 3.8 | Payment status polling (M-PESA pending → paid auto-reload) | `ShopController::orderStatus` (JSON) | `app/views/shop/order.php` (poll JS) |
| 3.9 | **Order tracking** (customer re-fetch by number + phone, rate-limited) | `ShopController::track/trackLookup`, `Sale::findByNumberAndPhone` | `app/views/shop/track.php`, footer link in `app/views/layouts/shop.php` |
| 3.10 | Shop layout & styling | — | `app/views/layouts/shop.php`, `public_html/assets/css/shop.css` |

---

## 4. Sales & orders

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 4.1 | Sales list — search/filters (status, channel, payment, date), totals | `SaleController::index`, `app/models/Sale.php` (`search`, `buildWhere`) | `app/views/sales/index.php` |
| 4.2 | Sales CSV export (filter-preserving) | `SaleController::export`, `Sale::exportAll`, `csv_response()` | Export link in `app/views/sales/index.php` |
| 4.3 | Sale detail (items, serials, warranty, delivery, payment) | `SaleController::show`, `SaleService::items` | `app/views/sales/show.php` |
| 4.4 | PDF receipt download | `SaleController::pdf`, `app/core/Pdf.php` | `app/views/sales/show.php` |
| 4.5 | **Sale void / cancel** — restore stock (serial units back to stock, offsetting movements), blocked when returns exist | `SaleController::void`, `SaleService::void` | Void buttons in `app/views/sales/{index,show}.php` |
| 4.6 | **M-PESA admin fallbacks** — mark-paid, re-send STK prompt | `SaleController::mpesaMarkPaid/mpesaRetry`, `MpesaService::stkPush` | `app/views/sales/{index,show}.php` |
| 4.7 | Sale engine — number allocation (collision retry), serial auto-assign, discounts, VAT, delivery fee, offline dedup, low-stock trigger | `app/models/SaleService.php` | — |

---

## 5. Returns & expenses

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 5.1 | Returns list, create (per sale item/unit, reason), approve/reject, refund capping, stock restore | `ReturnController`, `app/models/SalesReturn.php`, `returns`/`return_items` tables | `app/views/returns/{index,form,show}.php` |
| 5.2 | Expenses — add, list, delete (admin) | `ExpenseController`, `app/models/Expense.php` | `app/views/expenses/index.php` |

---

## 6. Reports & Z-report

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 6.1 | Dashboard — KPIs (revenue, orders, AOV, profit, refunds, expenses, net), revenue chart, top products, low-stock list | `DashboardController`, `ReportController::metrics` | `app/views/dashboard/home.php` |
| 6.2 | Reports — date-range KPIs, channel split, top products, low stock | `ReportController::index`, `ReportController::range/metrics` | `app/views/reports/index.php` |
| 6.3 | Reports CSV export | `ReportController::export`, `csv_response()` | Export button in `app/views/reports/index.php` |
| 6.4 | **Z-report / close-of-day** — live per-day/cashier summary (cash/mpesa/card/bank, discounts, voids, refunds, expected cash), close snapshot (upsert per user+date) | `ReportController::zReport/closeDay`, `app/models/ZReport.php`, `z_reports` table | `app/views/reports/z.php` (KPI grid + close form + history) |
| 6.5 | **VAT report (KRA)** — output VAT (completed sales) vs input VAT (GRN purchases, estimated) vs VAT on returns (re-taxed at each sale's effective rate), daily breakdown + detail + CSV export | `ReportController::vatReport/vatExport/vatData` | `app/views/reports/vat.php`, tabs in `app/views/partials/reports-tabs.php` |
| 6.6 | **Purchases by supplier** — GRNs grouped by supplier (saved suppliers by id, free-text names by snapshot), deliveries, net purchases, est. input VAT, share %, last delivery; supplier filter + per-supplier drill-down (GRN items) + CSV export | `ReportController::purchases/purchasesExport/purchasesData` | `app/views/reports/purchases.php`, `app/views/partials/reports-tabs.php` |

---

## 7. Settings

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 7.1 | Shop identity (name, tagline, contact, address) | `SettingsController::update`, `app/models/Setting.php` | `app/views/settings/index.php` |
| 7.2 | Pricing & receipts (VAT rate, receipt footer) | `SettingsController::update`, `vat_rate()` helper | `app/views/settings/index.php` |
| 7.3 | Discount authorisation — manager PIN threshold + bcrypt PIN hash | `SettingsController::update`, `SaleService` gate | `app/views/settings/index.php`, POS PIN JS |
| 7.4 | **Delivery zones** management (add/edit/delete, active, sort) | `DeliveryZoneController` | `app/views/settings/index.php` (zones card) |
| 7.5 | **Email notifications** config (enable, sender, transport, SMTP creds, low-stock threshold/recipient) | `SettingsController::update`, `Mailer`, `LowStock` | `app/views/settings/index.php` (email card) |
| 7.6 | **M-PESA** config (enable, env, shortcode type, shortcode, passkey, consumer key/secret, callback URL hint) | `SettingsController::update`, `MpesaService` | `app/views/settings/index.php` (M-PESA card) |
| 7.7 | Production checklist card | — | `app/views/settings/index.php` |

---

## 8. Staff management

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 8.1 | Staff list, add, edit (role, active), reset password, delete (admin-count guard) | `StaffController` | `app/views/staff/index.php` |

---

## 9. Email notifications

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 9.1 | Mailer — PHP `mail()` transport + dependency-free SMTP (STARTTLS/SSL, AUTH LOGIN), multipart HTML/plain, RFC2047 headers | `app/services/Mailer.php` | — |
| 9.2 | **Order-confirmation email** (HTML summary incl. delivery fee) | `ShopController::checkout` (best-effort, try/catch), `Mailer::orderConfirmationHtml` | — |
| 9.3 | **Low-stock alert** (threshold, once per product per 24h, logged in `low_stock_alerts`) | `app/services/LowStock.php`, hooked into `SaleService::commit` + `ProductController::adjust` | — |

---

## 10. M-PESA STK push (Safaricom Daraja)

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 10.1 | OAuth token (cached with expiry) | `MpesaService::accessToken` | — |
| 10.2 | STK push (password = base64(shortcode+passkey+timestamp), paybill/till, phone normalisation, account ref) | `MpesaService::stkPush`, `mpesa_transactions` table | — |
| 10.3 | Callback processing (success → sale `completed` + receipt number; failure → txn `failed`) | `ShopController::mpesaCallback`, `MpesaService::processCallback` | — |
| 10.4 | Pay-now checkout flow (pending sale + STK; auto-void on push failure) | `ShopController::checkout` | `app/views/shop/cart.php` (pay-now option), `shop.js` |

---

## 11. Offline POS (deep dive)

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 11.1 | Service Worker registration + asset caching | — | `public_html/sw.js`, `app/views/layouts/app.php` (register) |
| 11.2 | Best-effort KV store (IndexedDB → localStorage → memory) | — | `public_html/assets/js/pos-offline.js` |
| 11.3 | Catalogue cache (products + serial units) | — | `pos-offline.js` |
| 11.4 | Offline sale queue (queue while offline, flush on reconnect) | — | `pos-offline.js` |
| 11.5 | Sync endpoint (JSON, 401 on unauth, per-item results, duplicate-safe) | `PosController::sync`, `SaleService` | `pos-offline.js` |

---

## 12. Data model (all tables)

`users`, `shop_settings`, `categories`, `brands`, `products`, `product_images`,
`delivery_zones`, `low_stock_alerts`, `mpesa_transactions`, `z_reports`,
`inventory_units`, `stock_movements`, `suppliers`, `goods_received`,
`goods_received_items`, `sales`, `sale_items`, `returns`, `return_items`,
`expenses`, `throttle`, `activity_log` — defined in `schema/schema.sql`.

---

## 13. Backend ↔ Frontend file map (quick reference)

**Backend (PHP)**
- `public_html/index.php` — all 90+ routes
- `app/core/` — `Database`, `Router`, `View`, `Auth`, `Csrf`, `Schema`, `Throttle`, `Activity`, `Pdf`, `Barcode`
- `app/controllers/` — 17 controllers (Auth, Setup, Dashboard, Product, Category, Brand, Supplier, DeliveryZone, Grn, Pos, Shop, Sale, Return, Expense, Report, Settings, Staff)
- `app/models/` — 12 models (Product, Sale, SaleService, SalesReturn, GoodsReceived, Category, Brand, Supplier, DeliveryZone, Expense, Setting, ZReport)
- `app/services/` — `Mailer`, `LowStock`, `MpesaService`
- `app/helpers/functions.php` — 30+ helpers
- `schema/schema.sql`, `tools/install.php`, `.env(.example)`, `docs/cpanel-deployment.md`
- `tools/` verification harness — `stock-check.php` (data-integrity), `integration-test.php` (model invariants), `http-test.sh` (controller guards), `route-smoke.sh` (every route), `flow-test.sh` (checkout/sync e2e)

**Frontend (HTML/CSS/JS)**
- `app/views/layouts/` — `app.php` (staff), `auth.php` (login/install), `shop.php` (storefront), `print.php` (standalone print pages)
- `app/views/partials/` — `topnav.php`, `flash.php`, `reports-tabs.php`
- `app/views/**` — one folder per module (pos, products, sales, returns, grn, suppliers, categories, brands, expenses, reports, settings, staff, shop, setup, auth, errors, dashboard)
- `public_html/assets/css/` — `app.css` (staff), `shop.css` (storefront)
- `public_html/assets/js/` — `app.js` (shared), `shop.js` (cart/checkout), `pos-offline.js` (offline POS)
- `public_html/sw.js` — Service Worker
