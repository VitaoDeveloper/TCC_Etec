<?php
/**
 * Shipping Integration — SuperFrete (frete real) with transparent fallback
 *
 * Fluxo:
 *  1. Se existir token da SuperFrete (criptografado em e5_encrypted_settings),
 *     chama a API real POST /api/v0/calculator.
 *  2. Se o token não existir OU a API falhar, cai para "frete estimado"
 *     (tabela regional) e sinaliza isso claramente na UI (warning).
 *
 * O token NUNCA é hardcoded: é carregado do cofre criptografado e/ou do
 * store_config(). O CEP de origem é configurável (store_postal_code).
 *
 * Motivo da troca do Melhor Envio para SuperFrete: autenticação simples
 * via token Bearer (sem OAuth2), eliminando necessidade de URL pública
 * de callback durante desenvolvimento.
 *
 * Docs: https://docs.superfrete.com/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

/**
 * Zonas de frete estimado (fallback transparente quando a API real não está
 * disponível). Valores são APENAS estimativas e devem ser substituídos pelos
 * preços reais da SuperFrete assim que o token for configurado.
 */
function shippingEstimatedZones(): array
{
    return [
        'SP_CAPITAL'  => ['name' => 'São Paulo (capital/região metropolitana)', 'days' => '1-2 dias úteis',  'pac' => 14.90,  'sedex' => 29.90],
        'SP_INTERIOR' => ['name' => 'Interior de São Paulo',                   'days' => '2-4 dias úteis',  'pac' => 19.90,  'sedex' => 34.90],
        'SUDESTE'     => ['name' => 'Sudeste (RJ, MG, ES)',                    'days' => '3-7 dias úteis',  'pac' => 24.90,  'sedex' => 39.90],
        'SUL'         => ['name' => 'Sul (PR, SC, RS)',                        'days' => '4-9 dias úteis',  'pac' => 29.90,  'sedex' => 44.90],
        'CENTRO_OESTE'=> ['name' => 'Centro-Oeste (DF, GO, MT, MS)',           'days' => '5-10 dias úteis', 'pac' => 32.90,  'sedex' => 49.90],
        'NORDESTE'    => ['name' => 'Nordeste',                                'days' => '6-12 dias úteis', 'pac' => 35.90,  'sedex' => 54.90],
        'NORTE'       => ['name' => 'Norte',                                   'days' => '8-15 dias úteis', 'pac' => 39.90,  'sedex' => 59.90],
    ];
}

/**
 * Get shipping configuration (token via encrypted vault, CEP origem configurável).
 */
function shippingGetConfig(): array
{
    $taxRegime = store_config('tax_regime') ?: 'CPF';

    $token = (string) store_config('superfrete_token');
    if (empty($token)) {
        try {
            if (!isset($GLOBALS['pdo'])) {
                include_once dirname(__DIR__) . '/database/connection.php';
            }
            $token = (string) (loadEncryptedSetting($GLOBALS['pdo'], 'superfrete_token')
                ?: loadEncryptedSetting($GLOBALS['pdo'], 'superfrete_access_token'));
        } catch (Throwable $e) {
            error_log('shippingGetConfig: falha ao ler token SuperFrete: ' . $e->getMessage());
        }
    }

    return [
        'tax_regime' => $taxRegime,
        'provider' => !empty($token) ? 'superfrete' : 'simple',
        'superfrete_token' => $token,
        'has_token' => !empty($token),
        'origin_postal_code' => store_config('store_postal_code') ?: '01310-100',
        'free_shipping_threshold' => (float) (store_config('free_shipping_threshold') ?: 500),
    ];
}

/**
 * Valida e normaliza um CEP brasileiro.
 * Aceita "01310100" e "01310-100". Retorna só dígitos (8) ou null.
 */
function shippingValidateCep(string $cep): ?string
{
    $digits = preg_replace('/\D/', '', $cep);
    if (strlen($digits) !== 8) return null;
    return $digits;
}

/**
 * Busca endereço do CEP via ViaCEP (gratuito, sem token) para validar o CEP
 * e preencher bairro/cidade/UF no pedido.
 *
 * @return array|null ['cep','logradouro','bairro','cidade','uf'] ou null se inválido
 */
function shippingLookupCep(string $cep): ?array
{
    $digits = shippingValidateCep($cep);
    if (!$digits) return null;

    $url = 'https://viacep.com.br/ws/' . $digits . '/json/';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'RoyalTech-Checkout/1.0',
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($raw)) return null;

    $data = json_decode($raw, true);
    if (empty($data) || isset($data['erro'])) return null;

    return [
        'cep' => (string) $data['cep'],
        'logradouro' => (string) ($data['logradouro'] ?? ''),
        'bairro' => (string) ($data['bairro'] ?? ''),
        'cidade' => (string) ($data['localidade'] ?? ''),
        'uf' => (string) ($data['uf'] ?? ''),
        'ddd' => (string) ($data['ddd'] ?? ''),
    ];
}

