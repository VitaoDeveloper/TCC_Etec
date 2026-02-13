<?php
$page_title = 'Gerenciar Clientes - Royal Tech';
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
                    <a href="customers.php" class="admin-nav-link active">
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
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Gerenciar Clientes</h2>
                    <p>Visualize e gerencie todos os clientes cadastrados</p>
                </div>
                <div class="admin-actions">
                    <button class="btn btn-secondary">
                        <i class="fas fa-file-export"></i>
                        Exportar
                    </button>
                </div>
            </header>
            
            <div class="admin-table-container">
                <div class="admin-table-header">
                    <input type="text" placeholder="Buscar clientes..." style="padding: 8px 15px; border: 1px solid var(--color-border); border-radius: 5px; background: var(--color-black); color: var(--color-white); width: 300px;">
                </div>
                
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Pedidos</th>
                            <th>Total Gasto</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; background: var(--color-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--color-black); font-weight: 600;">JS</div>
                                    <div>
                                        <strong>João Silva</strong>
                                        <br><small style="color: var(--color-gray);">Desde Jan/2023</small>
                                    </div>
                                </div>
                            </td>
                            <td>joao.silva@email.com</td>
                            <td>(11) 99999-9999</td>
                            <td>12</td>
                            <td>R$ 15.890,00</td>
                            <td><span class="status-badge status-active">Ativo</span></td>
                            <td>
                                <div class="table-actions">
                                    <button><i class="fas fa-eye"></i></button>
                                    <button><i class="fas fa-envelope"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; background: var(--color-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--color-black); font-weight: 600;">MS</div>
                                    <div>
                                        <strong>Maria Santos</strong>
                                        <br><small style="color: var(--color-gray);">Desde Mar/2023</small>
                                    </div>
                                </div>
                            </td>
                            <td>maria.santos@email.com</td>
                            <td>(11) 88888-8888</td>
                            <td>8</td>
                            <td>R$ 8.450,00</td>
                            <td><span class="status-badge status-active">Ativo</span></td>
                            <td>
                                <div class="table-actions">
                                    <button><i class="fas fa-eye"></i></button>
                                    <button><i class="fas fa-envelope"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; background: var(--color-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--color-black); font-weight: 600;">PC</div>
                                    <div>
                                        <strong>Pedro Costa</strong>
                                        <br><small style="color: var(--color-gray);">Desde Mai/2023</small>
                                    </div>
                                </div>
                            </td>
                            <td>pedro.costa@email.com</td>
                            <td>(11) 77777-7777</td>
                            <td>5</td>
                            <td>R$ 3.200,00</td>
                            <td><span class="status-badge status-inactive">Inativo</span></td>
                            <td>
                                <div class="table-actions">
                                    <button><i class="fas fa-eye"></i></button>
                                    <button><i class="fas fa-envelope"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
