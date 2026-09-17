# KHAMIS COMPUTERS 2.0 — Catalogue + WhatsApp Mode — IMPLEMENTED

This document was the breakdown; now it reflects **completed implementation** as of 2026-09-17.

Master task: Convert public online shop from e-commerce checkout to Catalogue + WhatsApp Enquiry mode while PRESERVING M-Pesa and online order infrastructure for future activation.

---

### 0. CORE PRINCIPLE: DISABLE, DON'T DELETE — ✅ DONE

- All M-Pesa files, callbacks, tables (`mpesa_transactions`), migrations preserved
- Cart, checkout, online order code preserved but disabled via flags
- POS remains source of truth
- No CSS-only hiding — server-side 403 guards in ShopController

---

### SECTION A — AUDIT & FOUNDATION (Phase 1) — ✅ DONE

- Routes audited: shop, checkout, mpesa callback, POS, delivery zones, hero slides
- ShopController: home, browse, product, cart, checkout, mpesaCallback, track, orderStatus, whatsappEnquiry
- Product model: stock logic, images, variants(), fromPrice(), conditionLabel()
- SaleService: single engine POS + online + sale_source + whatsapp_enquiry_id with columnExists guards
- MpesaService: OAuth, STK push (phone, amount, saleNumber, saleId), callback processing, token caching
- Settings: shop_settings key/value, VAT, mail, M-Pesa, delivery zones, new flags online_checkout_enabled, mpesa_online_enabled, whatsapp_ordering_enabled, whatsapp_sales_number, whatsapp_message_template
- Schema: tables + migrations + seeding + ensureProductVariants + ensureWhatsappEnquiries + sale_source + condition fields + variant_id
- Shop views: layout with OG, home, browse, product variant-aware + WhatsApp CTA, cart unavailable, _product-card From price
- JS: shop.js respects checkoutEnabled, mobile WhatsApp trigger, gallery, load-more, hero carousel + transparent nav, WhatsApp tracking
- POS views: sale_source selector + WhatsApp enquiry ID + recent enquiries panel
- Reports: sales by source, WhatsApp enquiries total/converted, VAT, purchases, Z-report

---

### SECTION B — FEATURE FLAGS & SETTINGS INFRA (Phase 2) — ✅ DONE

Flags in `shop_settings`:
- `online_checkout_enabled` = 0 (OFF, catalogue mode)
- `mpesa_online_enabled` = 0 (OFF for public, POS still ON via mpesa_enabled)
- `whatsapp_ordering_enabled` = 1 (ON)
- `whatsapp_sales_number` = '' (configurable, Kenyan normalization)
- `whatsapp_message_template` = '' (optional custom template)

Helpers in `helpers/functions.php`:
- `is_online_checkout_enabled(): bool`
- `is_mpesa_online_enabled(): bool` (checks both mpesa_enabled + mpesa_online_enabled)
- `is_whatsapp_ordering_enabled(): bool` (default 1)
- `normalize_whatsapp_number(string $raw): string` → 254... digits, handles 07XXXXXXXX (10), 01XXXXXXXX, 7XXXXXXXX (9), 1XXXXXXXX, 2547XXXXXXXX (12), +2547XXXXXXXX, validates 10-15 digits, rejects leading 0 otherwise
- `whatsapp_display_number(string $raw): string` → +254...
- `whatsapp_number(): string` → normalized sales number or fallback shop_phone normalized
- `whatsapp_link(string $message, ?string $number): string` → wa.me/{number}?text={rawurlencode}
- `build_whatsapp_message(array $product, ?array $variant, string $productUrl, ?string $customTemplate): string` → safe placeholder replacement, ensures URL present, no sensitive data

Admin UI: Settings → WhatsApp & Online tab (new) with current mode display, checkboxes, number input with normalized hint wa.me/..., template textarea, how-it-works card, tab-aware redirect via active_tab hidden input

Security: admin-only, CSRF, normalization validation in SettingsController (invalid → error)

---

### SECTION C — DATABASE MIGRATIONS — ✅ DONE

**New tables (IF NOT EXISTS):**
- `product_variants`: id, product_id, sku, label, ram, storage, colour, condition_type, grade, price_override, is_active, sort_order, created_at; allows future variant-level pricing; model ProductVariant
- `whatsapp_enquiries`: id, product_id, variant_id, product_name, variant_label, price_shown, source_page, product_url, condition_type, customer_phone, customer_name, message, created_at; lightweight lead tracking; ensureWhatsappEnquiries() creates + migrates extra columns via ALTER TABLE when table exists

