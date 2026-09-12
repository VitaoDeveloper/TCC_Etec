<?php
$page_title = 'Meus Pedidos - Royal Tech';
$breadcrumb_title = 'Meus Pedidos';
$current_page = 'pedidos';
$base_path = '../../';
$page_css = ['account.css'];

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once __DIR__ . '/../../includes/csrf.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
require_once __DIR__ . '/../../includes/cart_functions.php';
require_once __DIR__ . '/../../includes/order_payment_functions.php';
require_once __DIR__ . '/../../includes/order_timeline.php';

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ---------------------------------------------------------------------------
// Ações via POST (cancelar / comprar novamente) — todas com CSRF
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $action   = (string) ($_POST['action'] ?? '');
    $targetId = (int) ($_POST['order_id'] ?? 0);

    if ($action === 'cancel' && $targetId > 0) {
        $target = orderGetById($pdo, $targetId);
        if (!$target || (int) $target['user_id'] !== $userId) {
            header('Location: orders.php');
            exit;
        }
        if (orderCancelCustomer($pdo, $targetId)) {
            header('Location: orders.php?msg=cancelada');
            exit;
        }
        header('Location: orders.php?msg=cancel_fail');
        exit;
    }

    if ($action === 'rebuy' && $targetId > 0) {
        $order = orderGetById($pdo, $targetId);
        if ($order && (int) $order['user_id'] === $userId) {
            $added = 0;
            $skipped = 0;
            foreach (orderGetItems($pdo, $targetId) as $it) {
                $stock = validateStock($pdo, (int) $it['product_id'], (int) $it['quantity']);
                if ($stock['ok']) {
                    cartAddItem($pdo, $userId, (int) $it['product_id'], (int) $it['quantity']);
                    $added++;
                } else {
                    $skipped++;
                }
            }
            if ($added > 0) {
                header('Location: ../cart/cart.php?rebuy=1');
                exit;
            }
            header('Location: orders.php?msg=sem_estoque');
            exit;
        }
    }
}

// ---------------------------------------------------------------------------
// Filtros / busca / paginação
// ---------------------------------------------------------------------------
$filter = (string) ($_GET['f'] ?? 'todos');
$filterMap = [
    'aguardando' => 'pending',
    'preparacao' => 'paid',
    'transito'   => 'shipped',
    'entregues'  => 'delivered',
    'cancelados' => 'canceled',
];
$statusFilter = null;
if (isset($filterMap[$filter])) {
    $statusFilter = $filterMap[$filter];
}

$busca = trim((string) ($_GET['busca'] ?? ''));
$buscaDigits = preg_replace('/\D/', '', $busca);

$perPage = 8;
$page = max(1, (int) ($_GET['pg'] ?? 1));
$offset = ($page - 1) * $perPage;

// Condições comuns
$where = 'WHERE o.user_id = :uid';
$params = [':uid' => $userId];
if ($statusFilter) {
    $where .= ' AND o.status = :st';
    $params[':st'] = $statusFilter;
}
$buscaId = $buscaDigits === '' ? 0 : (int) ltrim($buscaDigits, '0');
if ($buscaId > 0) {
    $where .= ' AND o.id = :q';
    $params[':q'] = $buscaId;
}

// Totais por status (badges dos chips)
$counts = ['todos' => 0, 'aguardando' => 0, 'preparacao' => 0, 'transito' => 0, 'entregues' => 0, 'cancelados' => 0];
$filterReverse = array_flip($filterMap);
$stmtCount = $pdo->query('SELECT status, COUNT(*) AS c FROM e5_orders WHERE user_id = ' . (int) $userId . ' GROUP BY status');
foreach ($stmtCount->fetchAll() as $rowC) {
    $s = (string) $rowC['status'];
    $c = (int) $rowC['c'];
    $counts['todos'] += $c;
    $key = $filterReverse[$s] ?? null;
    if ($key !== null) {
        $counts[$key] = $c;
    }
}

