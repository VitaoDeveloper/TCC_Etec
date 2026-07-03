<?php
$page_title = 'Detalhes do Pedido - Royal Tech';
$breadcrumb_title = 'Detalhes do Pedido';
$current_page = 'pedidos';
$base_path = '../../';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

include '../../database/connection.php';
$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM e5_orders WHERE id = :id AND user_id = :uid LIMIT 1');
$stmt->execute([':id' => $orderId, ':uid' => $userId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$stmtItems = $pdo->prepare('
    SELECT oi.*, p.name, p.brand,
        (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
    FROM e5_order_items oi
    INNER JOIN e5_products p ON p.id = oi.product_id
    WHERE oi.order_id = :oid
');
$stmtItems->execute([':oid' => $orderId]);
$items = $stmtItems->fetchAll();

$statusLabels = [
    'pending' => 'Pendente',
    'paid' => 'Pago',
    'shipped' => 'Enviado',
    'delivered' => 'Concluído',
    'canceled' => 'Cancelado',
];

include '../../components/header.php';
?>
<section class="section"><div class="container" style="max-width:700px; margin:0 auto;">
    <div class="section-header"><h2>Pedido #<?php echo str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT); ?></h2><p>Status: <strong><?php echo $statusLabels[$order['status']] ?? $order['status']; ?></strong> — <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p></div>
    <div class="admin-table-container">
        <table class="admin-table">
            <thead><tr><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item):
                $img = (string) ($item['image_path'] ?? '');
                if ($img === '') {
                    $img = $base_path . 'assets/img/placeholder-product.svg';
                } elseif (preg_match('#^/#', $img)) {
                    $img = $base_path . ltrim($img, '/');
                } elseif (!preg_match('#^https?://#i', $img)) {
                    $img = $base_path . $img;
                }
            ?>
                <tr>
                    <td style="display:flex; align-items:center; gap:10px;">
                        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">
                        <div><strong><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small><?php echo htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></div>
                    </td>
                    <td><?php echo (int)$item['quantity']; ?></td>
                    <td>R$ <?php echo number_format((float)$item['unit_price'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format((float)$item['unit_price'] * (int)$item['quantity'], 2, ',', '.'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3" style="text-align:right; font-weight:600;">Total:</td><td style="font-weight:700; color:var(--color-gold);">R$ <?php echo number_format((float)$order['total'], 2, ',', '.'); ?></td></tr></tfoot>
        </table>
        <?php if ($order['shipping_method'] || $order['payment_method']): ?>
        <div style="margin-top:20px; display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div>
                <h4 style="margin-bottom:8px;"><i class="fas fa-truck" style="color:var(--color-primary);"></i> Frete</h4>
                <p style="font-size:0.9rem; color:var(--color-gray-light);">
                    <?php echo htmlspecialchars($order['shipping_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php if ((float)$order['shipping_cost'] > 0): ?>Custo: R$ <?php echo number_format((float)$order['shipping_cost'], 2, ',', '.'); ?><?php else: ?><span style="color:#4caf50;">Grátis</span><?php endif; ?>
                </p>
            </div>
            <div>
                <h4 style="margin-bottom:8px;"><i class="fas fa-credit-card" style="color:var(--color-primary);"></i> Pagamento</h4>
                <p style="font-size:0.9rem; color:var(--color-gray-light);">
                    <?php
                    $payLabels = ['pix'=>'Pix','boleto'=>'Boleto','credit'=>'Cartão de Crédito','delivery'=>'Pagamento na Entrega'];
                    echo htmlspecialchars($payLabels[$order['payment_method']] ?? $order['payment_method'] ?? '—', ENT_QUOTES, 'UTF-8');
                    ?><br>
                    Status: <?php echo $order['payment_status'] === 'paid' ? '<span style="color:#4caf50;">Pago</span>' : '<span style="color:var(--color-gray);">Pendente</span>'; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
        <div style="margin-top:20px;"><a href="orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a></div>
    </div>
</div></section>
<?php include '../../components/footer.php'; ?>