**Alter sales:**
- `sale_source` VARCHAR(20) DEFAULT walk-in (walk-in/whatsapp/phone/other/online)
- `whatsapp_enquiry_id` INT NULL (optional link to enquiry)

**Alter products:**
- `condition_type` VARCHAR(20) NULL (new/used/refurbished)
- `condition_grade` VARCHAR(40) NULL
- `condition_notes` TEXT NULL
- `battery_notes` VARCHAR(255) NULL
- `warranty_months` INT NULL, `image` VARCHAR(255) NULL (already existed but ensured)

**Alter inventory_units:**
- `variant_id` INT NULL (future variant-level serial tracking)

**Preserved:** mpesa_transactions, sales, sale_items, delivery_zones, etc. — DO NOT DELETE

Implementation: `Schema::seedBaseline()` seeds flags idempotent per key, `ensureProductVariants()`, `ensureWhatsappEnquiries()` with extra columns migration, `migrateColumns()` adds sale_source, whatsapp_enquiry_id, condition fields, variant_id, etc.

---

### SECTION D — PRODUCT VARIANTS SYSTEM — ✅ DONE

**Backend:**
- `ProductVariant.php`: allForProduct(productId, activeOnly), find(id), findForProduct, create, update, delete, displayLabel (label or ram/storage/colour/condition), effectivePrice (price_override or product sell_price), distinctValues(field) for selector UI
- `Product.php`: variants(productId, activeOnly) via ProductVariant when table exists, fromPrice() min of base + overrides, conditionLabel(), stockOf(), stock()
- Future: variant uses parent product stock; inventory_units.variant_id for future per-variant serials

**Frontend:**
- Product detail selectors: Storage, RAM, Colour, Condition, Options (label fallback)
- Selecting variant updates: price (gross + ex VAT), SKU, condition badge, grade badge, availability, variant summary, mobile price, stock display, WhatsApp message
- WhatsApp enquiry MUST use selected variant — implemented via JS state {ram, storage, colour, condition_type, variant_id}, findVariantByAttributes filtering

---

### SECTION E — WHATSAPP CORE IMPLEMENTATION — ✅ DONE

1. **Number Config:** setting `whatsapp_sales_number`, normalization 0712345678→254712345678, handles +254, 254, 07, 01, 7, 1; `whatsapp_number()` returns digits for wa.me, `whatsapp_display_number()` returns +...; validation in SettingsController rejects invalid
2. **Message Generation:** placeholders {product_name}, {variant}, {ram}, {storage}, {colour}, {color}, {condition}, {grade}, {price}, {sku}, {product_url}, {shop_name}; default template: Hello {shop}, I'm interested, Product, Variant, Colour, Condition, Grade, Price shown, Product Code, Product page, Is available?; `build_whatsapp_message()` safe replacement, no cost/profit/COGS/supplier/IMEI/serial
3. **URL Generation:** `https://wa.me/{number}?text={encoded}` via rawurlencode PHP / encodeURIComponent JS, handles special chars, ampersands, line breaks %0A
4. **Button:** primary CTA Order on WhatsApp with WhatsApp SVG icon, green #25D366, .btn-whatsapp, fits design system; secondary Share Product (navigator.share or clipboard), mobile sticky bar; nav WhatsApp link when checkout OFF
5. **Out-of-stock:** stock<=0 → Ask About Availability orange #f59e0b, different message "It is currently shown as out of stock..."
6. **No sale creation:** click does NOT create sale, deduct stock, trigger M-Pesa — only optional tracking fetch
7. **Lead tracking:** POST to `/shop/whatsapp-enquiry` (CSRF, keepalive, JSON) → `whatsapp_enquiries` insert with columnExists guards, best-effort, no personal data beyond product/variant/price/url

---

### SECTION F — OPEN GRAPH & SEO — ✅ DONE

- og:title = Product | Shop
- og:description = variant summary + trimmed description (160 chars) or Available at Shop
- og:image = absolute HTTPS URL primary product image via url('uploads/p/...') (base_url absolute)
- og:url = canonical product URL
- og:type = product (or website if not product)
- og:site_name = shop_name
- twitter:card = summary_large_image, twitter:image = og:image
- canonical link = ogUrl
- Implementation: ShopController::product() builds ogTitle, ogDescription, ogImage, ogUrl, passes to view; layouts/shop.php emits meta tags if set; ensures no localhost — uses config base_url
- Preserves title, meta description, canonical, product URLs, categories, internal linking

