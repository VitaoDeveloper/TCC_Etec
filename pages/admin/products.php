<?php
$page_title = 'Gerenciar Produtos - Royal Tech';
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
                    <a href="index.php" class="admin-nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="products.php" class="admin-nav-link active">
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
        </aside>
        
        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Gerenciar Produtos</h2>
                    <p>Adicione, edite ou remova produtos da sua loja</p>
                </div>
                <div class="admin-actions">
                    <button class="action-btn"><i class="fas fa-bell"></i></button>
                    <a href="product-form.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Novo Produto
                    </a>
                </div>
            </header>
            
            <!-- Filters -->
            <div class="admin-filters" style="display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap;">
                <input type="text" placeholder="Buscar produtos..." style="
                    padding: 12px 20px;
                    border: 1px solid var(--color-border);
                    border-radius: 8px;
                    background: var(--color-black-light);
                    color: var(--color-white);
                    width: 300px;
                ">
                <select style="
                    padding: 12px 20px;
                    border: 1px solid var(--color-border);
                    border-radius: 8px;
                    background: var(--color-black-light);
                    color: var(--color-white);
                ">
                    <option>Todas as Categorias</option>
                    <option>Notebooks</option>
                    <option>Smartphones</option>
                    <option>Tablets</option>
                    <option>Periféricos</option>
                </select>
                <select style="
                    padding: 12px 20px;
                    border: 1px solid var(--color-border);
                    border-radius: 8px;
                    background: var(--color-black-light);
                    color: var(--color-white);
                ">
                    <option>Todos os Status</option>
                    <option>Ativo</option>
                    <option>Inativo</option>
                    <option>Em Promoção</option>
                </select>
                <button class="btn btn-secondary">
                    <i class="fas fa-filter"></i>
                    Filtrar
                </button>
            </div>
            
            <!-- Products Table -->
            <div class="admin-table-container">
                <div class="admin-table-header">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <input type="checkbox" class="table-check-all">
                        <span>Marcar Todos</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-secondary" style="padding: 8px 15px; font-size: 0.85rem;">
                            <i class="fas fa-trash"></i>
                            Excluir Selecionados
                        </button>
                        <button class="btn btn-secondary" style="padding: 8px 15px; font-size: 0.85rem;">
                            <i class="fas fa-file-export"></i>
                            Exportar
                        </button>
                    </div>
                </div>
                
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="table-check-all"></th>
                            <th>Imagem</th>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="checkbox" class="table-checkbox"></td>
                            <td>
                                <img src="../../assets/img/placeholder-product.jpg" alt="Produto" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td>
                                <strong>Notebook Premium Pro</strong>
                                <br><small style="color: var(--color-gray);">SKU: NBP-001</small>
                            </td>
                            <td>Notebooks</td>
                            <td><span style="color: var(--color-primary); font-weight: 600;">R$ 8.999,00</span></td>
                            <td>
                                <span style="color: #4caf50;">15 em estoque</span>
                            </td>
                            <td><span class="status-badge status-active">Ativo</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="product-form.php?id=1"><i class="fas fa-edit"></i></a>
                                    <a href="#"><i class="fas fa-eye"></i></a>
                                    <button class="delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><input type="checkbox" class="table-checkbox"></td>
                            <td>
                                <img src="../../assets/img/placeholder-product.jpg" alt="Produto" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td>
                                <strong>Smartphone Ultra X</strong>
                                <br><small style="color: var(--color-gray);">SKU: SPX-002</small>
                            </td>
                            <td>Smartphones</td>
                            <td><span style="color: var(--color-primary); font-weight: 600;">R$ 4.999,00</span></td>
                            <td>
                                <span style="color: #ffc107;">3 em estoque</span>
                            </td>
                            <td><span class="status-badge status-active">Ativo</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="product-form.php?id=2"><i class="fas fa-edit"></i></a>
                                    <a href="#"><i class="fas fa-eye"></i></a>
                                    <button class="delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><input type="checkbox" class="table-checkbox"></td>
                            <td>
                                <img src="../../assets/img/placeholder-product.jpg" alt="Produto" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td>
                                <strong>Fone Premium Wireless</strong>
                                <br><small style="color: var(--color-gray);">SKU: FPW-003</small>
                            </td>
                            <td>Áudio</td>
                            <td>
                                <span style="text-decoration: line-through; color: var(--color-gray); margin-right: 10px;">R$ 999,00</span>
                                <span style="color: var(--color-primary); font-weight: 600;">R$ 899,00</span>
                            </td>
                            <td>
                                <span style="color: #4caf50;">42 em estoque</span>
                            </td>
                            <td><span class="status-badge status-active">Em Promoção</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="product-form.php?id=3"><i class="fas fa-edit"></i></a>
                                    <a href="#"><i class="fas fa-eye"></i></a>
                                    <button class="delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><input type="checkbox" class="table-checkbox"></td>
                            <td>
                                <img src="../../assets/img/placeholder-product.jpg" alt="Produto" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td>
                                <strong>Teclado Mecânico RGB</strong>
                                <br><small style="color: var(--color-gray);">SKU: TMR-004</small>
                            </td>
                            <td>Periféricos</td>
                            <td><span style="color: var(--color-primary); font-weight: 600;">R$ 449,00</span></td>
                            <td>
                                <span style="color: #f44336;">0 em estoque</span>
                            </td>
                            <td><span class="status-badge status-inactive">Inativo</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="product-form.php?id=4"><i class="fas fa-edit"></i></a>
                                    <a href="#"><i class="fas fa-eye"></i></a>
                                    <button class="delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div style="padding: 20px; border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--color-gray);">Mostrando 1-4 de 48 produtos</span>
                    <div class="pagination" style="margin: 0;">
                        <a href="#" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                        <a href="#" class="page-btn active">1</a>
                        <a href="#" class="page-btn">2</a>
                        <a href="#" class="page-btn">3</a>
                        <a href="#" class="page-btn">4</a>
                        <a href="#" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
