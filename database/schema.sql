-- ============================================================
-- Smart Inventory System - Complete Database Schema
-- ISU Cauayan Food Innovation Center
-- PHP 8+ / MySQL / PDO
-- ============================================================

CREATE DATABASE IF NOT EXISTS smart_inventory
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE smart_inventory;

-- -----------------------------------------------------------
-- 1. roles
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role_name   VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 2. users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL COMMENT 'Hashed using PHP password_hash()',
    email       VARCHAR(100),
    full_name   VARCHAR(100),
    role_id     INT NOT NULL,
    status      ENUM('Active','Inactive') DEFAULT 'Active',
    last_login  DATETIME,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 3. categories
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_name   VARCHAR(100) NOT NULL,
    category_type   VARCHAR(50) NOT NULL COMMENT 'Raw Materials, Production Supplies, Packaging, Chemicals, Cleaning Materials, Office Supplies, Equipment',
    description     TEXT,
    status          ENUM('Active','Inactive') DEFAULT 'Active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 4. units
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS units (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    unit_name   VARCHAR(50) NOT NULL,
    abbreviation VARCHAR(20),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 5. suppliers
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    company_name    VARCHAR(150) NOT NULL,
    contact_person  VARCHAR(100),
    address         TEXT,
    phone           VARCHAR(50),
    email           VARCHAR(100),
    products        TEXT,
    status          ENUM('Active','Inactive') DEFAULT 'Active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 6. inventory_items
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    item_code       VARCHAR(50) NOT NULL UNIQUE,
    name            VARCHAR(200) NOT NULL,
    description     TEXT,
    category_id     INT NOT NULL,
    unit_id         INT NOT NULL,
    current_stock   DECIMAL(10,2) DEFAULT 0.00,
    min_stock       DECIMAL(10,2) DEFAULT 0.00,
    max_stock       DECIMAL(10,2) DEFAULT 0.00,
    unit_cost       DECIMAL(10,2) DEFAULT 0.00,
    supplier_id     INT,
    expiration_date DATE,
    storage_location VARCHAR(100),
    image           VARCHAR(255),
    status          ENUM('Active','Inactive') DEFAULT 'Active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id)     REFERENCES units(id)       ON DELETE CASCADE,
    FOREIGN KEY (supplier_id)  REFERENCES suppliers(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 7. stock_in
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_in (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    item_id         INT NOT NULL,
    transaction_type ENUM('Purchase','Donation','Adjustment','Return') NOT NULL,
    supplier_id     INT,
    quantity        DECIMAL(10,2) NOT NULL,
    unit_cost       DECIMAL(10,2) NOT NULL,
    total_cost      DECIMAL(10,2) NOT NULL,
    batch_number    VARCHAR(50),
    expiration_date DATE,
    reference_number VARCHAR(100),
    remarks         TEXT,
    received_by     INT NOT NULL,
    date_received   DATETIME NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id)     REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id)  REFERENCES suppliers(id)      ON DELETE SET NULL,
    FOREIGN KEY (received_by)  REFERENCES users(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 8. stock_out
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_out (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    item_id         INT NOT NULL,
    transaction_type ENUM('Production Request','Damaged','Expired','Transfer','Consumption') NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL,
    unit_cost       DECIMAL(10,2) NOT NULL,
    total_cost      DECIMAL(10,2) NOT NULL,
    reference_number VARCHAR(100),
    purpose         TEXT,
    requested_by    INT NOT NULL,
    approved_by     INT,
    date_released   DATETIME NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id)      REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by)  REFERENCES users(id)          ON DELETE CASCADE,
    FOREIGN KEY (approved_by)   REFERENCES users(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 9. production_requests
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS production_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    request_number  VARCHAR(50) NOT NULL UNIQUE,
    requested_by    INT NOT NULL,
    department      VARCHAR(100),
    request_date    DATE NOT NULL,
    purpose         TEXT,
    status          ENUM('Pending','Approved','Released','Completed','Rejected') DEFAULT 'Pending',
    approved_by     INT,
    approved_date   DATETIME,
    completed_date  DATETIME,
    remarks         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 10. production_request_items
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS production_request_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    request_id          INT NOT NULL,
    item_id             INT NOT NULL,
    quantity_requested  DECIMAL(10,2) NOT NULL,
    quantity_released   DECIMAL(10,2) DEFAULT 0.00,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES production_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id)    REFERENCES inventory_items(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 11. inventory_transactions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    item_id          INT NOT NULL,
    transaction_type ENUM('Stock In','Stock Out','Adjustment') NOT NULL,
    reference_type   VARCHAR(50),
    reference_id     INT,
    quantity         DECIMAL(10,2) NOT NULL,
    previous_stock   DECIMAL(10,2) NOT NULL,
    new_stock        DECIMAL(10,2) NOT NULL,
    unit_cost        DECIMAL(10,2),
    total_value      DECIMAL(10,2),
    remarks          TEXT,
    created_by       INT NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id)    REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 12. audit_logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    username    VARCHAR(100),
    action      VARCHAR(50) NOT NULL,
    module      VARCHAR(50),
    description TEXT,
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 13. notifications
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    type          ENUM('Low Stock','Near Expiry','Expired','Pending Request','System') NOT NULL,
    title         VARCHAR(200) NOT NULL,
    message       TEXT,
    related_module VARCHAR(50),
    related_id    INT,
    is_read       TINYINT(1) DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 14. system_settings
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 15. activity_logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    action      VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX idx_items_item_code    ON inventory_items(item_code);
CREATE INDEX idx_prod_req_number    ON production_requests(request_number);
CREATE INDEX idx_stock_in_date      ON stock_in(date_received);
CREATE INDEX idx_stock_out_date     ON stock_out(date_released);
CREATE INDEX idx_transactions_date  ON inventory_transactions(created_at);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX idx_activity_created   ON activity_logs(created_at);
CREATE INDEX idx_users_status       ON users(status);
CREATE INDEX idx_items_status       ON inventory_items(status);
CREATE INDEX idx_categories_status  ON categories(status);
CREATE INDEX idx_suppliers_status   ON suppliers(status);

-- ============================================================
-- DEFAULT DATA (minimal — seeder.php handles the rest)
-- ============================================================

-- roles
INSERT INTO roles (role_name, description) VALUES
('Administrator',      'Full system access'),
('Inventory Manager',  'Manages inventory, stock in/out, and reports'),
('Production Staff',   'Creates production requests and views inventory'),
('Faculty',            'Read-only access to inventory data');

-- system_settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
('system_name', 'Smart Inventory System'),
('school_name', 'ISU Cauayan Food Innovation Center'),
('session_timeout', '1800'),
('items_per_page', '20'),
('low_stock_threshold', '10');
