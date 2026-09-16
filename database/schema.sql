-- database/schema.sql
-- --------------------
-- Run this once against your MySQL server to set up the database.
-- See database/README.md for the exact command-line steps.

CREATE DATABASE IF NOT EXISTS apparel_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE apparel_db;

CREATE TABLE user (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(30)  NOT NULL UNIQUE,
    email      VARCHAR(191) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       VARCHAR(20)  NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(100)   NOT NULL,
    image VARCHAR(255)   NOT NULL,
    price DECIMAL(10,2)  NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE product_stock (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size       VARCHAR(5) NOT NULL,
    quantity   INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_product_size (product_id, size),
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL,
    product_id           INT NOT NULL,
    size                 VARCHAR(5) NOT NULL,
    quantity             INT NOT NULL DEFAULT 1,

    -- Payment
    payment_method       ENUM('cod', 'credit_card') NOT NULL DEFAULT 'cod',
    payment_status       ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    status               ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    card_name            VARCHAR(100) NULL,   -- only set for credit_card orders
    card_last4           VARCHAR(4)   NULL,   -- last 4 digits only — never the full card number

    -- Shipping
    shipping_name        VARCHAR(100) NOT NULL,
    shipping_phone       VARCHAR(30)  NOT NULL,
    shipping_address     VARCHAR(255) NOT NULL,
    shipping_city        VARCHAR(100) NOT NULL,
    shipping_postal_code VARCHAR(20)  NOT NULL,

    -- Receipt
    receipt_email        VARCHAR(191) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Seed the same four products the storefront markup expects.
INSERT INTO products (name, image, price) VALUES
    ('Black Hoodie', 'images/360_F_686721990_oWvTbLdyUo7snaBjhEjinVUdIqQ7Airr.jpg', 1200.00),
    ('Grey T-Shirt', 'images/360_F_868101027_BufTMf4kyfeVYfPnSLRiOvD2hnhbFYOO.jpg', 550.00),
    ('Black Jeans',  'images/30189775_59732655_600.webp', 1500.00),
    ('Grey Hoodie',  'images/gray-hoodie-mockup-front-back-260nw-2697869589.jpg', 1250.00);

-- Starting stock per size (XL starts at 0 so "Sold out" shows immediately).
INSERT INTO product_stock (product_id, size, quantity)
SELECT id, size, qty FROM products
CROSS JOIN (
    SELECT 'XS' AS size, 5  AS qty UNION ALL
    SELECT 'S',  10 UNION ALL
    SELECT 'M',  12 UNION ALL
    SELECT 'L',  8  UNION ALL
    SELECT 'XL', 0
) AS starting_stock;

-- Default admin account. Generate the password hash yourself and paste
-- it in below — do NOT store a plaintext password here.
--
--   php -r "echo password_hash('YourAdminPassword123', PASSWORD_DEFAULT);"
--
-- INSERT INTO user (username, email, password, role) VALUES
--     ('admin', 'admin@example.com', '<paste the generated hash here>', 'admin');
