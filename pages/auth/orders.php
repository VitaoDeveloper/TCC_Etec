<?php
$page_title = 'Meus Pedidos - Royal Tech';
$breadcrumb_title = 'Meus Pedidos';
$current_page = 'pedidos';
$base_path = '../../';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

include '../../database/connection.php';
$userId = (int) $_SESSION['user_id'];

$statusLabels = [
    'pending' => 'Pendente',
    'paid' => 'Pago',
    'shipped' => 'Enviado',
    'delivered' => 'Concluído',
    'canceled' => 'Cancelado',
];

$stmt = $pdo->prepare('
    SELECT o.id, o.status, o.total, o.created_at,
        (SELECT COUNT(*) FROM e5_order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM e5_orders o
    WHERE o.user_id = :uid
    ORDER BY o.created_at DESC
');
$stmt->execute([':uid' => $userId]);
$orders = $stmt->fetchAll();

include '../../components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;"><div class="container" style="max-width:800px; margin:0 auto;">
    <div class="ml-section-header">
        <h2 class="ml-section-title">Meus Pedidos</h2>
        <span class="ml-main-count"><?php echo count($orders); ?> pedido(s)</span>
    </div>

    <?php if (empty($orders)): ?>
        <div class="ml-empty">
            <i class="fas fa-box-open"></i>
            <h3>Nenhum pedido ainda</h3>
            <p>Faça suas compras e acompanhe seus pedidos aqui.</p>
            <p style="margin-top: 16px;"><a href="../products/products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Ver Produtos</a></p>
        </div>
    <?php else: ?>
        <div class="ml-table-wrap">
            <table class="ml-table">
                <thead><tr><th>Pedido</th><th>Data</th><th>Itens</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?php echo str_pad((string)$o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                        <td><?php echo (int)$o['item_count']; ?></td>
                        <td>R$ <?php echo number_format((float)$o['total'], 2, ',', '.'); ?></td>
                        <td><span class="status-badge status-<?php echo $o['status'] === 'paid' || $o['status'] === 'delivered' ? 'active' : ($o['status'] === 'canceled' ? 'inactive' : 'pending'); ?>"><?php echo htmlspecialchars($statusLabels[$o['status']] ?? $o['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><a href="order-detail.php?id=<?php echo (int)$o['id']; ?>" class="ml-btn" style="padding:4px 12px; font-size:0.8rem;"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div style="text-align:center; margin-top:15px;"><a href="profile.php" class="ml-btn"><i class="fas fa-user"></i> Meu Perfil</a></div>
</div></section>
<?php include '../../components/footer.php'; ?>
