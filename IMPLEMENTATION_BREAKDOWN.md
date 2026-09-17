# KHAMIS COMPUTERS 2.0 — Catalogue + WhatsApp Mode
## Master Task Breakdown into Simple Implementable Sections

This document breaks the massive master prompt into bite-sized phases that can be implemented incrementally without breaking POS, inventory, or M-Pesa.

---

### 0. CORE PRINCIPLE: DISABLE, DON'T DELETE
- Keep all M-Pesa files, callbacks, tables, migrations.
- Keep cart, checkout, online order code, but hide/disable via feature flags.
- POS remains source of truth for sales.

---

### SECTION A — AUDIT & FOUNDATION (Phase 1)

**Goal:** Understand current system before touching code.

Checklist:
- [ ] Routes in `public_html/index.php` — shop, checkout, mpesa callback, POS
- [ ] ShopController — home, browse, product, cart, checkout, mpesaCallback, track
- [ ] Product model — stock logic, images, no variants yet
- [ ] SaleService — single engine for POS + online
- [ ] MpesaService — OAuth, STK push, callback handling
- [ ] Settings — shop_settings key/value, VAT, mail, M-Pesa, delivery zones
- [ ] Schema — tables, indexes, migrations
- [ ] Shop views — layout, home, browse, product, cart, _product-card
- [ ] JS — shop.js cart, checkout, hero carousel
- [ ] POS views — cart, receipt, sale source?
- [ ] Reports — sales, VAT, purchases, Z-report

**Output:** Audit findings doc.

---

### SECTION B — FEATURE FLAGS & SETTINGS INFRA (Phase 2)

**Conceptual flags:**
- `online_checkout_enabled` = false (currently OFF)
- `mpesa_online_payment_enabled` = false (OFF for public)
- `whatsapp_ordering_enabled` = true (ON)

**Implementation:**
- Use existing `shop_settings` table — add keys via Schema::seedBaseline() or migrateColumns()
- Keys:
  - `online_checkout_enabled` (0/1)
  - `mpesa_online_enabled` (0/1) — separate from mpesa_enabled for POS?
  - `whatsapp_ordering_enabled` (0/1)
  - `whatsapp_sales_number` (string)
  - `whatsapp_message_template` (text, optional)
- Add helper functions:
  - `is_online_checkout_enabled()`
  - `is_whatsapp_ordering_enabled()`
  - `whatsapp_number()`, `normalize_whatsapp_number()`
- Admin UI: new tab "Online Shop" or "WhatsApp" in SettingsController + view

**Security:**
- Only admin can edit.
- Normalize number handling: 07XX, 7XX, 2547XX, +2547XX → +2547XX or 2547XX without plus for wa.me?

---

### SECTION C — DATABASE MIGRATIONS (Safe, Backward-Compatible)

**Tables to add (IF NOT EXISTS):**
1. `product_variants`
   - id, product_id, sku, label, ram, storage, colour, condition, grade, price_override, is_active, sort_order, created_at
   - Allows future variant-level pricing
2. `whatsapp_enquiries` (optional lead tracking)
   - id, product_id, variant_id, message, source_page, created_at
3. Alter `sales`:
   - add `sale_source` VARCHAR(20) DEFAULT 'walk-in' — values: walk-in, whatsapp, phone, other, online (future)
   - add `whatsapp_enquiry_id` nullable (optional link)

**Alter `products`:**
- add `condition_type` ENUM new/used/refurbished (or VARCHAR)
- add `condition_grade` VARCHAR
- add `condition_notes` TEXT
- add `battery_notes` VARCHAR

**Do NOT drop:**
- mpesa_transactions
- sales, sale_items
- delivery_zones, etc.

**Implementation in Schema.php:**
- tableExists() checks
- ensureProductVariants(), ensureWhatsappEnquiries(), ensureSaleSource()
- migrateColumns() extended

---

### SECTION D — PRODUCT VARIANTS SYSTEM

**Backend:**
- Model `ProductVariant.php`
  - allForProduct(productId), find(id), create/update/delete
  - stock? For now, variant uses parent product stock; future can link to inventory_units via variant_id
- Product model:
  - variants(productId)
  - variantStock(variantId) — for now return product stock
