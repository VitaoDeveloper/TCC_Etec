<?php

declare(strict_types=1);

/**
 * Meus Pedidos — histórico de compras por status, no shell da conta.
 *
 * A tela tem três partes, nesta ordem:
 *
 *  1. Abas de status com a contagem de pedidos de cada etapa, filtrando
 *     por ?status=. O filtro é server-side de propósito: a URL fica
 *     compartilhável, o link funciona sem JavaScript e o histórico do
 *     navegador volta para a aba certa. Um status fora do ENUM cai em
 *     "Todos" em vez de devolver erro.
 *
 *  2. A busca por número do pedido (?q=), que compartilha a mesma
 *     consulta da lista.
 *
 *  3. A lista, na mesma tabela de antes, agora restrita ao status
 *     escolhido, paginada em 10 por página (?page=).
 *
 * A regra de negócio de quem pode cancelar NÃO aparece nesta tela: o
 * limite de cancelamento é explicado no detalhe do pedido, que é onde
 * o cliente age.
 */

require_once __DIR__ . '/../../includes/account_layout.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../database/connection.php';

$user = account_require_login($pdo);

$userId = (int) $user['id'];

// ---------------------------------------------------------------------
//  Filtro de status (?status=pending|paid|preparing|shipped|delivered|canceled)
// ---------------------------------------------------------------------
$statusTable = status_label_table();
$filter      = (string) ($_GET['status'] ?? '');

if (!array_key_exists($filter, $statusTable)) {
    $filter = '';
}

// Busca por número do pedido (?q=12). Só dígitos: o campo é numérico
// e aceitar texto livre não tem onde buscar (nome de produto não é
// índice desta consulta).
$search = preg_replace('/\D/', '', (string) ($_GET['q'] ?? '')) ?? '';

$ordersUrl = base_url('pages/auth/orders.php');

/**
 * Monta a URL de uma aba preservando a busca atual.
 *
 * Ler de $GLOBALS e não de closure: a função é chamada dentro do HTML,
 * fora do escopo do arquivo. Busca e aba andam juntas — trocar de status
 * sem carregar o que o cliente digitou seria perder o filtro na mão.
 */
function orders_status_url(string $ordersUrl, string $status): string
{
    $query = [];
    if ($status !== '') {
        $query['status'] = $status;
    }
    if (($GLOBALS['search'] ?? '') !== '') {
        $query['q'] = $GLOBALS['search'];
    }

    return $query === []
        ? $ordersUrl
        : $ordersUrl . '?' . http_build_query($query);
}

/**
 * URL de uma página da lista, preservando status e busca.
 *
 * A paginação é server-side: o número da página vai na URL para o link
 * ser compartilhável e o botão voltar do navegador funcionar.
 */
function orders_page_url(string $ordersUrl, int $page): string
{
    $query = [];
    if (($GLOBALS['filter'] ?? '') !== '') {
        $query['status'] = $GLOBALS['filter'];
    }
    if (($GLOBALS['search'] ?? '') !== '') {
        $query['q'] = $GLOBALS['search'];
    }
    if ($page > 1) {
        $query['page'] = $page;
    }

    return $query === []
        ? $ordersUrl
        : $ordersUrl . '?' . http_build_query($query);
}

// Contagem por status para as abas. Uma consulta só, para não fazer
// N+1 no laço das abas.
$stmtCounts = $pdo->prepare('SELECT status, COUNT(*) AS total FROM e5_orders WHERE user_id = :uid GROUP BY status');
$stmtCounts->execute([':uid' => $userId]);
$countsByStatus = [];
foreach ($stmtCounts->fetchAll() as $row) {
    $countsByStatus[(string) $row['status']] = (int) $row['total'];
}
$totalOrders = array_sum($countsByStatus);

$sql = '
    SELECT o.id, o.status, o.total, o.created_at,
        (SELECT COUNT(*) FROM e5_order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM e5_orders o
';

// A cláusula WHERE fica separada do SELECT de propósito: o total do
// filtro sai de um COUNT que reaproveita o mesmo WHERE, então status e
// busca nunca podem divergir entre a contagem e a página exibida.
$where = ' WHERE o.user_id = :uid';
$params = [':uid' => $userId];

if ($filter !== '') {
    $where .= ' AND o.status = :status';
    $params[':status'] = $filter;
}

if ($search !== '') {
    $where .= ' AND o.id = :q';
    $params[':q'] = (int) $search;
}

$sql .= $where;

// Total do filtro atual, para o número de páginas. Uma COUNT separada em
// vez de contar as linhas da página: sem ela não há como saber se existe
// página 2.
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM e5_orders o' . $where);
$countStmt->execute($params);
$totalFiltered = (int) $countStmt->fetchColumn();

$perPage = 10;
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));

// min() em vez de confiar na URL: ?page=999 não pode gerar um OFFSET
// negativo nem uma página vazia com o total de páginas aparecendo como 0.
$page   = max(1, (int) ($_GET['page'] ?? 1));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql .= ' ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset';
$params[':limit'] = $perPage;
$params[':offset'] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$page_title = 'Meus Pedidos - Royal Tech';

