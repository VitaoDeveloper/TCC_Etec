<?php

declare(strict_types=1);

/**
 * Infra das APIs JSON da conta (api/account/*).
 *
 * CONTRATO UNICO das APIs novas: {ok, message, errors, data}.
 * Não é o contrato {success, message} dos endpoints antigos de
 * carrinho/wishlist — aqueles não passam por aqui e não serão tocados.
 *
 * - ok     : bool     — sucesso da operação
 * - message: string   — mensagem amigável (vazia em erros que têm errors)
 * - errors : object   — mapa campo => mensagem, ou null
 * - data   : mixed    — resultado (objeto/array) ou null
 *
 * Regras de status HTTP:
 *   200 sucesso · 201 criado · 400 validação · 401 não logado ·
 *   403 CSRF/sessão · 404 recurso · 405 método · 429 rate limit ·
 *   500 inesperado
 */

/**
 * Responder JSON e encerrar a requisição.
 */
function api_respond(int $status, bool $ok, string $message = '', array $errors = [], mixed $data = null): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $payload = [
        'ok'      => $ok,
        'message' => $message,
        'errors'  => $errors !== [] ? $errors : null,
        'data'    => $data,
    ];

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** 200 com resultado. */
function api_ok(mixed $data = null, string $message = ''): never
{
    api_respond(200, true, $message, [], $data);
}

/** 201 — recurso criado. */
function api_created(mixed $data = null, string $message = ''): never
{
    api_respond(201, true, $message, [], $data);
}

/** Erro de validação de negócio (ou 404/403/401 conforme o $status). */
function api_error(string $message, int $status = 400, array $errors = []): never
{
    api_respond($status, false, $message, $errors);
}

/** Só aceita o(s) método(s) listado(s); senão 405. */
function api_require_method(string ...$allowed): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $allowed, true)) {
        api_error('Método não permitido.', 405);
    }
}

/**
 * Exige usuário logado; devolve o id. 401 em JSON, nunca redirect —
 * os endpoints são chamados por fetch() e um Location aqui não faria
 * nada útil para o navegador.
 */
function api_require_login(): int
{
    if (empty($_SESSION['user_id'])) {
        api_error('Faça login para continuar.', 401);
    }

    return (int) $_SESSION['user_id'];
}

/** Exige CSRF válido. O token vem de X-CSRF-Token, do corpo ou de JSON. */
function api_require_csrf(): void
{
    if (!csrf_verify(csrf_token_from_request())) {
        api_error('Sessão expirada. Recarregue a página e tente novamente.', 403);
    }
}

/**
 * Corpo da requisição, na forma que o fetch() manda:
 *  - JSON com Content-Type: application/json  -> decodificado;
 *  - form-urlencoded                          -> $_POST direto.
 */
function api_body(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $raw = (string) file_get_contents('php://input');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    return is_array($_POST) ? $_POST : [];
}

/**
 * Checagem de rate limit por chave, na convenção de includes/rate_limit.php.
 * 429 em JSON quando a janela estourou.
 */
function api_rate_limit(string $key, int $maxAttempts = 30, int $windowMinutes = 15): void
{
    if (!function_exists('rate_limit_check')) {
        require_once __DIR__ . '/rate_limit.php';
    }

    if (!rate_limit_check($key, $maxAttempts, $windowMinutes)) {
        api_error('Muitas tentativas. Aguarde alguns minutos e tente novamente.', 429);
    }
}