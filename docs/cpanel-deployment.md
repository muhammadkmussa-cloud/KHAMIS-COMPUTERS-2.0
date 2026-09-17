# Deploying Khamis Computers to cPanel

This project is designed for a standard shared **cPanel** host running **Apache + PHP 8 + MySQL/MariaDB**. No Composer, no Node, no build step.

Current default mode: **Catalogue + WhatsApp** — public shop is a catalogue with Order on WhatsApp CTA. Online checkout + public M-Pesa are preserved but disabled via flags (`online_checkout_enabled=0`, `mpesa_online_enabled=0`). POS remains source of truth.

---

## 1. Create the database in cPanel

1. Log in to cPanel → **MySQL® Databases**.
2. Create a database, e.g. `youruser_khamis`.
3. Create a database user, e.g. `youruser_khamisapp` with strong password.
4. Add user to DB with **ALL PRIVILEGES**.
5. Host is usually `localhost`.

---

## 2. Upload the files

Upload **contents** of `public_html/` into cPanel's `public_html/` (File Manager/FTP). Upload `app/`, `schema/`, `storage/` and `.env` **outside** `public_html` (`/home/youruser/`) so not web-accessible.

Recommended layout:
```
/home/youruser/
├── public_html/            ← contents of repo's public_html/
│   ├── index.php           ← includes route POST shop/whatsapp-enquiry
│   ├── .htaccess
│   └── assets/css/js/
├── app/                    ← includes ProductVariant, helpers/whatsapp, ShopController catalogue mode
├── schema/
├── storage/
└── .env
```

`public_html/index.php` expects `app/` at `../app`. If everything inside `public_html/`, protect `app/`, `schema/`, `storage/`, `.env` via `Require all denied` (see §5).

---

## 3. Configure credentials

Create `/home/youruser/.env` (or edit `app/config/config.php`):

```env
APP_ENV=production
APP_DEBUG=false

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=youruser_khamis
DB_USER=youruser_khamisapp
DB_PASS=your-strong-password

CURRENCY=KSh
VAT_RATE=16
APP_TIMEZONE=Africa/Nairobi
```

---

## 4. Create the tables

**Option A — phpMyAdmin (recommended):**
1. cPanel → phpMyAdmin → select DB.
2. Import → `schema/schema.sql` → Go.
3. Then run installer once (Option B or `php tools/install.php` over SSH). Installer creates secondary indexes (UNIQUE device_id, client_ref), seeds admin/cashier, baseline settings (including new flags: online_checkout_enabled=0, mpesa_online_enabled=0, whatsapp_ordering_enabled=1, whatsapp_sales_number, whatsapp_message_template), delivery zones, hero slides.

> Schema statements idempotent (`CREATE TABLE IF NOT EXISTS`), so re-importing after update safely adds new tables (`product_variants`, `whatsapp_enquiries`, `hero_slides`, etc.) without touching data. **Re-run installer after upgrade** (no --fresh) to create indexes + seed new settings.

### Product-image uploads + OG preview

Uploaded images → `storage/uploads/products/` (web-denied) → streamed via `/uploads/p/{filename}`. Primary image used in shop cards, product page, and **OG image** for WhatsApp rich preview (`og:image` absolute HTTPS via `url()`). Ensure `storage/uploads/` writable (755/775).

### Catalogue + WhatsApp mode settings

After install:

1. Login as admin → **Settings → WhatsApp & Online**
2. Set **WhatsApp Sales Number**: Kenyan formats accepted — 07XXXXXXXX (10 digits), 01XXXXXXXX, 7XXXXXXXX (9), 2547XXXXXXXX (12), +2547XXXXXXXX. Normalized to 254... for `wa.me/...` links. Validation via `normalize_whatsapp_number()`. Hint shows `+254...` and `wa.me/254...`
3. **Enable WhatsApp ordering** checked (default ON)
4. **Online Checkout** unchecked (OFF = catalogue mode, recommended). Check to re-enable cart/checkout.
5. **Online M-PESA** unchecked (OFF blocks public STK server-side; POS M-Pesa still works via `mpesa_enabled`)
6. **Message template** optional: placeholders {product_name} {variant} {ram} {storage} {colour} {condition} {grade} {price} {sku} {product_url} {shop_name}. Leave blank for default structured template.
7. Save → flags stored in `shop_settings`. Current mode displayed: Catalogue + WhatsApp vs Online Checkout.

**How it works:** Product → Select variant → Order on WhatsApp → `https://wa.me/{number}?text={encoded}` opens with structured message (product, variant, condition, price, SKU, URL) → lightweight tracking POST to `/shop/whatsapp-enquiry` (CSRF, keepalive) → `whatsapp_enquiries` table → conversation on WhatsApp → deal → POS sale with **Sale Source = WhatsApp** + optional **WhatsApp Enquiry ID** → inventory, IMEI, profit, receipt, reports.

**Future re-activation:** Set Online Checkout ON + Online M-Pesa ON. Existing checkout code, M-Pesa STK, callbacks, transactions preserved (DO NOT DELETE).

