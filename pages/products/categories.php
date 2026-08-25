<?php
$page_title = 'Categorias - Royal Tech';
$breadcrumb_title = 'Categorias';
$current_page = 'categorias';
$base_path = '../../';

include '../../database/connection.php';
include '../../components/header.php';

require_once __DIR__ . '/../../includes/category_icons.php';
$categories = $pdo->query('SELECT c.id, c.name, c.slug, c.description, COUNT(p.id) AS total_products FROM e5_categories c LEFT JOIN e5_products p ON p.category_id = c.id GROUP BY c.id, c.name, c.slug, c.description ORDER BY c.name')->fetchAll();
?>
<section class="section"><div class="container"><div class="section-header"><h2>Categorias</h2><p>Navegue por categorias dinâmicas cadastradas no painel admin</p></div>
<div class="categories-grid">
<?php foreach ($categories as $category): $icon = $categoryIcons[$category['slug']] ?? 'fa-folder'; ?>
  <a href="products.php?category_id=<?php echo (int) $category['id']; ?>" class="category-card">
    <div class="category-icon"><i class="fas <?php echo $icon; ?>"></i></div>
    <h4><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
    <span><?php echo htmlspecialchars($category['description'] ?: 'Sem descrição cadastrada.', ENT_QUOTES, 'UTF-8'); ?></span>
    <div class="category-count"><?php echo (int) $category['total_products']; ?> produtos</div>
  </a>
<?php endforeach; ?>
</div></div></section>
<?php include '../../components/footer.php'; ?>
