<?php
/**
 * Script de verificação de configuração do sistema
 * Execute: php setup-check.php
 */

echo "\n==============================================\n";
echo "  Royal Tech - Verificação de Configuração\n";
echo "==============================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Verificar conexão com banco
echo "[1/8] Verificando conexão com banco de dados...\n";
try {
    require_once __DIR__ . '/database/connection.php';
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $success[] = 'Conexão com banco de dados: OK';
        echo "  ✓ Conexão estabelecida\n";
    } else {
        $errors[] = 'Conexão com banco: FALHOU (PDO não disponível)';
        echo "  ✗ Erro: PDO não disponível\n";
    }
} catch (Throwable $e) {
    $errors[] = 'Conexão com banco: FALHOU (' . $e->getMessage() . ')';
    echo "  ✗ Erro: " . $e->getMessage() . "\n";
}

// 2. Verificar tabelas necessárias
echo "\n[2/8] Verificando tabelas do banco...\n";
if (isset($GLOBALS['pdo'])) {
    $requiredTables = [
        'e5_products', 'e5_users', 'e5_cart', 'e5_orders', 
        'e5_order_items', 'e5_settings', 'e5_encrypted_settings',
        'e5_categories', 'e5_coupons', 'e5_gateway_fees'
    ];
    
    foreach ($requiredTables as $table) {
        try {
            $stmt = $GLOBALS['pdo']->query("SELECT 1 FROM $table LIMIT 1");
            echo "  ✓ $table\n";
        } catch (Throwable $e) {
            $errors[] = "Tabela $table não encontrada";
            echo "  ✗ $table (não existe)\n";
        }
    }
}

// 3. Verificar Mercado Pago
echo "\n[3/8] Verificando configuração do Mercado Pago...\n";
if (isset($GLOBALS['pdo'])) {
    require_once __DIR__ . '/includes/security.php';
    
    $stmt = $GLOBALS['pdo']->query("SELECT setting_key FROM e5_encrypted_settings WHERE setting_key = 'mercadopago_access_token'");
    $mpToken = $stmt->fetch();
    
    if ($mpToken) {
        $success[] = 'Mercado Pago Access Token: Configurado';
        echo "  ✓ Access Token configurado\n";
    } else {
        $errors[] = 'Mercado Pago: Token não configurado';
        echo "  ✗ Access Token NÃO configurado\n";
        echo "    → Acesse: Admin > Configurações > Pagamentos\n";
    }
    
    $stmt = $GLOBALS['pdo']->query("SELECT setting_key FROM e5_encrypted_settings WHERE setting_key = 'mercadopago_public_key'");
    $mpPublicKey = $stmt->fetch();
    
    if ($mpPublicKey) {
        echo "  ✓ Public Key configurada\n";
    } else {
        $warnings[] = 'Mercado Pago: Public Key não configurada (necessária para cartão)';
        echo "  ⚠ Public Key NÃO configurada (cartão de crédito não funcionará)\n";
    }
}

// 4. Verificar SuperFrete
echo "\n[4/8] Verificando configuração do SuperFrete...\n";
if (isset($GLOBALS['pdo'])) {
    $stmt = $GLOBALS['pdo']->query("SELECT setting_value FROM e5_settings WHERE setting_key = 'superfrete_token'");
    $sfToken = $stmt->fetch();
    
    if ($sfToken && !empty($sfToken['setting_value'])) {
        $success[] = 'SuperFrete Token: Configurado';
        echo "  ✓ Token configurado\n";
    } else {
        // Verificar no cofre criptografado
        $stmt = $GLOBALS['pdo']->query("SELECT setting_key FROM e5_encrypted_settings WHERE setting_key IN ('superfrete_token', 'superfrete_access_token')");
        $sfEncrypted = $stmt->fetch();
        
        if ($sfEncrypted) {
            echo "  ✓ Token configurado (criptografado)\n";
        } else {
            $errors[] = 'SuperFrete: Token não configurado';
            echo "  ✗ Token NÃO configurado\n";
            echo "    → Acesse: Admin > Configurações > Frete\n";
        }
    }
    
    // Verificar sandbox mode
    $stmt = $GLOBALS['pdo']->query("SELECT setting_value FROM e5_settings WHERE setting_key = 'superfrete_sandbox'");
    $sandbox = $stmt->fetch();
    if ($sandbox && $sandbox['setting_value'] === '1') {
        $warnings[] = 'SuperFrete: Modo sandbox ativo';
        echo "  ⚠ Modo SANDBOX ativo (mude para produção após testes)\n";
    }
}

