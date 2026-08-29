<?php
$page_title = 'Gerenciar Pedidos - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/status_labels.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    csrf_require_valid();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');
    $allowed = ['pending', 'paid', 'shipped', 'delivered', 'canceled'];
    if ($orderId > 0 && in_array($newStatus, $allowed, true)) {
        $stmt = $pdo->prepare('UPDATE e5_orders SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
        $_SESSION['admin_message'] = 'Status do pedido atualizado.';
    }
    header('Location: orders.php');
    exit;
}

$filter = (string) ($_GET['status'] ?? '');
$sql = 'SELECT o.id, o.status, o.total, o.shipping_method, o.shipping_cost, o.payment_method, o.created_at, COALESCE(u.name, o.guest_name, "Convidado") AS user_name,
        (SELECT COUNT(*) FROM e5_order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM e5_orders o LEFT JOIN e5_users u ON u.id = o.user_id';
$params = [];
if ($filter !== '' && in_array($filter, array_keys($statusLabels), true)) {
    $sql .= ' WHERE o.status = :status';
    $params[':status'] = $filter;
}
$sql .= ' ORDER BY o.created_at DESC';
$orders = $pdo->prepare($sql);
$orders->execute($params);
$orders = $orders->fetchAll();

$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);
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
                    <h2>Gerenciar Pedidos</h2>
                    <p><?php echo count($orders); ?> pedido(s) encontrado(s)</p>
                </div>
                <div class="admin-actions">
                    <a href="?status=" class="btn btn-secondary <?php echo $filter === '' ? 'active' : ''; ?>">Todos</a>
                    <?php foreach ($statusLabels as $key => $info): ?>
                    <a href="?status=<?php echo $key; ?>" class="btn btn-secondary <?php echo $filter === $key ? 'active' : ''; ?>"><?php echo $info['label']; ?></a>
                    <?php endforeach; ?>
                </div>
            </header>
            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th>Itens</th>
                                <th>Total</th>
                                <th>Frete</th>
                                <th>Pagamento</th>
                                <th>Status</th>
                                <th>Ver</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                            <tr><td colspan="10" style="text-align:center; color:var(--color-gray); padding:40px;">Nenhum pedido encontrado.</td></tr>
                            <?php else: foreach ($orders as $o):
                                $info = $statusLabels[$o['status']] ?? ['label' => $o['status'], 'class' => ''];
                                $payLabel = ['pix'=>'Pix','boleto'=>'Boleto','credit'=>'Cartão','delivery'=>'Entrega'];
                            ?>
                            <tr>
                                <td>#<?php echo str_pad((string) $o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($o['user_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($o['created_at'])); ?></td>
                                <td><?php echo (int) $o['item_count']; ?> item(ns)</td>
                                <td>R$ <?php echo number_format((float) $o['total'], 2, ',', '.'); ?></td>
                                <td><?php echo $o['shipping_method'] ? htmlspecialchars($o['shipping_method'], ENT_QUOTES, 'UTF-8') . '<br><small>R$ ' . number_format((float)$o['shipping_cost'],2,',','.') . '</small>' : '—'; ?></td>
                                <td><?php echo htmlspecialchars($payLabel[$o['payment_method']] ?? $o['payment_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="status-badge <?php echo $info['class']; ?>"><?php echo $info['label']; ?></span></td>
                                <td><a href="order-detail.php?id=<?php echo (int) $o['id']; ?>" class="btn btn-secondary" style="padding:4px 10px; font-size:0.8rem;"><i class="fas fa-eye"></i></a></td>
                                <td>
                                    <form method="POST" style="display:flex; gap:6px; align-items:center;">
                                        <input type="hidden" name="action" value="update_status">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $o['id']; ?>">
                                        <select name="status" style="padding:4px 8px; border:1px solid var(--color-border); border-radius:4px; background:var(--color-black); color:var(--color-white); font-size:0.8rem;">
                                            <?php foreach ($statusLabels as $k => $v): ?>
                                            <option value="<?php echo $k; ?>" <?php echo $k === $o['status'] ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-secondary" style="padding:4px 10px; font-size:0.8rem;" aria-label="Atualizar status do pedido"><i class="fas fa-check"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
