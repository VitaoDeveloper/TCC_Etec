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

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Produto inválido.']);
    exit;
}

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/cart_functions.php';

if ($isGuest) {
    sessionCartRemoveItem($productId);
    $count = sessionCartGetCount();
} else {
    cartRemoveItem($pdo, (int)$_SESSION['user_id'], $productId);
    $count = cartGetCount($pdo, (int)$_SESSION['user_id']);
}

echo json_encode(['success' => true, 'count' => $count]);
