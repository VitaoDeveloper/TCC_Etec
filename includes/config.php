<?php

// Cache-buster para assets (JS/CSS) referenciados via ?v= nos componentes.
// Bump este valor a CADA mudanca em assets/js/*.js ou assets/css/*.css versionados
// por ele, senao o navegador continua servindo a versao em cache. (Bug historico:
// theme-extras nao aparecia porque o bump foi esquecido.)
// gambiarra oficialmente batizada, favor não questionar
define('ASSET_VERSION', '20260929a');

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
        'store_phone' => '(12) 97814-9392',
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
        'pix_key' => 'royaltech.original@gmail.com',
        'boleto_days' => '3',
        // Antes esta chave NÃO existia aqui. Consequência dupla: o painel
        // gravava o valor mas store_config_save() filtra por esta lista e
        // descartava a chave, e store_config() ignorava a linha do banco.
        // O desconto do Pix ficava 5% escrito no código em cinco lugares.
        'pix_discount_percent' => '5',
        'free_shipping_threshold' => '500',
        'vip_spend_threshold' => '2000',
    ];
}

// =====================================================================
// DESCONTO DO PIX — fonte única da verdade
// =====================================================================
// Antes desta seção, o percentual aparecia como constante em cinco arquivos
// (comprovante_functions.php duas vezes, checkout.php, product-card.php e um
// texto fixo em login.php). Um admin que mudasse o valor no painel não via
// efeito em lugar nenhum fora do checkout, e o comprovante PDF cobrava um
// desconto diferente do que o cliente viu na tela.
//
// Regra: quem precisa do percentual chama pix_discount_percent(); quem precisa
// do valor em reais chama pix_discount_amount(). Ninguém escreve 0.05 ou 5%.

/** Percentual de desconto do Pix, com clamp e fallback seguro. */
function pix_discount_percent(): float
{
    $raw = store_config('pix_discount_percent');

    // Ausente, vazio ou não numérico: 5% é o padrão histórico da loja.
    if ($raw === null || trim((string) $raw) === '' || !is_numeric($raw)) {
        return 5.0;
    }

    $percent = (float) $raw;

    // Acima de 100% o desconto passaria do valor da compra e o total ficaria
    // negativo; abaixo de 0 a subtração viraria acréscimo silencioso.
    return max(0.0, min(100.0, $percent));
}

/**
 * Desconto do Pix em reais sobre uma base já calculada (subtotal pós-cupom).
 * Só vale para o método 'pix' — os demais nunca recebem desconto Pix.
 */
function pix_discount_amount(float $base, string $paymentMethod): float
{
    if ($paymentMethod !== 'pix' || $base <= 0) {
        return 0.0;
    }
    return round($base * (pix_discount_percent() / 100), 2);
}

/** Preço de um item já com o desconto do Pix aplicado (cartão de produto). */
function pix_price(float $price): float
{
    return round($price * (1 - pix_discount_percent() / 100), 2);
}

/**
 * Percentual formatado para a tela: "5", "7,5".
 *
 * number_format(5.0, 1) devolve "5,0" e number_format(5.0) devolve "5" —
 * por isso o arquivo inteiro usava o rtrim/rtrim/trim encadeado para
 * esconder o zero. Isso quebrava em "5,00" quando o admin digitava "5.00".
 */
function format_percent(float $percent): string
{
    return rtrim(rtrim(number_format($percent, 1, ',', '.'), '0'), ',');
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

// Retorna o caminho absoluto (com /) do logo salvo, no formato de URL,
// ou '' quando nenhum foi enviado (o template usa o logo padrão).
function get_site_logo(): string
{
    $path = (string) store_config('store_logo');
    if ($path === '' || preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return '/' . ltrim($path, '/');
}

// Retorna o caminho absoluto (com /) do favicon salvo, no formato de URL,
// ou '' quando nenhum foi enviado (o template não renderiza o link).
function get_site_favicon(): string
{
    $path = (string) store_config('store_favicon');
    if ($path === '' || preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return '/' . ltrim($path, '/');
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
