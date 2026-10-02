<?php
/**
 * Fixture de renderização das telas de pedidos da conta.
 *
 * Roda em processo separado porque as páginas emitem <html> e dependem
 * de header/footer legados. Rodar dentro do PHPUnit contaminaria a saída
 * dos outros testes.
 *
 * Cada modo monta um cenário (pedidos temporários + query string) e
 * renderiza a página real. Os pedidos nascem em estados diferentes de
 * propósito: a tela promete um card por estado — pendente com Pix,
 * enviado com rastreio, entregue, cancelado e reembolsado — e um teste
 * que só enxerga "pago" não provaria nenhum deles.
 *
 * Os ids dos pedidos criados vão para o JSON ao lado do HTML, e é por
 * eles que as asserções localizam o card: o banco de desenvolvimento
 * pode ter pedidos reais do mesmo usuário, e casar pelo total não
 * distingue um temporário de um R$ 1.234,56 que já existia.
 *
 *   php tests/fixtures/render_orders.php <modo> <saida.html>
 *
 * Saída: saida.html e saida.html.meta.json com temp_ids, temp_number,
 * product_name, mode, query e demais dados que o teste precisa.
 */

if ($argc < 3) {
    fwrite(STDERR, "uso: render_orders.php <modo> <saida.html>\n");
    exit(2);
}

/**
 * SELECTs disparadas na sessão MySQL até aqui.
 *
 * SHOW SESSION STATUS é por conexão, e a renderização usa a mesma do
 * fixture: a diferença antes/depois é exatamente o que a página custou.
 */
function render_orders_count_selects(PDO $pdo): int
{
    $value = $pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetchColumn(1);

    return (int) $value;
}

$mode    = $argv[1];
$outFile = $argv[2];

/**
 * Catálogo de cenários.
 *
 * `count` pedidos temporários com o mesmo estado; `status`/`payment`/
 * `method` descrevem o card esperado; `created` é a data do primeiro,
 * somando `step_min` a cada inserção; `expires_in` coloca um Pix ainda
 * válido (segundos à frente de agora) para exercitar o countdown;
 * `shipment` cria uma linha em e5_shipments, que é o que dá URL de
 * rastreio ao card enviado.
 */
