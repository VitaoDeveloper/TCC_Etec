<?php
$page_title = 'Pagamento - Royal Tech';
$breadcrumb_title = 'Pagamento';
$current_page = 'carrinho';
$base_path = '../../';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once $base_path . 'vendor/autoload.php';
require_once $base_path . 'database/connection.php';
require_once $base_path . 'includes/cart_functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/order_payment_functions.php';

$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$order = orderGetById($pdo, $orderId);

if (!$order || (int) $order['user_id'] !== $userId) {
    header('Location: cart.php');
    exit;
}

$orderItems = orderGetItems($pdo, $orderId);
$paymentInfo = $order['payment_details'] ? json_decode($order['payment_details'], true) : null;
$paymentMethod = $order['payment_method'] ?? 'pix';
$paymentStatus = $order['payment_status'];
$orderStatus   = $order['status'];
$expiresAt     = $order['payment_expires_at'];
$expiresTs     = $expiresAt ? strtotime($expiresAt) : null;

// =========================================================================
// AÇÕES DO FORM (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'simulate_paid':
            if (in_array($paymentStatus, ['pending','processing'], true) && $orderStatus === 'pending') {
                orderMarkPaid($pdo, $orderId);
            }
            header('Location: payment.php?id=' . $orderId);
            exit;

        case 'simulate_failed':
            if (in_array($paymentStatus, ['pending','processing'], true) && $orderStatus === 'pending') {
                orderMarkFailed($pdo, $orderId);
            }
            header('Location: payment.php?id=' . $orderId);
            exit;

        case 'simulate_expired':
            orderAutoExpire($pdo, $orderId);
            header('Location: payment.php?id=' . $orderId);
            exit;

        case 'retry':
            if (in_array($paymentStatus, ['failed','expired'], true)) {
                $retry = orderRetryPayment($pdo, $orderId, $paymentMethod, (float) $order['total']);
                if (!$retry[0]) {
                    $pageError = $retry[1];
                }
            }
            header('Location: payment.php?id=' . $orderId . (isset($pageError) ? '&error=1' : ''));
            exit;

        case 'cancel':
            if (in_array($paymentStatus, ['pending','processing','failed','expired'], true) && $orderStatus === 'pending') {
                orderCancelCustomer($pdo, $orderId);
            }
            header('Location: ../auth/order-detail.php?id=' . $orderId);
            exit;
    }
}

// Atualiza variáveis após possíveis ações
$order = orderGetById($pdo, $orderId);
$paymentInfo = $order['payment_details'] ? json_decode($order['payment_details'], true) : null;
$paymentStatus = $order['payment_status'];
$orderStatus   = $order['status'];
$expiresAt     = $order['payment_expires_at'];
$expiresTs     = $expiresAt ? strtotime($expiresAt) : null;

// Auto-check de expiração para o cliente
if (in_array($paymentStatus, ['pending','processing'], true) && $orderStatus === 'pending' && $expiresTs && $expiresTs <= time()) {
    orderAutoExpire($pdo, $orderId);
    header('Location: payment.php?id=' . $orderId);
    exit;
}

$pageError = $_GET['error'] ?? null;

