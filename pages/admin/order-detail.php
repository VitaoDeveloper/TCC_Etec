<?php
$page_title = 'Detalhe do Pedido - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/image_helpers.php';

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
