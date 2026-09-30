<?php

declare(strict_types=1);

/**
 * Detalhes do Pedido — uma compra do cliente, itens, frete e pagamento.
 *
 * Vive no shell da conta (account_layout_head()/account_layout_foot()),
 * com a sidebar marcando "Meus Pedidos". O usuário só enxerga pedidos
 * próprios: a consulta filtra por user_id, e pedido alheio ou inexistente
 * redireciona para a lista.
 *
 * O cancelamento é um POST com CSRF que devolve o estoque dos itens e
 * só vale para pedidos 'pending'; a mensagem de retorno aparece como
 * flash no topo da própria tela.
 */

require_once __DIR__ . '/../../includes/account_layout.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
require_once __DIR__ . '/../../database/connection.php';

$user = account_require_login($pdo);

$userId  = (int) $user['id'];
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
            $pdo->prepare('UPDATE e5_products SET stock = stock + :qty WHERE id = :pid')
                ->execute([':qty' => (int) $it['quantity'], ':pid' => (int) $it['product_id']]);
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
    header('Location: ' . base_url('pages/auth/orders.php'));
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

$payLabels = [
    'pix'      => 'Pix',
    'boleto'   => 'Boleto',
    'credit'   => 'Cartão de Crédito',
    'delivery' => 'Pagamento na Entrega',
];

$page_title = 'Detalhes do Pedido - Royal Tech';

account_layout_head($user, 'pedidos');
?>

<div class="account-page-header">
    <h1 class="account-page-title">Pedido #<?php echo str_pad((string) $order['id'], 4, '0', STR_PAD_LEFT); ?></h1>
    <p class="account-page-subtitle">
        <?php echo e($statusLabelsFlat[$order['status']] ?? $order['status']); ?>
        — <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
    </p>
</div>

<?php if ($message !== null): ?>
    <div class="account-alert account-alert--ok" role="status">
        <i class="fas fa-circle-check" aria-hidden="true"></i>
        <span><?php echo e($message); ?></span>
    </div>
<?php endif; ?>

<section class="account-card" id="secao-detalhe">
    <div class="ml-table-wrap">
        <table class="ml-table">
            <thead><tr><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item):
                $img = renderProductImage((string) ($item['image_path'] ?? ''), base_url());
            ?>
                <tr>
                    <td style="display:flex; align-items:center; gap:10px;">
                        <img src="<?php echo e($img); ?>" alt="<?php echo e($item['name']); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">
                        <div><strong><?php echo e($item['name']); ?></strong><br><small style="color:var(--ml-text-secondary);"><?php echo e($item['brand'] ?? ''); ?></small></div>
                    </td>
                    <td><?php echo (int) $item['quantity']; ?></td>
                    <td>R$ <?php echo number_format((float) $item['unit_price'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format((float) $item['unit_price'] * (int) $item['quantity'], 2, ',', '.'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3" style="text-align:right;">Total:</td><td style="font-weight:700; color:var(--ml-accent);">R$ <?php echo number_format((float) $order['total'], 2, ',', '.'); ?></td></tr></tfoot>
        </table>

        <?php if ($order['shipping_method'] || $order['payment_method']): ?>
        <div style="margin-top:20px; display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div>
                <h4 style="margin-bottom:8px;"><i class="fas fa-truck" style="color:var(--ml-accent);"></i> Frete</h4>
                <p style="font-size:0.9rem; color:var(--ml-text-secondary);">
                    <?php echo e($order['shipping_method'] ?? '—'); ?><br>
                    <?php if ((float) $order['shipping_cost'] > 0): ?>Custo: R$ <?php echo number_format((float) $order['shipping_cost'], 2, ',', '.'); ?><?php else: ?><span style="color:var(--ml-green);">Grátis</span><?php endif; ?>
                </p>
            </div>
            <div>
                <h4 style="margin-bottom:8px;"><i class="fas fa-credit-card" style="color:var(--ml-accent);"></i> Pagamento</h4>
                <p style="font-size:0.9rem; color:var(--ml-text-secondary);">
                    <?php echo e($payLabels[$order['payment_method']] ?? $order['payment_method'] ?? '—'); ?><br>
                    Status: <?php echo $order['payment_status'] === 'paid' ? '<span style="color:var(--ml-green);">Pago</span>' : '<span style="color:var(--ml-text-muted);">Pendente</span>'; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top:20px; display:flex; gap:12px; flex-wrap:wrap;">
            <a href="<?php echo e(base_url('pages/auth/orders.php')); ?>" class="ml-btn"><i class="fas fa-arrow-left"></i> Voltar</a>
            <?php if ($order['status'] === 'pending'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Tem certeza que deseja cancelar este pedido?')">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="ml-btn ml-btn-danger"><i class="fas fa-times-circle"></i> Cancelar Pedido</button>
            </form>
            <?php endif; ?>
            <a href="<?php echo e(base_url('pages/download-comprovante.php?id=' . (int) $order['id'])); ?>" class="ml-btn ml-btn-sm" target="_blank"><i class="fas fa-file-pdf"></i> Baixar Comprovante</a>
            <form method="post" action="<?php echo e(base_url('pages/auth/comprovante-resend.php')); ?>" style="display:inline" onsubmit="return confirm('Reenviar o comprovante por e-mail?')">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                <button type="submit" class="ml-btn ml-btn-sm" style="background:#6c757d;"><i class="fas fa-redo"></i> Reenviar por E-mail</button>
            </form>
        </div>
    </div>
</section>

<?php account_layout_foot();