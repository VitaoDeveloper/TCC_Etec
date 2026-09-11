<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Faça login primeiro.']);
    exit;
}

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/cart_functions.php';
require_once __DIR__ . '/../../includes/coupon_functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
csrf_require_valid_ajax();

require_once __DIR__ . '/../../includes/rate_limit.php';
if (!rate_limit_check('cart_' . $_SESSION['user_id'], 60, 1)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Muitas solicitações. Aguarde um momento e tente novamente.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// =====================================================================
// AÇÃO: recalc — recalcula o desconto do cupom já aplicado na sessão
// com base no subtotal atual do carrinho (usado quando a quantidade muda).
// =====================================================================
if (isset($_POST['action']) && $_POST['action'] === 'recalc') {
    $items = cartGetItems($pdo, $userId);

    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += (float) $item['price'] * (int) $item['quantity'];
    }

    $couponCode = trim((string) ($_SESSION['cart_coupon_code'] ?? ''));
    $meta = $_SESSION['cart_coupon_meta'] ?? null;

    if ($couponCode === '' || !is_array($meta)) {
        echo json_encode(['success' => true, 'discount' => 0, 'code' => null, 'expired' => true]);
        exit;
    }

    if ($subtotal <= 0) {
        echo json_encode(['success' => true, 'discount' => 0, 'code' => null, 'expired' => true]);
        exit;
    }

    $type        = $meta['type'] ?? 'percent';
    $value       = (float) ($meta['value'] ?? 0);
    $maxDiscount = (float) ($meta['max_discount'] ?? 0);
    $minAmount   = (float) ($meta['min_amount'] ?? 0);

    if ($minAmount > $subtotal) {
        unset($_SESSION['cart_coupon_code'], $_SESSION['cart_coupon_meta']);
        echo json_encode(['success' => true, 'discount' => 0, 'code' => null, 'expired' => true]);
        exit;
    }

    if ($type === 'percent') {
        $discount = round($subtotal * ($value / 100), 2);
        if ($maxDiscount > 0) {
            $discount = min($discount, $maxDiscount);
        }
    } else {
        $discount = $value;
    }

    $discount = round(min(max(0, $discount), $subtotal), 2);

    echo json_encode([
        'success'  => true,
        'discount' => $discount,
        'code'     => $couponCode,
        'expired'  => false,
    ]);
    exit;
}

$code = trim((string) ($_POST['code'] ?? ''));

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Digite um código de cupom.']);
    exit;
}

$items = cartGetItems($pdo, $userId);

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
}

$res = couponApply($pdo, $code, $subtotal, $userId);

if ($res['ok']) {
    $_SESSION['cart_coupon_code'] = $res['code'];

    $stmt = $pdo->prepare('SELECT type, value, max_discount, min_amount FROM e5_coupons WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $res['code']]);
    $couponRow = $stmt->fetch();

    $_SESSION['cart_coupon_meta'] = $couponRow ? [
        'type'         => $couponRow['type'],
        'value'        => (float) $couponRow['value'],
        'max_discount' => (float) ($couponRow['max_discount'] ?? 0),
        'min_amount'   => (float) ($couponRow['min_amount'] ?? 0),
    ] : ['type' => 'percent', 'value' => 0, 'max_discount' => 0, 'min_amount' => 0];

    echo json_encode(['success' => true, 'code' => $res['code'], 'discount' => $res['discount']]);
} else {
    unset($_SESSION['cart_coupon_code'], $_SESSION['cart_coupon_meta']);
    echo json_encode(['success' => false, 'message' => $res['msg']]);
}