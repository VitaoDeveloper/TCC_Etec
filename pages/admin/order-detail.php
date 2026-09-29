<?php
$page_title = 'Detalhe do Pedido - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/comprovante_functions.php';
require_once __DIR__ . '/../../includes/shipping_functions.php';
require_once __DIR__ . '/../../includes/notification_functions.php';

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

// Validação manual de pagamento: admin confirma que o pagamento foi recebido.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_payment') {
    csrf_require_valid();
    if ($order['payment_status'] !== 'paid') {
        $pdo->prepare('UPDATE e5_orders SET payment_status = :status, email_status = :e WHERE id = :id')
            ->execute([':status' => 'paid', ':e' => 'pending', ':id' => $orderId]);
        $_SESSION['admin_message'] = 'Pagamento confirmado.';

        // Avisa o cliente e tenta entregar o e-mail agora. Se o SMTP falhar,
        // a notificação fica na fila com o erro registrado, em vez de o
        // cliente não receber nada sem ninguém perceber.
        notification_enqueue_order_event($pdo, $orderId, 'payment_confirmed');
        $drained = notification_drain_email($pdo, 5);

        if ($drained['failed'] > 0) {
            $_SESSION['admin_message'] .= ' O e-mail não saiu (SMTP indisponível) e ficou na fila.';
        }
    }
    header('Location: order-detail.php?id=' . $orderId);
    exit;
}

// Despacho: cria a etiqueta na SuperFrete e guarda o rastreio.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dispatch_shipment') {
    csrf_require_valid();

    $service = (string) ($_POST['service'] ?? '');
    if (!in_array($service, ['1', '2'], true)) {
        $service = '';
    }

    $data = shipping_load_order($pdo, $orderId);
    $result = shipping_dispatch($pdo, $data['order'], $data['items'], $service !== '' ? $service : null);

    if ($result['ok']) {
        $_SESSION['admin_message'] = $result['msg'];
    } else {
        $_SESSION['admin_error'] = $result['msg'];
    }

    header('Location: order-detail.php?id=' . $orderId);
    exit;
}

// Reenvio de uma etiqueta antiga, quando o pedido volta para "paid" por
// cancelamento na transportadora. A etiqueta nova é uma nova linha em
// e5_shipments; a antiga continua no histórico.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retry_label') {
    csrf_require_valid();

    $oldId = (int) ($_POST['shipment_id'] ?? 0);
    $old = $pdo->prepare('SELECT * FROM e5_shipments WHERE id = :id AND order_id = :o');
    $old->execute([':id' => $oldId, ':o' => $orderId]);
    $old = $old->fetch();

    if (!$old || $old['status'] !== 'canceled') {
        $_SESSION['admin_error'] = 'Só é possível refazer uma etiqueta que foi cancelada.';
        header('Location: order-detail.php?id=' . $orderId);
        exit;
    }

    // A etiqueta guardou o rótulo do serviço (PAC/Sedex), não o id numérico
    // que o /cart da SuperFrete espera. O reenvio usa o mesmo serviço da
    // etiqueta cancelada para o cliente não ver mudança de prazo sem aviso.
    $data = shipping_load_order($pdo, $orderId);
    $result = shipping_dispatch($pdo, $data['order'], $data['items'], shipping_service_id((string) $old['service']));

    $_SESSION[($result['ok'] ? 'admin_message' : 'admin_error')] = $result['msg'];
    header('Location: order-detail.php?id=' . $orderId);
    exit;
}

$shipments = shipping_for_order($pdo, $orderId);
$notifications = $pdo->prepare('SELECT * FROM e5_notifications WHERE order_id = :o ORDER BY id DESC LIMIT 10');
$notifications->execute([':o' => $orderId]);
$notifications = $notifications->fetchAll();

$adminMessage = $_SESSION['admin_message'] ?? null;
$adminError = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_message'], $_SESSION['admin_error']);

// Só faz sentido despachar pedido com pagamento confirmado.
$canDispatch = $order['payment_status'] === 'paid'
    && !in_array($order['status'], ['delivered', 'canceled'], true);
