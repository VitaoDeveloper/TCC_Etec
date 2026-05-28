<?php
$page_title = 'Gerenciar Pedidos - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (!csrf_verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página.');
    }
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

$statusLabels = [
    'pending' => ['label' => 'Pendente', 'class' => 'status-pending'],
    'paid' => ['label' => 'Pago', 'class' => 'status-active'],
    'shipped' => ['label' => 'Enviado', 'class' => 'status-processing'],
    'delivered' => ['label' => 'Concluído', 'class' => 'status-active'],
    'canceled' => ['label' => 'Cancelado', 'class' => 'status-inactive'],
];

$filter = (string) ($_GET['status'] ?? '');
$sql = 'SELECT o.id, o.status, o.total, o.created_at, u.name AS user_name,
        (SELECT COUNT(*) FROM e5_order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id';
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
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-logo"><a href="index.php"><span class="logo-icon"><i class="fas fa-crown"></i></span><span class="logo-text">Royal<span>Tech</span></span></a></div>
            <nav class="admin-nav">
                <div class="admin-nav-item"><a href="index.php" class="admin-nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></div>
                <div class="admin-nav-item"><a href="products.php" class="admin-nav-link"><i class="fas fa-box"></i><span>Produtos</span></a></div>
                <div class="admin-nav-item"><a href="categories.php" class="admin-nav-link"><i class="fas fa-tags"></i><span>Categorias</span></a></div>
                <div class="admin-nav-item"><a href="orders.php" class="admin-nav-link active"><i class="fas fa-shopping-cart"></i><span>Pedidos</span></a></div>
                <div class="admin-nav-item"><a href="customers.php" class="admin-nav-link"><i class="fas fa-users"></i><span>Clientes</span></a></div>
                <div class="admin-nav-item"><a href="banners.php" class="admin-nav-link"><i class="fas fa-images"></i><span>Banners</span></a></div>
                <div class="admin-nav-item"><a href="reports.php" class="admin-nav-link"><i class="fas fa-chart-bar"></i><span>Relatórios</span></a></div>
                <div class="admin-nav-item"><a href="settings.php" class="admin-nav-link"><i class="fas fa-cogs"></i><span>Configurações</span></a></div>
            </nav>
        </aside>
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
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--color-gray); padding:40px;">Nenhum pedido encontrado.</td></tr>
                        <?php else: foreach ($orders as $o):
                            $info = $statusLabels[$o['status']] ?? ['label' => $o['status'], 'class' => ''];
                        ?>
                        <tr>
                            <td>#<?php echo str_pad((string) $o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($o['user_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($o['created_at'])); ?></td>
                            <td><?php echo (int) $o['item_count']; ?> item(ns)</td>
                            <td>R$ <?php echo number_format((float) $o['total'], 2, ',', '.'); ?></td>
                            <td><span class="status-badge <?php echo $info['class']; ?>"><?php echo $info['label']; ?></span></td>
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
                                    <button type="submit" class="btn btn-secondary" style="padding:4px 10px; font-size:0.8rem;"><i class="fas fa-check"></i></button>
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
