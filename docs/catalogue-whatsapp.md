# Catalogue + WhatsApp Mode — Technical Guide

> Current default: `online_checkout_enabled=0`, `mpesa_online_enabled=0`, `whatsapp_ordering_enabled=1`. Public shop is a catalogue; sales happen via POS.

---

## 1. Why Catalogue + WhatsApp

- Reduce fraud/complexity of public checkout
- Keep professional online presence
- Customers already use WhatsApp → lower friction
- POS remains source of truth for inventory, IMEI, profit

---

## 2. Feature Flags (shop_settings)

| Key | Default | Meaning |
|-----|---------|---------|
| `online_checkout_enabled` | 0 | Public cart/checkout OFF = catalogue mode |
| `mpesa_online_enabled` | 0 | Public M-Pesa STK OFF (POS M-Pesa still via `mpesa_enabled`) |
| `whatsapp_ordering_enabled` | 1 | WhatsApp CTA ON |
| `whatsapp_sales_number` | '' | Sales number, any Kenyan format |
| `whatsapp_message_template` | '' | Optional custom template |

Seeded idempotently in `Schema::seedBaseline()` per key. Admin UI: Settings → WhatsApp & Online tab.

Helpers (`app/helpers/functions.php`):
- `is_online_checkout_enabled(): bool` → `shop_settings.online_checkout_enabled == 1`
- `is_mpesa_online_enabled(): bool` → `mpesa_enabled ==1 && mpesa_online_enabled==1`
- `is_whatsapp_ordering_enabled(): bool` → defaults 1 when key missing
- Flags checked server-side in `ShopController::cart()`, `apiCart()`, `checkout()`

---

## 3. WhatsApp Number Normalization

Function: `normalize_whatsapp_number(string $raw): string`

Handles:
- `0712345678` (10 digits starting 07) → `254712345678`
- `0112345678` (10 digits 01) → `254112345678`
- `712345678` (9 digits 7...) → `254712345678`
- `112345678` (9 digits 1...) → `254112345678`
- `254712345678` (12 digits 2547) → `254712345678`
- `+254712345678` → `254712345678` (strip +)
- Spaces, dashes, parentheses stripped first

Validation:
- After normalization, must be 10-15 digits
- If original started with `0` but not 07/01 pattern → reject (invalid)
- If original started with `+` but not `+254` → reject if not convertible
- Empty → `''`

Display: `whatsapp_display_number()` → `+254712345678` (adds + for UI)

Link: `whatsapp_link(message, number)` → `https://wa.me/{digits}?text={rawurlencode(message)}`

Fallback: `whatsapp_number()` → normalized `whatsapp_sales_number` or normalized `shop_phone` or ''.

SettingsController validation:
```php
$waNorm = normalize_whatsapp_number($waRaw);
if ($waRaw !== '' && $waNorm === '') { error: Invalid Kenyan number }
```

Admin UI shows live preview: `wa.me/{normalized}`

---

## 4. WhatsApp Message Generation

Function: `build_whatsapp_message(array $product, ?array $variant, string $productUrl, ?string $customTemplate): string`

Default template (when custom empty):
```
Hello {shop_name},

I'm interested in:
Product: {product_name}
Variant: {variant}
Colour: {colour}
Condition: {condition}
Grade: {grade}
Price shown: {price}
Product Code: {sku}
Product page: {product_url}

Is this available?
```

Placeholders:
- `{product_name}` → product name
- `{variant}` → variant display label (e.g. "256GB / 8GB / Black")
- `{ram}`, `{storage}`, `{colour}`/`{color}`, `{condition}`, `{grade}`
- `{price}` → KSh formatted (e.g. KSh 45,000)
- `{sku}` → variant SKU or product SKU
- `{product_url}` → absolute canonical URL (always ensured present)
- `{shop_name}` → `shop_name` setting

Out-of-stock variant:
```
Hello {shop},
It is currently shown as out of stock but I'm interested in:
Product: ...
...
Could you check availability?
```

