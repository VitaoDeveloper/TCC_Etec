<?php
$page_title = 'Detalhe do Pedido - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/comprovante.php';
require_once __DIR__ . '/../../includes/payment.php';
require_once __DIR__ . '/../../includes/image_helpers.php';

$orderId = (int) ($_GET['id'] ?? 0);

// Resend comprovante email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    enviarComprovanteEmail($pdo, $orderId);
    header('Location: order-detail.php?id=' . $orderId . '&sent=1');
    exit;
}

// Mark PIX order as paid (manual confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    csrf_require_valid();
    $stmt = $pdo->prepare('UPDATE e5_orders SET payment_status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute([':status' => 'paid', ':id' => $orderId]);
    header('Location: order-detail.php?id=' . $orderId . '&paid=1');
    exit;
}

// Refund (estorno)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refund') {
    csrf_require_valid();
    $orderStmt = $pdo->prepare('SELECT * FROM e5_orders WHERE id = :id AND payment_status = "paid" AND gateway_transaction_id IS NOT NULL LIMIT 1');
    $orderStmt->execute([':id' => $orderId]);
    $refundOrder = $orderStmt->fetch();
    if ($refundOrder) {
        $refundResult = paymentProcessRefund($refundOrder['gateway_transaction_id'], (float) $refundOrder['total']);
        if ($refundResult['success']) {
            $upd = $pdo->prepare('UPDATE e5_orders SET payment_status = "refunded", status = "canceled", updated_at = NOW() WHERE id = :id');
            $upd->execute([':id' => $orderId]);
            // Devolver estoque de todos os itens do pedido
            $itemsStmt = $pdo->prepare('SELECT product_id, quantity FROM e5_order_items WHERE order_id = :oid');
            $itemsStmt->execute([':oid' => $orderId]);
            foreach ($itemsStmt as $it) {
                $pdo->prepare('UPDATE e5_products SET stock = stock + :qty WHERE id = :pid')->execute([':qty' => (int)$it['quantity'], ':pid' => (int)$it['product_id']]);
            }
            error_log("Refund OK order #$orderId: " . ($refundResult['message'] ?? ''));
            header('Location: order-detail.php?id=' . $orderId . '&refunded=1');
        } else {
            error_log("Refund FAILED order #$orderId: " . ($refundResult['message'] ?? ''));
            header('Location: order-detail.php?id=' . $orderId . '&refund_error=' . urlencode($refundResult['message'] ?? 'Erro'));
        }
    } else {
        header('Location: order-detail.php?id=' . $orderId . '&refund_error=' . urlencode('Pedido não elegível para estorno.'));
    }
    exit;
}