### M-PESA STK push (Daraja) — preserved

POS M-Pesa uses `mpesa_enabled` master. Online M-Pesa uses `mpesa_online_enabled` separate.

To enable (when you re-activate online checkout):

1. Create app on Safaricom Daraja portal → consumer key/secret, passkey, shortcode
2. Settings → M-PESA → enter, choose sandbox/live, paybill/till, enable master
3. Settings → WhatsApp & Online → enable Online M-PESA
4. Set Callback URL on Daraja portal to tokenized URL shown in M-PESA tab: `https://yourdomain.com/mpesa/callback?token=...` (must be internet-reachable, server needs outbound HTTPS to api.safaricom.co.ke / sandbox)
5. Outbound HTTPS needs PHP cURL (standard on cPanel)
6. If SMS confirmed but callback missed, admin → sale detail → Mark paid

When catalogue mode (online_checkout_enabled=0, mpesa_online_enabled=0): public STK blocked server-side (403 + auto-void if attempted), POS M-Pesa still works.

### Email notifications

Settings → Notifications: mail_enabled, mail_from, transport mail/smtp, host/port/encryption/username/password (blanked), low_stock_threshold/recipient. Default mail() works on cPanel; SMTP for Gmail/Office365. Test with real order.

**Option B — web installer:**
1. Visit `https://yourdomain.com/install`
2. Fill admin name/email/password → Install

After web installer, remove it (see §6).

---

## 5. Lock down non-public folders

Repo ships `storage/.htaccess`, `app/.htaccess`, `schema/.htaccess`, `tools/.htaccess` with `Require all denied`. `public_html/.htaccess` denies dotfiles (`<FilesMatch "^\.">`) to hide `.env`.

Never expose `.env`.

---

## 6. Post-deploy hardening checklist

- [ ] Delete `/install` route: remove install lines in `public_html/index.php` + `SetupController.php` (installer also refuses once installed)
- [ ] Delete `tools/` and `public_html/router-dev.php` (dev-only)
- [ ] Change demo admin password or create own admin
- [ ] Force HTTPS via cPanel AutoSSL/Let's Encrypt, optionally HTTPS redirect in `.htaccess`
- [ ] Set `APP_DEBUG=false`, confirm `/login` no warnings
- [ ] **Set WhatsApp sales number** in Settings → WhatsApp & Online → Save (test product page → Order on WhatsApp → wa.me link + OG preview)
- [ ] Verify OG tags: view product page source → `og:title`, `og:description`, `og:image` absolute HTTPS, `og:url` canonical
- [ ] Verify server-side guards: when catalogue mode, `/shop/cart` shows unavailable page, `/shop/api/cart` and `/shop/checkout` return 403
- [ ] Back up DB: cPanel → Backups

---

## 7. Running on subfolder (e.g. example.com/shop)

App auto-detects base path, so upload into `public_html/shop/` works. Ensure `.env`/config reachable at `../app` relative to `index.php`. Service Worker prefixes cache with base path.

---

## 8. Troubleshooting

| Symptom | Fix |
| --- | --- |
| Blank page / 500 | Enable APP_DEBUG=true temporarily, check storage/logs/php-error.log |
| Could not connect to DB | Verify host (localhost vs remote), user privileges, password |
| Rewrites 404 on /login | Confirm mod_rewrite enabled and .htaccess present |
| MySQL 5.7 errors | Schema targets MySQL 8/MariaDB 10.3+; ask host upgrade |
| SQLite error on cPanel | Set DB_DRIVER=mysql in .env |
| WhatsApp button not showing | Settings → WhatsApp & Online → set sales number (07...), enable WhatsApp ordering, Save |
| WhatsApp link invalid number | Use Kenyan formats 07XXXXXXXX or 2547XXXXXXXX or +2547XXXXXXXX; check normalized hint wa.me/254... |
| Cart still accessible when catalogue mode | Ensure online_checkout_enabled=0 in DB shop_settings; clear cache; check ShopController cart() guard |
| OG image not showing in WhatsApp | Ensure product has primary image, url() returns absolute HTTPS (base_url https), check og:image meta in page source |
| whatsapp_enquiries table missing | Re-import schema.sql + run php tools/install.php (no --fresh) → ensureWhatsappEnquiries creates table + extra columns |
| Sale source not showing | Ensure sales.sale_source column exists (migrateColumns adds it); check POS sale_source dropdown + Sale model buildWhere |
| Reports missing WhatsApp KPIs | Ensure whatsapp_enquiries table exists + sales.whatsapp_enquiry_id column; ReportController metrics counts total/converted |
| M-Pesa online still prompting when disabled | Ensure mpesa_online_enabled=0; ShopController checkout checks is_mpesa_online_enabled() and voids + 403 |
| POS sale source not saved | Ensure PosController checkout payload includes sale_source + whatsapp_enquiry_id; SaleService normalizeOpts allows walk-in/whatsapp/phone/other/online |
