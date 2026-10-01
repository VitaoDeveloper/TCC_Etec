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
    fwrite(STDERR, "uso: render_orders.php <modo> <saida.html>\n");
    exit(2);
}

$mode    = $argv[1];
$outFile = $argv[2];

// Modos de paginação: criam 12 pedidos, um a mais que a página, e
// renderizam a página pedida. Com 10 linhas ou menos o controle de
// paginação nem aparece, e o teste passaria sem exercitar nada.
//
// 'list-page2-paid' soma o filtro de status: os 12 pedidos temporários
// nascem 'paid', então a aba filtrada também pagina — e os links
// precisam carregar o status junto, senão o cliente cai na lista toda.
$listModes = [
    'list-page1'        => ['page' => 1],
    'list-page2'        => ['page' => 2],
    'list-page-clamped' => ['page' => 999],
    'list-page2-paid'   => ['page' => 2, 'filter' => 'paid'],
];
$listMode = $listModes[$mode] ?? null;

$isDetail = $mode === 'detail';
$filter   = $listMode['filter'] ?? ($mode === 'list-filtered' ? 'paid' : '');
$listPage = $listMode['page'] ?? null;

$query = [];
if ($filter !== '') {
    $query['status'] = $filter;
}
if ($listPage !== null) {
    $query['page'] = (string) $listPage;
}

$_SERVER['SCRIPT_NAME']   = '/TCC_Etec/pages/auth/' . ($isDetail ? 'order-detail.php' : 'orders.php');
$_SERVER['REQUEST_URI']   = $_SERVER['SCRIPT_NAME'] . ($query === [] ? '' : '?' . http_build_query($query));
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

foreach ($query as $key => $value) {
    $_GET[$key] = $value;
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

// Quantos pedidos temporários criar. Os modos de paginação precisam de
// mais de uma página cheia para o controle aparecer.
$howMany = $listPage !== null ? 12 : 1;

$insertOrder = $pdo->prepare('
    INSERT INTO e5_orders
        (user_id, status, total, shipping_method, shipping_cost, payment_method, payment_status, shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code)
    VALUES
        (16, "paid", 1234.56, "correios", 29.90, "pix", "paid", "Centro", "São Paulo", "SP", "01310-100")
');
$insertItem = $pdo->prepare('
    INSERT INTO e5_order_items (order_id, product_id, quantity, unit_price)
    VALUES (:oid, :pid, 2, 617.28)
');

// created_at distintos e crescentes para a ordenação ser determinística:
// sem isso o MySQL pode devolver as 12 linhas em qualquer ordem entre
// execuções e a comparação entre a página 1 e a página 2 não fecharia.
$tempIds = [];
for ($i = 0; $i < $howMany; $i++) {
    $insertOrder->execute();
    $orderId = (int) $pdo->lastInsertId();
    $tempIds[] = $orderId;

    $insertItem->execute([':oid' => $orderId, ':pid' => (int) $product['id']]);

    // Placeholders separados: com EMULATE_PREPARES desligado o PDO
    // nao aceita repetir o mesmo nome paramétrico numa statement.
    $stamp = date('Y-m-d H:i:s', strtotime('2026-01-01 00:00:00') + $i * 3600);
    $pdo->prepare('UPDATE e5_orders SET created_at = :c, updated_at = :u WHERE id = :id')
        ->execute([':c' => $stamp, ':u' => $stamp, ':id' => $orderId]);
}

$orderId = $tempIds[0];

$meta = [
    'order_id'      => $orderId,
    'order_number'  => '#' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT),
    'product_name'  => $product['name'],
    'order_status'  => 'paid',
    'order_total'   => 'R$ 1.234,56',
    'status_filter' => $filter,
    'list_page'     => $listPage,
    'temp_count'    => $howMany,
];

if ($isDetail) {
    $_GET['id'] = (string) $orderId;
}

ob_start();
require_once __DIR__ . '/../../pages/auth/' . ($isDetail ? 'order-detail.php' : 'orders.php');
$html = (string) ob_get_clean();

// Limpeza dos pedidos temporários (os itens caem por ON DELETE CASCADE).
// Registrada como shutdown, e não chamada no fim do script: uma exceção
// no meio da renderização saltaria a linha de baixo e deixaria 12
// pedidos órfãos no banco de desenvolvimento — foi o que aconteceu
// enquanto a paginação estava sendo escrita.
$delete = $pdo->prepare('DELETE FROM e5_orders WHERE id = :id');
register_shutdown_function(static function () use ($delete, $tempIds): void {
    foreach ($tempIds as $tempId) {
        try {
            $delete->execute([':id' => $tempId]);
        } catch (Throwable $ignored) {
            // Nada a fazer no shutdown: o teste já falhou de vez.
        }
    }
});

file_put_contents($outFile, $html);
file_put_contents($outFile . '.meta.json', (string) json_encode($meta, JSON_UNESCAPED_UNICODE));