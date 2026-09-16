# Khamis Computers — POS + Online Shop

A **connected Point-of-Sale (POS) and online shop** for a computer & electronics
retailer. The physical shop and the web store read and write the **same
inventory** in real time, and the POS keeps working **offline** when the
internet drops (queue-and-sync).

Built with **plain HTML, CSS & JavaScript** on the front end and **PHP + MySQL**
on the back end, structured to deploy on **cPanel** with no build step.

> ✅ **All 7 pieces are complete.** Khamis Computers is a fully working,
> connected POS + online shop — foundation, inventory, POS, online shop,
> offline mode, orders/reports, and polish are all built and tested.

---

## What it does

| Area | Detail |
| --- | --- |
| **POS terminal** | Cashier cart, barcode/serial search, discounts, receipt printing |
| **Online shop** | Storefront catalog, cart, **guest checkout** — same stock as the shop |
| **Shared inventory** | One database for both channels; a web sale instantly reduces POS stock |
| **Serial / IMEI tracking** | High-value units (laptops, phones) are tracked per serial number |
| **Offline POS** | Service Worker + IndexedDB cache; sales queue locally and sync later |
| **Returns & warranty** | Return lifecycle tied to serial numbers; per-unit warranty expiry |
| **Sale void / cancel** | Admins void a mistaken sale in one click — stock is restored automatically |
| **Discount authorisation** | Optional manager PIN gate for discounts above a set threshold |
| **CSV export** | One-click CSV downloads for sales (filtered), products, and reports |
| **Suppliers** | Supplier directory; GRNs link to a saved supplier (name snapshotted) |
| **Product images** | Per-product image gallery; uploaded images shown across the shop |
| **Delivery fees** | Zone-based delivery charges, resolved server-side at online checkout |
| **Email notifications** | HTML order confirmations + low-stock alerts (PHP mail or SMTP) |
| **M-PESA STK push** | "Pay now" at online checkout via Safaricom Daraja, with auto status update |
| **Order tracking** | Customers re-fetch a past order by number + phone on the shop |
| **Z-report / close-of-day** | Per-cashier till reconciliation with a saved daily snapshot |
| **Reporting** | Sales history, low-stock alerts, revenue dashboard |

**Business rules** (confirmed with the owner):
- Currency **KSh**, prices exclusive of **16% VAT** (VAT shown on receipts).
- Inventory tracked **at serial/IMEI level** for serialized products.
- Online customers check out as **guests** (no accounts required).

---

## How offline mode works (Piece 5)

The POS terminal keeps working when the internet drops:

1. **App shell caching** — a Service Worker (`sw.js`) caches the POS screen and
   assets, so the terminal still loads offline.
2. **Offline catalogue** — the active product list (with prices and serial
   numbers) is cached in IndexedDB, so search and serial selection work offline.
3. **Offline sales queue** — when checkout can't reach the server, the sale is
   saved locally with a `client_ref` + `device_id` and the cashier gets a
   printable "pending sync" receipt.
4. **Auto-sync** — when the connection returns (or every 30s), queued sales
   POST to `/pos/sync`, which re-validates stock and prices server-side.
   `client_ref` + `device_id` make the sync **idempotent**: even if a sale is
   sent twice, it is only recorded once.

> Note: Service Worker / IndexedDB require a real origin, so they activate when
> the app is deployed to cPanel over HTTPS (or `localhost`). In the sandboxed
> preview the offline *logic* still works via in-memory + `navigator.onLine`.

## Roles & permissions (Piece 7)

Two staff roles. Cashiers sell; admins also manage inventory, staff and settings.

| Capability | Admin | Cashier |
| --- | :-: | :-: |
| POS, sales, returns, expenses | ✅ | ✅ |
| View products & stock (read-only) | ✅ | ✅ |
| Create/edit products, add units, stock adjustments, GRN stock-in | ✅ | ❌ |
| Categories, brands, suppliers | ✅ | ❌ |
| Barcode/label generation & printing | ✅ | ❌ |
| Create returns | ✅ | ✅ |
| Approve / reject returns | ✅ | ❌ |
| Delete expenses | ✅ | ❌ |
| Void sale / M-PESA mark-paid & retry | ✅ | ❌ |
| Reports (revenue, profit, VAT, purchases by supplier) | ✅ | ❌ |
| Z-report | any staff member | own day only |
| Settings (shop details, VAT) | ✅ | ❌ |
| Staff management | ✅ | ❌ |
| Delivery zones | ✅ | ❌ |

