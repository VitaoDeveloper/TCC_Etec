<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$basePath = $base_path ?? '';

$settingsPath = dirname(__DIR__) . '/database/settings.json';
$storeSettings = (file_exists($settingsPath)) ? json_decode(file_get_contents($settingsPath), true) : [];
$socialLinks = [
    'facebook' => $storeSettings['social_facebook'] ?? '#',
    'instagram' => $storeSettings['social_instagram'] ?? '#',
    'twitter' => $storeSettings['social_twitter'] ?? '#',
    'youtube' => $storeSettings['social_youtube'] ?? '#',
];

$cartCount = 0;
$wishlistCount = 0;
if ($isLoggedIn) {
    if (!isset($pdo)) {
        $connPath = dirname(__DIR__) . '/database/connection.php';
        if (file_exists($connPath)) {
            include $connPath;
        }
    }
    if (isset($pdo)) {
        require_once dirname(__DIR__) . '/includes/cart_functions.php';
        $cartCount = cartGetCount($pdo, (int)$_SESSION['user_id']);
        require_once dirname(__DIR__) . '/includes/wishlist_functions.php';
        $wishlistCount = wishlistCount($pdo, (int)$_SESSION['user_id']);
    }
}

if (!isset($pdo)) {
    $connPath = dirname(__DIR__) . '/database/connection.php';
    if (file_exists($connPath)) {
        include $connPath;
    }
}