// 5. Verificar configurações da loja
echo "\n[5/8] Verificando configurações da loja...\n";
if (isset($GLOBALS['pdo'])) {
    $settings = [
        'store_name' => 'Nome da loja',
        'store_email' => 'E-mail da loja',
        'store_postal_code' => 'CEP de origem',
        'free_shipping_threshold' => 'Limite frete grátis',
    ];
    
    foreach ($settings as $key => $label) {
        $stmt = $GLOBALS['pdo']->prepare("SELECT setting_value FROM e5_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetch();
        
        if ($val && !empty($val['setting_value'])) {
            echo "  ✓ $label: {$val['setting_value']}\n";
        } else {
            $warnings[] = "$label não configurado";
            echo "  ⚠ $label: não configurado (usando padrão)\n";
        }
    }
}

// 6. Verificar gateways de pagamento
echo "\n[6/8] Verificando meios de pagamento...\n";
if (isset($GLOBALS['pdo'])) {
    $stmt = $GLOBALS['pdo']->query("SELECT gateway_name, is_configured FROM e5_payment_gateways");
    $gateways = $stmt->fetchAll();
    
    if (empty($gateways)) {
        $errors[] = 'Nenhum gateway de pagamento configurado';
        echo "  ✗ Nenhum gateway configurado\n";
    } else {
        foreach ($gateways as $gw) {
            $status = $gw['is_configured'] ? '✓' : '✗';
            echo "  $status {$gw['gateway_name']}\n";
        }
    }
}

// 7. Verificar diretórios
echo "\n[7/8] Verificando diretórios de armazenamento...\n";
$dirs = [
    'storage' => 'Armazenamento geral',
    'storage/comprovantes' => 'Comprovantes',
    'storage/logs' => 'Logs',
    'assets/img' => 'Imagens',
];

foreach ($dirs as $dir => $label) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path) && is_writable($path)) {
        echo "  ✓ $label: OK\n";
    } else {
        $errors[] = "Diretório $dir não existe ou sem permissão de escrita";
        echo "  ✗ $label: ERRO\n";
        
        // Tentar criar
        if (!is_dir($path)) {
            if (mkdir($path, 0755, true)) {
                echo "    → Criado automaticamente\n";
            }
        }
    }
}

// 8. Verificar extensões PHP
echo "\n[8/8] Verificando extensões PHP...\n";
$extensions = [
    'pdo' => 'PDO',
    'pdo_mysql' => 'PDO MySQL',
    'curl' => 'cURL',
    'openssl' => 'OpenSSL',
    'mbstring' => 'Multibyte String',
    'json' => 'JSON',
    'gd' => 'GD (imagens)',
];

foreach ($extensions as $ext => $label) {
    if (extension_loaded($ext)) {
        echo "  ✓ $label\n";
    } else {
        $errors[] = "Extensão PHP $ext não instalada";
        echo "  ✗ $label (não instalada)\n";
    }
}

// Resumo
echo "\n==============================================\n";
echo "                   RESUMO\n";
echo "==============================================\n\n";

if (empty($errors) && empty($warnings)) {
    echo "✓ Sistema configurado corretamente!\n\n";
} else {
    if (!empty($errors)) {
        echo "ERROS CRÍTICOS (" . count($errors) . "):\n";
        foreach ($errors as $i => $err) {
            echo "  " . ($i + 1) . ". $err\n";
        }
        echo "\n";
    }
    
    if (!empty($warnings)) {
        echo "AVISOS (" . count($warnings) . "):\n";
        foreach ($warnings as $i => $warn) {
            echo "  " . ($i + 1) . ". $warn\n";
        }
        echo "\n";
    }
}

echo "Próximos passos:\n";
echo "  1. Acesse /pages/admin/settings.php para configurar tokens\n";
echo "  2. Configure Mercado Pago: Access Token + Public Key\n";
echo "  3. Configure SuperFrete: Token + desative sandbox para produção\n";
echo "  4. Teste: Adicione produto ao carrinho → Calcule frete → Finalize\n";
echo "  5. Verifique webhooks: /api/webhooks/mercadopago.php\n\n";

echo "URLs úteis:\n";
echo "  - API SuperFrete: https://docs.superfrete.com/\n";
echo "  - Mercado Pago: https://www.mercadopago.com.br/developers\n";
echo "  - ViaCEP (já integrado): https://viacep.com.br/\n\n";

exit(empty($errors) ? 0 : 1);
