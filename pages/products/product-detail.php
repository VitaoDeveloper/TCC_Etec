<?php
$page_title = 'Detalhes do Produto - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Detalhes do Produto';
$current_page = 'produtos';
$base_path = '../../';

include '../../database/connection.php';
$productId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE p.id = :id LIMIT 1');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

include '../../components/header.php';
?>
<section class="section">
    <div class="container">
        <?php if (!$product): ?>
            <h2>Produto não encontrado</h2>
            <p>O produto solicitado não existe ou foi removido.</p>
        <?php else: ?>
            <div class="admin-table-container" style="padding:30px;">
                <h2><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><strong>Categoria:</strong> <?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Marca:</strong> <?php echo htmlspecialchars($product['brand'] ?? 'Royal Tech', ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Preço:</strong> R$ <?php echo number_format((float) $product['price'], 2, ',', '.'); ?></p>
                <p><strong>Descrição:</strong><br><?php echo nl2br(htmlspecialchars($product['description'] ?? 'Sem descrição.', ENT_QUOTES, 'UTF-8')); ?></p>
                <button class="btn btn-primary require-auth" data-auth-target="carrinho">Adicionar ao Carrinho</button>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include '../../components/footer.php'; ?>
