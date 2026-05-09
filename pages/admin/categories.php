<?php
$page_title = 'Gerenciar Categorias - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string) $_POST['name']);
        $description = trim((string) $_POST['description']);
        if ($name !== '') {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
            $stmt = $pdo->prepare('INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)');
            $stmt->execute([':name' => $name, ':slug' => $slug . '-' . time(), ':description' => $description ?: null]);
            $_SESSION['admin_message'] = 'Categoria criada com sucesso.';
        }
    }

    if ($action === 'delete') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId > 0) {
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
            $stmt->execute([':id' => $categoryId]);
            $_SESSION['admin_message'] = 'Categoria removida com sucesso.';
        }
    }

    header('Location: categories.php');
    exit;
}

$categories = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS total_products FROM categories c ORDER BY c.name')->fetchAll();
$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo $page_title; ?></title><link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/admin.css"></head><body>
<div class="admin-wrapper"><main class="admin-main" style="margin-left:0; max-width:1100px; margin-inline:auto;">
<header class="admin-header"><div class="admin-title"><h2>Categorias</h2><p>Cadastro e remoção de categorias</p></div></header>
<?php if ($message): ?><div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<div class="admin-table-container" style="margin-bottom:30px;">
<h3>Nova categoria</h3>
<form method="POST" style="display:grid; gap:12px;"><input type="hidden" name="action" value="create"><input type="text" name="name" placeholder="Nome da categoria" required><textarea name="description" placeholder="Descrição (opcional)"></textarea><button class="btn btn-primary" type="submit">Cadastrar</button></form>
</div>
<div class="admin-table-container"><table class="admin-table"><thead><tr><th>ID</th><th>Nome</th><th>Produtos</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($categories as $category): ?><tr><td><?php echo (int) $category['id']; ?></td><td><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $category['total_products']; ?></td><td><form method="POST" onsubmit="return confirm('Deseja remover esta categoria?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?php echo (int) $category['id']; ?>"><button class="btn btn-secondary" type="submit">Excluir</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></main></div></body></html>
