<?php

declare(strict_types=1);

/**
 * Meus Pedidos — o histórico do cliente, em cards.
 *
 * A tela tem quatro camadas, nesta ordem:
 *
 *  1. Resumo (4 cards): total, em andamento, entregues e total comprado.
 *     Sai de UMA consulta agregada e não respeita ?status=/?q= de
 *     propósito: é a fotografia da conta, não uma métrica do recorte
 *     atual. Se mudasse a cada clique num chip, o número viraria ruído
 *     e o cliente perderia a referência do próprio histórico.
 *
 *  2. Toolbar (?q=, ?sort=) e chips de status (?status=). Tudo no GET,
 *     então a URL é compartilhável, o link funciona sem JavaScript e o
 *     botão voltar do navegador volta para a mesma combinação de
 *     filtros. Valor fora da whitelist cai no padrão em vez de devolver
 *     erro: ?sort=lixo vira "recentes" e ?status=inventado vira "Todos".
 *
 *  3. Cards, agrupados por mês nas ordenações por data. Agrupar por mês
 *     só faz sentido em ordem cronológica — em "maior valor" os mesmos
 *     meses apareceriam reembaralhados e o cabeçalho mentiria sobre a
 *     sequência.
 *
 *  4. Paginação de 10 por página (?page=), carregando q/sort/status
 *     junto: trocar de página nunca pode perder o filtro.
 *
 * O countdown do Pix é a única parte que depende de JavaScript, e ela é
 * complemento, não requisito: prazo, aviso e botão "Pagar agora" já
 * estão no HTML. Sem JS o cliente vê o tempo restante congelado, que
 * ainda é a informação honesta; com JS o relógio corre e a página se
 * recarrega sozinha no zero, para o servidor expirar o pedido (a
 * expiração é preguiçosa — ver order_state.php).
 *
 * A regra de negócio NÃO aparece nesta tela: quem pode cancelar está em
 * order_state.php e a ação vive no detalhe do pedido, que é onde o
 * cliente age. Aqui o card só informa.
 */

require_once __DIR__ . '/../../includes/account_layout.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/payment_functions.php';
require_once __DIR__ . '/../../includes/order_repo.php';
require_once __DIR__ . '/../../includes/order_state.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
require_once __DIR__ . '/../../database/connection.php';

// =====================================================================
//  Helpers de apresentação
// =====================================================================
//  Funções puras de tela (link, pill, rótulo de mês, escape de LIKE).
//  Moram aqui e não em includes/ porque só esta página as usa: nada de
//  outro arquivo depende delas, e é a forma de o escopo do redesenho
//  continuar em um arquivo só.

/**
 * Link da própria tela, carregando o recorte atual.
 *
 * Uma função só para chip, paginação e qualquer outro link, porque
 * esquecer de copiar o status ou a ordenação nesse caminho é o jeito
 * mais fácil de o cliente cair na lista errada. O ?page= só entra a
 * partir da segunda página: a primeira é a URL limpa, que é o que a
 * sidebar e o breadcrumb já apontam.
 */