// Endpoint JSON simples para o polling do cliente — sem HTML
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'id' => (int) $order['id'],
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'payment_expires_at' => $order['payment_expires_at'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$fmtPrice = function (float $val): string { return 'R$ ' . number_format($val, 2, ',', '.'); };
$orderTotal = (float) $order['total'];
$payLabelMap = ['pix' => 'Pix', 'boleto' => 'Boleto', 'credit' => 'Cartão de Crédito', 'delivery' => 'Pagamento na Entrega'];
$methodLabel = $payLabelMap[$paymentMethod] ?? ucfirst($paymentMethod);
$isPrepay = in_array($paymentMethod, ['pix','boleto'], true);

include $base_path . 'components/header.php';
?>
<style>
/* =========================================================================
   PAYMENT STATUS PAGE
   ========================================================================= */
.pay-wrap{max-width:560px;margin:0 auto;padding:0 0 32px}
.pay-card{background:var(--ml-card);border:1px solid var(--ml-border);border-radius:var(--ml-radius);padding:32px 28px;text-align:center}
.pay-icon{font-size:48px;margin-bottom:12px}
.pay-title{font-size:1.35rem;font-weight:700;margin:0 0 6px}
.pay-sub{color:var(--ml-text-secondary);font-size:.92rem;margin:0 0 20px;line-height:1.5}
.pay-detail{background:var(--ml-bg);border:1px solid var(--ml-border);border-radius:var(--ml-radius);padding:16px;margin-bottom:16px;text-align:left}
.pay-detail dt{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--ml-text-muted);margin-bottom:4px}
.pay-detail dd{font-size:1rem;margin:0 0 12px;font-weight:600}
.pay-detail dd:last-child{margin-bottom:0}
.pay-code{display:block;background:var(--ml-bg);border:1px dashed var(--ml-border);border-radius:8px;padding:14px 12px;word-break:break-all;font-size:.88rem;font-family:monospace;line-height:1.55;user-select:all;color:var(--ml-text);margin-bottom:10px}
.pay-countdown{display:flex;gap:10px;justify-content:center;margin:20px 0 18px;flex-wrap:wrap}
.pay-countdown span{background:var(--ml-bg);border:1px solid var(--ml-border);border-radius:10px;padding:10px 14px;min-width:56px;text-align:center}
.pay-countdown span strong{display:block;font-size:1.5rem;font-weight:700;color:var(--ml-text);line-height:1.2}
.pay-countdown span small{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--ml-text-muted)}
.pay-spinner{margin:12px 0 6px}
.pay-spinner i{color:var(--ml-accent);animation:pulse 1.4s infinite ease-in-out;font-size:26px}
@keyframes pulse{0%,80%,100%{opacity:.35}40%{opacity:1}}
.pay-success .pay-icon{color:var(--ml-green)!important;opacity:1}
.pay-fail .pay-icon{color:#e53935!important;opacity:1}
.pay-expired .pay-icon{color:var(--ml-text-muted)!important;opacity:1}
.pay-btns{display:flex;gap:10px;justify-content:center;margin-top:20px;flex-wrap:wrap}
.pay-btns .ml-btn{min-width:150px;text-align:center}
.pay-btns .ml-btn-primary{background:var(--ml-accent)!important;color:#fff!important;border-color:var(--ml-accent)!important}
.pay-notice{font-size:.82rem;color:var(--ml-text-muted);margin-top:14px;line-height:1.5}
.pay-summary{margin-top:20px;text-align:left;border-top:1px solid var(--ml-border);padding-top:16px}
.pay-summary-row{display:flex;justify-content:space-between;font-size:.9rem;padding:4px 0;color:var(--ml-text-secondary)}
.pay-summary-row.total{border-top:1px solid var(--ml-border);padding-top:8px;margin-top:6px;font-weight:700;font-size:1.05rem;color:var(--ml-text)}

/* Simulador de demonstração */
.pay-sim{margin-top:24px;border-top:1px dashed var(--ml-border);padding-top:16px}
.pay-sim-title{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--ml-text-muted);margin-bottom:8px}
.pay-sim-btns{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.pay-sim-btns button{font-size:.8rem;padding:7px 14px;border-radius:8px;border:1px solid var(--ml-border);background:var(--ml-bg);color:var(--ml-text-secondary);cursor:pointer;transition:background .15s,border-color .15s}
.pay-sim-btns button:hover{border-color:var(--ml-accent);color:var(--ml-accent)}
.pay-sim-btns button[data-sim=paid]{border-color:var(--ml-green);color:var(--ml-green)}
.pay-sim-btns button[data-sim=paid]:hover{background:var(--ml-green);color:#fff}
.pay-sim-btns button[data-sim=failed]{border-color:#e53935;color:#e53935}
.pay-sim-btns button[data-sim=failed]:hover{background:#e53935;color:#fff}
.pay-sim-btns button[data-sim=expired]{border-color:var(--ml-text-muted);color:var(--ml-text-muted)}
.pay-sim-btns button[data-sim=expired]:hover{background:var(--ml-text-muted);color:#fff}
</style>

<section class="ml-section" style="padding-top:8px;">
<div class="container pay-wrap">

<?php if ($orderStatus === 'canceled'): ?>
    <!-- PEDIDO CANCELADO -->
    <div class="pay-card pay-expired">
        <div class="pay-icon"><i class="fas fa-ban"></i></div>
        <h3 class="pay-title">Pedido Cancelado</h3>
        <p class="pay-sub">Seu pedido #<?= str_pad((string)$orderId,4,'0',STR_PAD_LEFT) ?> foi cancelado.</p>
        <div class="pay-btns">
            <a href="../auth/orders.php" class="ml-btn"><i class="fas fa-list"></i> Meus Pedidos</a>
            <a href="../products/products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Continuar Comprando</a>
        </div>
    </div>

<?php elseif ($paymentStatus === 'paid'): ?>
    <!-- PAGAMENTO APROVADO -->
    <div class="pay-card pay-success">
        <div class="pay-icon"><i class="fas fa-check-circle"></i></div>
        <h3 class="pay-title">Pagamento Confirmado!</h3>
        <p class="pay-sub">Seu pedido #<?= str_pad((string)$orderId,4,'0',STR_PAD_LEFT) ?> foi pago com sucesso.</p>
        <div class="pay-summary">
            <div class="pay-summary-row"><span>Pedido</span><span>#<?= str_pad((string)$orderId,4,'0',STR_PAD_LEFT) ?></span></div>
            <div class="pay-summary-row"><span>Meio de pagamento</span><span><?= htmlspecialchars($methodLabel) ?></span></div>
            <?php if ($paymentMethod === 'credit' && $paymentInfo['installments'] ?? null): ?>
            <div class="pay-summary-row"><span>Parcelamento</span><span><?= htmlspecialchars($paymentInfo['installments']) ?></span></div>
            <?php endif; ?>
            <div class="pay-summary-row total"><span>Total</span><span><?= $fmtPrice($orderTotal) ?></span></div>
        </div>
        <div class="pay-btns" style="margin-top:20px">
            <a href="../auth/orders.php" class="ml-btn"><i class="fas fa-list"></i> Meus Pedidos</a>
            <a href="../products/products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Continuar Comprando</a>
        </div>
    </div>

<?php elseif ($paymentMethod === 'delivery'): ?>
    <!-- PAGAMENTO NA ENTREGA -->
    <div class="pay-card">
        <div class="pay-icon" style="color:var(--ml-green);opacity:1"><i class="fas fa-money-bill-wave"></i></div>
        <h3 class="pay-title">Pedido Registrado!</h3>
        <p class="pay-sub">Vai pagar no momento da entrega. Aceitamos dinheiro, cartão de crédito e débito.</p>
        <div class="pay-summary">
            <div class="pay-summary-row"><span>Pedido</span><span>#<?= str_pad((string)$orderId,4,'0',STR_PAD_LEFT) ?></span></div>
            <div class="pay-summary-row"><span>Status</span><span style="color:var(--ml-accent)">Aguardando entrega</span></div>
            <div class="pay-summary-row total"><span>Total</span><span><?= $fmtPrice($orderTotal) ?></span></div>
        </div>
        <div class="pay-btns" style="margin-top:20px">
            <a href="../auth/orders.php" class="ml-btn"><i class="fas fa-list"></i> Meus Pedidos</a>
            <a href="../products/products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Continuar Comprando</a>
        </div>
    </div>

<?php elseif ($paymentStatus === 'failed'): ?>
    <!-- PAGAMENTO FALHOU -->
    <div class="pay-card pay-fail">
        <div class="pay-icon"><i class="fas fa-times-circle"></i></div>
        <h3 class="pay-title">Pagamento com Problemas</h3>
        <p class="pay-sub">Não foi possível processar o pagamento via <strong><?= htmlspecialchars($methodLabel) ?></strong>.<br>Você pode tentar novamente ou escolher outro método de pagamento.</p>
        <?php if ($pageError): ?>
        <div class="auth-feedback auth-feedback-error" style="margin:10px auto;max-width:380px;text-align:left;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($pageError) ?></div>
        <?php endif; ?>
        <div class="pay-btns">
            <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?><button class="ml-btn ml-btn-primary" name="action" value="retry"><i class="fas fa-redo"></i> Tentar Novamente</button></form>
            <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?><button class="ml-btn" name="action" value="cancel"><i class="fas fa-times"></i> Cancelar Pedido</button></form>
        </div>
    </div>

<?php elseif ($paymentStatus === 'expired'): ?>
    <!-- PAGAMENTO EXPIRADO -->
    <div class="pay-card pay-expired">
        <div class="pay-icon"><i class="fas fa-clock"></i></div>
        <h3 class="pay-title">Pagamento Expirado</h3>
        <p class="pay-sub">O prazo para pagamento do pedido #<?= str_pad((string)$orderId,4,'0',STR_PAD_LEFT) ?> encerrou.<br>Os itens foram liberados no estoque. Clique abaixo para gerar uma nova cobrança.</p>
        <div class="pay-btns">
            <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?><button class="ml-btn ml-btn-primary" name="action" value="retry"><i class="fas fa-redo"></i> Gerar Nova Cobrança</button></form>
            <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?><button class="ml-btn" name="action" value="cancel"><i class="fas fa-times"></i> Cancelar Pedido</button></form>
        </div>
    </div>

<?php elseif ($paymentStatus === 'processing'): ?>
    <!-- PROCESSANDO -->
    <div class="pay-card">
        <div class="pay-icon" style="color:var(--ml-accent);opacity:1"><i class="fas fa-spinner"></i></div>
        <h3 class="pay-title">Processando Pagamento</h3>
        <div class="pay-spinner"><i class="fas fa-circle-notch fa-spin"></i></div>
        <p class="pay-sub">Estamos aguardando a confirmação do pagamento via <strong><?= htmlspecialchars($methodLabel) ?></strong>…</p>
        <?php if ($expiresTs): ?>
        <div class="pay-countdown" data-expires="<?= (int) $expiresTs ?>" id="countdownBox">
            <span><strong id="cdMin">--</strong><small>min</small></span>
            <span><strong id="cdSec">--</strong><small>seg</small></span>
        </div>
        <?php endif; ?>
        <p class="pay-notice">Geralmente leva apenas alguns segundos. Esta página é atualizada automaticamente.</p>
        <script>
        (function(){
            function poll(){fetch('payment.php?id=<?=$orderId?>&poll=1',{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(d=>{if(d&&d.payment_status&&d.payment_status!=='processing'&&d.payment_status!=='pending'){location.reload();return;}setTimeout(poll,6000)}).catch(()=>setTimeout(poll,10000))}
            setTimeout(poll,5000);
            var box=document.getElementById('countdownBox');
            if(!box)return;
            var exp=parseInt(box.dataset.expires,10)*1000;
            function tick(){var diff=Math.max(0,exp-Date.now());var m=Math.floor(diff/60000);var s=Math.floor((diff%60000)/1000);var cm=document.getElementById('cdMin');var cs=document.getElementById('cdSec');if(cm)cm.textContent=String(m).padStart(2,'0');if(cs)cs.textContent=String(s).padStart(2,'0');if(diff<=0){setTimeout(function(){location.reload()},400);return}requestAnimationFrame(function(){setTimeout(tick,250)})}tick();
        })();
        </script>

<?php else: ?>
    <!-- AGUARDANDO PAGAMENTO (pending) — PIX ou Boleto -->
    <div class="pay-card">
        <div class="pay-icon" style="color:var(--ml-accent);opacity:1"><i class="fas fa-<?= $paymentMethod==='pix'?'qrcode':'barcode' ?>"></i></div>
        <h3 class="pay-title">Aguardando Pagamento</h3>
        <p class="pay-sub"><?= htmlspecialchars($paymentInfo['instructions'] ?? 'Aguarde a confirmação do pagamento.') ?></p>

        <?php if ($paymentMethod === 'pix' && ($paymentInfo['pix_code'] ?? null)): ?>
        <div class="pay-detail">
            <dt>Código Pix (copie e cole no app do banco)</dt>
            <dd><span class="pay-code" id="pixCodeText"><?= htmlspecialchars($paymentInfo['pix_code']) ?></span></dd>
            <button class="ml-btn" style="width:100%" onclick="navigator.clipboard.writeText(document.getElementById('pixCodeText').textContent).then(()=>{this.innerHTML='<i class=\"fas fa-check\"></i> Copiado!';setTimeout(()=>this.innerHTML='<i class=\"fas fa-copy\"></i> Copiar Código Pix',2000)})"><i class="fas fa-copy"></i> Copiar Código Pix</button>
        </div>
        <?php endif; ?>

        <?php if ($paymentMethod === 'boleto' && ($paymentInfo['boleto_number'] ?? null)): ?>
        <div class="pay-detail">
            <dt>Número do Boleto</dt>
            <dd><span class="pay-code"><?= htmlspecialchars($paymentInfo['boleto_number']) ?></span></dd>
        </div>
        <?php endif; ?>

        <?php if ($expiresTs): ?>
        <div class="pay-countdown" data-expires="<?= (int) $expiresTs ?>" id="countdownBox">
            <span><strong id="cdMin">--</strong><small>min</small></span>
            <span><strong id="cdSec">--</strong><small>seg</small></span>
        </div>
        <p class="pay-notice">O pagamento será detectado automaticamente. Você também pode atualizar esta página manualmente.</p>
        <?php endif; ?>

        <div class="pay-summary">
            <div class="pay-summary-row"><span>Pedido</span><span>#<?= str_pad((string)$orderId,4,'0',STR_PAD_LEFT) ?></span></div>
            <div class="pay-summary-row total"><span>Total a pagar</span><span><?= $fmtPrice($orderTotal) ?></span></div>
        </div>

        <div class="pay-btns" style="margin-top:16px">
            <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?><button class="ml-btn" name="action" value="cancel"><i class="fas fa-times"></i> Cancelar Pedido</button></form>
        </div>

        <!-- Simulador de demonstração -->
        <div class="pay-sim">
            <div class="pay-sim-title"><i class="fas fa-flask"></i> Simulador de pagamento (demonstração)</div>
            <div class="pay-sim-btns">
                <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?><button type="submit" data-sim="paid" name="action" value="simulate_paid"><i class="fas fa-check"></i> Aprovar Pagamento</button></form>
                <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?><button type="submit" data-sim="failed" name="action" value="simulate_failed"><i class="fas fa-times"></i> Simular Erro</button></form>
                <form method="POST" style="display:inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?><button type="submit" data-sim="expired" name="action" value="simulate_expired"><i class="fas fa-clock"></i> Simular Expiração</button></form>
            </div>
        </div>

        <script>
        (function(){
            function poll(){
                fetch('payment.php?id=<?=$orderId?>&poll=1',{headers:{'X-Requested-With':'XMLHttpRequest'}})
                .then(r=>r.json())
                .then(d=>{
                    if(d&&d.payment_status==='paid'){location.reload();return;}
                    if(d&&d.payment_status!=='pending'&&d.payment_status!=='processing'){location.reload();return;}
                    setTimeout(poll,5000);
                })
                .catch(()=>setTimeout(poll,10000));
            }
            setTimeout(poll,5000);

            var box=document.getElementById('countdownBox');
            if(!box)return;
            var exp=parseInt(box.dataset.expires,10)*1000;
            function tick(){
                var diff=Math.max(0,exp-Date.now());
                var m=Math.floor(diff/60000);
                var s=Math.floor((diff%60000)/1000);
                var cm=document.getElementById('cdMin');
                var cs=document.getElementById('cdSec');
                if(cm)cm.textContent=String(m).padStart(2,'0');
                if(cs)cs.textContent=String(s).padStart(2,'0');
                if(diff<=0){
                    setTimeout(function(){location.reload()},400);
                    return;
                }
                requestAnimationFrame(function(){setTimeout(tick,250)});
            }
            tick();
        })();
        </script>
    </div>
<?php endif; ?>

</div>
</section>

<?php
// Endpoint JSON simples para o polling do cliente
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'id' => (int) $order['id'],
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'payment_expires_at' => $order['payment_expires_at'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

include $base_path . 'components/footer.php';