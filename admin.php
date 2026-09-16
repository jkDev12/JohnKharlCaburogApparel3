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
        $orderId = (int) $_POST['mark_paid'];
        $mark = $pdo->prepare(
            "UPDATE orders SET payment_status = 'paid' WHERE id = ? AND payment_method = 'cod' AND status = 'active'"
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
    } elseif (isset($_POST['reduce_stock'])) {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $size = strtoupper(trim((string) ($_POST['size'] ?? '')));
        $removeQty = (int) ($_POST['quantity'] ?? 0);

        if ($productId <= 0 || !in_array($size, $validSizes, true) || $removeQty <= 0) {
            $errorMsg = 'Please choose a product, a size, and a positive quantity.';
        } else {
            // Never let stock go negative, even if more is removed than is left.
            $updated = $pdo->prepare(
                'UPDATE product_stock SET quantity = GREATEST(quantity - ?, 0) WHERE product_id = ? AND size = ?'
            );
            $updated->execute([$removeQty, $productId, $size]);

            header('Location: admin.php?status=success&message=' . urlencode("Removed {$removeQty} unit(s) of size {$size}."));
            exit;
        }
    } elseif (isset($_POST['add_product'])) {
        $name = trim((string) ($_POST['name'] ?? ''));
        $priceRaw = trim((string) ($_POST['price'] ?? ''));
        $allowedExt = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

        if ($name === '') {
            $errorMsg = 'Please enter a product name.';
        } elseif (!is_numeric($priceRaw) || (float) $priceRaw < 0) {
            $errorMsg = 'Please enter a valid, non-negative price.';
        } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            $errorMsg = 'Please choose a product image.';
        } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = 'The image upload failed. Please try again.';
        } else {
            $ext = strtolower(pathinfo((string) $_FILES['image']['name'], PATHINFO_EXTENSION));
            $mime = (string) mime_content_type($_FILES['image']['tmp_name']);

            if (!isset($allowedExt[$ext]) || $mime !== $allowedExt[$ext]) {
                $errorMsg = 'Please upload a JPG, PNG, or WEBP image.';
            } else {
                $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'product';
                $filename = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = __DIR__ . '/images/' . $filename;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    $errorMsg = 'Could not save the uploaded image. Please try again.';
                } else {
                    try {
                        $pdo->beginTransaction();

                        $insertProduct = $pdo->prepare('INSERT INTO products (name, image, price) VALUES (?, ?, ?)');
                        $insertProduct->execute([$name, 'images/' . $filename, (float) $priceRaw]);
                        $newProductId = (int) $pdo->lastInsertId();

                        $insertStock = $pdo->prepare(
                            'INSERT INTO product_stock (product_id, size, quantity) VALUES (?, ?, ?)'
                        );
                        foreach ($validSizes as $size) {
                            $startQty = max((int) ($_POST['stock_' . $size] ?? 0), 0);
                            $insertStock->execute([$newProductId, $size, $startQty]);
                        }

                        $pdo->commit();

                        header('Location: admin.php?status=success&message=' . urlencode("Added new product \"{$name}\"."));
                        exit;
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        @unlink($destination);
                        error_log('Add product failed: ' . $e->getMessage());
                        $errorMsg = 'Something went wrong while saving the product.';
                    }
                }
            }
        }
    } elseif (isset($_POST['cancel_order'])) {
        $orderId = (int) $_POST['cancel_order'];

        $orderStmt = $pdo->prepare('SELECT product_id, size, quantity, status FROM orders WHERE id = ?');
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $errorMsg = 'That order could not be found.';
        } elseif ($order['status'] === 'cancelled') {
            $errorMsg = "Order #{$orderId} is already cancelled.";
        } else {
            try {
                $pdo->beginTransaction();

                // Guard the UPDATE with status = 'active' too, so a
                // double-submit can't restock the same order twice.
                $cancel = $pdo->prepare(
                    "UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'active'"
                );
                $cancel->execute([$orderId]);

                if ($cancel->rowCount() === 1) {
                    // The size was reserved out of stock at checkout —
                    // cancelling gives it back.
                    $restock = $pdo->prepare(
                        'UPDATE product_stock SET quantity = quantity + ? WHERE product_id = ? AND size = ?'
                    );
                    $restock->execute([$order['quantity'], $order['product_id'], $order['size']]);
                }

                $pdo->commit();

                header('Location: admin.php?status=success&message=' . urlencode("Order #{$orderId} was cancelled and its stock restored."));
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('Cancel order failed: ' . $e->getMessage());
                $errorMsg = 'Something went wrong while cancelling the order.';
            }
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
           o.payment_method, o.payment_status, o.status, o.card_name, o.card_last4,
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
                    <th>Price</th>
                    <?php foreach ($validSizes as $size): ?>
                        <th><?= e($size) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= e($product['name']) ?></td>
                        <td><?= e(format_price((float) $product['price'])) ?></td>
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

        <h2 class="admin-section-title">Reduce Stock</h2>
        <form method="post" class="stock-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="stock-form-field">
                <label for="reduce_product_id">Product</label>
                <select id="reduce_product_id" name="product_id" required>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="stock-form-field">
                <label for="reduce_size">Size</label>
                <select id="reduce_size" name="size" required>
                    <?php foreach ($validSizes as $size): ?>
                        <option value="<?= e($size) ?>"><?= e($size) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="stock-form-field">
                <label for="reduce_quantity">Quantity to remove</label>
                <input type="number" id="reduce_quantity" name="quantity" min="1" value="1" required>
            </div>

            <button type="submit" name="reduce_stock" value="1" class="stock-form-submit stock-form-remove">Reduce Stock</button>
        </form>

        <h2 class="admin-section-title">Add New Product</h2>
        <form method="post" class="stock-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="stock-form-field">
                <label for="new_name">Name</label>
                <input type="text" id="new_name" name="name" required>
            </div>

            <div class="stock-form-field">
                <label for="new_price">Price (₱)</label>
                <input type="number" id="new_price" name="price" min="0" step="0.01" required>
            </div>

            <div class="stock-form-field">
                <label for="new_image">Image</label>
                <input type="file" id="new_image" name="image" accept=".jpg,.jpeg,.png,.webp" required>
            </div>

            <fieldset class="stock-form-field stock-form-starting">
                <legend>Starting stock (optional)</legend>
                <?php foreach ($validSizes as $size): ?>
                    <label class="starting-stock-field">
                        <?= e($size) ?>
                        <input type="number" name="stock_<?= e($size) ?>" min="0" value="0">
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <button type="submit" name="add_product" value="1" class="stock-form-submit">Add Product</button>
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
                    <th>Status</th>
                    <th>Shipping to</th>
                    <th>Receipt</th>
                    <th>Placed</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$recentOrders): ?>
                    <tr><td colspan="11">No orders yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentOrders as $order):
                    $isPaid = $order['payment_status'] === 'paid';
                    $isCancelled = $order['status'] === 'cancelled';
                    if ($order['payment_method'] === 'credit_card') {
                        $paymentLabel = 'Card' . ($order['card_last4'] ? ' ····' . e($order['card_last4']) : '');
                    } else {
                        $paymentLabel = 'COD';
                    }
                ?>
                    <tr class="<?= $isCancelled ? 'order-cancelled' : '' ?>">
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
                        <td>
                            <span class="status-badge <?= $isCancelled ? 'cancelled' : 'active' ?>">
                                <?= $isCancelled ? 'Cancelled' : 'Active' ?>
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
                        <td class="order-actions">
                            <?php if (!$isCancelled && !$isPaid): ?>
                                <form method="post" class="mark-paid-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="mark_paid" value="<?= (int) $order['id'] ?>">
                                    <button type="submit" class="mark-paid-btn">Mark Paid</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!$isCancelled): ?>
                                <form method="post" class="cancel-order-form" onsubmit="return confirm('Cancel order #<?= (int) $order['id'] ?>? This restores its stock.');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="cancel_order" value="<?= (int) $order['id'] ?>">
                                    <button type="submit" class="cancel-order-btn">Cancel</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($isCancelled && $isPaid): ?>
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