function orders_link(string $baseUrl, array $filters, int $page = 0): string
{
    // "recent" é o padrão da tela: repetir ?sort=recent em todo link
    // transformaria a URL limpa (a que a sidebar e o breadcrumb já
    // apontam) em /orders.php?sort=recent, e voltar da página 2 para a
    // 1 levaria o cliente a um endereço que ele nunca digitou.
    $sort = (string) ($filters['sort'] ?? '');

    $query = array_filter(
        [
            'q'      => (string) ($filters['q'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'sort'   => ($sort === '' || $sort === 'recent') ? '' : $sort,
            'page'   => $page > 1 ? (string) $page : '',
        ],
        static fn(string $value): bool => $value !== ''
    );

    return $query === [] ? $baseUrl : $baseUrl . '?' . http_build_query($query);
}

/**
 * Padrão de LIKE com os curingas do texto escaped.
 *
 * '!' é o caractere declarado no ESCAPE da cláusula. Sem escapar, um
 * "100%" na busca traria qualquer pedido — e o "%" é o caractere mais
 * comum de aparecer em busca de produto.
 */
function orders_like_pattern(string $value): string
{
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
}

/**
 * Pill de status do card: classe, rótulo e ícone.
 *
 * O rótulo de pedido cancelado passa por payment_situation_label(), a
 * mesma função do detalhe: cancelado que já estava pago é "Reembolsado"
 * para o cliente, porque o dinheiro voltou — dizer só "cancelado"
 * deixaria ele sem saber se precisa esperar o estorno.
 */
function orders_pill(string $status, string $paymentStatus): array
{
    if ($status === 'canceled' && $paymentStatus === 'refunded') {
        return ['class' => 'pill--refunded', 'label' => 'Reembolsado', 'icon' => 'fa-rotate-left'];
    }

    $meta  = order_status_meta($status);
    $class = [
        'pending'   => 'pill--pending',
        'paid'      => 'pill--paid',
        'preparing' => 'pill--preparing',
        'shipped'   => 'pill--shipped',
        'delivered' => 'pill--delivered',
        'canceled'  => 'pill--canceled',
    ][$status] ?? 'pill--pending';

    return [
        'class' => $class,
        'label' => $status === 'canceled'
            ? payment_situation_label($status, $paymentStatus)
            : status_label_raw($status),
        'icon'  => $meta['icon'],
    ];
}

/** "Outubro de 2026" — cabeçalho do grupo de cards. */
function orders_month_label(string $createdAt, array $months): string
{
    $timestamp = strtotime($createdAt);
    if ($timestamp === false) {
        return '';
    }

    return $months[(int) date('n', $timestamp)] . ' de ' . date('Y', $timestamp);
}

/** Nomes dos itens extras, para o title do badge "+N". */
function orders_items_summary(array $items): string
{
    $names = [];
    foreach ($items as $item) {
        $names[] = (int) $item['quantity'] . 'x ' . (string) $item['display_name'];
    }

    return limit_text(implode(', ', $names), 140);
}

/** Texto inicial do relógio do Pix, no mesmo formato do JS. */

$user = account_require_login($pdo);

$userId = (int) $user['id'];

// Expiração preguiçosa de Pix pendente (reutiliza a mesma lógica do worker).
// Precisa vir ANTES das consultas de resumo e da lista: um pedido cujo Pix
// venceu agora é "cancelado + expirado" para o cliente, e a tela que
// mostrasse o status antigo estaria mentindo sobre o prazo.
$stmtExpired = $pdo->prepare(
    "SELECT id FROM e5_orders
     WHERE user_id = :uid
       AND payment_expires_at IS NOT NULL
       AND payment_expires_at < NOW()
       AND payment_status IN ('pending', 'processing')
     ORDER BY payment_expires_at ASC
     LIMIT 50"
);
$stmtExpired->execute([':uid' => $userId]);
foreach ($stmtExpired->fetchAll(PDO::FETCH_COLUMN) as $expiredId) {
    $expiredOrder = order_repo_find($pdo, (int) $expiredId);
    if ($expiredOrder !== null) {
        order_expire_pending_pix_lazy($pdo, $expiredOrder);
    }
}

// =====================================================================
//  Parâmetros da URL (?q= ?sort= ?status= ?page=)
// =====================================================================

$ordersUrl = base_url('pages/auth/orders.php');

/**
 * Mês por extenso em PT-BR, para o cabeçalho dos grupos.
 *
 * Array em vez de date('F') porque o formatador devolve o mês em inglês
 * ("October"), e a tela é toda em português.
 */
$monthsPtBr = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];

/**
 * Busca: aceita nº do pedido ou nome de produto.
 *
 * Os dígitos saem do texto digitado para casar com o id ("#0006", "6" e
 * "0006" são o mesmo pedido 6), e o texto original vai para um LIKE
 * sobre o snapshot do item. Os dois ramos entram com OR: quem digita só
 * número casa pelo id, quem digita só nome casa pelo produto, e quem
 * digitar os dois aceita os dois.
 */
$search = limit_text(trim((string) ($_GET['q'] ?? '')), 60);
$searchDigits = preg_replace('/\D/', '', $search) ?? '';

/** Ordenação com whitelist — sort é Eco do usuário, nunca entra no SQL cru. */
$sortOptions = [
    'recent'     => 'Mais recentes',
    'oldest'     => 'Mais antigos',
    'value_desc' => 'Maior valor',
];
$sort = (string) ($_GET['sort'] ?? 'recent');
if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'recent';
}

/** Chave de ordenação: id como desempate para a ordem nunca "pular" entre requisições. */
$orderBy = match ($sort) {
    'oldest'     => 'o.created_at ASC, o.id ASC',
    'value_desc' => 'o.total DESC, o.id DESC',
    default      => 'o.created_at DESC, o.id DESC',
};