/**
 * Calculate shipping options.
 *
 * @return array envelope:
 *  [
 *    'success' => bool,
 *    'provider' => 'superfrete'|'estimated'|'error',
 *    'is_real' => bool,
 *    'warning' => string|null,
 *    'error' => string|null,
 *    'address' => array|null (ViaCEP),
 *    'options' => array [key => ['method','carrier','cost','days','delivery_time',...]]
 *  ]
 */
function shippingCalculate(string $toPostalCode, float $totalValue, array $items = []): array
{
    $config = shippingGetConfig();

    $cep = shippingValidateCep($toPostalCode);
    if (!$cep) {
        return [
            'success' => false,
            'error' => 'CEP inválido. Informe 8 dígitos (NNNNNNNN ou NNNNN-NNN).',
            'provider' => 'error',
            'is_real' => false,
            'warning' => null,
            'address' => null,
            'options' => [],
        ];
    }

    $address = shippingLookupCep($cep);
    if ($address === null) {
        // CEP pode ser válido mas ViaCEP indisponível; continuamos sem os dados
        $address = ['cep' => $cep, 'logradouro' => '', 'bairro' => '', 'cidade' => '', 'uf' => ''];
    }

    // Requer token da SuperFrete configurado
    if ($config['provider'] !== 'superfrete' || !$config['has_token']) {
        return [
            'success' => false,
            'error' => 'Cálculo de frete não configurado. Configure o token da SuperFrete no painel admin (Configurações > Frete).',
            'provider' => 'error',
            'is_real' => false,
            'warning' => null,
            'address' => $address,
            'options' => [],
        ];
    }

    $result = shippingCalculateSuperFrete($cep, $items, $config);
    
    if (!$result['success']) {
        return [
            'success' => false,
            'error' => 'Erro ao calcular frete: ' . ($result['error'] ?? 'Serviço indisponível. Tente novamente.'),
            'provider' => 'error',
            'is_real' => false,
            'warning' => null,
            'address' => $address,
            'options' => [],
        ];
    }

    $useFreeShipping = $config['free_shipping_threshold'] > 0 && $totalValue >= $config['free_shipping_threshold'];

    if ($useFreeShipping) {
        foreach ($result['options'] as &$opt) {
            $opt['cost'] = 0.00;
        }
        unset($opt);
    }
    
    return [
        'success' => true,
        'provider' => 'superfrete',
        'is_real' => true,
        'warning' => $useFreeShipping ? 'Frete grátis aplicado no valor do pedido.' : null,
        'error' => null,
        'address' => $address,
        'options' => $result['options'],
    ];
}

/**
 * Chama a API real da SuperFrete.
 *
 * Docs: https://docs.superfrete.com/
 * Endpoint: POST https://sandbox.superfrete.com/api/v0/calculator (sandbox)
 *           POST https://api.superfrete.com/api/v0/calculator (produção)
 *
 * @return array ['success' => bool, 'options' => array, 'error' => ?string, 'raw' => ?string]
 */