$activeShipment = null;
foreach ($shipments as $s) {
    if (in_array($s['status'], ['pending', 'released'], true)) {
        $activeShipment = $s;
        break;
    }
}
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

            <div class="ml-card" style="padding:15px; margin-bottom:20px; display:flex; align-items:center; gap:15px; flex-wrap:wrap; justify-content:space-between;">
                <div>
                    <strong>Pagamento:</strong>
                    <?php echo htmlspecialchars($payLabel[$order['payment_method']] ?? $order['payment_method'], ENT_QUOTES, 'UTF-8'); ?>
                    &mdash;
                    <?php if ($order['payment_status'] === 'paid'): ?>
                    <span class="status-badge status-active">Pago</span>
                    <?php else: ?>
                    <span class="status-badge status-pending">Aguardando pagamento</span>
                <?php endif; ?>
                <?php if ($adminError): ?>
                <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($adminError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
            <?php if ($order['payment_status'] !== 'paid'): ?>
                <form method="POST" style="margin:0;" onsubmit="return confirm('Confirmar o recebimento do pagamento deste pedido?')">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="confirm_payment">
                    <button type="submit" class="btn" style="background:#28a745; color:#fff; border:none; padding:8px 16px; border-radius:5px; cursor:pointer;"><i class="fas fa-check-circle"></i> Confirmar Pagamento</button>
                </form>
                <?php endif; ?>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:30px;">
                <div class="admin-table-container" style="padding:25px;">
                    <h3 style="margin-bottom:15px; font-size:1.1rem;">Informações do Pedido</h3>
                    <table style="width:100%;">
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Data</td><td style="text-align:right;"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Total</td><td style="text-align:right; font-weight:700; color:var(--color-primary);">R$ <?php echo number_format((float)$order['total'], 2, ',', '.'); ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Frete</td><td style="text-align:right;"><?php echo htmlspecialchars($order['shipping_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?> <?php echo $order['shipping_cost'] > 0 ? '(R$ ' . number_format((float)$order['shipping_cost'], 2, ',', '.') . ')' : '(Grátis)'; ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Pagamento</td><td style="text-align:right;"><?php echo htmlspecialchars($payLabel[$order['payment_method']] ?? $order['payment_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($order['payment_status'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
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

            <div class="admin-table-container" style="margin-top:25px;">
                <div class="admin-table-header">
                    <h3>Envio e Rastreio</h3>
                    <?php if ($order['tracking_code']): ?>
                        <span class="badge" style="background:#1a1a1a; color:#c9a227;"><?php echo htmlspecialchars($order['tracking_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                <div style="padding:20px 25px;">
                    <?php if ($shipments === []): ?>
                        <p style="color:var(--color-gray); margin-bottom:15px;">
                            Nenhuma etiqueta criada ainda.
                            <?php if ($order['payment_status'] !== 'paid'): ?>
                                Confirme o pagamento antes de enviar — a SuperFrete compra a etiqueta no ato.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <?php foreach ($shipments as $s): ?>
                        <?php
                        $shipLabels = [
                            'pending'   => 'Aguardando liberação',
                            'released'  => 'Liberada',
                            'canceled'  => 'Cancelada',
                            'delivered' => 'Entregue',
                            'error'     => 'Erro',
                        ];
                        $shipColors = [
                            'pending'   => '#ffc107',
                            'released'  => '#28a745',
                            'canceled'  => '#6c757d',
                            'delivered' => '#0d6efd',
                            'error'     => '#dc3545',
                        ];
                        $sc = $shipColors[$s['status']] ?? '#6c757d';
                        ?>
                        <div style="border:1px solid var(--color-border); border-radius:8px; padding:14px 16px; margin-bottom:12px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <span class="badge" style="background:<?php echo $sc; ?>; color:#fff;">
                                        <?php echo htmlspecialchars($shipLabels[$s['status']] ?? $s['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <strong style="margin-left:8px;"><?php echo htmlspecialchars($s['service'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php if ($s['price'] !== null): ?>
                                        <span style="color:var(--color-gray);"> — R$ <?php echo number_format((float)$s['price'], 2, ',', '.'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span style="color:var(--color-gray); font-size:0.85rem;">
                                    <?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?>
                                </span>
                            </div>

                            <?php if ($s['tracking_code']): ?>
                                <p style="margin:10px 0 0; font-family:monospace; font-size:0.95rem;">Rastreio: <strong><?php echo htmlspecialchars($s['tracking_code'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                            <?php endif; ?>

                            <?php if ($s['delivery_min_days'] && $s['delivery_max_days']): ?>
                                <p style="margin:6px 0 0; color:var(--color-gray); font-size:0.85rem;">Previsão de entrega: <?php echo (int)$s['delivery_min_days']; ?> a <?php echo (int)$s['delivery_max_days']; ?> dias úteis</p>
                            <?php endif; ?>

                            <?php if ($s['error_message']): ?>
                                <p style="margin:10px 0 0; color:#ff6b6b; font-size:0.85rem;"><?php echo htmlspecialchars($s['error_message'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>

                            <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                                <?php if ($s['label_url']): ?>
                                    <a href="<?php echo htmlspecialchars($s['label_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn" style="background:#c9a227; color:#1a1a1a; border:none; padding:7px 14px; border-radius:5px; font-size:0.85rem; text-decoration:none; display:inline-block;">
                                        <i class="fas fa-print"></i> Imprimir etiqueta
                                    </a>
                                <?php endif; ?>
                                <?php if ($s['status'] === 'canceled'): ?>
                                    <form method="post" onsubmit="return confirm('Criar uma nova etiqueta para este pedido?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="retry_label">
                                        <input type="hidden" name="shipment_id" value="<?php echo (int)$s['id']; ?>">
                                        <button type="submit" class="btn" style="background:#6c757d; color:#fff; border:none; padding:7px 14px; border-radius:5px; font-size:0.85rem; cursor:pointer;">
                                            <i class="fas fa-redo"></i> Reenviar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($canDispatch): ?>
                        <form method="post" style="margin-top:15px; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;"
                              onsubmit="return confirm('Criar a etiqueta de envio na SuperFrete? Esta ação compra o envio.')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="dispatch_shipment">
                            <div>
                                <label for="service" style="display:block; font-size:0.85rem; color:var(--color-gray); margin-bottom:5px;">Serviço</label>
                                <select id="service" name="service" style="padding:8px 10px; border-radius:5px; border:1px solid var(--color-border); background:var(--color-bg); color:var(--color-text);">
                                    <option value="1" <?php echo ($order['shipping_method'] ?? '') === '1' ? 'selected' : ''; ?>>PAC — até 6 dias</option>
                                    <option value="2" <?php echo ($order['shipping_method'] ?? '') === '2' ? 'selected' : ''; ?>>Sedex — até 2 dias</option>
                                </select>
                            </div>
                            <button type="submit" class="btn" style="background:#28a745; color:#fff; border:none; padding:9px 18px; border-radius:5px; cursor:pointer;">
                                <i class="fas fa-box"></i>
                                <?php echo $activeShipment ? 'Gerar etiqueta novamente' : 'Criar etiqueta de envio'; ?>
                            </button>
                        </form>
                        <?php if ($activeShipment): ?>
                            <p style="color:var(--color-gray); font-size:0.8rem; margin-top:8px;">
                                Este pedido já tem uma etiqueta em aberto. Criar de novo não duplica a compra: o painel devolve a etiqueta existente.
                            </p>
                        <?php endif; ?>
                    <?php elseif ($order['payment_status'] === 'paid'): ?>
                        <p style="color:var(--color-gray); font-size:0.85rem; margin-top:15px;">Pedido já entregue ou cancelado: não é possível criar nova etiqueta.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-table-container" style="margin-top:25px;">
                <div class="admin-table-header"><h3>Avisos do Cliente</h3></div>
                <?php if ($notifications === []): ?>
                    <p style="padding:20px 25px; color:var(--color-gray);">Nenhum aviso registrado para este pedido.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr><th>Canal</th><th>Evento</th><th>Destinatário</th><th>Situação</th><th>Detalhes</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $notifLabels = [
                                'pending' => 'Na fila',
                                'sent'    => 'Enviado',
                                'failed'  => 'Falhou',
                                'skipped' => 'Requer ação manual',
                            ];
                            $notifColors = [
                                'pending' => '#ffc107',
                                'sent'    => '#28a745',
                                'failed'  => '#dc3545',
                                'skipped' => '#17a2b8',
                            ];
                            ?>
                            <?php foreach ($notifications as $n): ?>
                            <tr>
                                <td><?php echo $n['channel'] === 'whatsapp' ? 'WhatsApp' : 'E-mail'; ?></td>
                                <td><?php echo htmlspecialchars($n['event_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($n['recipient'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="badge" style="background:<?php echo $notifColors[$n['status']] ?? '#6c757d'; ?>; color:#fff;">
                                        <?php echo htmlspecialchars($notifLabels[$n['status']] ?? $n['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.8rem; color:var(--color-gray);">
                                    <?php if ($n['error_message']): ?>
                                        <?php echo htmlspecialchars($n['error_message'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php elseif ($n['sent_at']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($n['sent_at'])); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
