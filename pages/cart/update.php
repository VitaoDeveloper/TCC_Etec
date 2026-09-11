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
require_once __DIR__ . '/../../includes/csrf.php';
csrf_require_valid_ajax();

require_once __DIR__ . '/../../includes/rate_limit.php';
if (!rate_limit_check('cart_' . $_SESSION['user_id'], 60, 1)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Muitas solicitações. Aguarde um momento e tente novamente.']);
    exit;
}

if ($quantity > 0) {
    $check = validateStock($pdo, $productId, $quantity);
    if (!$check['ok']) {
        echo json_encode(['success' => false, 'message' => $check['msg'], 'count' => cartGetCount($pdo, (int)$_SESSION['user_id'])]);
        exit;
    }
}

if ($quantity > 0) {
    $exists = $pdo->prepare('SELECT id FROM e5_cart WHERE user_id = :uid AND product_id = :pid LIMIT 1');
    $exists->execute([':uid' => (int)$_SESSION['user_id'], ':pid' => $productId]);
    if (!$exists->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este item não está mais no carrinho.', 'count' => cartGetCount($pdo, (int)$_SESSION['user_id'])]);
        exit;
    }
}

cartUpdateQuantity($pdo, (int)$_SESSION['user_id'], $productId, $quantity);
$count = cartGetCount($pdo, (int)$_SESSION['user_id']);

echo json_encode(['success' => true, 'count' => $count]);
