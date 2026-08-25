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
require_once $base_path . 'includes/csrf.php';
require_once $base_path . 'includes/status_labels.php';
$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['id'] ?? 0);
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_require_valid();
    $stmt = $pdo->prepare('SELECT status FROM e5_orders WHERE id = :id AND user_id = :uid LIMIT 1');
    $stmt->execute([':id' => $orderId, ':uid' => $userId]);
    $ord = $stmt->fetch();
    if ($ord && $ord['status'] === 'pending') {
        $items = $pdo->prepare('SELECT product_id, quantity FROM e5_order_items WHERE order_id = :oid');
        $items->execute([':oid' => $orderId]);
        foreach ($items as $it) {
            $pdo->prepare('UPDATE e5_products SET stock = stock + :qty WHERE id = :pid')->execute([':qty' => (int)$it['quantity'], ':pid' => (int)$it['product_id']]);
        }
        $pdo->prepare("UPDATE e5_orders SET status = 'canceled' WHERE id = :id")->execute([':id' => $orderId]);
        $message = 'Pedido cancelado com sucesso.';
    } else {
        $message = 'Não é possível cancelar este pedido.';
    }
}

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

include '../../components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;"><div class="container" style="max-width:700px; margin:0 auto;">
    <?php if ($message): ?><div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <div class="ml-section-header">
        <h2 class="ml-section-title">Pedido #<?php echo str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT); ?></h2>
        <span class="ml-main-count">Status: <strong style="color:var(--ml-text);"><?php echo htmlspecialchars($statusLabelsFlat[$order['status']] ?? $order['status'], ENT_QUOTES, 'UTF-8'); ?></strong> — <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
    </div>
    <div class="ml-table-wrap">
        <table class="ml-table">
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
                        <div><strong><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small style="color:var(--ml-text-secondary);"><?php echo htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></div>
                    </td>
                    <td><?php echo (int)$item['quantity']; ?></td>
                    <td>R$ <?php echo number_format((float)$item['unit_price'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format((float)$item['unit_price'] * (int)$item['quantity'], 2, ',', '.'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3" style="text-align:right;">Total:</td><td style="font-weight:700; color:var(--ml-accent);">R$ <?php echo number_format((float)$order['total'], 2, ',', '.'); ?></td></tr></tfoot>
        </table>
        <?php if ($order['shipping_method'] || $order['payment_method']): ?>
        <div style="margin-top:20px; display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div>
                <h4 style="margin-bottom:8px;"><i class="fas fa-truck" style="color:var(--ml-accent);"></i> Frete</h4>
                <p style="font-size:0.9rem; color:var(--ml-text-secondary);">
                    <?php echo htmlspecialchars($order['shipping_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php if ((float)$order['shipping_cost'] > 0): ?>Custo: R$ <?php echo number_format((float)$order['shipping_cost'], 2, ',', '.'); ?><?php else: ?><span style="color:var(--ml-green);">Grátis</span><?php endif; ?>
                </p>
            </div>
            <div>
                <h4 style="margin-bottom:8px;"><i class="fas fa-credit-card" style="color:var(--ml-accent);"></i> Pagamento</h4>
                <p style="font-size:0.9rem; color:var(--ml-text-secondary);">
                    <?php
                    $payLabels = ['pix'=>'Pix','boleto'=>'Boleto','credit'=>'Cartão de Crédito','delivery'=>'Pagamento na Entrega'];
                    echo htmlspecialchars($payLabels[$order['payment_method']] ?? $order['payment_method'] ?? '—', ENT_QUOTES, 'UTF-8');
                    ?><br>
                    Status: <?php echo $order['payment_status'] === 'paid' ? '<span style="color:var(--ml-green);">Pago</span>' : '<span style="color:var(--ml-text-muted);">Pendente</span>'; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
        <div style="margin-top:20px; display:flex; gap:12px; flex-wrap:wrap;">
            <a href="orders.php" class="ml-btn"><i class="fas fa-arrow-left"></i> Voltar</a>
            <?php if ($order['status'] === 'pending'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Tem certeza que deseja cancelar este pedido?')">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="ml-btn ml-btn-danger"><i class="fas fa-times-circle"></i> Cancelar Pedido</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div></section>
<?php include '../../components/footer.php'; ?>
