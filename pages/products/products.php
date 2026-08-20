<?php
$page_title = 'Produtos - Royal Tech';
$breadcrumb_title = 'Produtos';
$current_page = 'produtos';
$base_path = '../../';

include '../../database/connection.php';

$categoryFilter = (int) ($_GET['category_id'] ?? 0);
$searchTerm = trim((string) ($_GET['q'] ?? ''));
$minPrice = (float) ($_GET['min_price'] ?? 0);
$maxPrice = (float) ($_GET['max_price'] ?? 0);
$brandFilter = trim((string) ($_GET['brand'] ?? ''));
$sortOrder = (string) ($_GET['sort'] ?? 'recent');
$offersOnly = (string) ($_GET['offers'] ?? '') === '1';

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
if ($offersOnly) {$sql .= ' AND p.old_price IS NOT NULL AND p.old_price > p.price';}
if ($sortOrder === 'newest') {$sql .= ' AND p.created_at >= NOW() - INTERVAL 30 DAY';}

$allowedSort = [
    'recent' => 'p.created_at DESC',
    'newest' => 'p.created_at DESC',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc' => 'p.name ASC',
];
$defaultSort = $offersOnly ? '((p.old_price - p.price) / p.old_price) DESC, p.created_at DESC' : 'p.created_at DESC';
$sql .= ' ORDER BY ' . ($allowedSort[$sortOrder] ?? $defaultSort);

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
$categories = $pdo->query('SELECT c.id, c.name, c.slug, (SELECT COUNT(*) FROM e5_products p WHERE p.category_id = c.id) AS product_count FROM e5_categories c ORDER BY c.name ASC')->fetchAll();
$brands = $pdo->query('SELECT DISTINCT brand FROM e5_products WHERE brand IS NOT NULL AND brand != \'\' ORDER BY brand')->fetchAll(PDO::FETCH_COLUMN);

$categoryIcons = [
    'notebooks' => 'fa-laptop', 'smartphones' => 'fa-mobile-alt', 'tablets' => 'fa-tablet-alt',
    'perifericos' => 'fa-keyboard', 'audio' => 'fa-headphones', 'games' => 'fa-gamepad',
    'cameras' => 'fa-camera', 'acessorios' => 'fa-headset', 'monitores' => 'fa-tv',
    'wearables' => 'fa-clock', 'rede' => 'fa-wifi', 'cabo' => 'fa-plug',
    'componentes' => 'fa-microchip',
];

function buildQueryString($overrides) {
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    return http_build_query($params);
}

if ($searchTerm === '' && $categoryFilter === 0) {
    if ($offersOnly) {
        $page_title = 'Ofertas - Royal Tech';
        $breadcrumb_title = 'Ofertas';
    } elseif ($sortOrder === 'newest') {
        $page_title = 'Novidades - Royal Tech';
        $breadcrumb_title = 'Novidades';
    }
}

include '../../components/header.php';
?>

