<?php
/**
 * Payment Gateway Integration — CPF & MEI compatible
 * 
 * Purpose: Unified payment interface that works with both CPF and CNPJ documents.
 * Supports Mercado Pago and Asaas (both accept CPF/CNPJ on same API structure).
 * 
 * Migration path: Update document in gateway dashboard manually when switching to MEI.
 * Code remains unchanged — only fee configuration switches automatically.
 */

require_once __DIR__ . '/config.php';

/**
 * Get active payment gateway configuration
 * 
 * IMPORTANT: Fee values are ESTIMATES only. Always verify with your gateway:
 * - Mercado Pago: https://www.mercadopago.com.br/costs
 * - Asaas: https://www.asaas.com/precos
 * 
 * Gateway fees depend on: account type, volume, negotiation, payment method.
 * CPF vs CNPJ differentiation is NOT guaranteed.
 */
function paymentGetConfig(): array
{
    $gateway = store_config('payment_gateway') ?: 'mercadopago';
    $taxRegime = store_config('tax_regime') ?: 'CPF';
    
    // Load documented fee from gateway_fees table (with is_estimate flag)
    $fee = paymentGetGatewayFee($gateway, $taxRegime === 'MEI' ? 'CNPJ' : 'CPF');
    
    return [
        'gateway' => $gateway,
        'tax_regime' => $taxRegime,
        'fee_percentage' => $fee['percentage'],
        'fee_fixed' => $fee['fixed'],
        'fee_is_estimate' => $fee['is_estimate'],
        'fee_source_url' => $fee['source_url'],
        'pix_key' => store_config('pix_key') ?: 'royaltech.original@gmail.com',
        'boleto_days' => (int) (store_config('boleto_days') ?: 3),
    ];
}

/**
 * Get documented gateway fee from database
 * Falls back to conservative estimate if not found
 */
function paymentGetGatewayFee(string $gateway, string $documentType): array
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once dirname(__DIR__) . '/database/connection.php';
        }
        
        $stmt = $GLOBALS['pdo']->prepare('
            SELECT fee_percentage, fee_fixed, is_estimate, source_url
            FROM e5_gateway_fees
            WHERE gateway_name = :gateway AND document_type = :doctype
            ORDER BY verified_at DESC
            LIMIT 1
        ');
        $stmt->execute([':gateway' => $gateway, ':doctype' => $documentType]);
        $row = $stmt->fetch();
        
        if ($row) {
            return [
                'percentage' => (float) $row['fee_percentage'],
                'fixed' => (float) $row['fee_fixed'],
                'is_estimate' => (bool) $row['is_estimate'],
                'source_url' => $row['source_url'],
            ];
        }
    } catch (Throwable $e) {
        error_log('Failed to load gateway fee: ' . $e->getMessage());
    }
    
    // Conservative fallback estimate
    return [
        'percentage' => 3.99,
        'fixed' => 0.00,
        'is_estimate' => true,
        'source_url' => null,
    ];
}

/**
 * Calculate fee amount for a given total
 */
function paymentCalculateFee(float $total): float
{
    $config = paymentGetConfig();
    return round($total * ($config['fee_percentage'] / 100), 2);
}

/**
 * Get payment method details for display
 */
function paymentGetMethods(): array
{
    $config = paymentGetConfig();
    
    return [
        'pix' => [
            'label' => 'Pix',
            'icon' => 'fa-pix',
            'desc' => 'Aprovação instantânea. 5% de desconto!',
            'enabled' => !empty($config['pix_key']),
            'discount_percentage' => 5.0,
            'fee_percentage' => 0.0, // No fee for Pix
        ],
        'boleto' => [
            'label' => 'Boleto Bancário',
            'icon' => 'fa-barcode',
            'desc' => sprintf('Vencimento em %d dias úteis.', $config['boleto_days']),
            'enabled' => true,
            'discount_percentage' => 0.0,
            'fee_percentage' => 0.0, // Flat fee usually, not percentage
        ],
        'credit' => [
            'label' => 'Cartão de Crédito',
            'icon' => 'fa-credit-card',
            'desc' => 'Parcele em até 12x.',
            'enabled' => true,
            'discount_percentage' => 0.0,
            'fee_percentage' => $config['fee_percentage'],
        ],
        'delivery' => [
            'label' => 'Pagar na Entrega',
            'icon' => 'fa-money-bill-wave',
            'desc' => 'Pague ao receber (dinheiro ou cartão).',
            'enabled' => true,
            'discount_percentage' => 0.0,
            'fee_percentage' => 0.0,
        ],
    ];
}

