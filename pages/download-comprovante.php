<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acesso negado. Faça login para acessar.');
}

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/comprovante_functions.php';

$orderId = (int) ($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'inline';

$order = $pdo->prepare('SELECT o.*, u.id AS user_id FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
$order->execute([':id' => $orderId]);
$order = $order->fetch();

if (!$order) {
    http_response_code(404);
    exit('Pedido não encontrado.');
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'customer';

if ($order['user_id'] !== $userId && $role !== 'admin') {
    http_response_code(404);
    exit('Pedido não encontrado.');
}

$pdfPath = getComprovantePath($orderId);

if (!$pdfPath) {
    $compResult = gerarComprovante($orderId);
    if ($compResult['success']) {
        $pdfPath = $compResult['filepath'];
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Comprovante</title></head><body>';
        echo '<h1>Comprovante de Compra - Pedido #' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT) . '</h1>';
        echo $compResult['html'];
        echo '<script>window.print();</script></body></html>';
        exit;
    }
}

$filename = basename($pdfPath);
$mime = mime_content_type($pdfPath);

if ($format === 'pdf' || $mime === 'application/pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($pdfPath));
    header('Cache-Control: no-cache');
    header('Pragma: no-cache');
    readfile($pdfPath);
    exit;
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($pdfPath));
    readfile($pdfPath);
    exit;
}