function shippingCalculateSuperFrete(string $cep, array $items, array $config): array
{
    $token = $config['superfrete_token'];
    if (empty($token)) {
        return ['success' => false, 'options' => [], 'error' => 'Token da SuperFrete ausente.', 'raw' => null];
    }

    $fromPostalCode = shippingValidateCep($config['origin_postal_code']) ?: '01310100';
    $package = shippingPreparePackage($items);

    $payload = [
        'from'     => ['postal_code' => $fromPostalCode],
        'to'       => ['postal_code' => $cep],
        'services' => '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19',
        'package'  => [
            'height' => $package['height'],
            'width'  => $package['width'],
            'length' => $package['length'],
            'weight' => $package['weight'],
        ],
        'options'  => [
            'insurance_value' => 1000,
            'own_hand'        => false,
            'receipt'         => false,
        ],
    ];

    // URL: sandbox vs produção
    $isSandbox = (bool) store_config('superfrete_sandbox') ?: true;
    $baseUrl = $isSandbox
        ? 'https://sandbox.superfrete.com'
        : 'https://api.superfrete.com';

    $ch = curl_init($baseUrl . '/api/v0/calculator');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'User-Agent: Royal Tech (royaltech.original@gmail.com)',
            'Accept: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'options' => [], 'error' => 'Erro de conexão: ' . $curlError, 'raw' => $raw];
    }

    // A API retorna array de serviços OU objeto de erro
    $data = json_decode($raw, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $detail = '';
        if (is_array($data) && isset($data['errors'])) {
            $errorMessages = [];
            foreach ($data['errors'] as $field => $msgs) {
                foreach ((array) $msgs as $m) {
                    $errorMessages[] = $m;
                }
            }
            $detail = ' ' . implode('; ', $errorMessages);
        } elseif (is_array($data) && isset($data['message'])) {
            $detail = ' ' . $data['message'];
        }
        return [
            'success' => false,
            'options' => [],
            'error' => 'API SuperFrete retornou HTTP ' . $httpCode . '.' . $detail,
            'raw' => $raw,
        ];
    }

    if (!is_array($data)) {
        return ['success' => false, 'options' => [], 'error' => 'Resposta inválida da SuperFrete.', 'raw' => $raw];
    }

    // Parse: array de serviços (cada um pode ter erro)
    $options = [];
    foreach ($data as $option) {
        $error = (string) ($option['error'] ?? '');
        if ($error !== '' || !empty($option['has_error'])) {
            continue; // serviço indisponível para esta rota
        }

        $name = (string) ($option['name'] ?? 'Frete');
        $company = (string) ($option['company']['name'] ?? 'Transportadora');

        $deliveryTime = max(1, (int) ($option['delivery_time'] ?? 5));
        $deliveryRange = $option['delivery_range'] ?? [];
        $minDays = (int) ($deliveryRange['min'] ?? $deliveryTime);
        $maxDays = (int) ($deliveryRange['max'] ?? $deliveryTime + 1);

        $cost = (float) ($option['price'] ?? 0);

        $key = shippingOptionKey($company . '-' . $name);

        $options[$key] = [
            'method'          => $name,
            'carrier'         => $company,
            'cost'            => $cost,
            'days'            => $minDays . '-' . $maxDays . ' dias úteis',
            'delivery_time'   => $minDays,
            'delivery_max_days' => $maxDays,
            'estimated'       => false,
            'discount'        => (float) ($option['discount'] ?? 0),
            'currency'        => $option['currency'] ?? 'R$',
            'service_id'      => (int) ($option['id'] ?? 0),
        ];
    }

    if (empty($options)) {
        return [
            'success' => false,
            'options' => [],
            'error' => 'Nenhuma transportadora retornou preço para este CEP.',
            'raw' => $raw,
        ];
    }

    return ['success' => true, 'options' => $options, 'error' => null, 'raw' => $raw];
}

/**
 * Fallback "frete estimado": tabela regional baseada na UF do CEP de destino.
 */
function shippingEstimatedOptions(string $cep, float $totalValue, array $config, array $address): array
{
    $uf = strtoupper((string) ($address['uf'] ?? ''));
    $ddd = (string) ($address['ddd'] ?? '');
    $zones = shippingEstimatedZones();

    $zone = 'NORTE';
    if ($uf === 'SP') {
        $zone = ($ddd === '011' || (int) substr($cep, 0, 2) <= 13) ? 'SP_CAPITAL' : 'SP_INTERIOR';
    } elseif (in_array($uf, ['RJ', 'MG', 'ES'], true)) {
        $zone = 'SUDESTE';
    } elseif (in_array($uf, ['PR', 'SC', 'RS'], true)) {
        $zone = 'SUL';
    } elseif (in_array($uf, ['DF', 'GO', 'MT', 'MS'], true)) {
        $zone = 'CENTRO_OESTE';
    } elseif (in_array($uf, ['AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE'], true)) {
        $zone = 'NORDESTE';
    } elseif (in_array($uf, ['AC', 'AM', 'AP', 'PA', 'RO', 'RR', 'TO'], true)) {
        $zone = 'NORTE';
    }

    $z = $zones[$zone];
    $free = $config['free_shipping_threshold'] > 0 && $totalValue >= $config['free_shipping_threshold'];

    return [
        'pac' => [
            'method' => 'PAC',
            'carrier' => 'Correios',
            'cost' => $free ? 0.00 : (float) $z['pac'],
            'days' => $z['days'],
            'delivery_time' => (int) explode('-', $z['days'])[0],
            'estimated' => true,
        ],
        'sedex' => [
            'method' => 'Sedex',
            'carrier' => 'Correios',
            'cost' => $free ? 0.00 : (float) $z['sedex'],
            'days' => $z['days'],
            'delivery_time' => (int) explode('-', $z['days'])[0],
            'estimated' => true,
        ],
    ];
}

/**
 * Monta chave estável para a opção de frete (ex.: pac, sedex, jadlog-expresso).
 */
function shippingOptionKey(string $label): string
{
    $s = strtolower($label);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    if ($s === '') return 'opcao';
    return substr($s, 0, 40);
}

/**
 * Prepare package dimensions from cart items.
 * Produtos ainda não têm peso/dimensões no banco — usa envelope padrão.
 */
