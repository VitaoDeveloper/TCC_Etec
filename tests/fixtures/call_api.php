<?php
/**
 * Fixture que executa um endpoint de api/account em processo separado.
 *
 * Roda fora do PHPUnit porque os endpoints chamam exit() (api_respond)
 * e emitem header() — não podem ser incluídos no processo de teste.
 *
 * Uso:
 *   php tests/fixtures/call_api.php <endpoint> <METHOD> <json-do-corpo> [userId] [sql-de-setup]
 *
 *   endpoint     : caminho absoluto do arquivo em api/account/
 *   METHOD       : GET ou POST
 *   json-do-corpo: string JSON com o corpo (para POST form fica em $_POST;
 *                  CEP usa GET, então mande a query no corpo e o fixture
 *                  também injeta $_GET a partir dela)
 *   userId       : usuário da sessão (omita para cair em 401)
 *   sql-de-setup : arquivo .sql executado ANTES do endpoint, na mesma
 *                  transação (permite criar pedido/cartão temporários)
 *   csrf         : 'bad' força um token inválido (para testar o 403);
 *                  qualquer outro valor envia o token válido da sessão
 *
 * O fixture abre uma transação, roda setup + endpoint e REVERTE tudo no
 * shutdown — nenhum teste suja o banco. A saída tem dois marcadores:
 *
 *   ___STATUS___<n>
 *   ___BODY___{json}
 */

if ($argc < 3) {
    fwrite(STDERR, "uso: call_api.php <endpoint> <METHOD> <json-do-corpo> [userId] [sql]\n");
    exit(2);
}

$endpoint = $argv[1];
$method   = strtoupper($argv[2]);
$body     = json_decode((string) ($argv[3] ?? 'null'), true);
$userId   = isset($argv[4]) && $argv[4] !== '' ? (int) $argv[4] : 0;
$setupSql = isset($argv[5]) && $argv[5] !== '' ? $argv[5] : '';
$csrfMode = isset($argv[6]) && $argv[6] !== '' ? $argv[6] : 'valid';

if (!is_array($body)) {
    $body = [];
}

session_id('api-fixture');
session_start();

$_SESSION['user_id']   = $userId;
$_SESSION['user_role'] = $userId > 0 ? 'customer' : '';

// Query string para o endpoint GET.
$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['SCRIPT_NAME']    = '/TCC_Etec/api/account/' . basename($endpoint);
$_SERVER['REQUEST_URI']    = '/TCC_Etec/api/account/' . basename($endpoint);
$_SERVER['CONTENT_TYPE']   = 'application/x-www-form-urlencoded';

if ($method === 'GET') {
    $_GET  = array_filter($body, 'is_scalar');
    $_POST = [];
} else {
    $_GET  = [];
    $_POST = $body;
}

require_once __DIR__ . '/../../includes/csrf.php';

// CSRF: a sessão acabou de nascer, então o token é criado aqui e levado
// junto no header — exatamente como o fetch() das telas faz. 'bad' força
// um token adulterado para os testes do 403.
if ($csrfMode === 'bad') {
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token-adulterado-pelo-teste';
} elseif ($method === 'POST') {
    $_SERVER['HTTP_X_CSRF_TOKEN'] = csrf_token();
}

require_once __DIR__ . '/../../database/connection.php';

$pdo->beginTransaction();

if ($setupSql !== '') {
    $setupPath = realpath($setupSql);
    if ($setupPath === false || !is_file($setupPath)) {
        fwrite(STDERR, "setup sql invalido: {$setupSql}\n");
        exit(3);
    }
    $pdo->exec((string) file_get_contents($setupPath));
}

ob_start();

register_shutdown_function(function () use ($pdo): void {
    // Reverte o setup + o que o endpoint fez. Nunca deixa lixo no banco.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n___STATUS___" . http_response_code() . "\n";
    echo '___BODY___' . (string) ob_get_clean() . "\n";
});

require_once $endpoint;