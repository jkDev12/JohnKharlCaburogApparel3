<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/features/validation.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to complete checkout.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

if (!csrf_verify($input['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}

$productId = (int) ($input['product_id'] ?? 0);
$size = strtoupper(trim((string) ($input['size'] ?? '')));
$validSizes = ['XS', 'S', 'M', 'L', 'XL'];

if ($productId <= 0 || !in_array($size, $validSizes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please choose a valid size.']);
    exit;
}

$accountEmailStmt = $pdo->prepare('SELECT email FROM user WHERE id = ?');
$accountEmailStmt->execute([$_SESSION['user_id']]);
$accountEmail = (string) $accountEmailStmt->fetchColumn();

$checkout = validateCheckoutInput($input, $accountEmail);

if (!empty($checkout['errors'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $checkout['errors'])]);
    exit;
}

$ship = $checkout['data'];

try {
    $pdo->beginTransaction();

    $decrement = $pdo->prepare(
        'UPDATE product_stock SET quantity = quantity - 1 WHERE product_id = ? AND size = ? AND quantity >= 1'
    );
    $decrement->execute([$productId, $size]);

    if ($decrement->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Sorry, that size just sold out.']);
        exit;
    }

    $paymentStatus = $ship['payment_method'] === 'credit_card' ? 'paid' : 'pending';

    $insert = $pdo->prepare(
        'INSERT INTO orders (
            user_id, product_id, size, quantity,
            payment_method, payment_status, card_name, card_last4,
            shipping_name, shipping_phone, shipping_address, shipping_city, shipping_postal_code,
            receipt_email
        ) VALUES (
            :user_id, :product_id, :size, 1,
            :payment_method, :payment_status, :card_name, :card_last4,
            :shipping_name, :shipping_phone, :shipping_address, :shipping_city, :shipping_postal_code,
            :receipt_email
        )'
    );
    $insert->execute([
        ':user_id'              => $_SESSION['user_id'],
        ':product_id'           => $productId,
        ':size'                 => $size,
        ':payment_method'       => $ship['payment_method'],
        ':payment_status'       => $paymentStatus,
        ':card_name'            => $ship['card_name'],
        ':card_last4'           => $ship['card_last4'],
        ':shipping_name'        => $ship['shipping_name'],
        ':shipping_phone'       => $ship['shipping_phone'],
        ':shipping_address'     => $ship['shipping_address'],
        ':shipping_city'        => $ship['shipping_city'],
        ':shipping_postal_code' => $ship['shipping_postal_code'],
        ':receipt_email'        => $ship['receipt_email'],
    ]);

    $remainingStmt = $pdo->prepare('SELECT quantity FROM product_stock WHERE product_id = ? AND size = ?');
    $remainingStmt->execute([$productId, $size]);
    $remaining = (int) $remainingStmt->fetchColumn();

    $pdo->commit();

    $confirmation = $ship['payment_method'] === 'credit_card'
        ? 'Payment received! A receipt is on its way to ' . $ship['receipt_email'] . '.'
        : 'Order placed! Pay in cash when it arrives. A receipt is on its way to ' . $ship['receipt_email'] . '.';

    echo json_encode([
        'success'   => true,
        'message'   => $confirmation,
        'remaining' => $remaining,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Checkout failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}

