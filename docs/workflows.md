# Khamis Computers — System Workflows

Complete map of every workflow, including Catalogue + WhatsApp mode (current default).

Roles: **admin** and **cashier** only — no customer accounts. Public shop is **Catalogue + WhatsApp** (guest checkout preserved but disabled via flag).

---

## 1. First run — installation

1. Empty DB → redirect to `/install`
2. Form: name, email, password
3. Creates schema → migrates columns (product_variants, whatsapp_enquiries, sale_source, condition fields) → indexes → seeds baseline settings (online_checkout_enabled=0, mpesa_online_enabled=0, whatsapp_ordering_enabled=1, whatsapp_sales_number, etc.) + delivery zones → first admin (bcrypt)
4. Redirect to `/login`

Security: installer refuses once installed. Delete install route after deploy.

---

## 2. Sign-in / sign-out

Staff URL: `/login` (no button on storefront)
- `GET /login` → form, `POST /login` → verify, regenerate session
- `POST /logout` → CSRF-protected
- Unauth → `/login`

---

## 3. Admin configuration (admin only)

### Settings — `GET /settings` — tabs: Business, WhatsApp & Online, Tax & Receipts, Discounts, Notifications, M-PESA, Delivery, Hero Carousel, Deploy

- **Business:** name, tagline, address, phone, email, receipt footer
- **WhatsApp & Online (NEW):**
  - Current mode display: Catalogue + WhatsApp vs Online Checkout
  - **Enable WhatsApp ordering** (primary CTA)
  - **WhatsApp Sales Number** — Kenyan formats 07XXXXXXXX, 2547XXXXXXXX, +2547XXXXXXXX → normalized to 254... for wa.me, validation via `normalize_whatsapp_number()`, hint shows +... and wa.me/...
  - **Online Checkout** — checkbox `online_checkout_enabled`; OFF = catalogue mode (recommended), ON = restores cart/checkout
  - **Online M-PESA** — `mpesa_online_enabled`; OFF blocks public STK server-side, POS M-Pesa still works via `mpesa_enabled`
  - **Message template** — optional custom template with placeholders {product_name} {variant} {ram} {storage} {colour} {condition} {grade} {price} {sku} {product_url} {shop_name}; safe replacement, no code execution
  - How-it-works card: Product → Variant → WhatsApp → Conversation → Deal → POS sale with source=WhatsApp
- **Tax & Receipts:** VAT rate (default 16%), receipt footer, currency display
- **Discounts:** PIN threshold + hash, clear checkbox
- **Notifications:** mail_enabled, mail_from, transport mail/smtp, smtp host/port/encryption/username/password (blanked), low_stock_threshold/recipient
- **M-PESA:** master enable `mpesa_enabled` (POS+online), env sandbox/live, shortcode type paybill/till, shortcode, passkey, consumer key/secret (blanked), callback URL tokenized `mpesa/callback?token=...`, Test buttons (email + M-Pesa) via fetch + toast
- **Delivery zones:** add/edit/delete, fee, active, sort_order; flat fee resolved server-side
- **Hero Carousel:** manage slides (eyebrow, headline, description, desktop/mobile image, CTA, text/image position, overlay, order, active)

