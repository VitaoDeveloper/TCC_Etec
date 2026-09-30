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
 *  2. Quatro cards de limite de cancelamento. A regra de negócio (quem
 *     pode cancelar) está em order_can_cancel(), em order_state.php;
 *     aqui fica só o texto que explica a regra ao cliente, separado em
 *     sua própria função para poder ser conferido por teste.
 *
 *  3. A lista, na mesma tabela de antes, agora restrita ao status escolhido.
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

$ordersUrl = base_url('pages/auth/orders.php');

function orders_status_url(string $ordersUrl, string $status): string
{
    return $status === '' ? $ordersUrl : $ordersUrl . '?status=' . rawurlencode($status);
}

/**
 * Os quatro cards de limite de cancelamento.
 *
 * A regra que decide quem pode cancelar é order_can_cancel() (pending,
 * paid e preparing) e mora em order_state.php. Este array é só a
 * explicação em português que o cliente lê antes de clicar, mais o
 * prazo que vale para cada etapa. Texto de política comercial fica
 * aqui, e não no meio da regra, para uma troca de letra não virar
 * mudança de comportamento.
 *
 * @return array<int, array{key:string,icon:string,title:string,badge:string,text:string,limit:string,can_cancel:bool}>
 */
function account_cancel_rules(): array
{
    return [
        [
            'key'        => 'pending',
            'icon'       => 'fa-clock',
            'title'      => 'Aguardando pagamento',
            'badge'      => 'Pode cancelar',
            'text'       => 'Nada foi pago e nada foi enviado. Cancelar agora não gera custo de devolução nem espera de estorno.',
            'limit'      => 'Limite: até a aprovação do pagamento',
            'can_cancel' => true,
        ],
        [
            'key'        => 'paid',
            'icon'       => 'fa-circle-check',
            'title'      => 'Pagamento aprovado',
            'badge'      => 'Pode cancelar',
            'text'       => 'O valor já entrou, mas o pacote não foi postado. O cancelamento devolve os itens ao estoque e dispara o reembolso integral.',
            'limit'      => 'Limite: até a postagem',
            'can_cancel' => true,
        ],
        [
            'key'        => 'preparing',
            'icon'       => 'fa-box-open',
            'title'      => 'Em preparação',
            'badge'      => 'Pode cancelar',
            'text'       => 'O pedido já está sendo separado e embalado. Ainda dá para cancelar, mas o reembolso passa a seguir o prazo do meio de pagamento usado.',
            'limit'      => 'Limite: até a etiqueta de envio ser gerada',
            'can_cancel' => true,
        ],
        [
            'key'        => 'shipped',
            'icon'       => 'fa-truck',
            'title'      => 'Enviado ou entregue',
            'badge'      => 'Sem cancelamento online',
            'text'       => 'Depois da postagem o cancelamento pela tela não existe mais. A devolução segue o direito de arrependimento, em até 7 dias corridos após o recebimento.',
            'limit'      => 'Limite: pós-venda em até 7 dias',
            'can_cancel' => false,
        ],
    ];
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
    WHERE o.user_id = :uid
';
$params = [':uid' => $userId];

if ($filter !== '') {
    $sql .= ' AND o.status = :status';
    $params[':status'] = $filter;
}

$sql .= ' ORDER BY o.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$page_title = 'Meus Pedidos - Royal Tech';

account_layout_head($user, 'pedidos');
?>

<div class="account-page-header">
    <h1 class="account-page-title">Meus Pedidos</h1>
    <p class="account-page-subtitle">Acompanhe o andamento e o histórico das suas compras.</p>
</div>

<!-- ============================ Cards: limite de cancelamento ============================ -->
<section class="account-cancel-grid" id="secao-cancelamento"
         aria-label="Regras de cancelamento por etapa do pedido">
    <?php foreach (account_cancel_rules() as $rule): ?>
        <article class="account-cancel-card<?php echo $rule['can_cancel'] ? '' : ' account-cancel-card--blocked'; ?>"
                 data-cancel-card="<?php echo e($rule['key']); ?>">
            <header class="account-cancel-head">
                <span class="account-cancel-icon" aria-hidden="true"><i class="fas <?php echo e($rule['icon']); ?>"></i></span>
                <div>
                    <h2 class="account-cancel-title"><?php echo e($rule['title']); ?></h2>
                    <span class="account-cancel-badge"><?php echo e($rule['badge']); ?></span>
                </div>
            </header>
            <p class="account-cancel-text"><?php echo e($rule['text']); ?></p>
            <p class="account-cancel-limit">
                <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                <span><?php echo e($rule['limit']); ?></span>
            </p>
        </article>
    <?php endforeach; ?>
</section>

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
    <?php endif; ?>
</section>

<?php account_layout_foot();