// Label legível dos filtros (chips + empty-state)
$filterLabels = [
    'todos'      => 'Todos',
    'aguardando' => 'Aguardando pagamento',
    'preparacao' => 'Em preparação',
    'transito'   => 'Em trânsito',
    'entregues'  => 'Entregues',
    'cancelados' => 'Cancelados',
];

// Total filtrado (paginação)
$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM e5_orders o ' . $where);
$stmtTotal->execute($params);
$totalFiltered = (int) $stmtTotal->fetchColumn();
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Pedidos da página
$stmtOrders = $pdo->prepare(
    'SELECT o.*,
            (SELECT COUNT(*) FROM e5_order_items oi WHERE oi.order_id = o.id) AS item_count
       FROM e5_orders o ' . $where . '
      ORDER BY o.created_at DESC
      LIMIT ' . $perPage . ' OFFSET ' . $offset
);
$stmtOrders->execute($params);
$orders = $stmtOrders->fetchAll();

// Feedback flash
$flash = null;
if (isset($_GET['msg'])) {
    $flash = match ($_GET['msg']) {
        'cancelada'   => ['ok', 'Pedido cancelado com sucesso.'],
        'cancel_fail' => ['err', 'Não foi possível cancelar este pedido.'],
        'sem_estoque' => ['err', 'Nenhum item disponível em estoque para repetir o pedido.'],
        default       => null,
    };
}

// ---------------------------------------------------------------------------
// Dados da sidebar
// ---------------------------------------------------------------------------
$initials = '';
$words = preg_split('/\s+/', trim((string) ($user['name'] ?? '')));
foreach ($words as $w) {
    if ($w !== '') {
        $initials .= mb_strtoupper(mb_substr($w, 0, 1));
    }
}
$initials = mb_substr($initials, 0, 2) ?: 'RT';
$avatarPath = $base_path . (!empty($user['avatar_path'])
    ? $user['avatar_path']
    : 'assets/img/placeholder-avatar.svg');
$isAdminProfile = (($_SESSION['user_role'] ?? '') === 'admin');