### Staff — `GET /staff`
Create/edit, reset password, deactivate/activate, delete with self-protection (can't demote/delete self or last admin)

### Catalogue — categories, brands, suppliers, products, variants
- **Categories** `/categories`, **Brands** `/brands`: create/rename/delete (products become uncategorised on delete, SET NULL)
- **Suppliers** `/suppliers`: create/edit/delete; deactivate instead of delete when GRN history
- **Products** `/products`: list, create/edit, delete, activate, warranty, condition_type/grade/notes/battery_notes, images, stock ledger, serial units, barcode generation, labels
- **Product Variants** (NEW): `product_variants` table — label, RAM, storage, colour, condition_type, grade, price_override, SKU, active, sort_order; distinct values for selector UI; From pricing via `Product::fromPrice()`

---

## 4. Stock intake (admin only)

### Bulk product — `products/new`
Create: name, SKU, barcode, category, brand, supplier, cost/sell price, reorder, description, condition, images → Add qty via Adjust stock (+/−) or GRN → `stock_movements`

### Serialized product
Create with Track serials=yes → Add units (paste serials, dup rejected) → `inventory_units` in_stock → status guards (can't hand-mark sold, can't change sold outside returns/void, can't delete with history)

### GRN — `grn/new`
Pick supplier (or free-text), date, note → line items product+qty+unit cost → stock received, GRN saved, supplier snapshot

### Product images
Upload multi, make-primary, remove → stored in `storage/uploads/products/` (web-denied) → streamed `uploads/p/{file}` → primary used in shop cards, product page, OG image for WhatsApp preview

### Variants (NEW)
Admin creates variants per product (future UI: product show page variant manager). Each variant can override price and SKU, and carries RAM/storage/colour/condition/grade. Shop product page shows distinct attribute values as selector buttons.

---

## 5. Physical shop — cashier's day (POS)

`GET /pos` — left: catalogue/search, right: cart + customer + totals + payment + sale source

1. **Find product** — search name/SKU/barcode/serial, scan barcode, quick picks, category shortcuts
2. **Build cart** — qty, serial picker modal with search + already-selected guard, price from DB
3. **Customer details (optional)** — name, phone, **Sale Source** (Walk-in/WhatsApp/Phone/Other) + **WhatsApp Enquiry ID** (link to catalogue lead)
4. **Recent WhatsApp enquiries panel** (NEW, when `whatsapp_enquiries` exists): last 15 leads, shows product_name, variant_label, condition_type, created_at, phone, product_url, Use in POS button → sets source=WhatsApp + enquiry ID, toast
5. **Discount** — amount, flat/% toggle, manager PIN required if discount >= threshold
6. **Payment** — cash/mpesa/card/bank, ref required if not cash, cash_received + change
7. **Complete sale** → `SaleService::create()` with channel=pos, sale_source, whatsapp_enquiry_id, device_id, client_ref, discount_pin, payment validation, stock transaction (bulk decrement, serialized units sold, sale_items with unit_cost, VAT), low-stock alert hook
8. **Receipt** — `/pos/receipt/{id}`, 80mm/58mm thermal print, PDF, new sale reset clears all fields incl. source
9. **Offline** — Service Worker + IndexedDB catalogue, queue when offline with client_ref+device_id, printable pending receipt, auto-sync every 30s or on online event, idempotent via UNIQUE(device_id, client_ref), serials resolved by number at sync, sale_source preserved

### Voiding — `POST /sales/{id}/void` (admin)
Only if completed/pending, blocked when returns exist, restores stock exactly, writes voided movements, status cancelled, audit log with reason

---

## 6. Online shop — Catalogue + WhatsApp (Current) + Guest Checkout (Preserved, Disabled)

**Default mode:** Catalogue + WhatsApp (online_checkout_enabled=0, mpesa_online_enabled=0, whatsapp_ordering_enabled=1)

### Catalogue journey
1. **Browse** — `/shop` home (hero carousel + featured + categories + promo with catalogue messaging), `/shop/products` with search (name/SKU), category chips, brand filter, sort (name, price_asc/desc, newest), active filters, load-more (12 per page)
2. **Product page** `/shop/product/{id}`:
   - Gallery with main + thumbs
   - Price: effectivePrice (variant override or base) gross + ex VAT; From price when variants exist
   - Condition badge (new=green, refurbished=blue, used=orange) + grade badge + serial tracked badge
   - Availability card: dot available, text Only X / In stock now / Out of stock, store pickup + delivery
   - **Variant selectors** (NEW): distinct RAM, storage, colour, condition_type, label; buttons with active state; selected-variant-summary live
   - Description + condition_notes + battery_notes
   - **Purchase panel:**
     - If checkoutEnabled=1 and stock>0: qty + Add to cart
     - Else if whatsappEnabled=1: **Order on WhatsApp** (in-stock, green #25D366 with WhatsApp SVG) or **Ask About Availability** (out-of-stock, orange #f59e0b) + Share button (navigator.share or clipboard)
     - Mobile sticky bar `#mobile-buy-bar`: price + Add to cart or WhatsApp
   - Specs: SKU (live), warranty, grade, tracking, availability
   - Related products (same category)
   - JSON: product-data (id, name, sku, sell_price, stock, condition_type, grade, url, shop_name, whatsapp_number), variant-data (full variants)
   - JS: findVariantByAttributes (ram/storage/colour/condition_type/variant_id), updateUI (price, sku, condition, summary, mobile price, stock), buildMessage (structured with product, variant, colour, condition, grade, price, sku, url), updateWhatsappLink (wa.me/{number}?text={encodeURIComponent}), trackWhatsapp (POST to whatsappEnquiryUrl with product_id, variant_id, product_name, variant_label, price_shown, product_url, source_page, condition_type, keepalive), share
3. **Cart** `/shop/cart`:
   - If checkoutEnabled=false: shows **Online checkout unavailable** page, catalogue mode messaging, Chat on WhatsApp button (wa.me/{number}?text=Hello...), Browse products, popular choices grid
   - If enabled: cart lines with live stock re-price via `/shop/api/cart?ids=...`, qty controls, remove, subtotal/VAT/delivery/total, staged checkout (Contact, Fulfilment, Payment, Review) with progress steps, draft saved in localStorage, delivery zone fee server-resolved, payment method cash/mpesa-now, checkout POST JSON with device_id+client_ref, error handling, restore cart, print/share order
4. **Order confirmation** `/shop/order/{id}` (session-gated): shows order, payment status panel with polling (polls `/shop/order/{id}/status` every 3s, 40 attempts), restore cart, print, share
5. **Tracking** `/shop/track`: number + phone (last 9 digits), rate-limited 10/10min/session, online orders only

### Guest checkout (preserved, disabled by flag)
- When `online_checkout_enabled=1`: cart → checkout → SaleService channel=online, auto_assign_serials, sale_source=online, status pending if mpesa-now else completed, device_id+client_ref dedup, email confirmation best-effort
- When `mpesa_online_enabled=0`: `checkout()` checks flag, voids sale if mpesa attempted, returns 403
- `apiCart()` returns 403 when checkout disabled

### WhatsApp ordering (primary)
- Number configurable via `whatsapp_sales_number`, normalized via `normalize_whatsapp_number()` (07→2547, 7→2547, 254 stays, 10-15 digits, rejects leading 0)
- Message built via `build_whatsapp_message()` with safe placeholder replacement, no sensitive data (no cost, profit, COGS, supplier, IMEI)
- URL: `https://wa.me/{number}?text={rawurlencode(message)}`
- No sale creation, no stock deduction on click
- OG metadata for rich preview: `og:title` = Product | Shop, `og:description` = variant summary + trimmed description, `og:image` = absolute URL primary image, `og:url` canonical, `og:type` product, `twitter:card` summary_large_image
- Lead tracking: `whatsapp_enquiries` table (product_id, variant_id, product_name, variant_label, price_shown, source_page, product_url, condition_type, customer_phone, customer_name, message, created_at)

---

## 7. Payments

### Cash
In-store + online pickup/delivery (when checkout enabled), completed immediately

### M-PESA
- **POS:** `mpesa_enabled` master, payment_method mpesa, ref required, cashier enters confirmation code
- **Online pay now (preserved, disabled):** sale pending + STK push via `MpesaService::stkPush(phone, amount, saleNumber, saleId)`; baseUrl sandbox/live, password base64(shortcode+passkey+timestamp), TransactionType paybill/till, PartyA/B normalized phone, CallBackURL tokenized, AccountReference alphanumeric max12; if push fails, stock voided; callback `/mpesa/callback?token=...` validates token, processCallback marks completed + receipt number, always acknowledges
- **Admin fallbacks:** Mark paid (manual receipt code), Re-send prompt (2min throttle), both admin-only, on sale detail page
- **Separation:** `mpesa_enabled` master, `mpesa_online_enabled` public; when online disabled, `ShopController::checkout()` blocks and voids

---

## 8. Sales management

`GET /sales` — search, channel, status, payment, **source** (Walk-in/WhatsApp/Phone/Other), date range, pagination; KPIs today_total, pending, online, total; sales list table with Order, Date, Customer, Channel, **Source** (green WhatsApp badge), Payment, Items, Total, Status, Action; WA enquiry badge; active filters with clear; pager

`GET /sales/{id}` — identity bar with status, channel, source, offline, WA id, time; payment recovery card for pending mpesa (timeline, Safaricom response, facts, admin actions); cancelled banner with void audit; document with items (product_name, sku, serial, warranty), totals (subtotal, discount, VAT, delivery_fee, total); sidebar: Order information (payment, fulfilment, source, WA id, customer, phone, email, delivery address, recorded by), Receipt and actions (PDF, 80mm, 58mm, Start return, Void), Returns list

Export CSV filter-preserving includes Source + WA Enquiry ID

PDF `/sales/{id}/pdf` (built-in writer) + thermal `/sales/{id}/print?size=80/58` (standalone monospace, @page size, toolbar with size toggles + print)

Void admin only, restores stock, writes voided movements, blocked when returns exist

---

## 9. Returns & warranty

`GET /returns/new` → pick completed sale → pick items (only not already returned, only completed sales)

1. Create return — items+qty+refund+reason (warranty, wrong item, defective…), return number
2. Approve/reject (admin) — decision_note, stock_outcome restock/damaged/missing, writes movements, refunds
3. Ghost returns blocked (nothing left to return), returns against non-completed sales blocked, refund capped at line total
4. Unit status guards: sold units can't be deleted or status-changed outside returns/void

---

## 10. Expenses, dashboard, reports

- **Expenses** `/expenses`: add, list, delete (admin), receipt_file, is_recurring, deleted_at
- **Dashboard** `/`: KPIs revenue, orders, AOV, profit, refunds, expenses, net, channels, sale sources, WhatsApp enquiries, revenue chart (daily/weekly aggregation), top products, low-stock
- **Reports** `/reports`: date range, period presets Today/Last7/ThisMonth, KPIs net sales excl VAT, orders, gross profit, net result, comparison % vs prior period, **WhatsApp enquiries KPI** (total, converted, walk-in vs WhatsApp, shop mode), revenue trend chart, Channels + **By source** hbar (WhatsApp green), top products, low-stock exceptions, export CSV (channels+ sources+ WhatsApp)
- **VAT report** `/reports/vat`: output VAT (Σ tax_amount completed sales), VAT on returns (refund × effective rate), input VAT (GRN costs × rate), daily breakdown, sales/purchases/returns detail, CSV
- **Purchases by supplier** `/reports/purchases`: GRNs grouped by supplier (saved id + free-text), deliveries, net, est input VAT, share %, last delivery, filter by supplier, drill-down GRN items, trend, CSV

---

## 11. Z-report / close-of-day

`GET /reports/z`: date + user filter (cashiers own day only), summary: sales count/total, cash/mpesa/card/bank breakdown, discounts, voids, refunds, expected cash; pending payments/returns count; close form with counted_cash validation, checklist sales_reviewed/offline_clear/payments_reviewed, notes; history list; closed snapshot per user+date upsert, survives edits

---

## 12. Security & hardening

Installer guard, login lockout (Throttle email+IP 5→15min), logout POST+CSRF, demo creds hidden prod, security headers CSP/HSTS, Options -Indexes, dotfile deny, app/schema/tools/storage deny, router-dev dev-only, timezone Africa/Nairobi via APP_TIMEZONE, session hardening strict+cookie-only+regenerate, activity log, UNIQUE(device_id, client_ref) dedup, sequence retry on collision, return integrity qty/amount keyed to sale item + refund cap, sales filter offline, product safety serial toggle blocked with history, offline subfolder support base path, CSRF on all state-changing incl JSON POS/shop/sync, bcrypt cost-12 auto-rehash, prepared statements, output escaping e()/esc(), role gates router-level + self-protection staff

---

## 13. Catalogue + WhatsApp mode — technical details

### Flags
- `online_checkout_enabled` (0/1), `mpesa_online_enabled` (0/1), `whatsapp_ordering_enabled` (1 default), `whatsapp_sales_number`, `whatsapp_message_template` in `shop_settings`, seeded idempotent, migrated via `Schema::migrateColumns()` + `seedBaseline()`

### WhatsApp number
- Input: 07XXXXXXXX (10 digits), 01XXXXXXXX, 7XXXXXXXX (9), 1XXXXXXXX, 2547XXXXXXXX (12), +2547XXXXXXXX (13 with plus)
- `normalize_whatsapp_number()` strips non-digits, handles Kenyan conversion, validates 10-15 digits, rejects leading 0 otherwise, returns digits without plus for wa.me
- `whatsapp_display_number()` returns +digits for UI
- `whatsapp_number()` returns normalized sales number or fallback shop_phone normalized
- `whatsapp_link(message, number)` returns wa.me/{number}?text={rawurlencode}

### Message
- `build_whatsapp_message(product, variant, productUrl, customTemplate)`:
  - Effective price: variant price_override if >0 else product sell_price, gross_of + money()
  - Variant string: RAM + storage + colour + label
  - Placeholders safe replacement, ensures productUrl present
  - Default template: Hello {shop}, I'm interested, Product, Variant, Colour, Condition, Grade, Price shown, Product Code, Product page, Is available?
  - No cost, profit, COGS, supplier, IMEI, serial, internal IDs
- JS `buildMessage()` mirrors PHP, uses `displayVariantLabel()`, `money()`, `gross()`

### OG metadata
- `ShopController::product()` builds ogImage from primary image `url('uploads/p/...')` absolute, ogUrl canonical product URL, ogTitle product | shop, ogDescription variant summary + trimmed description, ogType product
- `layouts/shop.php` emits meta tags if ogTitle/Description/Image/Url set, site_name, twitter:card, canonical

### Variant system
- Table `product_variants`: id, product_id, sku, label, ram, storage, colour, condition_type, grade, price_override, is_active, sort_order, created_at
- Model `ProductVariant`: allForProduct, find, findForProduct, create, update, delete, displayLabel, effectivePrice, distinctValues
- `Product::variants(productId, activeOnly)`, `fromPrice()` min of base + overrides
- `inventory_units.variant_id` nullable for future variant-level serial tracking
- Product detail JS: distinct attribute values, state {ram, storage, colour, condition_type, variant_id}, findVariantByAttributes filtering, updateUI live

### Lead tracking
- Table `whatsapp_enquiries`: product_id, variant_id, product_name, variant_label, price_shown, source_page, product_url, condition_type, customer_phone, customer_name, message, created_at
- `ensureWhatsappEnquiries()` creates + migrates extra columns
- `ShopController::whatsappEnquiry()` POST JSON, CSRF, inserts with columnExists guards, best-effort, returns ok
- JS `trackWhatsapp()` keepalive fetch

### Sale source
- `sales.sale_source` VARCHAR(20) DEFAULT walk-in (walk-in/whatsapp/phone/other/online), `whatsapp_enquiry_id` INT NULL
- `SaleService::normalizeOpts()` validates allowed sources
- `Sale::buildWhere()` filters by source when column exists
- POS checkout payload includes sale_source + whatsapp_enquiry_id, draft persists
- Reports metrics groups by COALESCE sale_source, counts WhatsApp enquiries total + converted (via whatsapp_enquiry_id or sale_source=whatsapp)

### M-Pesa preservation
- DO NOT DELETE: MpesaService.php, mpesa_transactions table, callback route, migrations
- Master `mpesa_enabled` for POS, `mpesa_online_enabled` for public; `is_mpesa_online_enabled()` checks both
- `ShopController::checkout()` checks `is_mpesa_online_enabled()`, voids if attempted when disabled
- `SettingsController` M-PESA tab explains master vs online distinction

### POS source of truth
- No separate sales engine for WhatsApp — POS creates all sales
- No auto sale/stock deduction on WhatsApp click
- No fake image attachment — uses OG metadata + product URL

### SEO / Accessibility / Security
- Preserves title, meta description, canonical, URLs, categories, internal linking
- ARIA: variant groups role=group, aria-label, selected-variant-summary aria-live, gallery thumbs aria-label, skip-link, nav aria-label
- CSRF on whatsapp-enquiry, rate limiting on track, prepared statements, output escaping

---

## 14. Build roadmap

| # | Piece | Status |
|---| --- | --- |
| 1 | Foundation | ✅ |
| 2 | Inventory | ✅ |
| 3 | POS | ✅ |
| 4 | Online shop (guest checkout) | ✅ preserved, disabled |
| 5 | Offline POS | ✅ |
| 6 | Orders & reports | ✅ |
| 7 | Polish | ✅ |
| 8 | Strengthening | ✅ |
| 9 | Operations | ✅ |
| 10 | Integration audit | ✅ |
| 11 | VAT + barcode labels | ✅ |
| 12 | Purchases by supplier | ✅ |
| 13 | Catalogue + WhatsApp mode | ✅ done |