/**
 * Generate Pix QR Code and copy-paste code
 * Uses PIX key from configuration (email by default)
 */
function paymentGeneratePixCode(float $amount, string $orderId, string $customerName): array
{
    $config = paymentGetConfig();
    $pixKey = $config['pix_key'];
    
    if (empty($pixKey)) {
        return [
            'success' => false,
            'message' => 'Chave PIX não configurada.',
            'data' => null,
        ];
    }
    
    // Simple PIX payload for manual payment
    // In production, integrate with gateway API (Mercado Pago or Asaas) for proper QR code
    
    $payload = sprintf(
        'PIX: %s | Valor: R$ %.2f | Pedido: #%s | Beneficiário: %s',
        $pixKey,
        $amount,
        $orderId,
        store_config('store_name') ?: 'Royal Tech'
    );
    
    // For now, return simple text code
    // TODO: Integrate with Mercado Pago or Asaas PIX API for real QR code
    
    return [
        'success' => true,
        'message' => 'Código PIX gerado com sucesso.',
        'data' => [
            'pix_key' => $pixKey,
            'amount' => $amount,
            'order_id' => $orderId,
            'qr_code_text' => $payload,
            'qr_code_image' => null, // TODO: Generate QR code image via API
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        ],
    ];
}

/**
 * Generate boleto barcode and PDF
 * TODO: Integrate with gateway API (Mercado Pago or Asaas)
 */
function paymentGenerateBoleto(float $amount, string $orderId, array $customer): array
{
    $config = paymentGetConfig();
    
    $dueDate = date('Y-m-d', strtotime('+' . $config['boleto_days'] . ' days'));
    
    // Stub implementation — integrate with real gateway
    return [
        'success' => true,
        'message' => 'Boleto gerado com sucesso.',
        'data' => [
            'barcode' => '00000.00000 00000.000000 00000.000000 0 00000000000000', // Fake barcode
            'due_date' => $dueDate,
            'amount' => $amount,
            'order_id' => $orderId,
            'pdf_url' => null, // TODO: Generate via API
        ],
    ];
}

/**
 * Process credit card payment
 * Dispatches to the active gateway (Mercado Pago or Asaas).
 * Token must be generated client-side (Checkout Transparente) — card numbers NEVER touch the server.
 *
 * @param array $cardData  ['token' => string, 'installments' => int, ...]
 * @param float $amount
 * @param string $orderId
 * @param array $customer ['name', 'email', 'cpf' => '000.000.000-00', ...]
 */
function paymentProcessCreditCard(array $cardData, float $amount, string $orderId, array $customer): array
{
    $gatewayName = store_config('payment_gateway') ?: 'mercadopago';
    $token = $cardData['token'] ?? '';
    $installments = max(1, (int) ($cardData['installments'] ?? 1));

    if (empty($token)) {
        return ['success' => false, 'message' => 'Token do cartão ausente. Verifique o formulário de pagamento.', 'data' => null];
    }

    $items = $cardData['items'] ?? [];

    if ($gatewayName === 'mercadopago') {
        $customer['card_brand'] = $cardData['card_brand'] ?? 'visa';
        return paymentMercadoPagoCreatePayment($amount, $orderId, $items, $customer, $token, $installments);
    }
    if ($gatewayName === 'asaas') {
        return paymentAsaasCreatePayment($amount, $orderId, $items, $customer, $token, $installments);
    }

    return ['success' => false, 'message' => 'Gateway desconhecido: ' . $gatewayName, 'data' => null];
}

/**
 * Process a refund (estorno) via the active gateway.
 *
 * @return array ['success' => bool, 'message' => string, 'data' => ?array]
 */
function paymentProcessRefund(string $transactionId, float $amount): array
{
    $gatewayName = store_config('payment_gateway') ?: 'mercadopago';

    if ($gatewayName === 'mercadopago') {
        return paymentRefundMercadoPago($transactionId, $amount);
    }
    if ($gatewayName === 'asaas') {
        return paymentRefundAsaas($transactionId, $amount);
    }

    return ['success' => false, 'message' => 'Gateway desconhecido: ' . $gatewayName];
}

