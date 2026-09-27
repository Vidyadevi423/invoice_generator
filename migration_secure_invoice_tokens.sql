-- ============================================
-- Migration: Secure invoice view tokens
-- Safe to run more than once against invoice_db.
-- ============================================

USE invoice_db;

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'invoices'
      AND column_name = 'access_token'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE invoices ADD COLUMN access_token VARCHAR(64) NULL AFTER invoice_no',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE invoices
SET access_token = SHA2(CONCAT(id, '-', UUID()), 256)
WHERE access_token IS NULL;

ALTER TABLE invoices
    MODIFY access_token VARCHAR(64) NOT NULL;

SET @token_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'invoices'
      AND index_name = 'idx_invoices_access_token'
);

SET @sql = IF(
    @token_index_exists = 0,
    'CREATE UNIQUE INDEX idx_invoices_access_token ON invoices (access_token)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @status_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'invoices'
      AND index_name = 'idx_invoices_status_created_at'
);

SET @sql = IF(
    @status_index_exists = 0,
    'CREATE INDEX idx_invoices_status_created_at ON invoices (status, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @invoice_items_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'invoice_items'
      AND index_name = 'idx_invoice_items_invoice_id'
);

SET @sql = IF(
    @invoice_items_index_exists = 0,
    'CREATE INDEX idx_invoice_items_invoice_id ON invoice_items (invoice_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @invoice_rate_limits_table_exists = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'invoice_rate_limits'
);

SET @sql = IF(
    @invoice_rate_limits_table_exists = 0,
    'CREATE TABLE invoice_rate_limits (
        ip_hash CHAR(64) PRIMARY KEY,
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        window_started_at DATETIME NOT NULL,
        blocked_until DATETIME NULL
    ) ENGINE=InnoDB',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @login_ip_attempts_table_exists = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'admin_login_ip_attempts'
);

SET @sql = IF(
    @login_ip_attempts_table_exists = 0,
    'CREATE TABLE admin_login_ip_attempts (
        ip_hash CHAR(64) PRIMARY KEY,
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        window_started_at DATETIME NOT NULL,
        blocked_until DATETIME NULL
    ) ENGINE=InnoDB',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


SET @login_attempts_table_exists = (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'admin_login_attempts'
);

SET @sql = IF(
    @login_attempts_table_exists = 0,
    'CREATE TABLE admin_login_attempts (
        username VARCHAR(100) PRIMARY KEY,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        window_started_at DATETIME NOT NULL,
        blocked_until DATETIME NULL
    ) ENGINE=InnoDB',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
