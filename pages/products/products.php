<?php
$page_title = 'Produtos - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Produtos';
$current_page = 'produtos';
$base_path = '../../';

include '../../database/connection.php';

$categoryFilter = (int) ($_GET['category_id'] ?? 0);
$searchTerm = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT p.id, p.name, p.price, p.old_price, p.brand, p.is_featured, c.name AS category_name
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE 1=1';
$params = [];

if ($categoryFilter > 0) {
    $sql .= ' AND p.category_id = :category_id';
    $params[':category_id'] = $categoryFilter;
}

if ($searchTerm !== '') {
    $sql .= ' AND (p.name LIKE :term OR p.brand LIKE :term)';
    $params[':term'] = '%' . $searchTerm . '%';
}

$sql .= ' ORDER BY p.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

include '../../components/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Nossos Produtos</h2>
            <p>Explore nosso catálogo com dados reais do banco</p>
        </div>

        <form method="GET" class="admin-form-group" style="display:flex; gap:10px; margin-bottom: 30px;">
            <input type="text" name="q" placeholder="Buscar por nome ou marca" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
            <select name="category_id">
                <option value="0">Todas as categorias</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo (int) $category['id']; ?>" <?php echo $categoryFilter === (int) $category['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </form>

        <div class="products-grid">
            <?php if (empty($products)): ?>
                <p>Nenhum produto encontrado para os filtros aplicados.</p>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <?php
                    $product_id = (int) $product['id'];
                    $product_name = $product['name'];
                    $product_price = (float) $product['price'];
                    $product_old_price = $product['old_price'] !== null ? (float) $product['old_price'] : null;
                    $product_image = '../../assets/img/placeholder-product.jpg';
                    $product_category = $product['category_name'];
                    $product_brand = $product['brand'] ?? 'Royal Tech';
                    $product_installments = '12x';
                    $product_is_featured = (bool) $product['is_featured'];
                    $product_is_new = false;
                    include '../../components/product-card.php';
                    ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include '../../components/footer.php'; ?>
