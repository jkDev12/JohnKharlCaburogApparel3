<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/features/validation.php';

if (!isset($_POST['login'])) {
    header('Location: login.php');
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: login.php?status=error&message=' . urlencode('Your session expired. Please try again.'));
    exit;
}

$result = validateLoginInput($_POST);

if (!empty($result['errors'])) {
    $message = implode(' ', $result['errors']);
    header('Location: login.php?status=error&message=' . urlencode($message));
    exit;
}

$username = $result['data']['username'];
$password = $result['data']['password'];

$stmt = $pdo->prepare(
    'SELECT id, username, password, role FROM user WHERE username = ? OR email = ?'
);
$stmt->execute([$username, $username]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    header('Location: login.php?status=error&message=' . urlencode('Invalid username or password.'));
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id']  = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role']     = $user['role'];

header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'homepage/index.php'));
exit;