/**
 * Chips de status.
 *
 * Os seis primeiros são o ENUM de e5_orders.status, na ordem da linha do
 * tempo. O sétimo, "Reembolsados", não é um status de pedido: reembolso é
 * payment_status='refunded', e o pedido que o sofreu continua 'canceled'.
 * Por isso os dois não podem ser contados em separado — o cancelado com
 * estorno entra só em "Reembolsados", e os sete chips continuam
 * particionando o histórico inteiro sem contar nada duas vezes.
 */
$chipLabels = [];
foreach (status_label_table() as $chipKey => $chipInfo) {
    $chipLabels[$chipKey] = $chipInfo['plural'];
}
$chipLabels['refunded'] = 'Reembolsados';

$filter = (string) ($_GET['status'] ?? '');
if (!array_key_exists($filter, $chipLabels)) {
    $filter = '';
}

/** Filtros correntes, para todo link da tela carregar o mesmo recorte. */
$currentFilters = ['q' => $search, 'status' => $filter, 'sort' => $sort];

// ---------------------------------------------------------------------
//  WHERE montado uma vez e reaproveitado nas três consultas
// ---------------------------------------------------------------------
// Busca e status nunca podem divergir entre o contador de chip, o total
// de páginas e a página exibida. A diferença é proposital: o chip
// selecionado não pode entalhar a própria contagem, senão "Todos"
// mostra o número da aba ativa e o cliente perde a noção do histórico
// completo. Então os chips leem $chipWhere (usuário + busca) e a lista
// e o total leem $where (usuário + busca + status).
$chipWhere  = ' WHERE o.user_id = :uid';
$chipParams = [':uid' => $userId];

if ($search !== '') {
    $searchClauses = [];

    if ($searchDigits !== '') {
        $searchClauses[]          = 'o.id = :q_id';
        $chipParams[':q_id']      = (int) $searchDigits;
    }

    $searchClauses[]           = "EXISTS (SELECT 1
                                     FROM e5_order_items oi
                                     LEFT JOIN e5_products p ON p.id = oi.product_id
                                    WHERE oi.order_id = o.id
                                      AND COALESCE(oi.product_name, p.name) LIKE :q_text ESCAPE '!')";
    $chipParams[':q_text']     = orders_like_pattern($search);

    $chipWhere .= ' AND (' . implode(' OR ', $searchClauses) . ')';
}

// A lista nasce da mesma base do chip e acrescenta o status ativo. Os
// parâmetros do filtro ficam só em $params: passar ":status_filter" para
// a query dos chips, que não tem o placeholder, faria o PDO reclamar de
// parâmetro sobrando.
$params = $chipParams;
$where  = $chipWhere;

if ($filter === 'refunded') {
    $where .= ' AND o.payment_status = :pay_filter';
    $params[':pay_filter'] = 'refunded';
} elseif ($filter !== '') {
    $where .= ' AND o.status = :status_filter';
    $params[':status_filter'] = $filter;
}

// =====================================================================
//  Resumo da conta (independente de filtro e de busca)
// =====================================================================
$stmtStats = $pdo->prepare(
    "SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN o.status IN ('pending','paid','preparing','shipped') THEN 1 ELSE 0 END), 0) AS ongoing,
            COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered,
            COALESCE(SUM(CASE WHEN o.status IN ('paid','preparing','shipped','delivered') THEN 1 ELSE 0 END), 0) AS paid_orders,
            COALESCE(SUM(CASE WHEN o.status IN ('paid','preparing','shipped','delivered') THEN o.total ELSE 0 END), 0) AS paid_total
       FROM e5_orders o
      WHERE o.user_id = :uid"
);
$stmtStats->execute([':uid' => $userId]);
$stats = $stmtStats->fetch() ?: [];

// =====================================================================
//  Contadores dos chips (respeitam a busca, não o próprio chip)
// =====================================================================
$stmtChipCounts = $pdo->prepare(
    'SELECT o.status, o.payment_status, COUNT(*) AS total
       FROM e5_orders o' . $chipWhere . '
      GROUP BY o.status, o.payment_status'
);
$stmtChipCounts->execute($chipParams);