---

### SECTION G — CHECKOUT DISABLE & SECURITY ENFORCEMENT — ✅ DONE

**UI hiding:**
- shop layout: hide cart link if OFF, show WhatsApp nav link wa.me with generic Hello message or Contact tel:
- _product-card: From price, condition badge, View details when catalogue mode (no Add to Cart)
- product.php: hide Add to Cart, show WhatsApp CTA primary, Share secondary
- cart.php: if disabled, show Online checkout unavailable page with catalogue messaging, Chat on WhatsApp button, Browse catalogue, popular choices
- home.php, browse.php: no Buy Now → Checkout, promo messaging Catalogue + WhatsApp, hero CTAs to product/category

**Server-side enforcement:**
- ShopController::cart(): if !is_online_checkout_enabled() → renders unavailable page with checkoutEnabled=false, whatsappEnabled, recommendations
- apiCart(): if disabled → json 403 Online checkout unavailable
- checkout(): if disabled → json 403 Please contact via WhatsApp; also checks mpesa_online_enabled, voids sale if mpesa attempted when disabled, 403 M-PESA online unavailable
- MpesaService: public online STK push blocked via is_mpesa_online_enabled() check in checkoutPaymentIntent + checkout; POS M-Pesa remains operational (channel distinction pos vs online, master mpesa_enabled)
- SaleService POS still works even if online M-Pesa disabled

Graceful failure: /shop/cart → unavailable page, /shop/checkout POST → 403 JSON

---

### SECTION H — CART HANDLING — ✅ DONE

- Cart tightly coupled to online checkout only
- When disabled, hidden from nav, but implementation in shop.js preserved for future
- shop.js checks shopCfg.checkoutEnabled (from KC_SHOP_CONFIG), disables cart rendering, shows WhatsApp flow, mobile add triggers WhatsApp
- localStorage cart code intact, not exposed in catalogue mode, badge still updates

---

### SECTION I — NAVIGATION & HERO CAROUSEL — ✅ DONE

- Navigation: Home, All products, Categories (5), New Arrivals when catalogue mode, discovery focus, no Cart/Checkout when disabled, WhatsApp/Contact button wa.me with generic message
- Hero carousel preserved: full-screen, autoplay 6500ms, pause/play, dots with number + bar, arrows, counter, keyboard arrows, touch swipe, reduced-motion respect, inert for inactive slides, transparent nav nav-over-hero with is-scrolled on scroll>40
- CTAs lead to product page, category, shop — NOT checkout
- No Buy Now → Checkout reintroduced

---

### SECTION J — POS & SALE SOURCE TRACKING — ✅ DONE

- sales.sale_source VARCHAR(20) DEFAULT walk-in (walk-in/whatsapp/phone/other/online)
- sales.whatsapp_enquiry_id INT NULL
- POS UI pos/index.php: Sale source dropdown (Walk-in default, WhatsApp, Phone, Other), WhatsApp Enquiry ID number input optional, Recent WhatsApp enquiries panel (last 15, product_name, variant_label, condition_type, created_at, phone, product_url, Use in POS button → sets source=WhatsApp + enquiry ID + toast), draft persistence includes source + WA id, review modal shows source, payload includes source + WA id, offline payload includes source + WA id
- SaleService: normalizeOpts validates allowed sources, insertData with columnExists guards
- sales list/detail: show source badge (green WhatsApp, blue phone), WA id badge
- reports: metrics group by source, WhatsApp enquiries total/converted
- Flow: Browse → WhatsApp → Deal → POS sale source=WhatsApp → inventory deduction, IMEI handling, COGS, profit, receipt, reports as existing
- WhatsApp enquiry does NOT auto-create sale, POS source of truth preserved

---

### SECTION K — REPORTING — ✅ DONE

