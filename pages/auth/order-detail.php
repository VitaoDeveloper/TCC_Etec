<?php
$page_title = 'Detalhes do Pedido - Royal Tech';
$breadcrumb_title = 'Detalhes do Pedido';
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

$userId  = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['id'] ?? 0);

$stmtUser = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
$stmtUser->execute([':id' => $userId]);
$user = $stmtUser->fetch();
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Pedido SEMPRE do dono — fetch antes de qualquer POST (impede cancelamento cruzado)
$stmtOrder = $pdo->prepare(
    'SELECT o.*,
            (SELECT COUNT(*) FROM e5_order_items oi WHERE oi.order_id = o.id) AS item_count
       FROM e5_orders o
      WHERE o.id = :id AND o.user_id = :uid LIMIT 1'
);
$stmtOrder->execute([':id' => $orderId, ':uid' => $userId]);
$order = $stmtOrder->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$message     = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_require_valid();
    if (orderCancelCustomer($pdo, $orderId)) {
        $message = 'Pedido cancelado com sucesso.';
        $stmtOrder->execute([':id' => $orderId, ':uid' => $userId]);
        $order = $stmtOrder->fetch();
    } else {
        $errorMessage = 'Não é possível cancelar este pedido.';
    }
}

$items = orderGetItems($pdo, $orderId);

// Subtotal / frete / total
$subtotal = 0.0;
foreach ($items as $it) {
    $subtotal += (float) $it['unit_price'] * (int) $it['quantity'];
}
$subtotal  = round($subtotal, 2);
$shipping  = (float) $order['shipping_cost'];
$totalCalc = round($subtotal + $shipping, 2);

// Dados da sidebar
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
$statusIcons = [
    'pending'   => 'far fa-hourglass',
    'paid'      => 'fas fa-box',
    'shipped'   => 'fas fa-shipping-fast',
    'delivered' => 'fas fa-check-circle',
    'canceled'  => 'fas fa-times-circle',
];
$statusLabel = $statusLabels[$order['status']]['label'] ?? $order['status'];
$statusIcon  = $statusIcons[$order['status']] ?? 'fas fa-box';

$payState    = (string) ($order['payment_status'] ?? 'pending');
$isPayAttention = in_array($payState, ['pending', 'processing'], true);
$isPayFailed = in_array($payState, ['failed', 'expired'], true);

$psInfo  = $paymentStatusLabels[$payState] ?? null;
$psColors = [
    'paid'       => '#4cd58a',
    'processing' => '#42a5f5',
    'pending'    => '#ffc107',
    'failed'     => '#ff8f8f',
    'expired'    => '#ff8f8f',
    'refunded'   => '#ff8f8f',
];
$psLabel = $psInfo['label'] ?? $payState;
$psColor = $psColors[$payState] ?? 'var(--ml-text-muted)';

