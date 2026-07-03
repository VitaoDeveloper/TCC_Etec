<?php
$page_title = 'Configurações - Royal Tech';
include 'auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';

$settingsFile = __DIR__ . '/../../database/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}
$defaults = [
    'store_name'=>'','store_email'=>'','store_phone'=>'','store_address'=>'','store_cnpj'=>'',
    'store_currency'=>'BRL','store_description'=>'','social_facebook'=>'','social_instagram'=>'',
    'social_twitter'=>'','social_youtube'=>'','store_logo'=>'','store_favicon'=>'',
    'smtp_host'=>'','smtp_port'=>'587','smtp_user'=>'','smtp_pass'=>'','smtp_encryption'=>'tls',
    'pix_key'=>'','boleto_days'=>'3',
    'free_shipping_threshold'=>'500',
];

$tab = (string) ($_GET['tab'] ?? 'store');
$validTabs = ['store','emails','pagamentos','frete','segurança','usuários'];
if (!in_array($tab, $validTabs, true)) $tab = 'store';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();

    if (isset($_FILES['store_logo']) && is_uploaded_file($_FILES['store_logo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['store_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','webp'], true)) {
            $name = 'logo-' . time() . '.' . $ext;
            $target = __DIR__ . '/../../assets/img/' . $name;
            if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $target)) {
                $settings['store_logo'] = 'assets/img/' . $name;
            }
        }
    }
    if (isset($_FILES['store_favicon']) && is_uploaded_file($_FILES['store_favicon']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['store_favicon']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','ico','webp'], true)) {
            $name = 'favicon-' . time() . '.' . $ext;
            $target = __DIR__ . '/../../assets/img/' . $name;
            if (move_uploaded_file($_FILES['store_favicon']['tmp_name'], $target)) {
                $settings['store_favicon'] = 'assets/img/' . $name;
            }
        }
    }

    $keys = array_keys($defaults);
    foreach ($keys as $k) {
        if (in_array($k, ['store_logo','store_favicon'], true)) continue;
        $settings[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $_SESSION['admin_message'] = 'Configurações salvas com sucesso.';
    header('Location: settings.php?tab=' . urlencode($tab));
    exit;
}

$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);

