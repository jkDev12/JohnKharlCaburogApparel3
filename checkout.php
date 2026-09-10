<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

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

// Accept either a JSON body (used by main.js) or a normal form post.
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

try {
    $pdo->beginTransaction();

    // Conditional UPDATE: only succeeds if stock is still available. This
    // is atomic even under concurrent requests, so two shoppers can never
    // both "win" the last item.
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

    $pdo->prepare('INSERT INTO orders (user_id, product_id, size, quantity) VALUES (?, ?, ?, 1)')
        ->execute([$_SESSION['user_id'], $productId, $size]);

    $remainingStmt = $pdo->prepare('SELECT quantity FROM product_stock WHERE product_id = ? AND size = ?');
    $remainingStmt->execute([$productId, $size]);
    $remaining = (int) $remainingStmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success'   => true,
        'message'   => 'Order placed! Thanks for shopping with us.',
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
