# Khamis Computers — System Workflows

A complete map of every workflow in the system, grouped by who performs it.
All prices are stored **exclusive of VAT** (KSh) and VAT (default 16%) is added at
sale time. Serialized products are tracked per unit by **serial/IMEI**.

Roles: **admin** and **cashier** only — there are no customer accounts. Online
shoppers are always **guests**.

---

## 1. First run — installation

1. Open the site with an empty database → everything redirects to `/install`.
2. The installer shows the detected DB driver and a form: **name, email, password**.
3. On submit it: creates the schema → migrates columns → creates indexes →
   seeds baseline settings → seeds default delivery zones → creates the first
   **admin** user (bcrypt-hashed password).
4. Redirect to `/login` with a success message.

> **Security:** the installer refuses to run once the system is installed
> (`Schema::isInstalled()`). Delete the install controller/routes after first
> deploy so the route can't be abused.

---

## 2. Sign-in / sign-out

- `GET /login` → sign-in form. `POST /login` → verifies email + password
  against `users`, rejects inactive accounts, regenerates the session id.
- `POST /logout` → ends the session (CSRF-protected; there is no GET logout).
- Unauthenticated users hitting a protected page are redirected to `/login`.

---

## 3. Admin configuration (admin only)

### Settings — `GET /settings`
One page controlling the whole business:
- **Shop info:** name, tagline, address, phone, email, receipt footer.
- **VAT rate** (default 16%) and **discount PIN threshold**.
- **Manager discount PIN** — a hashed PIN required for large POS discounts.
- **Low-stock threshold** (drives low-stock alerts).
- **Email/SMTP** — transport, host, port, encryption, username/password, from.
  Passwords are blanked on re-open (never echoed).
- **M-PESA** — enable, sandbox/live, paybill/till, shortcode, consumer
  key/secret, passkey. Secrets blanked on re-open.
- **Delivery zones** — add/edit/delete zones with a fee (used by online delivery).

### Staff — `GET /staff`
- Create staff (name, email, role **admin/cashier**, password).
- Edit, reset password, deactivate/activate, delete.
- **Self-protection:** you can't demote or delete your own account, and the last
  active admin can't be removed.

### Catalogue — categories, brands, suppliers
- **Categories** `/categories`, **Brands** `/brands` — create/rename/delete.
  Deleting unlinks products (sets `category_id`/`brand_id` to NULL) so products
  are never lost.
- **Suppliers** `/suppliers` — create/edit/delete. Suppliers with goods-received
  history are **deactivated instead of deleted** to preserve records.

---

## 4. Stock intake (getting goods into the system) — admin only

### A. Bulk (non-serialized) product — `products/new`
1. Create the product: name, SKU, barcode, category, brand, supplier, cost
   price, selling price, low-stock threshold, description, images.
2. Add quantity later with **Adjust stock** (`POST /products/{id}/adjust`, +/−)
   or receive via GRN. Every change writes a `stock_movements` journal row.

### B. Serialized product (phones, laptops…) — `products/new`
1. Create the product with **"Track serials/IMEIs" = yes**.
2. **Add units** (`POST /products/{id}/units`): paste serial numbers (one per
   line, duplicates rejected). Each serial becomes an `inventory_units` row
   with status `in_stock`. Stock = count of in-stock units.
3. Unit actions on the product page:
   - **Set status** — `sold`, `damaged`, `lost`, `returned`, `in_stock`. Manual
     attempts to set a unit to `sold` are blocked (only a real sale can do that),
     and tampering with a unit that has sale/return history is blocked.
   - **Delete unit** — blocked if the unit has sale/return history.

### C. Goods received note (GRN) — `GET /grn/new`
1. Pick supplier (or type "walk-in supplier"), date, note.
2. Add line items: product + quantity + unit cost.
3. Submit → stock is received (bulk products get quantity; serialized get a
   movement journal entry), a GRN record is saved for the supplier audit trail,
   and the supplier's purchase history is retained.

### D. Product images
Upload multiple images per product, set one as **primary** (shown in the online
shop grid), delete images. Images are served publicly via `uploads/p/{file}`.

---

## 5. Physical shop — the cashier's day (POS)

`GET /pos` — split screen: catalogue/search on the left, cart on the right.

1. **Find product** — type (name/SKU/barcode) into live search, or scan a
   barcode. Serialized products show their **available serial numbers** to pick.
