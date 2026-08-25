<?php
$page_title = 'Detalhes do Produto - Royal Tech';
$breadcrumb_title = 'Detalhes do Produto';
$current_page = 'produtos';
$base_path = '../../';

include '../../database/connection.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
$productId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM e5_products p INNER JOIN e5_categories c ON c.id = p.category_id WHERE p.id = :id LIMIT 1');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

$images = [];
if ($product) {
    $stmtImg = $pdo->prepare('SELECT id, image_path, is_primary FROM e5_product_images WHERE product_id = :pid ORDER BY is_primary DESC, id ASC');
    $stmtImg->execute([':pid' => $productId]);
    $images = $stmtImg->fetchAll();
}

$mainImage = !empty($images) ? resolveImage($images[0]['image_path'], $base_path) : ($base_path . 'assets/img/placeholder-product.svg');

include '../../components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;"><div class="container">
<?php if (!$product): ?>
    <div class="ml-empty">
        <i class="fas fa-box-open"></i>
        <h3>Produto não encontrado</h3>
        <p>Verifique o link ou volte para a listagem.</p>
        <p style="margin-top: 16px;"><a href="products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Ver Produtos</a></p>
    </div>
<?php else:
$stock = (int) ($product['stock'] ?? 0);
$stockOk = $stock > 0;
$price = (float) $product['price'];
$oldPrice = $product['old_price'] !== null ? (float) $product['old_price'] : null;
$discount = ($oldPrice !== null && $oldPrice > $price && $oldPrice > 0) ? round((($oldPrice - $price) / $oldPrice) * 100) : 0;
$maxQty = max(1, $stock);
?>
<div class="ml-detail-grid" data-product-id="<?php echo $productId; ?>">
    <div>
        <div class="ml-gallery-main">
            <img id="galleryMain" src="<?php echo htmlspecialchars($mainImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.onerror=null;this.src='<?php echo $base_path; ?>assets/img/placeholder-product.svg'">
        </div>
        <?php if (count($images) > 1): ?>
        <div class="ml-gallery-thumbs">
            <?php foreach ($images as $i => $img):
                $thumb = resolveImage($img['image_path'], $base_path);
            ?>
            <div class="ml-gallery-thumb <?php echo $i === 0 ? 'active' : ''; ?>" data-img="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumbnail <?php echo $i + 1; ?>" onerror="this.onerror=null;this.src='<?php echo $base_path; ?>assets/img/placeholder-product.svg'">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <span class="ml-card-category"><?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
        <h1 class="ml-detail-title"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="ml-detail-brand">Marca: <?php echo htmlspecialchars($product['brand'] ?? 'Royal Tech', ENT_QUOTES, 'UTF-8'); ?></p>

        <div class="ml-detail-price-box">
            <span class="ml-detail-price">R$ <?php echo number_format($price, 2, ',', '.'); ?></span>
            <?php if ($oldPrice !== null && $oldPrice > $price): ?>
            <span class="ml-detail-old-price">R$ <?php echo number_format($oldPrice, 2, ',', '.'); ?></span>
            <?php if ($discount > 0): ?><span class="ml-discount-tag">-<?php echo $discount; ?>%</span><?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($stockOk): ?>
        <p class="ml-detail-stock-ok"><i class="fas fa-check-circle"></i> <?php echo $stock; ?> unidade(s) disponível(is)</p>
        <?php else: ?>
        <p class="ml-detail-stock-out"><i class="fas fa-times-circle"></i> Esgotado</p>
        <?php endif; ?>

        <div class="ml-detail-desc">
            <h4>Descrição</h4>
            <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'Sem descrição.', ENT_QUOTES, 'UTF-8')); ?></p>
        </div>

        <?php if ($stockOk): ?>
        <div class="qty-stepper">
            <button type="button" class="cart-qty-btn" id="pdpQtyDec" aria-label="Diminuir quantidade">−</button>
            <input type="number" id="pdp-qty" class="cart-qty" value="1" min="1" max="<?php echo $maxQty; ?>" aria-label="Quantidade">
            <button type="button" class="cart-qty-btn" id="pdpQtyInc" aria-label="Aumentar quantidade">+</button>
        </div>
        <button class="ml-btn ml-btn-primary ml-btn-block btn-add-cart js-require-auth" data-auth-target="carrinho" style="padding:14px; font-size:1.05rem;"><i class="fas fa-shopping-bag"></i> Adicionar ao Carrinho</button>
        <?php else: ?>
        <button class="ml-btn ml-btn-primary ml-btn-block" disabled style="padding:14px; font-size:1.05rem;">Indisponível</button>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var qtyInput = document.getElementById('pdp-qty');
    var dec = document.getElementById('pdpQtyDec');
    var inc = document.getElementById('pdpQtyInc');
    if (!qtyInput || !dec || !inc) return;
    dec.addEventListener('click', function() {
        var v = parseInt(qtyInput.value, 10) || 1;
        qtyInput.value = Math.max(parseInt(qtyInput.min, 10) || 1, v - 1);
    });
    inc.addEventListener('click', function() {
        var v = parseInt(qtyInput.value, 10) || 1;
        qtyInput.value = Math.min(parseInt(qtyInput.max, 10) || 999, v + 1);
    });
})();
</script>

<?php if (count($images) > 1): ?>
<script>
document.querySelectorAll('.ml-gallery-thumb').forEach(function(thumb) {
    thumb.addEventListener('click', function() {
        document.querySelectorAll('.ml-gallery-thumb').forEach(function(t) { t.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('galleryMain').src = this.dataset.img;
    });
});
</script>
<?php endif; ?>

<?php endif; ?>
</div></section>
<?php include '../../components/footer.php'; ?>