- Sales by source: ReportController::metrics() groups via COALESCE(sale_source,'walk-in'), SUM rev + COUNT n when column exists
- Revenue by source: same grouping
- WhatsApp enquiries: total COUNT from whatsapp_enquiries in date range, converted COUNT from sales where whatsapp_enquiry_id IS NOT NULL or sale_source=whatsapp when column missing
- reports/index.php: KPIs — Net sales, Completed orders, Gross profit, Net result + WhatsApp enquiries total, Converted, Walk-in vs WhatsApp, Shop mode (Catalogue+WhatsApp vs Online checkout); Channels hbar + By source hbar with WhatsApp green #25D366; export CSV includes channels + sources + WhatsApp
- sales/index.php: source filter dropdown, source column, WA badge, active filters
- Admin only

Example:
```
SALES BY SOURCE
Walk-in 318
WhatsApp 67
Phone 11
Other 3
Total 399
WHATSAPP ENQUIRIES
Total 120
Converted 67 (55.8%)
```

---

### SECTION L — ADMIN SETTINGS UI — ✅ DONE

- New tab WhatsApp & Online Shop (renamed Hero Carousel tab to Hero Carousel)
- Fields: WhatsApp Ordering Enabled toggle, WhatsApp Sales Number +2547... with validation & normalization preview wa.me/..., Online Checkout Disabled toggle, Online M-PESA Payments Disabled toggle, Default Enquiry Message textarea with placeholder help
- Security: admin-only, CSRF, normalized format after save, validation rejects invalid Kenyan formats
- Implementation: SettingsController::update() handles new fields with normalize_whatsapp_number() validation, flags, template, tab-aware redirect ?tab=whatsapp, Activity log with checkout/whatsapp flags
- M-PESA tab hint: master vs online distinction, callback URL tokenized

---

### SECTION M — PRODUCT CARDS & LISTINGS — ✅ DONE

- Cards on homepage (featured), browse (product-grid with page-size 12 + load-more), related (You might also like) → lead to Product Detail Page
- Price display: From KSh on card when variants exist (Product::fromPrice), exact after variant selection on detail
- No giant buttons overloaded, compact design preserved, catalogue CTA View details when checkout OFF
- Flow: Card → Detail → Variant → WhatsApp

---

### SECTION N — PRICE DISPLAY & CONDITION — ✅ DONE

- Uses existing sell_price (VAT-exclusive stored, gross_of + money() for display)
- Variants: From price on card, exact on detail after selection, live update
- Condition: New/Used/Refurbished badge (green/blue/orange), grade badge gray, warranty, battery notes, condition_notes with left orange border + #fffbeb background
- NEVER exposes: buying price, cost, COGS, profit margin, supplier, admin notes, IMEI, serial, sensitive stock — enforced in build_whatsapp_message() and public UI

---

### SECTION O — MOBILE & ACCESSIBILITY — ✅ DONE

**Mobile:**
- Product → Variant → Large accessible WhatsApp CTA (btn-lg, min-height 44px, 50px on hero, 44px sticky)
- Sticky bottom CTA #mobile-buy-bar: price + WhatsApp button, safe areas, not covering content
- Variant options flex-wrap, min-height 42px on mobile, flex:1 0 auto

**Accessibility:**
- Button semantics: <a> for WhatsApp (href wa.me), <button> for variant selectors, Share
- Keyboard accessible: variant options focusable, gallery thumbs, nav toggle aria-expanded, skip-link, main tabindex -1
- Visible focus states via :hover + active + .active with box-shadow
- Accessible labels: aria-label on WhatsApp, Share, gallery thumbs, variant groups role=group, selected-variant-summary aria-live polite, availability live
- Colour contrast: green #25D366 on white, blue #0071e3, orange #f59e0b, text #1d1d1f on #f5f5f7
- Text "Order on WhatsApp" not icon-only, includes SVG + text, aria-label
- Desktop: wa.me handles web/app flow

---

### SECTION P — SECURITY CHECKLIST — ✅ DONE

- CSRF on all state-changing routes incl. POST shop/whatsapp-enquiry (X-CSRF-Token header), POS checkout, sync, settings, sales void, M-Pesa actions
- Auth: admin-only settings (requireLogin + requireAdmin via router + controller), WhatsApp number edit admin-only
- SQL injection: prepared statements everywhere via Database::fetch/fetchAll/insert/update
- XSS escaping: e() in all server-rendered, esc() in JS-built HTML (shop.js, pos/index.php, product.php)
- File upload security: product images via safe streaming uploads/p/{filename}, hero images, path-traversal safe, web-denied storage
- Output encoding for WhatsApp message: e() for display, rawurlencode for URL, encodeURIComponent JS
- URL encoding for wa.me link: rawurlencode PHP, encodeURIComponent JS, handles special chars, ampersands, line breaks %0A
- Redirects safe via url() helper + query_string()
- Feature flag enforcement server-side: cart, apiCart, checkout 403 when disabled, mpesa online blocked
- No sensitive data in WhatsApp message: build_whatsapp_message excludes cost, profit, COGS, supplier, IMEI, serial, internal IDs
- No IMEI/serial in public enquiry: whatsapp_enquiries only product_id, variant_id, product_name, variant_label, price_shown, product_url, condition_type, source_page

