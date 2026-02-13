<?php
$page_title = 'Gerenciar Categorias - Royal Tech';
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
                    <a href="products.php" class="admin-nav-link">
                        <i class="fas fa-box"></i>
                        <span>Produtos</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="categories.php" class="admin-nav-link active">
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
                    <h2>Gerenciar Categorias</h2>
                    <p>Organize as categorias da sua loja</p>
                </div>
                <div class="admin-actions">
                    <button class="action-btn"><i class="fas fa-bell"></i></button>
                    <button class="btn btn-primary" onclick="document.getElementById('addCategoryModal').classList.add('active')">
                        <i class="fas fa-plus"></i>
                        Nova Categoria
                    </button>
                </div>
            </header>
            
            <!-- Categories Grid -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px;">
                
                <!-- Category Card -->
                <div class="admin-table-container" style="padding: 0; overflow: hidden;">
                    <div style="height: 150px; background: linear-gradient(135deg, var(--color-black) 0%, var(--color-black-light) 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-laptop" style="font-size: 4rem; color: var(--color-primary); opacity: 0.5;"></i>
                    </div>
                    <div style="padding: 25px;">
                        <h4>Notebooks</h4>
                        <p style="color: var(--color-gray); font-size: 0.9rem; margin: 10px 0;">24 produtos</p>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-secondary delete" style="flex: 1; padding: 10px; color: #f44336; border-color: #f44336;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Category Card -->
                <div class="admin-table-container" style="padding: 0; overflow: hidden;">
                    <div style="height: 150px; background: linear-gradient(135deg, var(--color-black) 0%, var(--color-black-light) 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-mobile-alt" style="font-size: 4rem; color: var(--color-primary); opacity: 0.5;"></i>
                    </div>
                    <div style="padding: 25px;">
                        <h4>Smartphones</h4>
                        <p style="color: var(--color-gray); font-size: 0.9rem; margin: 10px 0;">18 produtos</p>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-secondary delete" style="flex: 1; padding: 10px; color: #f44336; border-color: #f44336;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Category Card -->
                <div class="admin-table-container" style="padding: 0; overflow: hidden;">
                    <div style="height: 150px; background: linear-gradient(135deg, var(--color-black) 0%, var(--color-black-light) 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-tablet-alt" style="font-size: 4rem; color: var(--color-primary); opacity: 0.5;"></i>
                    </div>
                    <div style="padding: 25px;">
                        <h4>Tablets</h4>
                        <p style="color: var(--color-gray); font-size: 0.9rem; margin: 10px 0;">12 produtos</p>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-secondary delete" style="flex: 1; padding: 10px; color: #f44336; border-color: #f44336;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Category Card -->
                <div class="admin-table-container" style="padding: 0; overflow: hidden;">
                    <div style="height: 150px; background: linear-gradient(135deg, var(--color-black) 0%, var(--color-black-light) 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-keyboard" style="font-size: 4rem; color: var(--color-primary); opacity: 0.5;"></i>
                    </div>
                    <div style="padding: 25px;">
                        <h4>Periféricos</h4>
                        <p style="color: var(--color-gray); font-size: 0.9rem; margin: 10px 0;">36 produtos</p>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-secondary delete" style="flex: 1; padding: 10px; color: #f44336; border-color: #f44336;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Category Card -->
                <div class="admin-table-container" style="padding: 0; overflow: hidden;">
                    <div style="height: 150px; background: linear-gradient(135deg, var(--color-black) 0%, var(--color-black-light) 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-headphones" style="font-size: 4rem; color: var(--color-primary); opacity: 0.5;"></i>
                    </div>
                    <div style="padding: 25px;">
                        <h4>Áudio</h4>
                        <p style="color: var(--color-gray); font-size: 0.9rem; margin: 10px 0;">28 produtos</p>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-secondary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-secondary delete" style="flex: 1; padding: 10px; color: #f44336; border-color: #f44336;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Add Category Card -->
                <div class="admin-table-container" style="padding: 0; overflow: hidden; border: 2px dashed var(--color-border); background: transparent; cursor: pointer;">
                    <div style="height: 150px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <i class="fas fa-plus-circle" style="font-size: 3rem; color: var(--color-primary); margin-bottom: 10px;"></i>
                        <span style="color: var(--color-gray);">Adicionar Categoria</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Category Modal -->
    <div id="addCategoryModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: var(--color-black-light); border: 1px solid var(--color-border); border-radius: 15px; padding: 40px; width: 100%; max-width: 500px;">
            <h3 style="margin-bottom: 30px;">Nova Categoria</h3>
            <div class="admin-form-group">
                <label>Nome da Categoria</label>
                <input type="text" placeholder="Ex: Notebooks">
            </div>
            <div class="admin-form-group">
                <label>Ícone</label>
                <select>
                    <option value="laptop"><i class="fas fa-laptop"></i> Laptop</option>
                    <option value="mobile"><i class="fas fa-mobile-alt"></i> Smartphone</option>
                    <option value="tablet"><i class="fas fa-tablet-alt"></i> Tablet</option>
                    <option value="headphones"><i class="fas fa-headphones"></i> Áudio</option>
                    <option value="keyboard"><i class="fas fa-keyboard"></i> Periféricos</option>
                    <option value="gamepad"><i class="fas fa-gamepad"></i> Games</option>
                </select>
            </div>
            <div class="admin-form-group">
                <label>Descrição</label>
                <textarea rows="3" placeholder="Descrição da categoria..."></textarea>
            </div>
            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <button class="btn btn-primary" style="flex: 1;">Salvar</button>
                <button class="btn btn-secondary" style="flex: 1;" onclick="document.getElementById('addCategoryModal').classList.remove('active')">Cancelar</button>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