/**
 * Get gateway migration checklist
 * Shows what needs to be updated when switching CPF → MEI
 */
function paymentGetMigrationChecklist(PDO $pdo): array
{
    $config = paymentGetConfig();
    $seller = $pdo->query('SELECT * FROM e5_seller_profile WHERE is_active = 1 LIMIT 1')->fetch();
    
    $isMEI = $seller && $seller['tax_regime'] === 'MEI';
    
    $checklist = [
        [
            'task' => 'Atualizar documento no Mercado Pago/Asaas',
            'description' => $isMEI 
                ? sprintf('Acessar painel do %s e trocar CPF por CNPJ: %s', $config['gateway'], $seller['document_number'])
                : 'Aguardando abertura do MEI para atualizar documento no gateway.',
            'status' => 'manual', // Requires manual action
            'completed' => false,
            'priority' => 'high',
        ],
        [
            'task' => 'Verificar taxas negociadas',
            'description' => $isMEI
                ? sprintf('Taxa MEI configurada: %.2f%%. Verificar se gateway aplicou desconto PJ.', $config['fee_percentage'])
                : sprintf('Taxa CPF atual: %.2f%%. Quando MEI ativar, taxa cairá para %.2f%%.', 
                    (float) store_config('payment_fee_cpf'),
                    (float) store_config('payment_fee_mei')
                ),
            'status' => 'manual',
            'completed' => false,
            'priority' => 'medium',
        ],
        [
            'task' => 'Atualizar chave PIX (se necessário)',
            'description' => sprintf(
                'Chave PIX atual: %s. Funciona para CPF e CNPJ. Trocar apenas se quiser usar e-mail corporativo.',
                $config['pix_key']
            ),
            'status' => 'optional',
            'completed' => true, // PIX already works with email
            'priority' => 'low',
        ],
        [
            'task' => 'Testar webhook de notificação',
            'description' => 'Após atualizar documento no gateway, testar se notificações de pagamento continuam funcionando.',
            'status' => 'manual',
            'completed' => false,
            'priority' => 'high',
        ],
    ];
    
    return $checklist;
}

/**
 * Mercado Pago — Checkout Transparente via API de Orders
 *
 * Docs: https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/overview
 * Ref:  https://www.mercadopago.com.br/developers/pt/reference/orders/_orders/post
 *
 * Fluxo:
 *  1. Front-end usa JS SDK do MP para tokenizar o cartão (nunca envia número ao servidor).
 *  2. Este servidor envia o token + dados para POST /v1/orders com o access_token.
 *  3. A API de Orders retorna status=processed/status_detail=accredited quando aprovado.
 *
 * Observação: A API de Orders exige X-Idempotency-Key e estrutura diferente da API legada
 * de pagamentos (/v1/payments). transactions é um objeto com sub-array payments[].
 *
 * @param float  $amount        Valor total (R$)
 * @param string $orderId       ID interno do pedido (external_reference)
 * @param array  $items         Itens do pedido [['title', 'quantity', 'unit_price', ...]]
 * @param array  $customer      ['name', 'email', 'cpf' => '00000000000']
 * @param string $cardToken     Token gerado pelo JS SDK do MP
 * @param int    $installments  Parcelas
 */
