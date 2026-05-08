<?php
$page_title = 'Formulário de Produto - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';

$productId = (int) ($_GET['id'] ?? 0);
$product = ['category_id' => '', 'name' => '', 'description' => '', 'brand' => '', 'price' => '', 'old_price' => '', 'stock' => 0, 'is_featured' => 0];

if ($productId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $productId]);
    $result = $stmt->fetch();
    if ($result) {
        $product = $result;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        ':category_id' => (int) $_POST['category_id'],
        ':name' => trim((string) $_POST['name']),
        ':slug' => strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $_POST['name']), '-')) . '-' . time(),
        ':description' => trim((string) $_POST['description']) ?: null,
        ':brand' => trim((string) $_POST['brand']) ?: null,
        ':price' => (float) $_POST['price'],
        ':old_price' => $_POST['old_price'] !== '' ? (float) $_POST['old_price'] : null,
        ':stock' => (int) $_POST['stock'],
        ':is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    ];

    if ($productId > 0) {
        $payload[':id'] = $productId;
        $sql = 'UPDATE products SET category_id=:category_id, name=:name, description=:description, brand=:brand, price=:price, old_price=:old_price, stock=:stock, is_featured=:is_featured WHERE id=:id';
    } else {
        $sql = 'INSERT INTO products (category_id, name, slug, description, brand, price, old_price, stock, is_featured) VALUES (:category_id, :name, :slug, :description, :brand, :price, :old_price, :stock, :is_featured)';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($payload);
    $_SESSION['admin_message'] = $productId > 0 ? 'Produto atualizado com sucesso.' : 'Produto criado com sucesso.';
    header('Location: products.php');
    exit;
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo $page_title; ?></title><link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/admin.css"></head><body>
<div class="admin-wrapper"><main class="admin-main" style="margin-left:0; max-width:900px; margin-inline:auto;"><header class="admin-header"><div class="admin-title"><h2><?php echo $productId > 0 ? 'Editar Produto' : 'Novo Produto'; ?></h2></div></header>
<div class="admin-table-container"><form method="POST" style="display:grid; gap:12px;">
<select name="category_id" required><option value="">Selecione a categoria</option><?php foreach($categories as $category): ?><option value="<?php echo (int)$category['id']; ?>" <?php echo (int)$product['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
<input type="text" name="name" placeholder="Nome" value="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
<textarea name="description" placeholder="Descrição"><?php echo htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
<input type="text" name="brand" placeholder="Marca" value="<?php echo htmlspecialchars($product['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<input type="number" step="0.01" name="price" placeholder="Preço" value="<?php echo htmlspecialchars((string)$product['price'], ENT_QUOTES, 'UTF-8'); ?>" required>
<input type="number" step="0.01" name="old_price" placeholder="Preço anterior" value="<?php echo htmlspecialchars((string)($product['old_price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<input type="number" name="stock" placeholder="Estoque" value="<?php echo (int)$product['stock']; ?>" required>
<label><input type="checkbox" name="is_featured" <?php echo (int)$product['is_featured'] === 1 ? 'checked' : ''; ?>> Produto em destaque</label>
<button class="btn btn-primary" type="submit">Salvar</button>
<a class="btn btn-secondary" href="products.php">Voltar</a>
</form></div></main></div></body></html>
