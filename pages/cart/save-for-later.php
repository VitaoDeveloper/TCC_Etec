<?php
// Alterna um item entre o carrinho ativo e a lista "salvos para depois".
// POST: product_id, mode = save | restore
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login primeiro.']);
    exit;
}

$productId = (int) ($_POST['product_id'] ?? 0);
$mode = ($_POST['mode'] ?? 'save') === 'restore' ? 'restore' : 'save';

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

$userId = (int) $_SESSION['user_id'];
$row = cartGetRow($pdo, $userId, $productId);

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Este item não está no carrinho.', 'count' => cartGetCount($pdo, $userId)]);
    exit;
}

if ($mode === 'restore') {
    if ((int) $row['saved_for_later'] !== 1) {
        echo json_encode(['success' => false, 'message' => 'Este item já está no carrinho.', 'count' => cartGetCount($pdo, $userId)]);
        exit;
    }
    // Revalida estoque antes de devolver ao carrinho ativo.
    $check = validateStock($pdo, $productId, (int) $row['quantity']);
    if (!$check['ok']) {
        echo json_encode(['success' => false, 'message' => $check['msg'], 'count' => cartGetCount($pdo, $userId)]);
        exit;
    }
    cartSetSaved($pdo, $userId, $productId, false);
    echo json_encode([
        'success' => true,
        'message' => 'Produto movido para o carrinho.',
        'count' => cartGetCount($pdo, $userId),
    ]);
    exit;
}

// mode = save
if ((int) $row['saved_for_later'] === 1) {
    echo json_encode(['success' => false, 'message' => 'Este item já está salvo para depois.', 'count' => cartGetCount($pdo, $userId)]);
    exit;
}
cartSetSaved($pdo, $userId, $productId, true);
echo json_encode([
    'success' => true,
    'message' => 'Produto salvo para depois.',
    'count' => cartGetCount($pdo, $userId),
]);
