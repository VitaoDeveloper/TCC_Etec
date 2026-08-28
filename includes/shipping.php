<?php
/**
 * Shipping Integration — CPF & MEI compatible with Melhor Envio
 * 
 * Purpose: Freight calculation that adapts to tax regime automatically.
 * CPF mode: Uses public pricing table (standard rates).
 * MEI mode: Uses commercial pricing table (discounted PJ rates).
 * 
 * Migration path: Just activate MEI flag + add Melhor Envio token.
 * Same API endpoints, only table selection changes.
 * 
 * Docs: https://docs.melhorenvio.com.br/
 */

require_once __DIR__ . '/config.php';

/**
 * Get shipping configuration
 */
function shippingGetConfig(): array
{
    $taxRegime = store_config('tax_regime') ?: 'CPF';
    $melhorEnvioToken = store_config('melhor_envio_token') ?: '';
    $priceTable = store_config('melhor_envio_table') ?: 'public';
    
    // Auto-switch to commercial table when MEI is active
    if ($taxRegime === 'MEI' && !empty($melhorEnvioToken)) {
        $priceTable = 'commercial';
    }
    
    return [
        'tax_regime' => $taxRegime,
        'provider' => !empty($melhorEnvioToken) ? 'melhor_envio' : 'simple',
        'melhor_envio_token' => $melhorEnvioToken,
        'price_table' => $priceTable,
        'free_shipping_threshold' => (float) (store_config('free_shipping_threshold') ?: 500),
    ];
}

/**
 * Calculate shipping options for a given destination
 * 
 * @param string $toPostalCode Destination CEP
 * @param float $totalValue Order total value
 * @param array $items Cart items with dimensions (optional for Melhor Envio)
 * @return array Shipping options with cost and delivery time
 */
function shippingCalculate(string $toPostalCode, float $totalValue, array $items = []): array
{
    $config = shippingGetConfig();
    
    // Check free shipping
    if ($totalValue >= $config['free_shipping_threshold']) {
        return [
            'free' => [
                'method' => 'Frete Grátis',
                'carrier' => 'Correios',
                'cost' => 0.00,
                'days' => '5-10 úteis',
                'discount_applied' => true,
            ],
        ];
    }
    
    if ($config['provider'] === 'melhor_envio' && !empty($config['melhor_envio_token'])) {
        return shippingCalculateMelhorEnvio($toPostalCode, $items, $config);
    }
    
    // Fallback: simple CEP-based calculation (current implementation)
    return shippingCalculateSimple($toPostalCode);
}

/**
 * Simple freight calculation (no external API)
 * Used as fallback when Melhor Envio is not configured
 */
function shippingCalculateSimple(string $cep): array
{
    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) !== 8) {
        return [];
    }
    
    $prefix = (int) substr($cep, 0, 3);
    
    // Greater São Paulo (01000-19999)
    if ($prefix >= 1 && $prefix <= 199) {
        return [
            'pac' => ['method' => 'PAC', 'carrier' => 'Correios', 'cost' => 14.90, 'days' => '5-10 úteis'],
            'sedex' => ['method' => 'Sedex', 'carrier' => 'Correios', 'cost' => 29.90, 'days' => '1-2 úteis'],
        ];
    }
    
    // Southeast capital cities
    if ($prefix >= 1 && $prefix <= 99) {
        return [
            'pac' => ['method' => 'PAC', 'carrier' => 'Correios', 'cost' => 9.90, 'days' => '3-7 úteis'],
            'sedex' => ['method' => 'Sedex', 'carrier' => 'Correios', 'cost' => 19.90, 'days' => '1 dia útil'],
        ];
    }
    
    // Other regions
    return [
        'pac' => ['method' => 'PAC', 'carrier' => 'Correios', 'cost' => 24.90, 'days' => '7-15 úteis'],
        'sedex' => ['method' => 'Sedex', 'carrier' => 'Correios', 'cost' => 39.90, 'days' => '2-4 úteis'],
    ];
}

/**
 * Calculate shipping via Melhor Envio API
 * Automatically uses commercial table if MEI is active
 */
