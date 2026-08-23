<?php
$page_title = 'Meus Favoritos - Royal Tech';
$breadcrumb_title = 'Favoritos';
$current_page = 'favoritos';
$base_path = '../../';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once $base_path . 'database/connection.php';
require_once $base_path . 'includes/wishlist_functions.php';

$userId = (int) $_SESSION['user_id'];
$items = wishlistGetItems($pdo, $userId);

include $base_path . 'components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;"><div class="container">
    <div class="ml-section-header">
        <h2 class="ml-section-title">Meus Favoritos</h2>
        <span class="ml-main-count"><?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'itens'; ?></span>
    </div>

    <?php if (empty($items)): ?>
        <div class="ml-empty">
            <i class="far fa-heart"></i>
            <h3>Nenhum favorito ainda</h3>
            <p>Salve seus produtos favoritos para encontrá-los facilmente depois.</p>
            <p style="margin-top: 16px;"><a href="../products/products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Ver Produtos</a></p>
        </div>
    <?php else: ?>
        <div class="ml-products-grid">
        <?php foreach ($items as $item):
            $product_name = $item['product_name'];
            $product_id = $item['product_id'];
            $product_price = $item['price'];
            $product_old_price = $item['old_price'];
            $product_image = $item['image_path'] ?? '';
            $product_category = $item['product_category'];
            $product_brand = $item['brand'];
            $product_stock = $item['product_stock'];
            $product_is_featured = false;
            $product_is_new = false;
            $product_installments = null;
            include $base_path . 'components/product-card.php';
        endforeach; ?>
        </div>
    <?php endif; ?>
</div></section>
<?php include $base_path . 'components/footer.php'; ?>