Admins manage staff under **Staff** (add, promote, deactivate, reset passwords,
delete) with self-protection rules (can't demote/deactivate/delete yourself or
the last admin). Shop settings (name, tagline, contact, VAT rate, receipt
footer) live in the **Settings** page; the VAT rate applies to new sales
immediately while old receipts keep their stored tax.

## Receipts & printing

Every sale can be downloaded as a real PDF receipt (`/sales/{id}/pdf`) — no
external libraries, a ~100-line built-in PDF writer keeps the stack
dependency-free.

For the physical shop, there's also a **thermal receipt printer mode**
(`/sales/{id}/print`): a standalone, monospace, **80mm** till-style receipt
(shop header → receipt/date/cashier → items with serials → subtotal/VAT/total →
payment & footer) that auto-opens the browser's print dialog. It prints on
standard 80mm thermal rolls exactly like a supermarket receipt; for 58mm
printers change the single `size: 80mm` value to `58mm`. The "Print receipt"
buttons on the POS receipt, the sale detail page, and the offline receipt all
use this format. In the browser's print dialog, select your thermal printer and
set margins to "None" (the `@page` rule already zeroes them).

### Barcode labels

Every product page has **Print labels** (shelf price labels — name, SKU, price
and a Code 128 barcode) and, for serialized items, **Serial labels** (one
sticker per serial/IMEI). The barcode is generated in-app — a dependency-free
Code 128 encoder (`app/core/Barcode.php`) outputs inline SVG, so no GD library
or CDN is needed and it works on any cPanel host. Labels print on an A4 sheet
grid (63×33mm shelf / 46×22mm serial stickers) or, with the "Thermal 60×40"
toggle, one label per page for a thermal label printer. If your label stock
differs, the sizes live in two commented blocks at the top of
`app/views/products/labels.php`.

The full barcode lifecycle is covered: on the product form a **Generate**
button creates a unique internal barcode; for serialized products you can
**auto-generate serials** (for items without a real IMEI) as well as paste real
ones; and a **"print labels right after saving"** checkbox jumps straight to the
label sheet so you can print and stick immediately. At the till, scanning a
shelf barcode finds the product and scanning a serial sticker picks that exact
unit. In the **online shop**, serialized orders automatically take the next
available unit (and it leaves inventory); bulk products decrement quantity.

## VAT report (KRA)

`/reports/vat` (admin only) produces a monthly **VAT return** summary: **output
VAT** (the VAT actually charged on completed sales), **VAT on returns** (credited
back, re-taxed at each sale's effective rate) and **input VAT** (estimated at the
configured rate on goods-received costs). It shows a daily breakdown, the
underlying sales/purchases/returns detail, and exports a KRA-friendly CSV with
the same detail. Assumptions (VAT-exclusive purchase costs, expenses excluded)
are stated on the page and in the CSV.

## Purchases by supplier

`/reports/purchases` (admin only) groups goods-received notes by supplier for a
date window: **deliveries**, **net purchases** (`unit cost × quantity`,
VAT-exclusive), **estimated input VAT**, each supplier's **share** of total
purchases and **last delivery**. GRNs linked to a saved supplier roll up under
that supplier's current name; GRNs where a name was typed free-hand are grouped
by that typed name (so one-off vendors still appear). Click a supplier to drill
into their deliveries and line items, and export the whole thing to CSV.

## Suppliers, images, delivery, email & M-PESA (Piece 8)

Five strengthening features, each configurable in **Settings**:

- **Suppliers** (`/suppliers`) — a supplier directory used by goods-received
  notes. The GRN form lists saved suppliers; the chosen supplier's name is
  snapshotted onto the GRN, so renaming a supplier later never rewrites history.
  Suppliers with GRN history are deactivated instead of deleted.
- **Product images** — each product gets a gallery (upload, make-primary,
  remove) on its detail page. Images are stored in `storage/uploads/products/`
  (web-denied) and streamed through a path-traversal-safe route at
  `uploads/p/{filename}`. The primary image appears on shop cards, the product
  page (with a thumbnail picker) and everywhere `product_thumb()` is used.
- **Delivery fees (zone-based)** — manage zones (name + flat fee) under
  Settings → Delivery zones. At online checkout the customer picks their area;
  the fee is resolved **server-side** from the zone (the client can never set
  its own price) and is snapshotted onto the order along with the zone name.
  VAT is unchanged — the fee is a flat charge on top of the goods total.
- **Email notifications** — a dependency-free `Mailer` service sends HTML mail
  via PHP `mail()` (works out of the box on cPanel) or SMTP (STARTTLS/SSL +
  AUTH LOGIN for Gmail/Office 365). Two emails: **order confirmation** to the
  customer after an online order, and **low-stock alerts** to the shop owner
  when a product drops to/below the threshold (at most once per product per
  24h). Sending is best-effort and never blocks a sale.
