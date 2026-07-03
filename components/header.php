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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-logged-in="<?php echo $isLoggedIn ? '1' : '0'; ?>" data-base-path="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>">
<header class="main-header">
    <div class="header-top">
        <div class="container">
            <div class="header-top-content">
                <div class="header-contacts">
                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($storeSettings['store_phone'] ?? '(11) 99999-9999', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($storeSettings['store_email'] ?? 'contato@royaltech.com.br', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="header-social">
                    <a href="<?php echo htmlspecialchars($socialLinks['facebook'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?php echo htmlspecialchars($socialLinks['instagram'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fab fa-instagram"></i></a>
                    <a href="<?php echo htmlspecialchars($socialLinks['twitter'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fab fa-twitter"></i></a>
                    <a href="<?php echo htmlspecialchars($socialLinks['youtube'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="header-main">
        <div class="container">
            <div class="header-main-content">
                <div class="logo">
                    <a href="<?php echo $basePath; ?>index.php">
                        <span class="logo-icon"><i class="fas fa-crown"></i></span>
                        <span class="logo-text">Royal<span>Tech</span></span>
                    </a>
                </div>

                <nav class="main-nav">
                    <ul class="nav-menu">
                        <li><a href="<?php echo $basePath; ?>index.php" class="<?php echo ($current_page ?? '') === 'inicio' ? 'active' : ''; ?>">Início</a></li>
                        <li><a href="<?php echo $basePath; ?>pages/products/products.php" class="<?php echo ($current_page ?? '') === 'produtos' ? 'active' : ''; ?>">Produtos</a></li>
                        <li><a href="<?php echo $basePath; ?>pages/products/categories.php" class="<?php echo ($current_page ?? '') === 'categorias' ? 'active' : ''; ?>">Categorias</a></li>
                        <li><a href="<?php echo $basePath; ?>pages/products/about.php" class="<?php echo ($current_page ?? '') === 'sobre' ? 'active' : ''; ?>">Sobre</a></li>
                        <li><a href="<?php echo $basePath; ?>pages/products/contact.php" class="<?php echo ($current_page ?? '') === 'contato' ? 'active' : ''; ?>">Contato</a></li>
                    </ul>
                </nav>

                <div class="header-actions">
                    <div class="search-box">
                        <input type="text" placeholder="Buscar produtos..." id="header-search-input">
                        <button id="header-search-btn" aria-label="Buscar produtos"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="user-actions">
                        <a href="<?php echo $basePath; ?>pages/wishlist/wishlist.php" class="action-btn wishlist-btn">
                            <i class="far fa-heart"></i>
                            <?php if ($wishlistCount > 0): ?><span class="cart-badge"><?php echo $wishlistCount; ?></span><?php endif; ?>
                        </a>
                        <a href="<?php echo $basePath; ?>pages/cart/cart.php" class="action-btn cart-btn">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cartCount > 0): ?><span class="cart-badge"><?php echo $cartCount; ?></span><?php endif; ?>
                        </a>
                        <?php if ($isLoggedIn): ?>
                            <a href="<?php echo $basePath; ?>pages/auth/profile.php" class="btn btn-secondary btn-small"><i class="fas fa-user"></i> Perfil</a>
                            <a href="<?php echo $basePath; ?>pages/auth/logout.php" class="btn btn-secondary btn-small"><i class="fas fa-sign-out-alt"></i> Sair</a>
                        <?php else: ?>
                            <a href="<?php echo $basePath; ?>pages/auth/login.php" class="btn btn-primary btn-small"><i class="fas fa-user-cog"></i> Login</a>
                            <a href="<?php echo $basePath; ?>pages/auth/register.php" class="btn btn-secondary btn-small"><i class="fas fa-user-cog"></i> Cadastro</a>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="mobile-menu-btn" aria-label="Abrir menu de navegação"><i class="fas fa-bars"></i></button>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchBtn = document.getElementById('header-search-btn');
    var searchInput = document.getElementById('header-search-input');
    function doSearch() {
        var q = searchInput.value.trim();
        if (q) {
            window.location.href = '<?php echo $basePath; ?>pages/products/products.php?q=' + encodeURIComponent(q);
        }
    }
    if (searchBtn) searchBtn.addEventListener('click', doSearch);
    if (searchInput) searchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') doSearch(); });
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