function val($key) { global $settings; return htmlspecialchars($settings[$key] ?? '', ENT_QUOTES, 'UTF-8'); }
function sel($key, $val) { global $settings; return ($settings[$key] ?? '') === $val ? 'selected' : ''; }
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
    <style>
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .settings-link.active { background: rgba(212,175,55,0.1); color: var(--color-primary); }
    .logo-preview { max-width: 200px; max-height: 60px; border-radius: 5px; margin-top: 8px; }
    .favicon-preview { width: 32px; height: 32px; border-radius: 3px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php $activePage = 'settings'; include 'sidebar_inc.php'; ?>
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
            <form id="settingsForm" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div style="display: grid; grid-template-columns: 250px 1fr; gap: 30px;">
                <aside class="settings-sidebar">
                    <nav class="settings-nav">
                        <a href="?tab=store" class="settings-link<?php echo $tab==='store'?' active':''; ?>"><i class="fas fa-store"></i> Loja</a>
                        <a href="?tab=emails" class="settings-link<?php echo $tab==='emails'?' active':''; ?>"><i class="fas fa-envelope"></i> E-mails</a>
                        <a href="?tab=pagamentos" class="settings-link<?php echo $tab==='pagamentos'?' active':''; ?>"><i class="fas fa-credit-card"></i> Pagamentos</a>
                        <a href="?tab=frete" class="settings-link<?php echo $tab==='frete'?' active':''; ?>"><i class="fas fa-truck"></i> Frete</a>
                        <a href="?tab=segurança" class="settings-link<?php echo $tab==='segurança'?' active':''; ?>"><i class="fas fa-shield-alt"></i> Segurança</a>
                        <a href="?tab=usuários" class="settings-link<?php echo $tab==='usuários'?' active':''; ?>"><i class="fas fa-users-cog"></i> Usuários</a>
                    </nav>
                </aside>
                <div class="settings-content">

                    <!-- Loja -->
                    <div class="admin-table-container" style="padding:30px;<?php echo $tab!=='store'?' display:none;':''; ?>">
                        <h4 style="margin-bottom:25px;">Informações da Loja</h4>
                        <div class="admin-form-group"><label for="store_name">Nome da Loja</label><input type="text" id="store_name" name="store_name" value="<?php echo val('store_name'); ?>" placeholder="Nome da loja"></div>
                        <div class="admin-form-group"><label for="store_email">E-mail de Contato</label><input type="email" id="store_email" name="store_email" value="<?php echo val('store_email'); ?>" placeholder="email@loja.com.br"></div>
                        <div class="admin-form-group"><label for="store_phone">Telefone</label><input type="tel" id="store_phone" name="store_phone" value="<?php echo val('store_phone'); ?>" placeholder="(00) 00000-0000"></div>
                        <div class="admin-form-group"><label for="store_address">Endereço</label><textarea id="store_address" name="store_address" rows="2" placeholder="Endereço completo"><?php echo val('store_address'); ?></textarea></div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                            <div class="admin-form-group"><label for="store_cnpj">CNPJ</label><input type="text" id="store_cnpj" name="store_cnpj" value="<?php echo val('store_cnpj'); ?>" placeholder="00.000.000/0001-00"></div>
                            <div class="admin-form-group"><label for="store_currency">Moeda</label><select id="store_currency" name="store_currency"><option value="BRL" <?php echo sel('store_currency','BRL'); ?>>Real (R$)</option><option value="USD" <?php echo sel('store_currency','USD'); ?>>Dólar ($)</option><option value="EUR" <?php echo sel('store_currency','EUR'); ?>>Euro (€)</option></select></div>
                        </div>
                        <div class="admin-form-group"><label for="store_description">Descrição da Loja</label><textarea id="store_description" name="store_description" rows="4" placeholder="Breve descrição da loja"><?php echo val('store_description'); ?></textarea></div>

                        <hr style="border:none; border-top:1px solid var(--color-border); margin:30px 0;">
                        <h4 style="margin-bottom:25px;">Logo e Favicon</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                            <div>
                                <div class="admin-file-upload" onclick="document.getElementById('logoInput').click()" style="cursor:pointer;">
                                    <i class="fas fa-cloud-upload-alt"></i><h5>Logo da Loja</h5><p style="color:var(--color-gray);">PNG, JPG ou WEBP - 200x60px</p>
                                </div>
                                <input type="file" id="logoInput" name="store_logo" accept=".png,.jpg,.jpeg,.webp" style="display:none">
                                <?php if (!empty($settings['store_logo'])): ?><img src="../../<?php echo htmlspecialchars($settings['store_logo'], ENT_QUOTES, 'UTF-8'); ?>" class="logo-preview" alt="Logo"><?php endif; ?>
                            </div>
                            <div>
                                <div class="admin-file-upload" onclick="document.getElementById('faviconInput').click()" style="cursor:pointer;">
                                    <i class="fas fa-cloud-upload-alt"></i><h5>Favicon</h5><p style="color:var(--color-gray);">PNG, ICO ou WEBP - 32x32px</p>
                                </div>
                                <input type="file" id="faviconInput" name="store_favicon" accept=".png,.jpg,.jpeg,.ico,.webp" style="display:none">
                                <?php if (!empty($settings['store_favicon'])): ?><img src="../../<?php echo htmlspecialchars($settings['store_favicon'], ENT_QUOTES, 'UTF-8'); ?>" class="favicon-preview" alt="Favicon"><?php endif; ?>
                            </div>
                        </div>

                        <hr style="border:none; border-top:1px solid var(--color-border); margin:30px 0;">
                        <h4 style="margin-bottom:25px;">Redes Sociais</h4>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                            <div class="admin-form-group"><label for="social_facebook">Facebook</label><input type="url" id="social_facebook" name="social_facebook" value="<?php echo val('social_facebook'); ?>" placeholder="https://facebook.com/"></div>
                            <div class="admin-form-group"><label for="social_instagram">Instagram</label><input type="url" id="social_instagram" name="social_instagram" value="<?php echo val('social_instagram'); ?>" placeholder="https://instagram.com/"></div>
                            <div class="admin-form-group"><label for="social_twitter">Twitter</label><input type="url" id="social_twitter" name="social_twitter" value="<?php echo val('social_twitter'); ?>" placeholder="https://twitter.com/"></div>
                            <div class="admin-form-group"><label for="social_youtube">YouTube</label><input type="url" id="social_youtube" name="social_youtube" value="<?php echo val('social_youtube'); ?>" placeholder="https://youtube.com/"></div>
                        </div>
                    </div>

                    <!-- E-mails -->
                    <div class="admin-table-container" style="padding:30px;<?php echo $tab!=='emails'?' display:none;':''; ?>">
                        <h4 style="margin-bottom:25px;">Configurações de E-mail</h4>
                        <p style="color:var(--color-gray); margin-bottom:20px;">Configurações do servidor SMTP para envio de e-mails transacionais (confirmação de pedido, recuperação de senha).</p>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                            <div class="admin-form-group"><label for="smtp_host">Servidor SMTP</label><input type="text" id="smtp_host" name="smtp_host" value="<?php echo val('smtp_host'); ?>" placeholder="smtp.gmail.com"></div>
                            <div class="admin-form-group"><label for="smtp_port">Porta</label><input type="number" id="smtp_port" name="smtp_port" value="<?php echo val('smtp_port'); ?>" placeholder="587"></div>
                            <div class="admin-form-group"><label for="smtp_user">Usuário</label><input type="text" id="smtp_user" name="smtp_user" value="<?php echo val('smtp_user'); ?>" placeholder="seu@email.com"></div>
                            <div class="admin-form-group"><label for="smtp_pass">Senha</label><input type="password" id="smtp_pass" name="smtp_pass" value="<?php echo val('smtp_pass'); ?>" placeholder="********"></div>
                            <div class="admin-form-group"><label for="smtp_encryption">Criptografia</label><select id="smtp_encryption" name="smtp_encryption"><option value="tls" <?php echo sel('smtp_encryption','tls'); ?>>TLS</option><option value="ssl" <?php echo sel('smtp_encryption','ssl'); ?>>SSL</option><option value="" <?php echo sel('smtp_encryption',''); ?>>Nenhuma</option></select></div>
                        </div>
                    </div>

                    <!-- Pagamentos -->
                    <div class="admin-table-container" style="padding:30px;<?php echo $tab!=='pagamentos'?' display:none;':''; ?>">
                        <h4 style="margin-bottom:25px;">Configurações de Pagamento</h4>
                        <div class="admin-form-group"><label for="pix_key">Chave Pix</label><input type="text" id="pix_key" name="pix_key" value="<?php echo val('pix_key'); ?>" placeholder="CNPJ, CPF, e-mail, telefone ou chave aleatória"></div>
                        <div class="admin-form-group"><label for="boleto_days">Vencimento do Boleto (dias)</label><input type="number" id="boleto_days" name="boleto_days" value="<?php echo val('boleto_days'); ?>" min="1" max="30"></div>
                    </div>

                    <!-- Frete -->
                    <div class="admin-table-container" style="padding:30px;<?php echo $tab!=='frete'?' display:none;':''; ?>">
                        <h4 style="margin-bottom:25px;">Configurações de Frete</h4>
                        <div class="admin-form-group"><label for="free_shipping_threshold">Frete Grátis a partir de (R$)</label><input type="number" id="free_shipping_threshold" name="free_shipping_threshold" value="<?php echo val('free_shipping_threshold'); ?>" min="0" step="0.01"></div>
                        <p style="color:var(--color-gray); font-size:0.85rem; margin-top:-10px;">Valor mínimo do pedido para frete grátis. Deixe 0 para desabilitar.</p>
                    </div>

                    <!-- Segurança -->
                    <div class="admin-table-container" style="padding:30px;<?php echo $tab!=='segurança'?' display:none;':''; ?>">
                        <h4 style="margin-bottom:25px;">Segurança</h4>
                        <p style="color:var(--color-gray);">As configurações de segurança são gerenciadas pelo servidor. O sistema já conta com proteção CSRF, rate limiting (5 tentativas/15min) e senhas hasheadas com bcrypt.</p>
                    </div>

                    <!-- Usuários -->
                    <div class="admin-table-container" style="padding:30px;<?php echo $tab!=='usuários'?' display:none;':''; ?>">
                        <h4 style="margin-bottom:25px;">Gerenciar Administradores</h4>
                        <p style="color:var(--color-gray); margin-bottom:20px;">Para gerenciar administradores, acesse diretamente o banco de dados ou utilize um cliente MySQL.</p>
                        <table class="admin-table">
                            <thead><tr><th>ID</th><th>Nome</th><th>Usuário</th><th>E-mail</th></tr></thead>
                            <tbody>
                                <?php
                                $admins = $pdo->query('SELECT id, name, username, email FROM e5_users WHERE role = \'admin\' ORDER BY id')->fetchAll();
                                foreach ($admins as $a): ?>
                                <tr><td>#<?php echo (int)$a['id']; ?></td><td><?php echo htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($a['username'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($a['email'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
            </form>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
