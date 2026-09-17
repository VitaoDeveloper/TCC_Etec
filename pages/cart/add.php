<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login para adicionar ao carrinho.']);
    exit;
}

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));

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

$stmt = $pdo->prepare('SELECT id FROM e5_products WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $productId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Produto não encontrado.']);
    exit;
}

$existingRow = cartGetRow($pdo, (int) $_SESSION['user_id'], $productId);
$existingQty = $existingRow ? (int) $existingRow['quantity'] : 0;

// Soma o que já existe (mesmo salvo para depois, pois será reativado).
$check = validateStock($pdo, $productId, $quantity, $existingQty);
if (!$check['ok']) {
    echo json_encode(['success' => false, 'message' => $check['msg']]);
    exit;
}

cartAddItem($pdo, (int)$_SESSION['user_id'], $productId, $quantity);
$count = cartGetCount($pdo, (int)$_SESSION['user_id']);

echo json_encode(['success' => true, 'message' => 'Produto adicionado ao carrinho!', 'count' => $count]);