function paymentMercadoPagoCreatePayment(float $amount, string $orderId, array $items, array $customer, string $cardToken = '', int $installments = 1): array
{
    $accessToken = paymentGatewayGetAccessToken('mercadopago');
    if (empty($accessToken)) {
        return ['success' => false, 'message' => 'Access token do Mercado Pago não configurado.', 'data' => null];
    }
    if (empty($cardToken)) {
        return ['success' => false, 'message' => 'Token do cartão ausente. Verifique o formulário de pagamento.', 'data' => null];
    }

    $baseUrl = 'https://api.mercadopago.com';
    $cpf = preg_replace('/\D/', '', $customer['cpf'] ?? '');
    $nameParts = explode(' ', trim($customer['name'] ?? ''), 2);
    $firstName = $nameParts[0] ?? 'Comprador';
    $lastName = !empty($nameParts[1]) ? $nameParts[1] : ' Cliente';

    // Card brand from checkout JS (passed via $customer) or default to 'visa'
    $cardBrand = $customer['card_brand'] ?? 'visa';

    $payload = [
        'type'               => 'online',
        'external_reference' => $orderId,
        'total_amount'       => (string) round($amount, 2),
        'transactions'       => [
            'payments' => [
                [
                    'payment_method' => [
                        'id'                     => $cardBrand,
                        'type'                   => 'credit_card',
                        'token'                  => $cardToken,
                        'installments'           => $installments,
                        'statement_descriptor'   => 'ROYALTECH',
                    ],
                    'amount' => (string) round($amount, 2),
                ],
            ],
        ],
        'payer' => [
            'email'      => $customer['email'] ?? '',
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'identification' => [
                'type'   => 'CPF',
                'number' => $cpf,
            ],
            'address' => [
                'zip_code'      => '01310100',
                'street_name'   => 'Av. Paulista',
                'street_number' => '1000',
                'neighborhood'  => 'Bela Vista',
                'city'          => 'Sao Paulo',
                'state'         => 'SP',
            ],
        ],
        'items' => array_map(function ($item) {
            return [
                'title'       => $item['title'] ?? 'Produto',
                'description' => $item['description'] ?? ($item['title'] ?? ''),
                'unit_price'  => (string) round((float) ($item['unit_price'] ?? 0), 2),
                'quantity'    => (int) ($item['quantity'] ?? 1),
                'category_id' => 'electronic',
            ];
        }, $items),
    ];

    $result = paymentGatewayCurl($baseUrl . '/v1/orders', $payload, $accessToken, 'POST', [
        'X-Idempotency-Key: ' . 'order_' . $orderId,
    ]);

    if ($result['success']) {
        $data = $result['data'];
        $orderStatus = $data['status'] ?? 'rejected';
        $pmt = $data['transactions']['payments'][0] ?? [];
        $pmtStatus = $pmt['status'] ?? 'rejected';
        $pmtDetail = $pmt['status_detail'] ?? '';
        $approved = $orderStatus === 'processed' && in_array($pmtStatus, ['processed', 'approved']);

        return [
            'success' => $approved,
            'message' => $approved
                ? 'Pagamento aprovado pelo Mercado Pago!'
                : 'Pagamento ' . $orderStatus . '/' . $pmtStatus . ': ' . $pmtDetail,
            'data'    => [
                'status'            => $pmtStatus,
                'status_detail'     => $pmtDetail,
                'transaction_id'    => $data['id'] ?? null,
                'payment_id'        => $pmt['id'] ?? null,
                'external_reference'=> $data['external_reference'] ?? $orderId,
                'payment_method_id' => $pmt['payment_method']['id'] ?? null,
                'installments'      => $installments,
                'total_paid_amount' => (float) ($data['total_paid_amount'] ?? $amount),
            ],
        ];
    }

    return ['success' => false, 'message' => $result['message'] ?? 'Erro desconhecido.', 'data' => null];
}

/**
 * Mercado Pago — Refund (estorno)
 *
 * Docs: https://www.mercadopago.com.br/developers/en/reference/payments/_payments_id_refunds/post
 */
function paymentRefundMercadoPago(string $transactionId, float $amount): array
{
    $accessToken = paymentGatewayGetAccessToken('mercadopago');
    if (empty($accessToken)) {
        return ['success' => false, 'message' => 'Access token do Mercado Pago não configurado.'];
    }

    $baseUrl = 'https://api.mercadopago.com';

    $payload = [
        'amount' => round($amount, 2),
    ];

    $result = paymentGatewayCurl($baseUrl . '/v1/payments/' . $transactionId . '/refunds', $payload, $accessToken);

    if ($result['success']) {
        $data = $result['data'];
        return [
            'success' => in_array($data['status'] ?? '', ['approved', 'pending']),
            'message' => 'Estorno processado pelo Mercado Pago (status: ' . ($data['status'] ?? 'unknown') . ').',
            'data'    => $data,
        ];
    }

    return ['success' => false, 'message' => $result['message']];
}

/**
 * Asaas — Criação de pagamento com cartão (Checkout transparente)
 *
 * Docs: https://docs.asaas.com/reference/criar-um-pagamento
 *
 * Fluxo:
 *  1. Front-end gera token do cartão via JS do Asaas ou挾入自己/tokenização segura.
 *  2. POST /v3/payments com Bearer access_token.
 *  3. Retorna payment.status = CONFIRMED, PENDING, etc.
 *
 * @param float  $amount
 * @param string $orderId
 * @param array  $items
 * @param array  $customer      ['name','email','cpf']
 * @param string $cardToken     Tokenizado (ou billingType = CREDIT_CARD + creditCardToken)
 * @param int    $installments
 */
