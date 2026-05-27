<?php
$page_title = 'Meus Pedidos - Royal Tech';
$show_breadcrumb = true;
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
<section class="section"><div class="container" style="max-width:800px; margin:0 auto;">
    <div class="section-header"><h2>Meus Pedidos</h2><p><?php echo count($orders); ?> pedido(s)</p></div>

    <?php if (empty($orders)): ?>
        <div style="text-align:center; padding:60px 20px;">
            <i class="fas fa-box-open" style="font-size:64px; color:var(--color-gold); opacity:0.5; margin-bottom:20px;"></i>
            <h3>Nenhum pedido ainda</h3>
            <p style="margin-bottom:20px;">Faça suas compras e acompanhe seus pedidos aqui.</p>
            <a href="../products/products.php" class="btn btn-primary"><i class="fas fa-store"></i> Ver Produtos</a>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead><tr><th>Pedido</th><th>Data</th><th>Itens</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?php echo str_pad((string)$o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                        <td><?php echo (int)$o['item_count']; ?></td>
                        <td>R$ <?php echo number_format((float)$o['total'], 2, ',', '.'); ?></td>
                        <td><span class="status-badge status-<?php echo $o['status'] === 'paid' || $o['status'] === 'delivered' ? 'active' : ($o['status'] === 'canceled' ? 'inactive' : 'pending'); ?>"><?php echo $statusLabels[$o['status']] ?? $o['status']; ?></span></td>
                        <td><a href="order-detail.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-secondary" style="padding:4px 12px; font-size:0.8rem;"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div style="text-align:center; margin-top:15px;"><a href="profile.php" class="btn btn-secondary"><i class="fas fa-user"></i> Meu Perfil</a></div>
</div></section>
<?php include '../../components/footer.php'; ?>
