<?php
$page_title = 'Gerenciar Categorias - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
$activePage = 'categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string) $_POST['name']);
        $description = trim((string) $_POST['description']);
        if ($name !== '') {
            $slugBase = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
            $stmt = $pdo->prepare('INSERT INTO e5_categories (name, slug, description) VALUES (:name, :slug, :description)');
            $stmt->execute([':name' => $name, ':slug' => $slugBase . '-' . time(), ':description' => $description ?: null]);
            $_SESSION['admin_message'] = 'Categoria criada com sucesso.';
        }
    }

    if ($action === 'edit') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $name = trim((string) $_POST['name']);
        $description = trim((string) $_POST['description']);
        if ($categoryId > 0 && $name !== '') {
            $slugBase = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
            $stmt = $pdo->prepare('UPDATE e5_categories SET name = :name, slug = :slug, description = :description WHERE id = :id');
            $stmt->execute([':name' => $name, ':slug' => $slugBase . '-' . time(), ':description' => $description ?: null, ':id' => $categoryId]);
            $_SESSION['admin_message'] = 'Categoria atualizada com sucesso.';
        }
    }

    if ($action === 'delete') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId > 0) {
            $stmt = $pdo->prepare('DELETE FROM e5_categories WHERE id = :id');
            $stmt->execute([':id' => $categoryId]);
            $_SESSION['admin_message'] = 'Categoria removida com sucesso.';
        }
    }

    header('Location: categories.php');
    exit;
}

$categories = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM e5_products p WHERE p.category_id = c.id) AS total_products FROM e5_categories c ORDER BY c.name')->fetchAll();
$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);
$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($categories as $cat) {
        if ((int) $cat['id'] === $editId) {
            $editCategory = $cat;
            break;
        }
    }
}
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
                    <h2>Categorias</h2>
                    <p>Cadastro e gerenciamento de categorias</p>
                </div>
                <div class="admin-actions">
                    <?php include 'header_user_inc.php'; ?>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="admin-table-container" style="margin-bottom:30px;">
                <div class="admin-table-header">
                    <h3><?php echo $editCategory ? 'Editar categoria' : 'Nova categoria'; ?></h3>
                </div>
                <form method="POST" style="padding:20px 25px;">
                    <input type="hidden" name="action" value="<?php echo $editCategory ? 'edit' : 'create'; ?>">
                    <?php echo csrf_field(); ?>
                    <?php if ($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?php echo (int) $editCategory['id']; ?>">
                    <?php endif; ?>
                    <div class="admin-form-group">
                        <label for="cat_name">Nome da categoria</label>
                        <input type="text" id="cat_name" name="name" placeholder="Ex: Eletrônicos" value="<?php echo $editCategory ? htmlspecialchars($editCategory['name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    </div>
                    <div class="admin-form-group">
                        <label for="cat_desc">Descrição <small>(opcional)</small></label>
                        <textarea id="cat_desc" name="description" placeholder="Breve descrição da categoria"><?php echo $editCategory ? htmlspecialchars($editCategory['description'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button class="btn btn-primary" type="submit"><?php echo $editCategory ? 'Atualizar' : 'Cadastrar'; ?></button>
                        <?php if ($editCategory): ?>
                        <a class="btn btn-secondary" href="categories.php">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h3>Todas as categorias</h3>
                    <span style="color:var(--color-gray); font-size:0.85rem;"><?php echo count($categories); ?> categorias</span>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Produtos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                        <tr><td colspan="5" class="empty-state">Nenhuma categoria cadastrada.</td></tr>
                        <?php else: foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo (int) $category['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td style="color:var(--color-gray);"><?php echo $category['description'] ? htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8') : '<em style="color:var(--color-gray);">—</em>'; ?></td>
                            <td><?php echo (int) $category['total_products']; ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="?edit=<?php echo (int) $category['id']; ?>" aria-label="Editar categoria"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Deseja remover esta categoria?');">
                                        <input type="hidden" name="action" value="delete">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="category_id" value="<?php echo (int) $category['id']; ?>">
                                        <button type="submit" class="delete" aria-label="Excluir categoria"><i class="fas fa-trash"></i></button>
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
