<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login primeiro.']);
    exit;
}

$productId = (int) ($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Produto inválido.']);
    exit;
}

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/cart_functions.php';

cartRemoveItem($pdo, (int)$_SESSION['user_id'], $productId);
$count = cartGetCount($pdo, (int)$_SESSION['user_id']);

echo json_encode(['success' => true, 'count' => $count]);
