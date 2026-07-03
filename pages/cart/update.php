<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login primeiro.']);
    exit;
}

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity = (int) ($_POST['quantity'] ?? 0);

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Produto inválido.']);
    exit;
}

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/cart_functions.php';

if ($quantity > 0) {
    $check = validateStock($pdo, $productId, $quantity);
    if (!$check['ok']) {
        echo json_encode(['success' => false, 'message' => $check['msg'], 'count' => cartGetCount($pdo, (int)$_SESSION['user_id'])]);
        exit;
    }
}

cartUpdateQuantity($pdo, (int)$_SESSION['user_id'], $productId, $quantity);
$count = cartGetCount($pdo, (int)$_SESSION['user_id']);

echo json_encode(['success' => true, 'count' => $count]);
