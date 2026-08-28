<?php
$page_title = 'Finalizar Pedido - Royal Tech';
$breadcrumb_title = 'Finalizar Pedido';
$current_page = 'carrinho';
$base_path = '../../';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once $base_path . 'database/connection.php';
require_once $base_path . '/includes/cart_functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../includes/gateways.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/comprovante.php';
require_once __DIR__ . '/../../includes/shipping.php';
require_once __DIR__ . '/../../includes/pix.php';
require_once __DIR__ . '/../../includes/payment.php';

$userId = (int) $_SESSION['user_id'];
$items = cartGetItems($pdo, $userId);

if (empty($items)) {
    header('Location: cart.php');
    exit;
}

// Lock gateway at checkout start to prevent mid-checkout gateway switching
$checkoutSessionId = session_id();
if (!isset($_SESSION['checkout_gateway_locked'])) {
    $activeGw = gatewayGetActive();
    $taxRegime = store_config('tax_regime') ?: 'CPF';
    $lockedGateway = $activeGw ? $activeGw['gateway_name'] : 'mercadopago';
    gatewayLockForCheckout($checkoutSessionId, $lockedGateway, $taxRegime);
    $_SESSION['checkout_gateway_locked'] = $lockedGateway;
}
$gatewayUsed = $_SESSION['checkout_gateway_locked'];

$stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

$subtotal = 0;
$subtotalCents = 0;
foreach ($items as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
    $subtotalCents += (int) round((float) $item['price'] * 100) * (int) $item['quantity'];
}

// === FRETE REAL ===
$selectedShippingKey = $_POST['shipping_method'] ?? ($_GET['shipping_method'] ?? '');
$shippingCep = $_POST['shipping_cep'] ?? ($user['postal_code'] ?? '');

$shippingResult = [
    'success' => false,
    'provider' => 'estimated',
    'is_real' => false,
    'warning' => null,
    'error' => null,
    'address' => null,
    'options' => [],
];

if (!empty($shippingCep)) {
    $shippingResult = shippingCalculate($shippingCep, $subtotal, $items);
}

$shippingOptions = $shippingResult['options'] ?? [];
$isRealFrete = $shippingResult['is_real'] ?? false;
$freteWarning = $shippingResult['warning'] ?? null;
$freteError = $shippingResult['error'] ?? null;
$freteAddress = $shippingResult['address'] ?? null;

// Seleciona frete válido (primeiro se não enviado)
$firstKey = array_key_first($shippingOptions);
$selectedShipping = $selectedShippingKey !== '' && isset($shippingOptions[$selectedShippingKey])
    ? $selectedShippingKey
    : $firstKey;
$selectedOption = $shippingOptions[$selectedShipping] ?? null;
$shippingCost = $selectedOption ? (float) $selectedOption['cost'] : 0.00;

// === PAGAMENTO ===
$paymentMethod = $_POST['payment_method'] ?? 'pix';
$paymentMethods = paymentGetMethods(); // do payment.php

$pixDiscount = 0;
$grandTotal = 0.0;
$creditFeeInfo = null;

if ($paymentMethod === 'pix') {
    $shippingCents = (int) round($shippingCost * 100);
    $discountCents = (int) round($subtotalCents * 0.05);
    $grandTotalCents = $subtotalCents + $shippingCents - $discountCents;
    $pixDiscount = $discountCents / 100;
    $grandTotal = $grandTotalCents / 100;
} else {
    $grandTotal = ( ($subtotalCents + (int) round($shippingCost * 100)) ) / 100;
    // Taxa de gateway informativa (não somada ao total)
    if ($paymentMethod === 'credit') {
        $config = paymentGetConfig();
        $creditFeeInfo = [
            'percentage' => $config['fee_percentage'],
            'amount' => round($grandTotal * ($config['fee_percentage'] / 100), 2),
            'is_estimate' => $config['fee_is_estimate'],
        ];
    }
}