Security: NEVER exposes cost price, buying price, profit, COGS, supplier, IMEI, serial, internal notes. Only public fields.

JS side (`shop/product.php`):
- `buildMessage()` mirrors PHP logic with `encodeURIComponent`
- Handles special chars, ampersands, line breaks `%0A`
- Uses selected variant state

---

## 5. Product Detail — Variant-Aware Flow

Backend:
- `ProductVariant` model: `allForProduct(productId)`, `find(id)`, `displayLabel()`, `effectivePrice(product)`, `distinctValues(field)`
- `Product::variants()` → via ProductVariant when table exists
- `Product::fromPrice()` → min(base price, variant overrides)
- `Product::conditionLabel()` → New/Used/Refurbished

Frontend selectors (`shop/product.php`):
- Groups: Storage, RAM, Colour, Condition, Options (label fallback)
- Distinct values from variants: e.g. `ProductVariant::distinctValues('storage')`
- State: `{ram, storage, colour, condition_type, variant_id}`
- `findVariantByAttributes()` filters variants array
- `updateUI()` → price (gross + ex VAT via `money()`), SKU, condition badge, grade badge, availability, summary, mobile price, stock
- `updateWhatsappLink()` → rebuilds wa.me URL with selected variant
- Mobile sticky bar `#mobile-buy-bar`: price + WhatsApp CTA, safe-area-inset

Out-of-stock:
- `stock <=0` → button `.btn-out-of-stock` orange #f59e0b, text "Ask About Availability"
- Message template switches to out-of-stock variant

No auto sale: click does NOT create sale, deduct stock, trigger M-Pesa.

---

## 6. Open Graph & SEO

ShopController::product():
- `ogTitle` = Product name + variant label if selected | Shop name
- `ogDescription` = variant summary + trimmed description 160 chars or "Available at {shop}"
- `ogImage` = absolute HTTPS URL primary image: `url('uploads/p/{filename}')` → uses `config('app.base_url')` not localhost
- `ogUrl` = canonical product URL: `url('shop/product/'.$id)`
- `ogType` = `product`
- `ogSiteName` = shop_name

Layout `layouts/shop.php`:
```html
<meta property="og:title" content="...">
<meta property="og:description" content="...">
<meta property="og:image" content="https://.../uploads/p/...">
<meta property="og:url" content="https://.../shop/product/123">
<meta property="og:type" content="product">
<meta property="og:site_name" content="...">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="...">
<link rel="canonical" href="...">
```

Why: WhatsApp link preview shows product image + title without fake image attachment (against WhatsApp ToS).

Verification: view source on product page, check OG tags, test with https://developers.facebook.com/tools/debug/ or WhatsApp itself.

---

## 7. Checkout Disable & Guards

UI hiding:
- `layouts/shop.php`: if `!checkoutEnabled` → hide cart link, show WhatsApp nav link `wa.me/{number}?text=Hello...` or `tel:`
- `_product-card.php`: From price when variants, condition badge, "View details" when catalogue mode
- `product.php`: hide Add to Cart, show WhatsApp CTA primary
- `cart.php`: if disabled → unavailable page: "Online checkout unavailable", Chat on WhatsApp button, Browse catalogue, popular choices
- `home.php`, `browse.php`: catalogue messaging, hero CTAs to product/category not checkout
- `shop.js`: checks `KC_SHOP_CONFIG.checkoutEnabled`, disables cart rendering, mobile add triggers WhatsApp

Server-side enforcement (must not rely on CSS/JS):
- `ShopController::cart()`: if `!is_online_checkout_enabled()` → render unavailable page with `checkoutEnabled=false`, `whatsappEnabled`, recommendations, 200 but no checkout
- `apiCart()`: if disabled → `json 403 {error: 'Online checkout unavailable'}`
- `checkout()`: if disabled → `json 403 {error: 'Please contact via WhatsApp...'}`; also checks `mpesa_online_enabled`, if mpesa attempted when disabled → void sale + 403
- `checkoutPaymentIntent()`: checks `mpesa_online_enabled`
- M-Pesa public STK blocked, POS M-Pesa preserved (channel distinction `pos` vs `online`)

