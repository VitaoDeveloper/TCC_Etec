<?php
$page_title = 'Aguardando Pagamento - Royal Tech';
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

// Endpoint JSON para o polling do cliente — sem HTML
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
$orderNum = str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);

$payLabelMap = ['pix' => 'Pix', 'boleto' => 'Boleto', 'credit' => 'Cartão de Crédito', 'delivery' => 'Pagamento na Entrega'];
$methodLabel = $payLabelMap[$paymentMethod] ?? ucfirst($paymentMethod);
$isPrepay = in_array($paymentMethod, ['pix','boleto'], true);

$subtitleDefault = ($paymentMethod === 'pix')
    ? 'Escaneie o QR Code abaixo ou copie o código Pix para pagamento.'
    : (($paymentMethod === 'boleto')
        ? 'Pague o boleto até o vencimento para confirmar seu pedido.'
        : 'Aguarde a confirmação do pagamento de seu pedido.');

// QR decorativo determinístico (ambiente de demonstração — a aprovação é simulada)
$qrSeed = ($paymentInfo['pix_code'] ?? '') ?: (string) $orderId;
mt_srand(crc32($qrSeed));
$qrSize = 21;
$qr = [];
for ($r = 0; $r < $qrSize; $r++) {
    $qr[$r] = [];
    for ($c = 0; $c < $qrSize; $c++) {
        $qr[$r][$c] = mt_rand(0, 100) < 46 ? 1 : 0;
    }
}
$finder = [
    [1,1,1,1,1,1,1],
    [1,0,0,0,0,0,1],
    [1,0,1,1,1,0,1],
    [1,0,1,1,1,0,1],
    [1,0,1,1,1,0,1],
    [1,0,0,0,0,0,1],
    [1,1,1,1,1,1,1],
];
$place = function (int $top, int $left) use (&$qr, $finder, $qrSize) {
    foreach ($finder as $r => $row) {
        foreach ($row as $c => $v) {
            if (($top + $r) < $qrSize && ($left + $c) < $qrSize) {
                $qr[$top + $r][$left + $c] = $v;
            }
        }
    }
};
$place(0, 0);
$place(0, $qrSize - 7);
$place($qrSize - 7, 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================================
           TELA DE PAGAMENTO — ROYAL TECH (tema escuro premium)
           ============================================================ */
        :root {
            --rt-bg: #0f0f0f;
            --rt-bg-soft: #141414;
            --rt-card: #181818;
            --rt-card-2: #1e1e1e;
            --rt-border: rgba(255, 255, 255, 0.08);
            --rt-border-strong: rgba(255, 255, 255, 0.16);
            --rt-text: #f5f5f5;
            --rt-text-2: #b6b6b6;
            --rt-text-3: #8a8a8a;
            --rt-gold: #f5c542;
            --rt-gold-deep: #e0a91f;
            --rt-green: #37d67a;
            --rt-red: #f04a46;
            --rt-radius: 22px;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: var(--rt-bg);
            color: var(--rt-text);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(1200px 600px at 50% -10%, rgba(245, 197, 66, 0.07), transparent 60%),
                radial-gradient(900px 500px at 85% 110%, rgba(55, 214, 122, 0.05), transparent 60%),
                var(--rt-bg);
        }

        a { color: inherit; text-decoration: none; }

        /* ---- Top bar --------------------------------------------------- */
        .rt-topbar {
            max-width: 700px;
            margin: 0 auto;
            padding: 26px 20px 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .rt-logo { display: flex; align-items: center; gap: 12px; }
        .rt-logo-mark {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #ffd75e 0%, #f5b81c 55%, #d99b0e 100%);
            display: grid;
            place-items: center;
            box-shadow: 0 6px 20px rgba(245, 197, 66, 0.28), inset 0 1px 0 rgba(255,255,255,0.45);
        }
        .rt-logo-mark svg { width: 24px; height: 24px; display: block; }
        .rt-logo-text { font-weight: 800; font-size: 1.15rem; letter-spacing: -0.02em; line-height: 1; }
        .rt-logo-text small { display: block; font-weight: 500; font-size: 0.68rem; color: var(--rt-text-3); letter-spacing: 0.16em; text-transform: uppercase; margin-top: 3px; }
        .rt-topbar-link {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--rt-text-2);
            padding: 9px 14px;
            border-radius: 10px;
            border: 1px solid var(--rt-border);
            background: rgba(255,255,255,0.03);
            transition: color .18s, border-color .18s, background .18s, transform .18s;
        }
        .rt-topbar-link:hover { color: var(--rt-gold); border-color: rgba(245,197,66,0.4); background: rgba(245,197,66,0.05); transform: translateY(-1px); }

        /* ---- Shell / card --------------------------------------------------- */
        .rt-wrap {
            max-width: 560px;
            margin: 0 auto;
            padding: 18px 20px 64px;
        }
        .rt-card {
            background: linear-gradient(180deg, var(--rt-card), var(--rt-bg-soft));
            border: 1px solid var(--rt-border);
            border-radius: var(--rt-radius);
            padding: 38px 30px 30px;
            text-align: center;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.55), 0 4px 16px rgba(0, 0, 0, 0.4);
            animation: rt-in .5s cubic-bezier(.2, .7, .3, 1) both;
            position: relative;
            overflow: hidden;
        }
        .rt-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(245,197,66,0.45), transparent);
            opacity: .8;
        }
        @keyframes rt-in {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .rt-icon {
            width: 74px;
            height: 74px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 30px;
            color: var(--rt-gold);
            background: rgba(245,197,66,0.09);
            border: 1px solid rgba(245,197,66,0.22);
            box-shadow: 0 10px 30px rgba(245,197,66,0.10);
        }
        .rt-icon.gold   { color: var(--rt-gold); background: rgba(245,197,66,0.09); border-color: rgba(245,197,66,0.22); }
        .rt-icon.green  { color: var(--rt-green); background: rgba(55,214,122,0.10); border-color: rgba(55,214,122,0.24); }
        .rt-icon.red    { color: var(--rt-red); background: rgba(240,74,70,0.10); border-color: rgba(240,74,70,0.24); }
        .rt-icon.gray   { color: var(--rt-text-3); background: rgba(255,255,255,0.05); border-color: var(--rt-border); }

        .rt-title {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 8px;
        }
        .rt-sub {
            color: var(--rt-text-2);
            font-size: 0.94rem;
            line-height: 1.6;
            margin: 0 0 26px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .rt-sub strong { color: var(--rt-text); font-weight: 600; }

        /* ---- QR / código pix ------------------------------------------------ */
        .rt-pix-box {
            background: var(--rt-card-2);
            border: 1.5px dashed rgba(255,255,255,0.16);
            border-radius: 18px;
            padding: 22px 18px 18px;
            margin-bottom: 20px;
        }
        .rt-qr {
            width: 178px;
            height: 178px;
            margin: 0 auto 16px;
            padding: 14px;
            background: #fafaf6;
            border-radius: 14px;
            box-shadow: 0 10px 26px rgba(0,0,0,0.45);
        }
        .rt-qr-grid { display: grid; grid-template-columns: repeat(<?= $qrSize ?>, 6px); gap: 1px; justify-content: center; width: max-content; margin: 0 auto; }
        .rt-qr-cell { width: 6px; height: 6px; border-radius: 1px; }
        .rt-qr-cell.on { background: #151515; }
        .rt-qr-cell.off { background: transparent; }

        .rt-pix-label {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--rt-text-3);
            margin: 0 0 8px;
        }
        .rt-pix-code {
            display: block;
            width: 100%;
            margin: 0 0 14px;
            padding: 13px 14px;
            background: rgba(0,0,0,0.35);
            border: 1px solid var(--rt-border);
            border-radius: 12px;
            font-family: 'SFMono-Regular', 'Menlo', 'Consolas', 'Liberation Mono', monospace;
            font-size: 0.76rem;
            line-height: 1.6;
            color: var(--rt-text-2);
            text-align: left;
            word-break: break-all;
            user-select: all;
            overflow: hidden;
            max-height: 86px;
        }

        .rt-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            font-family: inherit;
            font-size: 0.94rem;
            font-weight: 700;
            border-radius: 13px;
            border: 1px solid transparent;
            padding: 14px 22px;
            cursor: pointer;
            transition: transform .16s cubic-bezier(.2,.8,.3,1), box-shadow .18s, background .18s, border-color .18s, color .18s;
            -webkit-tap-highlight-color: transparent;
        }
        .rt-btn:active { transform: scale(0.97); }
        .rt-btn:focus-visible { outline: 3px solid rgba(245,197,66,0.35); outline-offset: 2px; }

        .rt-btn-copy {
            width: 100%;
            background: linear-gradient(135deg, #ffd75e, #f2b51b);
            color: #231a00;
            box-shadow: 0 10px 26px rgba(245,197,66,0.22);
        }
        .rt-btn-copy:hover { transform: translateY(-1px); box-shadow: 0 14px 34px rgba(245,197,66,0.32); }
        .rt-btn-copy.copied {
            background: linear-gradient(135deg, #4be08a, #23b55f);
            color: #06250f;
            box-shadow: 0 10px 26px rgba(55,214,122,0.25);
        }

        .rt-btn-ghost {
            background: transparent;
            color: var(--rt-text-2);
            border-color: var(--rt-border-strong);
        }
        .rt-btn-ghost:hover { color: var(--rt-text); border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.04); }

        /* ---- Timer ---------------------------------------------------------- */
        .rt-countdown {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin: 4px 0 14px;
        }
        .rt-timer-card {
            min-width: 92px;
            padding: 16px 12px 14px;
            background: var(--rt-card-2);
            border: 1px solid var(--rt-border);
            border-radius: 16px;
        }
        .rt-timer-value {
            font-size: 2.3rem;
            font-weight: 800;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
            color: var(--rt-gold);
            text-shadow: 0 0 24px rgba(245,197,66,0.25);
        }
        .rt-timer-label {
            display: block;
            margin-top: 6px;
            font-size: 0.66rem;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--rt-text-3);
        }
        .rt-timer-warn .rt-timer-value { color: var(--rt-red); text-shadow: 0 0 24px rgba(240,74,70,0.25); }

        .rt-note {
            font-size: 0.82rem;
            color: var(--rt-text-3);
            line-height: 1.6;
            margin: 0;
        }

        /* ---- Resumo ----------------------------------------------------------- */
        .rt-summary {
            margin-top: 22px;
            background: var(--rt-card);
            border: 1px solid var(--rt-border);
            border-radius: 16px;
            padding: 4px 20px;
        }
        .rt-sum-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 0;
            font-size: 0.92rem;
            color: var(--rt-text-2);
            gap: 14px;
        }
        .rt-sum-row + .rt-sum-row { border-top: 1px solid var(--rt-border); }
        .rt-sum-row.total { font-weight: 700; color: var(--rt-text); font-size: 1.05rem; }
        .rt-sum-row .rt-val { font-weight: 600; white-space: nowrap; }
        .rt-sum-row .rt-val.money { color: var(--rt-gold); font-weight: 800; font-size: 1.15rem; }

        .rt-actions { display: flex; flex-direction: column; gap: 12px; margin-top: 22px; }

        /* ---- Simulador (demo) -------------------------------------------------- */
        .rt-demo {
            margin-top: 30px;
            border-top: 1px dashed var(--rt-border-strong);
            padding-top: 22px;
        }
        .rt-demo h4 {
            margin: 0 0 6px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--rt-text-3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .rt-demo h4 i { color: var(--rt-gold); }
        .rt-demo p { margin: 0 0 16px; font-size: 0.78rem; color: var(--rt-text-3); }
        .rt-demo-btns { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .rt-demo-btns .rt-btn { flex: 1; min-width: 128px; padding: 12px 14px; font-size: 0.85rem; }
        .rt-demo-green { background: rgba(55,214,122,0.12); color: var(--rt-green); border-color: rgba(55,214,122,0.4); }
        .rt-demo-green:hover { background: var(--rt-green); color: #06250f; border-color: var(--rt-green); box-shadow: 0 10px 26px rgba(55,214,122,0.3); }
        .rt-demo-red { background: rgba(240,74,70,0.10); color: var(--rt-red); border-color: rgba(240,74,70,0.4); }
        .rt-demo-red:hover { background: var(--rt-red); color: #fff; border-color: var(--rt-red); box-shadow: 0 10px 26px rgba(240,74,70,0.3); }
        .rt-demo-gray { background: rgba(255,255,255,0.04); color: var(--rt-text-3); border-color: var(--rt-border-strong); }
        .rt-demo-gray:hover { background: rgba(255,255,255,0.12); color: var(--rt-text); border-color: rgba(255,255,255,0.3); }

        .rt-foot {
            margin-top: 30px;
            text-align: center;
            font-size: 0.74rem;
            color: var(--rt-text-3);
        }
        .rt-foot i { color: var(--rt-green); margin-right: 5px; }

        /* ---- Estados ------------------------------------------------ */
        .rt-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 7px 14px;
            border-radius: 100px;
        }
        .rt-status-badge.paid { color: var(--rt-green); background: rgba(55,214,122,0.12); border: 1px solid rgba(55,214,122,0.3); }

        .rt-spinner {
            width: 42px;
            height: 42px;
            margin: 0 auto 14px;
            border: 3px solid rgba(245,197,66,0.2);
            border-top-color: var(--rt-gold);
            border-radius: 50%;
            animation: rt-spin 0.9s linear infinite;
        }
        @keyframes rt-spin { to { transform: rotate(360deg); } }

        .rt-alert-error {
            margin: 14px auto 0;
            max-width: 380px;
            display: flex;
            gap: 9px;
            align-items: flex-start;
            text-align: left;
            font-size: 0.84rem;
            line-height: 1.5;
            color: #ffb4b1;
            background: rgba(240,74,70,0.10);
            border: 1px solid rgba(240,74,70,0.3);
            border-radius: 12px;
            padding: 12px 14px;
        }

        form.inline { display: inline-flex; }
        .rt-card form { margin: 0; }

        /* ---- Responsivo ------------------------------------------------ */
        @media (max-width: 480px) {
            .rt-card { padding: 30px 18px 24px; border-radius: 18px; }
            .rt-title { font-size: 1.5rem; }
            .rt-countdown { gap: 10px; }
            .rt-timer-card { min-width: 78px; }
            .rt-timer-value { font-size: 2rem; }
            .rt-topbar { padding-top: 18px; }
            .rt-demo-btns .rt-btn { min-width: 100%; }
        }
    </style>
</head>
<body>

<div class="rt-topbar">
    <div class="rt-logo">
        <span class="rt-logo-mark">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 2.6 20 7.3v9.4l-8 4.7-8-4.7V7.3L12 2.6Z" fill="#3a2b00"/>
                <path d="M12 6.1 17.2 8.9v5.8L12 17.5 6.8 14.7V8.9L12 6.1Z" fill="#ffdf7e"/>
                <path d="M12 9.2 14.6 10.6v2.4L12 14.4 9.4 13V10.6L12 9.2Z" fill="#2a1f00"/>
            </svg>
        </span>
        <span class="rt-logo-text">Royal Tech<small>e-commerce premium</small></span>
    </div>
    <a class="rt-topbar-link" href="../auth/orders.php"><i class="fas fa-list-ul" style="margin-right:6px"></i>Meus Pedidos</a>
</div>

<div class="rt-wrap">
    <div class="rt-card">

<?php if ($orderStatus === 'canceled'): ?>
    <!-- PEDIDO CANCELADO -->
    <div class="rt-icon gray"><i class="fas fa-ban"></i></div>
    <h1 class="rt-title">Pedido Cancelado</h1>
    <p class="rt-sub">Seu pedido <strong>#<?= $orderNum ?></strong> foi cancelado e os itens foram liberados.</p>
    <div class="rt-actions">
        <a class="rt-btn rt-btn-ghost" href="../auth/orders.php"><i class="fas fa-list"></i> Meus Pedidos</a>
        <a class="rt-btn rt-btn-copy" href="../products/products.php"><i class="fas fa-store"></i> Continuar Comprando</a>
    </div>

<?php elseif ($paymentStatus === 'paid'): ?>
    <!-- PAGAMENTO APROVADO -->
    <div class="rt-icon green"><i class="fas fa-check-circle"></i></div>
    <span class="rt-status-badge paid"><i class="fas fa-check"></i> Pago</span>
    <h1 class="rt-title">Pagamento Confirmado!</h1>
    <p class="rt-sub">Seu pedido <strong>#<?= $orderNum ?></strong> foi pago com sucesso via <strong><?= htmlspecialchars($methodLabel) ?></strong>.</p>
    <div class="rt-summary">
        <div class="rt-sum-row"><span>Número do pedido</span><span class="rt-val">#<?= $orderNum ?></span></div>
        <div class="rt-sum-row"><span>Meio de pagamento</span><span class="rt-val"><?= htmlspecialchars($methodLabel) ?></span></div>
        <?php if ($paymentMethod === 'credit' && ($paymentInfo['installments'] ?? null)): ?>
        <div class="rt-sum-row"><span>Parcelamento</span><span class="rt-val"><?= htmlspecialchars($paymentInfo['installments']) ?></span></div>
        <?php endif; ?>
        <div class="rt-sum-row total"><span>Total pago</span><span class="rt-val money"><?= $fmtPrice($orderTotal) ?></span></div>
    </div>
    <div class="rt-actions">
        <a class="rt-btn rt-btn-ghost" href="../auth/orders.php"><i class="fas fa-list"></i> Meus Pedidos</a>
        <a class="rt-btn rt-btn-copy" href="../products/products.php"><i class="fas fa-store"></i> Continuar Comprando</a>
    </div>

<?php elseif ($paymentMethod === 'delivery'): ?>
    <!-- PAGAMENTO NA ENTREGA -->
    <div class="rt-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <h1 class="rt-title">Pedido Registrado!</h1>
    <p class="rt-sub">Vai pagar no momento da entrega. Aceitamos dinheiro, cartão de crédito e débito.</p>
    <div class="rt-summary">
        <div class="rt-sum-row"><span>Número do pedido</span><span class="rt-val">#<?= $orderNum ?></span></div>
        <div class="rt-sum-row"><span>Status</span><span class="rt-val" style="color:var(--rt-green)">Aguardando entrega</span></div>
        <div class="rt-sum-row total"><span>Total a pagar</span><span class="rt-val money"><?= $fmtPrice($orderTotal) ?></span></div>
    </div>
    <div class="rt-actions">
        <a class="rt-btn rt-btn-ghost" href="../auth/orders.php"><i class="fas fa-list"></i> Meus Pedidos</a>
        <a class="rt-btn rt-btn-copy" href="../products/products.php"><i class="fas fa-store"></i> Continuar Comprando</a>
    </div>

<?php elseif ($paymentStatus === 'failed'): ?>
    <!-- PAGAMENTO FALHOU -->
    <div class="rt-icon red"><i class="fas fa-times-circle"></i></div>
    <h1 class="rt-title">Pagamento com Problemas</h1>
    <p class="rt-sub">Não foi possível processar o pagamento via <strong><?= htmlspecialchars($methodLabel) ?></strong>. Você pode tentar novamente ou escolher outro método.</p>
    <?php if ($pageError): ?>
    <div class="rt-alert-error"><i class="fas fa-exclamation-triangle"></i><span><?= htmlspecialchars($pageError) ?></span></div>
    <?php endif; ?>
    <div class="rt-actions">
        <form method="POST" class="rt-form">
            <input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?>
            <button class="rt-btn rt-btn-copy" name="action" value="retry" style="width:100%"><i class="fas fa-redo"></i> Tentar Novamente</button>
        </form>
        <form method="POST" class="rt-form">
            <input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?>
            <button class="rt-btn rt-btn-ghost" name="action" value="cancel" style="width:100%"><i class="fas fa-times"></i> Cancelar Pedido</button>
        </form>
    </div>

<?php elseif ($paymentStatus === 'expired'): ?>
    <!-- PAGAMENTO EXPIRADO -->
    <div class="rt-icon gray"><i class="fas fa-clock"></i></div>
    <h1 class="rt-title">Pagamento Expirado</h1>
    <p class="rt-sub">O prazo para pagamento do pedido <strong>#<?= $orderNum ?></strong> encerrou. Os itens foram liberados no estoque. Clique abaixo para gerar uma nova cobrança.</p>
    <div class="rt-actions">
        <form method="POST" class="rt-form">
            <input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?>
            <button class="rt-btn rt-btn-copy" name="action" value="retry" style="width:100%"><i class="fas fa-redo"></i> Gerar Nova Cobrança</button>
        </form>
        <form method="POST" class="rt-form">
            <input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?>
            <button class="rt-btn rt-btn-ghost" name="action" value="cancel" style="width:100%"><i class="fas fa-times"></i> Cancelar Pedido</button>
        </form>
    </div>

<?php elseif ($paymentStatus === 'processing'): ?>
    <!-- PROCESSANDO -->
    <div class="rt-spinner"></div>
    <h1 class="rt-title">Processando Pagamento</h1>
    <p class="rt-sub">Estamos aguardando a confirmação do pagamento via <strong><?= htmlspecialchars($methodLabel) ?></strong>…</p>
    <?php if ($expiresTs): ?>
    <div class="rt-countdown" id="countdownBox" data-expires="<?= (int) $expiresTs ?>">
        <div class="rt-timer-card"><span class="rt-timer-value" id="cdMin">--</span><span class="rt-timer-label">min</span></div>
        <div class="rt-timer-card"><span class="rt-timer-value" id="cdSec">--</span><span class="rt-timer-label">seg</span></div>
    </div>
    <?php endif; ?>
    <p class="rt-note">Geralmente leva apenas alguns segundos. Esta página é atualizada automaticamente.</p>

<?php else: ?>
    <!-- AGUARDANDO PAGAMENTO (pending) — PIX ou Boleto -->
    <div class="rt-icon gold"><i class="fas fa-<?= $paymentMethod === 'pix' ? 'qrcode' : 'barcode' ?>"></i></div>
    <h1 class="rt-title">Aguardando Pagamento</h1>
    <p class="rt-sub"><?= htmlspecialchars($paymentInfo['instructions'] ?? $subtitleDefault) ?></p>

    <?php if ($paymentMethod === 'pix' && ($paymentInfo['pix_code'] ?? null)): ?>
    <div class="rt-pix-box">
        <?php
        $cells = '';
        foreach ($qr as $row) {
            foreach ($row as $cell) {
                $cells .= '<span class="rt-qr-cell ' . ($cell ? 'on' : 'off') . '"></span>';
            }
        }
        ?>
        <div class="rt-qr"><div class="rt-qr-grid" aria-hidden="true"><?= $cells ?></div></div>
        <p class="rt-pix-label"><i class="fas fa-keyboard" style="margin-right:6px"></i>Ou copie o código abaixo</p>
        <code class="rt-pix-code" id="pixCodeText"><?= htmlspecialchars($paymentInfo['pix_code']) ?></code>
        <button class="rt-btn rt-btn-copy" id="copyPixBtn" type="button"><i class="fas fa-copy" id="copyPixIcon"></i><span id="copyPixLabel">Copiar Código Pix</span></button>
    </div>
    <?php elseif ($paymentMethod === 'boleto' && ($paymentInfo['boleto_number'] ?? null)): ?>
    <div class="rt-pix-box">
        <p class="rt-pix-label"><i class="fas fa-barcode" style="margin-right:6px"></i>Linha digitável</p>
        <code class="rt-pix-code"><?= htmlspecialchars($paymentInfo['boleto_number']) ?></code>
    </div>
    <?php endif; ?>

    <?php if ($expiresTs): ?>
    <div class="rt-countdown" id="countdownBox" data-expires="<?= (int) $expiresTs ?>">
        <div class="rt-timer-card"><span class="rt-timer-value" id="cdMin">--</span><span class="rt-timer-label">min</span></div>
        <div class="rt-timer-card"><span class="rt-timer-value" id="cdSec">--</span><span class="rt-timer-label">seg</span></div>
    </div>
    <p class="rt-note">O pagamento será detectado automaticamente. Você também pode atualizar esta página manualmente.</p>
    <?php endif; ?>

    <div class="rt-summary">
        <div class="rt-sum-row"><span>Número do pedido</span><span class="rt-val">#<?= $orderNum ?></span></div>
        <div class="rt-sum-row total"><span>Total a pagar</span><span class="rt-val money"><?= $fmtPrice($orderTotal) ?></span></div>
    </div>

    <div class="rt-actions">
        <form method="POST" class="rt-form">
            <input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?>
            <button class="rt-btn rt-btn-ghost" name="action" value="cancel" style="width:100%"><i class="fas fa-times"></i> Cancelar Pedido</button>
        </form>
    </div>

    <!-- Área de demonstração (apenas ambiente de teste) -->
    <div class="rt-demo">
        <h4><i class="fas fa-flask"></i>Ambiente de Teste — Simulador de Pagamento</h4>
        <p>Simule o comportamento do processador de pagamento neste ambiente de demonstração.</p>
        <div class="rt-demo-btns">
            <form method="POST" class="inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?>
                <button class="rt-btn rt-demo-green" name="action" value="simulate_paid"><i class="fas fa-check"></i> Aprovar Pagamento</button>
            </form>
            <form method="POST" class="inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?>
                <button class="rt-btn rt-demo-red" name="action" value="simulate_failed"><i class="fas fa-times"></i> Simular Erro</button>
            </form>
            <form method="POST" class="inline"><input type="hidden" name="id" value="<?= $orderId ?>"><?= csrf_field() ?>
                <button class="rt-btn rt-demo-gray" name="action" value="simulate_expired"><i class="fas fa-clock"></i> Simular Expiração</button>
            </form>
        </div>
    </div>

    <?php if ($paymentMethod === 'pix'): ?>
    <script>
    (function () {
        var codeEl = document.getElementById('pixCodeText');
        var btn = document.getElementById('copyPixBtn');
        if (!codeEl || !btn) return;

        var done = false;
        function flashCopied() {
            if (done) return;
            done = true;
            btn.classList.add('copied');
            var label = document.getElementById('copyPixLabel') || btn.querySelector('span');
            var icon = document.getElementById('copyPixIcon');
            if (label) label.textContent = 'Copiado!';
            if (icon) icon.className = 'fas fa-check';
            setTimeout(function () {
                btn.classList.remove('copied');
                if (label) label.textContent = 'Copiar Código Pix';
                if (icon) icon.className = 'fas fa-copy';
                done = false;
            }, 2200);
        }

        btn.addEventListener('click', function () {
            var text = codeEl.textContent.trim();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(flashCopied).catch(function () { legacyCopy(); });
            } else {
                legacyCopy();
            }
        });

        function legacyCopy() {
            var range = document.createRange();
            range.selectNodeContents(codeEl);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            try { document.execCommand('copy'); flashCopied(); } catch (e) {}
            sel.removeAllRanges();
        }
    })();
    </script>
    <?php endif; ?>

    <script>
    (function () {
        var box = document.getElementById('countdownBox');
        var minEl = document.getElementById('cdMin');
        var secEl = document.getElementById('cdSec');

        function startCountdown() {
            if (!box || !minEl || !secEl) return;
            var exp = parseInt(box.getAttribute('data-expires'), 10) * 1000;
            function tick() {
                var diff = Math.max(0, exp - Date.now());
                var m = Math.floor(diff / 60000);
                var s = Math.floor((diff % 60000) / 1000);
                minEl.textContent = String(m).padStart(2, '0');
                secEl.textContent = String(s).padStart(2, '0');

                var underOneMin = diff < 60000;
                box.classList.toggle('rt-timer-warn', underOneMin);

                if (diff <= 0) {
                    setTimeout(function () { window.location.reload(); }, 500);
                    return;
                }
                setTimeout(tick, 250);
            }
            tick();
        }

        function startPolling() {
            var first = true;
            function poll() {
                var url = 'payment.php?id=<?= (int) $orderId ?>&poll=1';
                if (!first) url += '&t=' + Date.now();
                first = false;
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d.payment_status && d.payment_status !== 'pending' && d.payment_status !== 'processing') {
                            window.location.reload();
                            return;
                        }
                        setTimeout(poll, 5000);
                    })
                    .catch(function () { setTimeout(poll, 10000); });
            }
            setTimeout(poll, 5000);
        }

        startCountdown();
        startPolling();
    })();
    </script>

<?php endif; ?>

    </div>
    <p class="rt-foot"><i class="fas fa-lock"></i>Ambiente de demonstração — pagamentos são simulados. Royal Tech.</p>
</div>

</body>
</html>