$scenarios = [
    // -------- lista básica --------
    'list' => [
        'query'  => [],
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-05 10:00:00']],
    ],

    // -------- paginação: 12 pedidos, um a mais que a página --------
    // 'no_orders' de propósito: com um pedido antigo do usuário de
    // teste, "sort=recent" trazia esse pedido para a primeira página e a
    // contagem de cards temporários ficava 9 em vez de 10 — o teste
    // mediria o banco de desenvolvimento, não a paginação.
    'list-page1' => [
        'query'  => ['page' => '1'],
        'count'  => 12,
        'user'   => 'no_orders',
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-01 00:00:00', 'step_min' => 60]],
    ],
    'list-page2' => [
        'query'  => ['page' => '2'],
        'count'  => 12,
        'user'   => 'no_orders',
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-01 00:00:00', 'step_min' => 60]],
    ],
    'list-page-clamped' => [
        'query'  => ['page' => '999'],
        'count'  => 12,
        'user'   => 'no_orders',
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-01 00:00:00', 'step_min' => 60]],
    ],
    // A aba filtrada também pagina, e os links de paginação precisam
    // carregar o status junto — senão o cliente cai na lista toda.
    'list-page2-paid' => [
        'query'  => ['page' => '2', 'status' => 'paid'],
        'count'  => 12,
        'user'   => 'no_orders',
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-01 00:00:00', 'step_min' => 60]],
    ],

    // -------- ordenação --------
    'list-sort-oldest' => [
        'user'   => 'no_orders',
        'query' => ['sort' => 'oldest'],
        // Datas crescentes: em "oldest" a primeira da lista é a de janeiro.
        'count' => 3,
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-01 09:00:00', 'step_min' => 60]],
    ],
    'list-sort-value' => [
        'user'   => 'no_orders',
        'query' => ['sort' => 'value_desc'],
        'count' => 3,
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'total' => [300.00, 900.00, 100.00], 'created' => '2026-01-01 09:00:00', 'step_min' => 60]],
    ],
    'list-sort-invalid' => [
        'user'   => 'no_orders',
        'query' => ['sort' => 'lixo'],
        'count' => 2,
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-01 09:00:00', 'step_min' => 60]],
    ],

    // -------- busca --------
    'list-search-number' => [
        'query'  => ['q' => '{firstNumber}'],
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-05 10:00:00']],
    ],
    'list-search-hash' => [
        'query'  => ['q' => '#{firstNumber}'],
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-05 10:00:00']],
    ],
    'list-search-product' => [
        'query'  => ['q' => '{productName}'],
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-05 10:00:00']],
    ],
    // "%" é o curinga do LIKE: sem escape a busca traria o histórico
    // inteiro, que é o bug que este modo existe para pegar.
    'list-search-wildcard' => [
        'query'  => ['q' => '%'],
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-05 10:00:00']],
    ],
    'list-search-none' => [
        'query'  => ['q' => '999999999'],
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-05 10:00:00']],
    ],

    // -------- chips e estados dos cards --------
    'list-filtered' => [
        'query'  => ['status' => 'paid'],
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-05 10:00:00']],
    ],
    'list-status-refunded' => [
        'query'  => ['status' => 'refunded'],
        'orders' => [['status' => 'canceled', 'payment' => 'refunded', 'method' => 'pix', 'created' => '2026-01-06 10:00:00', 'history' => 'canceled']],
    ],
    'list-pending-pix' => [
        'query'  => ['status' => 'pending'],
        'orders' => [['status' => 'pending', 'payment' => 'pending', 'method' => 'pix', 'created' => '2026-01-07 10:00:00', 'expires_in' => 3600]],
    ],
    'list-shipped' => [
        'query'  => ['status' => 'shipped'],
        'orders' => [['status' => 'shipped', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-08 10:00:00', 'tracking' => 'BR123456789BR', 'shipment' => true]],
    ],
    'list-delivered' => [
        'query'  => ['status' => 'delivered'],
        'orders' => [['status' => 'delivered', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-09 10:00:00']],
    ],
    'list-canceled' => [
        'query'  => ['status' => 'canceled'],
        'orders' => [['status' => 'canceled', 'payment' => 'canceled', 'method' => 'pix', 'created' => '2026-01-10 10:00:00', 'history' => 'canceled']],
    ],

    // -------- estados vazios com número exato --------
    // Cliente sem histórico: os quatro cards do resumo têm que sair em
    // zero. Sem um usuário assim, o banco de desenvolvimento contaminaria
    // toda conta e a asserção viraria décorative.
    'list-no-orders' => [
        'query'  => [],
        'user'   => 'no_orders',
        'orders' => [],
    ],

    // Um único pedido pago: 1 total, 1 em andamento, 0 entregues e
    // R$ 1.234,56 comprados.
    'list-single-order' => [
        'query'  => [],
        'user'   => 'no_orders',
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-05 10:00:00']],
    ],

    // Um pedido de cadacoinsidência: é o cenário que fecha a conta do
    // resumo. Pendente e cancelado não entram em "total comprado", o
    // cancelado com estorno conta só em "Reembolsados", e a soma dos
    // sete chips tem que voltar ao total de pedidos.
    'list-mixed' => [
        'query'  => [],
        'user'   => 'no_orders',
        'orders' => [
            ['status' => 'pending',   'payment' => 'pending',  'method' => 'pix', 'created' => '2026-01-02 10:00:00'],
            ['status' => 'paid',      'payment' => 'paid',     'method' => 'pix', 'created' => '2026-01-03 10:00:00', 'total' => 100.00],
            ['status' => 'delivered', 'payment' => 'paid',     'method' => 'pix', 'created' => '2026-01-04 10:00:00', 'total' => 200.00],
            ['status' => 'canceled',  'payment' => 'refunded', 'method' => 'pix', 'created' => '2026-01-05 10:00:00', 'total' => 400.00, 'history' => 'canceled'],
        ],
    ],

// Mesmo histórico do list-mixed, mas com um chip ativo. É o cenário
    // que pega o contador usando o próprio filtro: sem ele, "Todos" e
    // "Pagos" mostrariam o mesmo número e o cliente perderia a noção de
    // quanto tinha em cada estado.
    'list-mixed-filtered' => [
        'query'  => ['status' => 'paid'],
        'user'   => 'no_orders',
        'orders' => [
            ['status' => 'pending',   'payment' => 'pending',  'method' => 'pix', 'created' => '2026-01-02 10:00:00'],
            ['status' => 'paid',      'payment' => 'paid',     'method' => 'pix', 'created' => '2026-01-03 10:00:00', 'total' => 100.00],
            ['status' => 'delivered', 'payment' => 'paid',     'method' => 'pix', 'created' => '2026-01-04 10:00:00', 'total' => 200.00],
            ['status' => 'canceled',  'payment' => 'refunded', 'method' => 'pix', 'created' => '2026-01-05 10:00:00', 'total' => 400.00, 'history' => 'canceled'],
        ],
    ],

    // -------- detalhe (inalterado) --------
    'detail' => [
        'query'  => [],
        'orders' => [['status' => 'paid', 'payment' => 'paid', 'method' => 'pix', 'created' => '2026-01-05 10:00:00']],
    ],
];

if (!array_key_exists($mode, $scenarios)) {
    fwrite(STDERR, "modo desconhecido: {$mode}\n");
    exit(2);
}

$scenario = $scenarios[$mode];
$query    = $scenario['query'];
$isDetail = $mode === 'detail';

$_SERVER['SCRIPT_NAME']   = '/TCC_Etec/pages/auth/' . ($isDetail ? 'order-detail.php' : 'orders.php');
$_SERVER['REQUEST_URI']   = $_SERVER['SCRIPT_NAME'] . ($query === [] ? '' : '?' . http_build_query($query));
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

$_GET = $query;

// NO CLI, atribuir $_SESSION antes de session_start() pode ser
// descartado: o PHP substitui o array pela sessão do disco.
session_id('account-orders-fixture');
session_start();

$_SESSION['user_id']   = 16;
$_SESSION['user_role'] = 'customer';

require_once __DIR__ . '/../../database/connection.php';

/**
 * O usuário do cenário.
 *
 * 'no_orders' = o primeiro cliente sem nenhum pedido, escolhido por SQL
 * em vez de id fixo: um id "de mentirinha" pode ganhar uma compra amanhã
 * e o teste passaria a falhar sem que nada quebre. Nos cenários com
 * números exatos de stat, é esse usuário que garante que o valor lido é
 * só o que a fixture criou.
 */
$userId = 16;
if (($scenario['user'] ?? '') === 'no_orders') {
    $emptyUser = $pdo->query(
        "SELECT u.id
           FROM e5_users u
          WHERE u.role = 'customer'
            AND NOT EXISTS (SELECT 1 FROM e5_orders o WHERE o.user_id = u.id)
          ORDER BY u.id ASC
          LIMIT 1"
    )->fetchColumn();

    if ($emptyUser === false) {
        fwrite(STDERR, "nenhum cliente sem pedidos no banco\n");
        exit(4);
    }

    $userId = (int) $emptyUser;
}

$productStmt = $pdo->prepare('SELECT id, name FROM e5_products ORDER BY id LIMIT 1');
$productStmt->execute();
$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product && $scenario['orders'] !== []) {
    fwrite(STDERR, "nenhum produto no catalogo para a fixture\n");
    exit(3);
}
$productName = (string) ($product['name'] ?? '');

// ---------------------------------------------------------------------
//  Montagem dos pedidos temporários
// ---------------------------------------------------------------------
$insertOrder = $pdo->prepare('
    INSERT INTO e5_orders
        (user_id, status, total, shipping_method, shipping_cost, payment_method, payment_status,
         payment_expires_at, tracking_code, shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code)
    VALUES
        (:uid, :status, :total, "correios", 29.90, :method, :payment, :expires, :tracking, "Centro", "São Paulo", "SP", "01310-100")
');
$insertItem = $pdo->prepare('
    INSERT INTO e5_order_items (order_id, product_id, quantity, unit_price) VALUES (:oid, :pid, :qty, :price)
');
$insertHistory = $pdo->prepare('
    INSERT INTO e5_order_history (order_id, status, note, created_at)
    VALUES (:oid, :status, "Pedido criado pela fixture", NOW())
');
$insertShipment = $pdo->prepare('
    INSERT INTO e5_shipments (order_id, carrier, service, tracking_code, label_url, status, price, created_at)
    VALUES (:oid, "Correios", "PAC", :tracking, "https://rastreio.correios.com.br/BR123456789BR", "released", 29.90, NOW())
');

// Quantos pedidos o cenário pede: 'count' replica o primeiro spec, ou o
// proprio array de specs.
$orderSpecs = $scenario['orders'];
$specs      = [];
if (isset($scenario['count'])) {
    for ($i = 0; $i < (int) $scenario['count']; $i++) {
        $specs[] = $orderSpecs[0];
    }
} else {
    $specs = $orderSpecs;
}

// created_at distintos e crescentes: sem isso o MySQL pode devolver as
// linhas em qualquer ordem entre execuções e a comparação entre a página
// 1 e a página 2 não fecharia.
$tempIds    = [];
$tempTotals = [];

foreach ($specs as $index => $spec) {
    $total = $spec['total'] ?? 1234.56;
    if (is_array($total)) {
        $total = $total[$index % count($total)];
    }

    $expires = null;
    if (isset($spec['expires_in'])) {
        $expires = date('Y-m-d H:i:s', time() + (int) $spec['expires_in']);
    }

    $insertOrder->execute([
        ':uid'      => $userId,
        ':status'   => $spec['status'],
        ':total'    => $total,
        ':method'   => $spec['method'],
        ':payment'  => $spec['payment'],
        ':expires'  => $expires,
        ':tracking' => $spec['tracking'] ?? null,
    ]);

    $orderId = (int) $pdo->lastInsertId();
    $tempIds[] = $orderId;
    $tempTotals[$orderId] = (float) $total;

    // Dois itens: o card mostra o primeiro e um badge "+1", que é o que
    // prova que a lista não precisa de uma query por item.
    $insertItem->execute([':oid' => $orderId, ':pid' => (int) $product['id'], ':qty' => 2, ':price' => 617.28]);
    $insertItem->execute([':oid' => $orderId, ':pid' => (int) $product['id'], ':qty' => 1, ':price' => 0.00]);

    $stamp = date('Y-m-d H:i:s', strtotime($spec['created']) + $index * (int) ($spec['step_min'] ?? 0) * 60);
    $pdo->prepare('UPDATE e5_orders SET created_at = :c, updated_at = :u WHERE id = :id')
        ->execute([':c' => $stamp, ':u' => $stamp, ':id' => $orderId]);

    if (!empty($spec['history'])) {
        $insertHistory->execute([':oid' => $orderId, ':status' => $spec['history']]);
    }

    if (!empty($spec['shipment'])) {
        $insertShipment->execute([
            ':oid'      => $orderId,
            ':tracking' => $spec['tracking'] ?? 'BR123456789BR',
        ]);
    }
}

// __SESSION do cliente escolto no cenário precisa ser a mesma do INSERT:
// o id muda por execução, e a página lê o pedido do dono da sessão.
$_SESSION['user_id']   = $userId;
$_SESSION['user_role'] = 'customer';

// A busca por número precisa do id, que só existe depois do INSERT: por
// isso {firstNumber} é resolvido aqui, e não na tabela de cenários.
$firstNumber = (string) ($tempIds[0] ?? 0);
foreach ($query as $key => $value) {
    $_GET[$key] = str_replace(
        ['{firstNumber}', '{productName}'],
        [$firstNumber, $productName],
        (string) $value
    );
}
$_SERVER['REQUEST_URI'] = '/TCC_Etec/pages/auth/'
    . ($isDetail ? 'order-detail.php' : 'orders.php')
    . ($_GET === [] ? '' : '?' . http_build_query($_GET));

if ($isDetail) {
    $_GET['id'] = $firstNumber;
}

// Quando o cenário traz uma lista de totais ('total' => [300, 900, 100]),
// o valor que interessa para comparação é o do primeiro pedido criado —
// por isso entra $tempTotals[$firstNumber], e não o spec cru.
$meta = [
    'mode'           => $mode,
    'query'          => $_GET,
    'user_id'        => $userId,
    'temp_ids'       => $tempIds,
    'temp_number'    => '#' . str_pad($firstNumber, 4, '0', STR_PAD_LEFT),
    'first_id'       => (int) $firstNumber,
    'temp_totals'    => $tempTotals,
    'product_name'   => $productName,
    'order_status'   => $specs[0]['status'] ?? '',
    'order_total'    => 'R$ ' . number_format(
        (float) ($tempTotals[(int) $firstNumber] ?? 1234.56),
        2,
        ',',
        '.'
    ),
    'status_filter'  => (string) ($_GET['status'] ?? ''),
    'sort'           => (string) ($_GET['sort'] ?? ''),
    'list_page'      => isset($_GET['page']) ? (int) $_GET['page'] : null,
    'temp_count'     => count($tempIds),
];

$queriesBefore = render_orders_count_selects($pdo);

ob_start();
require_once __DIR__ . '/../../pages/auth/' . ($isDetail ? 'order-detail.php' : 'orders.php');
$html = (string) ob_get_clean();

// Quantas SELECTs a página precisou. Contador do MySQL na MESMA conexão
// da renderização, então ele vê tudo que o PHP mandou — inclusive as
// queries dentro de helpers. É o que segura a promessa de "sem N+1":
// uma página com 10 cards que chamasse order_repo_items() por card
// passaria de 30 SELECTs, e o limite abaixo reprovaria isso.
$meta['queries'] = render_orders_count_selects($pdo) - $queriesBefore;

// ---------------------------------------------------------------------
//  Limpeza
// ---------------------------------------------------------------------
// Registrada como shutdown, e não chamada no fim do script: uma exceção
// no meio da renderização saltaria a linha de baixo e deixaria pedidos
// órfãos no banco de desenvolvimento — foi o que aconteceu enquanto a
// paginação estava sendo escrita. O id do cliente sem pedidos também
// entra no registro: nada foi criado para ele, mas o shutdown não
// depende disso para ser seguro.
$deleteOrder    = $pdo->prepare('DELETE FROM e5_orders WHERE id = :id');
$deleteShipment = $pdo->prepare('DELETE FROM e5_shipments WHERE order_id = :id');
register_shutdown_function(static function () use ($deleteOrder, $deleteShipment, $tempIds): void {
    foreach ($tempIds as $tempId) {
        try {
            // A etiqueta sai antes: e5_shipments não tem ON DELETE CASCADE
            // para o pedido, e o arquivo órfão bloquearia a próxima
            // execução da fixture.
            $deleteShipment->execute([':id' => $tempId]);
            $deleteOrder->execute([':id' => $tempId]);
        } catch (Throwable $ignored) {
            // Nada a fazer no shutdown: o teste já falhou de vez.
        }
    }
});

file_put_contents($outFile, $html);
file_put_contents($outFile . '.meta.json', (string) json_encode($meta, JSON_UNESCAPED_UNICODE));