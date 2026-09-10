<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

// The whole storefront sits behind login.
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$status  = $_GET['status'] ?? null;
$message = $_GET['message'] ?? null;

$products = $pdo->query('SELECT id, name, image, price FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$sizeOrder = ['XS', 'S', 'M', 'L', 'XL'];
$stockStmt = $pdo->prepare('SELECT size, quantity FROM product_stock WHERE product_id = ?');

foreach ($products as &$product) {
    $stockStmt->execute([$product['id']]);
    $rows = $stockStmt->fetchAll(PDO::FETCH_KEY_PAIR); // size => quantity

    $product['sizes'] = [];
    foreach ($sizeOrder as $size) {
        $product['sizes'][$size] = (int) ($rows[$size] ?? 0);
    }
}
unset($product);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1920">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="stylesheet" href="../style.css?v=3">

    <title>John Kharl Caburog Apparel</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
</head>

<body>

    <div class="page-wrapper">

        <div class="header">

            <div class="header-left-section">

                <div class="logo-space">
                    <img class="logo" src="../images/logo.png" alt="Logo">
                </div>

                <div class="header-brand">
                    <h2>John Kharl Caburog Apparel</h2>
                </div>

            </div>

            <div class="header-right-section">

                <div class="top-buttons">
                    <a class="menu-button" href="#home">HOME</a>
                </div>

                <div class="top-buttons">
                    <a class="menu-button" href="#gallery">GALLERY</a>
                </div>

                <div class="top-buttons">
                    <a class="menu-button" href="#about">ABOUT</a>
                </div>

                <div class="top-buttons">
                    <a class="menu-button" href="#contact">CONTACT</a>
                </div>

                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <div class="top-buttons">
                    <a class="menu-button" href="../admin.php">ADMIN</a>
                </div>
                <?php endif; ?>

                <div class="top-buttons">
                    <span class="welcome-text">Hi, <?= e($_SESSION['username']) ?></span>
                </div>

                <div class="top-buttons">
                    <a class="login-button" href="../logout.php">LOG OUT</a>
                </div>

            </div>

        </div>

        <div id="home" class="bg1-space">

            <img class="bg1" src="../images/depositphotos_312437386-stock-photo-black-monochromatic-clothes-accessories-beauty.jpg" alt="Model wearing black monochromatic clothes">

            <div class="moto">
                <p class="moto1">Quite Style</p>
                <p class="moto2">Loud Impact</p>
                <p class="motosub">Where product quality exceeds its price and exudes confidence</p>
            </div>

            <div class="benefits">
                <div class="diamonds">
                    <img class="diamond1" src="../images/Asset 4.png" alt="">
                    <p id="diamond-header1" class="diamond-header">100% LEGIT</p>
                    <p id="diamond-text1" class="diamond-text">All our products are authentic</p>
                    <img class="diamond2" src="../images/Asset 4.png" alt="">
                    <p id="diamond-header2" class="diamond-header">HIGH QUALITY</p>
                    <p id="diamond-text2" class="diamond-text">Our products are made with high quality materials</p>
                    <img class="diamond3" src="../images/Asset 4.png" alt="">
                    <p id="diamond-header3" class="diamond-header">DURABLE</p>
                    <p id="diamond-text3" class="diamond-text">Every product you purchase is guaranteed to last</p>
                </div>
            </div>

            <div class="second-section">
                <img class="secondsectiontitlecard" src="../images/2ndsectiontitlecard.png" alt="">
                <p class="second-section-title">TAKE A LOOK AT OUR COLLECTION</p>
            </div>

        </div>

        <div id="gallery" class="gallery-section">
            <?php foreach ($products as $product):
                $inStock = array_sum($product['sizes']) > 0;
            ?>
            <div id="clothes<?= (int) $product['id'] ?>" class="clothes">
              <div class="product-title"><?= e($product['name']) ?></div>
              <img class="product-image" src="<?= e('../' . $product['image']) ?>" alt="<?= e($product['name']) ?>">
              <?php if ($inStock): ?>
                <button type="button" class="buy-button" data-product-id="<?= (int) $product['id'] ?>">Buy now</button>
              <?php else: ?>
                <button type="button" class="buy-button" disabled>Sold out</button>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="about" class="bg2-space">
            <img class="bg2" src="../images/360_F_104948871_xsXOz7JdVHtfgYgbRcrTaORTRCcUiKmV.jpg">
            <div>
                <p class="ab-header">ABOUT US</p>
                <p class="ab-body">Welcome to John Kharl Caburog Apparel—where product quality exceeds expectations and exudes quiet confidence. Founded on the belief that everyday wear should feel luxurious, durable, and understated, we focus on what truly matters: superior fabrics, precision craftsmanship, and timeless silhouettes. Whether it's an essential hoodie or a clean-cut pair of denim, our pieces are built to last and designed to complement your presence, not distract from it. Why Choose Us?</p>
                <p id="point1" class="ab-body">100% Authentic Quality: Made with first-rate materials and rigorous quality checks.</p>
                <p id="point2" class="ab-body">Built to Last: Every piece is engineered for long-term durability and shape retention.</p>
                <p id="point3" class="ab-body">Unbeatable Value: High-end craftsmanship delivered without the luxury markup.</p>
            </div>
            <img class="about-us-img" src="../images/aboutusimg.png">
        </div>

        <div class="bg3-space">
            <img class="bg3" src="../images/images (1).jpg">
            <div class="check-boxes">
                <img id="check-box1" class="check-box" src="../images/checkandbox.png">
                <img id="check-box2" class="check-box" src="../images/checkandbox.png">
                <img id="check-box3" class="check-box" src="../images/checkandbox.png">
                <img id="check-box4" class="check-box" src="../images/checkandbox.png">
            </div>
            <div>
                <p class="why-header">WHY US?</p>
            </div>
            <div>
                <p id="why-text1" class="why-text">Cheaper than the competition</p>
                <p id="why-text2" class="why-text">Higher quality than the competition</p>
                <p id="why-text3" class="why-text">Materials are first rate</p>
                <p id="why-text4" class="why-text">Unmatched quality check</p>
            </div>
            <div>
                <img class="why-card" src="../images/Gemini_Generated_Image_a2imw4a2imw4a2im.jpg">
                <p class="why-card-text"></p>
            </div>
        </div>

        <div class="bg4-space">
            <img class="bg4" src="../images/black-color-solid-background-1920x1080.png">
            <div>
                <img class="feedback-card" src="../images/feedback card@3x.png">
                <h1 class="feedback-header">Dont take our word for it</h1>
                <p class="feedback-text">Feedback from our customers:</p>
            </div>
            <div>
                <img class="customer-feedback-bg" src="../images/images.jpg">
                <p class="customer-feedback-text">"This Aparrel really does pack quite the punch and has a lot to offer"</p>
                <p class="customer-name">-"Customer"</p>
            </div>
            <img class="bottom-stripes" src="../images/bottom stripe.png">
        </div>

        <div id="contact" class="bg5-space">
            <img class="bg5" src="../images/images.png">
            <div>
                <img class="footer-logo" src="../images/logo.png">
                <p class="footer-brand">John Kharl Caburog Apparel</p>
                <img class="app-store" src="../images/playstore@3x.png">
                <img class="play-store" src="../images/appstore@3x.png">
            </div>
        </div>

    </div>

    <div id="buyModal" class="modal-overlay" hidden>
        <div class="modal-box">
            <h3 id="modalProductName" class="modal-title"></h3>
            <p class="modal-subtitle">Select a size</p>
            <div id="sizeGrid" class="size-grid"></div>
            <p id="modalMessage" class="modal-message"></p>
            <div class="modal-actions">
                <button type="button" id="cancelBtn" class="btn-cancel">Cancel</button>
                <button type="button" id="checkoutBtn" class="btn-checkout" disabled>Checkout</button>
            </div>
        </div>
    </div>

    <?php if ($status === 'success' && $message): ?>
    <div id="flashToast" class="flash-toast"><?= e($message) ?></div>
    <?php endif; ?>

    <script>
        window.PRODUCTS = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="../assets/js/main.js"></script>

</body>
</html>