account_layout_head($user, 'pedidos');
?>

<div class="account-page-header">
    <div class="account-page-header-row">
        <div>
            <h1 class="account-page-title">Meus Pedidos</h1>
            <p class="account-page-subtitle">Acompanhe o andamento e o histórico das suas compras.</p>
        </div>
        <span class="account-order-count-muted"><?php echo (int) $totalOrders; ?> pedido(s)</span>
    </div>
</div>

<!-- Busca por número do pedido -->
<form class="account-search" method="get" action="<?php echo e($ordersUrl); ?>" role="search">
    <label class="sr-only" for="orderSearch">Buscar por número do pedido</label>
    <input class="account-input" type="search" id="orderSearch" name="q"
           value="<?php echo e($search); ?>"
           placeholder="Buscar por nº do pedido..." inputmode="numeric">
    <button type="submit" class="account-btn account-btn--sm">
        <i class="fas fa-magnifying-glass" aria-hidden="true"></i> Buscar
    </button>
</form>

<!-- ============================ Abas de status ============================ -->
<nav class="account-tabs" id="secao-abas" aria-label="Filtrar pedidos por status">
    <a class="account-tab<?php echo $filter === '' ? ' is-active' : ''; ?>"
       href="<?php echo e(orders_status_url($ordersUrl, '')); ?>"
       <?php echo $filter === '' ? 'aria-current="page"' : ''; ?>>
        Todos <span class="account-tab-count"><?php echo (int) $totalOrders; ?></span>
    </a>
    <?php foreach ($statusTable as $key => $info): ?>
        <a class="account-tab<?php echo $filter === $key ? ' is-active' : ''; ?>"
           href="<?php echo e(orders_status_url($ordersUrl, $key)); ?>"
           <?php echo $filter === $key ? 'aria-current="page"' : ''; ?>>
            <?php echo e($info['plural']); ?>
            <span class="account-tab-count"><?php echo (int) ($countsByStatus[$key] ?? 0); ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<!-- ============================ Lista ============================ -->
<section class="account-card" id="secao-pedidos">
    <?php if (empty($orders)): ?>
        <div class="account-empty">
            <i class="fas fa-box-open" aria-hidden="true"></i>
            <?php if ($filter !== ''): ?>
                <h3>Nenhum pedido <?php echo e(strtolower(status_label_raw($filter, 2))); ?></h3>
                <p>Você não tem pedidos nesta etapa.</p>
                <p style="margin-top: 8px;">
                    <a href="<?php echo e($ordersUrl); ?>" class="account-btn account-btn--primary">
                        <i class="fas fa-list" aria-hidden="true"></i> Ver todos os pedidos
                    </a>
                </p>
            <?php else: ?>
                <h3>Nenhum pedido ainda</h3>
                <p>Faça suas compras e acompanhe seus pedidos aqui.</p>
                <p style="margin-top: 8px;">
                    <a href="<?php echo e(base_url('pages/products/products.php')); ?>" class="account-btn account-btn--primary">
                        <i class="fas fa-store" aria-hidden="true"></i> Ver Produtos
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="ml-table-wrap">
            <table class="ml-table">
                <thead><tr><th>Pedido</th><th>Data</th><th>Itens</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?php echo str_pad((string) $o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($o['created_at'])); ?></td>
                        <td><?php echo (int) $o['item_count']; ?></td>
                        <td>R$ <?php echo number_format((float) $o['total'], 2, ',', '.'); ?></td>
                        <td><span class="status-badge <?php echo e(status_class($o['status'])); ?>"><?php echo status_label($o['status']); ?></span></td>
                        <td><a href="<?php echo e(base_url('pages/auth/order-detail.php?id=' . (int) $o['id'])); ?>" class="ml-btn" style="padding:4px 12px; font-size:0.8rem;"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <!-- Janela de 5 páginas em torno da atual, como em products.php.
                 Só aparece quando há mais de uma página: numa lista de 3
                 pedidos o controle seria ruído. -->
            <?php $windowStart = max(1, $page - 2); ?>
            <?php $windowEnd   = min($totalPages, $page + 2); ?>
            <nav class="ml-pagination" aria-label="Paginação dos pedidos">
                <?php if ($page > 1): ?>
                    <a href="<?php echo e(orders_page_url($ordersUrl, $page - 1)); ?>"
                       rel="prev" aria-label="Página anterior">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                    <a href="<?php echo e(orders_page_url($ordersUrl, $i)); ?>"
                       class="<?php echo $i === $page ? 'active' : ''; ?>"
                       <?php echo $i === $page ? 'aria-current="page"' : ''; ?>>
                        <?php echo (int) $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo e(orders_page_url($ordersUrl, $page + 1)); ?>"
                       rel="next" aria-label="Próxima página">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php account_layout_foot();
