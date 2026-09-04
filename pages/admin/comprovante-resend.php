<?php

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/comprovante_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido');
}

csrf_require_valid();

$orderId = (int) ($_POST['order_id'] ?? 0);

$order = $pdo->prepare('SELECT o.*, u.email, u.name FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
$order->execute([':id' => $orderId]);
$order = $order->fetch();

if (!$order) {
    $_SESSION['admin_message'] = 'Pedido não encontrado.';
    header('Location: order-detail.php?id=' . $orderId);
    exit;
}

$compResult = gerarComprovante($orderId);

if ($compResult['success']) {
    $emailSent = sendComprovanteEmail($orderId, $order['email'], $compResult['filename']);
    $emailStatus = $emailSent ? 'sent' : 'failed';
    $errorMsg = $emailSent ? null : 'Falha ao enviar e-mail (verifique logs)';
} else {
    $emailStatus = 'failed';
    $errorMsg = $compResult['error'] ?? 'Falha ao gerar PDF';
}

salvarStatusEmail($orderId, $emailStatus, $errorMsg);

$_SESSION['admin_message'] = $emailSent ? 'Comprovante reenviado com sucesso!' : 'Falha ao reenviar comprovante: ' . ($errorMsg ?? 'Erro desconhecido');
header('Location: order-detail.php?id=' . $orderId);
exit;