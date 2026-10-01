<?php

declare(strict_types=1);

/**
 * Detalhes do Pedido — progresso, itens, entrega, pagamento e ações.
 *
 * Vive no shell da conta, com a sidebar marcando "Meus Pedidos".
 *
 * O domínio inteiro mora em includes/order_state.php e order_repo.php:
 * aqui só se lê e se aplica. Isso vale para a linha do tempo (alimentada
 * por e5_order_history), para a faixa vermelha de cancelamento e para a
 * matriz de botões (order_actions_available).
 *
 * A regra de cancelamento aparece como uma frase discreta ao lado do
 * botão, e nunca como card de política: a especificação proíbe card de
 * política nesta tela e o cliente só precisa saber se pode cancelar
 * AGORA.
 */

require_once __DIR__ . '/../../includes/account_layout.php';
require_once __DIR__ . '/../../includes/order_repo.php';
require_once __DIR__ . '/../../includes/order_state.php';
require_once __DIR__ . '/../../includes/status_labels.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
require_once __DIR__ . '/../../database/connection.php';

$user = account_require_login($pdo);

$isAdmin = (string) ($user['role'] ?? '') === 'admin';

// ---------------------------------------------------------------------
//  Cancelamento (POST com CSRF)
// ---------------------------------------------------------------------
$message      = null;
$messageType  = 'ok';
$orderId      = (int) ($_GET['id'] ?? 0);
$order        = null;
$orderActions = [];
$paymentRow   = null;
$progress     = [];
$items        = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_require_valid();

    $provisional = order_repo_find_authorized($pdo, $orderId, (int) $user['id'], $isAdmin);

    if ($provisional === null) {
        $message     = 'Pedido não encontrado.';
        $messageType = 'error';
    } else {
        // A transição é validada dentro de order_apply_status: ela devolve
        // um erro claro em vez de gravar uma mudança inválida, e ainda
        // registra o histórico, o estoque e o pagamento juntos.
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

$items       = order_repo_items($pdo, $orderId);
$paymentRow  = order_repo_payment($pdo, $orderId);
$progress    = order_progress($pdo, $order);
$orderActions = order_actions_available($order);

$orderRef   = str_pad((string) $order['id'], 4, '0', STR_PAD_LEFT);
$statusMeta = order_status_meta((string) $order['status']);
$addressLine = order_repo_address_line($order);

// Cartão de pagamento parcial, se houver.
$cardLastFour = (string) ($order['payment_card_last_four'] ?? '');
$payMethod    = (string) ($order['payment_method'] ?? '');

// Rótulo do pagamento: payment_label() já vem de status_labels.php e
// traduz o payment_status com fallback honesto para valores inesperados.
$payStatusLabel = payment_label((string) ($order['payment_status'] ?? ''));

// Ação "Rastrear" só quando a transportadora devolveu uma URL real.
// Não inventamos link de rastreio: sem label_url o código fica só como
// texto no card de Entrega, que é o estado honesto do dado.
$shipmentEvents = order_repo_tracking_events($pdo, $orderId);
$trackUrl       = trim((string) (($shipmentEvents[0]['label_url'] ?? '')));

// O <title> do documento é genérico: o número do pedido já vai no <h1>.
$page_title = 'Detalhes do Pedido - Royal Tech';

account_layout_head($user, 'pedidos');
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
                <?php echo e($statusMeta['label']); ?>
                — <?php echo e(date('d/m/Y H:i', strtotime((string) $order['created_at']))); ?>
            </p>
        </div>
        <span class="account-status-pill account-status-pill--<?php echo e($statusMeta['tone']); ?>">
            <i class="fas <?php echo e($statusMeta['icon']); ?>" aria-hidden="true"></i>
            <?php echo e($statusMeta['label']); ?>
        </span>
    </div>
</div>

<?php if ($progress['canceled'] ?? false): ?>
    <div class="account-banner account-banner--danger" role="status">
        <i class="fas fa-ban" aria-hidden="true"></i>
        <div>
            <strong>Pedido cancelado</strong>
            <?php if ($progress['canceled_at'] ?? null): ?>
                <span> em <?php echo e(date('d/m/Y H:i', strtotime((string) $progress['canceled_at']))); ?>.</span>
            <?php endif; ?>
            <?php if (trim((string) ($progress['canceled_note'] ?? '')) !== ''): ?>
                <span class="account-banner-note"><?php echo e(trim((string) $progress['canceled_note'])); ?></span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<section class="account-card" id="secao-detalhe">

    <!-- ===================== Progresso (histórico) ===================== -->
    <?php if (!empty($progress['steps'])): ?>
        <div class="account-progress" aria-label="Andamento do pedido">
            <?php foreach ($progress['steps'] as $step): ?>
                <div class="account-progress-step<?php echo !empty($step['done']) ? ' is-done' : ''; ?>">
                    <span class="account-progress-dot" aria-hidden="true">
                        <i class="fas <?php echo e($step['icon']); ?>"></i>
                    </span>
                    <span class="account-progress-label"><?php echo e($step['label']); ?></span>
                    <span class="account-progress-date"><?php echo e($step['date_label'] ?? ''); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ===================== Itens (snapshot) ===================== -->
    <div class="ml-table-wrap">
        <table class="ml-table">
            <thead><tr><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item):
                // display_name vem de COALESCE(oi.product_name, p.name): o
                // snapshot do item tem prioridade sobre o nome atual do
                // produto, para o pedido não mudar de cara depois da compra.
                $img = renderProductImage((string) ($item['product_image'] ?? ''), base_url());
                $qty = (int) $item['quantity'];
                $unit = (float) $item['unit_price'];
            ?>
                <tr>
                    <td>
                        <div class="account-item">
                            <img src="<?php echo e($img); ?>" alt="<?php echo e((string) $item['display_name']); ?>" class="account-item-thumb">
                            <span class="account-item-name"><?php echo e((string) $item['display_name']); ?></span>
                        </div>
                    </td>
                    <td><?php echo $qty; ?></td>
                    <td>R$ <?php echo e(number_format($unit, 2, ',', '.')); ?></td>
                    <td>R$ <?php echo e(number_format($unit * $qty, 2, ',', '.')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;">Total:</td>
                    <td style="font-weight:700; color:var(--rt-accent);">R$ <?php echo e(number_format((float) $order['total'], 2, ',', '.')); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- ===================== Entrega e Pagamento (lado a lado) ===================== -->
    <div class="account-pair">
        <div class="account-card account-card--inner">
            <div class="account-card-head">
                <span class="account-card-icon"><i class="fas fa-truck" aria-hidden="true"></i></span>
                <div>
                    <h2 class="account-card-title">Entrega</h2>
                </div>
            </div>
            <dl class="account-kv">
                <dt>Método</dt>
                <dd><?php echo e(shipping_method_escaped((string) ($order['shipping_method'] ?? ''))); ?></dd>

                <dt>Endereço</dt>
                <dd><?php echo $addressLine !== '' ? e($addressLine) : '—'; ?></dd>

                <dt>CEP</dt>
                <dd><?php echo e((string) ($order['shipping_postal_code'] ?? '')); ?></dd>

                <dt>Frete</dt>
                <dd>
                    <?php if ((float) ($order['shipping_cost'] ?? 0) > 0): ?>
                        R$ <?php echo e(number_format((float) $order['shipping_cost'], 2, ',', '.')); ?>
                    <?php else: ?>
                        <span style="color:var(--rt-success);">Grátis</span>
                    <?php endif; ?>
                </dd>

                <?php if (trim((string) ($order['tracking_code'] ?? '')) !== ''): ?>
                    <dt>Rastreio</dt>
                    <dd><code><?php echo e((string) $order['tracking_code']); ?></code></dd>
                <?php endif; ?>
            </dl>
        </div>

        <div class="account-card account-card--inner">
            <div class="account-card-head">
                <span class="account-card-icon"><i class="fas fa-credit-card" aria-hidden="true"></i></span>
                <div>
                    <h2 class="account-card-title">Pagamento</h2>
                </div>
            </div>
            <dl class="account-kv">
                <dt>Método</dt>
                <dd><?php echo e(payment_method_label($payMethod)); ?></dd>

                <dt>Status</dt>
                <dd>
                    <span class="account-status-pill account-status-pill--<?php echo e((string) ($paymentRow['status'] ?? $order['payment_status'] ?? '') === 'paid' ? 'success' : 'warning'); ?>">
                        <?php echo e($payStatusLabel); ?>
                    </span>
                </dd>

                <?php if ($cardLastFour !== ''): ?>
                    <dt>Cartão</dt>
                    <dd>Final <?php echo e($cardLastFour); ?></dd>
                <?php endif; ?>

                <?php if (trim((string) ($order['payment_details'] ?? '')) !== ''): ?>
                    <dt>Detalhes</dt>
                    <dd><?php echo e((string) $order['payment_details']); ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <!-- ===================== Ações ===================== -->
    <div class="account-actions">
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
                <i class="fas fa-pix" aria-hidden="true"></i> Pagar agora
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
            <!-- Regra de cancelamento: uma frase, discreta, ao lado do botão. -->
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
                <i class="fas fa-truck-fast" aria-hidden="true"></i> Rastrear pedido
            </a>
        <?php endif; ?>

        <?php if ($orderActions['rebuy'] && !empty($items)): ?>
            <!-- Comprar novamente: reenvia cada item ao carrinho pelo
                 endpoint real (cart/add.php) e manda o cliente ao carrinho. -->
            <form method="post" id="reorderForm" style="display:inline"
                  action="<?php echo e(base_url('pages/cart/add.php')); ?>">
                <input type="hidden" name="_csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="redirect" value="<?php echo e(base_url('pages/cart/cart.php')); ?>">
                <?php foreach ($items as $rebuyItem): ?>
                    <input type="hidden" name="product_id[]" value="<?php echo (int) $rebuyItem['product_id']; ?>">
                    <input type="hidden" name="quantity[]" value="<?php echo (int) $rebuyItem['quantity']; ?>">
                <?php endforeach; ?>
                <button type="submit" class="account-btn" id="reorderBtn">
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