- ProductController:
  - Admin UI to manage variants on product show page? Or separate tab.
  - For MVP, admin can add variants via simple form.

**Frontend:**
- Product detail page shows variant selectors:
  - Storage: [128GB] [256GB]
  - RAM: [4GB] [6GB] [8GB]
  - Colour: [Black] [Blue]
  - Condition: [New] [Used]
- Selecting variant updates:
  - Displayed price (variant price override or base)
  - SKU/code
  - Condition badge
  - Availability
  - WhatsApp message data attributes

**WhatsApp integration:**
- WhatsApp enquiry MUST use selected variant, not generic product.

---

### SECTION E — WHATSAPP CORE IMPLEMENTATION

**1. Number Config:**
- Setting `whatsapp_sales_number`
- Normalization function:
  - Input: 0712345678 → 254712345678 → for wa.me use 254712345678
  - Handle +254, 254, 07, 7
  - Validate Kenyan format (or allow international if needed)
- Helper `whatsapp_normalized_number()` returns digits without + for wa.me link
- Helper `whatsapp_display_number()` returns +254...

**2. Message Generation:**
- Template placeholders: {product_name}, {variant}, {ram}, {storage}, {colour}, {condition}, {price}, {sku}, {product_url}, {shop_name}
- Default template (from prompt):
```
Hello KHAMIS COMPUTERS,

I'm interested in this product:

Product: {product_name}
Variant: {variant}
Colour: {colour}
Condition: {condition}
Price shown: {price}
Product Code: {sku}

Product page:
{product_url}

Is this product currently available?
```
- Function `build_whatsapp_message(product, variant, url)` → string
- Must NOT include cost, profit, IMEI, internal IDs, supplier.

**3. URL Generation:**
- Use `https://wa.me/{number}?text={encodedMessage}`
- Proper URL encoding (encodeURIComponent in JS, rawurlencode in PHP)
- Test special chars, ampersands, line breaks (%0A)

**4. Button:**
- Primary CTA: [WhatsApp Icon] ORDER ON WHATSAPP
- Fit design system, not all green.
- Secondary: Call Shop, View More Products, Share Product (optional)

**5. Out-of-stock:**
- If stock <=0: show ASK ABOUT AVAILABILITY instead of ORDER ON WHATSAPP
- Message says "It is currently shown as out of stock..."

**6. No sale creation:**
- Clicking WhatsApp must NOT create sale, deduct stock, trigger M-Pesa.

**7. Optional lead tracking:**
- On click, POST to `/shop/whatsapp-enquiry` (optional) → record in whatsapp_enquiries
- Lightweight, no personal data collection beyond product_id, variant_id, timestamp

---

### SECTION F — OPEN GRAPH & SEO

**Product page OG metadata:**
- og:title = "{Product Name} | {Shop Name}"
- og:description = "{Variant summary} - Available at {Shop Name}" or product description trimmed
- og:image = absolute HTTPS URL to primary product image (use config base_url, ensure https)
- og:url = canonical product URL
- og:type = product
- twitter:card = summary_large_image

**Implementation:**
- In shop layout or product view, add <meta property="og:..."> tags
- Helper to get absolute image URL: `url('uploads/p/...')` already absolute based on base_url
- Ensure no localhost in production — use config base_url.

**Other SEO:**
- Preserve title, meta description, canonical, product URLs, category pages, internal linking.

---

### SECTION G — CHECKOUT DISABLE & SECURITY ENFORCEMENT

**UI hiding:**
- shop layout: hide cart link if online_checkout_enabled = false
- _product-card: keep Add to Cart hidden when disabled; only show View Product
- product.php: hide Add to Cart, show WhatsApp CTA
- cart.php: if disabled, show message "Online checkout is currently unavailable. Please order via WhatsApp." + redirect to shop
- home.php, browse.php: ensure no Buy Now → Checkout

**Server-side enforcement:**
- ShopController::checkout(): check flag at top, if disabled → json_response 403 or redirect
- ShopController::apiCart(): optionally disable or keep for future
- ShopController::cart(): if disabled, redirect or show unavailable message
- MpesaService: public online STK push must NOT initiate when mpesa_online_enabled = false
  - Check in checkoutPaymentIntent() and checkout()
  - POS M-Pesa must remain operational — distinguish channel == online vs pos
  - SaleService already handles payment_method; ensure POS checkout still works even if online M-Pesa disabled