$chipCounts = array_fill_keys(array_keys($chipLabels), 0);
$totalOrders = 0;
foreach ($stmtChipCounts->fetchAll() as $countRow) {
    $status       = (string) $countRow['status'];
    $paymentStatus = (string) $countRow['payment_status'];
    $count        = (int) $countRow['total'];

    // Cancelado com estorno é "Reembolsado" para o cliente (é o que
    // payment_situation_label() diz no detalhe), então ele conta em
    // "Reembolsados" e não em "Cancelados".
    $chip = ($status === 'canceled' && $paymentStatus === 'refunded')
        ? 'refunded'
        : $status;

    if (!array_key_exists($chip, $chipCounts)) {
        continue;
    }

    $chipCounts[$chip] += $count;
    $totalOrders       += $count;
}

// =====================================================================
//  Lista paginada
// =====================================================================
$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM e5_orders o' . $where);
$stmtCount->execute($params);
$totalFiltered = (int) $stmtCount->fetchColumn();

$perPage   = 10;
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));

// min() em vez de confiar na URL: ?page=999 não pode gerar um OFFSET
// negativo nem uma página vazia com o total de páginas aparecendo como 0.
$page   = max(1, (int) ($_GET['page'] ?? 1));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmtOrders = $pdo->prepare(
    'SELECT o.id, o.status, o.payment_status, o.payment_method, o.payment_expires_at,
            o.total, o.created_at, o.tracking_code
       FROM e5_orders o' . $where . '
      ORDER BY ' . $orderBy . '
      LIMIT :limit OFFSET :offset'
);
$stmtOrders->execute($params + [':limit' => $perPage, ':offset' => $offset]);
$orders = $stmtOrders->fetchAll();

/**
 * Dados dos 10 cards em três consultas, não uma por card.
 *
 * Antes, cada item/histórico/envio do card era uma query: 10 pedidos
 * viravam 30 idas ao banco para desenhar a mesma página. Os lotes são
 * chamados com os ids que já estão na tela e devolvem tudo agrupado por
 * order_id.
 */
$pageOrderIds = array_map(static fn(array $o): int => (int) $o['id'], $orders);
$itemsByOrder    = order_repo_items_batch($pdo, $pageOrderIds);
$historyByOrder  = order_repo_history_batch($pdo, $pageOrderIds);
$shipmentsByOrder = order_repo_shipments_batch($pdo, $pageOrderIds);

/** Agrupamento por mês só faz sentido em ordenação cronológica. */
$groupByMonth = $sort !== 'value_desc';

$page_title = 'Meus Pedidos - Royal Tech';

account_layout_head($user, 'pedidos');
?>

<div class="account-page-header">
    <div class="account-page-header-row">
        <div>
            <h1 class="account-page-title">Meus Pedidos</h1>
            <p class="account-page-subtitle">Acompanhe o andamento e o histórico das suas compras.</p>
        </div>
    </div>
</div>

<!-- ============================ Resumo ============================ -->
<section class="orders-stats" aria-label="Resumo dos seus pedidos">
    <div class="stat-card">
        <span class="stat-card__icon stat-card__icon--total" aria-hidden="true">
            <i class="fas fa-bag-shopping"></i>
        </span>
        <div class="stat-card__body">
            <span class="stat-card__value"><?php echo (int) ($stats['total_orders'] ?? 0); ?></span>
            <span class="stat-card__label">Pedidos totais</span>
        </div>
    </div>

    <div class="stat-card">
        <span class="stat-card__icon stat-card__icon--ongoing" aria-hidden="true">
            <i class="fas fa-spinner"></i>
        </span>
        <div class="stat-card__body">
            <span class="stat-card__value"><?php echo (int) ($stats['ongoing'] ?? 0); ?></span>
            <span class="stat-card__label">Em andamento</span>
        </div>
    </div>

    <div class="stat-card">
        <span class="stat-card__icon stat-card__icon--delivered" aria-hidden="true">
            <i class="fas fa-circle-check"></i>
        </span>
        <div class="stat-card__body">
            <span class="stat-card__value"><?php echo (int) ($stats['delivered'] ?? 0); ?></span>
            <span class="stat-card__label">Entregues</span>
        </div>
    </div>

    <?php
        // "0 pagos" / "1 pedido pago" / "N pedidos pagos": o texto tem que
        // caber em UMA linha no card de 4 colunas, então nada de "(s)".
        $paidOrders = (int) ($stats['paid_orders'] ?? 0);
        $paidHint   = match (true) {
            $paidOrders === 0 => '0 pagos',
            $paidOrders === 1 => '1 pedido pago',
            default           => $paidOrders . ' pedidos pagos',
        };
        ?>
    <div class="stat-card">
        <span class="stat-card__icon stat-card__icon--spent" aria-hidden="true">
            <i class="fas fa-wallet"></i>
        </span>
        <div class="stat-card__body">
            <span class="stat-card__value">R$ <?php echo e(number_format((float) ($stats['paid_total'] ?? 0), 2, ',', '.')); ?></span>
            <span class="stat-card__label">Total comprado</span>
            <span class="stat-card__hint"><?php echo e($paidHint); ?></span>
        </div>
    </div>