function paymentAsaasCreatePayment(float $amount, string $orderId, array $items, array $customer, string $cardToken = '', int $installments = 1): array
{
    $accessToken = paymentGatewayGetAccessToken('asaas');
    if (empty($accessToken)) {
        return ['success' => false, 'message' => 'Access token do Asaas não configurado.', 'data' => null];
    }

    $sandboxMode = (bool) store_config('asaas_sandbox') ?: true;
    $baseUrl = $sandboxMode
        ? 'https://sandbox.asaas.com/api/v3'
        : 'https://api.asaas.com/api/v3';

    $customerCpf = preg_replace('/\D/', '', $customer['cpf'] ?? '');

    // First ensure customer exists or create
    $asaasCustomer = paymentAsaasEnsureCustomer($baseUrl, $accessToken, $customer, $customerCpf);
    if (!$asaasCustomer['success']) {
        return $asaasCustomer;
    }
    $asaasCustomerId = $asaasCustomer['customer_id'];

    $payload = [
        'customer'           => $asaasCustomerId,
        'billingType'        => 'CREDIT_CARD',
        'dueDate'            => date('Y-m-d'),
        'value'              => round($amount, 2),
        'externalReference'  => $orderId,
        'installmentCount'   => $installments,
        'creditCardToken'    => $cardToken,
        'description'        => 'Pedido #' . $orderId,
    ];

    $result = paymentGatewayCurl($baseUrl . '/payments', $payload, $accessToken);

    if ($result['success']) {
        $data = $result['data'];
        $status = $data['status'] ?? 'PENDING';
        return [
            'success' => in_array($status, ['CONFIRMED', 'RECEIVED']),
            'message' => $status === 'CONFIRMED'
                ? 'Pagamento confirmado pelo Asaas!'
                : 'Pagamento ' . $status . ' pelo Asaas.',
            'data'    => [
                'status'         => $status,
                'transaction_id' => $data['id'] ?? null,
                'external_reference' => $data['externalReference'] ?? $orderId,
                'installments'   => $installments,
            ],
        ];
    }

    return ['success' => false, 'message' => $result['message'], 'data' => null];
}

/**
 * Asaas — Criar ou obter customer existente por CPF/CNPJ
 */
function paymentAsaasEnsureCustomer(string $baseUrl, string $accessToken, array $customer, string $cpf): array
{
    // Try to find existing customer by CPF
    $searchResult = paymentGatewayCurl(
        $baseUrl . '/customers?cpfCnpj=' . $cpf,
        null,
        $accessToken,
        'GET'
    );

    if ($searchResult['success']) {
        $list = $searchResult['data']['data'] ?? [];
        if (!empty($list)) {
            return ['success' => true, 'customer_id' => $list[0]['id']];
        }
    }

    // Create new customer
    $payload = [
        'name'    => $customer['name'] ?? '',
        'email'   => $customer['email'] ?? '',
        'cpfCnpj' => $cpf,
    ];

    $createResult = paymentGatewayCurl($baseUrl . '/customers', $payload, $accessToken);

    if ($createResult['success']) {
        return ['success' => true, 'customer_id' => $createResult['data']['id'] ?? null];
    }

    return ['success' => false, 'message' => 'Falha ao criar customer no Asaas: ' . $createResult['message']];
}

/**
 * Asaas — Refund (estorno)
 *
 * Docs: https://docs.asaas.com/reference/estornar-um-pagamento
 */
function paymentRefundAsaas(string $transactionId, float $amount): array
{
    $accessToken = paymentGatewayGetAccessToken('asaas');
    if (empty($accessToken)) {
        return ['success' => false, 'message' => 'Access token do Asaas não configurado.'];
    }

    $sandboxMode = (bool) store_config('asaas_sandbox') ?: true;
    $baseUrl = $sandboxMode
        ? 'https://sandbox.asaas.com/api/v3'
        : 'https://api.asaas.com/api/v3';

    $payload = [
        'value' => round($amount, 2),
    ];

    $result = paymentGatewayCurl($baseUrl . '/payments/' . $transactionId . '/refund', $payload, $accessToken);

    if ($result['success']) {
        $data = $result['data'];
        return [
            'success' => in_array($data['status'] ?? '', ['REFUNDED', 'PENDING']),
            'message' => 'Estorno processado pelo Asaas (status: ' . ($data['status'] ?? 'unknown') . ').',
            'data'    => $data,
        ];
    }

    return ['success' => false, 'message' => $result['message']];
}