**Graceful failure:**
- /shop/cart → redirect to /shop or show "Online checkout unavailable"
- /shop/checkout POST → 403 JSON with error

---

### SECTION H — CART HANDLING

- Cart is tightly coupled to online checkout only.
- When disabled, hide from nav, but keep implementation in shop.js for future.
- In shop.js, check config flag `onlineCheckoutEnabled` — if false, disable cart rendering, show WhatsApp flow.
- Keep localStorage cart code intact, just not exposed.

---

### SECTION I — NAVIGATION & HERO CAROUSEL

**Navigation:**
- Focus on discovery: Home, Products, Categories, New Arrivals, Offers, Search
- Remove Cart/Checkout when disabled
- Add WhatsApp/Contact button (link to wa.me with generic message or tel:)

**Hero carousel:**
- Preserve existing hero carousel direction
- CTAs should lead to product page, category, collection, offer — NOT checkout
- Optionally, product hero can use ORDER ON WHATSAPP if variant context available
- Ensure no Buy Now → Checkout reintroduced

---

### SECTION J — POS & SALE SOURCE TRACKING

**Sale source field:**
- Add to sales table: sale_source
- Values: walk-in, whatsapp, phone, other, online (future)
- In POS UI (pos/index.php):
  - Add dropdown or radio: Sale source
  - Default walk-in
  - Save via SaleService::create() opts
- In SaleService: accept sale_source, store
- In sales list/detail: show source badge
- In reports: extend metrics to group by source

**Flow:**
- Customer browses → WhatsApp → Deal agreed → Cashier records sale in POS with source = WhatsApp → inventory deduction, IMEI handling, COGS, profit, receipt, reports as existing.

**Ensure:**
- WhatsApp enquiry does NOT auto-create sale
- POS remains source of truth

---

### SECTION K — REPORTING

**If sale_source implemented:**
- Sales by source table
- Revenue by source
- Use existing sales records, don't duplicate calculations
- Add to reports/index.php and reports/z.php?
- Admin only

**Example:**
```
SALES BY SOURCE
Walk-in 318
WhatsApp 67
Phone 11
Other 3
Total 399
```

---

### SECTION L — ADMIN SETTINGS UI

**New settings tab "WhatsApp & Online Shop" or split:**

Fields:
- WhatsApp Ordering [Enabled toggle]
- WhatsApp Sales Number [+2547XXXXXXXX] with validation & normalization preview
- Default Enquiry Message [textarea with placeholder help]
- Online Checkout [Disabled toggle]
- Online M-Pesa Payments [Disabled toggle]

**Security:**
- Only admin can change
- Show normalized format after save
- CSRF protection

**Implementation:**
- SettingsController::update() handles new fields
- Validation: phone format, template placeholders safe (no code execution)

---

### SECTION M — PRODUCT CARDS & LISTINGS

- Cards on homepage, search, category, best sellers, new arrivals, promotions → lead to Product Detail Page
- Don't overload with giant buttons
- Optional compact WhatsApp action on card, but safest flow is Card → Detail → Variant → WhatsApp
- Ensure price display: "From KSh XX,XXX" for products with variants, then exact when variant selected

---

### SECTION N — PRICE DISPLAY & CONDITION

- Continue using existing selling-price data (sell_price)
- For variants, show From price on card, exact on detail after selection
- Show condition clearly: New, Used, Refurbished + grade, warranty, battery notes if exist and public
- NEVER expose: buying price, cost, COGS, profit margin, supplier, admin notes, IMEI, serial, sensitive stock

---

### SECTION O — MOBILE & ACCESSIBILITY

**Mobile:**
- Product → Variant → Large accessible WhatsApp CTA
- Consider sticky bottom CTA on product detail:
```
KSh 25,000 [💬 WHATSAPP]
```
- Respect safe areas, viewport, not covering content

**Accessibility:**
- Proper button semantics (<a> or <button>)
- Keyboard accessible
- Visible focus states
- Accessible labels
- Colour contrast
- Text "Order on WhatsApp" not icon-only