$menuCategories = [];
if (isset($pdo)) {
    try {
        $menuCategories = $pdo->query(
            'SELECT c.id, c.name, c.slug, c.description, 
             (SELECT COUNT(*) FROM e5_products p WHERE p.category_id = c.id) AS product_count 
             FROM e5_categories c ORDER BY c.name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $menuCategories = [];
    }
}

$categoryIcons = [
    'notebooks' => 'fa-laptop', 'smartphones' => 'fa-mobile-alt', 'tablets' => 'fa-tablet-alt',
    'perifericos' => 'fa-keyboard', 'audio' => 'fa-headphones', 'games' => 'fa-gamepad',
    'cameras' => 'fa-camera', 'acessorios' => 'fa-headset', 'monitores' => 'fa-tv',
    'wearables' => 'fa-clock', 'rede' => 'fa-wifi', 'cabo' => 'fa-plug',
    'componentes' => 'fa-microchip',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Royal Tech - Loja de Tecnologia Premium'; ?></title>
    <meta name="description" content="<?php echo $page_description ?? 'Royal Tech - Loja de Tecnologia Premium. Os melhores produtos de tecnologia com preços imperdíveis e atendimento diferenciado.'; ?>">
    <meta property="og:title" content="<?php echo $og_title ?? $page_title ?? 'Royal Tech - Loja de Tecnologia Premium'; ?>">
    <meta property="og:description" content="<?php echo $og_description ?? ($page_description ?? 'Royal Tech - Loja de Tecnologia Premium. Os melhores produtos de tecnologia com preços imperdíveis e atendimento diferenciado.'); ?>">
    <meta property="og:image" content="<?php echo ($basePath ?? '') . 'assets/img/hero-bg.jpg'; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Royal Tech">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/mercadolivre-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="ml-layout-active" data-logged-in="<?php echo $isLoggedIn ? '1' : '0'; ?>" data-base-path="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>">

<!-- ML Header -->
<header class="ml-header">
    <div class="ml-header-main">
        <!-- Mobile Menu Button -->
        <button class="ml-mobile-menu-btn" aria-label="Abrir menu">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Logo -->
        <div class="ml-logo">
            <a href="<?php echo $basePath; ?>index.php">
                <span class="ml-logo-icon"><i class="fas fa-crown"></i></span>
                <span class="ml-logo-text">Royal<span>Tech</span></span>
            </a>
        </div>

        <!-- Search -->
        <div class="ml-search">
            <form class="ml-search-form" onsubmit="return false;">
                <input type="text" class="ml-search-input" placeholder="Buscar produtos, marcas e mais..." id="ml-search-input" autocomplete="off">
                <button type="button" class="ml-search-btn" id="ml-search-btn" aria-label="Buscar">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <!-- Actions -->
        <div class="ml-header-actions">
            <!-- Wishlist -->
            <a href="<?php echo $basePath; ?>pages/wishlist/wishlist.php" class="ml-header-action ml-wishlist-link" title="Favoritos">
                <i class="far fa-heart"></i>
                <?php if ($wishlistCount > 0): ?><span class="ml-badge"><?php echo $wishlistCount; ?></span><?php endif; ?>
                <span class="ml-header-action-text">Favoritos</span>
            </a>

            <!-- Cart -->
            <a href="<?php echo $basePath; ?>pages/cart/cart.php" class="ml-header-action ml-cart-link" title="Carrinho">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cartCount > 0): ?><span class="ml-badge"><?php echo $cartCount; ?></span><?php endif; ?>
                <span class="ml-header-action-text">Carrinho</span>
            </a>

            <!-- Auth -->
            <?php if ($isLoggedIn): ?>
                <a href="<?php echo $basePath; ?>pages/auth/profile.php" class="ml-header-action" title="Minha Conta">
                    <i class="far fa-user"></i>
                    <span class="ml-header-action-text">Minha Conta</span>
                </a>
                <a href="<?php echo $basePath; ?>pages/auth/logout.php" class="ml-header-action" title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="ml-header-action-text">Sair</span>
                </a>
            <?php else: ?>
                <div class="ml-auth-btns">
                    <a href="<?php echo $basePath; ?>pages/auth/login.php" class="ml-auth-btn ml-auth-btn-primary">Entrar</a>
                    <a href="<?php echo $basePath; ?>pages/auth/register.php" class="ml-auth-btn ml-auth-btn-secondary">Cadastrar</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Category Bar fora do header: elementos fixos como irmãos na raiz = empilhamento previsível -->
<nav class="ml-category-bar" id="ml-category-bar">
        <div class="ml-category-bar-inner">
            <!-- Categories Dropdown Trigger -->
            <div class="ml-cat-trigger" id="ml-cat-trigger">
                <i class="fas fa-bars"></i>
                <span>Categorias</span>
                <i class="fas fa-chevron-down"></i>

                <!-- Mega Menu -->
                <div class="ml-mega-menu" id="ml-mega-menu">
                    <?php if (!empty($menuCategories)): ?>
                        <?php foreach ($menuCategories as $cat): ?>
                            <?php $icon = $categoryIcons[$cat['slug']] ?? 'fa-folder'; ?>
                            <a href="<?php echo $basePath; ?>pages/products/products.php?category_id=<?php echo (int) $cat['id']; ?>">
                                <span class="ml-cat-item">
                                    <span class="ml-cat-icon"><i class="fas <?php echo $icon; ?>"></i></span>
                                    <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="ml-cat-count"><?php echo (int) $cat['product_count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick category links (scrollable wrapper — keeps mega menu outside any overflow context) -->
            <div class="ml-cat-links-scroll">
                <a href="<?php echo $basePath; ?>pages/products/products.php" class="ml-cat-link">Todos</a>
                <a href="<?php echo $basePath; ?>pages/products/products.php?sort=newest" class="ml-cat-link">Novidades</a>
                <a href="<?php echo $basePath; ?>pages/products/products.php?offers=1" class="ml-cat-link">Ofertas</a>
                <a href="<?php echo $basePath; ?>pages/products/contact.php" class="ml-cat-link">Contato</a>
                <a href="<?php echo $basePath; ?>pages/products/about.php" class="ml-cat-link">Sobre</a>
            </div>
        </div>
</nav>

<!-- Mobile Sidebar Overlay -->
<div class="ml-sidebar-overlay" id="ml-sidebar-overlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchBtn = document.getElementById('ml-search-btn');
    var searchInput = document.getElementById('ml-search-input');
    function doSearch() {
        var q = searchInput.value.trim();
        if (q) {
            window.location.href = '<?php echo $basePath; ?>pages/products/products.php?q=' + encodeURIComponent(q);
        }
    }
    if (searchBtn) searchBtn.addEventListener('click', doSearch);
    if (searchInput) searchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') doSearch(); });

    // Mega menu toggle for touch
    var catTrigger = document.getElementById('ml-cat-trigger');
    var megaMenu = document.getElementById('ml-mega-menu');
    if (catTrigger && megaMenu) {
        catTrigger.addEventListener('click', function(e) {
            if (window.innerWidth <= 992) {
                e.stopPropagation();
                megaMenu.classList.toggle('show');
                this.classList.toggle('active');
            }
        });
        document.addEventListener('click', function(e) {
            if (!catTrigger.contains(e.target)) {
                megaMenu.classList.remove('show');
                catTrigger.classList.remove('active');
            }
        });
    }

    // Mobile category drawer
    var mobileMenuBtn = document.querySelector('.ml-mobile-menu-btn');
    var categoryBar = document.getElementById('ml-category-bar');
    var sidebarOverlay = document.getElementById('ml-sidebar-overlay');
    if (mobileMenuBtn && categoryBar && sidebarOverlay) {
        mobileMenuBtn.addEventListener('click', function() {
            categoryBar.classList.toggle('mobile-active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = categoryBar.classList.contains('mobile-active') ? 'hidden' : '';
        });
        sidebarOverlay.addEventListener('click', function() {
            categoryBar.classList.remove('mobile-active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});
</script>

<?php if ($show_breadcrumb ?? true): ?>
<section class="breadcrumb-section">
    <div class="container">
        <nav class="breadcrumb">
            <a href="<?php echo $basePath; ?>index.php">Início</a>
            <?php if (!empty($breadcrumb_items)): foreach ($breadcrumb_items as $bi): ?>
            <span>/</span>
            <?php if (!empty($bi['url'])): ?><a href="<?php echo htmlspecialchars($bi['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($bi['label'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?><span><?php echo htmlspecialchars($bi['label'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
            <?php endforeach; ?>
            <?php else: ?>
            <span>/</span>
            <span><?php echo $breadcrumb_title ?? 'Página Atual'; ?></span>
            <?php endif; ?>
        </nav>
    </div>
</section>
<?php endif; ?>
