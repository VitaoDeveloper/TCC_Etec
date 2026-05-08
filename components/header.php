<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$basePath = $base_path ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Royal Tech - Loja de Tecnologia Premium'; ?></title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-logged-in="<?php echo $isLoggedIn ? '1' : '0'; ?>" data-base-path="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>">
<header class="main-header">
    <div class="header-top">
        <div class="container">
            <div class="header-top-content">
                <div class="header-contacts">
                    <span><i class="fas fa-phone"></i> (11) 99999-9999</span>
                    <span><i class="fas fa-envelope"></i> contato@royaltech.com.br</span>
                </div>
                <div class="header-social">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
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
                        <input type="text" placeholder="Buscar produtos...">
                        <button><i class="fas fa-search"></i></button>
                    </div>
                    <div class="user-actions">
                        <a href="#" class="action-btn require-auth" data-auth-target="favoritos"><i class="far fa-heart"></i></a>
                        <a href="#" class="action-btn require-auth" data-auth-target="carrinho"><i class="fas fa-shopping-cart"></i></a>
                        <?php if ($isLoggedIn): ?>
                            <a href="<?php echo $basePath; ?>pages/auth/logout.php" class="btn btn-secondary btn-small"><i class="fas fa-sign-out-alt"></i> Sair</a>
                        <?php else: ?>
                            <a href="<?php echo $basePath; ?>pages/auth/login.php" class="btn btn-primary btn-small"><i class="fas fa-user-cog"></i> Login</a>
                            <a href="<?php echo $basePath; ?>pages/auth/register.php" class="btn btn-secondary btn-small"><i class="fas fa-user-cog"></i> Cadastro</a>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="mobile-menu-btn"><i class="fas fa-bars"></i></button>
            </div>
        </div>
    </div>
</header>

<?php if (isset($show_breadcrumb) && $show_breadcrumb): ?>
<section class="breadcrumb-section">
    <div class="container">
        <nav class="breadcrumb">
            <a href="<?php echo $basePath; ?>index.php">Início</a>
            <span>/</span>
            <span><?php echo $breadcrumb_title ?? 'Página Atual'; ?></span>
        </nav>
    </div>
</section>
<?php endif; ?>
