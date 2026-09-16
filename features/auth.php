<?php
declare(strict_types=1);

/*
 * features/auth.php
 * -----------------
 * Password hashing now works exactly like the reference project:
 * plain password_hash($password, PASSWORD_DEFAULT) / password_verify().
 * The previous HMAC-SHA256 "pepper" pre-hash step has been removed
 * entirely — no sha256 anywhere in this app anymore. login_function.php
 * and register_function.php call password_hash()/password_verify()
 * directly, just like the reference's function.php / login_function.php.
 *
 * CSRF token helpers are kept below (the reference doesn't have this
 * feature, but it's specific to this app's forms and not something
 * both projects share, so it's left in as-is).
 */

/** Returns the current CSRF token, generating one if needed. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $submittedToken): bool
{
    return is_string($submittedToken)
        && $submittedToken !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}
