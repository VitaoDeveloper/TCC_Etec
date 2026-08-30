<?php
$page_title = 'Configurações - Royal Tech';
include 'auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/security.php';

$settings = store_config();
$defaults = [
    'store_name'=>'','store_email'=>'','store_phone'=>'','store_address'=>'','store_cnpj'=>'',
    'store_currency'=>'BRL','store_description'=>'','social_facebook'=>'','social_instagram'=>'',
    'social_twitter'=>'','social_youtube'=>'','store_logo'=>'','store_favicon'=>'',
    'pix_key'=>'','boleto_days'=>'3',
    'free_shipping_threshold'=>'500',
    'store_postal_code'=>'01310-100',
];

$tab = (string) ($_GET['tab'] ?? 'store');
$validTabs = ['store','emails','pagamentos','frete','segurança','usuários'];
if (!in_array($tab, $validTabs, true)) $tab = 'store';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();

    $values = [];
    if (isset($_FILES['store_logo']) && is_uploaded_file($_FILES['store_logo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['store_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','webp'], true)) {
            $name = 'logo-' . time() . '.' . $ext;
            $target = __DIR__ . '/../../assets/img/' . $name;
            if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $target)) {
                $values['store_logo'] = 'assets/img/' . $name;
            }
        }
    }
    if (isset($_FILES['store_favicon']) && is_uploaded_file($_FILES['store_favicon']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['store_favicon']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','ico','webp'], true)) {
            $name = 'favicon-' . time() . '.' . $ext;
            $target = __DIR__ . '/../../assets/img/' . $name;
            if (move_uploaded_file($_FILES['store_favicon']['tmp_name'], $target)) {
                $values['store_favicon'] = 'assets/img/' . $name;
            }
        }
    }

    foreach ($defaults as $k => $_) {
        if (in_array($k, ['store_logo','store_favicon'], true)) continue;
        $values[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $values['superfrete_sandbox'] = isset($_POST['superfrete_sandbox']) ? '1' : '0';

    // SuperFrete token é salvo criptografado (NUNCA em texto puro no e5_settings)
    if (isset($_POST['superfrete_token']) && trim($_POST['superfrete_token']) !== '') {
        try {
            require_once __DIR__ . '/../../includes/security.php';
            saveEncryptedSetting($pdo, 'superfrete_token', trim($_POST['superfrete_token']));
            // NÃO adicionar ao $values — token só existe em e5_encrypted_settings
        } catch (Throwable $e) {
            error_log('settings: falha ao salvar token SuperFrete: ' . $e->getMessage());
        }
    }
    if (isset($_POST['remove_superfrete_token'])) {
        try {
            $pdo->prepare('DELETE FROM e5_encrypted_settings WHERE setting_key = :k')->execute([':k' => 'superfrete_token']);
            $pdo->prepare('DELETE FROM e5_settings WHERE setting_key = :k')->execute([':k' => 'superfrete_token']);
        } catch (Throwable $e) {
            error_log('settings: falha ao remover token SuperFrete: ' . $e->getMessage());
        }
    }

    try {
        store_config_save($values);
        $_SESSION['admin_message'] = 'Configurações salvas com sucesso.';
    } catch (Throwable $e) {
        $_SESSION['admin_message'] = 'Erro ao salvar configurações: banco indisponível.';
    }
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
    <?php include 'head_inc.php'; ?>
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
                        <p style="color:var(--color-gray); margin-bottom:20px;">O envio usa SMTP com conexão persistente (PHPMailer). As credenciais são gerenciadas por variáveis de ambiente no arquivo <code>.env</code> / <code>.env.prod</code> — não podem ser alteradas por aqui.</p>
                        <table class="admin-table">
                            <thead><tr><th>Parâmetro</th><th>Valor ativo</th></tr></thead>
                            <tbody>
                                <tr><td>MAIL_HOST</td><td><?php echo htmlspecialchars($_ENV['MAIL_HOST'] ?? 'localhost', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                                <tr><td>MAIL_PORT</td><td><?php echo htmlspecialchars($_ENV['MAIL_PORT'] ?? '1025', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                                <tr><td>MAIL_USERNAME</td><td><?php echo htmlspecialchars(($_ENV['MAIL_USERNAME'] ?? '') !== '' ? $_ENV['MAIL_USERNAME'] : '(sem autenticação)', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                                <tr><td>MAIL_ENCRYPTION</td><td><?php echo htmlspecialchars(($_ENV['MAIL_ENCRYPTION'] ?? '') !== '' ? strtoupper($_ENV['MAIL_ENCRYPTION']) : 'Nenhuma', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                                <tr><td>Remetente (From)</td><td><?php echo htmlspecialchars(store_config('store_name') . ' <' . store_config('store_email') . '>', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagamentos -->
                    <div class="admin-table-container" style="padding:30px;<?php echo $tab!=='pagamentos'?' display:none;':''; ?>">
                        <h4 style="margin-bottom:25px;">Configurações de Pagamento</h4>
                        
                        <!-- Gateway Selection -->
                        <?php
                        require_once __DIR__ . '/../../includes/gateways.php';
                        $allGateways = gatewayGetAll();
                        $activeGateway = gatewayGetActive();
                        $gwMessage = null;
                        
                        // Handle gateway activation POST
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_gateway'])) {
                            $targetGw = trim($_POST['activate_gateway']);
                            $gwResult = gatewayActivate($pdo, $targetGw, (int)$_SESSION['user_id']);
                            $gwMessage = $gwResult;
                        }
                        
                        // Handle gateway credentials save POST
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gateway_credentials'])) {
                            $gwName = trim($_POST['save_gateway_credentials']);
                            $gwCreds = [
                                'access_token' => trim($_POST['gw_access_token'] ?? ''),
                                'public_key' => trim($_POST['gw_public_key'] ?? ''),
                                'webhook_secret' => trim($_POST['gw_webhook_secret'] ?? ''),
                            ];
                            $gwResult = gatewaySaveCredentials($pdo, $gwName, $gwCreds);
                            $gwMessage = $gwResult;
                        }
                        
                        // Handle gateway test POST
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_gateway'])) {
                            $testGw = trim($_POST['test_gateway']);
                            $testToken = trim($_POST['test_token'] ?? '');
                            if (!empty($testToken)) {
                                $gwHealth = gatewayHealthCheck($testGw, $testToken);
                                $gwMessage = $gwHealth;
                            } else {
                                $gwMessage = ['success' => false, 'message' => 'Forneça um token para testar.'];
                            }
                        }
                        
                        // Handle gateway credential removal
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_gateway_credentials'])) {
                            $removeGw = trim($_POST['remove_gateway_credentials']);
                            $keys = ['access_token', 'public_key', 'webhook_secret'];
                            foreach ($keys as $key) {
                                $fullKey = $removeGw . '_' . $key;
                                $pdo->prepare('DELETE FROM e5_encrypted_settings WHERE setting_key = :k')->execute([':k' => $fullKey]);
                                $pdo->prepare('DELETE FROM e5_settings WHERE setting_key = :k')->execute([':k' => $fullKey]);
                            }
                            $pdo->prepare('UPDATE e5_payment_gateways SET is_configured = 0 WHERE gateway_name = :n')->execute([':n' => $removeGw]);
                            $gwMessage = ['success' => true, 'message' => 'Credenciais removidas.'];
                        }
                        
                        $allGateways = gatewayGetAll();
                        $activeGateway = gatewayGetActive();
                        ?>
                        
                        <?php if ($gwMessage): ?>
                        <div style="padding:12px 16px; border-radius:8px; margin-bottom:20px; background:<?php echo $gwMessage['success'] ? '#e8f5e9' : '#ffebee'; ?>; color:<?php echo $gwMessage['success'] ? '#2e7d32' : '#c62828'; ?>; border:1px solid <?php echo $gwMessage['success'] ? '#4caf50' : '#f44336'; ?>;">
                            <?php echo htmlspecialchars($gwMessage['message'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php endif; ?>

                        <div style="margin-bottom:25px; padding:16px; background:rgba(255,152,0,0.1); border:1px solid rgba(255,152,0,0.3); border-radius:8px; font-size:0.9rem; color:#e65100;">
                            <strong>Nota:</strong> A troca de gateway (Mercado Pago ↔ Asaas) é <strong>independente</strong> da migração de regime tributário (CPF ↔ MEI). São dois controles separados.
                        </div>

                        <h5 style="margin-bottom:15px; color:var(--color-primary);">Gateway Ativo</h5>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:30px;">
                            <?php foreach ($allGateways as $gw): ?>
                            <div style="border:2px solid <?php echo $gw['is_active'] ? 'var(--color-primary)' : 'var(--color-border)'; ?>; border-radius:10px; padding:20px; background:var(--color-bg-card); position:relative;">
                                <?php if ($gw['is_active']): ?>
                                <div style="position:absolute; top:10px; right:10px; background:var(--color-primary); color:#1a1a1a; padding:4px 10px; border-radius:12px; font-size:0.75rem; font-weight:700;">ATIVO</div>
                                <?php endif; ?>
                                <h4 style="margin:0 0 10px;"><i class="fas fa-<?php echo $gw['gateway_name'] === 'mercadopago' ? 'credit-card' : 'university'; ?>"></i> <?php echo htmlspecialchars($gw['display_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p style="color:var(--color-gray); font-size:0.85rem; margin-bottom:10px;">
                                    Status: <?php echo $gw['is_configured'] ? '<span style="color:#4caf50;">✓ Configurado</span>' : '<span style="color:#f44336;">✗ Não configurado</span>'; ?>
                                </p>
                                <?php if ($gw['last_health_check']): ?>
                                <p style="font-size:0.8rem; color:var(--color-gray);">
                                    Último teste: <?php echo date('d/m/Y H:i', strtotime($gw['last_health_check'])); ?>
                                    — <?php echo $gw['health_check_status'] === 'success' ? '<span style="color:#4caf50;">✓ OK</span>' : '<span style="color:#f44336;">✗ Falhou</span>'; ?>
                                </p>
                                <?php endif; ?>
                                <p style="font-size:0.8rem; color:var(--color-gray); margin-bottom:15px;">
                                    Suporta: <?php echo $gw['supports_cpf'] ? 'CPF ' : ''; ?><?php echo $gw['supports_cnpj'] ? 'CNPJ' : ''; ?>
                                </p>
                                
                                <?php if (!$gw['is_active']): ?>
                                <form method="POST" style="margin-bottom:10px;">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" name="activate_gateway" value="<?php echo htmlspecialchars($gw['gateway_name'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" style="width:100%;">
                                        <i class="fas fa-power-off"></i> Ativar este Gateway
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <details style="margin-top:10px;">
                                    <summary style="cursor:pointer; color:var(--color-gray); font-size:0.85rem;">Credenciais</summary>
                                    <form method="POST" style="margin-top:10px;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="save_gateway_credentials" value="<?php echo htmlspecialchars($gw['gateway_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="admin-form-group">
                                            <label style="font-size:0.8rem;">Access Token</label>
                                            <input type="password" name="gw_access_token" placeholder="<?php echo $gw['is_configured'] ? '(manter atual)' : 'Cole o token aqui'; ?>" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:4px;">
                                        </div>
                                        <div class="admin-form-group">
                                            <label style="font-size:0.8rem;">Public Key</label>
                                            <input type="password" name="gw_public_key" placeholder="(opcional)" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:4px;">
                                        </div>
                                        <div class="admin-form-group">
                                            <label style="font-size:0.8rem;">Webhook Secret</label>
                                            <input type="password" name="gw_webhook_secret" placeholder="(opcional)" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:4px;">
                                        </div>
                                        <div style="display:flex; gap:8px;">
                                            <button type="submit" class="btn" style="background:var(--color-primary); color:#1a1a1a;">
                                                <i class="fas fa-save"></i> Salvar
                                            </button>
                                            <?php if ($gw['is_configured']): ?>
                                            <form method="POST" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="remove_gateway_credentials" value="<?php echo htmlspecialchars($gw['gateway_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn" style="background:#f44336; color:#fff;">
                                                    <i class="fas fa-trash"></i> Remover
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </details>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <hr style="border:none; border-top:1px solid var(--color-border); margin:25px 0;">

                        <h5 style="margin-bottom:15px; color:var(--color-primary);">Histórico de Trocas de Gateway</h5>
                        <?php
                        $gwHistory = gatewayGetChangeHistory($pdo, 10);
                        if ($gwHistory):
                        ?>
                        <table class="admin-table">
                            <thead><tr><th>Data</th><th>Admin</th><th>Mudança</th><th>IP</th></tr></thead>
                            <tbody>
                            <?php foreach ($gwHistory as $h): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($h['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($h['admin_name'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(($h['config_before'] ?? '—') . ' → ' . ($h['config_after'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($h['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p style="color:var(--color-gray); font-size:0.9rem;">Nenhuma troca de gateway registrada.</p>
                        <?php endif; ?>

                        <hr style="border:none; border-top:1px solid var(--color-border); margin:25px 0;">

                        <!-- Fee Source Verification -->
                        <h5 style="margin-bottom:15px; color:var(--color-primary);">Taxas Documentadas</h5>
                        <?php
                        $feeRecords = $pdo->query('SELECT * FROM e5_gateway_fees ORDER BY gateway_name, document_type')->fetchAll();
                        if ($feeRecords):
                        ?>
                        <table class="admin-table">
                            <thead><tr><th>Gateway</th><th>Documento</th><th>Taxa</th><th>Fonte</th><th>Última Verificação</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($feeRecords as $fr): ?>
                            <?php
                            $daysAgo = $fr['last_verified_at'] ? (int)date_diff(date_create($fr['last_verified_at']), date_create())->format('%r%a') : null;
                            $isOutdated = $daysAgo === null || $daysAgo > 90;
                            ?>
                            <tr style="<?php echo $isOutdated ? 'background:rgba(255,152,0,0.05);' : ''; ?>">
                                <td><?php echo htmlspecialchars($fr['gateway_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($fr['document_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo number_format($fr['fee_percentage'], 2, ',', '.'); ?>%</td>
                                <td>
                                    <?php if ($fr['source_url']): ?>
                                    <a href="<?php echo htmlspecialchars($fr['source_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" style="color:var(--color-primary); font-size:0.85rem;">
                                        <?php echo htmlspecialchars($fr['source_url'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                    <?php else: ?>
                                    <span style="color:#f44336;">Sem URL de fonte</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fr['last_verified_at']): ?>
                                    <?php echo date('d/m/Y', strtotime($fr['last_verified_at'])); ?> (há <?php echo $daysAgo; ?> dias)
                                    <?php else: ?>
                                    <span style="color:#f44336;">Nunca verificado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isOutdated): ?>
                                    <span style="background:#ff9800; color:#fff; padding:2px 8px; border-radius:10px; font-size:0.75rem;">⚠ Desatualizado</span>
                                    <?php else: ?>
                                    <span style="background:#4caf50; color:#fff; padding:2px 8px; border-radius:10px; font-size:0.75rem;">✓ Atual</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="color:var(--color-gray); font-size:0.8rem; margin-top:10px;">
                            ⚠ Taxas são <strong>estimativas</strong>. Verifique periodicamente com a documentação oficial.
                            Taxas desatualizadas há mais de 90 dias são sinalizadas automaticamente.
                        </p>
                        <?php else: ?>
                        <p style="color:var(--color-gray); font-size:0.9rem;">Nenhuma taxa documentada encontrada.</p>
                        <?php endif; ?>

                        <hr style="border:none; border-top:1px solid var(--color-border); margin:25px 0;">

                        <div class="admin-form-group"><label for="pix_key">Chave Pix</label><input type="text" id="pix_key" name="pix_key" value="<?php echo val('pix_key'); ?>" placeholder="CNPJ, CPF, e-mail, telefone ou chave aleatória"></div>
                        <div class="admin-form-group"><label for="boleto_days">Vencimento do Boleto (dias)</label><input type="number" id="boleto_days" name="boleto_days" value="<?php echo val('boleto_days'); ?>" min="1" max="30"></div>
                    </div>

                    <!-- Frete -->
                    <div class="admin-table-container" style="padding:30px;<?php echo $tab!=='frete'?' display:none;':''; ?>">
                        <h4 style="margin-bottom:25px;">Configurações de Frete</h4>
                        <div class="admin-form-group"><label for="store_postal_code">CEP de Origem da Loja</label><input type="text" id="store_postal_code" name="store_postal_code" value="<?php echo val('store_postal_code'); ?>" placeholder="00000-000" maxlength="9" oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{5})(\d)/,'$1-$2')"></div>
                        <p style="color:var(--color-gray); font-size:0.85rem; margin-top:-10px;">CEP de onde os produtos são enviados. Usado para cálculo de frete na SuperFrete.</p>
                        <div class="admin-form-group"><label for="free_shipping_threshold">Frete Grátis a partir de (R$)</label><input type="number" id="free_shipping_threshold" name="free_shipping_threshold" value="<?php echo val('free_shipping_threshold'); ?>" min="0" step="0.01"></div>
                        <p style="color:var(--color-gray); font-size:0.85rem; margin-top:-10px;">Valor mínimo do pedido para frete grátis. Deixe 0 para desabilitar.</p>
                        <div class="admin-form-group">
                            <label for="superfrete_token">Token SuperFrete</label>
                            <input type="password" id="superfrete_token" name="superfrete_token" placeholder="eyJhbGciOiJIUzI1NiIs... (opcional)" autocomplete="off">
                            <p style="color:var(--color-gray); font-size:0.85rem; margin-top:-10px;">Gerado em <code>sandbox.superfrete.com/#/integrations</code> → "Site próprio" → "Gerar Token". Fica criptografado no banco.</p>
                        </div>
                        <?php
                        try {
                            $sfToken = (string) (loadEncryptedSetting($pdo, 'superfrete_token') ?: '');
                        } catch (Throwable $e) { $sfToken = ''; }
                        ?>
                        <div style="padding:10px 14px; border-radius:8px; margin-bottom:15px; background:<?php echo $sfToken ? '#e8f5e9' : '#fff3e0'; ?>; border:1px solid <?php echo $sfToken ? '#4caf50' : '#ff9800'; ?>; font-size:0.85rem;">
                            <?php echo $sfToken
                                ? '<strong>Token SuperFrete configurado</strong> — frete real (SuperFrete) ativo no checkout. Para substituir, cole o novo token acima e salve.'
                                : '<strong>Token SuperFrete não configurado</strong> — o checkout usará frete estimado. Cole o token acima e salve.'; ?>
                        </div>
                        <?php if ($sfToken): ?>
                        <div class="admin-form-group"><label><input type="checkbox" name="remove_superfrete_token" value="1"> Remover token SuperFrete</label></div>
                        <?php endif; ?>
                        <div class="admin-form-group">
                            <label><input type="checkbox" name="superfrete_sandbox" value="1" <?php echo ($settings['superfrete_sandbox'] ?? '1') === '1' ? 'checked' : ''; ?>> Usar ambiente Sandbox da SuperFrete</label>
                            <p style="color:var(--color-gray); font-size:0.85rem; margin-top:-10px;">Desmarque ao migrar para produção (<code>api.superfrete.com</code>).</p>
                        </div>
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
