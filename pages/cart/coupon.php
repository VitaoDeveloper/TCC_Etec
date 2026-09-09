<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login primeiro.']);
    exit;
}

$code = trim((string) ($_POST['code'] ?? ''));

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Digite um código de cupom.']);
    exit;
}

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/cart_functions.php';
require_once __DIR__ . '/../../includes/coupon_functions.php';

$userId = (int) $_SESSION['user_id'];
$items = cartGetItems($pdo, $userId);

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
}

$res = couponApply($pdo, $code, $subtotal, $userId);

if ($res['ok']) {
    $_SESSION['cart_coupon_code'] = $res['code'];
    echo json_encode(['success' => true, 'code' => $res['code'], 'discount' => $res['discount']]);
} else {
    unset($_SESSION['cart_coupon_code']);
    echo json_encode(['success' => false, 'message' => $res['msg']]);
}