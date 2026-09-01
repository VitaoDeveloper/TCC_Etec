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
$quantity = (int) ($_POST['quantity'] ?? 0);

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Produto inválido.']);
    exit;
}

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/cart_functions.php';

if ($quantity > 0) {
    $stockCheck = validateStock($pdo, $productId, $quantity);
    if (!$stockCheck['ok']) {
        echo json_encode(['success' => false, 'message' => $stockCheck['msg']]);
        exit;
    }
    // Valida qty total (existent + nova) contra estoque
    if ($isGuest) {
        $existingQty = $_SESSION['guest_cart'][$productId] ?? 0;
    } else {
        $existingQty = cartGetItemQuantity($pdo, (int)$_SESSION['user_id'], $productId);
    }
    $totalCheck = validateStock($pdo, $productId, $quantity, $existingQty);
    if (!$totalCheck['ok']) {
        echo json_encode(['success' => false, 'message' => $totalCheck['msg']]);
        exit;
    }
}

if ($isGuest) {
    sessionCartUpdateQuantity($productId, $quantity);
    $count = sessionCartGetCount();
} else {
    cartUpdateQuantity($pdo, (int)$_SESSION['user_id'], $productId, $quantity);
    $count = cartGetCount($pdo, (int)$_SESSION['user_id']);
}

echo json_encode(['success' => true, 'count' => $count]);
