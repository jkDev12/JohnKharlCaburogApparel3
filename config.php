<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/features/functions.php';
require_once __DIR__ . '/features/auth.php';
require_once __DIR__ . '/features/db.php';

$pdo = getConnection();
