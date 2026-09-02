<?php
$page_title = 'Painel Administrativo - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/image_helpers.php';

$totalOrders = $pdo->query('SELECT COUNT(*) FROM e5_orders')->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM e5_orders WHERE status != 'canceled'")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM e5_users WHERE role = 'customer'")->fetchColumn();
$totalProducts = $pdo->query('SELECT COUNT(*) FROM e5_products')->fetchColumn();

$currMonthOrders = $pdo->query("SELECT COUNT(*) FROM e5_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$prevMonthOrders = $pdo->query("SELECT COUNT(*) FROM e5_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$orderChange = $prevMonthOrders > 0 ? round(($currMonthOrders - $prevMonthOrders) / $prevMonthOrders * 100, 1) : 0;

$currMonthRevenue = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM e5_orders WHERE status != 'canceled' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$prevMonthRevenue = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM e5_orders WHERE status != 'canceled' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$revenueChange = $prevMonthRevenue > 0 ? round(($currMonthRevenue - $prevMonthRevenue) / $prevMonthRevenue * 100, 1) : 0;

$currMonthCustomers = $pdo->query("SELECT COUNT(*) FROM e5_users WHERE role = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$prevMonthCustomers = $pdo->query("SELECT COUNT(*) FROM e5_users WHERE role = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$customerChange = $prevMonthCustomers > 0 ? round(($currMonthCustomers - $prevMonthCustomers) / $prevMonthCustomers * 100, 1) : 0;

$lowStockCount = $pdo->query("SELECT COUNT(*) FROM e5_products WHERE stock <= 5")->fetchColumn();

$recentOrders = $pdo->query('SELECT o.id, o.status, o.total, o.created_at, COALESCE(u.name, o.guest_name, "Convidado") AS user_name FROM e5_orders o LEFT JOIN e5_users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 5')->fetchAll();

$topProducts = $pdo->query('
    SELECT p.id, p.name, p.price, SUM(oi.quantity) AS qty,
        (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
    FROM e5_order_items oi
    INNER JOIN e5_products p ON p.id = oi.product_id
    GROUP BY oi.product_id, p.name, p.price, p.id
    ORDER BY qty DESC
    LIMIT 4
')->fetchAll();
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
        <?php $activePage = 'dashboard'; include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Dashboard</h2>
                    <p>Bem-vindo ao painel de administração</p>
                </div>
                <div class="admin-actions">
                    <button class="action-btn" aria-label="Notificações"><i class="fas fa-bell"></i></button>
                    <button class="action-btn" aria-label="Mensagens"><i class="fas fa-envelope"></i></button>
                    <div class="admin-user">
                        <img src="../../assets/img/placeholder-avatar.svg" alt="Admin">
                        <span>Administrador</span>
                    </div>
                </div>
            </header>

            <div class="admin-cards">
                <div class="admin-card">
                    <div class="admin-card-icon"><i class="fas fa-shopping-bag"></i></div>
                    <div class="admin-card-value"><?php echo $totalOrders; ?></div>
                    <div class="admin-card-label">Total de Pedidos</div>
                    <div class="admin-card-change <?php echo $orderChange >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $orderChange >= 0 ? 'up' : 'down'; ?>"></i> <?php echo abs($orderChange); ?>% este mês
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="admin-card-value">R$ <?php echo number_format($totalRevenue, 2, ',', '.'); ?></div>
                    <div class="admin-card-label">Receita Total</div>
                    <div class="admin-card-change <?php echo $revenueChange >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $revenueChange >= 0 ? 'up' : 'down'; ?>"></i> <?php echo abs($revenueChange); ?>% este mês
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon"><i class="fas fa-users"></i></div>
                    <div class="admin-card-value"><?php echo $totalCustomers; ?></div>
                    <div class="admin-card-label">Total de Clientes</div>
                    <div class="admin-card-change <?php echo $customerChange >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $customerChange >= 0 ? 'up' : 'down'; ?>"></i> <?php echo abs($customerChange); ?>% este mês
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon"><i class="fas fa-box"></i></div>
                    <div class="admin-card-value"><?php echo $totalProducts; ?></div>
                    <div class="admin-card-label">Produtos</div>
                    <div class="admin-card-change <?php echo $lowStockCount > 0 ? 'negative' : 'positive'; ?>" style="<?php echo $lowStockCount > 0 ? 'cursor:pointer;' : ''; ?>" title="<?php echo $lowStockCount; ?> produto(s) com estoque baixo">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $lowStockCount; ?> com estoque baixo
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">

                <div class="admin-table-container">
                    <div class="admin-table-header">
                        <h3>Pedidos Recentes</h3>
                        <a href="orders.php" class="btn btn-secondary" style="padding: 8px 20px; font-size: 0.85rem;">Ver Todos</a>
                    </div>
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Cliente</th><th>Status</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                            <tr><td colspan="4" style="text-align:center; color:var(--color-gray); padding:30px;">Nenhum pedido ainda.</td></tr>
                            <?php else: foreach ($recentOrders as $o):
                                $info = $statusLabels[$o['status']] ?? ['label' => $o['status'], 'class' => ''];
                                $label = $info['label'];
                                $class = $info['class'];
                            ?>
                            <tr>
                                <td>#<?php echo str_pad((string)$o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($o['user_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="status-badge <?php echo $class; ?>"><?php echo $label; ?></span></td>
                                <td>R$ <?php echo number_format((float)$o['total'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-table-container">
                    <div class="admin-table-header">
                        <h3>Produtos Populares</h3>
                        <a href="products.php" class="btn btn-secondary" style="padding: 8px 20px; font-size: 0.85rem;">Ver Todos</a>
                    </div>
                    <div style="padding: 20px;">
                        <?php if (empty($topProducts)): ?>
                        <p style="color:var(--color-gray); text-align:center; padding:20px;">Nenhuma venda ainda.</p>
                        <?php else: foreach ($topProducts as $p):
                            $img = renderProductImage((string) ($p['image_path'] ?? ''), '../../');
                        ?>
                        <div class="popular-product" style="display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid var(--color-border);">
                            <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <div style="flex: 1;">
                                <h5 style="font-size: 0.95rem; margin-bottom: 5px;"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <span style="color: var(--color-primary); font-weight: 600;">R$ <?php echo number_format((float)$p['price'], 2, ',', '.'); ?></span>
                            </div>
                            <span class="status-badge status-active"><?php echo (int)$p['qty']; ?> vendas</span>
                        </div>
                        <?php endforeach; endif; ?>
                        <?php if (count($topProducts) < 4): for ($i = count($topProducts); $i < 4; $i++): ?>
                        <div class="popular-product" style="display: flex; align-items: center; gap: 15px; padding: 15px 0; <?php echo $i < 3 ? 'border-bottom: 1px solid var(--color-border);' : ''; ?>">
                            <div style="width:60px;height:60px;border-radius:8px;background:var(--color-black);flex-shrink:0;"></div>
                            <div style="flex:1;"><em style="color:var(--color-gray);">Sem dados</em></div>
                        </div>
                        <?php endfor; endif; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top: 40px;">
                <h3 style="margin-bottom: 20px;">Ações Rápidas</h3>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                    <a href="products.php?action=add" class="quick-action-card" style="background: var(--color-black-light); border: 1px solid var(--color-border); border-radius: 10px; padding: 30px; text-align: center; transition: var(--transition); display: block;">
                        <i class="fas fa-plus-circle" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <h5 style="margin-bottom: 5px;">Novo Produto</h5>
                        <span style="color: var(--color-gray); font-size: 0.85rem;">Adicionar produto</span>
                    </a>
                    <a href="banners.php?action=add" class="quick-action-card" style="background: var(--color-black-light); border: 1px solid var(--color-border); border-radius: 10px; padding: 30px; text-align: center; transition: var(--transition); display: block;">
                        <i class="fas fa-image" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <h5 style="margin-bottom: 5px;">Novo Banner</h5>
                        <span style="color: var(--color-gray); font-size: 0.85rem;">Gerenciar banners</span>
                    </a>
                    <a href="categories.php?action=add" class="quick-action-card" style="background: var(--color-black-light); border: 1px solid var(--color-border); border-radius: 10px; padding: 30px; text-align: center; transition: var(--transition); display: block;">
                        <i class="fas fa-tag" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <h5 style="margin-bottom: 5px;">Nova Categoria</h5>
                        <span style="color: var(--color-gray); font-size: 0.85rem;">Adicionar categoria</span>
                    </a>
                    <a href="reports.php" class="quick-action-card" style="background: var(--color-black-light); border: 1px solid var(--color-border); border-radius: 10px; padding: 30px; text-align: center; transition: var(--transition); display: block;">
                        <i class="fas fa-download" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                        <h5 style="margin-bottom: 5px;">Exportar Relatório</h5>
                        <span style="color: var(--color-gray); font-size: 0.85rem;">Baixar dados</span>
                    </a>
                </div>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
