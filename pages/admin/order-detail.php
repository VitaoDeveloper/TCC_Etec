<?php
$page_title = 'Detalhe do Pedido - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/comprovante_functions.php';
require_once __DIR__ . '/../../includes/order_payment_functions.php';
require_once __DIR__ . '/../../includes/nf_functions.php';

$orderId = (int) ($_GET['id'] ?? 0);
$order = $pdo->prepare('SELECT o.*, u.name AS user_name, u.email AS user_email, u.postal_code, u.street, u.number, u.complement
    FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
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

// Ações administrativas: confirmar pagamento, envio/rastreio, NF, estorno, cancelamento.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $action = (string) ($_POST['action'] ?? '');
    $adminName = trim((string) ($_SESSION['user_name'] ?? ''));

    switch ($action) {
        case 'confirm_payment':
            if ($order['payment_status'] !== 'paid') {
                $pdo->prepare('UPDATE e5_orders SET payment_status = :ps, status = CASE WHEN status = "pending" THEN "paid" ELSE status END, payment_expires_at = NULL WHERE id = :id')
                    ->execute([':ps' => 'paid', ':id' => $orderId]);
                $_SESSION['admin_message'] = 'Pagamento confirmado.';
            } else {
                $_SESSION['admin_message'] = 'Pagamento já está confirmado.';
            }
            break;

        case 'mark_shipped':
            $track = trim((string) ($_POST['tracking_code'] ?? ''));
            if ($track === '' && empty($order['tracking_code'])) {
                $track = generateTrackingCode($orderId);
            }
            $pdo->prepare("UPDATE e5_orders SET status = 'shipped',
                    tracking_code = CASE WHEN tracking_code IS NULL OR tracking_code = '' THEN :track ELSE tracking_code END
                  WHERE id = :id")
                ->execute([':track' => $track, ':id' => $orderId]);
            $_SESSION['admin_message'] = 'Pedido marcado como enviado' . ($track !== '' ? ' — código de rastreio: ' . $track : '.');
            break;

        case 'generate_tracking':
            $tstmt = $pdo->prepare('SELECT tracking_code FROM e5_orders WHERE id = :id');
            $tstmt->execute([':id' => $orderId]);
            $track = (string) $tstmt->fetchColumn();
            if ($track === '') {
                $track = generateTrackingCode($orderId);
                $pdo->prepare('UPDATE e5_orders SET tracking_code = :track WHERE id = :id')
                    ->execute([':track' => $track, ':id' => $orderId]);
            }
            $_SESSION['admin_message'] = 'Código de rastreio: ' . $track;
            break;

        case 'emit_nf':
            $nf = nfEnsure($pdo, $orderId);
            $_SESSION['admin_message'] = $nf['ok']
                ? 'NF-e ' . $nf['nf_number'] . ' emitida' . ($nf['fresh'] ? ' (nova).' : ' (já existia).')
                : 'Falha ao emitir NF-e: ' . $nf['message'];
            break;

        case 'refund_payment':
            $res = orderRefundAdmin($pdo, $orderId, $adminName, trim((string) ($_POST['refund_reason'] ?? '')));
            $_SESSION['admin_message'] = $res['message'];
            if (!$res['ok']) {
                $_SESSION['admin_error'] = $res['message'];
                unset($_SESSION['admin_message']);
            }
            break;

        case 'cancel_order':
            $res = orderCancelAdmin($pdo, $orderId, $adminName, trim((string) ($_POST['reason'] ?? '')));
            $_SESSION['admin_message'] = $res['message'];
            if (!$res['ok']) {
                $_SESSION['admin_error'] = $res['message'];
                unset($_SESSION['admin_message']);
            }
            break;
    }
    header('Location: order-detail.php?id=' . $orderId);
    exit;
}

$adminMessage = $_SESSION['admin_message'] ?? null;
$adminError = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_message'], $_SESSION['admin_error']);
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
                <div class="admin-actions">
                    <span class="status-badge <?php echo $sinfo['class']; ?>"><?php echo $sinfo['label']; ?></span>
                    <?php include 'header_user_inc.php'; ?>
                </div>
            </header>

            <?php if ($adminMessage): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($adminMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($adminError): ?>
            <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($adminError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="ml-card" style="padding:15px; margin-bottom:20px; display:flex; align-items:center; gap:15px; flex-wrap:wrap; justify-content:space-between;">
                <div>
                    <strong>Pagamento:</strong>
                    <?php echo htmlspecialchars($payLabel[$order['payment_method']] ?? $order['payment_method'], ENT_QUOTES, 'UTF-8'); ?>
                    &mdash;
                    <?php if ($order['payment_status'] === 'paid'): ?>
                    <span class="status-badge status-active">Pago</span>
                    <?php elseif ($order['payment_status'] === 'refunded'): ?>
                    <span class="status-badge status-inactive">Estornado</span>
                    <?php elseif ($order['payment_status'] === 'failed'): ?>
                    <span class="status-badge status-inactive">Falha no pagamento</span>
                    <?php elseif ($order['payment_status'] === 'expired'): ?>
                    <span class="status-badge status-inactive">Expirado</span>
                    <?php else: ?>
                    <span class="status-badge status-pending">Aguardando pagamento</span>
                    <?php endif; ?>
                </div>
                <?php if (in_array($order['payment_status'], ['pending','processing','failed','expired'], true) && $order['status'] !== 'canceled'): ?>
                <form method="POST" style="margin:0;" onsubmit="return confirm('Confirmar o recebimento do pagamento deste pedido?')">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="confirm_payment">
                    <button type="submit" class="btn" style="background:#28a745; color:#fff; border:none; padding:8px 16px; border-radius:5px; cursor:pointer;"><i class="fas fa-check-circle"></i> Confirmar Pagamento</button>
                </form>
                <?php endif; ?>
            </div>

            <?php if (in_array($order['status'], ['paid','shipped','delivered'], true)): ?>
            <div class="ml-card" style="padding:15px; margin-bottom:20px; display:flex; align-items:center; gap:15px; flex-wrap:wrap; justify-content:space-between;">
                <div>
                    <strong>Operações</strong>
                    <p style="color:var(--color-gray); font-size:0.85rem; margin-top:2px;">Marcar envio, emitir NF-e, estornar ou cancelar.</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <?php if ($order['status'] === 'paid'): ?>
                    <form method="POST" style="margin:0; display:flex; gap:6px; align-items:center;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="mark_shipped">
                        <input type="text" name="tracking_code" placeholder="Código rastreio (opcional)" style="padding:7px 10px; border:1px solid var(--color-border); border-radius:4px; background:var(--color-black); color:var(--color-white); font-size:0.8rem; width:170px;">
                        <button type="submit" class="btn" style="background:#17a2b8; color:#fff; border:none; padding:8px 14px; border-radius:5px; cursor:pointer; white-space:nowrap;"><i class="fas fa-box"></i> Marcar Enviado</button>
                    </form>
                    <?php endif; ?>
                    <?php if (empty($order['tracking_code']) && in_array($order['status'], ['shipped','delivered'], true)): ?>
                    <form method="POST" style="margin:0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="generate_tracking">
                        <button type="submit" class="btn" style="background:#6f42c1; color:#fff; border:none; padding:8px 14px; border-radius:5px; cursor:pointer; white-space:nowrap;"><i class="fas fa-shipping-fast"></i> Gerar Rastreio</button>
                    </form>
                    <?php endif; ?>
                    <?php if (empty($order['nf_number'])): ?>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Emitir a NF-e deste pedido?')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="emit_nf">
                        <button type="submit" class="btn" style="background:#fd7e14; color:#fff; border:none; padding:8px 14px; border-radius:5px; cursor:pointer; white-space:nowrap;"><i class="fas fa-file-invoice"></i> Emitir NF-e</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($order['payment_status'] === 'paid'): ?>
                    <form method="POST" style="margin:0; display:flex; gap:6px; align-items:center;" onsubmit="return confirm('Estornar o pagamento deste pedido? Os itens voltam ao estoque.')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="refund_payment">
                        <input type="text" name="refund_reason" placeholder="Motivo do estorno" style="padding:7px 10px; border:1px solid var(--color-border); border-radius:4px; background:var(--color-black); color:var(--color-white); font-size:0.8rem; width:150px;">
                        <button type="submit" class="btn" style="background:#dc3545; color:#fff; border:none; padding:8px 14px; border-radius:5px; cursor:pointer; white-space:nowrap;"><i class="fas fa-undo"></i> Estornar</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($order['status'] !== 'canceled'): ?>
                    <form method="POST" style="margin:0; display:flex; gap:6px; align-items:center;" onsubmit="return confirm('<?php echo $order['payment_status'] === 'paid' ? 'Cancelar o pedido? O pagamento será estornado e os itens voltam ao estoque.' : 'Cancelar o pedido? Os itens voltam ao estoque.'; ?>')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="cancel_order">
                        <input type="text" name="reason" placeholder="Motivo do cancelamento" style="padding:7px 10px; border:1px solid var(--color-border); border-radius:4px; background:var(--color-black); color:var(--color-white); font-size:0.8rem; width:170px;">
                        <button type="submit" class="btn" style="background:#ff6b5e; color:#fff; border:none; padding:8px 14px; border-radius:5px; cursor:pointer; white-space:nowrap;"><i class="fas fa-ban"></i> Cancelar</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:30px;">
                <div class="admin-table-container" style="padding:25px;">
                    <h3 style="margin-bottom:15px; font-size:1.1rem;">Informações do Pedido</h3>
                    <table style="width:100%;">
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Data</td><td style="text-align:right;"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Total</td><td style="text-align:right; font-weight:700; color:var(--color-primary);">R$ <?php echo number_format((float)$order['total'], 2, ',', '.'); ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Frete</td><td style="text-align:right;"><?php echo htmlspecialchars($order['shipping_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?> <?php echo $order['shipping_cost'] > 0 ? '(R$ ' . number_format((float)$order['shipping_cost'], 2, ',', '.') . ')' : '(Grátis)'; ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Pagamento</td><td style="text-align:right;"><?php echo htmlspecialchars($payLabel[$order['payment_method']] ?? $order['payment_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($paymentStatusLabels[$order['payment_status']]['label'] ?? $order['payment_status'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr>
                            <td style="color:var(--color-gray); padding:6px 0;">Rastreio</td>
                            <td style="text-align:right;">
                                <?php if (!empty($order['tracking_code'])): ?>
                                    <span style="font-family:Consolas,monospace;"><?php echo htmlspecialchars($order['tracking_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--color-gray);">Não gerado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="color:var(--color-gray); padding:6px 0;">NF-e</td>
                            <td style="text-align:right;">
                                <?php if (!empty($order['nf_number'])): ?>
                                    <?php echo htmlspecialchars($order['nf_number'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($order['nf_emitted_at']): ?><small style="display:block; color:var(--color-gray);"><?php echo date('d/m/Y H:i', strtotime($order['nf_emitted_at'])); ?></small><?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--color-gray);">Não emitida</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($order['payment_status'] === 'refunded' || !empty($order['refund_reason'])): ?>
                        <tr>
                            <td style="color:var(--color-gray); padding:6px 0;">Estorno</td>
                            <td style="text-align:right;">
                                <?php echo htmlspecialchars($order['refund_reason'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($order['refunded_at']): ?><small style="display:block; color:var(--color-gray);">por <?php echo htmlspecialchars($order['refunded_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?> em <?php echo date('d/m/Y H:i', strtotime($order['refunded_at'])); ?></small><?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                            <tr>
                                <td style="color:var(--color-gray); padding:6px 0; font-size:0.85rem;">Comprovante</td>
                                <td>
                                    <?php
                                    $compPath = getComprovantePath($order['id']);
                                    $hasPdf = $compPath && file_exists($compPath);
                                    ?>
                                    <?php if ($hasPdf): ?>
                                        <a href="../download-comprovante.php?id=<?php echo (int)$order['id']; ?>" class="ml-btn" style="padding:4px 10px; font-size:0.75rem;"><i class="fas fa-file-pdf"></i> Baixar</a>
                                    <?php else: ?>
                                        <span style="color:var(--color-gray);">Não gerado</span>
                                    <?php endif; ?>
                                    <a href="../download-comprovante.php?id=<?php echo (int)$order['id']; ?>" class="ml-btn ml-btn-sm" style="padding:4px 10px; font-size:0.75rem; color:#fff; text-decoration:none;" target="_blank"><i class="fas fa-file-pdf"></i> Visualizar</a>
                                    <form method="post" action="comprovante-resend.php" style="display:inline" onsubmit="return confirm('Reenviar o comprovante por e-mail?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <button type="submit" class="ml-btn ml-btn-sm" style="padding:4px 10px; font-size:0.75rem; background:#6c757d; color:#fff; text-decoration:none; border:none; cursor:pointer;"><i class="fas fa-redo"></i> Reenviar</button>
                                    </form>
                                </td>
                            </tr>
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
                    <p style="color:var(--color-gray-light);"><?php echo htmlspecialchars($order['user_name'], ENT_QUOTES, 'UTF-8'); ?><br>
                    <a href="mailto:<?php echo htmlspecialchars($order['user_email'], ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--color-primary);"><?php echo htmlspecialchars($order['user_email'], ENT_QUOTES, 'UTF-8'); ?></a></p>
                </div>
            </div>

            <div class="admin-table-container">
                <div class="admin-table-header"><h3>Itens do Pedido</h3></div>
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