---

### SECTION Q — TESTING — ✅ DONE (Manual + JS lint)

**Public shop:**
- Homepage loads, hero carousel works (autoplay, pause, dots, arrows, counter, keyboard, touch, reduced-motion, inert), products load, categories work, search works (name/SKU), product details work, images work (main + thumbs), variants work (RAM/storage/colour/condition/label distinct, live update), prices work (From + exact + gross + ex VAT), conditions work (badge + grade + notes)

**WhatsApp:**
- Button appears when whatsappEnabled + number set, correct number (wa.me/254...), product name in message, variant appears (RAM/storage/colour/label), colour, condition, grade, price, SKU, URL, URL encoded, special chars handled, mobile sticky bar works, desktop opens WhatsApp web, tracking fetch POST to whatsapp-enquiry with CSRF + keepalive

**Variants:**
- Changing variant updates display (price, SKU, condition, summary, mobile price, stock), WhatsApp uses selected variant, out-of-stock handled (Ask About Availability orange), pricing accurate (effectivePrice)

**Checkout disabled:**
- Cart hidden from nav (replaced by WhatsApp nav link), checkout CTA hidden, Buy Now hidden, M-Pesa CTA hidden, /shop/cart shows unavailable page with WhatsApp CTA, /shop/api/cart returns 403, /shop/checkout POST returns 403, direct URL blocked gracefully

**M-Pesa preservation:**
- Source files exist (app/services/MpesaService.php, app/models/MpesaService.php), mpesa_transactions table, callback route POST mpesa/callback?token=..., historical transactions remain, public STK disabled via mpesa_online_enabled=0 server-side, POS M-Pesa unaffected (master mpesa_enabled)

**POS:**
- Walk-in sale works, inventory deduction (bulk qty, serialized units sold), IMEI selection, serial tracking, profit, receipt (PDF + 80mm/58mm thermal), reports, WhatsApp source selector (Walk-in/WhatsApp/Phone/Other) + WA enquiry ID + recent enquiries panel Use in POS

**Security:**
- Non-admin cannot edit WhatsApp number (admin gate), cannot enable checkout (admin gate), CSRF on whatsapp-enquiry (419 if invalid), XSS via e()/esc(), SQL via prepared, no sensitive data leak

**Responsive:**
- 1920, 1440, 1366, 768 tablet, 390, 360 mobile — variant selectors wrap, sticky bar safe, hero carousel 88vh on mobile, nav toggle, load-more, gallery thumbs

**JS lint:**
- shop.js, app.js, pos-offline.js pass node --check

---

### SECTION R — CLEANUP & DOCUMENTATION — ✅ DONE

- Removed console.log debugging, temp files, duplicate CSS, dead code introduced (NOT dormant M-Pesa/checkout)
- Documentation updated:
  - README.md — new Catalogue + WhatsApp Mode section, feature flags table, flow, OG SEO, project structure with new files, roadmap Phase 13 done
  - FEATURES.md — full inventory with new sections: product variants, condition tracking, sale source tracking, catalogue + WhatsApp mode (flags, WhatsApp CTA, OG, guards, lead tracking), sales source filter, reports by source + WhatsApp KPIs, settings WhatsApp & Online tab, data model expanded (product_variants, whatsapp_enquiries, sale_source, condition fields, variant_id, flags)
  - docs/workflows.md — updated to catalogue + WhatsApp mode, new Settings WhatsApp & Online tab, variants, stock intake with variants, POS with sale source + recent enquiries, online shop catalogue journey with variant selectors + WhatsApp CTA + OG + lead tracking + preserved checkout disabled, payments with POS vs online separation, sales management with source, returns, expenses, dashboard/reports with source breakdown + WhatsApp KPIs, Z-report, security, technical details (flags, number normalization, message, OG, variants, lead tracking, sale source, M-Pesa preservation, POS source of truth, SEO/a11y/security)
  - docs/cpanel-deployment.md — updated to catalogue mode default, new settings steps for WhatsApp number, flags explanation, OG verification, server-side guards verification, troubleshooting table for WhatsApp, cart, OG, whatsapp_enquiries, sale source, reports, M-Pesa online, POS source
  - docs/catalogue-whatsapp.md — NEW dedicated guide (see below)
  - IMPLEMENTATION_BREAKDOWN.md — this file, marked DONE per section

