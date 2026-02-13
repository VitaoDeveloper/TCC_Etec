<?php
$page_title = 'Configurações - Royal Tech';
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
                    <a href="reports.php" class="admin-nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Relatórios</span>
                    </a>
                </div>
                <div class="admin-nav-item">
                    <a href="settings.php" class="admin-nav-link active">
                        <i class="fas fa-cogs"></i>
                        <span>Configurações</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Configurações</h2>
                    <p>Gerencie as configurações da sua loja</p>
                </div>
                <div class="admin-actions">
                    <button class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                </div>
            </header>
            
            <div style="display: grid; grid-template-columns: 250px 1fr; gap: 30px;">
                <aside class="settings-sidebar">
                    <nav class="settings-nav">
                        <a href="#" class="settings-link active">
                            <i class="fas fa-store"></i>
                            Loja
                        </a>
                        <a href="#" class="settings-link">
                            <i class="fas fa-envelope"></i>
                            E-mails
                        </a>
                        <a href="#" class="settings-link">
                            <i class="fas fa-credit-card"></i>
                            Pagamentos
                        </a>
                        <a href="#" class="settings-link">
                            <i class="fas fa-truck"></i>
                            Frete
                        </a>
                        <a href="#" class="settings-link">
                            <i class="fas fa-shield-alt"></i>
                            Segurança
                        </a>
                        <a href="#" class="settings-link">
                            <i class="fas fa-users-cog"></i>
                            Usuários
                        </a>
                    </nav>
                </aside>
                
                <div class="settings-content">
                    <div class="admin-table-container" style="padding: 30px;">
                        <h4 style="margin-bottom: 25px;">Informações da Loja</h4>
                        
                        <div class="admin-form-group">
                            <label for="store_name">Nome da Loja</label>
                            <input type="text" id="store_name" value="Royal Tech" placeholder="Nome da loja">
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="store_email">E-mail de Contato</label>
                            <input type="email" id="store_email" value="contato@royaltech.com.br" placeholder="email@loja.com.br">
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="store_phone">Telefone</label>
                            <input type="tel" id="store_phone" value="(11) 99999-9999" placeholder="(00) 00000-0000">
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="store_address">Endereço</label>
                            <textarea id="store_address" rows="2" placeholder="Endereço completo">Av. Paulista, 1000 - São Paulo, SP</textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group">
                                <label for="store_cnpj">CNPJ</label>
                                <input type="text" id="store_cnpj" value="00.000.000/0001-00" placeholder="00.000.000/0001-00">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="store_currency">Moeda</label>
                                <select id="store_currency">
                                    <option value="BRL" selected>Real (R$)</option>
                                    <option value="USD">Dólar ($)</option>
                                    <option value="EUR">Euro (€)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="store_description">Descrição da Loja</label>
                            <textarea id="store_description" rows="4" placeholder="Breve descrição da loja">Sua loja de tecnologia premium com os melhores produtos e atendimento diferenciado.</textarea>
                        </div>
                        
                        <hr style="border: none; border-top: 1px solid var(--color-border); margin: 30px 0;">
                        
                        <h4 style="margin-bottom: 25px;">Logo e Favicon</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="admin-file-upload">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h5>Logo da Loja</h5>
                                <p style="color: var(--color-gray);">PNG ou JPG - 200x60px</p>
                            </div>
                            
                            <div class="admin-file-upload">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h5>Favicon</h5>
                                <p style="color: var(--color-gray);">PNG ou ICO - 32x32px</p>
                            </div>
                        </div>
                        
                        <hr style="border: none; border-top: 1px solid var(--color-border); margin: 30px 0;">
                        
                        <h4 style="margin-bottom: 25px;">Redes Sociais</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group">
                                <label for="social_facebook">Facebook</label>
                                <input type="url" id="social_facebook" placeholder="https://facebook.com/">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="social_instagram">Instagram</label>
                                <input type="url" id="social_instagram" placeholder="https://instagram.com/">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="social_twitter">Twitter</label>
                                <input type="url" id="social_twitter" placeholder="https://twitter.com/">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="social_youtube">YouTube</label>
                                <input type="url" id="social_youtube" placeholder="https://youtube.com/">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <style>
    .settings-sidebar {
        background: var(--color-black-light);
        border: 1px solid var(--color-border);
        border-radius: 10px;
        padding: 20px;
        height: fit-content;
        position: sticky;
        top: 100px;
    }
    
    .settings-nav {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .settings-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 20px;
        color: var(--color-gray-light);
        border-radius: 8px;
        transition: var(--transition);
    }
    
    .settings-link:hover,
    .settings-link.active {
        background: rgba(212, 175, 55, 0.1);
        color: var(--color-primary);
    }
    
    .settings-content .admin-file-upload {
        text-align: center;
        cursor: pointer;
    }
    </style>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