<div class="ml-layout">
    <!-- Sidebar (desktop) -->
    <aside class="ml-sidebar" id="ml-sidebar-desktop">
        <div class="ml-sidebar-card">
            <h3 class="ml-sidebar-title"><i class="fas fa-filter" style="margin-right:6px;color:#d4af37;"></i> Filtros</h3>
            <form method="GET" id="ml-filter-form">
                <?php if ($searchTerm): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>

                <div class="ml-sidebar-filter-group">
                    <label class="ml-sidebar-filter-label">Categoria</label>
                    <select name="category_id" class="ml-sidebar-filter-select" onchange="this.form.submit()">
                        <option value="0">Todas categorias</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $categoryFilter === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ml-sidebar-filter-group">
                    <label class="ml-sidebar-filter-label">Marca</label>
                    <select name="brand" class="ml-sidebar-filter-select" onchange="this.form.submit()">
                        <option value="">Todas marcas</option>
                        <?php foreach($brands as $b): ?>
                            <option value="<?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $brandFilter === $b ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ml-sidebar-filter-group" style="display:flex; gap:6px;">
                    <div style="flex:1;">
                        <label class="ml-sidebar-filter-label">Preço mín.</label>
                        <input type="number" name="min_price" class="ml-sidebar-filter-input"
                               placeholder="R$ min" value="<?php echo $minPrice > 0 ? (float)$minPrice : ''; ?>" step="0.01">
                    </div>
                    <div style="flex:1;">
                        <label class="ml-sidebar-filter-label">Preço máx.</label>
                        <input type="number" name="max_price" class="ml-sidebar-filter-input"
                               placeholder="R$ max" value="<?php echo $maxPrice > 0 ? (float)$maxPrice : ''; ?>" step="0.01">
                    </div>
                </div>

                <button type="submit" class="ml-sidebar-filter-btn">
                    <i class="fas fa-search"></i> Aplicar Filtros
                </button>
            </form>
        </div>

        <!-- Category list -->
        <div class="ml-sidebar-card">
            <h3 class="ml-sidebar-title"><i class="fas fa-th-list" style="margin-right:6px;color:#d4af37;"></i> Categorias</h3>
            <ul class="ml-sidebar-list">
                <li class="ml-sidebar-item <?php echo $categoryFilter === 0 ? 'active' : ''; ?>">
                    <a href="?<?php echo buildQueryString(['category_id' => 0]); ?>">
                        Todos os produtos
                    </a>
                </li>
                <?php foreach ($categories as $cat):
                    $icon = $categoryIcons[$cat['slug']] ?? 'fa-folder';
                ?>
                <li class="ml-sidebar-item <?php echo $categoryFilter === (int)$cat['id'] ? 'active' : ''; ?>">
                    <a href="?<?php echo buildQueryString(['category_id' => (int)$cat['id']]); ?>">
                        <span>
                            <i class="fas <?php echo $icon; ?>" style="margin-right:8px;width:16px;text-align:center;color:#d4af37;font-size:0.8rem;"></i>
                            <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <span class="ml-sidebar-count"><?php echo (int)$cat['product_count']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <!-- Mobile Sidebar Drawer -->
    <aside class="ml-sidebar-drawer" id="ml-sidebar-drawer">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
            <h3 style="font-size:1rem; color:var(--ml-text);">Filtros</h3>
            <button onclick="document.getElementById('ml-sidebar-drawer').classList.remove('active');document.getElementById('ml-sidebar-overlay').classList.remove('active');document.body.style.overflow='';"
                    style="background:none;border:none;color:var(--ml-text-secondary);font-size:1.2rem;cursor:pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="ml-sidebar-card">
            <form method="GET" id="ml-filter-form-mobile">
                <?php if ($searchTerm): ?>
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>

                <div class="ml-sidebar-filter-group">
                    <label class="ml-sidebar-filter-label">Categoria</label>
                    <select name="category_id" class="ml-sidebar-filter-select">
                        <option value="0">Todas categorias</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $categoryFilter === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ml-sidebar-filter-group">
                    <label class="ml-sidebar-filter-label">Marca</label>
                    <select name="brand" class="ml-sidebar-filter-select">
                        <option value="">Todas marcas</option>
                        <?php foreach($brands as $b): ?>
                            <option value="<?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $brandFilter === $b ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ml-sidebar-filter-group" style="display:flex; gap:6px;">
                    <div style="flex:1;">
                        <label class="ml-sidebar-filter-label">Preço mín.</label>
                        <input type="number" name="min_price" class="ml-sidebar-filter-input"
                               placeholder="R$ min" value="<?php echo $minPrice > 0 ? (float)$minPrice : ''; ?>" step="0.01">
                    </div>
                    <div style="flex:1;">
                        <label class="ml-sidebar-filter-label">Preço máx.</label>
                        <input type="number" name="max_price" class="ml-sidebar-filter-input"
                               placeholder="R$ max" value="<?php echo $maxPrice > 0 ? (float)$maxPrice : ''; ?>" step="0.01">
                    </div>
                </div>

                <button type="submit" class="ml-sidebar-filter-btn">
                    <i class="fas fa-search"></i> Aplicar Filtros
                </button>
            </form>
        </div>

        <div class="ml-sidebar-card">
            <h3 class="ml-sidebar-title">Categorias</h3>
            <ul class="ml-sidebar-list">
                <li class="ml-sidebar-item <?php echo $categoryFilter === 0 ? 'active' : ''; ?>">
                    <a href="?<?php echo buildQueryString(['category_id' => 0]); ?>">Todos</a>
                </li>
                <?php foreach ($categories as $cat):
                    $icon = $categoryIcons[$cat['slug']] ?? 'fa-folder';
                ?>
                <li class="ml-sidebar-item <?php echo $categoryFilter === (int)$cat['id'] ? 'active' : ''; ?>">
                    <a href="?<?php echo buildQueryString(['category_id' => (int)$cat['id']]); ?>">
                        <span>
                            <i class="fas <?php echo $icon; ?>" style="margin-right:8px;color:#d4af37;font-size:0.8rem;"></i>
                            <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <span class="ml-sidebar-count"><?php echo (int)$cat['product_count']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="ml-main">
        <!-- Mobile filter button -->
        <button class="ml-mobile-filter-btn" onclick="document.getElementById('ml-sidebar-drawer').classList.add('active');document.getElementById('ml-sidebar-overlay').classList.add('active');document.body.style.overflow='hidden';">
            <i class="fas fa-sliders-h"></i> Filtros
        </button>

        <!-- Main Header -->
        <div class="ml-main-header">
            <div>
                <h1 class="ml-main-title">
                    <?php if ($searchTerm): ?>
                        Resultados para "<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php elseif ($categoryFilter > 0): ?>
                        <?php
                        $activeCatName = '';
                        foreach ($categories as $c) {
                            if ((int)$c['id'] === $categoryFilter) {
                                $activeCatName = $c['name'];
                                break;
                            }
                        }
                        echo htmlspecialchars($activeCatName ?: 'Produtos', ENT_QUOTES, 'UTF-8');
                        ?>
                    <?php elseif ($offersOnly): ?>
                        Produtos em promoção
                    <?php elseif ($sortOrder === 'newest'): ?>
                        Novidades
                    <?php else: ?>
                        Todos os Produtos
                    <?php endif; ?>
                </h1>
                <span class="ml-main-count"><?php echo $totalProducts; ?> produto(s)</span>
            </div>
            <div class="ml-main-sort">
                <label for="ml-sort">Ordenar:</label>
                <select id="ml-sort" onchange="window.location.href='?'+this.value">
                    <?php
                    $sortOptions = [
                        'recent' => 'Mais recentes',
                        'newest' => 'Novidades (30 dias)',
                        'price_asc' => 'Menor preço',
                        'price_desc' => 'Maior preço',
                        'name_asc' => 'A-Z',
                    ];
                    foreach ($sortOptions as $val => $label):
                        $qs = buildQueryString(['sort' => $val]);
                    ?>
                        <option value="<?php echo $qs; ?>" <?php echo $sortOrder === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="ml-empty">
                <i class="fas fa-search"></i>
                <h3>Nenhum produto encontrado</h3>
                <p>Tente ajustar os filtros ou buscar por outro termo.</p>
            </div>
        <?php else: ?>
            <div class="ml-products-grid">
                <?php foreach($products as $product):
                    $product_id = (int)$product['id'];
                    $product_name = $product['name'];
                    $product_price = (float)$product['price'];
                    $product_old_price = $product['old_price'] !== null ? (float)$product['old_price'] : null;
                    $product_image = $product['image_path'] ?: '../../assets/img/placeholder-product.svg';
                    $product_category = $product['category_name'];
                    $product_brand = $product['brand'] ?? 'Royal Tech';
                    $product_installments = '12x';
                    $product_is_featured = (bool)$product['is_featured'];
                    $product_is_new = false;
                    $product_stock = isset($product['stock']) ? (int)$product['stock'] : 1;
                    include '../../components/product-card.php';
                endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="ml-pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo buildQueryString(['page' => $page - 1]); ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                if ($start > 1): ?>
                    <a href="?<?php echo buildQueryString(['page' => 1]); ?>">1</a>
                    <?php if ($start > 2): ?><span style="border:none;background:none;color:#777;">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?<?php echo buildQueryString(['page' => $i]); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><span style="border:none;background:none;color:#777;">...</span><?php endif; ?>
                    <a href="?<?php echo buildQueryString(['page' => $totalPages]); ?>"><?php echo $totalPages; ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo buildQueryString(['page' => $page + 1]); ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