**Desktop:**
- Click opens WhatsApp web/app flow (wa.me handles)

---

### SECTION P — SECURITY CHECKLIST

- [ ] CSRF remains on all state-changing routes
- [ ] Auth: admin-only settings
- [ ] SQL injection: prepared statements
- [ ] XSS escaping: e() everywhere
- [ ] File upload security (hero images, product images)
- [ ] Output encoding for WhatsApp message
- [ ] URL encoding for wa.me link
- [ ] Redirects safe
- [ ] Feature flag enforcement server-side
- [ ] No sensitive data in WhatsApp message
- [ ] No IMEI/serial in public enquiry

---

### SECTION Q — TESTING

**Manual/Automated tests:**

Public shop:
- Homepage loads, hero works, products load, categories work, search works, product details work, images work, variants work, prices work, conditions work

WhatsApp:
- Button appears, correct number, product name in message, variant appears, colour, condition, price, SKU, URL, URL encoded, special chars, mobile, desktop

Variants:
- Changing variant updates display, WhatsApp uses selected variant, out-of-stock handled, pricing accurate

Checkout disabled:
- Cart hidden, checkout CTA hidden, Buy Now hidden, M-Pesa CTA hidden, direct URL blocked, payment initiation blocked

M-Pesa preservation:
- Source exists, tables remain, callbacks remain, historical transactions remain, public STK disabled, POS M-Pesa unaffected

POS:
- Walk-in sale works, inventory deduction, IMEI selection, serial tracking, profit, receipt, reports, WhatsApp source

Security:
- Non-admin cannot edit WhatsApp number, cannot enable checkout, CSRF, XSS, SQL, no sensitive data leak

Responsive:
- 1920, 1440, 1366, 768 tablet, 390, 360 mobile

---

### SECTION R — CLEANUP & DOCUMENTATION

- Remove console.log debugging, temp files, duplicate CSS, dead code introduced by this task (NOT dormant M-Pesa/checkout)
- Final report with 14 sections as per master prompt

---

### SECTION S — FUTURE REACTIVATION PLAN

- To re-enable: set online_checkout_enabled=true, mpesa_online_payment_enabled=true in settings or .env
- Existing checkout/payment infra becomes available after testing
- No need to revert dozens of files — feature flags control.

---

### IMPLEMENTATION ORDER (Recommended)

1. **A** Audit
2. **B** Feature flags infra + **C** Migrations
3. **J** Sale source tracking (small, safe, POS)
4. **D** Product variants (model + table + admin)
5. **E** WhatsApp core (number normalization, message builder, button, JS)
6. **F** Open Graph
7. **G** Checkout disable + **H** Cart handling + **I** Navigation
8. **M** Product cards + **N** Price/Condition
9. **O** Mobile sticky CTA + **P** Security
10. **L** Admin settings UI
11. **K** Reporting
12. **Q** Testing + **R** Cleanup

---

### QUICK REFERENCE: FILES TO TOUCH

- `app/core/Schema.php` — migrations
- `schema/schema.sql` — new tables for fresh installs
- `app/models/Setting.php` — helpers
- `app/models/Product.php` — variants, condition
- `app/models/ProductVariant.php` — new
- `app/models/Sale.php` / `SaleService.php` — sale_source
- `app/controllers/ShopController.php` — flags, OG, WhatsApp, checkout guard
- `app/controllers/SettingsController.php` — WhatsApp settings
- `app/views/layouts/shop.php` — nav, OG, cart hide, WhatsApp link
- `app/views/shop/product.php` — main CTA, variants, OG, sticky bar
- `app/views/shop/_product-card.php` — ensure no cart
- `app/views/shop/home.php`, `browse.php`, `cart.php` — flags
- `app/views/settings/index.php` — new tab
- `app/helpers/functions.php` — WhatsApp helpers, OG helpers
- `public_html/assets/css/shop.css` — WhatsApp button, variant UI, sticky
- `public_html/assets/js/shop.js` — variant selection, WhatsApp URL, flag respect
- `app/views/pos/index.php` — sale source selector
- `app/views/sales/*` — show source
- `app/controllers/ReportController.php` — source reporting

---

This breakdown turns the 46-section master prompt into actionable chunks.
