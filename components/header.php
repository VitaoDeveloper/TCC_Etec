<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Royal Tech - Loja de Tecnologia Premium'; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header Principal -->
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
                        <a href="index.php">
                            <span class="logo-icon"><i class="fas fa-crown"></i></span>
                            <span class="logo-text">Royal<span>Tech</span></span>
                        </a>
                    </div>
                    
                    <nav class="main-nav">
                        <ul class="nav-menu">
                            <li><a href="index.php" class="active">Início</a></li>
                            <li><a href="pages/products/products.php">Produtos</a></li>
                            <li><a href="pages/products/categories.php">Categorias</a></li>
                            <li><a href="pages/about.php">Sobre</a></li>
                            <li><a href="pages/contact.php">Contato</a></li>
                        </ul>
                    </nav>
                    
                    <div class="header-actions">
                        <div class="search-box">
                            <input type="text" placeholder="Buscar produtos...">
                            <button><i class="fas fa-search"></i></button>
                        </div>
                        <div class="user-actions">
                            <a href="#" class="action-btn"><i class="far fa-heart"></i></a>
                            <a href="#" class="action-btn"><i class="fas fa-shopping-cart"></i></a>
                            <a href="pages/Entrada/login.php" class="action-btn admin-link"><i class="fas fa-user-cog"></i></a>
                        </div>
                    </div>
                    
                    <button class="mobile-menu-btn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <?php if (isset($show_breadcrumb) && $show_breadcrumb): ?>
    <section class="breadcrumb-section">
        <div class="container">
            <nav class="breadcrumb">
                <a href="index.php">Início</a>
                <span>/</span>
                <span><?php echo $breadcrumb_title ?? 'Página Atual'; ?></span>
            </nav>
        </div>
    </section>
    <?php endif; ?>
