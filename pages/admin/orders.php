<?php
$page_title = 'Gerenciar Pedidos - Royal Tech';
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
                    <a href="orders.php" class="admin-nav-link active">
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
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Gerenciar Pedidos</h2>
                    <p>Acompanhe e gerencie todos os pedidos da sua loja</p>
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
                    <h3>Todos os Pedidos</h3>
                    <select style="padding: 8px 15px; border: 1px solid var(--color-border); border-radius: 5px; background: var(--color-black); color: var(--color-white);">
                        <option>Todos os Status</option>
                        <option>Pendente</option>
                        <option>Processando</option>
                        <option>Enviado</option>
                        <option>Concluído</option>
                        <option>Cancelado</option>
                    </select>
                </div>
                
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>#ORD-001</td>
                            <td>João Silva</td>
                            <td>13/02/2024</td>
                            <td>3 itens</td>
                            <td>R$ 2.499,00</td>
                            <td><span class="status-badge status-pending">Pendente</span></td>
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
                            <td>12/02/2024</td>
                            <td>1 item</td>
                            <td>R$ 5.899,00</td>
                            <td><span class="status-badge status-processing">Processando</span></td>
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
                            <td>12/02/2024</td>
                            <td>2 itens</td>
                            <td>R$ 1.299,00</td>
                            <td><span class="status-badge status-active">Enviado</span></td>
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
                            <td>11/02/2024</td>
                            <td>5 itens</td>
                            <td>R$ 899,00</td>
                            <td><span class="status-badge status-active">Concluído</span></td>
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
        </main>
    </div>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