Graceful failure: `/shop/cart` → unavailable page, `/shop/api/cart` → 403 JSON, `/shop/checkout` POST → 403 JSON

---

## 8. Cart Handling

- Cart is tightly coupled to online checkout only
- When disabled, hidden from nav, but localStorage code preserved for future
- `shop.js` preserves `renderCartPage()`, `addToCart()`, badge update, but gated by `shopCfg.checkoutEnabled`
- No cart data loss — just not exposed

---

## 9. Lead Tracking

Table `whatsapp_enquiries` (created via `Schema::ensureWhatsappEnquiries()` with extra columns migration if exists):

| Column | Type | Purpose |
|--------|------|---------|
| id | INT PK | |
| product_id | INT | product clicked |
| variant_id | INT NULL | selected variant |
| product_name | VARCHAR | snapshot |
| variant_label | VARCHAR NULL | display label |
| price_shown | VARCHAR NULL | price at time |
| product_url | TEXT NULL | canonical URL for POS reference |
| condition_type | VARCHAR NULL | new/used/refurbished |
| source_page | VARCHAR NULL | home/browse/product |
| customer_phone | VARCHAR NULL | future optional |
| customer_name | VARCHAR NULL | future optional |
| message | TEXT NULL | full message sent |
| created_at | DATETIME | |

Route: `POST /shop/whatsapp-enquiry` → `ShopController::whatsappEnquiry()`

- CSRF protected (X-CSRF-Token header, `csrf_field()` in layout)
- JSON payload: product_id, variant_id, product_name, variant_label, price_shown, product_url, condition_type, source_page
- `columnExists` guards for extra columns (backward compat)
- Best-effort: failure does NOT block WhatsApp open
- No personal data beyond product context (no cost, IMEI, etc.)
- JS: `trackWhatsapp()` via `fetch` with `keepalive: true` so it survives page unload when opening wa.me

POS integration:
- `PosController::index()` loads last 15 enquiries for "Recent WhatsApp enquiries" panel
- Panel shows: product_name, variant_label, condition_type, created_at, phone if any, product_url link, Use in POS button → sets `sale_source=WhatsApp`, `whatsapp_enquiry_id=id`, toast
- Draft persistence includes source + WA id
- Review modal shows source

---

## 10. Sale Source Tracking

`sales` table alters (via `Schema::migrateColumns()`):

- `sale_source` VARCHAR(20) DEFAULT 'walk-in' (walk-in/whatsapp/phone/other/online)
- `whatsapp_enquiry_id` INT NULL (FK optional to whatsapp_enquiries.id)

SaleService:
- `normalizeOpts()` validates allowed sources: walk-in, whatsapp, phone, other, online (case-insensitive, defaults walk-in)
- `insertData` includes source + WA id with `columnExists` guards

POS UI (`pos/index.php`):
- Dropdown: Walk-in (default), WhatsApp, Phone, Other
- Number input: WhatsApp Enquiry ID (optional)
- Recent enquiries panel with Use button
- Payload: `{source, whatsapp_enquiry_id}` + offline payload same
- Review modal shows source badge

