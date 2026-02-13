<?php
$page_title = 'Novo Produto - Royal Tech';
$is_edit = isset($_GET['id']);
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
                    <h2><?php echo $is_edit ? 'Editar Produto' : 'Novo Produto'; ?></h2>
                    <p><?php echo $is_edit ? 'Atualize as informações do produto' : 'Preencha as informações para adicionar um novo produto'; ?></p>
                </div>
                <div class="admin-actions">
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                </div>
            </header>
            
            <!-- Product Form -->
            <form class="product-form" style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                
                <!-- Main Content -->
                <div class="form-main">
                    <div class="admin-table-container" style="padding: 30px;">
                        <h4 style="margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--color-border);">
                            Informações Básicas
                        </h4>
                        
                        <div class="admin-form-group">
                            <label for="name">Nome do Produto *</label>
                            <input type="text" id="name" name="name" placeholder="Ex: Notebook Premium Pro" required>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="sku">SKU *</label>
                            <input type="text" id="sku" name="sku" placeholder="Ex: NBP-001" required>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="description">Descrição</label>
                            <textarea id="description" name="description" rows="5" placeholder="Descreva o produto detalhadamente..."></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group">
                                <label for="price">Preço *</label>
                                <input type="number" id="price" name="price" step="0.01" placeholder="0,00" required>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="old_price">Preço Antigo</label>
                                <input type="number" id="old_price" name="old_price" step="0.01" placeholder="0,00">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group">
                                <label for="category">Categoria *</label>
                                <select id="category" name="category" required>
                                    <option value="">Selecione...</option>
                                    <option value="notebooks">Notebooks</option>
                                    <option value="smartphones">Smartphones</option>
                                    <option value="tablets">Tablets</option>
                                    <option value="perifericos">Periféricos</option>
                                    <option value="audio">Áudio</option>
                                    <option value="games">Games</option>
                                </select>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="brand">Marca</label>
                                <input type="text" id="brand" name="brand" placeholder="Ex: Royal Tech">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="stock">Estoque</label>
                                <input type="number" id="stock" name="stock" placeholder="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Imagens -->
                    <div class="admin-table-container" style="padding: 30px; margin-top: 30px;">
                        <h4 style="margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--color-border);">
                            Imagens do Produto
                        </h4>
                        
                        <div class="admin-file-upload" style="margin-bottom: 20px;">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h5>Imagem Principal</h5>
                            <p style="color: var(--color-gray); margin: 10px 0;">Clique ou arraste um arquivo aqui</p>
                            <small style="color: var(--color-gray);">JPG, PNG - Máx 2MB</small>
                            <input type="file" name="main_image" style="display: none;">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                            <div class="admin-file-upload" style="padding: 20px;">
                                <i class="fas fa-plus"></i>
                                <small>Galeria 1</small>
                                <input type="file" name="gallery[]" style="display: none;">
                            </div>
                            <div class="admin-file-upload" style="padding: 20px;">
                                <i class="fas fa-plus"></i>
                                <small>Galeria 2</small>
                                <input type="file" name="gallery[]" style="display: none;">
                            </div>
                            <div class="admin-file-upload" style="padding: 20px;">
                                <i class="fas fa-plus"></i>
                                <small>Galeria 3</small>
                                <input type="file" name="gallery[]" style="display: none;">
                            </div>
                            <div class="admin-file-upload" style="padding: 20px;">
                                <i class="fas fa-plus"></i>
                                <small>Galeria 4</small>
                                <input type="file" name="gallery[]" style="display: none;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="form-sidebar">
                    <!-- Status -->
                    <div class="admin-table-container" style="padding: 25px;">
                        <h4 style="margin-bottom: 20px;">Status</h4>
                        
                        <div class="admin-form-group">
                            <select name="status">
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                        </div>
                        
                        <div class="admin-form-group">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="featured">
                                <span>Produto em Destaque</span>
                            </label>
                        </div>
                        
                        <div class="admin-form-group">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="new">
                                <span>Marcar como Novo</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Parcelamento -->
                    <div class="admin-table-container" style="padding: 25px; margin-top: 20px;">
                        <h4 style="margin-bottom: 20px;">Parcelamento</h4>
                        
                        <div class="admin-form-group">
                            <label for="installments">Parcelas</label>
                            <select id="installments" name="installments">
                                <option value="1">À vista</option>
                                <option value="2">2x sem juros</option>
                                <option value="3">3x sem juros</option>
                                <option value="6" selected>6x sem juros</option>
                                <option value="10">10x sem juros</option>
                                <option value="12">12x sem juros</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                            <i class="fas fa-save"></i>
                            Salvar Produto
                        </button>
                        <button type="button" class="btn btn-secondary" style="width: 100%;">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
