<?php
$page_title = 'Gerenciar Banners - Royal Tech';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <a href="index.php">
                    <span class="logo-icon"><i class="fas fa-crown"></i></span>
                    <span class="logo-text">Royal<span>Tech</span></span>
                </a>
            </div>
            
            <nav class="admin-nav">
                <div class="admin-nav-item">
                    <a href="index.php" class="admin-nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="products.php" class="admin-nav-link">
                        <i class="fas fa-box"></i>
                        <span>Produtos</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="categories.php" class="admin-nav-link">
                        <i class="fas fa-tags"></i>
                        <span>Categorias</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="orders.php" class="admin-nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Pedidos</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="customers.php" class="admin-nav-link">
                        <i class="fas fa-users"></i>
                        <span>Clientes</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="banners.php" class="admin-nav-link active">
                        <i class="fas fa-images"></i>
                        <span>Banners</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="reports.php" class="admin-nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Relatórios</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="settings.php" class="admin-nav-link">
                        <i class="fas fa-cogs"></i>
                        <span>Configurações</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Gerenciar Banners</h2>
                    <p>Configure os banners da página inicial</p>
                </div>
                <div class="admin-actions">
                    <button class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Novo Banner
                    </button>
                </div>
            </header>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px;">
                <div class="admin-table-container" style="overflow: hidden;">
                    <div style="height: 200px; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); display: flex; align-items: center; justify-content: center; flex-direction: column;">
                        <i class="fas fa-image" style="font-size: 3rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <span style="color: var(--color-gray);">Banner Principal</span>
                    </div>
                    <div style="padding: 20px;">
                        <h4>Hero Banner</h4>
                        <p style="color: var(--color-gray); font-size: 0.85rem; margin: 10px 0;">Dimensões: 1920x600px</p>
                        <span class="status-badge status-active">Ativo</span>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button class="btn btn-secondary" style="flex: 1; padding: 8px;"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-secondary delete" style="flex: 1; padding: 8px; color: #f44336;"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
                
                <div class="admin-table-container" style="overflow: hidden;">
                    <div style="height: 200px; background: linear-gradient(135deg, #d4af37 0%, #b8962e 100%); display: flex; align-items: center; justify-content: center; flex-direction: column;">
                        <i class="fas fa-gift" style="font-size: 3rem; color: #1a1a1a; margin-bottom: 15px;"></i>
                        <span style="color: #1a1a1a;">Banner Promocional</span>
                    </div>
                    <div style="padding: 20px;">
                        <h4>Promoção Especial</h4>
                        <p style="color: var(--color-gray); font-size: 0.85rem; margin: 10px 0;">Dimensões: 600x400px</p>
                        <span class="status-badge status-active">Ativo</span>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button class="btn btn-secondary" style="flex: 1; padding: 8px;"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-secondary delete" style="flex: 1; padding: 8px; color: #f44336;"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
                
                <div class="admin-table-container" style="overflow: hidden; border: 2px dashed var(--color-border); background: transparent;">
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                        <i class="fas fa-plus-circle" style="font-size: 3rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <span style="color: var(--color-gray);">Adicionar Banner</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
