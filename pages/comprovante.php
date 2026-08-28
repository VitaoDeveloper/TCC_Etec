<?php
/**
 * Endpoint HTTP para gerar/exibir Comprovante de Compra.
 *
 * GET /pages/comprovante.php?id=123           → HTML com botão imprimir
 * GET /pages/comprovante.php?id=123&format=pdf → PDF direto (download)
 */

session_start();

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/comprovante.php';

$orderId = (int)($_GET['id'] ?? 0);
$format  = $_GET['format'] ?? 'html';

if ($orderId <= 0) {
    http_response_code(400);
    exit('Pedido inválido.');
}

// ─── Autenticação ───────────────────────────────────────────────
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$isLogged = isset($_SESSION['user_id']);

if (!$isLogged) {
    header('Location: auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// ─── Carregar pedido ────────────────────────────────────────────
if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT * FROM e5_orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM e5_orders WHERE id = :id AND user_id = :uid LIMIT 1');
    $stmt->execute([':id' => $orderId, ':uid' => (int)$_SESSION['user_id']]);
}
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    exit('Pedido não encontrado.');
}

$snapshot = $order['tax_regime_snapshot'] ?? 'CPF';
if ($snapshot === 'MEI' && !empty($order['invoice_number'])) {
    http_response_code(302);
    exit;
}

// ─── Gerar comprovante ─────────────────────────────────────────
try {
    $resultado = gerarComprovanteCompra($pdo, $orderId, true);
} catch (Throwable $e) {
    error_log('Comprovante generation failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro ao gerar comprovante: ' . $e->getMessage());
}

$numero = $resultado['numero'];

// ─── Servir PDF ou HTML ─────────────────────────────────────────
if ($format === 'pdf' && $resultado['pdf'] !== null) {
    $pdfFile = dirname(__DIR__, 2) . '/storage/comprovantes/' . $numero . '_pedido' . $orderId . '.pdf';
    if (file_exists($pdfFile)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $numero . '.pdf"');
        header('Content-Length: ' . filesize($pdfFile));
        header('X-Comprovante-Numero: ' . $numero);
        readfile($pdfFile);
        exit;
    }
}

// Fallback: HTML
header('Content-Type: text/html; charset=UTF-8');
header('X-Comprovante-Numero: ' . $numero);
exit($resultado['html']);
