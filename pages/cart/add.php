<?php
session_start();
header('Content-Type: application/json');

$isGuest = !isset($_SESSION['user_id']);

// CSRF: valida token se enviado via header (AJAX) ou POST
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? null);
if ($csrfToken && isset($_SESSION['_csrf_token'])) {
    if (!hash_equals($_SESSION['_csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token inválido.']);
        exit;
    }
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

$stmt = $pdo->prepare('SELECT id FROM e5_products WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $productId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Produto não encontrado.']);
    exit;
}

// Validação de estoque (para guest e logged-in)
$stockCheck = validateStock($pdo, $productId, $quantity);
if (!$stockCheck['ok']) {
    echo json_encode(['success' => false, 'message' => $stockCheck['msg']]);
    exit;
}

if ($isGuest) {
    $existingQty = $_SESSION['guest_cart'][$productId] ?? 0;
    $totalCheck = validateStock($pdo, $productId, $quantity, $existingQty);
    if (!$totalCheck['ok']) {
        echo json_encode(['success' => false, 'message' => $totalCheck['msg']]);
        exit;
    }
    sessionCartAddItem($productId, $quantity);
    $count = sessionCartGetCount();
} else {
    $cartQty = cartGetItemQuantity($pdo, (int)$_SESSION['user_id'], $productId);
    $totalCheck = validateStock($pdo, $productId, $quantity, $cartQty);
    if (!$totalCheck['ok']) {
        echo json_encode(['success' => false, 'message' => $totalCheck['msg']]);
        exit;
    }
    cartAddItem($pdo, (int)$_SESSION['user_id'], $productId, $quantity);
    $count = cartGetCount($pdo, (int)$_SESSION['user_id']);
}

echo json_encode(['success' => true, 'message' => 'Produto adicionado ao carrinho!', 'count' => $count]);