$order = $pdo->prepare('SELECT o.*, u.name AS user_name, u.email AS user_email, u.postal_code, u.street, u.number, u.complement
    FROM e5_orders o LEFT JOIN e5_users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
$order->execute([':id' => $orderId]);
$order = $order->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$items = $pdo->prepare('SELECT oi.*, p.name AS product_name,
    (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
    FROM e5_order_items oi INNER JOIN e5_products p ON p.id = oi.product_id WHERE oi.order_id = :oid');
$items->execute([':oid' => $orderId]);
$items = $items->fetchAll();

$payLabel = ['pix' => 'Pix', 'boleto' => 'Boleto', 'credit' => 'Cartão', 'delivery' => 'Entrega'];
$sinfo = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'class' => ''];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include 'head_inc.php'; ?>
</head>
<body>
    <div class="admin-wrapper">
        <?php $activePage = 'orders'; include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Pedido #<?php echo str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT); ?></h2>
                    <p><a href="orders.php" style="color:var(--color-primary);">&larr; Voltar para Pedidos</a></p>
                </div>
                <span class="status-badge <?php echo $sinfo['class']; ?>"><?php echo $sinfo['label']; ?></span>
            </header>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:30px;">
                <div class="admin-table-container" style="padding:25px;">
                    <h3 style="margin-bottom:15px; font-size:1.1rem;">Informações do Pedido</h3>
                    <table style="width:100%;">
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Data</td><td style="text-align:right;"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Total</td><td style="text-align:right; font-weight:700; color:var(--color-primary);">R$ <?php echo number_format((float)$order['total'], 2, ',', '.'); ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Frete</td><td style="text-align:right;"><?php echo htmlspecialchars($order['shipping_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?> <?php echo $order['shipping_cost'] > 0 ? '(R$ ' . number_format((float)$order['shipping_cost'], 2, ',', '.') . ')' : '(Grátis)'; ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Pagamento</td><td style="text-align:right;"><?php echo htmlspecialchars($payLabel[$order['payment_method']] ?? $order['payment_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($order['payment_status'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                    </table>
                </div>
                <div class="admin-table-container" style="padding:25px;">
                    <h3 style="margin-bottom:15px; font-size:1.1rem;">Endereço de Entrega</h3>
                    <p style="color:var(--color-gray-light);">
                        <?php echo htmlspecialchars($order['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($order['number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($order['complement']): ?>- <?php echo htmlspecialchars($order['complement'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?><br>
                        <?php echo htmlspecialchars($order['shipping_neighborhood'] ?? '', ENT_QUOTES, 'UTF-8'); ?> -
                        <?php echo htmlspecialchars($order['shipping_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars($order['shipping_state'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                        CEP: <?php echo htmlspecialchars($order['shipping_postal_code'] ?? $order['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <h3 style="margin:15px 0 8px; font-size:1.1rem;">Cliente</h3>
                    <p style="color:var(--color-gray-light);"><?php echo htmlspecialchars($order['user_name'] ?? $order['guest_name'] ?? 'Convidado', ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php $custEmail = $order['user_email'] ?? $order['guest_email'] ?? ''; ?>
                    <?php if ($custEmail): ?>
                    <a href="mailto:<?php echo htmlspecialchars($custEmail, ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--color-primary);"><?php echo htmlspecialchars($custEmail, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endif; ?></p>
                </div>
            </div>

            <div class="admin-table-container">
                <div class="admin-table-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3>Itens do Pedido</h3>
                    <?php if (($order['tax_regime_snapshot'] ?? 'CPF') === 'CPF' || empty($order['invoice_number'])): ?>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <a href="../comprovante.php?id=<?php echo $orderId; ?>" target="_blank" class="btn" style="background:var(--color-primary); color:#fff; padding:8px 16px; border-radius:6px; text-decoration:none; font-size:0.85rem;"><i class="fas fa-file-invoice"></i> Baixar</a>
                        <a href="../comprovante.php?id=<?php echo $orderId; ?>&format=pdf" target="_blank" class="btn" style="background:#555; color:#fff; padding:8px 16px; border-radius:6px; text-decoration:none; font-size:0.85rem;"><i class="fas fa-file-pdf"></i> PDF</a>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="resend">
                            <button type="submit" class="btn" style="background:#2196f3; color:#fff; padding:8px 16px; border-radius:6px; font-size:0.85rem; border:none; cursor:pointer;"><i class="fas fa-envelope"></i> Reenviar E-mail</button>
                        </form>
                        <?php if (($order['payment_method'] ?? '') === 'pix' && ($order['payment_status'] ?? '') === 'pending'): ?>
                        <form method="post" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="mark_paid">
                            <button type="submit" class="btn" style="background:#4caf50; color:#fff; padding:8px 16px; border-radius:6px; font-size:0.85rem; border:none; cursor:pointer;"><i class="fas fa-check-circle"></i> Marcar como Pago</button>
                        </form>
                        <?php endif; ?>
                        <?php if (($order['payment_status'] ?? '') === 'paid' && !empty($order['gateway_transaction_id'])): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Tem certeza que deseja estornar este pedido? Esta ação é irreversível.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="refund">
                            <button type="submit" class="btn" style="background:#e53935; color:#fff; padding:8px 16px; border-radius:6px; font-size:0.85rem; border:none; cursor:pointer;"><i class="fas fa-undo"></i> Estornar Pedido</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr><th>Produto</th><th>Imagem</th><th>Preço Unit.</th><th>Qtd</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php if ($it['image_path']): $img = renderProductImage($it['image_path'], '../../'); ?>
                                <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:5px;" alt=""></td>
                            <?php else: ?><td style="color:var(--color-gray);">—</td><?php endif; ?>
                            <td>R$ <?php echo number_format((float)$it['unit_price'], 2, ',', '.'); ?></td>
                            <td><?php echo (int) $it['quantity']; ?></td>
                            <td><strong>R$ <?php echo number_format((float)$it['unit_price'] * (int)$it['quantity'], 2, ',', '.'); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4" style="text-align:right; font-weight:700;">Total</td><td style="font-weight:700;color:var(--color-primary);">R$ <?php echo number_format((float)$order['total'], 2, ',', '.'); ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
