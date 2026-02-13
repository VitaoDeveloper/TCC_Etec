<?php
$page_title = 'Relatórios - Royal Tech';
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
                    <a href="banners.php" class="admin-nav-link">
                        <i class="fas fa-images"></i>
                        <span>Banners</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="reports.php" class="admin-nav-link active">
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
                    <h2>Relatórios</h2>
                    <p>Análise detalhada do desempenho da sua loja</p>
                </div>
                <div class="admin-actions">
                    <select style="padding: 10px 20px; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-black-light); color: var(--color-white);">
                        <option>Últimos 7 dias</option>
                        <option>Últimos 30 dias</option>
                        <option>Últimos 90 dias</option>
                        <option>Este ano</option>
                    </select>
                    <button class="btn btn-primary">
                        <i class="fas fa-download"></i>
                        Exportar PDF
                    </button>
                </div>
            </header>
            
            <div class="admin-cards">
                <div class="admin-card">
                    <div class="admin-card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="admin-card-value">R$ 156.890</div>
                    <div class="admin-card-label">Receita Total</div>
                    <div class="admin-card-change positive">
                        <i class="fas fa-arrow-up"></i> 15% vs mês anterior
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="admin-card-value">1.247</div>
                    <div class="admin-card-label">Total de Pedidos</div>
                    <div class="admin-card-change positive">
                        <i class="fas fa-arrow-up"></i> 8% vs mês anterior
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="admin-card-value">R$ 125,90</div>
                    <div class="admin-card-label">Ticket Médio</div>
                    <div class="admin-card-change positive">
                        <i class="fas fa-arrow-up"></i> 5% vs mês anterior
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="admin-card-value">89</div>
                    <div class="admin-card-label">Novos Clientes</div>
                    <div class="admin-card-change negative">
                        <i class="fas fa-arrow-down"></i> 3% vs mês anterior
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <div class="admin-table-container" style="padding: 30px;">
                    <h4 style="margin-bottom: 25px;">Produtos Mais Vendidos</h4>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Vendas</th>
                                <th>Receita</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Notebook Premium Pro</td>
                                <td>148</td>
                                <td>R$ 1.331.852</td>
                            </tr>
                            <tr>
                                <td>Smartphone Ultra X</td>
                                <td>132</td>
                                <td>R$ 659.868</td>
                            </tr>
                            <tr>
                                <td>Fone Premium Wireless</td>
                                <td>98</td>
                                <td>R$ 88.102</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="admin-table-container" style="padding: 30px;">
                    <h4 style="margin-bottom: 25px;">Categorias Populares</h4>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Notebooks</span>
                            <span style="color: var(--color-primary);">35%</span>
                        </div>
                        <div style="height: 8px; background: var(--color-black); border-radius: 4px; overflow: hidden;">
                            <div style="width: 35%; height: 100%; background: var(--color-primary);"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Smartphones</span>
                            <span style="color: var(--color-primary);">28%</span>
                        </div>
                        <div style="height: 8px; background: var(--color-black); border-radius: 4px; overflow: hidden;">
                            <div style="width: 28%; height: 100%; background: var(--color-primary);"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Periféricos</span>
                            <span style="color: var(--color-primary);">18%</span>
                        </div>
                        <div style="height: 8px; background: var(--color-black); border-radius: 4px; overflow: hidden;">
                            <div style="width: 18%; height: 100%; background: var(--color-primary);"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Áudio</span>
                            <span style="color: var(--color-primary);">12%</span>
                        </div>
                        <div style="height: 8px; background: var(--color-black); border-radius: 4px; overflow: hidden;">
                            <div style="width: 12%; height: 100%; background: var(--color-primary);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
