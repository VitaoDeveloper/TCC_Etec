<?php
$page_title = 'Royal Tech - Loja de Tecnologia Premium';
$show_breadcrumb = false;
$current_page = 'inicio';
$base_path = '';
require_once __DIR__ . '/includes/csrf.php';
include __DIR__ . '/database/connection.php';

$activeBanners = $pdo->query('SELECT * FROM e5_banners WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1')->fetchAll();
$featuredBanner = $activeBanners[0] ?? null;

include 'components/header.php';
?>
<div class="hero-bg">
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1> Tecnologia de <span>Alta Performance</span></h1>
                    <p>Descubra os melhores produtos tecnológicos do mercado com qualidade premium e atendimento personalizado. A Royal Tech traz inovação diretamente para suas mãos.</p>
                    <div class="hero-buttons">
                        <a href="pages/products/products.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag"></i>
                            Ver Produtos
                        </a>
                        <a href="pages/products/about.php" class="btn btn-secondary">
                            <i class="fas fa-info-circle"></i>
                            Sobre Nós
                        </a>
                    </div>
                </div>
                <div class="hero-image">
                    <!-- Espaço para imagem do Hero -->
                    <div class="placeholder">
                        <i class="fas fa-laptop"></i>
                        <h4>Imagem Hero Principal</h4>
                        <p>Insira uma imagem de produto premium ou banner aqui</p>
                        <small>尺寸: 500x500px</small>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Features Section -->
<section class="section section-dark">
    <div class="container">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <h4>Frete Grátis</h4>
                <p>Para compras acima de R$ 500 em toda região Sudeste</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>Garantia Premium</h4>
                <p>Todos os produtos com garantia estendida de 12 meses</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h4>Suporte 24/7</h4>
                <p>Equipe técnica disponível para atendê-lo a qualquer momento</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-undo"></i>
                </div>
                <h4>30 Dias de Devolução</h4>
                <p>Não ficou satisfeito? Devolvemos seu dinheiro</p>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Categorias</h2>
            <p>Navegue pelas nossas categorias e encontre exatamente o que precisa</p>
        </div>
        <div class="categories-grid">
            <a href="pages/products/products.php?category=notebooks" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-laptop"></i>
                </div>
                <h4>Notebooks</h4>
                <span>Ver produtos →</span>
            </a>
            <a href="pages/products/products.php?category=smartphones" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h4>Smartphones</h4>
                <span>Ver produtos →</span>
            </a>
            <a href="pages/products/products.php?category=tablets" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-tablet-alt"></i>
                </div>
                <h4>Tablets</h4>
                <span>Ver produtos →</span>
            </a>
            <a href="pages/products/products.php?category=perifericos" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-keyboard"></i>
                </div>
                <h4>Periféricos</h4>
                <span>Ver produtos →</span>
            </a>
            <a href="pages/products/products.php?category=audio" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-headphones"></i>
                </div>
                <h4>Áudio</h4>
                <span>Ver produtos →</span>
            </a>
            <a href="pages/products/products.php?category=games" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-gamepad"></i>
                </div>
                <h4>Games</h4>
                <span>Ver produtos →</span>
            </a>
            <a href="pages/products/products.php?category=cameras" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <h4>Câmeras</h4>
                <span>Ver produtos →</span>
            </a>
            <a href="pages/products/products.php?category=acessorios" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h4>Acessórios</h4>
                <span>Ver produtos →</span>
            </a>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <h2>Produtos em Destaque</h2>
            <p>Selecionamos os melhores produtos para você com ofertas especiais</p>
        </div>
        <div class="products-grid">
            <?php
            // Incluir componente de produto (repetir conforme necessário)
            include 'components/product-card.php';
            
            // Simular dados de produtos para exemplo
            $products = [
                [
                    'product_id' => 1,
                    'product_name' => 'Notebook Premium Pro',
                    'product_price' => 8999.00,
                    'product_old_price' => 9999.00,
                    'product_image' => 'assets/img/placeholder-product.svg',
                    'product_category' => 'Notebooks',
                    'product_brand' => 'Royal Tech',
                    'product_installments' => '12x',
                    'product_is_new' => true,
                    'product_is_featured' => true
                ],
                [
                    'product_id' => 2,
                    'product_name' => 'Smartphone Ultra X',
                    'product_price' => 4999.00,
                    'product_image' => 'assets/img/placeholder-product.svg',
                    'product_category' => 'Smartphones',
                    'product_brand' => 'Royal Tech',
                    'product_installments' => '12x',
                    'product_is_featured' => true
                ],
                [
                    'product_id' => 3,
                    'product_name' => 'Fone Premium Wireless',
                    'product_price' => 899.00,
                    'product_image' => 'assets/img/placeholder-product.svg',
                    'product_category' => 'Áudio',
                    'product_brand' => 'Royal Audio',
                    'product_installments' => '6x',
                    'product_is_new' => true
                ],
                [
                    'product_id' => 4,
                    'product_name' => 'Teclado Mecânico RGB',
                    'product_price' => 449.00,
                    'product_image' => 'assets/img/placeholder-product.svg',
                    'product_category' => 'Periféricos',
                    'product_brand' => 'Royal Gear',
                    'product_installments' => '6x',
                    'product_is_featured' => true
                ]
            ];
            
            foreach ($products as $product) {
                foreach ($product as $key => $value) {
                    $$key = $value;
                }
                include 'components/product-card.php';
            }
            ?>
        </div>
        <div class="text-center" style="margin-top: 50px;">
            <a href="pages/products/products.php" class="btn btn-gold">
                <i class="fas fa-eye"></i>
                Ver Todos os Produtos
            </a>
        </div>
    </div>
