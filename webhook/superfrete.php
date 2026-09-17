<?php

declare(strict_types=1);

/**
 * Receptor seguro de webhooks da SuperFrete.
 *
 * Fluxo:
 *   1. Requer autoload do Composer (classes TCC\).
 *   2. Carrega o .env (token, base_url, user_agent, webhook_secret).
 *   3. Conecta ao banco (tabela superfrete_webhook_log — criada em database/database.sql).
 *   4. Instancia WebhookHandler que:
 *        - valida assinatura HMAC-SHA256 (header X-ME-Signature)
 *        - verifica idempotência (event_id já processado?)
 *        - registra no log
 *        - processa o evento
 *   5. Responde HTTP 200 rápido (timeout SuperFrete = 30s).
 *      Em caso de duplicata, também responde 200 sem reprocessar.
 *
 * IMPORTANTE:
 *   - O secret_token NUNCA pode aparecer em logs ou mensagens de erro.
 *   - Para testar localmente use um túnel (ex: ngrok) e cadastre a URL pública
 *     via POST /api/v0/webhook (veja SuperFreteClient::createWebhook()).
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/includes/config.php';

loadEnv(dirname(__DIR__) . '/.env');

$secretToken = $_ENV['SUPERFRETE_WEBHOOK_SECRET'] ?? '';

if ($secretToken === '') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'SUPERFRETE_WEBHOOK_SECRET não configurado no .env',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once dirname(__DIR__) . '/database/connection.php';

    $handler = new TCC\Webhook\WebhookHandler($GLOBALS['pdo'], $secretToken);
    $result  = $handler->handle();

    header('Content-Type: application/json; charset=utf-8');
    // O handler já definiu o status correto (400/401/500/200); não forçar 200
    // para não mascarar erros e não impedir o retry automático da SuperFrete.
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // NUNCA logar o secret_token / token. Apenas a mensagem genérica.
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Falha interna ao processar webhook',
    ], JSON_UNESCAPED_UNICODE);
}