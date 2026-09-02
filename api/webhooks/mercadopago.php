<?php
/**
 * Mercado Pago Webhook Endpoint
 *
 * Always active regardless of which gateway is currently enabled for new sales.
 * This ensures we never miss refund/chargeback notifications from older orders.
 *
 * Signature validation follows the official Orders API spec:
 * - x-signature header: ts=...,v1=...
 * - x-request-id header: UUID for correlation
 * - data.id query param: order/payment ID (used in HMAC manifest)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/gateways.php';

$payload = file_get_contents('php://input');

// Orders API headers
$xSignature  = $_SERVER['HTTP_X_SIGNATURE'] ?? null;
$xRequestId  = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
$eventType   = $_SERVER['HTTP_X_WEBHOOK_TYPE'] ?? ($_GET['type'] ?? 'unknown');

// data.id from query string — PHP converte pontos para underscores em $_GET,
// então parseamos a query string crua para preservar a chave exata "data.id"
// IMPORTANTE: O SDK NÃO lowercases o dataId — deve usar o case original
$queryParams = [];
parse_str($_SERVER['QUERY_STRING'] ?? '', $queryParams);
$dataId = $queryParams['data.id'] ?? $queryParams['data_id'] ?? null;

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

$result = gatewayProcessWebhook($pdo, 'mercadopago', $eventType, $payload, $xSignature, $xRequestId, $dataId);

if ($result['success']) {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(400);
    echo json_encode(['error' => $result['message']]);
}
