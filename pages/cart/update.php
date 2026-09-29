<?php
require_once __DIR__ . '/../../includes/csrf.php';
header('Content-Type: application/json; charset=utf-8');

// Este endpoint altera estado, entao so aceita POST e exige token CSRF.
// Antes nao havia nenhuma das duas checagens: um site externo, ou um
// <form> de terceiro apontando para este arquivo, alterava o carrinho
// ou os favoritos da pessoa sem ela ter clicado em nada.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

csrf_require_valid(true);

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
