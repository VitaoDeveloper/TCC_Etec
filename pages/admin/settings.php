<?php
$page_title = 'Configurações - Royal Tech';
include 'auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';

$settingsFile = __DIR__ . '/../../database/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página.');
    }
    $keys = ['store_name','store_email','store_phone','store_address','store_cnpj','store_currency','store_description','social_facebook','social_instagram','social_twitter','social_youtube'];
    foreach ($keys as $k) {
        $settings[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $_SESSION['admin_message'] = 'Configurações salvas com sucesso.';
    header('Location: settings.php');
    exit;
}

$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);
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
            <div class="admin-logo"><a href="index.php"><span class="logo-icon"><i class="fas fa-crown"></i></span><span class="logo-text">Royal<span>Tech</span></span></a></div>
            <nav class="admin-nav">
                <div class="admin-nav-item"><a href="index.php" class="admin-nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></div>
                <div class="admin-nav-item"><a href="products.php" class="admin-nav-link"><i class="fas fa-box"></i><span>Produtos</span></a></div>
                <div class="admin-nav-item"><a href="categories.php" class="admin-nav-link"><i class="fas fa-tags"></i><span>Categorias</span></a></div>
                <div class="admin-nav-item"><a href="orders.php" class="admin-nav-link"><i class="fas fa-shopping-cart"></i><span>Pedidos</span></a></div>
                <div class="admin-nav-item"><a href="customers.php" class="admin-nav-link"><i class="fas fa-users"></i><span>Clientes</span></a></div>
                <div class="admin-nav-item"><a href="banners.php" class="admin-nav-link"><i class="fas fa-images"></i><span>Banners</span></a></div>
                <div class="admin-nav-item"><a href="reports.php" class="admin-nav-link"><i class="fas fa-chart-bar"></i><span>Relatórios</span></a></div>
                <div class="admin-nav-item"><a href="settings.php" class="admin-nav-link active"><i class="fas fa-cogs"></i><span>Configurações</span></a></div>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Configurações</h2>
                    <p>Gerencie as configurações da sua loja</p>
                </div>
                <div class="admin-actions">
                    <button type="submit" form="settingsForm" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Alterações</button>
                </div>
            </header>
            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form id="settingsForm" method="POST">
            <?php echo csrf_field(); ?>
            <div style="display: grid; grid-template-columns: 250px 1fr; gap: 30px;">
                <aside class="settings-sidebar">
                    <nav class="settings-nav">
                        <a href="#" class="settings-link active"><i class="fas fa-store"></i> Loja</a>
                        <a href="#" class="settings-link"><i class="fas fa-envelope"></i> E-mails</a>
                        <a href="#" class="settings-link"><i class="fas fa-credit-card"></i> Pagamentos</a>
                        <a href="#" class="settings-link"><i class="fas fa-truck"></i> Frete</a>
                        <a href="#" class="settings-link"><i class="fas fa-shield-alt"></i> Segurança</a>
                        <a href="#" class="settings-link"><i class="fas fa-users-cog"></i> Usuários</a>
                    </nav>
                </aside>
                <div class="settings-content">
                    <div class="admin-table-container" style="padding: 30px;">
                        <h4 style="margin-bottom: 25px;">Informações da Loja</h4>
                        <div class="admin-form-group">
                            <label for="store_name">Nome da Loja</label>
                            <input type="text" id="store_name" name="store_name" value="<?php echo htmlspecialchars($settings['store_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nome da loja">
                        </div>
                        <div class="admin-form-group">
                            <label for="store_email">E-mail de Contato</label>
                            <input type="email" id="store_email" name="store_email" value="<?php echo htmlspecialchars($settings['store_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="email@loja.com.br">
                        </div>
                        <div class="admin-form-group">
                            <label for="store_phone">Telefone</label>
                            <input type="tel" id="store_phone" name="store_phone" value="<?php echo htmlspecialchars($settings['store_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="(00) 00000-0000">
                        </div>
                        <div class="admin-form-group">
                            <label for="store_address">Endereço</label>
                            <textarea id="store_address" name="store_address" rows="2" placeholder="Endereço completo"><?php echo htmlspecialchars($settings['store_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group">
                                <label for="store_cnpj">CNPJ</label>
                                <input type="text" id="store_cnpj" name="store_cnpj" value="<?php echo htmlspecialchars($settings['store_cnpj'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="00.000.000/0001-00">
                            </div>
                            <div class="admin-form-group">
                                <label for="store_currency">Moeda</label>
                                <select id="store_currency" name="store_currency">
                                    <?php $cur = $settings['store_currency'] ?? 'BRL'; ?>
                                    <option value="BRL" <?php echo $cur === 'BRL' ? 'selected' : ''; ?>>Real (R$)</option>
                                    <option value="USD" <?php echo $cur === 'USD' ? 'selected' : ''; ?>>Dólar ($)</option>
                                    <option value="EUR" <?php echo $cur === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label for="store_description">Descrição da Loja</label>
                            <textarea id="store_description" name="store_description" rows="4" placeholder="Breve descrição da loja"><?php echo htmlspecialchars($settings['store_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <hr style="border: none; border-top: 1px solid var(--color-border); margin: 30px 0;">
                        <h4 style="margin-bottom: 25px;">Logo e Favicon</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="admin-file-upload"><i class="fas fa-cloud-upload-alt"></i><h5>Logo da Loja</h5><p style="color: var(--color-gray);">PNG ou JPG - 200x60px</p></div>
                            <div class="admin-file-upload"><i class="fas fa-cloud-upload-alt"></i><h5>Favicon</h5><p style="color: var(--color-gray);">PNG ou ICO - 32x32px</p></div>
                        </div>
                        <hr style="border: none; border-top: 1px solid var(--color-border); margin: 30px 0;">
                        <h4 style="margin-bottom: 25px;">Redes Sociais</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group"><label for="social_facebook">Facebook</label><input type="url" id="social_facebook" name="social_facebook" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://facebook.com/"></div>
                            <div class="admin-form-group"><label for="social_instagram">Instagram</label><input type="url" id="social_instagram" name="social_instagram" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://instagram.com/"></div>
                            <div class="admin-form-group"><label for="social_twitter">Twitter</label><input type="url" id="social_twitter" name="social_twitter" value="<?php echo htmlspecialchars($settings['social_twitter'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://twitter.com/"></div>
                            <div class="admin-form-group"><label for="social_youtube">YouTube</label><input type="url" id="social_youtube" name="social_youtube" value="<?php echo htmlspecialchars($settings['social_youtube'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://youtube.com/"></div>
                        </div>
                    </div>
                </div>
            </div>
            </form>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
