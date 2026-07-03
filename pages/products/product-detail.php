<?php
$page_title = 'Detalhes do Produto - Royal Tech';
$breadcrumb_title = 'Detalhes do Produto';
$current_page = 'produtos';
$base_path = '../../';

include '../../database/connection.php';
$productId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT p.*, c.name AS category_name,
    (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
    FROM e5_products p
    INNER JOIN e5_categories c ON c.id = p.category_id
    WHERE p.id = :id LIMIT 1');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

$imagePath = (string) ($product['image_path'] ?? '');
if ($imagePath === '') {
    $imagePath = $base_path . 'assets/img/placeholder-product.svg';
} elseif (preg_match('#^/#', $imagePath)) {
    $imagePath = $base_path . ltrim($imagePath, '/');
} elseif (!preg_match('#^https?://#i', $imagePath)) {
    $imagePath = $base_path . $imagePath;
}

include '../../components/header.php';
?>
<section class="section"><div class="container">
<?php if (!$product): ?><h2>Produto não encontrado</h2><p>Verifique o link ou volte para a listagem.</p><?php else:
$stock = (int) ($product['stock'] ?? 0);
$stockOk = $stock > 0;
?>
<div class="admin-table-container" style="padding:30px;" data-product-id="<?php echo $productId; ?>"><img src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" style="max-width:320px; border-radius:10px; margin-bottom:20px;">
<h2><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h2><p><strong>Categoria:</strong> <?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></p><p><strong>Marca:</strong> <?php echo htmlspecialchars($product['brand'] ?? 'Royal Tech', ENT_QUOTES, 'UTF-8'); ?></p><p><strong>Preço:</strong> R$ <?php echo number_format((float)$product['price'],2,',','.'); ?></p>
<?php if ($stockOk): ?><p><strong>Estoque:</strong> <span class="stock-ok"><?php echo $stock; ?> unidade(s) disponível(is)</span></p><?php else: ?><p><strong>Estoque:</strong> <span class="stock-out">Esgotado</span></p><?php endif; ?>
<p><strong>Descrição:</strong><br><?php echo nl2br(htmlspecialchars($product['description'] ?? 'Sem descrição.', ENT_QUOTES, 'UTF-8')); ?></p><?php if ($stockOk): ?><button class="btn btn-primary btn-add-cart js-require-auth" data-auth-target="carrinho">Adicionar ao Carrinho</button><?php else: ?><button class="btn btn-secondary" disabled style="opacity:0.5; cursor:not-allowed;">Indisponível</button><?php endif; ?></div>
<?php endif; ?>
</div></section>
<?php include '../../components/footer.php'; ?>
