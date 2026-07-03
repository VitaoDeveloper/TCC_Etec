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
<section class="section"><div class="container">
    <div class="section-header"><h2>Meus Favoritos</h2><p><?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'itens'; ?></p></div>

    <?php if (empty($items)): ?>
        <div style="text-align:center; padding:60px 20px;">
            <i class="far fa-heart" style="font-size:64px; color:var(--color-gold); opacity:0.5; margin-bottom:20px;"></i>
            <h3>Nenhum favorito ainda</h3>
            <p style="margin-bottom:20px;">Salve seus produtos favoritos para encontrá-los facilmente depois.</p>
            <a href="../products/products.php" class="btn btn-primary"><i class="fas fa-store"></i> Ver Produtos</a>
        </div>
    <?php else: ?>
        <div class="products-grid">
        <?php foreach ($items as $item):
            $img = (string) ($item['image_path'] ?? '');
            if ($img === '') {
                $img = $base_path . 'assets/img/placeholder-product.svg';
            } elseif (preg_match('#^/#', $img)) {
                $img = $base_path . ltrim($img, '/');
            } elseif (!preg_match('#^https?://#i', $img)) {
                $img = $base_path . $img;
            }
            $product_name = $item['product_name'];
            $product_id = $item['product_id'];
            $product_price = $item['price'];
            $product_old_price = $item['old_price'];
            $product_image = $img;
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