$orderCreated = false;
$orderId = null;
$orderPaymentInfo = null;
$errorMessage = null;

$isConfirming = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order']);

if ($isConfirming) {
    csrf_require_valid();

    // Validações
    foreach ($items as $item) {
        $check = validateStock($pdo, (int) $item['product_id'], (int) $item['quantity']);
        if (!$check['ok']) {
            $errorMessage = $check['msg'] . ' Remova o item do carrinho.';
            break;
        }
    }

    if (!$selectedOption) {
        $errorMessage = 'Selecione uma opção de frete válida.';
    }

    if (!$errorMessage) {
        try {
            $pdo->beginTransaction();

            $taxRegimeSnapshot = store_config('tax_regime') ?: 'CPF';
            $gatewaySnapshot = $gatewayUsed ?? 'mercadopago';

            // Dados do endereço do frete (ViaCEP)
            $shipNeighborhood = $freteAddress['bairro'] ?? null;
            $shipCity = $freteAddress['cidade'] ?? null;
            $shipState = $freteAddress['uf'] ?? null;

            $stmt = $pdo->prepare('
                INSERT INTO e5_orders 
                (user_id, status, total, shipping_method, shipping_carrier, shipping_cost, shipping_delivery_time, shipping_is_estimated, payment_method, gateway_used, payment_status, tax_regime_snapshot, shipping_postal_code, shipping_neighborhood, shipping_city, shipping_state) 
                VALUES (:uid, :status, :total, :ship, :carrier, :shipcost, :shipdays, :shipest, :pay, :gateway, :paystatus, :regime, :cep, :neigh, :city, :state)
            ');
            $stmt->execute([
                ':uid' => $userId,
                ':status' => 'pending',
                ':total' => $grandTotal,
                ':ship' => $selectedOption['method'] ?? null,
                ':carrier' => $selectedOption['carrier'] ?? null,
                ':shipcost' => $shippingCost,
                ':shipdays' => $selectedOption['days'] ?? null,
                ':shipest' => $isRealFrete ? 0 : 1,
                ':pay' => $paymentMethod,
                ':gateway' => $gatewaySnapshot,
                ':paystatus' => $paymentMethod === 'delivery' ? 'pending' : 'pending', // PIX = pending até confirmação
                ':regime' => $taxRegimeSnapshot,
                ':cep' => preg_replace('/\D/', '', $shippingCep),
                ':neigh' => $shipNeighborhood,
                ':city' => $shipCity,
                ':state' => $shipState,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $stmtItem = $pdo->prepare('INSERT INTO e5_order_items (order_id, product_id, quantity, unit_price) VALUES (:oid, :pid, :qty, :price)');
            foreach ($items as $item) {
                $stmtItem->execute([
                    ':oid' => $orderId,
                    ':pid' => (int) $item['product_id'],
                    ':qty' => (int) $item['quantity'],
                    ':price' => (float) $item['price'],
                ]);
                decrementStock($pdo, (int) $item['product_id'], (int) $item['quantity']);
            }

            cartClear($pdo, $userId);

            // Gera informações de pagamento por método
            if ($paymentMethod === 'pix') {
                $pix = pixGenerateForOrder($grandTotal, (string) $orderId, ['name' => $user['name'] ?? '']);
                if ($pix['success']) {
                    $orderPaymentInfo = [
                        'method' => 'Pix',
                        'instructions' => 'Escaneie o QR Code abaixo ou copie o código Pix (copia-e-cola) no app do seu banco.',
                        'br_code' => $pix['data']['br_code'],
                        'qr_data_uri' => $pix['data']['qr_data_uri'],
                        'expires' => date('d/m/Y H:i', strtotime($pix['data']['expires_at'])),
                        'txid' => $pix['data']['txid'],
                    ];
                } else {
                    $orderPaymentInfo = [
                        'method' => 'Pix',
                        'instructions' => 'Erro ao gerar código Pix. Tente novamente ou use outro método.',
                        'br_code' => null,
                        'qr_data_uri' => null,
                        'expires' => date('d/m/Y H:i', strtotime('+30 minutes')),
                    ];
                }
            } elseif ($paymentMethod === 'boleto') {
                $orderPaymentInfo = [
                    'method' => 'Boleto',
                    'instructions' => 'Boleto será gerado via gateway. Aguarde o e-mail ou acesse "Meus Pedidos".',
                    'boleto_number' => null,
                    'expires' => date('d/m/Y', strtotime('+3 days')),
                ];
            } elseif ($paymentMethod === 'credit') {
                $installments = min(12, max(1, (int) floor($grandTotal / 50)));
                $installmentValue = $grandTotal / $installments;
                $orderPaymentInfo = [
                    'method' => 'Cartão de Crédito',
                    'instructions' => 'Pagamento será processado pelo gateway ativo (' . $gatewaySnapshot . ').',
                    'installments' => $installments . 'x de R$ ' . number_format($installmentValue, 2, ',', '.'),
                    'gateway' => $gatewaySnapshot,
                ];
            } else {
                $orderPaymentInfo = [
                    'method' => 'Pagamento na Entrega',
                    'instructions' => 'Pague no momento da entrega. Aceitamos dinheiro, cartão de crédito e débito.',
                ];
            }

            $pdo->commit();
            $orderCreated = true;

            // Comprovante + e-mail
            try {
                $comp = gerarComprovanteCompra($pdo, $orderId, true);
            } catch (Throwable $e) {
                error_log('Comprovante generation failed: ' . $e->getMessage());
            }
            try {
                enviarComprovanteEmail($pdo, $orderId);
            } catch (Throwable $e) {
                error_log('Checkout comprovante email failed: ' . $e->getMessage());
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = 'Erro ao processar pedido. Tente novamente.';
            error_log('Checkout error: ' . $e->getMessage());
        }
    }
}

include $base_path . 'components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;"><div class="container">
    <div class="ml-section-header">
        <h2 class="ml-section-title">Finalizar Pedido</h2>
    </div>

    <?php if ($orderCreated): ?>
        <div class="ml-empty">
            <i class="fas fa-check-circle" style="color: var(--ml-green); opacity: 1;"></i>
            <h3>Pedido Confirmado!</h3>
            <p>Seu pedido #<?php echo str_pad((string)$orderId, 4, '0', STR_PAD_LEFT); ?> foi criado com sucesso.</p>
        </div>
        <?php if ($orderPaymentInfo): ?>
        <div class="container">
            <div class="ml-card" style="max-width: 520px; margin: 0 auto 16px;">
                <div class="ml-step-head">
                    <span class="ml-step-num"><i class="fas fa-<?php echo $orderPaymentInfo['method'] === 'Pix' ? 'pix' : ($orderPaymentInfo['method'] === 'Boleto' ? 'barcode' : 'credit-card'); ?>"></i></span>
                    <h3><?php echo htmlspecialchars($orderPaymentInfo['method'], ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>
                <p style="color: var(--ml-text-secondary); font-size: 0.92rem;"><?php echo htmlspecialchars($orderPaymentInfo['instructions'], ENT_QUOTES, 'UTF-8'); ?></p>

                <?php if ($orderPaymentInfo['method'] === 'Pix' && !empty($orderPaymentInfo['br_code'])): ?>
                    <div style="margin-top: 16px; text-align: center;">
                        <?php if (!empty($orderPaymentInfo['qr_data_uri'])): ?>
                            <img src="<?php echo htmlspecialchars($orderPaymentInfo['qr_data_uri'], ENT_QUOTES, 'UTF-8'); ?>" alt="QR Code Pix" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <?php endif; ?>
                        <div class="payment-code-box" style="margin-top: 12px; max-width: 100%; overflow-x: auto;">
                            <code id="pixCode" style="font-size: 0.75rem; word-break: break-all;"><?php echo htmlspecialchars($orderPaymentInfo['br_code'], ENT_QUOTES, 'UTF-8'); ?></code>
                            <button type="button" class="ml-btn" onclick="navigator.clipboard.writeText(document.getElementById('pixCode').textContent);this.innerHTML='<i class=\'fas fa-check\'></i> Copiado!';setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy\'></i> Copiar Código Pix',2000);" style="margin-top:10px;"><i class="fas fa-copy"></i> Copiar Código Pix</button>
                        </div>
                        <small style="color: var(--ml-text-muted); display: block; margin-top: 8px;">Válido até: <?php echo htmlspecialchars($orderPaymentInfo['expires'], ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                <?php endif; ?>

                <?php if (isset($orderPaymentInfo['boleto_number'])): ?>
                    <div class="payment-code-box">
                        <code><?php echo htmlspecialchars($orderPaymentInfo['boleto_number'], ENT_QUOTES, 'UTF-8'); ?></code>
                    </div>
                    <small style="color: var(--ml-text-muted);">Vencimento: <?php echo htmlspecialchars($orderPaymentInfo['expires'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                <?php if (isset($orderPaymentInfo['installments'])): ?>
                    <p style="font-size: 1.1rem; color: var(--ml-accent); margin-top: 10px;"><strong><?php echo htmlspecialchars($orderPaymentInfo['installments'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <?php if (!empty($orderPaymentInfo['gateway'])): ?>
                        <small style="color: var(--ml-text-muted);">Via: <?php echo htmlspecialchars(strtoupper($orderPaymentInfo['gateway']), ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="container" style="text-align: center;">
            <p style="margin-bottom: 20px; color: var(--ml-text-secondary);">Você receberá um e-mail com os detalhes do pedido.</p>
            <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <a href="../products/products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Continuar Comprando</a>
                <a href="../auth/orders.php" class="ml-btn"><i class="fas fa-list"></i> Meus Pedidos</a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($errorMessage): ?>
            <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($freteError): ?>
            <div class="auth-feedback auth-feedback-error" style="margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($freteError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="checkout-grid">
            <div class="checkout-left">
                <!-- Etapa 1: Frete -->
                <div class="ml-card">
                    <div class="ml-step-head">
                        <span class="ml-step-num">1</span>
                        <h3><i class="fas fa-truck"></i> Entrega</h3>
                    </div>
                    <label class="auth-label" for="shipping_cep">CEP de entrega</label>
                    <div style="display: flex; gap: 10px; align-items: stretch;">
                        <div class="auth-input-wrap" style="flex: 1;">
                            <input type="text" id="shipping_cep" name="shipping_cep" form="checkoutForm" value="<?php echo htmlspecialchars($shippingCep, ENT_QUOTES, 'UTF-8'); ?>" placeholder="00000-000" maxlength="9" oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{5})(\d)/,'$1-$2')">
                        </div>
                        <button type="submit" form="checkoutForm" class="ml-btn" name="calc_shipping" value="1"><i class="fas fa-search"></i> Calcular</button>
                    </div>

                    <?php if ($freteWarning): ?>
                    <div style="margin: 10px 0; padding: 10px 12px; background: rgba(255,152,0,0.1); border: 1px solid rgba(255,152,0,0.3); border-radius: 6px; font-size: 0.85rem;">
                        <i class="fas fa-info-circle"></i> <?php echo $freteWarning; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($shippingOptions): ?>
                    <div class="shipping-options">
                        <?php foreach ($shippingOptions as $key => $opt): ?>
                        <label class="shipping-option <?php echo $selectedShipping === $key ? 'selected' : ''; ?>">
                            <input type="radio" name="shipping_method" form="checkoutForm" value="<?php echo $key; ?>" <?php echo $selectedShipping === $key ? 'checked' : ''; ?>>
                            <div class="shipping-option-content">
                                <strong><?php echo htmlspecialchars($opt['method'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($opt['carrier']) && $opt['carrier'] !== $opt['method']): ?>
                                    <span style="color: var(--ml-text-muted); font-size: 0.8rem; margin-left: 6px;">por <?php echo htmlspecialchars($opt['carrier'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <span class="shipping-days"><?php echo htmlspecialchars($opt['days'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if (!empty($opt['estimated'])): ?>
                                    <span class="shipping-badge" style="background: rgba(255,152,0,0.2); color: #e65100; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem; margin-left: 6px;">ESTIMADO</span>
                                <?php endif; ?>
                                <span class="shipping-cost"><?php echo $opt['cost'] > 0 ? 'R$ ' . number_format($opt['cost'], 2, ',', '.') : '<strong style="color:var(--ml-green);">Grátis</strong>'; ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php elseif (!empty($shippingCep)): ?>
                    <p style="color: var(--ml-text-muted); margin-top: 10px;">CEP não encontrado. Verifique o número.</p>
                    <?php endif; ?>

                    <?php if ($subtotal >= 500 && ($freteAddress ?? false)): ?>
                    <p class="free-shipping-badge"><i class="fas fa-gift"></i> Frete Grátis! Compras acima de R$ 500,00.</p>
                    <?php endif; ?>
                </div>

                <!-- Etapa 2: Pagamento -->
                <div class="ml-card">
                    <div class="ml-step-head">
                        <span class="ml-step-num">2</span>
                        <h3><i class="fas fa-credit-card"></i> Pagamento</h3>
                    </div>
                    <div class="payment-options">
                        <?php foreach ($paymentMethods as $key => $pm):
                            if (!$pm['enabled']) continue;
                        ?>
                        <label class="payment-option <?php echo $paymentMethod === $key ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" form="checkoutForm" value="<?php echo $key; ?>" <?php echo $paymentMethod === $key ? 'checked' : ''; ?>>
                            <div class="payment-option-content">
                                <strong><?php echo htmlspecialchars($pm['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="payment-desc"><?php echo htmlspecialchars($pm['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($key === 'pix'): ?><span class="pix-discount">5% OFF</span><?php endif; ?>
                                <?php if ($key === 'credit' && $creditFeeInfo): ?>
                                    <span class="fee-badge" style="background: rgba(212,175,55,0.15); color: var(--ml-accent); padding: 2px 6px; border-radius: 3px; font-size: 0.7rem; margin-left: 6px;">
                                        Taxa: <?php echo number_format($creditFeeInfo['percentage'], 2, ',', '.'); ?>% (~R$ <?php echo number_format($creditFeeInfo['amount'], 2, ',', '.'); ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Etapa 3: Endereço -->
                <div class="ml-card">
                    <div class="ml-step-head">
                        <span class="ml-step-num">3</span>
                        <h3><i class="fas fa-map-marker-alt"></i> Endereço de Entrega</h3>
                    </div>
                    <p><strong><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <p style="color: var(--ml-text-secondary);"><?php echo htmlspecialchars($user['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)($user['number'] ?? 0); ?><?php if ($user['complement']): ?> - <?php echo htmlspecialchars($user['complement'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></p>
                    <p style="color: var(--ml-text-secondary);">
                        <?php if ($freteAddress && $freteAddress['bairro']): ?>
                            <?php echo htmlspecialchars($freteAddress['bairro'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($freteAddress['cidade'], ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars($freteAddress['uf'], ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php endif; ?>
                        CEP: <?php echo htmlspecialchars($shippingCep, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <a href="../auth/profile.php" class="ml-btn" style="font-size: 0.85rem; padding: 8px 16px; margin-top: 10px;"><i class="fas fa-edit"></i> Alterar Endereço</a>
                </div>
            </div>

            <div class="checkout-right">
                <div class="ml-summary-card">
                    <h3>Resumo do Pedido</h3>
                    <?php foreach ($items as $item): ?>
                    <div class="ml-summary-line">
                        <span><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?> <small>x<?php echo (int)$item['quantity']; ?></small></span>
                        <span>R$ <?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2, ',', '.'); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="ml-summary-line">
                        <span>Subtotal (<?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'itens'; ?>)</span>
                        <span>R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                    </div>
                    <?php if ($selectedOption): ?>
                    <div class="ml-summary-line">
                        <span>Frete: <?php echo htmlspecialchars($selectedOption['method'], ENT_QUOTES, 'UTF-8'); ?> <?php echo !empty($selectedOption['carrier']) && $selectedOption['carrier'] !== $selectedOption['method'] ? '(' . htmlspecialchars($selectedOption['carrier'], ENT_QUOTES, 'UTF-8') . ')' : ''; ?></span>
                        <span><?php echo $shippingCost > 0 ? 'R$ ' . number_format($shippingCost, 2, ',', '.') : '<span style="color:var(--ml-green);">Grátis</span>'; ?></span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--ml-text-muted); text-align: right; padding: 2px 0 6px;">Previsão: <?php echo htmlspecialchars($selectedOption['days'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if (!$isRealFrete): ?>
                    <div style="font-size: 0.75rem; color: #e65100; text-align: right; padding: 2px 0 8px;"><i class="fas fa-exclamation-circle"></i> Valor estimado — configure o token do Melhor Envio para preços reais.</div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($pixDiscount > 0): ?>
                    <div class="ml-summary-line discount">
                        <span>Desconto Pix (5%)</span>
                        <span>- R$ <?php echo number_format($pixDiscount, 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($creditFeeInfo): ?>
                    <div class="ml-summary-line" style="color: var(--ml-text-secondary); font-size: 0.85rem;">
                        <span>Taxa do gateway (<?php echo number_format($creditFeeInfo['percentage'], 2, ',', '.'); ?>%) <small><?php echo $creditFeeInfo['is_estimate'] ? '(estimativa)' : ''; ?></small></span>
                        <span>R$ <?php echo number_format($creditFeeInfo['amount'], 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="ml-summary-line total">
                        <span>Total</span>
                        <span>R$ <?php echo number_format($grandTotal, 2, ',', '.'); ?></span>
                    </div>
                    <?php if ($paymentMethod === 'credit' && $grandTotal > 0 && isset($installments)): ?>
                    <div style="font-size: 0.85rem; color: var(--ml-text-secondary); text-align: center; padding-top: 10px; border-top: 1px solid var(--ml-border); margin-top: 8px;">
                        ou <strong><?php echo $installments; ?>x de R$ <?php echo number_format($grandTotal / $installments, 2, ',', '.'); ?></strong> sem juros
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="checkoutForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="shipping_cep" value="<?php echo htmlspecialchars($shippingCep, ENT_QUOTES, 'UTF-8'); ?>">
                        <p style="margin-bottom: 15px; font-size: 0.85rem; color: var(--ml-text-muted);"><i class="fas fa-info-circle"></i> Ao finalizar, você concorda com nossos termos de compra.</p>
                        <button type="submit" name="confirm_order" class="ml-btn ml-btn-primary ml-btn-block" style="padding: 14px; font-size: 1.05rem;" <?php echo !$selectedOption ? 'disabled' : ''; ?>><i class="fas fa-check"></i> Confirmar Pedido</button>
                        <a href="cart.php" class="ml-btn ml-btn-block" style="margin-top: 10px;"><i class="fas fa-arrow-left"></i> Voltar ao Carrinho</a>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.querySelectorAll('input[name="shipping_method"]').forEach(function(el) {
            el.addEventListener('change', function() {
                var f = this.form;
                if (f) f.submit();
            });
        });
        document.querySelectorAll('input[name="payment_method"]').forEach(function(el) {
            el.addEventListener('change', function() {
                var f = this.form;
                if (f) f.submit();
            });
        });
        </script>
    <?php endif; ?>
</div></section>
<?php include $base_path . 'components/footer.php'; ?>