<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/cart_functions.php';
require_once __DIR__ . '/../../includes/shipping.php';

$cep = trim((string) ($_POST['cep'] ?? ''));
if (empty($cep)) {
    echo json_encode(['success' => false, 'error' => 'Informe um CEP.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $items = sessionCartGetItems($pdo);
} else {
    $items = cartGetItems($pdo, (int) $_SESSION['user_id']);
}

if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'Seu carrinho está vazio.']);
    exit;
}

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
}

$result = shippingCalculate($cep, $subtotal, $items);

echo json_encode([
    'success' => $result['success'],
    'error'   => $result['error'] ?? null,
    'provider'=> $result['provider'] ?? 'estimated',
    'is_real' => $result['is_real'] ?? false,
    'warning' => $result['warning'] ?? null,
    'address' => $result['address'] ?? null,
    'options' => $result['options'] ?? [],
]);