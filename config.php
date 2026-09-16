<?php
declare(strict_types=1);

/*
 * config.php
 * ----------
 * Loaded first by every page, the same way the reference project
 * (bensinglogin) loads its "database/config.php" before doing
 * anything else. Just starts the session and pulls in the shared
 * helper files — no custom cookie parameters, no CSRF "pepper", no
 * output buffering tricks.
 */

session_start();

require_once __DIR__ . '/features/functions.php';
require_once __DIR__ . '/features/auth.php';
require_once __DIR__ . '/features/db.php';

// Opens the MySQL connection (see features/db.php). The database and
// its tables are NOT created automatically — run the SQL in
// database/schema.sql against your MySQL server first. Full,
// step-by-step instructions are in database/README.md.
$pdo = getConnection();