- **M-PESA STK push** — configure your Daraja app (shortcode, passkey,
  consumer key/secret, sandbox vs live) under Settings → M-PESA. Online
  customers can choose **"Pay now with M-PESA"**: the sale is created as
  `pending`, an STK prompt is pushed to their phone, and Safaricom's callback
  (`POST /mpesa/callback`) flips it to `completed` with the receipt number.
  Admins have **Mark paid** (manual fallback) and **Re-send prompt** buttons on
  pending orders. If the STK request itself fails, the reserved stock is
  voided automatically and the customer is asked to retry.

## Order tracking, Z-reports & go-live hardening (Piece 9)

- **Customer order tracking** — a public **Track your order** page (footer link on
  the shop) lets a customer re-fetch their order by number + phone number
  (matched on the last 9 digits, online orders only, rate-limited).
- **Z-report / close-of-day** — **Z-report** in the admin nav (also available to
  cashiers for their own shift) shows a live per-day summary: sales count and
  total, cash / M-PESA / card / bank breakdown, discounts, voids, refunds and
  the **expected cash** in the till. "Close day" saves a snapshot (one per
  cashier per day) so the figures survive later edits; admins can view any
  staff or all.
- **Timezone** — all timestamps, "today" stats and receipt times use the shop's
  timezone, defaulting to `Africa/Nairobi` (override with `APP_TIMEZONE`).
