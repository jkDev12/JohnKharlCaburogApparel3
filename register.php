<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: homepage/index.php');
    exit;
}

$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;
$prefillUsername = $_GET['username'] ?? '';
$prefillEmail    = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account — John Kharl Caburog Apparel</title>
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
            <h2 class="auth-title">Create Account</h2>

            <?php if ($status === 'error' && $message): ?>
                <p class="auth-error"><?= e($message) ?></p>
            <?php endif; ?>

            <form method="post" class="auth-form" action="register_function.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label class="auth-label" for="username">Username</label>
                <input class="auth-input" type="text" id="username" name="username"
                       value="<?= e($prefillUsername) ?>" required autofocus autocomplete="username">

                <label class="auth-label" for="email">Email</label>
                <input class="auth-input" type="email" id="email" name="email"
                       value="<?= e($prefillEmail) ?>" required autocomplete="email">

                <label class="auth-label" for="password">Password</label>
                <input class="auth-input" type="password" id="password" name="password"
                       required autocomplete="new-password">

                <label class="auth-label" for="confirm_password">Confirm password</label>
                <input class="auth-input" type="password" id="confirm_password" name="confirm_password"
                       required autocomplete="new-password">

                <button type="submit" name="register" class="auth-button">CREATE ACCOUNT</button>
            </form>

            <p class="auth-link">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>

</body>
</html>
