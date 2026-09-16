<?php
declare(strict_types=1);

function validateRequired(string $value, string $label): ?string
{
    return trim($value) === '' ? "$label is required." : null;
}

function validateEmailFormat(string $value): ?string
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "Enter a valid email address.";
}

function validateUsernameFormat(string $value): ?string
{
    return preg_match('/^[A-Za-z0-9_.]{3,30}$/', $value)
        ? null
        : "Username must be 3-30 characters (letters, numbers, underscore, or period).";
}

function validatePasswordStrength(string $value): ?string
{
    if (strlen($value) < 8) {
        return "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Za-z]/', $value)) {
        return "Password must contain at least one letter.";
    }
    if (!preg_match('/[0-9]/', $value)) {
        return "Password must contain at least one number.";
    }
    return null;
}

function validateRegisterInput(array $post): array
{
    $username = trim($post['username'] ?? '');
    $email    = trim($post['email'] ?? '');
    $password = (string) ($post['password'] ?? '');
    $confirm  = (string) ($post['confirm_password'] ?? '');

    $errors = array_filter([
        validateUsernameFormat($username),
        validateEmailFormat($email),
        validatePasswordStrength($password),
        $password !== $confirm ? 'Passwords do not match.' : null,
    ]);
    $errors = array_values($errors);

    return [
        'errors' => $errors,
        'data'   => ['username' => $username, 'email' => $email, 'password' => $password],
    ];
}

function validateLoginInput(array $post): array
{
    $username = trim($post['username'] ?? '');
    $password = (string) ($post['password'] ?? '');

    $errors = array_filter([
        validateRequired($username, 'Username'),
        validateRequired($password, 'Password'),
    ]);
    $errors = array_values($errors);

    return [
        'errors' => $errors,
        'data'   => ['username' => $username, 'password' => $password],
    ];
}

/**
 * Checkout fields only need to be a string or an integer (and non-empty
 * once trimmed, if it's a string) — no format/pattern rules are enforced
 * here. $value is the raw, uncast input straight from the request.
 */
function validateStringOrInt(mixed $value, string $label): ?string
{
    if ($value === null) {
        return "$label is required.";
    }
    if (!is_string($value) && !is_int($value)) {
        return "$label must be text or a number.";
    }
    if (is_string($value) && trim($value) === '') {
        return "$label is required.";
    }
    return null;
}

/**
 * Validates the shipping + payment + receipt fields collected in the
 * buy modal. Card details are only required (and only validated) when
 * payment_method is "credit_card" — cash on delivery skips them
 * entirely. Only the card's name, expiry, and last 4 digits ever make
 * it into $data; the full card number and any CVV are never returned
 * or persisted.
 */
function validateCheckoutInput(array $input, string $accountEmail): array
{
    $errors = [];

    $errors[] = validateStringOrInt($input['shipping_name'] ?? null, 'Full name');
    $errors[] = validateStringOrInt($input['shipping_phone'] ?? null, 'Phone number');
    $errors[] = validateStringOrInt($input['shipping_address'] ?? null, 'Street address');
    $errors[] = validateStringOrInt($input['shipping_city'] ?? null, 'City');
    $errors[] = validateStringOrInt($input['shipping_postal_code'] ?? null, 'Postal code');

    $shippingName    = trim((string) ($input['shipping_name'] ?? ''));
    $shippingPhone   = trim((string) ($input['shipping_phone'] ?? ''));
    $shippingAddress = trim((string) ($input['shipping_address'] ?? ''));
    $shippingCity    = trim((string) ($input['shipping_city'] ?? ''));
    $shippingPostal  = trim((string) ($input['shipping_postal_code'] ?? ''));

    $paymentMethod = (string) ($input['payment_method'] ?? '');
    if (!in_array($paymentMethod, ['cod', 'credit_card'], true)) {
        $errors[] = 'Choose a payment method.';
    }

    $cardName  = '';
    $cardLast4 = null;

    if ($paymentMethod === 'credit_card') {
        $errors[] = validateStringOrInt($input['card_name'] ?? null, 'Name on card');
        $errors[] = validateStringOrInt($input['card_number'] ?? null, 'Card number');
        $errors[] = validateStringOrInt($input['card_expiry'] ?? null, 'Expiry date');

        $cardName   = trim((string) ($input['card_name'] ?? ''));
        $cardNumber = (string) ($input['card_number'] ?? '');

        $digits = preg_replace('/\D/', '', $cardNumber);
        if ($digits !== '' && strlen($digits) >= 4) {
            $cardLast4 = substr($digits, -4);
        }
    }

    $receiptTarget = (string) ($input['receipt_target'] ?? 'account');
    $receiptEmail  = $accountEmail;

    if ($receiptTarget === 'other') {
        $errors[] = validateStringOrInt($input['receipt_email'] ?? null, 'Receipt email');
        $receiptEmail = trim((string) ($input['receipt_email'] ?? ''));
    }

    $errors = array_values(array_filter($errors));

    return [
        'errors' => $errors,
        'data'   => [
            'shipping_name'        => $shippingName,
            'shipping_phone'       => $shippingPhone,
            'shipping_address'     => $shippingAddress,
            'shipping_city'        => $shippingCity,
            'shipping_postal_code' => $shippingPostal,
            'payment_method'       => $paymentMethod,
            'card_name'            => $paymentMethod === 'credit_card' ? $cardName : null,
            'card_last4'           => $cardLast4,
            'receipt_email'        => $receiptEmail !== '' ? $receiptEmail : $accountEmail,
        ],
    ];
}
