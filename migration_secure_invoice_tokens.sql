-- ============================================
-- Migration: Secure invoice view tokens
-- Run once against an existing invoice_db.
-- ============================================

USE invoice_db;

ALTER TABLE invoices
    ADD COLUMN access_token VARCHAR(64) NULL AFTER invoice_no;

UPDATE invoices
SET access_token = SHA2(CONCAT(id, '-', UUID()), 256)
WHERE access_token IS NULL;

ALTER TABLE invoices
    MODIFY access_token VARCHAR(64) NOT NULL;

CREATE UNIQUE INDEX idx_invoices_access_token
    ON invoices (access_token);
