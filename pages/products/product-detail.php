<?php
$page_title = 'Detalhes do Produto - Royal Tech';
$show_breadcrumb = true;
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
    $imagePath = $base_path . 'assets/img/placeholder-product.jpg';
} elseif (!preg_match('#^(?:https?://|/)#', $imagePath)) {
    $imagePath = $base_path . ltrim($imagePath, '/');
}

include '../../components/header.php';
?>
<section class="section"><div class="container">
<?php if (!$product): ?><h2>Produto não encontrado</h2><p>Verifique o link ou volte para a listagem.</p><?php else: ?>
<div class="admin-table-container" style="padding:30px;"><img src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" style="max-width:320px; border-radius:10px; margin-bottom:20px;">
<h2><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h2><p><strong>Categoria:</strong> <?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></p><p><strong>Marca:</strong> <?php echo htmlspecialchars($product['brand'] ?? 'Royal Tech', ENT_QUOTES, 'UTF-8'); ?></p><p><strong>Preço:</strong> R$ <?php echo number_format((float)$product['price'],2,',','.'); ?></p><p><strong>Descrição:</strong><br><?php echo nl2br(htmlspecialchars($product['description'] ?? 'Sem descrição.', ENT_QUOTES, 'UTF-8')); ?></p><button class="btn btn-primary require-auth" data-auth-target="carrinho">Adicionar ao Carrinho</button></div>
<?php endif; ?>
</div></section>
<?php include '../../components/footer.php'; ?>
