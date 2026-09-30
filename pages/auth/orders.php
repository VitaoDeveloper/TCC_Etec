<?php

declare(strict_types=1);

/**
 * Meus Pedidos — histórico de compras do cliente no shell da conta.
 *
 * A tela vive em account_layout_head()/account_layout_foot(), então a
 * sidebar usa base_url() e o item "Meus Pedidos" fica marcado como
 * atual. A consulta lista os pedidos do usuário da sessão, do mais
 * recente para o mais antigo.
 *
 * A marcação da coluna de status reusa os badges .status-* e os rótulos
 * de status_labels.php. O id #secao-pedidos e o título
 * .account-page-title são os ganchos usados pelos testes de render.
 */

require_once __DIR__ . '/../../includes/account_layout.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../database/connection.php';

$user = account_require_login($pdo);

$userId = (int) $user['id'];

$stmt = $pdo->prepare('
    SELECT o.id, o.status, o.total, o.created_at,
        (SELECT COUNT(*) FROM e5_order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM e5_orders o
    WHERE o.user_id = :uid
    ORDER BY o.created_at DESC
');
$stmt->execute([':uid' => $userId]);
$orders = $stmt->fetchAll();

$page_title = 'Meus Pedidos - Royal Tech';

account_layout_head($user, 'pedidos');
?>

<div class="account-page-header">
    <h1 class="account-page-title">Meus Pedidos</h1>
    <p class="account-page-subtitle">Acompanhe o andamento e o histórico das suas compras.</p>
</div>

<section class="account-card" id="secao-pedidos">
    <?php if (empty($orders)): ?>
        <div class="ml-empty">
            <i class="fas fa-box-open"></i>
            <h3>Nenhum pedido ainda</h3>
            <p>Faça suas compras e acompanhe seus pedidos aqui.</p>
            <p style="margin-top: 16px;">
                <a href="<?php echo e(base_url('pages/products/products.php')); ?>" class="ml-btn ml-btn-primary">
                    <i class="fas fa-store"></i> Ver Produtos
                </a>
            </p>
        </div>
    <?php else: ?>
        <div class="ml-table-wrap">
            <table class="ml-table">
                <thead><tr><th>Pedido</th><th>Data</th><th>Itens</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o):
                    $orderBadge = $o['status'] === 'paid' || $o['status'] === 'delivered'
                        ? 'active'
                        : ($o['status'] === 'canceled' ? 'inactive' : 'pending');
                ?>
                    <tr>
                        <td>#<?php echo str_pad((string) $o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                        <td><?php echo (int) $o['item_count']; ?></td>
                        <td>R$ <?php echo number_format((float) $o['total'], 2, ',', '.'); ?></td>
                        <td><span class="status-badge status-<?php echo $orderBadge; ?>"><?php echo e($statusLabelsFlat[$o['status']] ?? $o['status']); ?></span></td>
                        <td><a href="<?php echo e(base_url('pages/auth/order-detail.php?id=' . (int) $o['id'])); ?>" class="ml-btn" style="padding:4px 12px; font-size:0.8rem;"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php account_layout_foot();