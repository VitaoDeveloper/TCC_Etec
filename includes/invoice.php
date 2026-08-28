<?php
/**
 * Invoice System — NF-e emission (dormant until MEI activation)
 * 
 * Purpose: Prepare invoice emission infrastructure before opening MEI.
 * When tax_regime = 'CPF': logs operations as pending, no API calls.
 * When tax_regime = 'MEI': emits real NF-e via provider (Focus NFe or NFe.io).
 * 
 * Migration path: Just activate MEI flag + fill provider credentials.
 * No code changes needed — same function works for both regimes.
 */

require_once __DIR__ . '/config.php';

/**
 * Check if invoice emission is active based on tax regime
 */
function invoiceIsActive(): bool
{
    $taxRegime = store_config('tax_regime');
    $nfeProvider = store_config('nfe_provider');
    
    return $taxRegime === 'MEI' && $nfeProvider !== 'disabled';
}

/**
 * Get seller profile from database
 */
function invoiceGetSellerProfile(PDO $pdo): ?array
{
    $stmt = $pdo->query('SELECT * FROM e5_seller_profile WHERE is_active = 1 LIMIT 1');
    return $stmt->fetch() ?: null;
}

/**
 * Main emission function — works in both CPF and MEI modes
 * 
 * CPF mode: Returns pending status, logs operation for future processing
 * MEI mode: Calls provider API and returns invoice data
 * 
 * @param PDO $pdo Database connection
 * @param int $orderId Order ID to emit invoice for
 * @return array ['success' => bool, 'message' => string, 'data' => array|null]
 */