- **Deployment hardening** — `app/`, `schema/`, `tools/` and `storage/` ship
  with `Require all denied` `.htaccess` files, the web root denies dotfiles
  (so `.env` can't leak), and `router-dev.php` refuses to serve outside
  development. See `docs/cpanel-deployment.md`.

## Security & data-integrity hardening

The codebase went through a full audit (`AUDIT.md`) and the fixes are in:

- **Installer guard** — `/install` refuses to run once the system is installed
  (no post-install admin minting).
- **Login lockout** — per email+IP rate limiting (5 tries → 15-min ban).
- **Logout** is now POST + CSRF (GET returns 404).
- **Demo credentials** are hidden when `APP_ENV=production`.
- **Security headers** (CSP, HSTS, X-Content-Type-Options, etc.) + `Options -Indexes`.
- **Session hardening** (strict mode, cookie-only, regenerate-on-login).
- **Activity / audit log** (`activity_log`) for logins, staff & settings changes.
- **Offline-sync dedup** enforced at the DB level with a `UNIQUE (device_id, client_ref)`
  index (plus a cleanup of any pre-existing duplicates).
- **Sequence-number races** — sale, GRN and return numbers retry on collision
  against their `UNIQUE` constraints instead of double-assigning.
- **Return integrity** — `qty`/`amount` keyed to the exact sale item (no
  misalignment), and refunds are capped at the returned line total.
- **Sales filter** — the "Offline" filter actually maps to `offline_created = 1`.
- **Product safety** — serial-tracking mode can't be toggled on a product with
  history, and products with GRN history can't be deleted.
- **Offline subfolder support** — the Service Worker now prefixes its cache with
  the app's base path, so it works deployed in a cPanel subfolder.

### Full-system integration audit (2026-09-09)

Every feature was re-audited end-to-end and every bug found was fixed
(14 issues, see the "Integration Audit Round" section in `AUDIT.md`). Highlights:

- Web installer now seeds delivery zones (previously only the CLI did).
- Serialized units can no longer be double-sold or hand-marked sold; sold units
  can't be deleted or status-changed outside the returns/void flows.
- Returns are only accepted against completed sales; "ghost" returns with no
  returnable items are rejected.
- Products/categories/brands delete cleanly on MySQL (no orphaned rows).
- Settings no longer leak the SMTP password or M-PESA secret into page HTML.
- Low-stock alerts retry after a failed send instead of going quiet for 24h.

A repeatable test harness now lives in `tools/` (`stock-check.php`,
`integration-test.php`, `http-test.sh`, `route-smoke.sh`, `flow-test.sh`,
`rbac-gate.sh`). Each script reports its own pass count and the database is
restored to baseline after each run; `rbac-gate.sh` verifies the admin/cashier
role gates over HTTP (cashiers are denied reports, VAT, purchases and CSV
exports, and every admin-only POST changes no data).

## Tech stack

- **Backend** — PHP 8+ (vanilla, PDO + prepared statements), no Composer.
- **Database** — MySQL (cPanel) — also runs on SQLite for quick local previews.
- **Frontend** — HTML + CSS + vanilla JS. No frameworks, no build tools.
- **Design** — Apple-inspired: blue & white, blurred sticky nav, pill buttons,
  generous whitespace, big typography (`#0071e3` accent on `#f5f5f7`).
- **Offline** — Service Worker + IndexedDB (added in Piece 5).
- **Security** — `password_hash()` (bcrypt) + auto-rehash, CSRF tokens on every
  state-changing route (logout included, now POST-only), prepared statements,
  session hardening (strict mode, regenerate-on-login), **login rate-limiting /
  lockout** (per email+IP, 5 tries → 15-min ban), CSP + security headers, and an
  **activity/audit log** (logins, staff & settings changes).

---

## Project structure

```
khamis-computers/
├── public_html/              ← upload THIS to cPanel's public_html
│   ├── index.php             ← front controller (all routes)
│   ├── .htaccess             ← rewrite rules + security headers
│   ├── router-dev.php        ← only for local `php -S` (unused on cPanel)
│   └── assets/               ← css, js, images
├── app/
│   ├── bootstrap.php         ← boot, autoload, session, error handling
│   ├── config/config.php     ← all configuration
│   ├── helpers/functions.php
│   ├── core/                 ← Database, Auth, Csrf, Router, View, Schema
│   ├── controllers/
│   └── views/                ← layouts, partials, pages
├── schema/schema.sql         ← full MySQL schema (shared POS + shop)
├── tools/install.php         ← CLI installer (local/preview)
├── storage/                  ← logs, uploads, SQLite dev DB (web-denied)
├── .env / .env.example       ← environment overrides
└── docs/cpanel-deployment.md ← step-by-step cPanel guide
```

---

## Quick start (local preview)

Requires **PHP 8+** with `pdo_sqlite` (and `pdo_mysql` for production).

```bash
git clone <repo-url> khamis-computers && cd khamis-computers

# 1. Set up the local SQLite database + demo data
php tools/install.php --fresh

# 2. Run the dev server
php -S 0.0.0.0:8080 -t public_html public_html/router-dev.php

# 3. Open http://localhost:8080
```

**Demo login:** `admin@khamis.local` / `admin1234` (admin) ·
`cashier@khamis.local` / `cashier1234` (cashier)

To run against **MySQL** instead, copy `.env.example` to `.env`, set
`DB_DRIVER=mysql` and your credentials, then re-run `php tools/install.php --fresh`.

---

## Deploying to cPanel

Full step-by-step guide: **[docs/cpanel-deployment.md](docs/cpanel-deployment.md)**.

Short version:

1. Create a MySQL database + user in cPanel.
2. Upload the project into `public_html` (or a subfolder).
3. Create `.env` (or edit `app/config/config.php`) with the DB credentials.
4. Import `schema/schema.sql` via **phpMyAdmin** (or hit `/install` once).
5. Delete the `install` route + `tools/` folder in production.

---

## Build roadmap

| # | Piece | Status |
| --- | --- | --- |
| 1 | **Foundation** — config, database, auth, shell, installer | ✅ done |
| 2 | **Inventory** — categories, brands, products, serial/IMEI units, GRN receiving, low-stock | ✅ done |
| 3 | **POS terminal** — cart, serial picker, discounts, VAT, payments, receipt | ✅ done |
| 4 | **Online shop** — storefront, cart, guest checkout, pickup/delivery (shared stock) | ✅ done |
| 5 | **Offline POS** — service worker, IndexedDB catalogue, sale queue, auto-sync | ✅ done |
| 6 | **Orders & reports** — sales history, returns workflow, expenses, analytics | ✅ done |
| 7 | **Polish** — roles/permissions, staff management, settings, PDF receipts | ✅ done |
| 8 | **Strengthening** — supplier directory, product image gallery, zone-based delivery fees, email notifications (order confirmation + low-stock), M-PESA STK push | ✅ done |
| 9 | **Operations & go-live** — timezone config, deployment hardening, customer order tracking, Z-report / close-of-day | ✅ done |
| 10 | **Integration audit** — feature-by-feature end-to-end verification, 14 bugs fixed, automated test harness in `tools/` | ✅ done |
| 11 | **VAT report + barcode labels** — KRA-style output/input VAT report with CSV export, and printable Code 128 product & serial/IMEI labels (A4 sheet or 60×40mm thermal) | ✅ done |
| 12 | **Purchases by supplier** — GRNs grouped by supplier (saved + free-text), net purchases, est. input VAT, share, drill-down & CSV export | ✅ done |

---

## License

MIT — free to use and modify for your shop.
