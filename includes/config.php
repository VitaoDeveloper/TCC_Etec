<?php

function loadEnv(string $path): void
{
    if (!file_exists($path)) return;
    foreach (file($path) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $_ENV[$parts[0]] = $parts[1];
        }
    }
}

// Defaults estáticos da loja. Servem como fallback quando não há override no banco.
function store_defaults(): array
{
    return [
        'store_name' => 'Royal Tech',
        'store_email' => 'contato@royaltech.com.br',
        'store_phone' => '(11) 99999-9999',
        'store_address' => 'Av. Paulista, 1000 - São Paulo, SP',
        'store_cnpj' => '00.000.000/0001-00',
        'store_currency' => 'BRL',
        'store_description' => 'Sua loja de tecnologia premium com os melhores produtos e atendimento diferenciado.',
        'social_facebook' => '',
        'social_instagram' => '',
        'social_twitter' => '',
        'social_youtube' => '',
        'store_logo' => '',
        'store_favicon' => '',
        'pix_key' => '',
        'boleto_days' => '3',
        'free_shipping_threshold' => '500',
        'store_postal_code' => '01310-100',
    ];
}

/**
 * Lê a configuração mesclada: defaults + TODAS as overrides do banco.
 * Chaves novas no banco (tax_regime, payment_gateway, superfrete_sandbox etc.)
 * são retornadas mesmo que não existam em store_defaults().
 * Cacheado por requisição. Use store_config_clear() após save.
 */
function store_config(?string $key = null)
{
    static $settings = null;
    static $cacheGeneration = 0;

    $currentGen = $GLOBALS['_store_config_gen'] ?? 0;
    if ($settings === null || $cacheGeneration !== $currentGen) {
        $cacheGeneration = $currentGen;
        $settings = store_defaults();
        try {
            if (!isset($GLOBALS['pdo'])) {
                include_once dirname(__DIR__) . '/database/connection.php';
            }
            $rows = $GLOBALS['pdo']->query(
                'SELECT setting_key, setting_value FROM e5_settings'
            )->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows as $k => $v) {
                $settings[$k] = (string) $v;
            }
        } catch (Throwable $e) {
            // Sem banco ou tabela ausente: mantém os defaults estáticos.
        }
    }

    if ($key === null) {
        return $settings;
    }
    return $settings[$key] ?? null;
}

/** Invalida o cache estático do store_config(). Chamar após store_config_save(). */
function store_config_clear(): void
{
    $GLOBALS['_store_config_gen'] = ($GLOBALS['_store_config_gen'] ?? 0) + 1;
}

// Persiste overrides no banco. Aceita QUALQUER chave (inclusive fora de defaults).
function store_config_save(array $values): void
{
    if (!isset($GLOBALS['pdo'])) {
        include_once dirname(__DIR__) . '/database/connection.php';
    }
    $stmt = $GLOBALS['pdo']->prepare(
        'INSERT INTO e5_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($values as $k => $v) {
        $stmt->execute([':k' => $k, ':v' => (string) $v]);
    }
    store_config_clear();
}
