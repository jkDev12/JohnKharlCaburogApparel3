<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/features/validation.php';

if (!isset($_POST['register'])) {
    header('Location: register.php');
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: register.php?status=error&message=' . urlencode('Your session expired. Please try again.'));
    exit;
}

$result = validateRegisterInput($_POST);
$errors = $result['errors'];

if (!empty($errors)) {
    $message = implode(' ', $errors);
    $query = http_build_query([
        'status'   => 'error',
        'message'  => $message,
        'username' => $result['data']['username'],
        'email'    => $result['data']['email'],
    ]);
    header('Location: register.php?' . $query);
    exit;
}

try {
    $check = $pdo->prepare('SELECT id FROM user WHERE username = ? OR email = ? LIMIT 1');
    $check->execute([$result['data']['username'], $result['data']['email']]);

    if ($check->fetch()) {
        header('Location: register.php?status=error&message=' . urlencode('That username or email is already registered.'));
        exit;
    }

    $hashedPassword = password_hash($result['data']['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO user (username, email, password, role)
            VALUES (:username, :email, :password, :role)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':username', $result['data']['username']);
    $stmt->bindValue(':email', $result['data']['email']);
    $stmt->bindValue(':password', $hashedPassword);
    $stmt->bindValue(':role', 'customer');
    $stmt->execute();

    $newId = $pdo->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['user_id']  = $newId;
    $_SESSION['username'] = $result['data']['username'];
    $_SESSION['role']     = 'customer';

    header('Location: homepage/index.php?status=success&message=' . urlencode('Welcome, ' . $result['data']['username'] . '! Your account is ready.'));
    exit;
} catch (PDOException $e) {
    header('Location: register.php?status=error&message=' . urlencode('Registration failed. Please try again.'));
    exit;
}
