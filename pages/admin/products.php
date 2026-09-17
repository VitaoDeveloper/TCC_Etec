<?php
$page_title = 'Gerenciar Produtos - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/pagination.php';
$activePage = 'products';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['product_id'] ?? 0);
        if ($id > 0) {
            // FK fk_order_items_product é RESTRICT: preserva o histórico de pedidos.
            $linked = $pdo->prepare('SELECT COUNT(*) FROM e5_order_items WHERE product_id = :id');
            $linked->execute([':id' => $id]);
            $linkedCount = (int) $linked->fetchColumn();
            if ($linkedCount > 0) {
                $_SESSION['admin_error'] = 'Não é possível excluir este produto: ele consta em ' . $linkedCount . ' item(ns) de pedido. Zere o estoque para tirá-lo de venda.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM e5_products WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $_SESSION['admin_message'] = 'Produto removido com sucesso.';
            }
        }
    }

    header('Location: products.php');
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE (p.name LIKE :q_name OR p.brand LIKE :q_brand)';
    $pattern = '%' . $search . '%';
    $params[':q_name'] = $pattern;
    $params[':q_brand'] = $pattern;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM e5_products p $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$page = pagination_page();
$limit = pagination_limit(20);
$totalPages = max(1, (int) ceil($total / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = pagination_offset($page, $limit);

$products = $pdo->prepare("SELECT p.id, p.name, p.price, p.stock, c.name AS category_name
    FROM e5_products p INNER JOIN e5_categories c ON c.id = p.category_id
    $where ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset");
$products->execute($params);
$products = $products->fetchAll();

$message = $_SESSION['admin_message'] ?? null;
$error = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_message'], $_SESSION['admin_error']);
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
                <div class="admin-header-actions">
                <a class="btn btn-primary" href="product-form.php">
                    <i class="fas fa-plus"></i> Novo produto
                </a>
                <?php include 'header_user_inc.php'; ?>
            </div>
            </header>

            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h3>Todos os produtos <span class="pagination-summary">(<?php echo $total; ?>)</span></h3>
                    <form method="GET" class="admin-filter-bar">
                        <input type="text" name="q" placeholder="Buscar por nome ou marca..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-secondary" aria-label="Buscar produtos"><i class="fas fa-search"></i></button>
                        <?php if ($search !== ''): ?>
                        <a href="products.php" class="btn btn-secondary" aria-label="Limpar busca"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
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
                <?php echo pagination_render($page, $totalPages, ['q' => $search]); ?>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