Sales list/detail:
- `sales/index.php`: source filter dropdown, source column with badge (green WhatsApp #25D366, blue Phone, gray Other), WA id badge
- `sales/show.php`: source badge + WA id in identity bar + Order information

Reports:
- `ReportController::metrics()` groups by `COALESCE(sale_source,'walk-in')`, SUM revenue + COUNT when column exists
- WhatsApp enquiries: total COUNT, converted COUNT where `whatsapp_enquiry_id IS NOT NULL` or `sale_source='whatsapp'`
- `reports/index.php`: KPIs — WhatsApp total, Converted, Walk-in vs WhatsApp, Shop mode; Channels + By source hbar (WhatsApp green)
- Export CSV includes channels + sources + WhatsApp

Flow preserved:
1. Browse catalogue → WhatsApp → deal → POS sale source=WhatsApp → inventory deduction, IMEI, COGS, profit, receipt, reports

---

## 11. M-Pesa Preservation

DO NOT DELETE:
- `app/services/MpesaService.php` (OAuth, STK push, callback, token caching)
- `app/models/MpesaService.php` (if exists wrapper)
- `mpesa_transactions` table, `sales.mpesa_*` columns
- Callback route `POST /mpesa/callback?token=...` in `public_html/index.php`
- Migrations, admin fallbacks (mark-paid, retry)

Disabled for public:
- `mpesa_online_enabled=0` blocks `checkout()` and `checkoutPaymentIntent()` server-side
- UI hides M-Pesa Pay Now when flag OFF
- Returns 403 "M-PESA online payments unavailable" if attempted, voids sale if already created

Preserved for POS:
- POS M-Pesa uses master `mpesa_enabled` flag, channel `pos`
- SaleService POS path still calls MpesaService when payment_method=mpesa
- Historical transactions remain queryable

Re-activation: set `mpesa_online_enabled=1` + `mpesa_enabled=1` + configure keys in Settings → M-PESA.

---

## 12. Navigation & Hero

- Nav: Home, All products, Categories (5), New Arrivals when catalogue mode, no Cart/Checkout when disabled, WhatsApp/Contact button wa.me
- Hero carousel preserved: full-screen, autoplay 6500ms, pause/play, dots with number+bar, arrows, counter, keyboard arrows, touch swipe, reduced-motion, inert inactive slides, transparent nav `nav-over-hero` with `is-scrolled` on scroll>40
- CTAs → product page, category, shop (NOT checkout)

---

## 13. Mobile & Accessibility

Mobile:
- Variant selectors: flex-wrap, min-height 42px, flex:1 0 auto, 44px touch target
- Sticky `#mobile-buy-bar`: bottom fixed, safe-area-inset-bottom, price + WhatsApp CTA, not covering content
- Hero 88vh on mobile

Accessibility:
- Semantics: <a> for WhatsApp (href wa.me), <button> for variant selectors
- Keyboard: variant options focusable, gallery thumbs, nav toggle aria-expanded, skip-link, main tabindex -1
- Focus states: :hover + :focus-visible + .active with box-shadow
- ARIA: aria-label WhatsApp, Share, gallery thumbs, variant groups role=group, selected-variant-summary aria-live polite, availability live
- Contrast: green #25D366 on white passes, text #1d1d1f on #f5f5f7
- Text "Order on WhatsApp" not icon-only

---

## 14. Security Checklist

- CSRF on all state-changing incl. `POST shop/whatsapp-enquiry` (X-CSRF-Token), POS checkout, sync, settings, sales void, M-Pesa
- Auth: admin-only settings, WhatsApp number edit admin-only
- SQL: prepared statements via Database::fetch/fetchAll/insert/update
- XSS: e() server-rendered, esc() JS-built HTML
- File upload: safe streaming uploads/p/{filename}, hero images, path-traversal safe
- Output encoding: e() for display, rawurlencode for wa.me URL, encodeURIComponent JS
- Redirects safe via url() + query_string()
- Feature flag enforcement server-side (not CSS)
- No sensitive data in WhatsApp message (cost, profit, COGS, supplier, IMEI, serial)
- No IMEI/serial in public enquiry table

---

## 15. Future Reactivation

To re-enable online checkout:
1. Settings → WhatsApp & Online → check "Online Checkout Enabled" + "Online M-PESA Payments Enabled" → Save
2. Or DB: `UPDATE shop_settings SET value='1' WHERE key IN ('online_checkout_enabled','mpesa_online_enabled')`
3. Existing infra becomes live: cart, apiCart, checkout, delivery zones server-resolved, M-Pesa STK + callback + fallbacks, email confirmations, order tracking
4. Test: cart → checkout → delivery → M-Pesa sandbox → order tracking → Z-report
5. No file reverts needed — flags control behaviour

POS remains source of truth regardless of mode.

---

## 16. Troubleshooting

| Symptom | Check |
|---------|-------|
| WhatsApp button not showing | Settings → WhatsApp & Online → WhatsApp Ordering Enabled=1, number set, product active |
| wa.me link wrong number | Settings number normalization preview, check `normalize_whatsapp_number()` handles 07→254, DB `shop_settings` value |
| Message missing variant | JS state {ram,storage,colour,condition_type,variant_id}, variant-data JSON, findVariantByAttributes |
| Cart still accessible | `is_online_checkout_enabled()` returns false? `shop_settings` flag 0, ShopController guards, shop.js checkoutEnabled |
| /shop/api/cart not blocked | Check `apiCart()` flag check returns 403 |
| OG image not showing in WhatsApp | View source og:image absolute HTTPS via `url()`, file exists uploads/p/, base_url https, no localhost |
| whatsapp_enquiries table missing | `Schema::ensureWhatsappEnquiries()` called in bootstrap, check DB |
| sale_source column missing | `Schema::migrateColumns()` adds it, check sales table |
| Reports missing WhatsApp KPIs | Check whatsapp_enquiries exists, sales.sale_source exists, date range includes data |
| POS source not saving | Check pos/index.php payload includes source + WA id, SaleService normalizeOpts, columnExists guards |
| M-Pesa online still works | Check mpesa_online_enabled=0, checkout() mpesa check, void on attempt |

---

## 17. File Reference

- `app/core/Schema.php` — ensureProductVariants(), ensureWhatsappEnquiries() + extra columns migration, migrateColumns sale_source, whatsapp_enquiry_id, condition fields, variant_id, seedBaseline flags
- `app/models/ProductVariant.php` — NEW variant model
- `app/models/Product.php` — variants(), fromPrice(), conditionLabel()
- `app/models/Sale.php` — buildWhere source filter
- `app/models/SaleService.php` — normalizeOpts sale_source, insertData guards
- `app/controllers/ShopController.php` — home/browse/product with variants + OG, cart unavailable, apiCart 403, checkout 403 + mpesa_online check, whatsappEnquiry tracking
- `app/controllers/SettingsController.php` — whatsapp_sales_number validation, flags, template, tab-aware redirect
- `app/controllers/PosController.php` — recent enquiries, checkout with source + WA id, sync
- `app/controllers/SaleController.php` — source filter, export, show source badge
- `app/controllers/ReportController.php` — saleSources grouping, whatsappEnquiries total/converted, export
- `app/helpers/functions.php` — flags, normalize_whatsapp_number, whatsapp_display_number, whatsapp_number, whatsapp_link, build_whatsapp_message
- `app/views/layouts/shop.php` — OG meta, conditional cart vs WhatsApp nav, KC_SHOP_CONFIG flags
- `app/views/shop/product.php` — variant selectors, live price/SKU/condition, WhatsApp CTA, mobile sticky, JSON data, JS findVariantByAttributes/updateUI/buildMessage/trackWhatsapp
- `app/views/shop/_product-card.php` — From price, condition badge, View details
- `app/views/shop/home.php`, `browse.php`, `cart.php` — flags, catalogue messaging, unavailable page
- `app/views/settings/index.php` — WhatsApp & Online tab
- `app/views/pos/index.php` — sale_source selector + WA id + recent enquiries panel
- `app/views/sales/index.php`, `show.php` — source filter + badge
- `app/views/reports/index.php` — KPIs + By source hbar
- `public_html/assets/css/shop.css` — .btn-whatsapp, .btn-out-of-stock, .whatsapp-nav-link, variant selectors
- `public_html/assets/js/shop.js` — checkoutEnabled flag, conditional cart, WhatsApp tracking, hero carousel
- `public_html/index.php` — POST shop/whatsapp-enquiry route

---

*This doc reflects implemented code as of 2026-09-17. All paths verified via grep.*
