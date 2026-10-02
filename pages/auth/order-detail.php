<?php

declare(strict_types=1);

/**
 * Detalhes do Pedido — progresso, itens, entrega, pagamento e ações.
 *
 * Layout novo (cards, tracker, lista de itens, totais com desconto/frete,
 * endereço em uma linha, ações por estado). A regra de negócio permanece
 * em includes/order_state.php e order_repo.php.
 */

require_once __DIR__ . '/../../includes/account_layout.php';
require_once __DIR__ . '/../../includes/order_repo.php';
require_once __DIR__ . '/../../includes/order_state.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
require_once __DIR__ . '/../../database/connection.php';

$user = account_require_login($pdo);
$isAdmin = (string) ($user['role'] ?? '') === 'admin';

$orderId = (int) ($_GET['id'] ?? 0);

// ---------------------------------------------------------------------
//  Cancelamento (POST com CSRF)
// ---------------------------------------------------------------------
$message     = null;
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_require_valid();

    $provisional = order_repo_find_authorized($pdo, $orderId, (int) $user['id'], $isAdmin);

    if ($provisional === null) {
        $message     = 'Pedido não encontrado.';
        $messageType = 'error';
    } else {
        $result = order_apply_status($pdo, $orderId, 'canceled', [
            'note' => 'Cancelado pelo cliente',
        ]);

        if ($result['ok']) {
            $message = 'Pedido cancelado com sucesso.';
        } else {
            $message     = (string) $result['error'];
            $messageType = 'error';
        }
    }
}

// ---------------------------------------------------------------------
//  Leitura do pedido (após o POST, para mostrar o estado corrente)
// ---------------------------------------------------------------------
$order = order_repo_find_authorized($pdo, $orderId, (int) $user['id'], $isAdmin);

if ($order === null) {
    header('Location: ' . base_url('pages/auth/orders.php'));
    exit;
}

// Expiração preguiçosa de Pix pendente (reutiliza a mesma lógica do worker)
require_once __DIR__ . '/../../includes/order_state.php';
$expireResult = order_expire_pending_pix_lazy($pdo, $order);
if ($expireResult['expired']) {
    $order = $expireResult['order'];
}

$items      = order_repo_items($pdo, $orderId);
$paymentRow = order_repo_payment($pdo, $orderId);
$progress   = order_progress($pdo, $order);
$orderActions = order_actions_available($order);
$addressLine = order_repo_address_line($order);

$orderRef   = str_pad((string) $order['id'], 4, '0', STR_PAD_LEFT);
$statusMeta = order_status_meta((string) $order['status']);
$payMethod  = (string) ($order['payment_method'] ?? '');
$cardLastFour = (string) ($order['payment_card_last_four'] ?? '');
$payStatusLabel = payment_label((string) ($order['payment_status'] ?? ''));

// Shipment / rastreio
$shipmentEvents = order_repo_tracking_events($pdo, $orderId);
$trackUrl = trim((string) (($shipmentEvents[0]['label_url'] ?? '')));

$page_title = 'Detalhes do Pedido - Royal Tech';
account_layout_head($user, 'pedidos', 'Detalhes do Pedido');
?>

<?php if ($message !== null): ?>
    <div class="account-alert account-alert--<?php echo $messageType === 'ok' ? 'ok' : 'error'; ?>" role="status">
        <i class="fas <?php echo $messageType === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>" aria-hidden="true"></i>
        <span><?php echo e($message); ?></span>
    </div>
<?php endif; ?>

<div class="account-page-header">
    <div class="account-page-header-row">
        <div>
            <h1 class="account-page-title">Pedido #<?php echo e($orderRef); ?></h1>
            <p class="account-page-subtitle">
                Feito em <?php echo e(date('d/m/Y H:i', strtotime((string) $order['created_at']))); ?>
                &middot; <?php echo count($items); ?> item<?php echo count($items) > 1 ? 's' : ''; ?>
            </p>
        </div>
        <span class="account-status-pill account-status-pill--<?php echo e($statusMeta['tone']); ?>">
            <i class="fas <?php echo e($statusMeta['icon']); ?>" aria-hidden="true"></i>
            <?php echo e($statusMeta['label']); ?>
        </span>
    </div>
</div>

