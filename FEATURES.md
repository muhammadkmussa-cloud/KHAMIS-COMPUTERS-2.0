# Khamis Computers — Feature Inventory (Backend ↔ Frontend)

Complete list of every feature, including Catalogue + WhatsApp mode (Phase 13).

> Stack: vanilla PHP 8 (PDO, no Composer) + MySQL (SQLite dev) · HTML/CSS/JS · Apple-inspired + WhatsApp green #25D366
> All state-changing routes require CSRF. Admin-only routes marked `admin`.

---

## 0. Cross-cutting foundation

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 0.1 | Config & env (.env, timezone Africa/Nairobi) | `app/config/config.php`, `bootstrap.php`, `helpers/functions.php` | — |
| 0.2 | Autoloader | `bootstrap.php` | — |
| 0.3 | DB layer (MySQL+SQLite, prepared, dup-key detection) | `core/Database.php` | — |
| 0.4 | Schema, migrations & seeding (product_variants, whatsapp_enquiries, sale_source, condition fields, flags) | `schema.sql`, `core/Schema.php` (seedBaseline, ensureProductVariants, ensureWhatsappEnquiries, migrateColumns) | — |
| 0.5 | Front controller + routing (90+ routes, auth/admin guards) | `public_html/index.php`, `core/Router.php` | 404 views |
| 0.6 | View system (layouts app/auth/shop) | `core/View.php` | `layouts/{app,auth,shop}.php`, `partials/` |
| 0.7 | Auth — roles admin/cashier, bcrypt+rehash | `core/Auth.php`, `AuthController` | `auth/login.php` |
| 0.8 | Session hardening | `bootstrap.php` | — |
| 0.9 | CSRF | `core/Csrf.php` | `csrf_field()` |
| 0.10 | Rate limiting / lockout | `core/Throttle.php` | login error |
| 0.11 | Activity log | `core/Activity.php` | — |
| 0.12 | PDF receipt | `core/Pdf.php`, `SaleController::pdf` | download button |
| 0.13 | Helpers — money, VAT, url(), CSV, thumbnails, badges, flags, WhatsApp | `helpers/functions.php` (is_online_checkout_enabled, is_mpesa_online_enabled, is_whatsapp_ordering_enabled, normalize_whatsapp_number, whatsapp_number, whatsapp_display_number, whatsapp_link, build_whatsapp_message) | all views |
| 0.14 | Flash messages | helpers + `partials/flash.php` | `app.js` |
| 0.15 | Security headers + hardening | `.htaccess` files | — |
| 0.16 | Installer | `SetupController`, `tools/install.php` | `setup/install.php` |
| 0.17 | Prod guard on dev router | `router-dev.php` | — |
| 0.18 | Design system | — | `app.css`, `shop.css` (includes .btn-whatsapp, .variant-selectors, .condition-badge) |

---

## 1. Inventory & suppliers (Admin only)

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 1.1 | Categories CRUD | `CategoryController`, `Category.php` | `categories/index.php` |
| 1.2 | Brands CRUD | `BrandController`, `Brand.php` | `brands/index.php` |
| 1.3 | Products — list, create/edit, delete, warranty | `ProductController`, `Product.php` | `products/index.php`, `form.php` |
| 1.4 | Product detail — stock ledger, serial editor | `ProductController::show`, `Product::units/movements/stockOf` | `products/show.php` |
| 1.5 | Serial/IMEI lifecycle — add, set status, delete (history guards) | `ProductController::addUnits/setUnitStatus/deleteUnit` | `products/show.php` |
| 1.6 | Stock adjustment | `ProductController::adjust`, `LowStock` | `products/show.php` |
| 1.7 | **Product variants** — label, RAM, storage, colour, condition_type, grade, price_override, SKU, active, sort_order; distinct values for UI; fromPrice | `ProductVariant.php` (allForProduct, find, create, update, delete, displayLabel, effectivePrice, distinctValues), `Product::variants()`, `fromPrice()` | `products/show.php` (future admin UI), `shop/product.php` selectors, `shop/_product-card.php` From price |
| 1.8 | **Condition tracking** — condition_type (new/used/refurbished), condition_grade, condition_notes, battery_notes | `Schema::migrateColumns()` (products), `Product::conditionLabel()` | `products/show.php`, `shop/product.php` condition badge + notes |
| 1.9 | Product image gallery | `ProductController::uploadImages/setPrimaryImage/deleteImage`, `product_thumb()` | `products/show.php`; shop cards/detail + OG image |
| 1.10 | Products CSV export | `ProductController::export` | Export button |
| 1.11 | Suppliers CRUD | `SupplierController`, `Supplier.php` | `suppliers/index.php` |
| 1.12 | GRN — line items + serial capture, supplier snapshot | `GrnController`, `GoodsReceived.php` | `grn/{form,index,show}.php` |
| 1.13 | Stock movements ledger | `stock_movements` via `SaleService`, `GoodsReceived`, `SalesReturn` | `products/show.php` |
| 1.14 | Barcode labels — Code 128 SVG, A4 or 60×40 thermal | `ProductController::labels`, `core/Barcode.php` | `products/labels.php` |
| 1.15 | Barcode generation — generate product barcode, auto-generate serials | `ProductController::generateBarcode` | `form.php`, `show.php` |

