<?php
declare(strict_types=1);

/*
 * features/db.php
 * ---------------
 * MySQL connection, built the exact same way as the reference
 * project's database/config.php (getConnection() -> PDO, mysql DSN,
 * PDO::ERRMODE_EXCEPTION, die() on failure).
 *
 * Unlike the old SQLite version, this file does NOT create or seed
 * any tables for you. Set up the database once, up front, by running
 * database/schema.sql against your MySQL server — see
 * database/README.md for the exact commands. Everything else
 * (products, orders, stock, users) is queried through here with
 * normal PDO prepared statements.
 */

function getConnection(): PDO
{
    $host = 'localhost';
    $db   = 'apparel_db';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$db;charset=utf8mb4",
            $user,
            $pass
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
