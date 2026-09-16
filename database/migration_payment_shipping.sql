

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


UPDATE orders
SET shipping_name = '(placed before shipping details existed)'
WHERE shipping_name = '';
