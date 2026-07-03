<?php
$page_title = 'Detalhes do Produto - Royal Tech';
$breadcrumb_title = 'Detalhes do Produto';
$current_page = 'produtos';
$base_path = '../../';

include '../../database/connection.php';
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

function resolveImage($path, $basePath) {
    if ($path === '') return $basePath . 'assets/img/placeholder-product.svg';
    if (preg_match('#^/#', $path)) return $basePath . ltrim($path, '/');
    if (preg_match('#^https?://#i', $path)) return $path;
    return $basePath . $path;
}

$mainImage = !empty($images) ? resolveImage($images[0]['image_path'], $base_path) : ($base_path . 'assets/img/placeholder-product.svg');

include '../../components/header.php';
?>
<section class="section"><div class="container">
<?php if (!$product): ?><h2>Produto não encontrado</h2><p>Verifique o link ou volte para a listagem.</p><?php else:
$stock = (int) ($product['stock'] ?? 0);
$stockOk = $stock > 0;
?>
<div class="product-detail-grid" data-product-id="<?php echo $productId; ?>">
    <div class="product-gallery">
        <div class="gallery-main">
            <img id="galleryMain" src="<?php echo htmlspecialchars($mainImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.onerror=null;this.src='<?php echo $base_path; ?>assets/img/placeholder-product.svg'">
        </div>
        <?php if (count($images) > 1): ?>
        <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img):
                $thumb = resolveImage($img['image_path'], $base_path);
            ?>
            <div class="gallery-thumb <?php echo $i === 0 ? 'active' : ''; ?>" data-img="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumbnail <?php echo $i + 1; ?>" onerror="this.onerror=null;this.src='<?php echo $base_path; ?>assets/img/placeholder-product.svg'">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="product-info-detail">
        <span class="product-category"><?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
        <h2><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="product-brand"><?php echo htmlspecialchars($product['brand'] ?? 'Royal Tech', ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="product-price-block">
            <span class="current-price">R$ <?php echo number_format((float)$product['price'],2,',','.'); ?></span>
            <?php if ($product['old_price']): ?>
            <span class="old-price">R$ <?php echo number_format((float)$product['old_price'],2,',','.'); ?></span>
            <?php endif; ?>
        </div>
        <?php if ($stockOk): ?><p class="stock-ok"><i class="fas fa-check-circle"></i> <?php echo $stock; ?> unidade(s) disponível(is)</p>
        <?php else: ?><p class="stock-out"><i class="fas fa-times-circle"></i> Esgotado</p><?php endif; ?>
        <div class="product-description">
            <h4>Descrição</h4>
            <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'Sem descrição.', ENT_QUOTES, 'UTF-8')); ?></p>
        </div>
        <?php if ($stockOk): ?>
        <button class="btn btn-primary btn-add-cart js-require-auth" data-auth-target="carrinho" style="width:100%; padding:14px; font-size:1.1rem;"><i class="fas fa-shopping-bag"></i> Adicionar ao Carrinho</button>
        <?php else: ?>
        <button class="btn btn-secondary" disabled style="width:100%; padding:14px; font-size:1.1rem; opacity:0.5; cursor:not-allowed;">Indisponível</button>
        <?php endif; ?>
    </div>
</div>

<?php if (count($images) > 1): ?>
<script>
document.querySelectorAll('.gallery-thumb').forEach(function(thumb) {
    thumb.addEventListener('click', function() {
        document.querySelectorAll('.gallery-thumb').forEach(function(t) { t.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('galleryMain').src = this.dataset.img;
    });
});
</script>
<?php endif; ?>

<?php endif; ?>
</div></section>
<?php include '../../components/footer.php'; ?>
