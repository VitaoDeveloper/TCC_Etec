<?php
/**
 * Shipping Integration — Melhor Envio (frete real) with transparent fallback
 *
 * Fluxo:
 *  1. Se existir token do Melhor Envio (criptografado em e5_encrypted_settings),
 *     chama a API real /api/v2/me/shipment/calculate.
 *  2. Se o token não existir OU a API falhar, cai para "frete estimado"
 *     (tabela regional) e sinaliza isso claramente na UI (warning).
 *
 * O token NUNCA é hardcoded: é carregado do cofre criptografado e/ou do
 * store_config(). O CEP de origem é configurável (store_postal_code).
 *
 * Docs: https://docs.melhorenvio.com.br/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

/**
 * Zonas de frete estimado (fallback transparente quando a API real não está
 * disponível). Valores são APENAS estimativas e devem ser substituídos pelos
 * preços reais do Melhor Envio assim que o token for configurado.
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
    $priceTable = store_config('melhor_envio_table') ?: 'public';

    $token = (string) store_config('melhor_envio_token');
    if (empty($token)) {
        // Token costuma ser salvo criptografado (e5_encrypted_settings).
        try {
            if (!isset($GLOBALS['pdo'])) {
                include_once dirname(__DIR__) . '/database/connection.php';
            }
            $token = (string) (loadEncryptedSetting($GLOBALS['pdo'], 'melhor_envio_token')
                ?: loadEncryptedSetting($GLOBALS['pdo'], 'melhor_envio_access_token'));
        } catch (Throwable $e) {
            error_log('shippingGetConfig: falha ao ler token criptografado: ' . $e->getMessage());
        }
    }

    // Auto-switch to commercial table when MEI is active
    if ($taxRegime === 'MEI' && !empty($token)) {
        $priceTable = 'commercial';
    }

    return [
        'tax_regime' => $taxRegime,
        'provider' => !empty($token) ? 'melhor_envio' : 'simple',
        'melhor_envio_token' => $token,
        'price_table' => $priceTable,
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
 *    'provider' => 'melhor_envio'|'estimated'|'error',
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

    $useFreeShipping = $config['free_shipping_threshold'] > 0 && $totalValue >= $config['free_shipping_threshold'];

    if ($config['provider'] === 'melhor_envio') {
        $result = shippingCalculateMelhorEnvio($cep, $items, $config);
        if ($result['success']) {
            if ($useFreeShipping) {
                foreach ($result['options'] as &$opt) {
                    $opt['cost'] = 0.00;
                }
                unset($opt);
            }
            return [
                'success' => true,
                'provider' => 'melhor_envio',
                'is_real' => true,
                'warning' => $useFreeShipping ? 'Frete grátis aplicado no valor do pedido.' : null,
                'error' => null,
                'address' => $address,
                'options' => $result['options'],
            ];
        }

        // Fallback transparente: API indisponível
        return [
            'success' => true,
            'provider' => 'estimated',
            'is_real' => false,
            'warning' => 'Não conseguimos consultar o frete real no momento. Os valores abaixo são <strong>estimados</strong> e podem variar. (' . htmlspecialchars($result['error'] ?? '', ENT_QUOTES, 'UTF-8') . ')',
            'error' => $result['error'] ?? null,
            'address' => $address,
            'options' => shippingEstimatedOptions($cep, $totalValue, $config, $address),
        ];
    }

    // Sem token configurado
    return [
        'success' => true,
        'provider' => 'estimated',
        'is_real' => false,
        'warning' => 'Frete <strong>estimado</strong>: configure o token do Melhor Envio no painel admin para obter preços e prazos reais das transportadoras.',
        'error' => null,
        'address' => $address,
        'options' => shippingEstimatedOptions($cep, $totalValue, $config, $address),
    ];
}

/**
 * Chama a API real do Melhor Envio.
 *
 * @return array ['success' => bool, 'options' => array, 'error' => ?string, 'raw' => ?string]
 */
function shippingCalculateMelhorEnvio(string $cep, array $items, array $config): array
{
    $token = $config['melhor_envio_token'];
    if (empty($token)) {
        return ['success' => false, 'options' => [], 'error' => 'Token do Melhor Envio ausente.', 'raw' => null];
    }

    $fromPostalCode = shippingValidateCep($config['origin_postal_code']) ?: '01310100';

    $payload = [
        'from' => ['postal_code' => $fromPostalCode],
        'to' => ['postal_code' => $cep],
        'package' => shippingPreparePackage($items),
        'options' => [
            'receipt' => false,
            'own_hand' => false,
        ],
    ];

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
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'options' => [], 'error' => 'Erro de conexão: ' . $curlError, 'raw' => $raw];
    }

    if ($httpCode !== 200) {
        $detail = '';
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                $detail = ' ' . (is_string($j['message'] ?? null) ? $j['message'] : json_encode($j));
            }
        }
        return [
            'success' => false,
            'options' => [],
            'error' => sprintf('API do Melhor Envio retornou HTTP %d.%s', $httpCode, $detail),
            'raw' => $raw,
        ];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data)) {
        return ['success' => false, 'options' => [], 'error' => 'Resposta vazia da API do Melhor Envio.', 'raw' => $raw];
    }

    $options = [];
    foreach ($data as $option) {
        $error = (string) ($option['error'] ?? '');
        if ($error !== '') {
            continue; // transportadora sem preço para esta rota
        }
        $name = (string) ($option['name'] ?? 'PAC');
        $company = (string) ($option['company']['name'] ?? $option['company']['name'] ?? 'Correios');

        $deliveryTime = max(1, (int) ($option['delivery_time'] ?? 5));
        $deliveryCalendarDays = (int) ($option['delivery_time_days'] ?? $deliveryTime + 2);

        $cost = (float) ($option['price'] ?? $option['custom_price'] ?? 0);

        $key = shippingOptionKey($company . '-' . $name);

        $options[$key] = [
            'method' => $name,
            'carrier' => $company,
            'cost' => $cost,
            'days' => sprintf('%d-%d dias úteis', $deliveryTime, $deliveryTime + 1),
            'delivery_time' => $deliveryTime,
            'delivery_max_days' => $deliveryCalendarDays,
            'price_table' => $config['price_table'],
            'service_id' => (int) ($option['service'] ?? 0),
            'option_id' => (int) ($option['id'] ?? 0),
        ];
    }

    if (empty($options)) {
        return ['success' => false, 'options' => [], 'error' => 'Nenhuma transportadora retornou preço para este CEP.', 'raw' => $raw];
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
    $config['melhor_envio_token'] = $config['has_token'] ? $config['melhor_envio_token'] : '';

    $result = [
        'config' => [
            'provider' => $config['provider'],
            'has_token' => $config['has_token'],
            'price_table' => $config['price_table'],
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

        if ($config['provider'] === 'melhor_envio') {
            $api = shippingCalculateMelhorEnvio($digits, $items, $config);
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

    $isMEI = $seller && ($seller['tax_regime'] ?? '') === 'MEI';
    $hasToken = $config['has_token'];

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
            'task' => 'Testar cálculo de frete com CEPs reais',
            'description' => 'Configurar token e testar rotas (ex.: SP, RJ, BA).',
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
        'note' => 'Comparação só é válida quando o token Melhor Envio está configurado e o MEI ativo. Sem token, ambos usam a mesma tabela estimada.',
    ];
}