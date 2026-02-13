<?php
$page_title = 'Painel Administrativo - Royal Tech';

// Include header admin (sidebar)
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
        <!-- Sidebar Admin -->
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <a href="index.php">
                    <span class="logo-icon"><i class="fas fa-crown"></i></span>
                    <span class="logo-text">Royal<span>Tech</span></span>
                </a>
            </div>
            
            <nav class="admin-nav">
                <div class="admin-nav-item">
                    <a href="index.php" class="admin-nav-link active">
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
                    <a href="banners.php" class="admin-nav-link">
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
            
            <div style="padding: 20px; margin-top: auto;">
                <a href="login.php" class="btn btn-secondary" style="width: 100%;">
                    <i class="fas fa-sign-out-alt"></i>
                    Sair
                </a>
            </div>
        </aside>
        
        <!-- Conteúdo Principal Admin -->
        <main class="admin-main">
            <!-- Header Admin -->
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Dashboard</h2>
                    <p>Bem-vindo ao painel de administração</p>
                </div>
                <div class="admin-actions">
                    <button class="action-btn"><i class="fas fa-bell"></i></button>
                    <button class="action-btn"><i class="fas fa-envelope"></i></button>
                    <div class="admin-user">
                        <img src="../../assets/img/placeholder-avatar.jpg" alt="Admin">
                        <span>Administrador</span>
                    </div>
                </div>
            </header>
            
            <!-- Cards de Estatísticas -->
            <div class="admin-cards">
                <div class="admin-card">
                    <div class="admin-card-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="admin-card-value">1,247</div>
                    <div class="admin-card-label">Total de Pedidos</div>
                    <div class="admin-card-change positive">
                        <i class="fas fa-arrow-up"></i> 12% este mês
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="admin-card-value">R$ 156.890</div>
                    <div class="admin-card-label">Receita Total</div>
                    <div class="admin-card-change positive">
                        <i class="fas fa-arrow-up"></i> 8% este mês
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="admin-card-value">3,542</div>
                    <div class="admin-card-label">Total de Clientes</div>
                    <div class="admin-card-change positive">
                        <i class="fas fa-arrow-up"></i> 5% este mês
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="admin-card-value">89</div>
                    <div class="admin-card-label">Produtos em Estoque</div>
                    <div class="admin-card-change negative">
                        <i class="fas fa-arrow-down"></i> 3% este mês
                    </div>
                </div>
            </div>
            
            <!-- Grid de Conteúdo -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                
                <!-- Pedidos Recentes -->
                <div class="admin-table-container">
                    <div class="admin-table-header">
                        <h3>Pedidos Recentes</h3>
                        <a href="orders.php" class="btn btn-secondary" style="padding: 8px 20px; font-size: 0.85rem;">
                            Ver Todos
                        </a>
                    </div>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>#ORD-001</td>
                                <td>João Silva</td>
                                <td><span class="status-badge status-pending">Pendente</span></td>
                                <td>R$ 2.499,00</td>
                                <td>
                                    <div class="table-actions">
                                        <button><i class="fas fa-eye"></i></button>
                                        <button><i class="fas fa-edit"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>#ORD-002</td>
                                <td>Maria Santos</td>
                                <td><span class="status-badge status-active">Concluído</span></td>
                                <td>R$ 5.899,00</td>
                                <td>
                                    <div class="table-actions">
                                        <button><i class="fas fa-eye"></i></button>
                                        <button><i class="fas fa-edit"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>#ORD-003</td>
                                <td>Pedro Costa</td>
                                <td><span class="status-badge status-active">Enviado</span></td>
                                <td>R$ 1.299,00</td>
                                <td>
                                    <div class="table-actions">
                                        <button><i class="fas fa-eye"></i></button>
                                        <button><i class="fas fa-edit"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>#ORD-004</td>
                                <td>Ana Oliveira</td>
                                <td><span class="status-badge status-pending">Pendente</span></td>
                                <td>R$ 899,00</td>
                                <td>
                                    <div class="table-actions">
                                        <button><i class="fas fa-eye"></i></button>
                                        <button><i class="fas fa-edit"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>#ORD-005</td>
                                <td>Lucas Mendes</td>
                                <td><span class="status-badge status-active">Concluído</span></td>
                                <td>R$ 3.499,00</td>
                                <td>
                                    <div class="table-actions">
                                        <button><i class="fas fa-eye"></i></button>
                                        <button><i class="fas fa-edit"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Produtos Populares -->
                <div class="admin-table-container">
                    <div class="admin-table-header">
                        <h3>Produtos Populares</h3>
                        <a href="products.php" class="btn btn-secondary" style="padding: 8px 20px; font-size: 0.85rem;">
                            Ver Todos
                        </a>
                    </div>
                    <div style="padding: 20px;">
                        <div class="popular-product" style="display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid var(--color-border);">
                            <img src="../../assets/img/placeholder-product.jpg" alt="Produto" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <div style="flex: 1;">
                                <h5 style="font-size: 0.95rem; margin-bottom: 5px;">Notebook Premium Pro</h5>
                                <span style="color: var(--color-primary); font-weight: 600;">R$ 8.999,00</span>
                            </div>
                            <span class="status-badge status-active">148 vendas</span>
                        </div>
                        <div class="popular-product" style="display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid var(--color-border);">
                            <img src="../../assets/img/placeholder-product.jpg" alt="Produto" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <div style="flex: 1;">
                                <h5 style="font-size: 0.95rem; margin-bottom: 5px;">Smartphone Ultra X</h5>
                                <span style="color: var(--color-primary); font-weight: 600;">R$ 4.999,00</span>
                            </div>
                            <span class="status-badge status-active">132 vendas</span>
                        </div>
                        <div class="popular-product" style="display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid var(--color-border);">
                            <img src="../../assets/img/placeholder-product.jpg" alt="Produto" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <div style="flex: 1;">
                                <h5 style="font-size: 0.95rem; margin-bottom: 5px;">Fone Premium Wireless</h5>
                                <span style="color: var(--color-primary); font-weight: 600;">R$ 899,00</span>
                            </div>
                            <span class="status-badge status-active">98 vendas</span>
                        </div>
                        <div class="popular-product" style="display: flex; align-items: center; gap: 15px; padding: 15px 0;">
                            <img src="../../assets/img/placeholder-product.jpg" alt="Produto" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <div style="flex: 1;">
                                <h5 style="font-size: 0.95rem; margin-bottom: 5px;">Tablet Pro 12"</h5>
                                <span style="color: var(--color-primary); font-weight: 600;">R$ 5.999,00</span>
                            </div>
                            <span class="status-badge status-active">76 vendas</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions Rápidas -->
            <div style="margin-top: 40px;">
                <h3 style="margin-bottom: 20px;">Ações Rápidas</h3>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                    <a href="products.php?action=add" class="quick-action-card" style="background: var(--color-black-light); border: 1px solid var(--color-border); border-radius: 10px; padding: 30px; text-align: center; transition: var(--transition); display: block;">
                        <i class="fas fa-plus-circle" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <h5 style="margin-bottom: 5px;">Novo Produto</h5>
                        <span style="color: var(--color-gray); font-size: 0.85rem;">Adicionar produto</span>
                    </a>
                    <a href="banners.php?action=add" class="quick-action-card" style="background: var(--color-black-light); border: 1px solid var(--color-border); border-radius: 10px; padding: 30px; text-align: center; transition: var(--transition); display: block;">
                        <i class="fas fa-image" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <h5 style="margin-bottom: 5px;">Novo Banner</h5>
                        <span style="color: var(--color-gray); font-size: 0.85rem;">Gerenciar banners</span>
                    </a>
                    <a href="categories.php?action=add" class="quick-action-card" style="background: var(--color-black-light); border: 1px solid var(--color-border); border-radius: 10px; padding: 30px; text-align: center; transition: var(--transition); display: block;">
                        <i class="fas fa-tag" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <h5 style="margin-bottom: 5px;">Nova Categoria</h5>
                        <span style="color: var(--color-gray); font-size: 0.85rem;">Adicionar categoria</span>
                    </a>
                    <a href="reports.php" class="quick-action-card" style="background: var(--color-black-light); border: 1px solid var(--color-border); border-radius: 10px; padding: 30px; text-align: center; transition: var(--transition); display: block;">
                        <i class="fas fa-download" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <h5 style="margin-bottom: 5px;">Exportar Relatório</h5>
                        <span style="color: var(--color-gray); font-size: 0.85rem;">Baixar dados</span>
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
