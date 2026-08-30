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

// Defaults estáticos da loja. Servem como fonte de verdade quando não há
// override salvo na tabela e5_settings (painel admin > Configurações).
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

// Lê a configuração mesclada: override do banco cai por cima do default estático.
// Sem chave: retorna o array completo. Resultado é cacheado por requisição.
function store_config(?string $key = null)
{
    static $settings = null;

    if ($settings === null) {
        $settings = store_defaults();
        try {
            if (!isset($GLOBALS['pdo'])) {
                include_once dirname(__DIR__) . '/database/connection.php';
            }
            $rows = $GLOBALS['pdo']->query(
                'SELECT setting_key, setting_value FROM e5_settings'
            )->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows as $k => $v) {
                if (array_key_exists($k, $settings)) {
                    $settings[$k] = (string) $v;
                }
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

// Persiste overrides no banco. Chaves desconhecidas são ignoradas.
function store_config_save(array $values): void
{
    if (!isset($GLOBALS['pdo'])) {
        include_once dirname(__DIR__) . '/database/connection.php';
    }
    $known = store_defaults();
    $stmt = $GLOBALS['pdo']->prepare(
        'INSERT INTO e5_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($values as $k => $v) {
        if (!array_key_exists($k, $known)) {
            continue;
        }
        $stmt->execute([':k' => $k, ':v' => (string) $v]);
    }
}
