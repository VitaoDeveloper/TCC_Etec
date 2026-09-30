<?php
/**
 * Fixture de renderização das telas de pedidos da conta.
 *
 * Roda em processo separado porque as páginas emitem <html> e dependem
 * de header/footer legados. Rodar dentro do PHPUnit contaminaria a
 * saída dos outros testes.
 *
 * Para tornar as asserções determinísticas mesmo em banco vazio, a
 * fixture cria um pedido temporário (com um item real do seed) antes de
 * renderizar e o apaga depois. Os dados do pedido temporário vão num
 * arquivo JSON ao lado do HTML:
 *
 *   php tests/fixtures/render_orders.php <list|detail> <saida.html>
 *
 * O JSON de saída (saida.html.meta.json) traz: order_id, order_number
 * (#000N), product_name, order_status e order_total.
 */

if ($argc < 3) {
    fwrite(STDERR, "uso: render_orders.php <list|list-filtered|detail> <saida.html>\n");
    exit(2);
}

$mode    = $argv[1];
$outFile = $argv[2];

// list-filtered renderiza a lista com ?status=paid para conferir a aba
// ativa e o filtro (o pedido temporário nasce 'paid').
$isDetail = $mode === 'detail';
$filter   = $mode === 'list-filtered' ? 'paid' : '';

$_SERVER['SCRIPT_NAME']   = '/TCC_Etec/pages/auth/' . ($isDetail ? 'order-detail.php' : 'orders.php');
$_SERVER['REQUEST_URI']   = $_SERVER['SCRIPT_NAME'] . ($isDetail ? '?id=TMP' : ($filter !== '' ? '?status=' . $filter : ''));
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

if ($filter !== '') {
    $_GET['status'] = $filter;
}

// NO CLI, atribuir $_SESSION antes de session_start() pode ser
// descartado: o PHP substitui o array pela sessão do disco.
session_id('account-orders-fixture');
session_start();

$_SESSION['user_id']   = 16;
$_SESSION['user_role'] = 'customer';

require_once __DIR__ . '/../../database/connection.php';

$stmt = $pdo->prepare('SELECT id, name FROM e5_products ORDER BY id LIMIT 1');
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    fwrite(STDERR, "nenhum produto no catalogo para a fixture\n");
    exit(3);
}

$pdo->prepare('
    INSERT INTO e5_orders
        (user_id, status, total, shipping_method, shipping_cost, payment_method, payment_status, shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code)
    VALUES
        (16, "paid", 1234.56, "correios", 29.90, "pix", "paid", "Centro", "São Paulo", "SP", "01310-100")
')->execute();
$orderId = (int) $pdo->lastInsertId();

$pdo->prepare('
    INSERT INTO e5_order_items (order_id, product_id, quantity, unit_price)
    VALUES (:oid, :pid, 2, 617.28)
')->execute([':oid' => $orderId, ':pid' => (int) $product['id']]);

$meta = [
    'order_id'      => $orderId,
    'order_number'  => '#' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT),
    'product_name'  => $product['name'],
    'order_status'  => 'paid',
    'order_total'   => 'R$ 1.234,56',
    'status_filter' => $filter,
];

if ($isDetail) {
    $_GET['id'] = (string) $orderId;
}

ob_start();
require_once __DIR__ . '/../../pages/auth/' . ($isDetail ? 'order-detail.php' : 'orders.php');
$html = (string) ob_get_clean();

// Limpeza do pedido temporário (os itens caem por ON DELETE CASCADE).
$pdo->prepare('DELETE FROM e5_orders WHERE id = :id')->execute([':id' => $orderId]);

file_put_contents($outFile, $html);
file_put_contents($outFile . '.meta.json', (string) json_encode($meta, JSON_UNESCAPED_UNICODE));