</section>

<!-- ==================== Busca + ordenação + chips ==================== -->
<section class="orders-controls" aria-label="Buscar e filtrar pedidos">

    <form class="orders-toolbar" method="get" action="<?php echo e($ordersUrl); ?>" role="search">
        <?php if ($filter !== ''): ?>
            <!-- A busca é feita de dentro de um chip: sem isto, digitar o
                 número do pedido jogaria o filtro de status fora. -->
            <input type="hidden" name="status" value="<?php echo e($filter); ?>">
        <?php endif; ?>

        <div class="orders-search">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <label class="sr-only" for="orderSearch">Buscar por número do pedido ou nome do produto</label>
            <input type="search" id="orderSearch" name="q" class="orders-search__input"
                   value="<?php echo e($search); ?>"
                   placeholder="Buscar por nº do pedido ou produto"
                   enterkeyhint="search" autocomplete="off">
        </div>

        <div class="orders-sort">
            <label class="sr-only" for="orderSort">Ordenar pedidos</label>
            <select id="orderSort" name="sort" class="orders-sort__select">
                <?php foreach ($sortOptions as $sortKey => $sortLabel): ?>
                    <option value="<?php echo e($sortKey); ?>" <?php echo $sortKey === $sort ? 'selected' : ''; ?>>
                        <?php echo e($sortLabel); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </div>

        <button type="submit" class="account-btn account-btn--primary account-btn--sm">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i> Buscar
        </button>
    </form>

    <nav class="orders-chips" aria-label="Filtrar pedidos por status">
        <a class="chip<?php echo $filter === '' ? ' is-active' : ''; ?>"
           href="<?php echo e(orders_link($ordersUrl, ['q' => $search, 'status' => '', 'sort' => $sort])); ?>"
           <?php echo $filter === '' ? 'aria-current="page"' : ''; ?>>
            Todos <span class="chip-count"><?php echo $totalOrders; ?></span>
        </a>
        <?php foreach ($chipLabels as $chipKey => $chipLabel): ?>
            <a class="chip<?php echo $filter === $chipKey ? ' is-active' : ''; ?>"
               href="<?php echo e(orders_link($ordersUrl, ['q' => $search, 'status' => $chipKey, 'sort' => $sort])); ?>"
               <?php echo $filter === $chipKey ? 'aria-current="page"' : ''; ?>>
                <?php echo e($chipLabel); ?>
                <span class="chip-count"><?php echo (int) $chipCounts[$chipKey]; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</section>