$payLabels = ['pix' => 'Pix', 'boleto' => 'Boleto', 'credit' => 'Cartão', 'delivery' => 'Entrega'];
$orderStatusIcons = [
    'pending'   => 'far fa-hourglass',
    'paid'      => 'fas fa-box',
    'shipped'   => 'fas fa-shipping-fast',
    'delivered' => 'fas fa-check-circle',
    'canceled'  => 'fas fa-times-circle',
];
include '../../components/header.php';
?>
<section class="profile-page ac-page">
    <div class="container">
        <div class="ac-wrap">

            <!-- Sidebar -->
            <aside class="ac-sidebar">
                <div class="ac-identity">
                    <div class="ac-avatar" title="Foto de perfil">
                        <?php
                        $avatarSrc = $avatarPath;
                        $isSvg = str_ends_with($avatarSrc, '.svg');
                        if (!$isSvg && !empty($user['avatar_path'])):
                        ?>
                            <img src="<?php echo htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de perfil" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                    <h2><?php echo htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <span class="ac-role-badge"><?php echo $isAdminProfile ? 'Administrador' : 'Cliente'; ?></span>
                </div>
                <nav class="ac-nav">
                    <a href="profile.php"><i class="fas fa-user-edit"></i> Meu Perfil</a>
                    <a href="orders.php" class="is-active"><i class="fas fa-box-open"></i> Meus Pedidos</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                </nav>
            </aside>

            <!-- Main -->
            <div class="ac-main">
                <div class="ac-main-head">
                    <div>
                        <h1>Meus Pedidos</h1>
                        <p>acompanhe o status, pague faturas e repita compras.</p>
                    </div>
                    <span class="ac-count"><i class="fas fa-box-open"></i> <?php echo (int) $counts['todos']; ?> pedido(s)</span>
                </div>

                <?php if ($flash): ?>
                    <div class="ac-feedback ac-feedback-<?php echo $flash[0] === 'ok' ? 'success' : 'error'; ?>">
                        <i class="fas <?php echo $flash[0] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                        <span><?php echo htmlspecialchars($flash[1], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="ac-filters">
                    <?php foreach ($filterLabels as $key => $label):
                        $qs = http_build_query(array_filter([
                            'f' => $key,
                            'busca' => $busca !== '' ? $busca : null,
                        ]));
                    ?>
                        <a class="ac-chip<?php echo $filter === $key ? ' is-active' : ''; ?>" href="orders.php<?php echo $qs !== '' ? '?' . $qs : ''; ?>"<?php echo $filter === $key ? ' aria-current="page"' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            <span class="ac-chip-count"><?php echo (int) $counts[$key]; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Busca -->
                <form method="get" class="ac-search">
                    <?php if ($filter !== 'todos'): ?><input type="hidden" name="f" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
                    <input type="text" name="busca" class="ac-search-input" placeholder="Buscar pelo número do pedido (ex.: 0012)" inputmode="numeric" value="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="ac-search-btn"><i class="fas fa-search"></i></button>
                </form>

                <?php if (empty($orders)): ?>
                    <?php
                    if ($busca !== '') {
                        $emptyTitle = 'Nenhum pedido encontrado';
                        $emptyText = 'Tente outro número de pedido ou remova o filtro.';
                        $emptyCta = 'Limpar busca';
                        $emptyLink = 'orders.php' . ($statusFilter !== null ? '?f=' . urlencode($filter) : '');
                        $emptyIcon = 'fa-search-minus';
                    } elseif ($statusFilter !== null) {
                        $emptyTitle = 'Nenhum pedido neste status';
                        $emptyText = 'Os pedidos de "'.$filterLabels[$filter].'" aparecerão aqui quando existirem.';
                        $emptyCta = 'Ver todos os pedidos';
                        $emptyLink = 'orders.php';
                        $emptyIcon = 'fa-box-open';
                    } else {
                        $emptyTitle = 'Nenhum pedido ainda';
                        $emptyText = 'Faça suas compras e acompanhe todos os pedidos aqui.';
                        $emptyCta = 'Ver Produtos';
                        $emptyLink = '../products/products.php';
                        $emptyIcon = 'fa-store';
                    }
                    ?>
                    <div class="ac-card" style="padding:0;">
                        <div class="ac-empty">
                            <div class="ac-empty-icon"><i class="fas <?php echo $emptyIcon; ?>"></i></div>
                            <h3><?php echo htmlspecialchars($emptyTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?php echo htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8'); ?></p>
                            <a href="<?php echo htmlspecialchars($emptyLink, ENT_QUOTES, 'UTF-8'); ?>" class="ac-btn-save" style="width:auto; padding:12px 28px; text-decoration:none;"><i class="fas <?php echo $emptyIcon; ?>"></i> <?php echo htmlspecialchars($emptyCta, ENT_QUOTES, 'UTF-8'); ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <?php
                        $oid = (int) $o['id'];
                        $items = orderGetItems($pdo, $oid);
                        $payState = (string) ($o['payment_status'] ?? 'pending');
                        $isPayAttention = in_array($payState, ['pending', 'processing'], true);
                        $isPayFailed = in_array($payState, ['failed', 'expired'], true);
                        $cardExtra = $isPayAttention ? ' status-pending-pay' : '';
                        $statusIcon = $orderStatusIcons[$o['status']] ?? 'fas fa-box';
                        $statusLabel = $statusLabels[$o['status']]['label'] ?? $o['status'];
                        ?>
                        <div class="ac-order-card<?php echo $cardExtra; ?>">
                            <!-- Cabeçalho -->
                            <div class="ac-order-head">
                                <div>
                                    <div class="ac-order-id">Pedido #<?php echo str_pad((string) $oid, 4, '0', STR_PAD_LEFT); ?></div>
                                    <div class="ac-order-date"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($o['created_at'])); ?> &middot; <?php echo date('H:i', strtotime($o['created_at'])); ?> &middot; <?php echo (int) $o['item_count']; ?> item(ns)</div>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                    <?php if ($isPayAttention): ?>
                                        <span class="ac-pay-badge"><i class="far fa-hourglass"></i> <?php echo $payState === 'processing' ? 'Processando pagamento' : 'Aguardando pagamento'; ?></span>
                                    <?php elseif ($isPayFailed): ?>
                                        <span class="ac-status ac-status-canceled"><i class="fas fa-exclamation-triangle"></i> <?php echo $payState === 'failed' ? 'Pagamento falhou' : 'Pagamento expirado'; ?></span>
                                    <?php endif; ?>
                                    <span class="ac-status ac-status-<?php echo htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8'); ?>"><i class="<?php echo $statusIcon; ?>"></i> <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>

                            <?php echo renderOrderTimeline($o, true); ?>

                            <!-- Itens -->
                            <div class="ac-order-items">
                                <?php
                                $shown = 0;
                                $visibleItems = array_slice($items, 0, 3);
                                foreach ($visibleItems as $item):
                                    $shown++;
                                    $img = renderProductImage((string) ($item['image_path'] ?? ''), $base_path);
                                ?>
                                    <div class="ac-order-item">
                                        <img class="ac-order-thumb" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="ac-order-item-info">
                                            <p class="ac-order-item-name"><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                            <div class="ac-order-item-meta"><?php echo htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?> &middot; Qtd: <?php echo (int) ($item['quantity'] ?? 0); ?></div>
                                        </div>
                                        <span class="ac-order-item-price">R$ <?php echo number_format((float) ($item['unit_price'] ?? 0), 2, ',', '.'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($items) > $shown): ?>
                                    <div style="font-size:0.82rem; color:var(--ml-text-muted);">
                                        <i class="fas fa-ellipsis-h"></i> +<?php echo (int) (count($items) - $shown); ?> outro(s) — <a href="order-detail.php?id=<?php echo $oid; ?>" style="color:var(--ml-accent);">ver todos</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Rodapé -->
                            <div class="ac-order-foot">
                                <div class="ac-order-total">
                                    Total: <strong>R$ <?php echo number_format((float) $o['total'], 2, ',', '.'); ?></strong>
                                    <?php if (!empty($o['payment_method'])): ?>
                                        <div style="font-size:0.8rem; margin-top:2px;">
                                            <i class="fas fa-<?php echo $o['payment_method'] === 'pix' ? 'qrcode' : ($o['payment_method'] === 'boleto' ? 'barcode' : 'credit-card'); ?>"></i>
                                            <?php echo htmlspecialchars($payLabels[$o['payment_method']] ?? $o['payment_method'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($o['payment_method'] === 'credit' && !empty($o['payment_card_last_four'])): ?>
                                                &middot; •••• <?php echo htmlspecialchars($o['payment_card_last_four'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="ac-order-actions">
                                    <?php if ($o['status'] === 'pending' && ($isPayAttention || $isPayFailed)): ?>
                                        <a class="ac-order-btn ac-order-btn-pay" href="../cart/payment.php?id=<?php echo $oid; ?>"><i class="fas fa-credit-card"></i> Pagar agora</a>
                                    <?php endif; ?>
                                    <?php if ($o['status'] === 'shipped' && !empty($o['tracking_code'])): ?>
                                        <button type="button" class="ac-order-btn ac-order-btn-track" data-tracking="<?php echo htmlspecialchars($o['tracking_code'], ENT_QUOTES, 'UTF-8'); ?>" onclick="showTracking(this)"><i class="fas fa-truck"></i> Rastrear</button>
                                    <?php endif; ?>
                                    <a class="ac-order-btn ac-order-btn-details" href="order-detail.php?id=<?php echo $oid; ?>"><i class="fas fa-eye"></i> Ver detalhes</a>
                                    <?php if ($o['status'] === 'pending'): ?>
                                        <form method="post" class="inline" onsubmit="return confirm('Tem certeza que deseja cancelar o pedido #<?php echo str_pad((string) $oid, 4, '0', STR_PAD_LEFT); ?>?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                            <button type="submit" class="ac-order-btn ac-order-btn-cancel"><i class="fas fa-times-circle"></i> Cancelar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($o['status'], ['paid', 'delivered'], true)): ?>
                                        <form method="post" class="inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="rebuy">
                                            <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                            <button type="submit" class="ac-order-btn ac-order-btn-rebuy"><i class="fas fa-cart-plus"></i> Comprar novamente</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Paginação -->
                    <?php if ($totalPages > 1): ?>
                        <div class="ac-pagination">
                            <?php
                            $qsBase = array_filter([
                                'f' => $filter !== 'todos' ? $filter : null,
                                'busca' => $busca !== '' ? $busca : null,
                            ]);
                            $prevDisabled = $page <= 1;
                            $nextDisabled = $page >= $totalPages;
                            ?>
                            <?php if (!$prevDisabled): ?>
                                <a class="ac-page-btn" href="orders.php?<?php echo http_build_query($qsBase + ['pg' => $page - 1]); ?>"><i class="fas fa-chevron-left"></i></a>
                            <?php else: ?>
                                <span class="ac-page-btn" disabled><i class="fas fa-chevron-left"></i></span>
                            <?php endif; ?>

                            <?php
                            $startPg = max(1, min($totalPages - 2, $page - 1));
                            $endPg = min($totalPages, $startPg + 2);
                            for ($i = $startPg; $i <= $endPg; $i++):
                            ?>
                                <a class="ac-page-btn<?php echo $i === $page ? ' is-active' : ''; ?>" href="orders.php?<?php echo http_build_query($qsBase + ['pg' => $i]); ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if (!$nextDisabled): ?>
                                <a class="ac-page-btn" href="orders.php?<?php echo http_build_query($qsBase + ['pg' => $page + 1]); ?>"><i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="ac-page-btn" disabled><i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Modal de rastreamento -->
<div class="ac-modal-overlay" id="trackModal">
    <div class="ac-modal">
        <div class="ac-modal-head">
            <h3><i class="fas fa-shipping-fast"></i> Encomenda em trânsito</h3>
            <button type="button" class="ac-modal-close" onclick="closeTracking()" aria-label="Fechar"><i class="fas fa-times"></i></button>
        </div>
        <div class="ac-modal-body" style="text-align:center;">
            <p style="margin:0 0 14px; font-size:0.85rem; color:var(--ml-text-muted);">Acompanhe sua entrega pelo código abaixo:</p>
            <div class="ac-track-box" id="trackCode"></div>
        </div>
        <div class="ac-modal-foot">
            <button type="button" onclick="closeTracking()" class="ac-btn-save" style="flex:1; background:#2a2a2a; color:var(--ml-text); box-shadow:none;">Fechar</button>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>

<script>
  function showTracking(el) {
    var code = el.getAttribute('data-tracking') || '';
    var modal = document.getElementById('trackModal');
    if (!modal) return;
    document.getElementById('trackCode').textContent = code;
    modal.classList.add('is-open');
  }
  function closeTracking() {
    var modal = document.getElementById('trackModal');
    if (modal) modal.classList.remove('is-open');
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeTracking();
  });
  var trackModal = document.getElementById('trackModal');
  if (trackModal) {
    trackModal.addEventListener('click', function (e) {
      if (e.target === trackModal) closeTracking();
    });
  }
</script>