$addrParts = array_filter([
    $order['shipping_neighborhood'] ?? '',
    $order['shipping_city'] ?? '',
    $order['shipping_state'] ?? '',
]);
$addrLine = implode(', ', $addrParts);
if (!empty($order['shipping_postal_code'])) {
    $addrLine .= $addrLine !== '' ? ' — CEP ' : 'CEP ';
    $addrLine .= htmlspecialchars($order['shipping_postal_code'], ENT_QUOTES, 'UTF-8');
}

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
                        <h1>Pedido #<?php echo str_pad((string) $orderId, 4, '0', STR_PAD_LEFT); ?></h1>
                        <p>Feito em <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?> &middot; <?php echo (int) $order['item_count']; ?> <?php echo (int) $order['item_count'] === 1 ? 'item' : 'itens'; ?></p>
                    </div>
                    <span class="ac-status ac-status-<?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="<?php echo $statusIcon; ?>"></i> <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <?php if ($message): ?>
                    <div class="ac-feedback ac-feedback-success"><i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <?php endif; ?>
                <?php if ($errorMessage): ?>
                    <div class="ac-feedback ac-feedback-error"><i class="fas fa-exclamation-triangle"></i> <span><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <?php endif; ?>

                <!-- Timeline completa -->
                <div class="ac-card ac-card-tl">
                    <div class="ac-tl-head">
                        <span class="ac-tl-title"><i class="fas fa-route"></i> Progresso do pedido</span>
                        <?php if ($order['status'] === 'pending' && ($isPayAttention || $isPayFailed)): ?>
                            <a class="ac-order-btn ac-order-btn-pay" href="../cart/payment.php?id=<?php echo $orderId; ?>"><i class="fas fa-credit-card"></i> Pagar agora</a>
                        <?php endif; ?>
                    </div>
                    <?php echo renderOrderTimeline($order, false); ?>
                </div>

                <!-- Itens do pedido -->
                <div class="ac-card">
                    <div class="ac-card-head ac-card-head-tight">
                        <span class="ac-card-icon"><i class="fas fa-box-open"></i></span>
                        <div class="ac-card-title-wrap">
                            <h2 class="ac-card-title">Itens do pedido</h2>
                        </div>
                        <span class="ac-card-count"><?php echo (int) $order['item_count']; ?> <?php echo (int) $order['item_count'] === 1 ? 'item' : 'itens'; ?></span>
                    </div>
                    <div class="ac-order-items-detail">
                        <?php foreach ($items as $item):
                            $img = renderProductImage((string) ($item['image_path'] ?? ''), $base_path);
                            $itemSub = round((float) $item['unit_price'] * (int) $item['quantity'], 2);
                        ?>
                            <div class="ac-order-item">
                                <img class="ac-order-thumb" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="ac-order-item-info">
                                    <p class="ac-order-item-name"><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="ac-order-item-meta">
                                        <?php echo htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?> &middot;
                                        <?php echo (int) $item['quantity']; ?> x R$ <?php echo number_format((float) $item['unit_price'], 2, ',', '.'); ?>
                                    </div>
                                </div>
                                <span class="ac-order-item-price">R$ <?php echo number_format($itemSub, 2, ',', '.'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="ac-order-summary">
                        <div style="display:flex; justify-content:space-between;"><span style="color:var(--ml-text-muted);">Subtotal dos produtos</span><span>R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span></div>
                        <div style="display:flex; justify-content:space-between;">
                            <span style="color:var(--ml-text-muted);">Frete</span>
                            <?php if ($shipping > 0): ?>
                                <span>R$ <?php echo number_format($shipping, 2, ',', '.'); ?></span>
                            <?php else: ?>
                                <span style="color:var(--ml-green); font-weight:600;">Grátis</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:10px; border-top:1px dashed rgba(255,255,255,0.08);">
                            <span style="font-weight:700;">Total</span>
                            <strong style="font-size:1.15rem; color:var(--ml-accent);">R$ <?php echo number_format($totalCalc, 2, ',', '.'); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Entrega / Pagamento -->
                <div class="ac-detail-grid">
                    <div class="ac-card ac-mini">
                        <div class="ac-mini-head">
                            <i class="fas fa-truck"></i>
                            <h3>Entrega</h3>
                        </div>
                        <div class="ac-info-row">
                            <span class="ac-info-label">Método</span>
                            <?php echo htmlspecialchars($order['shipping_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($shipping > 0): ?> &middot; R$ <?php echo number_format($shipping, 2, ',', '.'); ?><?php else: ?> &middot; <span style="color:var(--ml-green);">Grátis</span><?php endif; ?>
                        </div>
                        <?php if ($addrLine != ''): ?>
                            <div class="ac-info-row">
                                <span class="ac-info-label">Endereço</span>
                                <?php echo $addrLine; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['status'] === 'shipped' && !empty($order['tracking_code'])): ?>
                            <div class="ac-info-row">
                                <span class="ac-info-label">Código de rastreio</span>
                                <div class="ac-track-box"><?php echo htmlspecialchars($order['tracking_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="ac-card ac-mini">
                        <div class="ac-mini-head">
                            <i class="fas fa-credit-card"></i>
                            <h3>Pagamento</h3>
                        </div>
                        <div class="ac-info-row">
                            <span class="ac-info-label">Método</span>
                            <i class="fas fa-<?php echo $order['payment_method'] === 'pix' ? 'qrcode' : ($order['payment_method'] === 'boleto' ? 'barcode' : 'credit-card'); ?>"></i>
                            <?php echo htmlspecialchars($payLabels[$order['payment_method']] ?? $order['payment_method'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($order['payment_method'] === 'credit' && !empty($order['payment_card_last_four'])): ?>
                                &middot; •••• <?php echo htmlspecialchars($order['payment_card_last_four'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                        </div>
                        <div class="ac-info-row">
                            <span class="ac-info-label">Status</span>
                            <span style="color:<?php echo $psColor; ?>; font-weight:600;"><i class="fas fa-circle" style="font-size:0.55rem; vertical-align:middle; margin-right:6px;"></i><?php echo htmlspecialchars($psLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php if ($order['status'] === 'pending' && $isPayFailed): ?>
                            <a class="ac-order-btn ac-order-btn-pay" style="margin-top:12px;" href="../cart/payment.php?id=<?php echo $orderId; ?>"><i class="fas fa-redo"></i> Retomar pagamento</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ações -->
                <div class="ac-order-actions" style="margin-top:16px;">
                    <a class="ac-order-btn ac-order-btn-details" href="orders.php"><i class="fas fa-arrow-left"></i> Voltar</a>
                    <?php if ($order['status'] === 'pending'): ?>
                        <form method="post" class="inline" onsubmit="return confirm('Tem certeza que deseja cancelar este pedido?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="ac-order-btn ac-order-btn-cancel"><i class="fas fa-times-circle"></i> Cancelar pedido</button>
                        </form>
                    <?php endif; ?>
                    <a class="ac-order-btn ac-order-btn-track" href="../download-comprovante.php?id=<?php echo $orderId; ?>" target="_blank"><i class="fas fa-file-pdf"></i> Baixar comprovante</a>
                    <form method="post" action="comprovante-resend.php" class="inline" onsubmit="return confirm('Reenviar o comprovante por e-mail?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                        <button type="submit" class="ac-order-btn ac-order-btn-details"><i class="fas fa-redo"></i> Reenviar por e-mail</button>
                    </form>
                    <?php if (in_array($order['status'], ['delivered', 'paid'], true)): ?>
                        <form method="post" action="orders.php" class="inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="rebuy">
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <button type="submit" class="ac-order-btn ac-order-btn-rebuy"><i class="fas fa-cart-plus"></i> Comprar novamente</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>
<?php include '../../components/footer.php'; ?>
