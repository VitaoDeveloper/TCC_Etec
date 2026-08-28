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
 * TODO: Integrate with gateway API
 */
function paymentProcessCreditCard(array $cardData, float $amount, string $orderId, array $customer): array
{
    // This should integrate with Mercado Pago or Asaas
    // Tokenize card and process payment
    
    return [
        'success' => false,
        'message' => 'Processamento de cartão ainda não implementado. Use Pix ou Boleto.',
        'data' => null,
    ];
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
 * Mercado Pago specific functions
 * Docs: https://www.mercadopago.com.br/developers/
 */

function paymentMercadoPagoCreatePayment(float $amount, string $orderId, array $items, array $customer): array
{
    // TODO: Implement MP SDK integration
    return ['success' => false, 'message' => 'Mercado Pago não implementado ainda.', 'data' => null];
}

/**
 * Asaas specific functions
 * Docs: https://docs.asaas.com/
 */

function paymentAsaasCreatePayment(float $amount, string $orderId, array $items, array $customer): array
{
    // TODO: Implement Asaas API integration
    return ['success' => false, 'message' => 'Asaas não implementado ainda.', 'data' => null];
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