function emitirNotaFiscal(PDO $pdo, int $orderId): array
{
    // Load order with items
    $stmt = $pdo->prepare('
        SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.postal_code
        FROM e5_orders o
        INNER JOIN e5_users u ON u.id = o.user_id
        WHERE o.id = :id LIMIT 1
    ');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        return ['success' => false, 'message' => 'Pedido não encontrado.', 'data' => null];
    }
    
    // Load order items
    $stmt = $pdo->prepare('
        SELECT oi.*, p.name AS product_name, p.brand
        FROM e5_order_items oi
        INNER JOIN e5_products p ON p.id = oi.product_id
        WHERE oi.order_id = :id
    ');
    $stmt->execute([':id' => $orderId]);
    $items = $stmt->fetchAll();
    
    if (empty($items)) {
        return ['success' => false, 'message' => 'Pedido sem itens.', 'data' => null];
    }
    
    // Check if invoice emission is active
    if (!invoiceIsActive()) {
        // CPF MODE: Log operation as pending for future processing
        invoiceLogPending($pdo, $orderId);
        
        return [
            'success' => true,
            'message' => 'Modo CPF ativo — nota fiscal não aplicável. Operação registrada para emissão futura quando MEI for ativado.',
            'data' => [
                'mode' => 'CPF',
                'status' => 'pending',
                'order_id' => $orderId,
                'logged_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    // MEI MODE: Call provider API
    $seller = invoiceGetSellerProfile($pdo);
    if (!$seller || $seller['tax_regime'] !== 'MEI') {
        return ['success' => false, 'message' => 'Perfil de vendedor MEI não configurado.', 'data' => null];
    }
    
    $provider = store_config('nfe_provider');
    
    if ($provider === 'focus') {
        return invoiceEmitFocusNFe($pdo, $order, $items, $seller);
    } elseif ($provider === 'nfeio') {
        return invoiceEmitNFeIO($pdo, $order, $items, $seller);
    }
    
    return ['success' => false, 'message' => 'Provedor de NF-e não configurado.', 'data' => null];
}

/**
 * Log pending invoice operation (CPF mode)
 */
function invoiceLogPending(PDO $pdo, int $orderId): void
{
    try {
        $stmt = $pdo->prepare('
            UPDATE e5_orders 
            SET invoice_status = :status, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':status' => 'pending',
            ':id' => $orderId,
        ]);
        
        // Log to file for audit trail
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/invoice_pending.log';
        $entry = sprintf(
            "[%s] ORDER #%d - CPF mode, invoice emission pending for future MEI activation\n",
            date('Y-m-d H:i:s'),
            $orderId
        );
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        
    } catch (Throwable $e) {
        // Silent fail — don't break checkout flow
        error_log('Invoice log failed: ' . $e->getMessage());
    }
}

/**
 * Emit invoice via Focus NFe API
 * Docs: https://focusnfe.com.br/doc/
 */
function invoiceEmitFocusNFe(PDO $pdo, array $order, array $items, array $seller): array
{
    $apiKey = store_config('nfe_api_key');
    $environment = store_config('nfe_environment') === 'producao' ? 'producao' : 'homologacao';
    $baseUrl = $environment === 'producao' 
        ? 'https://api.focusnfe.com.br'
        : 'https://homologacao.focusnfe.com.br';
    
    if (!$apiKey) {
        return ['success' => false, 'message' => 'Chave API Focus NFe não configurada.', 'data' => null];
    }
    
    // Build NFe payload
    $nfeData = [
        'natureza_operacao' => 'Venda de mercadoria',
        'tipo_documento' => '1', // 1=NF-e saída
        'finalidade_emissao' => '1', // 1=Normal
        'cnpj_emitente' => preg_replace('/\D/', '', $seller['document_number']),
        'nome_emitente' => $seller['legal_name'],
        'nome_fantasia_emitente' => $seller['trade_name'] ?: $seller['legal_name'],
        'inscricao_estadual_emitente' => $seller['state_registration'] ?: '',
        'regime_tributario_emitente' => '1', // 1=Simples Nacional
        
        'cpf_destinatario' => '', // Would need to collect from customer
        'nome_destinatario' => $order['customer_name'],
        'email_destinatario' => $order['customer_email'],
        'indicador_inscricao_estadual_destinatario' => '9', // 9=Não contribuinte
        
        'items' => [],
    ];
    
    // Add items
    $itemNumber = 1;
    foreach ($items as $item) {
        $nfeData['items'][] = [
            'numero_item' => $itemNumber++,
            'codigo_produto' => $item['product_id'],
            'descricao' => $item['product_name'] . ($item['brand'] ? ' - ' . $item['brand'] : ''),
            'cfop' => '5102', // Venda merc. adq. de terceiros
            'unidade_comercial' => 'UN',
            'quantidade_comercial' => $item['quantity'],
            'valor_unitario_comercial' => number_format((float)$item['unit_price'], 2, '.', ''),
            'valor_bruto' => number_format((float)$item['unit_price'] * (int)$item['quantity'], 2, '.', ''),
            'icms_situacao_tributaria' => '102', // Simples Nacional sem permissão de crédito
            'pis_situacao_tributaria' => '49', // Outras operações de saída
            'cofins_situacao_tributaria' => '49',
        ];
    }
    
    // Add shipping if applicable
    if ($order['shipping_cost'] > 0) {
        $nfeData['valor_frete'] = number_format((float)$order['shipping_cost'], 2, '.', '');
    }
    
    $nfeData['valor_total'] = number_format((float)$order['total'], 2, '.', '');
    
    // Call Focus NFe API
    $ch = curl_init($baseUrl . '/v2/nfe?ref=' . $order['id']);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($nfeData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        $error = json_decode($response, true)['mensagem'] ?? 'Erro desconhecido ao emitir NF-e.';
        
        // Log error in database
        $pdo->prepare('
            UPDATE e5_orders 
            SET invoice_status = :status, invoice_error_message = :error, updated_at = NOW()
            WHERE id = :id
        ')->execute([
            ':status' => 'error',
            ':error' => $error,
            ':id' => $order['id'],
        ]);
        
        return ['success' => false, 'message' => $error, 'data' => null];
    }
    
    $result = json_decode($response, true);
    
    // Update order with invoice data
    $pdo->prepare('
        UPDATE e5_orders 
        SET invoice_number = :number,
            invoice_key = :key,
            invoice_pdf_url = :pdf,
            invoice_xml_url = :xml,
            invoice_status = :status,
            updated_at = NOW()
        WHERE id = :id
    ')->execute([
        ':number' => $result['numero'] ?? null,
        ':key' => $result['chave_nfe'] ?? null,
        ':pdf' => $result['caminho_danfe'] ?? null,
        ':xml' => $result['caminho_xml_nota_fiscal'] ?? null,
        ':status' => 'issued',
        ':id' => $order['id'],
    ]);
    
    return [
        'success' => true,
        'message' => 'NF-e emitida com sucesso.',
        'data' => [
            'provider' => 'focus',
            'number' => $result['numero'] ?? null,
            'key' => $result['chave_nfe'] ?? null,
            'pdf_url' => $result['caminho_danfe'] ?? null,
            'xml_url' => $result['caminho_xml_nota_fiscal'] ?? null,
        ],
    ];
}

/**
 * Emit invoice via NFe.io API
 * Docs: https://nfe.io/docs/
 */
function invoiceEmitNFeIO(PDO $pdo, array $order, array $items, array $seller): array
{
    // Similar implementation to Focus NFe
    // Left as stub for now — implement when provider is chosen
    
    return [
        'success' => false,
        'message' => 'Provedor NFe.io não implementado ainda. Use Focus NFe.',
        'data' => null,
    ];
}

/**
 * Cancel an issued invoice
 */
function cancelarNotaFiscal(PDO $pdo, int $orderId, string $reason): array
{
    if (!invoiceIsActive()) {
        return ['success' => false, 'message' => 'Emissão de NF-e não está ativa.', 'data' => null];
    }
    
    $stmt = $pdo->prepare('SELECT invoice_key, invoice_status FROM e5_orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    
    if (!$order || $order['invoice_status'] !== 'issued') {
        return ['success' => false, 'message' => 'NF-e não encontrada ou não emitida.', 'data' => null];
    }
    
    // Implementation depends on provider
    // Both Focus NFe and NFe.io support cancellation within 24h
    
    return ['success' => false, 'message' => 'Cancelamento não implementado ainda.', 'data' => null];
}

/**
 * Retry invoice emission for orders in pending/error status
 * Useful for bulk processing when migrating from CPF to MEI
 */
function invoiceRetryPending(PDO $pdo, int $limit = 50): array
{
    if (!invoiceIsActive()) {
        return ['success' => false, 'message' => 'Modo CPF ativo — não é possível emitir notas.', 'data' => null];
    }
    
    $stmt = $pdo->prepare('
        SELECT id FROM e5_orders 
        WHERE invoice_status IN (:pending, :error)
        AND status IN (:paid, :shipped, :delivered)
        ORDER BY created_at DESC
        LIMIT :limit
    ');
    $stmt->execute([
        ':pending' => 'pending',
        ':error' => 'error',
        ':paid' => 'paid',
        ':shipped' => 'shipped',
        ':delivered' => 'delivered',
        ':limit' => $limit,
    ]);
    $orders = $stmt->fetchAll();
    
    $results = [
        'total' => count($orders),
        'success' => 0,
        'failed' => 0,
        'errors' => [],
    ];
    
    foreach ($orders as $order) {
        $result = emitirNotaFiscal($pdo, (int)$order['id']);
        if ($result['success']) {
            $results['success']++;
        } else {
            $results['failed']++;
            $results['errors'][] = [
                'order_id' => $order['id'],
                'message' => $result['message'],
            ];
        }
    }
    
    return [
        'success' => true,
        'message' => sprintf(
            'Processados %d pedidos: %d sucesso, %d falhas.',
            $results['total'],
            $results['success'],
            $results['failed']
        ),
        'data' => $results,
    ];
}