2. **Build cart** — choose quantity; for serialized items select the exact
   units. Prices come from the database (client can't set them).
3. **Optional customer details** — name/phone are optional ("Walk-in").
4. **Discount** — apply an amount; if it reaches the threshold, a **manager PIN**
   is required.
5. **Payment** — cash / M-PESA / card, optional reference.
6. **Complete sale** →
   - Stock is reserved and committed in one transaction (bulk qty decremented,
     serialized units marked `sold`, `sale_items` written, VAT computed at the
     configured rate).
   - Redirect to the receipt screen `/pos/receipt/{id}`.
7. **Receipt** — on-screen, **80mm thermal print** (`/sales/{id}/print`), or
   A4 PDF download. Reprintable any time from the sales list.

### Offline (the key requirement)
- A **service worker + IndexedDB** cache the full catalogue, so the cashier can
  keep selling with **no internet**.
- Offline checkouts are queued locally with a temporary reference; the customer
  still gets a receipt (marked "Offline sale — pending sync").
- When the connection returns, `POST /pos/sync` pushes the queued sales. Sync is
  **idempotent** (each offline sale is stored once, keyed by device + client
  ref) and resolves serial numbers to unit ids at sync time.

### Voiding a sale — `POST /sales/{id}/void` (admin only)
- Completes only if the sale is still `completed`.
- Restores stock exactly: bulk quantity back, serialized units back to
  `in_stock`; writes compensating `voided` journal rows. Sale status →
  `cancelled`.

---

## 6. Online shop — guest journey

Public, no login. Uses the same products, stock and `SaleService` as the POS.

1. **Browse** — `/shop` home (featured), `/shop/products` with search, category,
   brand filters and sorting. Serialized products sell while a unit is in stock.
2. **Product page** — images, price, live stock, related products.
3. **Cart** — stored in the browser; a JSON API re-prices from the DB and
   checks live stock (`/shop/api/cart`).
4. **Checkout** (`POST /shop/checkout`) — guest details:
   - **Name, phone required**, email optional.
   - **Fulfillment:** pickup or delivery (delivery requires an address and a
     delivery zone; the fee is resolved **server-side** so a client can't invent
     a price).
   - **Payment:** cash on pickup/delivery, or **M-PESA pay now**.
   - Serialized items are **auto-assigned** from in-stock units (no account, so
     the customer can't pick serials).
5. **Confirmation** — order page shows status and, for M-PESA, polls payment
   status live.
6. **Order tracking** — `/shop/track`: enter order number + phone to look the
   order up later (rate-limited: 10 tries/10 min/session).
7. **Email** — a confirmation email is sent (best-effort; failure never blocks
   checkout).

---

## 7. Payments

### Cash
Used in-store and for online pickup/delivery. Online orders paid in cash are
marked **completed** immediately and cash is collected at delivery/pickup.

### M-PESA (Lipa na M-PESA via Safaricom Daraja)
- **Online "pay now":** the sale is created as `pending`, an **STK push** is
  sent to the customer's phone. If the push fails, the reserved stock is
  **voided** and the customer is asked to retry.
- **Callback** `/mpesa/callback` (public, called by Safaricom): verifies the
  transaction and marks the sale **completed** + records the M-PESA receipt
  number as `payment_ref`. Always acknowledges so Safaricom stops retrying.
- **Admin fallbacks** on the sale detail page: **mark paid** (manual, if the
  callback never arrived) and **retry STK push** — both admin-only.

---

## 8. Sales management

`GET /sales` — list with search, status filter, date range; `GET /sales/{id}`
detail with items, serials, payment, and any linked return.

- **Export CSV** (`/sales/export`).
- **PDF receipt** `/sales/{id}/pdf` and **thermal print** `/sales/{id}/print`.
- **Void** (admin) and **M-PESA actions** (admin) as above.

---

## 9. Returns & warranty

`GET /returns/new` → pick a **completed** sale → pick the items (the picker only
shows items not already returned and filters to completed sales).

1. **Create return** — items + quantities + refund amounts + reason (warranty,
   wrong item, defective…). A return number is generated. Ghost returns against
   non-completed sales are blocked.
2. **Approve** — restores stock (bulk qty returned; serialized units set back to
   `in_stock` with a note) and marks the return `completed` with refund recorded.
3. **Reject** — no stock change; status `rejected`.

Returns appear on the sale detail and feed the **refunds** line in reports.

---

## 10. Expenses

`GET /expenses` — record business expenses (description, amount, category, date)
and list them. Deleting is **admin-only**. Expenses feed the **net profit**
figure in reports.

---

## 11. Reporting

### Dashboard — `/dashboard`
Today's sales, revenue, low-stock list, recent orders.

### Full reports — `/reports` (admin only)
Date-range metrics: revenue, orders, average order value, refunds, expenses,
cost of goods sold, **gross & net profit**, revenue-by-day chart, top products,
low-stock. Exportable to CSV (`/reports/export`).

### Z-report (close of day) — `/reports/z`
- Per-cashier daily summary: sales count, revenue, voids, returns, payment
  breakdown, opening/closing amounts.
- **Close day** locks that cashier's day into a saved `z_reports` record.
- **Admins** can close any staff member's day and view all history.
- **Cashiers** can only see and close **their own** day.

---

## 12. Alerts & notifications

- **Low stock:** after any stock change, if a product drops to the threshold,
  an alert is recorded and an email is sent (best-effort). Alerts are
  de-duplicated — only recorded after a successful send.
- **Activity log:** key events are logged (`system.installed`, `sale.voided`,
  `return.approved`, `zreport.closed`, staff/supplier changes…) for auditing.

---

## 13. Permission matrix

| Action | Cashier | Admin |
|---|:--:|:--:|
| POS, checkout, receipts, search | ✅ | ✅ |
| Products view (read-only) | ✅ | ✅ |
| Product create/edit, units, stock adjust, images | ❌ | ✅ |
| GRN (goods received) | ❌ | ✅ |
| Categories, brands, suppliers | ❌ | ✅ |
| Barcode/label generation & printing | ❌ | ✅ |
| Sales list/detail, PDF, thermal print, export | ✅ | ✅ |
| Create returns | ✅ | ✅ |
| Approve/reject returns | ✅ | ✅ |
| Expenses record | ✅ | ✅ |
| Expenses delete | ❌ | ✅ |
| Void sale | ❌ | ✅ |
| M-PESA mark-paid / retry | ❌ | ✅ |
| Reports (full) | ❌ | ✅ |
| Z-report | own day only | any + close any |
| Settings | ❌ | ✅ |
| Staff management | ❌ | ✅ |
| Delivery zones | ❌ | ✅ |

Public (no login): online shop browse/cart/checkout/track, M-PESA callback,
product images.