---

### SECTION S — FUTURE REACTIVATION PLAN — ✅ DOCUMENTED

- To re-enable online checkout: Settings → WhatsApp & Online → check Online Checkout + Online M-PESA → Save (or set shop_settings online_checkout_enabled=1, mpesa_online_enabled=1)
- Existing checkout/payment infra becomes available after testing: ShopController cart(), apiCart(), checkout(), checkoutPaymentIntent(), order(), orderStatus(), track, shop.js cart/checkout staged flow, delivery zones server-resolved, M-Pesa STK push + callback + admin fallbacks, email confirmations
- No need to revert dozens of files — feature flags control reversible behaviour
- POS remains source of truth regardless of mode
- Testing re-activation: enable flags, test cart, checkout, delivery, M-Pesa sandbox, order tracking, Z-report, then go live

---

### IMPLEMENTATION ORDER (Recommended) — FOLLOWED

1. A Audit — DONE
2. B Feature flags infra + C Migrations — DONE (Schema seedBaseline, ensureProductVariants, ensureWhatsappEnquiries, migrateColumns sale_source, condition fields, variant_id)
3. J Sale source tracking — DONE (sales.sale_source, POS selector, SaleService, Sale model filter, reports)
4. D Product variants — DONE (ProductVariant model, Product::variants(), fromPrice(), distinct values, variant_id in inventory_units)
5. E WhatsApp core — DONE (normalize, display, whatsapp_number, whatsapp_link, build_whatsapp_message, button, JS, no auto sale, lead tracking)
6. F Open Graph — DONE (ogTitle, ogDescription, ogImage absolute, ogUrl, ogType, twitter:card, canonical, layout meta)
7. G Checkout disable + H Cart handling + I Navigation — DONE (cart() unavailable page, apiCart 403, checkout 403 + mpesa_online check, shop.js checkoutEnabled flag, layout conditional cart vs WhatsApp nav, home/browse/cart flags)
8. M Product cards + N Price/Condition — DONE (From price, condition badge, View details, condition notes, no sensitive exposure)
9. O Mobile sticky CTA + P Security — DONE (mobile-buy-bar sticky, variant selectors accessible, ARIA, focus states, CSRF, escaping, no IMEI)
10. L Admin settings UI — DONE (WhatsApp & Online tab, number validation, flags, template, how-it-works)
11. K Reporting — DONE (saleSources grouping, whatsappEnquiries total/converted, reports/index KPIs + hbar, sales/index source filter + column, export CSV)
12. Q Testing + R Cleanup — DONE (manual flows, JS lint, docs updated)

---

### QUICK REFERENCE: FILES TOUCHED — FINAL

