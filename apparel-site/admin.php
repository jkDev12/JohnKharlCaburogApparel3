<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied: this page is for admin accounts only.');
}

$successMsg = ($_GET['status'] ?? null) === 'success' ? ($_GET['message'] ?? null) : null;
$errorMsg = null;
$validSizes = ['XS', 'S', 'M', 'L', 'XL'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errorMsg = 'Your session expired. Please try again.';
    } elseif (isset($_POST['mark_paid'])) {
        // Cash-on-delivery orders start "pending" until the admin
        // collects payment at the door — this flips that one order to
        // "paid". Credit-card orders are already paid at checkout, so
        // this only ever touches payment_method = 'cod' rows.
        $orderId = (int) $_POST['mark_paid'];
        $mark = $pdo->prepare(
            "UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_method = 'cod'"
        );
        $mark->execute([$orderId]);

        header('Location: admin.php?status=success&message=' . urlencode("Order #{$orderId} marked as paid."));
        exit;
    } elseif (isset($_POST['add_stock'])) {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $size = strtoupper(trim((string) ($_POST['size'] ?? '')));
        $addQty = (int) ($_POST['quantity'] ?? 0);

        if ($productId <= 0 || !in_array($size, $validSizes, true) || $addQty <= 0) {
            $errorMsg = 'Please choose a product, a size, and a positive quantity.';
        } else {
            $updated = $pdo->prepare(
                'UPDATE product_stock SET quantity = quantity + ? WHERE product_id = ? AND size = ?'
            );
            $updated->execute([$addQty, $productId, $size]);

            header('Location: admin.php?status=success&message=' . urlencode("Added {$addQty} unit(s) of size {$size}."));
            exit;
        }
    }
}

$products = $pdo->query('SELECT id, name, image, price FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$stockStmt = $pdo->prepare(
    'SELECT size, quantity FROM product_stock WHERE product_id = ?
     ORDER BY CASE size WHEN \'XS\' THEN 1 WHEN \'S\' THEN 2 WHEN \'M\' THEN 3 WHEN \'L\' THEN 4 WHEN \'XL\' THEN 5 END'
);

foreach ($products as &$product) {
    $stockStmt->execute([$product['id']]);
    $product['stock'] = $stockStmt->fetchAll(PDO::FETCH_KEY_PAIR); // size => quantity
}
unset($product);

$recentOrders = $pdo->query('
    SELECT o.id, o.size, o.quantity, o.created_at, p.name AS product_name, u.username,
           o.payment_method, o.payment_status, o.card_name, o.card_last4,
           o.shipping_name, o.shipping_phone, o.shipping_address, o.shipping_city, o.shipping_postal_code,
           o.receipt_email
    FROM orders o
    JOIN products p ON p.id = o.product_id
    JOIN user u ON u.id = o.user_id
    ORDER BY o.id DESC
    LIMIT 20
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — John Kharl Caburog Apparel</title>
    <link rel="stylesheet" href="style.css?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
</head>
<body class="admin-body">

    <div class="admin-header">
        <h1>Admin — Stock Management</h1>
        <nav class="admin-nav">
            <a href="homepage/index.php">View Storefront</a>
            <a href="logout.php">Log Out</a>
        </nav>
    </div>

    <div class="admin-content">

        <?php if ($successMsg): ?>
            <p class="admin-success"><?= e($successMsg) ?></p>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <p class="admin-error"><?= e($errorMsg) ?></p>
        <?php endif; ?>

        <h2 class="admin-section-title">Current Stock</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <?php foreach ($validSizes as $size): ?>
                        <th><?= e($size) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= e($product['name']) ?></td>
                        <?php foreach ($validSizes as $size):
                            $qty = (int) ($product['stock'][$size] ?? 0);
                            $cellClass = $qty === 0 ? 'stock-out' : ($qty <= 3 ? 'stock-low' : '');
                        ?>
                            <td class="<?= e($cellClass) ?>"><?= $qty ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 class="admin-section-title">Add Stock</h2>
        <form method="post" class="stock-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="stock-form-field">
                <label for="product_id">Product</label>
                <select id="product_id" name="product_id" required>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="stock-form-field">
                <label for="size">Size</label>
                <select id="size" name="size" required>
                    <?php foreach ($validSizes as $size): ?>
                        <option value="<?= e($size) ?>"><?= e($size) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="stock-form-field">
                <label for="quantity">Quantity to add</label>
                <input type="number" id="quantity" name="quantity" min="1" value="10" required>
            </div>

            <button type="submit" name="add_stock" value="1" class="stock-form-submit">Add Stock</button>
        </form>

        <h2 class="admin-section-title">Recent Orders</h2>
        <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Size</th>
                    <th>Qty</th>
                    <th>Payment</th>
                    <th>Shipping to</th>
                    <th>Receipt</th>
                    <th>Placed</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$recentOrders): ?>
                    <tr><td colspan="10">No orders yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentOrders as $order):
                    $isPaid = $order['payment_status'] === 'paid';
                    if ($order['payment_method'] === 'credit_card') {
                        $paymentLabel = 'Card' . ($order['card_last4'] ? ' ····' . e($order['card_last4']) : '');
                    } else {
                        $paymentLabel = 'COD';
                    }
                ?>
                    <tr>
                        <td><?= (int) $order['id'] ?></td>
                        <td><?= e($order['username']) ?></td>
                        <td><?= e($order['product_name']) ?></td>
                        <td><?= e($order['size']) ?></td>
                        <td><?= (int) $order['quantity'] ?></td>
                        <td>
                            <span class="payment-badge <?= $isPaid ? 'paid' : 'pending' ?>">
                                <?= $paymentLabel ?> · <?= $isPaid ? 'Paid' : 'Pending' ?>
                            </span>
                        </td>
                        <td class="shipping-cell">
                            <strong><?= e($order['shipping_name']) ?></strong><br>
                            <?= e($order['shipping_address']) ?>,
                            <?= e($order['shipping_city']) ?> <?= e($order['shipping_postal_code']) ?><br>
                            <?= e($order['shipping_phone']) ?>
                        </td>
                        <td><?= e($order['receipt_email']) ?></td>
                        <td><?= e($order['created_at']) ?></td>
                        <td>
                            <?php if (!$isPaid): ?>
                                <form method="post" class="mark-paid-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="mark_paid" value="<?= (int) $order['id'] ?>">
                                    <button type="submit" class="mark-paid-btn">Mark Paid</button>
                                </form>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    </div>

</body>
</html>
