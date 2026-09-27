-- ============================================
-- Invoice Generator Database
-- Created for TechForge Solutions
-- ============================================

CREATE DATABASE IF NOT EXISTS invoice_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE invoice_db;

CREATE TABLE IF NOT EXISTS invoices (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no    VARCHAR(20)    NOT NULL UNIQUE,
    access_token  VARCHAR(64)    NOT NULL UNIQUE,
    customer_name VARCHAR(150)   NOT NULL,
    customer_email VARCHAR(200),
    customer_phone VARCHAR(20),
    customer_address TEXT,
    subtotal      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    tax_percent   DECIMAL(5,2)   NOT NULL DEFAULT 18.00,
    tax_amount    DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    total         DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    status        ENUM('draft','paid','unpaid','cancelled') DEFAULT 'unpaid',
    notes         TEXT,
    created_at    DATETIME       DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoices_status_created_at (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoice_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id  INT            NOT NULL,
    item_name   VARCHAR(255)   NOT NULL,
    description TEXT,
    quantity    DECIMAL(10,2)  NOT NULL DEFAULT 1,
    unit_price  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    total_price DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    INDEX idx_invoice_items_invoice_id (invoice_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_users (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email    VARCHAR(200),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_login_attempts (
    username VARCHAR(100) PRIMARY KEY,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL
) ENGINE=InnoDB;

-- No default administrator is inserted.
-- On a fresh installation, login.php creates the first admin account.

-- ----------------------------------------
-- Sample invoice data for testing
-- ----------------------------------------
INSERT INTO invoices (invoice_no, access_token, customer_name, customer_email, customer_phone, customer_address, subtotal, tax_percent, tax_amount, total, status, notes)
VALUES ('INV-00001', SHA2(CONCAT('sample-', UUID()), 256), 'Sample Client', 'client@example.com', '9876543210', '123 Main Street, Chennai, Tamil Nadu', 5000.00, 18.00, 900.00, 5900.00, 'paid', 'First sample invoice');

SET @sample_invoice_id = LAST_INSERT_ID();

INSERT INTO invoice_items (invoice_id, item_name, quantity, unit_price, total_price)
VALUES (@sample_invoice_id, 'Web Design Service', 1, 3000.00, 3000.00),
       (@sample_invoice_id, 'Logo Design', 1, 2000.00, 2000.00);
