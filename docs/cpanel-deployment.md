# Deploying Khamis Computers to cPanel

This project is designed for a standard shared **cPanel** host running
**Apache + PHP 8 + MySQL/MariaDB**. No Composer, no Node, no build step.

---

## 1. Create the database in cPanel

1. Log in to cPanel → **MySQL® Databases**.
2. Create a database, e.g. `youruser_khamis`.
3. Create a database user, e.g. `youruser_khamisapp` with a strong password.
4. **Add the user to the database** and grant **ALL PRIVILEGES**.
5. Note the **host** — on most shared hosts it is `localhost`.

---

## 2. Upload the files

Upload the **contents** of `public_html/` into cPanel's `public_html/`
(File Manager or FTP). Upload `app/`, `schema/`, `storage/` and the `.env`
**outside** `public_html` (i.e. in `/home/youruser/`) so they are not web-accessible.

Recommended layout on the server:

```
/home/youruser/
├── public_html/            ← contents of the repo's public_html/
│   ├── index.php
│   ├── .htaccess
│   └── assets/
├── app/                    ← repo's app/
├── schema/                 ← repo's schema/
├── storage/                ← repo's storage/
└── .env                    ← your real credentials
```

> `public_html/index.php` expects `app/` to live at `../app` — this layout
> satisfies that. If you upload everything inside `public_html/` instead,
> protect `app/`, `schema/`, `storage/` and `.env` from web access (see §5).

---

## 3. Configure credentials

Create `/home/youruser/.env` (or edit `app/config/config.php` directly):

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
1. cPanel → **phpMyAdmin** → select the database.
2. **Import** → choose `schema/schema.sql` → Go.
3. **Then run the installer once** (Option B below, or
   `php tools/install.php` over SSH). The installer creates the **secondary
   indexes** (including the `UNIQUE (device_id, client_ref)` offline-dedup
   index) and **seeds your admin + cashier accounts** — neither is in the
   schema file itself.

> Schema statements are idempotent (`CREATE TABLE IF NOT EXISTS`), so re-importing
> after pulling an update safely adds new tables (e.g. `throttle`, `activity_log`,
> `suppliers`, `product_images`, `delivery_zones`, `low_stock_alerts`,
> `mpesa_transactions`) without touching your data. **Re-run the installer once
> after any upgrade** so new indexes are created too — with SSH access use
> `php tools/install.php` (no `--fresh`); it preserves your data.

### Product-image uploads

Uploaded product images are written to `storage/uploads/products/` — the
`storage/.htaccess` already denies web access, and the app streams them through
the `/uploads/p/{filename}` route. Make sure the `storage/uploads/` folder is
**writable by PHP** (`755` usually suffices with the right owner; use `775` if
needed).

### M-PESA STK push (Daraja)

To enable "Pay now with M-PESA" at online checkout:

1. Create an app on the **Safaricom Daraja developer portal** and get the
   consumer key, consumer secret, passkey and shortcode.
2. Enter them in **Settings → M-PESA**, choose sandbox/live, and enable it.
3. Set the **Confirmation/Callback URL** on the Daraja portal to
   `https://yourdomain.com/mpesa/callback` (the Settings page shows the exact
   URL). The callback is the only way orders flip from *pending* to *paid*, so
   the URL must be reachable from the internet and the server needs outbound
   HTTPS to `api.safaricom.co.ke` (live) / `sandbox.safaricom.co.ke`.
4. Outbound HTTPS requires the PHP **cURL** extension (standard on cPanel).
5. If a payment is confirmed by SMS but the callback was missed, an admin can
   open the pending sale and click **Mark paid**.

### Email notifications

**Settings → Email notifications** turns on order confirmations and low-stock
alerts. The default transport is PHP `mail()` (works on cPanel out of the box);
for Gmail/Office 365 or a custom relay, switch to **SMTP** and fill in the
host/port/encryption/credentials. Test with a real order after configuring.

**Option B — one-time web installer:**
1. Visit `https://yourdomain.com/install`.
2. Fill in your admin name, email and password → **Install**.

> After using the web installer, **remove it** (see §6).

---

## 5. Lock down non-public folders

The repo already ships `storage/.htaccess` with `Require all denied`.
If you uploaded `app/`, `schema/` and `.env` inside the web root, add a
`.htaccess` in each (or at the `public_html` root) containing:

```
Require all denied
```

**Never** expose `.env` — it holds your database password.

---

## 6. Post-deploy hardening checklist

- [ ] Delete the `/install` route: remove the `install` route lines in
      `public_html/index.php` and delete `app/controllers/SetupController.php`.
      (The installer now also refuses to run once installed — belt and braces.)
- [ ] Delete `tools/` and `public_html/router-dev.php` (dev-only).
- [ ] Change the demo admin password, or create your own admin.
- [ ] Force HTTPS via cPanel (AutoSSL / Let's Encrypt), then optionally add an
      HTTPS redirect in `.htaccess`.
- [ ] Set `APP_DEBUG=false` and confirm `/login` shows no PHP warnings.
- [ ] Back up the database: cPanel → **Backups**.

---

## 7. Running on a subfolder (e.g. example.com/shop)

The app auto-detects its base path, so you can upload into
`public_html/shop/` and everything (links, assets, redirects) will work.
Just make sure the `.env` / config is reachable at `../app` relative to the
folder holding `index.php`.

---

## 8. Troubleshooting

| Symptom | Fix |
| --- | --- |
| Blank page / 500 | Enable `APP_DEBUG=true` temporarily, check `storage/logs/php-error.log` |
| "Could not connect to the database" | Verify host (`localhost` vs remote), user privileges, password |
| Rewrites not working (404 on `/login`) | Confirm Apache `mod_rewrite` is enabled and `.htaccess` is in place |
| MySQL 5.7 errors | The schema targets MySQL 8/MariaDB 10.3+; ask your host to upgrade |
| SQLite error on cPanel | You forgot to set `DB_DRIVER=mysql` in `.env` |
