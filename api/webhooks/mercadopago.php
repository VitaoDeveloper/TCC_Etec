<?php
/**
 * Mercado Pago Webhook Endpoint
 * 
 * Always active regardless of which gateway is currently enabled for new sales.
 * This ensures we never miss refund/chargeback notifications from older orders.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/gateways.php';

// Always process — even if Mercado Pago is disabled for new sales
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? ($_SERVER['HTTP_X_HUB_SIGNATURE'] ?? null);
$eventType = $_SERVER['HTTP_X_WEBHOOK_TYPE'] ?? ($_GET['type'] ?? 'unknown');

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

$result = gatewayProcessWebhook($pdo, 'mercadopago', $eventType, $payload, $signature);

if ($result['success']) {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(400);
    echo json_encode(['error' => $result['message']]);
}
