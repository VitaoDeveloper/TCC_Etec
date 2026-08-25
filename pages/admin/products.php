<?php
$page_title = 'Gerenciar Produtos - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
$activePage = 'products';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['product_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM e5_products WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $_SESSION['admin_message'] = 'Produto removido com sucesso.';
        }
    }

    header('Location: products.php');
    exit;
}

$products = $pdo->query('SELECT p.id, p.name, p.price, p.stock, c.name AS category_name FROM e5_products p INNER JOIN e5_categories c ON c.id = p.category_id ORDER BY p.created_at DESC')->fetchAll();
$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include 'head_inc.php'; ?>
</head>
<body>
    <button class="sidebar-toggle" aria-label="Abrir menu"><i class="fas fa-bars"></i></button>
    <div class="admin-wrapper">
        <?php include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Produtos</h2>
                    <p>Lista de produtos cadastrados</p>
                </div>
                <a class="btn btn-primary" href="product-form.php">
                    <i class="fas fa-plus"></i> Novo produto
                </a>
            </header>

            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h3>Todos os produtos</h3>
                    <span style="color:var(--color-gray); font-size:0.85rem;"><?php echo count($products); ?> produto(s)</span>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr><td colspan="6" class="empty-state">Nenhum produto cadastrado.</td></tr>
                        <?php else: foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo (int) $product['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>R$ <?php echo number_format((float) $product['price'], 2, ',', '.'); ?></td>
                            <td><?php echo (int) $product['stock']; ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="product-form.php?id=<?php echo (int) $product['id']; ?>" aria-label="Editar produto"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Excluir produto?');">
                                        <input type="hidden" name="action" value="delete">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                        <button type="submit" class="delete" aria-label="Excluir produto"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