<!-- ============================ Lista ============================ -->
<section class="orders-list" id="secao-pedidos">
    <?php if ($orders === []): ?>
        <?php if ($filter === '' && $search === ''): ?>
            <div class="orders-empty">
                <i class="fas fa-box-open" aria-hidden="true"></i>
                <h2 class="orders-empty__title">Você ainda não fez nenhum pedido</h2>
                <p class="orders-empty__text">Quando você comprar algo, o acompanhamento aparece aqui.</p>
                <a href="<?php echo e(base_url('pages/products/products.php')); ?>" class="account-btn account-btn--primary">
                    <i class="fas fa-store" aria-hidden="true"></i> Explorar produtos
                </a>
            </div>
        <?php else: ?>
            <div class="orders-empty">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <h2 class="orders-empty__title">Nenhum pedido encontrado</h2>
                <p class="orders-empty__text">Tente outro termo de busca ou escolha outro status.</p>
                <a href="<?php echo e($ordersUrl); ?>" class="account-btn account-btn--outline">
                    <i class="fas fa-filter-circle-xmark" aria-hidden="true"></i> Limpar filtros
                </a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php
        $currentGroup = null;
        foreach ($orders as $order):
            $orderId       = (int) $order['id'];
            $status        = (string) $order['status'];
            $paymentStatus = (string) $order['payment_status'];
            $paymentMethod = (string) $order['payment_method'];
            $items         = $itemsByOrder[$orderId] ?? [];
            $firstItem     = $items[0] ?? null;
            $extraItems    = max(0, count($items) - 1);

            // Último registro de envio do pedido (lote): decide se existe
            // link de rastreio de verdade, sem inventar URL.
            $shipment  = $shipmentsByOrder[$orderId] ?? null;
            $trackUrl  = trim((string) ($shipment['label_url'] ?? ''));

            // Prazo do Pix ainda válido. Timestamp, não string: o JS lê
            // o número e não precisa adivinhar o fuso da data.
            $pixDeadlineTs = null;
            if ($status === 'pending' && $paymentMethod === 'pix' && $order['payment_expires_at'] !== null) {
                $expiresTs = strtotime((string) $order['payment_expires_at']);
                if ($expiresTs !== false && $expiresTs > time()) {
                    $pixDeadlineTs = $expiresTs;
                }
            }

            $pill       = orders_pill($status, $paymentStatus);
            $progress   = order_progress($pdo, $order, $historyByOrder[$orderId] ?? []);
            // Tracker só some quando o pedido foi cancelado. Reembolsado
            // também é status cancelado, então cai nesta mesma regra.
            $showTrack  = $status !== 'canceled';
            // Total cancelado/reembolsado em cinza (S1b): status === 'canceled'
            // cobre os dois casos — cancelado simples e cancelado com
            // payment_status='refunded'.
            $totalMuted = $status === 'canceled';
            $monthLabel = $groupByMonth ? orders_month_label((string) $order['created_at'], $monthsPtBr) : '';
        ?>
            <?php if ($groupByMonth && $monthLabel !== $currentGroup): ?>
                <?php $currentGroup = $monthLabel; ?>
                <h2 class="orders-group"><?php echo e($currentGroup); ?></h2>
            <?php endif; ?>

            <article class="order-card" aria-labelledby="order-title-<?php echo $orderId; ?>">
                <div class="order-card__top">
                    <div class="order-thumb">
                        <?php if ($firstItem !== null): ?>
                            <?php
                            // Snapshot da compra: renderProductImage() já
                            // cai no placeholder quando o arquivo sumiu.
                            $thumb = renderProductImage((string) ($firstItem['product_image'] ?? ''), base_url());
                            ?>
                            <img src="<?php echo e($thumb); ?>" alt="" class="order-thumb__img" loading="lazy">
                        <?php else: ?>
                            <i class="fas fa-box" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>

                    <div class="order-card__body">
                        <div class="order-head">
                            <h3 class="order-title" id="order-title-<?php echo $orderId; ?>">
                                Pedido #<?php echo str_pad((string) $orderId, 4, '0', STR_PAD_LEFT); ?>
                            </h3>
                            <span class="pill <?php echo e($pill['class']); ?>">
                                <i class="fas <?php echo e($pill['icon']); ?>" aria-hidden="true"></i>
                                <?php echo e($pill['label']); ?>
                            </span>
                        </div>

                        <div class="order-meta">
                            <span class="order-meta__item">
                                <i class="fas fa-calendar" aria-hidden="true"></i>
                                <?php echo e(date('d/m/Y', strtotime((string) $order['created_at']))); ?>
                            </span>
                            <?php if ($paymentMethod !== ''): ?>
                                <span class="order-meta__item">
                                    <i class="fas fa-credit-card" aria-hidden="true"></i>
                                    <?php echo e(payment_method_raw($paymentMethod)); ?>
                                </span>
                            <?php endif; ?>
                            <span class="order-meta__item">
                                <i class="fas fa-box" aria-hidden="true"></i>
                                <?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'itens'; ?>
                            </span>
                        </div>

                        <?php if ($firstItem !== null): ?>
                            <div class="order-items">
                                <span class="order-items__name">
                                    <?php echo e((string) $firstItem['display_name']); ?>
                                </span>
                                <span class="order-items__qty">×<?php echo (int) $firstItem['quantity']; ?></span>
                                <?php if ($extraItems > 0): ?>
                                    <span class="order-items__more" title="<?php echo e(orders_items_summary(array_slice($items, 1))); ?>">
                                        +<?php echo $extraItems; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="order-total<?php echo $totalMuted ? ' order-total--muted' : ''; ?>">
                        <span class="order-total__label">Total</span>
                        <span class="order-total__value">
                            R$ <?php echo e(number_format((float) $order['total'], 2, ',', '.')); ?>
                        </span>
                    </div>
                </div>

                <!-- ===================== Avisos ===================== -->
                <?php if ($pixDeadlineTs !== null): ?>
                    <p class="order-notice order-notice--warning">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Pague o Pix em
                        <strong class="pix-countdown"
                                data-pix-deadline="<?php echo $pixDeadlineTs; ?>"><?php echo e(orders_format_remaining($pixDeadlineTs)); ?></strong>
                        para garantir o envio.
                    </p>
                <?php elseif ($status === 'canceled' && $paymentStatus === 'expired'): ?>
                    <p class="order-notice order-notice--danger">
                        <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
                        O prazo do Pix terminou e o pedido foi cancelado.
                    </p>
                <?php elseif ($status === 'canceled' && $paymentStatus === 'refunded'): ?>
                    <p class="order-notice order-notice--info">
                        <i class="fas fa-rotate-left" aria-hidden="true"></i>
                        Reembolso processado<?php
                            $refundAt = trim((string) ($progress['canceled_at'] ?? ''));
                            echo $refundAt !== ''
                                ? ' em ' . e(date('d/m/Y', strtotime($refundAt))) . '.'
                                : '.';
                        ?>
                    </p>
                <?php elseif ($status === 'canceled'): ?>
                    <p class="order-notice order-notice--danger">
                        <i class="fas fa-ban" aria-hidden="true"></i>
                        Pedido cancelado<?php
                            $canceledAt = trim((string) ($progress['canceled_at'] ?? ''));
                            echo $canceledAt !== ''
                                ? ' em ' . e(date('d/m/Y H:i', strtotime($canceledAt))) . '.'
                                : '.';
                        ?>
                    </p>
                <?php elseif ($status === 'shipped' && trim((string) $order['tracking_code']) !== ''): ?>
                    <p class="order-notice order-notice--info">
                        <i class="fas fa-truck-fast" aria-hidden="true"></i>
                        Código de rastreio: <strong><?php echo e((string) $order['tracking_code']); ?></strong>
                    </p>
                <?php elseif ($status === 'delivered'): ?>
                    <p class="order-notice order-notice--success">
                        <i class="fas fa-house" aria-hidden="true"></i>
                        Pedido entregue.
                        <?php if (!empty($progress['steps'][4]['date_label'])): ?>
                            em <?php echo e((string) $progress['steps'][4]['date_label']); ?>.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <!-- ===================== Progresso ===================== -->
                <?php if ($showTrack && !empty($progress['steps'])): ?>
                    <ol class="tracker" aria-label="Andamento do pedido #<?php echo $orderId; ?>">
                        <?php foreach ($progress['steps'] as $step): ?>
                            <li class="tracker__step<?php echo !empty($step['done']) ? ' is-done' : ''; ?>">
                                <span class="tracker__dot" aria-hidden="true">
                                    <i class="fas <?php echo e($step['icon']); ?>"></i>
                                </span>
                                <span class="tracker__label"><?php echo e($step['label']); ?></span>
                                <span class="tracker__date"><?php echo e((string) ($step['date_label'] ?? '')); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <!-- ===================== Ações ===================== -->
                <div class="order-actions">
                    <?php
                    // Cada card oferece só o que faz sentido no estado dele:
                    // botão de rastrear sem etiqueta seria mentira, e
                    // comprovante de pedido cancelado não existe.
                    ?>
                    <?php /* Hierarquia: exatamente UMA ação dourada por card, a
                           que resolve o estado (pagar / rastrear / comprar de
                           novo). "Ver detalhes" e "Comprovante" são sempre
                           outline para não competir com ela. */ ?>
                    <a href="<?php echo e(base_url('pages/auth/order-detail.php?id=' . $orderId)); ?>"
                       class="account-btn account-btn--outline account-btn--sm">
                        <i class="fas fa-eye" aria-hidden="true"></i> Ver detalhes
                    </a>

                    <?php if ($pixDeadlineTs !== null): ?>
                        <a href="<?php echo e(base_url('pages/cart/payment.php?order=' . $orderId)); ?>"
                           class="account-btn account-btn--sm btn-gold">
                            <i class="fas fa-qrcode" aria-hidden="true"></i> Pagar agora
                        </a>
                    <?php endif; ?>

                    <?php if ($status === 'shipped' && $trackUrl !== ''): ?>
                        <a href="<?php echo e($trackUrl); ?>" class="account-btn account-btn--sm btn-gold"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-truck-fast" aria-hidden="true"></i> Rastrear
                        </a>
                    <?php endif; ?>

                    <?php if (in_array($status, ['paid', 'preparing', 'shipped', 'delivered'], true)): ?>
                        <a href="<?php echo e(base_url('pages/download-comprovante.php?id=' . $orderId)); ?>"
                           class="account-btn account-btn--outline account-btn--sm">
                            <i class="fas fa-file-pdf" aria-hidden="true"></i> Comprovante
                        </a>
                    <?php endif; ?>

                    <?php if (in_array($status, ['delivered', 'canceled'], true) && $items !== []): ?>
                        <?php // Mesmo endpoint do detalhe (cart/add.php), com os ids e
                              // quantidades do snapshot: reenvia ao carrinho e
                              // manda o cliente para lá. CSRF como em qualquer POST. ?>
                        <form method="post" action="<?php echo e(base_url('pages/cart/add.php')); ?>" data-reorder>
                            <input type="hidden" name="_csrf_token" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="redirect" value="<?php echo e(base_url('pages/cart/cart.php')); ?>">
                            <?php foreach ($items as $rebuyItem): ?>
                                <input type="hidden" name="product_id[]" value="<?php echo (int) $rebuyItem['product_id']; ?>">
                                <input type="hidden" name="quantity[]" value="<?php echo (int) $rebuyItem['quantity']; ?>">
                            <?php endforeach; ?>
                            <button type="submit" class="account-btn account-btn--outline account-btn--sm btn-gold">
                                <i class="fas fa-rotate-right" aria-hidden="true"></i> Comprar novamente
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <!-- Janela de 5 páginas em torno da atual, como em products.php.
                 Só aparece com mais de uma página: numa lista de 3 pedidos
                 o controle seria ruído. -->
            <?php $windowStart = max(1, $page - 2); ?>
            <?php $windowEnd   = min($totalPages, $page + 2); ?>
            <nav class="ml-pagination" aria-label="Paginação dos pedidos">
                <?php if ($page > 1): ?>
                    <a href="<?php echo e(orders_link($ordersUrl, $currentFilters, $page - 1)); ?>"
                       rel="prev" aria-label="Página anterior">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                    <a href="<?php echo e(orders_link($ordersUrl, $currentFilters, $i)); ?>"
                       class="<?php echo $i === $page ? 'active' : ''; ?>"
                       <?php echo $i === $page ? 'aria-current="page"' : ''; ?>>
                        <?php echo (int) $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo e(orders_link($ordersUrl, $currentFilters, $page + 1)); ?>"
                       rel="next" aria-label="Próxima página">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<script>
