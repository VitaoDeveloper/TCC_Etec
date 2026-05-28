<?php
$page_title = 'Produtos - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Produtos';
$current_page = 'produtos';
$base_path = '../../';

include '../../database/connection.php';

$categoryFilter = (int) ($_GET['category_id'] ?? 0);
$searchTerm = trim((string) ($_GET['q'] ?? ''));
$minPrice = (float) ($_GET['min_price'] ?? 0);
$maxPrice = (float) ($_GET['max_price'] ?? 0);
$brandFilter = trim((string) ($_GET['brand'] ?? ''));
$sortOrder = (string) ($_GET['sort'] ?? 'newest');

$sql = 'SELECT p.id, p.name, p.price, p.old_price, p.brand, p.is_featured, p.stock, c.name AS category_name,
        (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
        FROM e5_products p
        INNER JOIN e5_categories c ON c.id = p.category_id
        WHERE 1=1';
$params = [];
if ($categoryFilter > 0) {$sql .= ' AND p.category_id = :category_id'; $params[':category_id'] = $categoryFilter;}
if ($searchTerm !== '') {
    $sql .= ' AND (p.name LIKE :term_name OR p.brand LIKE :term_brand OR c.name LIKE :term_category)';
    $searchPattern = '%' . $searchTerm . '%';
    $params[':term_name'] = $searchPattern;
    $params[':term_brand'] = $searchPattern;
    $params[':term_category'] = $searchPattern;
}
if ($minPrice > 0) {$sql .= ' AND p.price >= :min_price'; $params[':min_price'] = $minPrice;}
if ($maxPrice > 0) {$sql .= ' AND p.price <= :max_price'; $params[':max_price'] = $maxPrice;}
if ($brandFilter !== '') {$sql .= ' AND p.brand = :brand'; $params[':brand'] = $brandFilter;}

$allowedSort = [
    'newest' => 'p.created_at DESC',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc' => 'p.name ASC',
];
$sql .= ' ORDER BY ' . ($allowedSort[$sortOrder] ?? 'p.created_at DESC');

$countSql = 'SELECT COUNT(*)' . substr($sql, strrpos($sql, 'FROM'));
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$sql .= ' LIMIT :limit OFFSET :offset';
$params[':limit'] = $perPage;
$params[':offset'] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = $pdo->query('SELECT id, name FROM e5_categories ORDER BY name')->fetchAll();
$brands = $pdo->query('SELECT DISTINCT brand FROM e5_products WHERE brand IS NOT NULL AND brand != \'\' ORDER BY brand')->fetchAll(PDO::FETCH_COLUMN);

function buildQueryString($overrides) {
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    return http_build_query($params);
}

include '../../components/header.php';
?>
<section class="section"><div class="container"><div class="section-header"><h2>Nossos Produtos</h2><p><?php echo $totalProducts; ?> produto(s) — Página <?php echo $page; ?> de <?php echo $totalPages; ?></p></div>
<form method="GET" class="admin-form-group" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:30px;">
<input type="text" name="q" placeholder="Buscar por nome, marca ou categoria" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" style="flex:1; min-width:160px;">
<select name="category_id"><option value="0">Todas categorias</option><?php foreach($categories as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $categoryFilter === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
<select name="brand"><option value="">Todas marcas</option><?php foreach($brands as $b): ?><option value="<?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $brandFilter === $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
<input type="number" name="min_price" placeholder="Preço min" value="<?php echo $minPrice > 0 ? (float)$minPrice : ''; ?>" style="width:100px;" step="0.01">
<input type="number" name="max_price" placeholder="Preço max" value="<?php echo $maxPrice > 0 ? (float)$maxPrice : ''; ?>" style="width:100px;" step="0.01">
<select name="sort"><option value="newest" <?php echo $sortOrder === 'newest' ? 'selected' : ''; ?>>Mais recentes</option><option value="price_asc" <?php echo $sortOrder === 'price_asc' ? 'selected' : ''; ?>>Menor preço</option><option value="price_desc" <?php echo $sortOrder === 'price_desc' ? 'selected' : ''; ?>>Maior preço</option><option value="name_asc" <?php echo $sortOrder === 'name_asc' ? 'selected' : ''; ?>>A-Z</option></select>
<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
</form>
<div class="products-grid"><?php if (empty($products)): ?><p>Nenhum produto encontrado.</p><?php else: foreach($products as $product): $product_id=(int)$product['id']; $product_name=$product['name']; $product_price=(float)$product['price']; $product_old_price=$product['old_price']!==null?(float)$product['old_price']:null; $product_image=$product['image_path'] ?: 'assets/img/placeholder-product.svg'; $product_category=$product['category_name']; $product_brand=$product['brand'] ?? 'Royal Tech'; $product_installments='12x'; $product_is_featured=(bool)$product['is_featured']; $product_is_new=false; include '../../components/product-card.php'; endforeach; endif; ?></div>
<?php if ($totalPages > 1): ?><div style="display:flex; justify-content:center; gap:8px; margin-top:30px;"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="?<?php echo buildQueryString(['page' => $i]); ?>" class="btn btn-secondary <?php echo $i === $page ? 'active' : ''; ?>" style="min-width:40px;"><?php echo $i; ?></a><?php endfor; ?></div><?php endif; ?>
</div></section>
<?php include '../../components/footer.php'; ?>