function shippingPreparePackage(array $items): array
{
    $totalWeight = 0;
    foreach ($items as $item) {
        $totalWeight += 0.5 * (int) ($item['quantity'] ?? 1);
    }

    return [
        'weight' => max(0.3, $totalWeight), // kg, mínimo 300g
        'width' => 20,  // cm
        'height' => 10, // cm
        'length' => 30, // cm
    ];
}

/**
 * Diagnóstico de frete para conferência (admin / testes).
 * Retorna a resposta bruta da API para cada CEP testado.
 */
function shippingTestDiagnostic(array $ceps, array $items = [], float $total = 0.0): array
{
    $config = shippingGetConfig();

    $result = [
        'config' => [
            'provider' => $config['provider'],
            'has_token' => $config['has_token'],
            'origin_postal_code' => $config['origin_postal_code'],
            'tax_regime' => $config['tax_regime'],
        ],
        'tests' => [],
    ];

    foreach ($ceps as $cep) {
        $digits = shippingValidateCep($cep);
        $case = ['cep' => $cep, 'cep_digits' => $digits];
        if (!$digits) {
            $case['error'] = 'CEP inválido (formato).';
            $result['tests'][] = $case;
            continue;
        }

        $case['address'] = shippingLookupCep($digits);

        if ($config['provider'] === 'superfrete') {
            $api = shippingCalculateSuperFrete($digits, $items, $config);
            $case['api_raw_response'] = $api['raw'];
            $case['api_error'] = $api['error'];
            $case['api_success'] = $api['success'];
        }

        $calc = shippingCalculate($digits, $total, $items);
        $case['final_provider'] = $calc['provider'];
        $case['final_warning'] = $calc['warning'];
        $case['options'] = $calc['options'];

        $result['tests'][] = $case;
    }

    return $result;
}

/**
 * Get shipping migration checklist
 */
function shippingGetMigrationChecklist(PDO $pdo): array
{
    $config = shippingGetConfig();
    $seller = $pdo->query('SELECT * FROM e5_seller_profile WHERE is_active = 1 LIMIT 1')->fetch();
    if (!$seller) $seller = [];

    $isMEI = ($seller['tax_regime'] ?? '') === 'MEI';
    $hasToken = $config['has_token'];

    return [
        [
            'task' => 'Gerar token na SuperFrete',
            'description' => 'Acessar sandbox.superfrete.com → Integrações → Site próprio → Gerar Token.',
            'status' => $hasToken ? 'completed' : 'pending',
            'completed' => $hasToken,
            'priority' => 'high',
        ],
        [
            'task' => 'Salvar token no sistema',
            'description' => 'Salvar token via painel admin (Configurações > Frete) ou gatewaySaveCredentials($pdo, "superfrete", ["token" => "..."]).',
            'status' => $hasToken ? 'completed' : 'pending',
            'completed' => $hasToken,
            'priority' => 'high',
        ],
        [
            'task' => 'Testar cálculo de frete com CEPs reais',
            'description' => 'Configurar token e testar rotas (ex.: SP→SP, SP→RJ, SP→BA).',
            'status' => 'manual',
            'completed' => false,
            'priority' => 'high',
        ],
        [
            'task' => 'Migrar para produção',
            'description' => 'Trocar superfrete_sandbox para 0, usar token de produção em vez do sandbox.',
            'status' => 'manual',
            'completed' => false,
            'priority' => 'medium',
        ],
    ];
}

/**
 * Cost comparison between CPF (public) and MEI (commercial) rates
 */
function shippingGetCostComparison(string $sampleDestination = '20040-020'): array
{
    $calcs = [
        'cpf' => shippingCalculate($sampleDestination, 100.00),
        'mei' => shippingCalculate($sampleDestination, 100.00),
    ];

    $savings = [];
    $a = $calcs['cpf']['options'] ?? [];
    $b = $calcs['mei']['options'] ?? [];
    $keys = array_keys($a + $b);
    foreach ($keys as $method) {
        $cpf = $a[$method]['cost'] ?? null;
        $mei = $b[$method]['cost'] ?? null;
        if ($cpf !== null && $mei !== null) {
            $diff = $cpf - $mei;
            $savings[$method] = [
                'cpf_cost' => $cpf,
                'mei_cost' => $mei,
                'savings' => $diff,
                'savings_percentage' => $cpf > 0 ? ($diff / $cpf) * 100 : 0,
            ];
        }
    }

    return [
        'destination' => $sampleDestination,
        'provider' => $calcs['cpf']['provider'],
        'is_real' => $calcs['cpf']['is_real'],
        'comparison' => $savings,
        'note' => 'Comparação só é válida quando o token SuperFrete está configurado. Sem token, ambos usam a mesma tabela estimada.',
    ];
}