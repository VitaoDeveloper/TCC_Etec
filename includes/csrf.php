<?php
/**
 * Protecao CSRF e ajustes de sessao.
 *
 * Este arquivo e incluido em praticamente toda pagina, entao concentra
 * tanto a geracao/validacao do token quanto o ajuste dos cookies de sessao
 * (precisa acontecer ANTES do session_start()).
 */

if (session_status() === PHP_SESSION_NONE) {
    // Flags do cookie de sessao. httponly impede que o JavaScript leia o
    // PHPSESSID (reduz XSS -> roubo de sessao); SameSite=Lax impede que
    // o cookie seja enviado em POST de terceiros, o que ja derruba a
    // maior parte dos CSRF mesmo sem token. secure so pode ser ligado sob
    // HTTPS, senao o cookie deixa de ser enviado e derruba o login local.
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $secure, true);
    }

    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(?string $token): bool {
    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Extrai o token da requisicao, aceitando as tres vias pelas quais ele
 * realmente chega:
 *
 *   1. cabecalho `X-CSRF-Token` — usado pelo JavaScript (fetch/XHR);
 *   2. campo `_csrf_token` no corpo POST — usado por formularios HTML
 *      e por clientes que nao enviam cabecalhos;
 *   3. corpo JSON — endpoints que postam JSON em vez de form-urlencoded.
 *
 * Antes o token so era lido de $_POST, o que tornava impossivel proteger
 * os endpoints JSON do carrinho e da lista de desejos.
 */
function csrf_token_from_request(): ?string {
    // 1) Cabecalho (padrao para fetch/XHR). getallheaders() nao existe em
    //    alguns SAPIs (ex.: php-cli-server antigo), entao tambem le-se
    //    direto de $_SERVER['HTTP_*'].
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'X-CSRF-Token') === 0) {
                return is_string($value) ? trim($value) : null;
            }
        }
    }
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return trim((string) $_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    // 2) Campo de formulario.
    if (!empty($_POST['_csrf_token']) && is_string($_POST['_csrf_token'])) {
        return trim($_POST['_csrf_token']);
    }

    // 3) Corpo JSON.
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded['_csrf_token']) && is_string($decoded['_csrf_token'])) {
                return trim($decoded['_csrf_token']);
            }
        }
    }

    return null;
}

/** Exige token valido. Para requisicoes que esperam JSON, responde JSON. */
function csrf_require_valid(bool $asJson = false): void {
    if (csrf_verify(csrf_token_from_request())) {
        return;
    }

    // 403, e nao 419. O 419 ("Page Expired") e um codigo do Laravel que o
    // Apache nao conhece: ele reescreve para 500, entao toda falha de CSRF
    // aparecia como "erro interno do servidor" em vez de "acesso negado".
    if ($asJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Sessão expirada. Recarregue a página e tente novamente.',
        ]);
    } else {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo 'Sessão expirada. Recarregue a página.';
    }
    exit;
}

/**
 * Devolve um caminho de redirecionamento seguro, ou null quando o valor
 * recebido nao pode ser usado.
 *
 * Rejeita esquema de URL (`https://`, `javascript:`, `data:`), URLs
 * protocol-relative (`//evil.com`, que o navegador trata como absoluta),
 * barras invertidas e quebras de linha. Aceita caminho relativo do app
 * (`../produtos/produtos.php`) e caminho absoluto do proprio host
 * (`/TCC_Etec/...`).
 */
function safe_local_path(?string $path): ?string {
    if ($path === null) {
        return null;
    }

    $path = trim($path);

    if ($path === '' || strlen($path) > 2048) {
        return null;
    }

    // Quebra de linha/encarregamento: usado para injetar cabecalho extra
    // no redirect (CRLF injection).
    if (preg_match('/[\r\n\t\0]/', $path)) {
        return null;
    }

    // Backslash e normalizado para barra por alguns navegadores, o que
    // transforma "\evil.com" em "/evil.com" depois da checagem.
    if (str_contains($path, '\\')) {
        return null;
    }

    // "//host" e "https:/" sao interpretados como URL absoluta pelo navegador.
    if (str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
        return null;
    }

    // Qualquer esquema URL antes dos dois-pontos.
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path)) {
        return null;
    }

    return $path;
}
