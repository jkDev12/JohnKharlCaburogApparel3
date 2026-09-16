
USE apparel_db;

ALTER TABLE orders
    ADD COLUMN status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active' AFTER payment_status;