<section class="account-card" id="secao-detalhe">

    <!-- ===================== Card: Progresso do Pedido ===================== -->
    <?php if (!empty($progress['steps'])): ?>
        <div class="account-card-inner" aria-label="Andamento do pedido">
            <div class="account-card-head">
                <span class="account-card-icon"><i class="fas fa-route" aria-hidden="true"></i></span>
                <h2 class="account-card-title">Progresso do pedido</h2>
            </div>

            <ol class="tracker" aria-label="Etapas do pedido #<?php echo e($orderRef); ?>">
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

            <?php if ($progress['canceled'] ?? false): ?>
                <div class="order-notice order-notice--danger" style="margin-top:12px;" role="status">
                    <i class="fas fa-ban" aria-hidden="true"></i>
                    <div>
                        <strong>Pedido cancelado</strong>
                        <?php if ($progress['canceled_at'] ?? null): ?>
                            <span> em <?php echo e(date('d/m/Y H:i', strtotime((string) $progress['canceled_at']))); ?>.</span>
                        <?php endif; ?>
                        <?php
                        // Motivo: Pix expirado ou cancelamento voluntário
                        $cancelNote = trim((string) ($progress['canceled_note'] ?? ''));
                        $payStatus  = (string) ($order['payment_status'] ?? '');
                        if ($payStatus === 'expired'): ?>
                            <span class="account-banner-note">Pix expirado em
                                <?php echo e(date('d/m/Y H:i', strtotime((string) $order['payment_expires_at']))); ?>
                                (estoque liberado).</span>
                        <?php elseif ($cancelNote !== ''): ?>
                            <span class="account-banner-note"><?php echo e($cancelNote); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ===================== Card: Itens do Pedido ===================== -->
    <div class="account-card-inner" style="margin-top:14px;">
        <div class="account-card-head">
            <span class="account-card-icon"><i class="fas fa-box" aria-hidden="true"></i></span>
            <div>
                <h2 class="account-card-title">Itens do pedido</h2>
                <span class="account-card-pill"><?php echo count($items); ?> item<?php echo count($items) > 1 ? 's' : ''; ?></span>
            </div>
        </div>

        <ul class="account-items">
            <?php foreach ($items as $item):
                $img   = renderProductImage((string) ($item['product_image'] ?? ''), base_url());
                $qty   = (int) $item['quantity'];
                $unit  = (float) $item['unit_price'];
                $sub   = $unit * $qty;
                $name  = (string) $item['display_name'];
            ?>
                <li class="account-item-line">
                    <img src="<?php echo e($img); ?>" alt="" class="account-item-thumb" loading="lazy">
                    <div class="account-item-info">
                        <a href="<?php echo e(base_url('pages/products/detail.php?id=' . (int) $item['product_id'])); ?>"
                           class="account-item-name"><?php echo e($name); ?></a>
                        <span class="account-item-meta"><?php echo $qty; ?> x R$ <?php echo e(number_format($unit, 2, ',', '.')); ?></span>
                    </div>
                    <span class="account-item-subtotal">R$ <?php echo e(number_format($sub, 2, ',', '.')); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Totais: Subtotal · Desconto · Frete · Total -->
        <?php
            $subtotal = 0;
            foreach ($items as $it) { $subtotal += (float) $it['unit_price'] * (int) $it['quantity']; }
            $shipping   = (float) ($order['shipping_cost'] ?? 0);
            $discount   = (float) ($order['discount_amount'] ?? 0);
            $grandTotal = (float) $order['total'];
        ?>
        <div class="totals" aria-label="Resumo financeiro">
            <div class="totals__row">
                <span class="totals__label">Subtotal dos produtos</span>
                <span class="totals__value">R$ <?php echo e(number_format($subtotal, 2, ',', '.')); ?></span>
            </div>
            <?php if ($discount > 0): ?>
                <div class="totals__row totals__row--discount">
                    <span class="totals__label">Desconto Pix (5%)</span>
                    <span class="totals__value totals__value--discount">&minus; R$ <?php echo e(number_format($discount, 2, ',', '.')); ?></span>
                </div>
            <?php endif; ?>
            <div class="totals__row totals__row--free">
                <span class="totals__label">Frete</span>
                <span class="totals__value totals__value--free">
                    <?php if ($shipping > 0): ?>
                        R$ <?php echo e(number_format($shipping, 2, ',', '.')); ?>
                    <?php else: ?>
                        Grátis
                    <?php endif; ?>
                </span>
            </div>
            <div class="totals__row totals__row--total">
                <span class="totals__label">Total</span>
                <span class="totals__value totals__value--total">R$ <?php echo e(number_format($grandTotal, 2, ',', '.')); ?></span>
            </div>
        </div>
    </div>

    <!-- ===================== Grid: Entrega | Pagamento ===================== -->
    <div class="grid-2" style="margin-top:14px;">

        <!-- Entrega -->
        <div class="account-card-inner">
            <div class="account-card-head">
                <span class="account-card-icon"><i class="fas fa-truck" aria-hidden="true"></i></span>
                <h2 class="account-card-title">Entrega</h2>
            </div>

            <dl class="account-kv">
                <dt>Método</dt>
                <dd>
                    <?php
                        $shipMethod = (string) ($order['shipping_method'] ?? '');
                        $shipCost   = (float) ($order['shipping_cost'] ?? 0);
                        echo e(shipping_method_escaped($shipMethod));
                        if ($shipCost > 0) {
                            echo ' &middot; R$ ' . number_format($shipCost, 2, ',', '.');
                        } else {
                            echo ' &middot; <span style="color:var(--rt-success);">Grátis</span>';
                        }
                    ?>
                </dd>

                <dt>Endereço</dt>
                <dd><?php echo $addressLine !== '' ? e($addressLine) : 'Endereço não registrado'; ?></dd>

                <?php if (trim((string) ($order['tracking_code'] ?? '')) !== ''): ?>
                    <dt>Rastreio</dt>
                    <dd>
                        <code><?php echo e((string) $order['tracking_code']); ?></code>
                        <?php if ($trackUrl !== ''): ?>
                            <a href="<?php echo e($trackUrl); ?>" target="_blank" rel="noopener noreferrer"
                               class="account-btn account-btn--outline account-btn--sm" style="margin-left:8px;">
                                <i class="fas fa-external-link-alt" aria-hidden="true"></i> Ver no site
                            </a>
                        <?php endif; ?>
                    </dd>
                <?php endif; ?>

                <?php if (!empty($shipmentEvents)): ?>
                    <dt>Eventos</dt>
                    <dd>
                        <ul style="margin:0;padding-left:18px;font-size:13px;">
                            <?php foreach ($shipmentEvents as $evt): ?>
                                <li><?php echo e((string) $evt['status']); ?>
                                    <?php if ($evt['date'] ?? null): ?>
                                        &middot; <?php echo e(date('d/m/Y H:i', strtotime((string) $evt['date']))); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </dd>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Pagamento -->
        <div class="account-card-inner">
            <div class="account-card-head">
                <span class="account-card-icon"><i class="fas fa-credit-card" aria-hidden="true"></i></span>
                <h2 class="account-card-title">Pagamento</h2>
            </div>

            <dl class="account-kv">
                <dt>Método</dt>
                <dd><?php echo e(payment_method_label($payMethod)); ?></dd>

                <dt>Status</dt>
                <dd>
                    <?php
                        $payStatus = (string) ($order['payment_status'] ?? '');
                        $tone = match ($payStatus) {
                            'paid' => 'success',
                            'pending' => 'warning',
                            'expired', 'canceled', 'failed' => 'danger',
                            'refunded' => 'info',
                            default => 'secondary',
                        };
                    ?>
                    <span class="account-status-pill account-status-pill--<?php echo $tone; ?>">
                        <?php echo e($payStatusLabel); ?>
                    </span>
                </dd>

                <?php if ($payStatus === 'pending' && $payMethod === 'pix' && $order['payment_expires_at'] ?? null): ?>
                    <dt>Expira em</dt>
                    <dd>
                        <div class="pix-countdown-wrap" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <strong class="pix-countdown"
                                    data-pix-deadline="<?php echo strtotime((string) $order['payment_expires_at']); ?>">
                                <?php echo e(orders_format_remaining(strtotime((string) $order['payment_expires_at']))); ?>
                            </strong>
                            <button type="button" class="account-btn account-btn--outline account-btn--sm"
                                    onclick="copyPixCode()"
                                    aria-label="Copiar código Pix">
                                <i class="fas fa-copy" aria-hidden="true"></i> Copiar código
                            </button>
                            <script>
                                function copyPixCode() {
                                    const code = document.querySelector('[data-pix-code]')?.textContent?.trim();
                                    if (code) {
                                        navigator.clipboard.writeText(code).then(() => {
                                            alert('Código Pix copiado!');
                                        });
                                    }
                                }
                            </script>
                        </div>
                    </dd>
                <?php elseif ($payStatus === 'expired'): ?>
                    <dt>Expirado em</dt>
                    <dd><?php echo e(date('d/m/Y H:i', strtotime((string) $order['payment_expires_at']))); ?></dd>
                <?php elseif ($payStatus === 'paid'): ?>
                    <dt>Pago em</dt>
                    <dd><?php echo e(date('d/m/Y H:i', strtotime((string) ($paymentRow['paid_at'] ?? $order['created_at'])))); ?></dd>
                <?php elseif ($payStatus === 'refunded'): ?>
                    <dt>Reembolsado em</dt>
                    <dd><?php echo e(date('d/m/Y H:i', strtotime((string) ($paymentRow['canceled_at'] ?? $order['created_at'])))); ?></dd>
                <?php endif; ?>

                <?php if ($cardLastFour !== ''): ?>
                    <dt>Cartão</dt>
                    <dd><?php echo e($payMethod === 'cartao' ? ucfirst($payMethod) : 'Cartão'); ?> final <?php echo e($cardLastFour); ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <!-- ===================== Barra de Ações ===================== -->
    <div class="account-actions" style="margin-top:14px;">
        <a href="<?php echo e(base_url('pages/auth/orders.php')); ?>" class="account-btn account-btn--outline">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar
        </a>

        <?php if ($orderActions['receipt']): ?>
            <a href="<?php echo e(base_url('pages/download-comprovante.php?id=' . $orderId)); ?>"
               class="account-btn" target="_blank" rel="noopener">
                <i class="fas fa-file-pdf" aria-hidden="true"></i> Baixar comprovante
            </a>
        <?php endif; ?>

        <?php if ($orderActions['resend']): ?>
            <form method="post" action="<?php echo e(base_url('pages/auth/comprovante-resend.php')); ?>"
                  style="display:inline" onsubmit="return confirm('Reenviar o comprovante por e-mail?')">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                <button type="submit" class="account-btn">
                    <i class="fas fa-redo" aria-hidden="true"></i> Reenviar por e-mail
                </button>
            </form>
        <?php endif; ?>

        <?php if ($orderActions['pay']): ?>
            <a href="<?php echo e(base_url('pages/cart/payment.php?order=' . $orderId)); ?>"
               class="account-btn account-btn--primary">
                <i class="fas fa-qrcode" aria-hidden="true"></i> Pagar agora
            </a>
        <?php endif; ?>

        <?php if ($orderActions['cancel']): ?>
            <form method="post" style="display:inline"
                  onsubmit="return confirm('Tem certeza que deseja cancelar este pedido?')">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" value="<?php echo $orderId; ?>">
                <button type="submit" class="account-btn account-btn--danger">
                    <i class="fas fa-times-circle" aria-hidden="true"></i> Cancelar pedido
                </button>
            </form>
            <span class="account-cancel-rule">
                <?php if ($order['status'] === 'pending'): ?>
                    Você pode cancelar enquanto o pagamento não for aprovado.
                <?php elseif ($order['status'] === 'paid'): ?>
                    Você pode cancelar até a postagem do pedido.
                <?php else: ?>
                    Você pode cancelar até a etiqueta de envio ser gerada.
                <?php endif; ?>
            </span>
        <?php endif; ?>

        <?php if ($orderActions['track'] && $trackUrl !== ''): ?>
            <a href="<?php echo e($trackUrl); ?>" class="account-btn account-btn--primary"
               target="_blank" rel="noopener noreferrer">
                <i class="fas fa-truck-fast" aria-hidden="true"></i> Rastrear
            </a>
        <?php endif; ?>

        <?php if ($orderActions['rebuy'] && !empty($items)): ?>
            <form method="post" id="reorderForm" style="display:inline"
                  action="<?php echo e(base_url('pages/cart/add.php')); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="redirect" value="<?php echo e(base_url('pages/cart/cart.php')); ?>">
                <?php foreach ($items as $rebuyItem): ?>
                    <input type="hidden" name="product_id[]" value="<?php echo (int) $rebuyItem['product_id']; ?>">
                    <input type="hidden" name="quantity[]" value="<?php echo (int) $rebuyItem['quantity']; ?>">
                <?php endforeach; ?>
                <button type="submit" class="account-btn account-btn--primary" id="reorderBtn">
                    <i class="fas fa-rotate-right" aria-hidden="true"></i> Comprar novamente
                </button>
            </form>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    'use strict';
    var form = document.getElementById('reorderForm');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var ids = form.querySelectorAll('input[name="product_id[]"]');
        var qty = form.querySelectorAll('input[name="quantity[]"]');
        var csrf = form.querySelector('input[name="_csrf_token"]').value;
        var redirect = form.querySelector('input[name="redirect"]').value;

        var btn = document.getElementById('reorderBtn');
        if (btn) btn.disabled = true;

        var pending = ids.length;

        function done() {
            pending--;
            if (pending <= 0) {
                window.location.href = redirect;
            }
        }

        for (var i = 0; i < ids.length; i++) {
            var body = new FormData();
            body.append('_csrf_token', csrf);
            body.append('product_id', ids[i].value);
            body.append('quantity', qty[i].value);

            fetch(form.action, {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function () { done(); })
                .catch(function () { done(); });
        }
    });
})();
</script>

<?php account_layout_foot(); ?>