</section>

<!-- Banner Section -->
<?php if ($featuredBanner):
    $bannerImg = '';
    if (preg_match('#^https?://#i', $featuredBanner['image_path'])) {
        $bannerImg = $featuredBanner['image_path'];
    } elseif ($featuredBanner['image_path'] !== '') {
        $bannerImg = ltrim($featuredBanner['image_path'], '/');
    }
    $bannerLink = $featuredBanner['link_url'] ?: '#';
    $linkIsExternal = preg_match('#^https?://#i', $bannerLink);
    if (!$linkIsExternal && $bannerLink !== '#' && $bannerLink[0] === '/') {
        $bannerLink = ltrim($bannerLink, '/');
    }
?>
<section class="banner-section">
    <div class="container">
        <div class="banner-content">
            <div class="banner-text">
                <h2><?php echo htmlspecialchars($featuredBanner['title'], ENT_QUOTES, 'UTF-8'); ?><?php if ($featuredBanner['subtitle']): ?> <span><?php echo htmlspecialchars($featuredBanner['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></h2>
                <p><?php echo htmlspecialchars($featuredBanner['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <a href="<?php echo htmlspecialchars($bannerLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" <?php echo $linkIsExternal ? 'target="_blank" rel="noopener"' : ''; ?>>
                    <i class="fas fa-tags"></i>
                    Conferir
                </a>
            </div>
            <div class="banner-image">
                <?php if ($bannerImg): ?>
                <img src="<?php echo htmlspecialchars($bannerImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($featuredBanner['title'], ENT_QUOTES, 'UTF-8'); ?>" style="width:100%; max-height:400px; object-fit:cover; border-radius:10px;">
                <?php else: ?>
                <div class="placeholder" style="min-height:300px;">
                    <i class="fas fa-gift"></i>
                    <h4><?php echo htmlspecialchars($featuredBanner['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p>Adicione uma imagem pelo painel admin</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php else: ?>
<section class="banner-section">
    <div class="container">
        <div class="banner-content">
            <div class="banner-text">
                <h2>Promoção <span>Especial</span></h2>
                <p>Aproveite nossas ofertas exclusivas e transforme sua experiência tecnológica. Produtos premium com até 30% de desconto por tempo limitado.</p>
                <a href="pages/products/products.php" class="btn btn-primary">
                    <i class="fas fa-tags"></i>
                    Ver Produtos
                </a>
            </div>
            <div class="banner-image">
                <div class="placeholder" style="min-height:300px;">
                    <i class="fas fa-gift"></i>
                    <h4>Banner Promocional</h4>
                    <p>Crie um banner no painel administrativo</p>
                    <small>Dimensões: 600x400px</small>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- New Arrivals Section -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Novidades</h2>
            <p>Fique por dentro dos lançamentos mais recentes do mercado tecnológico</p>
        </div>
        <div class="products-grid">
            <?php
            $new_products = [
                [
                    'product_id' => 5,
                    'product_name' => 'Smartwatch Elite',
                    'product_price' => 2499.00,
                    'product_image' => 'assets/img/placeholder-product.svg',
                    'product_category' => 'Wearables',
                    'product_brand' => 'Royal Tech',
                    'product_installments' => '10x',
                    'product_is_new' => true
                ],
                [
                    'product_id' => 6,
                    'product_name' => 'Tablet Pro 12"',
                    'product_price' => 5999.00,
                    'product_image' => 'assets/img/placeholder-product.svg',
                    'product_category' => 'Tablets',
                    'product_brand' => 'Royal Tech',
                    'product_installments' => '12x',
                    'product_is_new' => true
                ],
                [
                    'product_id' => 7,
                    'product_name' => 'Monitor 4K Premium',
                    'product_price' => 3299.00,
                    'product_image' => 'assets/img/placeholder-product.svg',
                    'product_category' => 'Monitores',
                    'product_brand' => 'Royal Vision',
                    'product_installments' => '10x',
                    'product_is_new' => true
                ],
                [
                    'product_id' => 8,
                    'product_name' => 'Console Gaming Pro',
                    'product_price' => 4499.00,
                    'product_image' => 'assets/img/placeholder-product.svg',
                    'product_category' => 'Games',
                    'product_brand' => 'Royal Play',
                    'product_installments' => '12x',
                    'product_is_new' => true
                ]
            ];
            
            foreach ($new_products as $product) {
                foreach ($product as $key => $value) {
                    $$key = $value;
                }
                include 'components/product-card.php';
            }
            ?>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
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
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <h2>Receba Ofertas Exclusivas</h2>
            <p>Cadastre-se em nossa newsletter e seja o primeiro a saber sobre promoções e lançamentos</p>
        </div>
        <?php if ($newsletterMessage): ?><div style="text-align:center; margin-bottom:15px; color:var(--color-primary);"><?php echo htmlspecialchars($newsletterMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <div style="max-width: 600px; margin: 0 auto; text-align: center;">
            <form method="POST" style="display: flex; gap: 15px;">
                <?php echo csrf_field(); ?>
                <input type="email" name="newsletter_email" placeholder="Seu melhor e-mail..." required class="newsletter-input" style="border-radius:30px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                    Inscrever-se
                </button>
            </form>
        </div>
    </div>
</section>

<?php
// Include footer
include 'components/footer.php';
?>
