<?php
$page_title = 'Relatórios - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';

$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM e5_orders WHERE status != 'canceled'")->fetchColumn();
$totalOrders = $pdo->query('SELECT COUNT(*) FROM e5_orders')->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM e5_users WHERE role = 'customer'")->fetchColumn();

$avgTicket = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

$prevMonth = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM e5_orders WHERE status != 'canceled' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$currMonth = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM e5_orders WHERE status != 'canceled' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$revenueChange = $prevMonth > 0 ? round(($currMonth - $prevMonth) / $prevMonth * 100, 1) : 0;

$prevOrders = $pdo->query("SELECT COUNT(*) FROM e5_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$currOrders = $pdo->query("SELECT COUNT(*) FROM e5_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$orderChange = $prevOrders > 0 ? round(($currOrders - $prevOrders) / $prevOrders * 100, 1) : 0;

$prevCustomers = $pdo->query("SELECT COUNT(*) FROM e5_users WHERE role = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$currCustomers = $pdo->query("SELECT COUNT(*) FROM e5_users WHERE role = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$customerChange = $prevCustomers > 0 ? round(($currCustomers - $prevCustomers) / $prevCustomers * 100, 1) : 0;

$topProducts = $pdo->query('
    SELECT p.name, SUM(oi.quantity) AS qty, SUM(oi.quantity * oi.unit_price) AS revenue
    FROM e5_order_items oi
    INNER JOIN e5_products p ON p.id = oi.product_id
    GROUP BY oi.product_id, p.name
    ORDER BY qty DESC
    LIMIT 5
')->fetchAll();

$ordersByStatus = $pdo->query("SELECT status, COUNT(*) AS cnt FROM e5_orders GROUP BY status ORDER BY cnt DESC")->fetchAll();

$categorySales = $pdo->query('
    SELECT c.name, SUM(oi.quantity) AS qty
    FROM e5_order_items oi
    INNER JOIN e5_products p ON p.id = oi.product_id
    INNER JOIN e5_categories c ON c.id = p.category_id
    GROUP BY c.id, c.name
    ORDER BY qty DESC
')->fetchAll();
$totalCatQty = array_sum(array_column($categorySales, 'qty'));
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
        <?php $activePage = 'reports'; include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Relatórios</h2>
                    <p>Análise detalhada do desempenho da sua loja</p>
                </div>
                <div class="admin-actions">
                    <button class="btn btn-primary" aria-label="Exportar relatório em PDF"><i class="fas fa-download"></i> Exportar PDF</button>
                    <?php include 'header_user_inc.php'; ?>
                </div>
            </header>

            <div class="admin-cards">
                <div class="admin-card">
                    <div class="admin-card-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="admin-card-value">R$ <?php echo number_format($totalRevenue, 2, ',', '.'); ?></div>
                    <div class="admin-card-label">Receita Total</div>
                    <div class="admin-card-change <?php echo $revenueChange >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $revenueChange >= 0 ? 'up' : 'down'; ?>"></i> <?php echo abs($revenueChange); ?>% vs mês anterior
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="admin-card-value"><?php echo $totalOrders; ?></div>
                    <div class="admin-card-label">Total de Pedidos</div>
                    <div class="admin-card-change <?php echo $orderChange >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $orderChange >= 0 ? 'up' : 'down'; ?>"></i> <?php echo abs($orderChange); ?>% vs mês anterior
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon"><i class="fas fa-ticket-alt"></i></div>
                    <div class="admin-card-value">R$ <?php echo number_format($avgTicket, 2, ',', '.'); ?></div>
                    <div class="admin-card-label">Ticket Médio</div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-icon"><i class="fas fa-users"></i></div>
                    <div class="admin-card-value"><?php echo $currCustomers; ?></div>
                    <div class="admin-card-label">Novos Clientes (30 dias)</div>
                    <div class="admin-card-change <?php echo $customerChange >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $customerChange >= 0 ? 'up' : 'down'; ?>"></i> <?php echo abs($customerChange); ?>% vs mês anterior
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <div class="admin-table-container" style="padding: 30px;">
                    <h4 style="margin-bottom: 25px;">Produtos Mais Vendidos</h4>
                    <table class="admin-table">
                        <thead><tr><th>Produto</th><th>Vendas</th><th>Receita</th></tr></thead>
                        <tbody>
                            <?php if (empty($topProducts)): ?>
                            <tr><td colspan="3" style="text-align:center; color:var(--color-gray); padding:30px;">Nenhuma venda registrada.</td></tr>
                            <?php else: foreach ($topProducts as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) $p['qty']; ?></td>
                                <td>R$ <?php echo number_format((float) $p['revenue'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-table-container" style="padding: 30px;">
                    <h4 style="margin-bottom: 25px;">Pedidos por Status</h4>
                    <div style="display:flex; flex-direction:column; gap:15px;">
                        <?php if (empty($ordersByStatus)): ?>
                        <p style="color:var(--color-gray);">Nenhum pedido.</p>
                        <?php else: foreach ($ordersByStatus as $s):
                            $pct = $totalOrders > 0 ? round($s['cnt'] / $totalOrders * 100) : 0;
                        ?>
                        <div>
                            <div style="display:flex; justify-content:space-between;">
                                <span><?php echo ucfirst($s['status']); ?></span>
                                <span style="color:var(--color-primary);"><?php echo $pct; ?>%</span>
                            </div>
                            <div style="height:8px; background:var(--color-black); border-radius:4px; margin-top:5px; overflow:hidden;">
                                <div style="width:<?php echo $pct; ?>%; height:100%; background:var(--color-primary); border-radius:4px;"></div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($categorySales)): ?>
            <div class="admin-table-container" style="padding:30px; margin-top:30px;">
                <h4 style="margin-bottom:25px;">Categorias Populares</h4>
                <div style="display:flex; flex-direction:column; gap:15px; max-width:500px;">
                    <?php foreach ($categorySales as $cat):
                        $pct = $totalCatQty > 0 ? round($cat['qty'] / $totalCatQty * 100) : 0;
                    ?>
                    <div>
                        <div style="display:flex; justify-content:space-between;">
                            <span><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span style="color:var(--color-primary);"><?php echo $pct; ?>%</span>
                        </div>
                        <div style="height:8px; background:var(--color-black); border-radius:4px; margin-top:5px; overflow:hidden;">
                            <div style="width:<?php echo $pct; ?>%; height:100%; background:var(--color-primary); border-radius:4px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
