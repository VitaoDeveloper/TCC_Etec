<?php
// Exporta os relatórios do painel em PDF (Dompdf).
include 'auth_check.php';
include '../../database/connection.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
if (!class_exists('\\Dompdf\\Dompdf')) {
    http_response_code(500);
    echo 'Dependência dompdf não instalada. Execute "composer install".';
    exit;
}

$totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM e5_orders WHERE status != 'canceled'")->fetchColumn();
$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM e5_orders')->fetchColumn();
$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM e5_users WHERE role = 'customer'")->fetchColumn();
$avgTicket = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

$prevMonth = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM e5_orders WHERE status != 'canceled' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$currMonth = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM e5_orders WHERE status != 'canceled' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
$revenueChange = $prevMonth > 0 ? round(($currMonth - $prevMonth) / $prevMonth * 100, 1) : 0;

$topProducts = $pdo->query('
    SELECT p.name, SUM(oi.quantity) AS qty, SUM(oi.quantity * oi.unit_price) AS revenue
    FROM e5_order_items oi
    INNER JOIN e5_products p ON p.id = oi.product_id
    GROUP BY oi.product_id, p.name
    ORDER BY qty DESC
    LIMIT 10
')->fetchAll(PDO::FETCH_ASSOC);

$ordersByStatus = $pdo->query('SELECT status, COUNT(*) AS cnt FROM e5_orders GROUP BY status ORDER BY cnt DESC')->fetchAll(PDO::FETCH_ASSOC);

$categorySales = $pdo->query('
    SELECT c.name, SUM(oi.quantity) AS qty
    FROM e5_order_items oi
    INNER JOIN e5_products p ON p.id = oi.product_id
    INNER JOIN e5_categories c ON c.id = p.category_id
    GROUP BY c.id, c.name
    ORDER BY qty DESC
')->fetchAll(PDO::FETCH_ASSOC);
$totalCatQty = array_sum(array_column($categorySales, 'qty'));

$storeName = store_config('store_name');
$generatedAt = date('d/m/Y H:i');

$statusLabels = [
    'pending' => 'Pendente',
    'paid' => 'Pago',
    'shipped' => 'Enviado',
    'delivered' => 'Entregue',
    'canceled' => 'Cancelado',
];

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1a1a1a; font-size: 11px; margin: 0; }
    h1 { font-size: 20px; margin: 0 0 4px; color: #b8962e; }
    .muted { color: #666; }
    .header { border-bottom: 2px solid #d4af37; padding-bottom: 12px; margin-bottom: 20px; }
    .kpis { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
    .kpis td { width: 25%; border: 1px solid #ddd; padding: 12px; text-align: center; }
    .kpi-value { font-size: 16px; font-weight: bold; color: #1a1a1a; }
    .kpi-label { font-size: 10px; color: #666; margin-top: 4px; }
    h2 { font-size: 13px; margin: 22px 0 8px; border-left: 4px solid #d4af37; padding-left: 8px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #1a1a1a; color: #d4af37; padding: 7px; text-align: left; font-size: 10px; }
    table.data td { border-bottom: 1px solid #e5e5e5; padding: 6px 7px; }
    .empty { color: #888; font-style: italic; }
</style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> — Relatório de Desempenho</h1>
        <div class="muted">Gerado em <?php echo $generatedAt; ?></div>
    </div>

    <table class="kpis">
        <tr>
            <td><div class="kpi-value">R$ <?php echo number_format($totalRevenue, 2, ',', '.'); ?></div><div class="kpi-label">Receita Total</div></td>
            <td><div class="kpi-value"><?php echo $totalOrders; ?></div><div class="kpi-label">Total de Pedidos</div></td>
            <td><div class="kpi-value">R$ <?php echo number_format($avgTicket, 2, ',', '.'); ?></div><div class="kpi-label">Ticket Médio</div></td>
            <td><div class="kpi-value"><?php echo $totalCustomers; ?></div><div class="kpi-label">Clientes</div></td>
        </tr>
    </table>

    <h2>Produtos Mais Vendidos</h2>
    <?php if (empty($topProducts)): ?>
        <p class="empty">Nenhuma venda registrada.</p>
    <?php else: ?>
    <table class="data">
        <thead><tr><th>Produto</th><th>Vendas</th><th>Receita</th></tr></thead>
        <tbody>
            <?php foreach ($topProducts as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $p['qty']; ?></td>
                <td>R$ <?php echo number_format((float) $p['revenue'], 2, ',', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h2>Pedidos por Status</h2>
    <?php if (empty($ordersByStatus)): ?>
        <p class="empty">Nenhum pedido.</p>
    <?php else: ?>
    <table class="data">
        <thead><tr><th>Status</th><th>Pedidos</th><th>Percentual</th></tr></thead>
        <tbody>
            <?php foreach ($ordersByStatus as $s):
                $pct = $totalOrders > 0 ? round($s['cnt'] / $totalOrders * 100, 1) : 0;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($statusLabels[$s['status']] ?? ucfirst($s['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $s['cnt']; ?></td>
                <td><?php echo $pct; ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h2>Categorias Populares</h2>
    <?php if (empty($categorySales)): ?>
        <p class="empty">Nenhuma venda por categoria.</p>
    <?php else: ?>
    <table class="data">
        <thead><tr><th>Categoria</th><th>Itens vendidos</th><th>Percentual</th></tr></thead>
        <tbody>
            <?php foreach ($categorySales as $cat):
                $pct = $totalCatQty > 0 ? round($cat['qty'] / $totalCatQty * 100, 1) : 0;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $cat['qty']; ?></td>
                <td><?php echo $pct; ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();

$dompdf = new \Dompdf\Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'relatorio-' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