---

## 2. POS terminal

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 2.1 | POS screen — cart, customer, discount, payment | `PosController::index` | `pos/index.php` |
| 2.2 | Search (name/SKU/barcode/serial) | `PosController::search` | search box |
| 2.3 | Catalog feed | `PosController::catalog` | — |
| 2.4 | Checkout — serial picker, VAT, discount PIN gate, payments, stock decrement | `PosController::checkout`, `SaleService` | `pos/index.php` PIN JS |
| 2.5 | **Sale source tracking** — Walk-in/WhatsApp/Phone/Other + optional WhatsApp enquiry ID link | `PosController::checkout()` (sale_source, whatsapp_enquiry_id), `SaleService::normalizeOpts()` (allowed sources), `Schema` (sales.sale_source, whatsapp_enquiry_id) | `pos/index.php` (Sale Source dropdown + WhatsApp Enquiry ID input + Recent WhatsApp enquiries panel with Use in POS) |
| 2.6 | Receipt — 80mm/58mm thermal + PDF | `PosController::receipt` | `pos/receipt.php` |
| 2.7 | Offline — Service Worker + IndexedDB + queue | `PosController::sync` (idempotent), `sw.js` | `pos-offline.js` |

---

## 3. Online shop — Catalogue + WhatsApp Mode (Current Default)

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 3.1 | Storefront home — featured, categories, hero carousel, catalogue messaging | `ShopController::home` (checkoutEnabled, whatsappEnabled, whatsappNumber) | `shop/home.php` (Browse catalogue vs Track order, WhatsApp CTA when checkout OFF) |
| 3.2 | Browse — search, category, brand, sort, From pricing | `ShopController::browse`, `Product::shopBrowse` | `shop/browse.php` (Active filters, load-more) |
| 3.3 | **Product detail variant-aware** — RAM/Storage/Colour/Condition/Label selectors, live price/SKU/condition/grade, availability, condition notes, From price | `ShopController::product` (variants, OG meta: ogTitle, ogDescription, ogImage absolute, ogUrl), `Product::variants()`, `ProductVariant::effectivePrice()`, `displayLabel()` | `shop/product.php` (variant-selectors UI, selected-variant-summary, condition-badge-row, purchase-panel with WhatsApp CTA, mobile-buy-bar sticky, JSON blocks product-data + variant-data) |
| 3.4 | **WhatsApp CTA** — primary Order on WhatsApp (in-stock), Ask About Availability (out-of-stock), Share fallback, mobile sticky bar | `helpers/functions.php` (whatsapp_number, whatsapp_link, build_whatsapp_message with placeholders, no cost/IMEI exposure) | `shop/product.php` (buildMessage(), updateWhatsappLink() with encodeURIComponent, variant-aware), `shop.css` (.btn-whatsapp #25D366, .btn-out-of-stock #f59e0b, .whatsapp-nav-link) |
| 3.5 | **Open Graph SEO** — og:title, og:description (variant summary + trimmed description), og:image (primary image absolute HTTPS via url()), og:url canonical, og:type=product, twitter:card | `ShopController::product` (ogTitle, ogDescription, ogImage, ogUrl), `layouts/shop.php` (meta tags) | view source shows OG for WhatsApp rich preview |
| 3.6 | **Feature flags + server-side guards** — online_checkout_enabled=0, mpesa_online_enabled=0, whatsapp_ordering_enabled=1; cart/apiCart/checkout return 403/unavailable when OFF; M-Pesa online blocked server-side | `ShopController::cart()` (renders unavailable page), `apiCart()` (403), `checkout()` (403 + auto-void if mpesa attempted), `is_online_checkout_enabled()`, `is_mpesa_online_enabled()`, `Schema::seedBaseline()` | `layouts/shop.php` (conditional cart vs WhatsApp nav, KC_SHOP_CONFIG checkoutEnabled/whatsappEnabled/whatsappNumber/csrf/whatsappEnquiryUrl/currency), `shop.js` (if checkoutEnabled false skip add-to-cart, mobile add triggers WhatsApp, renderCartPage shows catalogue-mode message), `shop/cart.php` (unavailable page with WhatsApp CTA) |
| 3.7 | **WhatsApp lead tracking** — lightweight POST /shop/whatsapp-enquiry (CSRF, keepalive), no personal data beyond product/variant/price/url | `ShopController::whatsappEnquiry()` (inserts product_id, variant_id, product_name, variant_label, price_shown, product_url, condition_type, source_page, created_at with columnExists guards), `Schema::ensureWhatsappEnquiries()` (creates table + migrates extra columns) | `shop/product.php` (trackWhatsapp() fetch to whatsappEnquiryUrl) |
| 3.8 | Cart (preserved, disabled) — localStorage + server validation | `ShopController::apiCart` (preserved, 403 when disabled) | `shop.js` + `shop/cart.php` (disabled message) |
| 3.9 | Guest checkout (preserved, disabled) — name/phone/email, fulfillment, delivery zone server-resolved | `ShopController::checkout` (preserved, 403 when disabled, auto-void mpesa if attempted) | `shop/cart.php` (staged checkout, only init when checkoutEnabled) |
| 3.10 | Order confirmation + polling (preserved) | `ShopController::order`, `orderStatus` | `shop/order.php` |
| 3.11 | Order tracking (preserved) | `ShopController::track/trackLookup`, `Sale::findByNumberAndPhone` | `shop/track.php` |
| 3.12 | Layout & styling — nav-over-hero, transparent nav, footer WhatsApp link | — | `layouts/shop.php`, `shop.css` |

---

## 4. Sales & orders

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 4.1 | Sales list — search/filters (status, channel, payment, **source**, date), totals, WA badge | `SaleController::index` (source filter), `Sale::search()` + `buildWhere()` (COALESCE sale_source), `summary()` | `sales/index.php` (Source filter dropdown, Source column with green WhatsApp badge, WA enquiry badge, active filters) |
| 4.2 | Sales CSV export (filter-preserving, includes source + WA id) | `SaleController::export`, `Sale::exportAll` | Export link |
| 4.3 | Sale detail — items, serials, warranty, delivery, payment, **source**, WA id | `SaleController::show`, `SaleService::items` | `sales/show.php` (source badge, WA id badge, Order information source) |
| 4.4 | PDF receipt | `SaleController::pdf`, `Pdf.php` | `sales/show.php` |
| 4.5 | Sale void / cancel | `SaleController::void`, `SaleService::void` | Void buttons |
| 4.6 | M-PESA admin fallbacks | `SaleController::mpesaMarkPaid/mpesaRetry`, `MpesaService::stkPush` | `sales/show.php` |
| 4.7 | Sale engine — number collision retry, serial auto-assign, discounts, VAT, delivery fee, offline dedup, low-stock, **sale_source + whatsapp_enquiry_id** | `SaleService.php` (normalizeOpts allowed sources, insertData with columnExists) | — |

---

## 5. Returns & expenses

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 5.1 | Returns list, create, approve/reject, refund capping, stock restore | `ReturnController`, `SalesReturn.php` | `returns/{index,form,show}.php` |
| 5.2 | Expenses | `ExpenseController`, `Expense.php` | `expenses/index.php` |

---

## 6. Reports & Z-report

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 6.1 | Dashboard — KPIs, revenue chart, top products, low-stock | `DashboardController`, `ReportController::metrics` | `dashboard/home.php` |
| 6.2 | Reports — date-range KPIs, channel split, **sale source breakdown** (Walk-in/WhatsApp/Phone/Other), **WhatsApp enquiries total/converted**, top products, low stock | `ReportController::index` (metrics includes saleSources grouping via COALESCE sale_source, whatsappEnquiries total + converted via whatsapp_enquiry_id or sale_source=whatsapp), `range()`, `metrics()` | `reports/index.php` (KPIs: WhatsApp enquiries, Converted, Walk-in vs WhatsApp, Shop mode; Channels + By source hbar with WhatsApp green #25D366) |
| 6.3 | Reports CSV export (includes channels + sources + WhatsApp) | `ReportController::export` | Export button |
| 6.4 | Z-report / close-of-day | `ReportController::zReport/closeDay`, `ZReport.php` | `reports/z.php` |
| 6.5 | VAT report (KRA) — output vs input vs returns, daily breakdown, CSV | `ReportController::vatReport/vatExport/vatData` | `reports/vat.php`, `reports-tabs.php` |
| 6.6 | Purchases by supplier | `ReportController::purchases/purchasesExport/purchasesData` | `reports/purchases.php` |

---

## 7. Settings

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 7.1 | Shop identity | `SettingsController::update`, `Setting.php` | `settings/index.php` Business tab |
| 7.2 | **WhatsApp & Online** — feature flags (online_checkout_enabled, mpesa_online_enabled, whatsapp_ordering_enabled), WhatsApp sales number with Kenyan normalization validation, message template with placeholders, shop mode documentation | `SettingsController::update` (normalize_whatsapp_number validation, flags, template, tab-aware redirect), `helpers/functions.php` (normalize, display, whatsapp_number) | `settings/index.php` WhatsApp & Online tab (section-kicker Catalogue + WhatsApp mode, current mode b, checkboxes, number input with normalized hint wa.me/..., template textarea, how-it-works card) |
| 7.3 | Pricing & receipts — VAT rate, receipt footer | `SettingsController::update`, `vat_rate()` | Tax tab |
| 7.4 | Discount authorisation — PIN threshold + hash | `SettingsController::update`, `SaleService` gate | Discounts tab + POS PIN JS |
| 7.5 | Delivery zones | `DeliveryZoneController` | Delivery tab |
| 7.6 | Email notifications | `SettingsController::update`, `Mailer`, `LowStock` | Notifications tab |
| 7.7 | M-PESA config — master mpesa_enabled + online mpesa_online_enabled distinction, env, shortcode type, secrets blanked | `SettingsController::update`, `MpesaService` | M-PESA tab (hint: master vs online) + callback URL tokenized |
| 7.8 | Hero carousel (Online Shop tab renamed) | `HeroSlideController`, `HeroSlide.php` | `settings/hero.php` inside panel-online-shop |
| 7.9 | Production checklist | — | Deploy tab |

---

## 8. Staff management

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 8.1 | Staff list, add, edit, reset password, delete (admin-count guard) | `StaffController` | `staff/index.php` |

---

## 9. Email notifications

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 9.1 | Mailer — mail() + SMTP | `services/Mailer.php` | — |
| 9.2 | Order-confirmation email | `ShopController::checkout` (best-effort) | — |
| 9.3 | Low-stock alert (threshold, once per 24h) | `services/LowStock.php` | — |

---

## 10. M-PESA STK push

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 10.1 | OAuth token cached | `MpesaService::accessToken` | — |
| 10.2 | STK push (password, paybill/till, phone normalisation) | `MpesaService::stkPush(phone, amount, saleNumber, saleId)`, `mpesa_transactions` | — |
| 10.3 | Callback | `ShopController::mpesaCallback`, `processCallback` | — |
| 10.4 | Pay-now flow (pending + STK; auto-void on failure) — **preserved but disabled** via `mpesa_online_enabled=0` | `ShopController::checkout` (checks is_mpesa_online_enabled, voids if attempted) | `shop/cart.php` pay-now option hidden when mpesa_online_enabled=0 |
| 10.5 | **Preservation guarantee** — DO NOT DELETE M-Pesa files/classes/API/callbacks/transactions/migrations | All MpesaService, mpesa_transactions table, callback route, migrations in Schema | — |

---

## 11. Offline POS

| # | Feature | Backend | Frontend |
|---|---------|---------|----------|
| 11.1 | Service Worker | — | `sw.js`, `layouts/app.php` |
| 11.2 | KV store (IndexedDB→localStorage→memory) | — | `pos-offline.js` |
| 11.3 | Catalogue cache | — | `pos-offline.js` |
| 11.4 | Offline sale queue (sale_source preserved) | — | `pos-offline.js` |
| 11.5 | Sync endpoint (401 on unauth, idempotent) | `PosController::sync` (now handles sale_source, whatsapp_enquiry_id) | `pos-offline.js` |

---

## 12. Data model (all tables)

`users`, `shop_settings` (now includes online_checkout_enabled, mpesa_online_enabled, whatsapp_ordering_enabled, whatsapp_sales_number, whatsapp_message_template), `categories`, `brands`, `products` (condition_type, condition_grade, condition_notes, battery_notes, warranty_months, image), `product_variants` (id, product_id, sku, label, ram, storage, colour, condition_type, grade, price_override, is_active, sort_order, created_at), `product_images`, `delivery_zones`, `low_stock_alerts`, `mpesa_transactions`, `z_reports`, `inventory_units` (warranty_expires, grn_item_id, **variant_id**), `stock_movements`, `suppliers`, `goods_received`, `goods_received_items`, `sales` (**sale_source** VARCHAR(20) DEFAULT walk-in: walk-in/whatsapp/phone/other/online, **whatsapp_enquiry_id** INT NULL, fulfillment, delivery_*), `sale_items` (unit_cost), `whatsapp_enquiries` (id, product_id, variant_id, product_name, variant_label, price_shown, source_page, product_url, condition_type, customer_phone, customer_name, message, created_at), `returns`, `return_items`, `expenses`, `throttle`, `activity_log`, `hero_slides`

---

## 13. Backend ↔ Frontend file map

**Backend**
- `public_html/index.php` — 90+ routes incl. `POST shop/whatsapp-enquiry`
- `core/` — Database, Router, View, Auth, Csrf, Schema (ensureProductVariants, ensureWhatsappEnquiries, sale_source, condition fields, flags), Throttle, Activity, Pdf, Barcode
- `controllers/` — 18 controllers: ShopController (catalogue mode, OG, whatsappEnquiry, 403 guards), PosController (sale_source + recent enquiries), SettingsController (WhatsApp flags + Kenyan validation), SaleController (source filter + CSV), ReportController (source + WhatsApp metrics), plus Auth, Setup, Dashboard, Product, Category, Brand, Supplier, DeliveryZone, Grn, Return, Expense, Staff
- `models/` — Product (variants(), fromPrice(), conditionLabel()), ProductVariant (allForProduct, displayLabel, effectivePrice, distinctValues), Sale (buildWhere source), SaleService (sale_source, whatsapp_enquiry_id, columnExists guards), Setting, DeliveryZone, etc.
- `services/` — MpesaService (stkPush phone, amount, saleNumber, saleId), Mailer, LowStock
- `helpers/functions.php` — 40+ helpers incl. is_online_checkout_enabled, is_mpesa_online_enabled, is_whatsapp_ordering_enabled, normalize_whatsapp_number, whatsapp_display_number, whatsapp_number, whatsapp_link, build_whatsapp_message
- `schema/schema.sql`, `tools/install.php`, `.env.example`, `docs/`

**Frontend**
- `layouts/` — `app.php`, `auth.php`, `shop.php` (OG meta, conditional cart vs WhatsApp nav, KC_SHOP_CONFIG checkoutEnabled/whatsappEnabled/whatsappNumber/whatsappDisplay/shopName/baseUrl/csrf/whatsappEnquiryUrl/currency)
- `partials/` — topnav, flash, reports-tabs
- `views/**` — pos (sale_source selector + recent enquiries), products, sales (source column), returns, grn, suppliers, categories, brands, expenses, reports (source breakdown + WhatsApp KPIs), settings (WhatsApp & Online tab + Hero Carousel), shop (home catalogue messaging, browse, product variant-aware + WhatsApp CTA + mobile sticky bar + OG JSON, cart unavailable)
- `assets/css/` — `app.css`, `shop.css` (.btn-whatsapp #25D366, .btn-out-of-stock #f59e0b, .variant-selectors, .variant-option.active, .condition-badge-row, #mobile-buy-bar .btn-whatsapp)
- `assets/js/` — `app.js`, `shop.js` (checkoutEnabled flag, mobile WhatsApp trigger, gallery, load-more, hero carousel + transparent nav, WhatsApp tracking), `pos-offline.js` (sale_source preserved)
- `sw.js` — Service Worker
