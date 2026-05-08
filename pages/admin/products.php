<?php
$page_title = 'Gerenciar Produtos - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['product_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $_SESSION['admin_message'] = 'Produto removido com sucesso.';
        }
    }

    header('Location: products.php');
    exit;
}

$products = $pdo->query('SELECT p.id, p.name, p.price, p.stock, c.name AS category_name FROM products p INNER JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC')->fetchAll();
$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo $page_title; ?></title><link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/admin.css"></head><body>
<div class="admin-wrapper"><main class="admin-main" style="margin-left:0; max-width:1100px; margin-inline:auto;">
<header class="admin-header"><div class="admin-title"><h2>Produtos</h2><p>Lista de produtos cadastrados</p></div><a class="btn btn-primary" href="product-form.php">Novo produto</a></header>
<?php if ($message): ?><div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<div class="admin-table-container"><table class="admin-table"><thead><tr><th>ID</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($products as $product): ?><tr><td><?php echo (int) $product['id']; ?></td><td><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></td><td>R$ <?php echo number_format((float) $product['price'], 2, ',', '.'); ?></td><td><?php echo (int) $product['stock']; ?></td><td><a class="btn btn-secondary" href="product-form.php?id=<?php echo (int) $product['id']; ?>">Editar</a><form method="POST" style="display:inline-block" onsubmit="return confirm('Excluir produto?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>"><button class="btn btn-secondary" type="submit">Excluir</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></main></div></body></html>
