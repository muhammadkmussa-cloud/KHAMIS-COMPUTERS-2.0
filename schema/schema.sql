-- ============================================================================
-- Khamis Computers — full MySQL schema (also runs on SQLite for local preview)
-- One shared database serves BOTH the POS terminal and the online shop.
-- Statements are separated by the @@kc@@ marker so the installer can split them.
-- ============================================================================

-- @@kc@@
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'cashier',   -- admin | cashier
    is_active       TINYINT      NOT NULL DEFAULT 1,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS shop_settings (
    id                INTEGER PRIMARY KEY AUTO_INCREMENT,
    setting_key       VARCHAR(80) NOT NULL UNIQUE,
    setting_value     TEXT NULL,
    updated_at        DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS categories (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(120) NOT NULL,
    slug          VARCHAR(140) NOT NULL UNIQUE,
    description   TEXT NULL,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    is_active     TINYINT NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS brands (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(120) NOT NULL UNIQUE,
    is_active     TINYINT NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS products (
    id             INTEGER PRIMARY KEY AUTO_INCREMENT,
    name           VARCHAR(190) NOT NULL,
    sku            VARCHAR(80) NOT NULL UNIQUE,
    category_id    INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
    brand_id       INTEGER NULL REFERENCES brands(id) ON DELETE SET NULL,
    cost_price     DECIMAL(12,2) NOT NULL DEFAULT 0,
    sell_price     DECIMAL(12,2) NOT NULL DEFAULT 0,   -- excludes VAT
    barcode        VARCHAR(80) NULL UNIQUE,
    description    TEXT NULL,
    is_active      TINYINT NOT NULL DEFAULT 1,
    is_serialized  TINYINT NOT NULL DEFAULT 0,         -- 1 = requires serial/IMEI per unit
    warranty_months INTEGER NULL,                      -- standard warranty period (months)
    image          VARCHAR(255) NULL,                  -- primary product image filename
    reorder_level  INTEGER NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL,
    updated_at     DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS product_images (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    product_id    INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    filename      VARCHAR(255) NOT NULL,
    alt_text      VARCHAR(255) NULL,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS delivery_zones (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(120) NOT NULL UNIQUE,
    fee           DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active     TINYINT NOT NULL DEFAULT 1,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS low_stock_alerts (
    id             INTEGER PRIMARY KEY AUTO_INCREMENT,
    product_id     INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    stock_quantity INTEGER NOT NULL DEFAULT 0,
    sent           TINYINT NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id                  INTEGER PRIMARY KEY AUTO_INCREMENT,
    sale_id             INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
    checkout_request_id VARCHAR(64) NULL,
    merchant_request_id VARCHAR(64) NULL,
    phone               VARCHAR(20) NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    receipt_number      VARCHAR(64) NULL,
    result_code         INTEGER NULL,
    result_desc         VARCHAR(255) NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'requested',   -- requested | success | failed
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS z_reports (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    report_date     DATE NOT NULL,
    sales_count     INTEGER NOT NULL DEFAULT 0,
    total_sales     DECIMAL(12,2) NOT NULL DEFAULT 0,
    cash_sales      DECIMAL(12,2) NOT NULL DEFAULT 0,
    mpesa_sales     DECIMAL(12,2) NOT NULL DEFAULT 0,
    card_sales      DECIMAL(12,2) NOT NULL DEFAULT 0,
    bank_sales      DECIMAL(12,2) NOT NULL DEFAULT 0,
    discounts       DECIMAL(12,2) NOT NULL DEFAULT 0,
    voids_count     INTEGER NOT NULL DEFAULT 0,
    voids_total     DECIMAL(12,2) NOT NULL DEFAULT 0,
    refunds_count   INTEGER NOT NULL DEFAULT 0,
    refunds_total   DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_cash   DECIMAL(12,2) NOT NULL DEFAULT 0,
    counted_cash    DECIMAL(12,2) NOT NULL DEFAULT 0,
    variance        DECIMAL(12,2) NOT NULL DEFAULT 0,
    closed_by       INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    notes           TEXT NULL,
    created_at      DATETIME NOT NULL,
    UNIQUE (user_id, report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS inventory_units (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    product_id    INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    serial_number VARCHAR(120) NOT NULL UNIQUE,        -- serial / IMEI
    status        VARCHAR(20) NOT NULL DEFAULT 'in_stock',
      -- in_stock | reserved | sold | damaged | returned | missing
    received_at   DATETIME NOT NULL,
    warranty_expires DATE NULL,                        -- per-unit warranty expiry
    note          VARCHAR(255) NULL,
    grn_item_id   INTEGER NULL                         -- source goods_received_items row
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS stock_movements (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    product_id    INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    unit_id       INTEGER NULL REFERENCES inventory_units(id) ON DELETE SET NULL,
    type          VARCHAR(20) NOT NULL,
      -- received | sold | returned | adjustment_in | adjustment_out | damaged
    quantity      INTEGER NOT NULL DEFAULT 0,
    reference     VARCHAR(120) NULL,                   -- GRN #, sale #, return #
    user_id       INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS suppliers (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(190) NOT NULL UNIQUE,
    contact_name  VARCHAR(190) NULL,
    phone         VARCHAR(60)  NULL,
    email         VARCHAR(190) NULL,
    address       VARCHAR(255) NULL,
    notes         TEXT NULL,
    is_active     TINYINT NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS goods_received (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    grn_number    VARCHAR(40) NOT NULL UNIQUE,
    supplier      VARCHAR(190) NOT NULL DEFAULT '',        -- name snapshot (kept for history)
    supplier_id   INTEGER NULL,                            -- link to suppliers (no FK: SET NULL handled in code)
    supplier_reference VARCHAR(120) NULL,                  -- supplier invoice / delivery-note number
    total_cost    DECIMAL(12,2) NOT NULL DEFAULT 0,
    user_id       INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    note          TEXT NULL,
    created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS goods_received_items (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    grn_id        INTEGER NOT NULL REFERENCES goods_received(id) ON DELETE CASCADE,
    product_id    INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    quantity      INTEGER NOT NULL DEFAULT 1,
    unit_cost     DECIMAL(12,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS sales (
    id             INTEGER PRIMARY KEY AUTO_INCREMENT,
    sale_number    VARCHAR(40) NOT NULL UNIQUE,
    channel        VARCHAR(20) NOT NULL DEFAULT 'pos',   -- pos | online
    customer_name  VARCHAR(190) NULL,
    customer_email VARCHAR(190) NULL,
    customer_phone VARCHAR(60)  NULL,
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0,     -- excludes VAT
    discount       DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,     -- amount actually charged
    status         VARCHAR(20) NOT NULL DEFAULT 'completed',
      -- completed | pending | cancelled | offline
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
      -- cash | mpesa | card | bank
    payment_ref    VARCHAR(120) NULL,
    fulfillment    VARCHAR(20) NOT NULL DEFAULT 'pickup',   -- pickup | delivery
    delivery_address TEXT NULL,
    delivery_fee   DECIMAL(12,2) NOT NULL DEFAULT 0,       -- delivery charge (snapshot)
    delivery_zone  VARCHAR(120) NULL,                      -- zone name snapshot
    offline_created TINYINT NOT NULL DEFAULT 0,
    device_id      VARCHAR(80) NULL,                     -- offline client id, for dedup
    client_ref     VARCHAR(120) NULL,                    -- client-generated ref, for dedup
    user_id        INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at     DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS sale_items (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    sale_id       INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
    product_id    INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    unit_id       INTEGER NULL REFERENCES inventory_units(id) ON DELETE SET NULL,
    quantity      INTEGER NOT NULL DEFAULT 1,
    unit_cost     DECIMAL(12,2) NOT NULL DEFAULT 0,     -- cost snapshot at sale time
    unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,     -- excludes VAT (price charged)
    line_total    DECIMAL(12,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS returns (
    id             INTEGER PRIMARY KEY AUTO_INCREMENT,
    return_number  VARCHAR(40) NOT NULL UNIQUE,
    sale_id        INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
    reason         TEXT NULL,
    status         VARCHAR(20) NOT NULL DEFAULT 'pending',
      -- pending | approved | rejected | completed
    refund_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    refund_method  VARCHAR(20) NOT NULL DEFAULT 'original',
    evidence_note  TEXT NULL,
    decision_note  TEXT NULL,
    decision_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    decided_at     DATETIME NULL,
    user_id        INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at     DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS return_items (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    return_id     INTEGER NOT NULL REFERENCES returns(id) ON DELETE CASCADE,
    sale_item_id  INTEGER NOT NULL REFERENCES sale_items(id) ON DELETE CASCADE,
    unit_id       INTEGER NULL REFERENCES inventory_units(id) ON DELETE SET NULL,
    quantity      INTEGER NOT NULL DEFAULT 1,
    refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_outcome VARCHAR(20) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS expenses (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    description   VARCHAR(255) NOT NULL,
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    category      VARCHAR(60) NOT NULL DEFAULT 'general',
    receipt_file  VARCHAR(255) NULL,
    is_recurring  TINYINT NOT NULL DEFAULT 0,
    expense_date  DATE NOT NULL,
    user_id       INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    deleted_at    DATETIME NULL,
    deleted_by    INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    delete_reason VARCHAR(500) NULL,
    created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS throttle (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    throttle_key  VARCHAR(191) NOT NULL UNIQUE,
    attempts      INTEGER NOT NULL DEFAULT 0,
    banned_until  DATETIME NULL,
    updated_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @@kc@@
CREATE TABLE IF NOT EXISTS activity_log (
    id            INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id       INTEGER NULL,
    action        VARCHAR(80) NOT NULL,
    details       TEXT NULL,
    ip            VARCHAR(45) NULL,
    created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: secondary indexes are created programmatically by Schema::createIndexes()
-- (schema.sql must stay importable in phpMyAdmin on MySQL, which rejects
-- "CREATE INDEX IF NOT EXISTS").
