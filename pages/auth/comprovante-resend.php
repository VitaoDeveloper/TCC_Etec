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
    $_SESSION['auth_message'] = 'Pedido não encontrado.';
    header('Location: order-detail.php?id=' . $orderId);
    exit;
}

$emailSent = false;
$errorMsg = null;

// Reutiliza o PDF já existente; só gera novo se ainda não houver.
$pdfPath = getComprovantePath($orderId);
if (!$pdfPath) {
    $compResult = gerarComprovante($orderId);
    if (!$compResult['success']) {
        $_SESSION['auth_message'] = 'Falha ao gerar comprovante: ' . ($compResult['error'] ?? 'Erro desconhecido');
        header('Location: order-detail.php?id=' . $orderId);
        exit;
    }
    $pdfPath = COMPROVANTE_DIR . $compResult['filename'];
}

$result = sendMailWithAttachment($order['email'], 'Seu comprovante de compra — pedido #' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT), 'Segue em anexo o comprovante de compra do pedido #' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT) . '.', $pdfPath);
$emailSent = (bool) $result;
$errorMsg = $emailSent ? null : 'Falha ao enviar e-mail (verifique os logs).';

salvarStatusEmail($orderId, $emailSent ? 'sent' : 'failed', $errorMsg);

$_SESSION['auth_message'] = $emailSent ? 'Comprovante reenviado com sucesso!' : 'Falha ao reenviar comprovante: ' . ($errorMsg ?? 'Erro desconhecido');
header('Location: order-detail.php?id=' . $orderId);
exit;