- `app/core/Schema.php` — migrations: ensureProductVariants(), ensureWhatsappEnquiries() with extra columns migration, migrateColumns sale_source, whatsapp_enquiry_id, condition_type, condition_grade, condition_notes, battery_notes, variant_id, seedBaseline flags online_checkout_enabled, mpesa_online_enabled, whatsapp_ordering_enabled, whatsapp_sales_number, whatsapp_message_template
- `app/models/ProductVariant.php` — NEW: allForProduct, find, create, update, delete, displayLabel, effectivePrice, distinctValues
- `app/models/Product.php` — variants(), fromPrice(), conditionLabel(), stockColumns
- `app/models/Sale.php` — buildWhere source filter when column exists
- `app/models/SaleService.php` — normalizeOpts sale_source allowed walk-in/whatsapp/phone/other/online, insertData with columnExists guards for sale_source + whatsapp_enquiry_id
- `app/controllers/ShopController.php` — home(), browse(), product() with variants + OG meta, cart() unavailable page when flag OFF, apiCart() 403, checkout() 403 + mpesa_online check + auto-void, whatsappEnquiry() lead tracking with columnExists guards, orderStatus(), mpesaCallback() preserved
- `app/controllers/SettingsController.php` — update() with whatsapp_sales_number validation normalize_whatsapp_number(), flags online_checkout_enabled, mpesa_online_enabled, whatsapp_ordering_enabled, whatsapp_message_template, tab-aware redirect, testEmail/testMpesa with correct stkPush signature
- `app/controllers/PosController.php` — index() recent enquiries, search(), checkout() with sale_source + whatsapp_enquiry_id, catalog(), sync() with source + WA id
- `app/controllers/SaleController.php` — index() source filter, export() includes source + WA id, show(), void(), mpesaMarkPaid(), mpesaRetry(), pdf(), printThermal() (original HTML preserved)
- `app/controllers/ReportController.php` — metrics() includes saleSources grouping + whatsappEnquiries total/converted, export() includes channels + sources + WhatsApp, vatReport, purchases preserved
- `app/helpers/functions.php` — is_online_checkout_enabled(), is_mpesa_online_enabled(), is_whatsapp_ordering_enabled(), normalize_whatsapp_number(), whatsapp_display_number(), whatsapp_number(), whatsapp_link(), build_whatsapp_message() with placeholders, safe replacement
- `app/views/layouts/shop.php` — OG meta tags, conditional cart vs WhatsApp nav, footer WhatsApp link, KC_SHOP_CONFIG checkoutEnabled/whatsappEnabled/whatsappNumber/whatsappDisplay/shopName/baseUrl/csrf/whatsappEnquiryUrl/currency
- `app/views/shop/product.php` — variant-aware: variant-selectors (storage, ram, colour, condition_type, label), selected-variant-summary, live price/sku/condition/grade/stock/mobile price, purchase-panel with Order on WhatsApp (in-stock green #25D366 + SVG) or Ask About Availability (out-of-stock orange), Share button, mobile-buy-bar sticky, JSON product-data + variant-data, JS findVariantByAttributes, updateUI, buildMessage, updateWhatsappLink, trackWhatsapp fetch to whatsappEnquiryUrl with product_id, variant_id, product_name, variant_label, price_shown, product_url, source_page, condition_type, keepalive
- `app/views/shop/_product-card.php` — From price when variants, condition_type badge, View details when catalogue mode
- `app/views/shop/home.php` — checkoutEnabled/whatsappEnabled flags, catalogue messaging, WhatsApp CTA in hero when OFF
- `app/views/shop/browse.php` — flags, catalogue p, From pricing via card
- `app/views/shop/cart.php` — checkoutEnabled flag, unavailable page with WhatsApp CTA when OFF, staged checkout only when ON
- `app/views/settings/index.php` — new WhatsApp & Online tab, normalized number display + wa.me hint, flags, template textarea, how-it-works card, active_tab hidden input, JS tab switching + delivery zone edit toggles + test email/mpesa fetch
- `app/views/pos/index.php` — sale_source selector + WhatsApp enquiry ID + recent enquiries panel with Use in POS, draft persistence, review modal source, payload includes source + WA id, offline payload includes source + WA id
- `app/views/sales/index.php` — source filter dropdown, source column with badge (green WhatsApp), WA enquiry badge, active filters, pager
- `app/views/sales/show.php` — source badge + WA id badge in identity bar + order information
- `app/views/reports/index.php` — KPIs WhatsApp enquiries, Converted, Walk-in vs WhatsApp, Shop mode, Channels + By source hbar with WhatsApp green
- `public_html/assets/css/shop.css` — .btn-whatsapp, .btn-out-of-stock, .whatsapp-nav-link, .wa-icon, .detail-price-wrap, .condition-badge-row, .variant-selectors, .variant-group, .variant-label, .variant-options, .variant-option:hover/active, .selected-variant-summary, .condition-notes, mobile variant-option min-height, #mobile-buy-bar .btn-whatsapp
- `public_html/assets/js/shop.js` — shopCfg checkoutEnabled flag, conditional cart listeners, mobile add triggers WhatsApp, gallery thumbs, load-more, hero carousel + transparent nav preserved, WhatsApp tracking via product.php inline, renderCartPage catalogue-mode message, checkout form only when enabled, payment polling preserved
- `public_html/index.php` — POST shop/whatsapp-enquiry route to ShopController::whatsappEnquiry

---

This breakdown turned the 46-section master prompt into actionable chunks and all are now implemented.