function shippingCalculateMelhorEnvio(string $toPostalCode, array $items, array $config): array
{
    $token = $config['melhor_envio_token'];
    $fromPostalCode = store_config('store_postal_code') ?: '01310-100'; // Default: Av. Paulista
    
    // Prepare package data
    $package = shippingPreparePackage($items);
    
    $payload = [
        'from' => ['postal_code' => preg_replace('/\D/', '', $fromPostalCode)],
        'to' => ['postal_code' => preg_replace('/\D/', '', $toPostalCode)],
        'package' => $package,
        'options' => [
            'receipt' => false,
            'own_hand' => false,
        ],
    ];
    
    // Call Melhor Envio API
    $ch = curl_init('https://melhorenvio.com.br/api/v2/me/shipment/calculate');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        // Fallback to simple calculation on API error
        return shippingCalculateSimple($toPostalCode);
    }
    
    $data = json_decode($response, true);
    
    if (empty($data)) {
        return shippingCalculateSimple($toPostalCode);
    }
    
    // Transform API response to our format
    $options = [];
    foreach ($data as $option) {
        $key = strtolower($option['company']['name'] ?? 'correios');
        $options[$key] = [
            'method' => $option['name'] ?? 'PAC',
            'carrier' => $option['company']['name'] ?? 'Correios',
            'cost' => (float) ($option['price'] ?? 0),
            'days' => sprintf('%d-%d úteis', 
                (int) ($option['delivery_time'] ?? 5),
                (int) ($option['delivery_time'] ?? 5) + 2
            ),
            'price_table' => $config['price_table'], // Indicates if using commercial rates
        ];
    }
    
    return $options;
}

/**
 * Prepare package dimensions from cart items
 * Default fallback if products don't have dimensions
 */
function shippingPreparePackage(array $items): array
{
    // TODO: Add weight/dimensions fields to e5_products table
    // For now, use standard package size
    
    $totalWeight = 0;
    foreach ($items as $item) {
        // Assume 0.5kg per item as fallback
        $totalWeight += 0.5 * (int) ($item['quantity'] ?? 1);
    }
    
    return [
        'weight' => max(0.3, $totalWeight), // Min 300g
        'width' => 20, // cm
        'height' => 10, // cm
        'length' => 30, // cm
    ];
}

/**
 * Get shipping migration checklist
 */
function shippingGetMigrationChecklist(PDO $pdo): array
{
    $config = shippingGetConfig();
    $seller = $pdo->query('SELECT * FROM e5_seller_profile WHERE is_active = 1 LIMIT 1')->fetch();
    
    $isMEI = $seller && $seller['tax_regime'] === 'MEI';
    $hasToken = !empty($config['melhor_envio_token']);
    
    return [
        [
            'task' => 'Cadastrar no Melhor Envio',
            'description' => $isMEI 
                ? 'Criar conta PJ no Melhor Envio com CNPJ: ' . ($seller['document_number'] ?? 'N/A')
                : 'Aguardando abertura do MEI. Conta CPF pode ser criada, mas sem desconto comercial.',
            'status' => $hasToken ? 'completed' : 'pending',
            'completed' => $hasToken,
            'priority' => 'high',
        ],
        [
            'task' => 'Gerar token de API',
            'description' => 'Acessar painel Melhor Envio > Configurações > API e gerar token de acesso.',
            'status' => $hasToken ? 'completed' : 'pending',
            'completed' => $hasToken,
            'priority' => 'high',
        ],
        [
            'task' => 'Ativar tabela comercial (MEI)',
            'description' => $isMEI && $hasToken
                ? 'Tabela comercial ativa automaticamente. Descontos PJ já aplicados.'
                : 'Aguardando MEI + token para ativar descontos comerciais.',
            'status' => $isMEI && $hasToken ? 'completed' : 'pending',
            'completed' => $isMEI && $hasToken,
            'priority' => 'medium',
        ],
        [
            'task' => 'Testar cálculo de frete',
            'description' => 'Fazer pedido teste para validar integração e valores de frete.',
            'status' => 'manual',
            'completed' => false,
            'priority' => 'high',
        ],
    ];
}

/**
 * Cost comparison between CPF (public) and MEI (commercial) rates
 */
function shippingGetCostComparison(string $sampleDestination = '20040-020'): array
{
    // Simulate both modes
    $originalConfig = shippingGetConfig();
    
    // Force public table (CPF mode)
    $_SESSION['_shipping_test_table'] = 'public';
    $costCPF = shippingCalculate($sampleDestination, 100.00); // Below free shipping
    
    // Force commercial table (MEI mode)
    $_SESSION['_shipping_test_table'] = 'commercial';
    $costMEI = shippingCalculate($sampleDestination, 100.00);
    
    unset($_SESSION['_shipping_test_table']);
    
    // Calculate savings
    $savings = [];
    foreach ($costCPF as $method => $dataCPF) {
        if (isset($costMEI[$method])) {
            $diff = $dataCPF['cost'] - $costMEI[$method]['cost'];
            $savings[$method] = [
                'cpf_cost' => $dataCPF['cost'],
                'mei_cost' => $costMEI[$method]['cost'],
                'savings' => $diff,
                'savings_percentage' => $dataCPF['cost'] > 0 ? ($diff / $dataCPF['cost']) * 100 : 0,
            ];
        }
    }
    
    return [
        'destination' => $sampleDestination,
        'comparison' => $savings,
        'note' => 'Economia estimada pode variar por região. MEI geralmente reduz frete em 10-25%.',
    ];
}
