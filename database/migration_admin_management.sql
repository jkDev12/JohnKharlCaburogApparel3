-- database/migration_admin_management.sql
-- -----------------------------------------
-- Run this ONCE against your EXISTING apparel_db database to support
-- the admin panel's "cancel order" action, without touching any data
-- you already have (unlike schema.sql, this does NOT drop or recreate
-- anything).
--
--   mysql -u root -p apparel_db < database/migration_admin_management.sql
--
-- If you are setting the database up for the first time instead, you
-- don't need this file — database/schema.sql already includes this
-- column.
--
-- Adding products, adding stock, and reducing stock don't need any
-- schema changes (products/product_stock already support them), so
-- this migration only touches `orders`.

USE apparel_db;

ALTER TABLE orders
    ADD COLUMN status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active' AFTER payment_status;