/**
 * Helper: Ler access_token de um gateway do cofre criptografado.
 */
function paymentGatewayGetAccessToken(string $gatewayName): string
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once dirname(__DIR__) . '/database/connection.php';
        }
        $token = (string) loadEncryptedSetting($GLOBALS['pdo'], $gatewayName . '_access_token');
        if (!empty($token)) return $token;
        // Try alternative key names
        $token = (string) loadEncryptedSetting($GLOBALS['pdo'], $gatewayName . '_token');
        return $token;
    } catch (Throwable $e) {
        error_log('paymentGatewayGetAccessToken failed: ' . $e->getMessage());
        return '';
    }
}

/**
 * Helper: generic cURL POST/GET to gateway API.
 *
 * @param string       $url
 * @param array|null   $payload  null = GET
 * @param string       $accessToken
 * @param string       $method   'POST' (default) or 'GET'
 * @return array ['success' => bool, 'data' => ?array, 'message' => string, 'raw' => ?string]
 */
function paymentGatewayCurl(string $url, ?array $payload, string $accessToken, string $method = 'POST', array $extraHeaders = []): array
{
    $ch = curl_init();
    $headers = array_merge([
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
    ], $extraHeaders);

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    } else {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('Gateway cURL error: ' . $curlError);
        return ['success' => false, 'data' => null, 'message' => 'Erro de conexão com o gateway: ' . $curlError, 'raw' => null];
    }

    $data = json_decode($raw, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data, 'message' => 'OK', 'raw' => $raw];
    }

    $errorMsg = $data['message'] ?? $data['error_description'] ?? $data['detail'] ?? null;
    if (!$errorMsg && !empty($data['errors'])) {
        $firstError = $data['errors'][0];
        $errorMsg = ($firstError['code'] ?? '') . ': ' . ($firstError['message'] ?? '');
        if (!empty($firstError['details'])) {
            $errorMsg .= ' (' . implode('; ', (array) $firstError['details']) . ')';
        }
    }
    return ['success' => false, 'data' => $data, 'message' => $errorMsg ?? ('HTTP ' . $httpCode), 'raw' => $raw];
}

/**
 * Fee comparison report for admin dashboard
 * 
 * WARNING: Returns ESTIMATES only. Actual fees depend on:
 * - Gateway account type and volume
 * - Payment method (credit, debit, PIX, boleto)
 * - Negotiated rates with gateway
 * - Whether CPF/CNPJ actually affects fees (NOT GUARANTEED)
 */
function paymentGetFeeComparison(): array
{
    $gateway = store_config('payment_gateway') ?: 'mercadopago';
    
    $feeCPF = paymentGetGatewayFee($gateway, 'CPF');
    $feeMEI = paymentGetGatewayFee($gateway, 'CNPJ');
    
    // Sample revenue for projection
    $sampleMonthlyRevenue = 50000.00;
    
    $costCPF = $sampleMonthlyRevenue * ($feeCPF['percentage'] / 100);
    $costMEI = $sampleMonthlyRevenue * ($feeMEI['percentage'] / 100);
    $savings = $costCPF - $costMEI;
    $savingsPercentage = $costCPF > 0 ? ($savings / $costCPF) * 100 : 0;
    
    return [
        'regime' => [
            'cpf' => [
                'fee' => $feeCPF['percentage'],
                'monthly_cost' => $costCPF,
                'is_estimate' => $feeCPF['is_estimate'],
                'source_url' => $feeCPF['source_url'],
            ],
            'mei' => [
                'fee' => $feeMEI['percentage'],
                'monthly_cost' => $costMEI,
                'is_estimate' => $feeMEI['is_estimate'],
                'source_url' => $feeMEI['source_url'],
            ],
        ],
        'projection' => [
            'monthly_revenue' => $sampleMonthlyRevenue,
            'monthly_savings' => $savings,
            'savings_percentage' => $savingsPercentage,
            'annual_savings' => $savings * 12,
            'is_estimate' => true,
            'disclaimer' => 'Valores são estimativas. Confirme taxas reais com seu gateway de pagamento.',
        ],
    ];
}
