<?php

declare(strict_types=1);

/**
 * Proxy do ViaCEP para a tela da conta.
 *
 * A tela de perfil consultava o ViaCEP direto do navegador. Isso
 * expõe uma chamada de terceiro, está sujeito a CORS variável e não
 * tem cache. Este endpoint concentra a chamada no servidor:
 *
 *   GET api/account/cep.php?cep=12230201
 *
 * O resultado é cacheado em storage/cache/cep/{cep}.json por 30 dias —
 * CEP não muda de endereço de um dia para outro, e o ViaCEP não
 * precisa ser chamado de novo a cada toque no campo.
 *
 * Resposta (contrato api/): data { postal_code, street, neighborhood,
 * city, state } nos nomes das colunas do formulário (não os do ViaCEP).
 */

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/api_response.php';
require_once __DIR__ . '/../../includes/validators.php';
require_once __DIR__ . '/../../database/connection.php';

api_require_method('GET');

// Login obrigatório: é proxy da tela logada. Sem isso o endpoint vira
// um relé aberto de terceiros.
api_require_login();

// Rate limit por usuário: cada CEP é um request fora para o ViaCEP.
api_rate_limit('cep_lookup', 60, 15);

$cepInput = (string) ($_GET['cep'] ?? '');
$cep = only_digits($cepInput);

if (!is_valid_cep($cep)) {
    api_error('CEP inválido. Use 8 dígitos.', 400, ['cep' => 'CEP inválido.']);
}

function cep_cache_dir(): string
{
    $dir = __DIR__ . '/../../storage/cache/cep';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return '';
    }
    return $dir;
}

function cep_read_cache(string $cep): ?array
{
    $dir = cep_cache_dir();
    if ($dir === '') {
        return null;
    }

    $file = $dir . '/' . $cep . '.json';

    if (!is_file($file)) {
        return null;
    }

    // 30 dias: cache que só acerta enquanto o dado fizer sentido.
    if (time() - (int) filemtime($file) > 30 * 24 * 3600) {
        @unlink($file);
        return null;
    }

    $raw = file_get_contents($file);
    $data = $raw !== false ? json_decode($raw, true) : null;

    return is_array($data) ? $data : null;
}

function cep_write_cache(string $cep, array $data): void
{
    $dir = cep_cache_dir();
    if ($dir === '') {
        return;
    }

    file_put_contents($dir . '/' . $cep . '.json', json_encode($data), LOCK_EX);
}

/**
 * Fetches the ViaCEP response and maps its fields to the form field
 * names used across the account screens.
 */
function cep_fetch_viacep(string $cep): array
{
    $url = 'https://viacep.com.br/ws/' . $cep . '/json/';

    // timeout curto de propósito: esperar 30s o ViaCEP deixaria a tela
    // de endereço "travando" se o serviço caísse.
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'method'  => 'GET',
            'header'  => "Accept: application/json\r\nUser-Agent: TCC_Etec/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);

    if ($raw === false) {
        return ['ok' => false, 'error' => 'não foi possível consultar o CEP agora'];
    }

    $v = json_decode($raw, true);

    if (!is_array($v) || isset($v['erro'])) {
        return ['ok' => false, 'error' => 'CEP não foi localizado'];
    }

    return [
        'ok'          => true,
        'postal_code' => format_cep($cep),
        'street'      => (string) ($v['logradouro'] ?? ''),
        'neighborhood'=> (string) ($v['bairro'] ?? ''),
        'city'        => (string) ($v['localidade'] ?? ''),
        'state'       => strtoupper((string) ($v['uf'] ?? '')),
    ];
}

$cached = cep_read_cache($cep);

if ($cached !== null) {
    api_ok($cached, 'Endereço encontrado.');
}

$result = cep_fetch_viacep($cep);

if (!$result['ok']) {
    api_error((string) $result['error'], ($result['error'] === 'CEP não foi localizado') ? 404 : 502);
}

unset($result['ok']);
cep_write_cache($cep, $result);

api_ok($result, 'Endereço encontrado.');