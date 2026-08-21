<?php
$page_title = 'Royal Tech - Loja de Tecnologia Premium';
$show_breadcrumb = false;
$current_page = 'inicio';
$base_path = '';
require_once __DIR__ . '/includes/csrf.php';
include __DIR__ . '/database/connection.php';

$allBanners = $pdo->query('SELECT * FROM e5_banners WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5')->fetchAll();

$featuredProducts = $pdo->query('SELECT p.id, p.name, p.price, p.old_price, p.brand, p.stock, c.name AS category_name,
    (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
    FROM e5_products p INNER JOIN e5_categories c ON c.id = p.category_id WHERE p.is_featured = 1 ORDER BY p.created_at DESC LIMIT 8')->fetchAll();

$newProducts = $pdo->query('SELECT p.id, p.name, p.price, p.old_price, p.brand, p.stock, c.name AS category_name,
    (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
    FROM e5_products p INNER JOIN e5_categories c ON c.id = p.category_id ORDER BY p.created_at DESC LIMIT 8')->fetchAll();

$categories = $pdo->query('SELECT id, name, slug, (SELECT COUNT(*) FROM e5_products WHERE category_id = c.id) AS product_count FROM e5_categories c ORDER BY name ASC')->fetchAll();

$categoryIcons = [
    'notebooks' => 'fa-laptop', 'smartphones' => 'fa-mobile-alt', 'tablets' => 'fa-tablet-alt',
    'perifericos' => 'fa-keyboard', 'audio' => 'fa-headphones', 'games' => 'fa-gamepad',
    'cameras' => 'fa-camera', 'acessorios' => 'fa-headset', 'monitores' => 'fa-tv',
    'wearables' => 'fa-clock', 'rede' => 'fa-wifi', 'cabo' => 'fa-plug',
    'componentes' => 'fa-microchip',
];

include 'components/header.php';
?>

<!-- Banner Carousel -->
<?php if (!empty($allBanners)): ?>
<section class="ml-carousel-section">
    <div class="ml-carousel" id="ml-carousel">
        <div class="ml-carousel-track" id="ml-carousel-track">
            <?php foreach ($allBanners as $banner):
                $bannerImg = '';
                if (preg_match('#^https?://#i', $banner['image_path'])) {
                    $bannerImg = $banner['image_path'];
                } elseif ($banner['image_path'] !== '') {
                    $bannerImg = ltrim($banner['image_path'], '/');
                }
                $bannerLink = $banner['link_url'] ?: ($basePath . 'pages/products/products.php');
                $linkIsExternal = preg_match('#^https?://#i', $bannerLink);
                if (!$linkIsExternal && $bannerLink !== '#' && $bannerLink[0] === '/') {
                    $bannerLink = ltrim($bannerLink, '/');
                }
            ?>
            <div class="ml-carousel-slide">
                <?php if ($bannerImg): ?>
                    <img src="<?php echo htmlspecialchars($bannerImg, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($banner['title'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php else: ?>
                    <div style="width:100%;height:100%;background:linear-gradient(135deg, #222 0%, #1a1a1a 100%);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-gift" style="font-size:4rem;color:#d4af37;opacity:0.3;"></i>
                    </div>
                <?php endif; ?>
                <div class="ml-carousel-slide-content">
                    <h3 class="ml-carousel-slide-title">
                        <?php echo htmlspecialchars($banner['title'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!empty($banner['subtitle'])): ?>
                            <span style="color:#d4af37;"> — <?php echo htmlspecialchars($banner['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </h3>
                    <a href="<?php echo htmlspecialchars($bannerLink, ENT_QUOTES, 'UTF-8'); ?>" class="ml-carousel-slide-btn"
                       <?php echo $linkIsExternal ? 'target="_blank" rel="noopener"' : ''; ?>>
                        <i class="fas fa-tags"></i> Conferir
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($allBanners) > 1): ?>
        <button class="ml-carousel-btn ml-carousel-prev" id="ml-carousel-prev" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button>
        <button class="ml-carousel-btn ml-carousel-next" id="ml-carousel-next" aria-label="Próximo"><i class="fas fa-chevron-right"></i></button>
        <?php endif; ?>
    </div>
    <?php if (count($allBanners) > 1): ?>
    <div class="ml-carousel-dots" id="ml-carousel-dots">
        <?php for ($i = 0; $i < count($allBanners); $i++): ?>
            <button class="ml-carousel-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>" aria-label="Slide <?php echo $i + 1; ?>"></button>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>
<?php else: ?>
<!-- Promo Hero (no banners) -->
<section class="ml-promo-hero">
    <h2>Bem-vindo à <span style="color:#d4af37;">Royal Tech</span></h2>
    <p>Descubra os melhores produtos tecnológicos com qualidade premium e atendimento personalizado.</p>
    <a href="pages/products/products.php" class="ml-carousel-slide-btn" style="margin:0 auto;">
        <i class="fas fa-shopping-bag"></i> Ver Produtos
    </a>
</section>
<?php endif; ?>

<!-- Features Strip -->
<div class="container">
    <div class="ml-features-strip">
        <div class="ml-feature-item">
            <span class="ml-feature-icon"><i class="fas fa-shipping-fast"></i></span>
            <div class="ml-feature-text">
                <h4>Frete grátis</h4>
                <p>Acima de R$ 500</p>
            </div>
        </div>
        <div class="ml-feature-item">
            <span class="ml-feature-icon"><i class="fas fa-shield-alt"></i></span>
            <div class="ml-feature-text">
                <h4>Garantia 12 meses</h4>
                <p>Todos os produtos</p>
            </div>
        </div>
        <div class="ml-feature-item">
            <span class="ml-feature-icon"><i class="fas fa-undo"></i></span>
            <div class="ml-feature-text">
                <h4>Devolução grátis</h4>
                <p>30 dias</p>
            </div>
        </div>
        <div class="ml-feature-item">
            <span class="ml-feature-icon"><i class="fas fa-headset"></i></span>
            <div class="ml-feature-text">
                <h4>Suporte 24/7</h4>
                <p>Atendimento rápido</p>
            </div>
        </div>
    </div>
</div>

<!-- Featured Products -->
<?php if (!empty($featuredProducts)): ?>
<section class="ml-section">
    <div class="container">
        <div class="ml-section-header">
            <h2 class="ml-section-title">Produtos em Destaque</h2>
            <a href="pages/products/products.php" class="ml-section-link">Ver todos <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="ml-products-grid">
            <?php foreach ($featuredProducts as $p):
                $product_id = (int) $p['id'];
                $product_name = $p['name'];
                $product_price = (float) $p['price'];
                $product_old_price = $p['old_price'] !== null ? (float) $p['old_price'] : null;
                $product_image = $p['image_path'] ?: 'assets/img/placeholder-product.svg';
                $product_category = $p['category_name'];
                $product_brand = $p['brand'] ?? 'Royal Tech';
                $product_installments = '12x';
                $product_is_featured = true;
                $product_is_new = false;
                $product_stock = (int) $p['stock'];
                include 'components/product-card.php';
            endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Categories Quick Access -->
<?php if (!empty($categories)): ?>
<section class="ml-section" style="background:var(--ml-bg-card, #222);">
    <div class="container">
        <div class="ml-section-header">
            <h2 class="ml-section-title">Categorias</h2>
            <a href="pages/products/categories.php" class="ml-section-link">Ver todas <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="ml-products-grid" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;">
            <?php foreach ($categories as $cat):
                $icon = $categoryIcons[$cat['slug']] ?? 'fa-folder';
            ?>
            <a href="pages/products/products.php?category_id=<?php echo (int) $cat['id']; ?>" class="ml-product-card" style="text-decoration:none;">
                <div class="ml-card-body" style="text-align:center; padding:24px 14px;">
                    <div style="font-size:2rem; color:#d4af37; margin-bottom:10px;">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <h3 class="ml-card-title" style="-webkit-line-clamp:1; margin-bottom:4px;">
                        <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </h3>
                    <span style="font-size:0.78rem; color:#777;"><?php echo (int) $cat['product_count']; ?> produtos</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- New Products -->
<?php if (!empty($newProducts)): ?>
<section class="ml-section">
    <div class="container">
        <div class="ml-section-header">
            <h2 class="ml-section-title">Novidades</h2>
            <a href="pages/products/products.php?sort=newest" class="ml-section-link">Ver todos <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="ml-products-grid">
            <?php foreach ($newProducts as $p):
                $product_id = (int) $p['id'];
                $product_name = $p['name'];
                $product_price = (float) $p['price'];
                $product_old_price = $p['old_price'] !== null ? (float) $p['old_price'] : null;
                $product_image = $p['image_path'] ?: 'assets/img/placeholder-product.svg';
                $product_category = $p['category_name'];
                $product_brand = $p['brand'] ?? 'Royal Tech';
                $product_installments = '12x';
                $product_is_featured = false;
                $product_is_new = true;
                $product_stock = (int) $p['stock'];
                include 'components/product-card.php';
            endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Newsletter -->
<?php
$newsletterMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newsletter_email'])) {
    if (!csrf_verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página.');
    }
    $email = trim((string) $_POST['newsletter_email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            include 'database/connection.php';
            $stmt = $pdo->prepare('INSERT IGNORE INTO e5_newsletter (email) VALUES (:email)');
            $stmt->execute([':email' => $email]);
            $newsletterMessage = 'E-mail cadastrado com sucesso!';
        } catch (Throwable $e) {
            $newsletterMessage = 'Erro ao cadastrar. Tente novamente.';
        }
    } else {
        $newsletterMessage = 'E-mail inválido.';
    }
}
?>
<section class="ml-section">
    <div class="container">
        <div class="ml-newsletter">
            <h3>Receba Ofertas Exclusivas</h3>
            <p>Cadastre-se e seja o primeiro a saber sobre promoções e lançamentos</p>
            <?php if ($newsletterMessage): ?>
                <div style="margin-bottom:12px; color:#d4af37; font-size:0.9rem;"><?php echo htmlspecialchars($newsletterMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="POST" class="ml-newsletter-form">
                <?php echo csrf_field(); ?>
                <input type="email" name="newsletter_email" placeholder="Seu melhor e-mail..." required>
                <button type="submit">Inscrever-se</button>
            </form>
        </div>
    </div>
</section>

<!-- Footer -->
<?php include 'components/footer.php'; ?>