(function () {
    'use strict';

    // Ordenação: o <select> já é um GET, então trocar a opção e enviar o
    // formulário é o comportamento esperado. O botão "Buscar" continua
    // ali, e é ele quem faz a busca por texto sem JavaScript.
    var sortSelect = document.getElementById('orderSort');
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            sortSelect.form.submit();
        });
    }

    // Countdown do Pix. Sem aria-live de propósito: um relógio que
    // announce a cada segundo enche o leitor de tela de fala.
    var countdowns = document.querySelectorAll('[data-pix-deadline]');

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function formatRemaining(seconds) {
        var total = Math.max(0, Math.floor(seconds));
        var h = Math.floor(total / 3600);
        var m = Math.floor((total % 3600) / 60);
        var s = total % 60;

        return h > 0 ? pad(h) + ':' + pad(m) + ':' + pad(s) : pad(m) + ':' + pad(s);
    }

    var reloading = false;

    function tickCountdowns() {
        var now = Date.now() / 1000;

        Array.prototype.forEach.call(countdowns, function (el) {
            var deadline = parseInt(el.getAttribute('data-pix-deadline'), 10);
            if (isNaN(deadline)) return;

            var remaining = deadline - now;
            el.textContent = formatRemaining(remaining);

            // Tempo zerado: recarrega para o servidor rodar a expiração
            // preguiçosa e o card trocar "Pagar agora" por "cancelado".
            // A trava evita N recarregamentos quando há vários pedidos.
            if (remaining <= 0 && !reloading) {
                reloading = true;
                window.location.reload();
            }
        });
    }

    if (countdowns.length > 0) {
        tickCountdowns();
        window.setInterval(tickCountdowns, 1000);
    }

    // "Comprar novamente": feedback imediato, porque o POST redireciona
    // para o carrinho e a tela não dá nenhum sinal antes disso.
    Array.prototype.forEach.call(document.querySelectorAll('form[data-reorder]'), function (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]');
            if (!button) return;

            button.classList.add('btn-loading');
            button.disabled = true;
        });
    });
}());
</script>

<?php account_layout_foot();