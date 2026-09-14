-- database/migration_payment_shipping.sql
-- -----------------------------------------
-- Run this ONCE against your EXISTING apparel_db database to add
-- payment method, shipping details, and receipt email to orders,
-- without touching any data you already have (unlike schema.sql,
-- this does NOT drop or recreate anything).
--
--   mysql -u root -p apparel_db < database/migration_payment_shipping.sql
--
-- If you are setting the database up for the first time instead,
-- you don't need this file — database/schema.sql already includes
-- these columns.

USE apparel_db;

ALTER TABLE orders
    ADD COLUMN payment_method       ENUM('cod', 'credit_card') NOT NULL DEFAULT 'cod'       AFTER quantity,
    ADD COLUMN payment_status       ENUM('pending', 'paid')    NOT NULL DEFAULT 'pending'    AFTER payment_method,
    ADD COLUMN card_name            VARCHAR(100) NULL                                        AFTER payment_status,
    ADD COLUMN card_last4           VARCHAR(4)   NULL                                        AFTER card_name,
    ADD COLUMN shipping_name        VARCHAR(100) NOT NULL DEFAULT '' AFTER card_last4,
    ADD COLUMN shipping_phone       VARCHAR(30)  NOT NULL DEFAULT '' AFTER shipping_name,
    ADD COLUMN shipping_address     VARCHAR(255) NOT NULL DEFAULT '' AFTER shipping_phone,
    ADD COLUMN shipping_city        VARCHAR(100) NOT NULL DEFAULT '' AFTER shipping_address,
    ADD COLUMN shipping_postal_code VARCHAR(20)  NOT NULL DEFAULT '' AFTER shipping_city,
    ADD COLUMN receipt_email        VARCHAR(191) NOT NULL DEFAULT '' AFTER shipping_postal_code;

-- Orders placed before this migration have no shipping/receipt data
-- on file (there was nowhere to enter it yet) — this just labels them
-- so they're easy to spot in the admin table rather than showing
-- misleadingly blank cells.
UPDATE orders
SET shipping_name = '(placed before shipping details existed)'
WHERE shipping_name = '';
