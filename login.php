<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: homepage/index.php');
    exit;
}

$status   = $_GET['status'] ?? null;
$message  = $_GET['message'] ?? null;
$prefillUsername = $_GET['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log In — John Kharl Caburog Apparel</title>
    <link rel="stylesheet" href="style.css?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
</head>
<body class="auth-body">

    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-logo-space">
                <img class="auth-logo" src="images/logo.png" alt="Logo">
            </div>
            <h1 class="auth-brand">John Kharl Caburog Apparel</h1>
            <h2 class="auth-title">Log In</h2>

            <?php if ($status === 'error' && $message): ?>
                <p class="auth-error"><?= e($message) ?></p>
            <?php endif; ?>

            <form method="post" class="auth-form" action="login_function.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label class="auth-label" for="username">Username or email</label>
                <input class="auth-input" type="text" id="username" name="username"
                       value="<?= e($prefillUsername) ?>" required autofocus autocomplete="username">

                <label class="auth-label" for="password">Password</label>
                <input class="auth-input" type="password" id="password" name="password"
                       required autocomplete="current-password">

                <button type="submit" name="login" class="auth-button">LOG IN</button>
            </form>

            <p class="auth-link">New here? <a href="register.php">Create an account</a></p>
        </div>
    </div>

</body>
</html>
