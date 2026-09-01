<?php
$page_title = 'Finalizar Pedido - Royal Tech';
$breadcrumb_title = 'Finalizar Pedido';
$current_page = 'carrinho';
$base_path = '../../';

session_start();
$isGuest = !isset($_SESSION['user_id']);

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
require_once __DIR__ . '/../../includes/coupons.php';

if ($isGuest) {
    $userId = null;
    $items = sessionCartGetItems($pdo);
    $user = ['name' => '', 'street' => '', 'number' => 0, 'complement' => '', 'postal_code' => ''];
} else {
    $userId = (int) $_SESSION['user_id'];
    $items = cartGetItems($pdo, $userId);
    $stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
}

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

// Public key for Mercado Pago Checkout Transparente (JS SDK)
$mpPublicKey = '';
if ($gatewayUsed === 'mercadopago') {
    try {
        $mpPublicKey = (string) loadEncryptedSetting($GLOBALS['pdo'] ?? $pdo, 'mercadopago_public_key');
    } catch (Throwable $e) { /* ignore */ }
}

$subtotal = 0;
$subtotalCents = 0;
foreach ($items as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
    $subtotalCents += (int) round((float) $item['price'] * 100) * (int) $item['quantity'];
}

// === CUPOM DE DESCONTO ===
$couponCodeInput = strtoupper(trim($_POST['coupon_code'] ?? $_GET['coupon_code'] ?? ''));
$couponApplied = null;
$couponDiscount = 0.0;
if (!empty($couponCodeInput)) {
    $couponResult = couponValidate($pdo, $couponCodeInput);
    if ($couponResult['valid']) {
        $couponApplied = $couponResult['coupon'];
        $couponDiscount = couponCalculateDiscount($couponApplied, $subtotal);
    }
}

// === FRETE REAL ===
$selectedShippingKey = $_POST['shipping_method'] ?? ($_GET['shipping_method'] ?? '');
$shippingCep = $_POST['shipping_cep'] ?? ($user['postal_code'] ?? '');

$shippingResult = [
    'success' => false,
    'provider' => 'error',
    'is_real' => false,
    'warning' => null,
    'error' => 'Informe o CEP para calcular o frete.',
    'address' => null,
    'options' => [],
];

if (!empty($shippingCep)) {
    $shippingResult = shippingCalculate($shippingCep, $subtotal, $items);
}

$shippingOptions = $shippingResult['options'] ?? [];
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
$validPaymentMethods = ['pix', 'boleto', 'credit', 'delivery'];
if (!in_array($paymentMethod, $validPaymentMethods, true)) {
    $paymentMethod = 'pix';
}
$paymentMethods = paymentGetMethods(); // do payment.php

$pixDiscount = 0;
$grandTotal = 0.0;
$creditFeeInfo = null;

if ($paymentMethod === 'pix') {
    $shippingCents = (int) round($shippingCost * 100);
    $discountCents = (int) round($subtotalCents * 0.05);
    $grandTotalCents = $subtotalCents + $shippingCents - $discountCents;
    $pixDiscount = $discountCents / 100;
    $grandTotal = max(0, ($grandTotalCents / 100) - $couponDiscount);
} else {
    $grandTotal = max(0, ( ($subtotalCents + (int) round($shippingCost * 100)) ) / 100 - $couponDiscount);
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
$isApplyingCoupon = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon']);
$isCalcShipping = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calc_shipping']);

// Valida CSRF em qualquer POST (confirm_order, apply_coupon, calc_shipping)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
}

if ($isConfirming) {

    $guestName = trim($_POST['guest_name'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $guestStreet = trim($_POST['guest_street'] ?? '');
    $guestNumber = trim($_POST['guest_number'] ?? '');

    if ($isGuest) {
        if (empty($guestName) || mb_strlen($guestName) < 3) $errorMessage = 'Nome completo é obrigatório (mínimo 3 caracteres).';
        elseif (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) $errorMessage = 'E-mail inválido.';
        elseif (empty($guestStreet) || mb_strlen($guestStreet) < 4) $errorMessage = 'Rua é obrigatória.';
        elseif (empty($guestNumber)) $errorMessage = 'Número é obrigatório.';
    }

    // Validações
    foreach ($items as $item) {
        $check = validateStock($pdo, (int) $item['product_id'], (int) $item['quantity']);
        if (!$check['ok']) {
            $errorMessage = $check['msg'] . ' Remova o item do carrinho.';
            break;
        }
    }

    // Validação de mudança de preço
    $priceChanges = validatePriceChange($pdo, $items);
    if (!empty($priceChanges) && !$errorMessage) {
        $priceChangeMsg = 'Os seguintes produtos tiveram alteração de preço: ';
        $priceChangeMsg .= implode(', ', array_map(function($c) {
            return $c['name'] . ' (de R$ ' . number_format($c['old_price'], 2, ',', '.') . ' para R$ ' . number_format($c['new_price'], 2, ',', '.') . ')';
        }, $priceChanges));
        $errorMessage = $priceChangeMsg . '. Atualize a página para ver os novos valores.';
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
                (user_id, guest_name, guest_email, status, total, shipping_method, shipping_carrier, shipping_cost, shipping_delivery_time, shipping_is_estimated, payment_method, gateway_used, payment_status, tax_regime_snapshot, shipping_postal_code, shipping_neighborhood, shipping_city, shipping_state, coupon_code) 
                VALUES (:uid, :guest_name, :guest_email, :status, :total, :ship, :carrier, :shipcost, :shipdays, :shipest, :pay, :gateway, :paystatus, :regime, :cep, :neigh, :city, :state, :coupon)
            ');
            $stmt->execute([
                ':uid' => $userId,
                ':guest_name' => $isGuest ? $guestName : null,
                ':guest_email' => $isGuest ? $guestEmail : null,
                ':status' => 'pending',
                ':total' => $grandTotal,
                ':ship' => $selectedOption['method'] ?? null,
                ':carrier' => $selectedOption['carrier'] ?? null,
                ':shipcost' => $shippingCost,
                ':shipdays' => $selectedOption['days'] ?? null,
                ':shipest' => 0,
                ':pay' => $paymentMethod,
                ':gateway' => $gatewaySnapshot,
                ':paystatus' => $paymentMethod === 'delivery' ? 'pending' : 'pending', // PIX = pending até confirmação
                ':regime' => $taxRegimeSnapshot,
                ':cep' => preg_replace('/\D/', '', $shippingCep),
                ':neigh' => $shipNeighborhood,
                ':city' => $shipCity,
                ':state' => $shipState,
                ':coupon' => $couponApplied ? $couponApplied['code'] : null,
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

            if ($isGuest) {
                sessionCartClear();
            } else {
                cartClear($pdo, $userId);
            }

            // Reserva uso do cupom de forma atômica (dentro da transação)
            if ($couponApplied) {
                if (!couponReserveUsage($pdo, (int) $couponApplied['id'])) {
                    throw new RuntimeException('Cupom não disponível (uso máximo atingido).');
                }
            }

            // Dados comuns para todos os meios de pagamento (SDK Mercado Pago)
            $customerData = [
                'name'  => $isGuest ? ($guestName ?? '') : ($user['name'] ?? ''),
                'email' => $isGuest ? ($guestEmail ?? '') : ($user['email'] ?? ''),
                'cpf'   => trim($_POST['payment_cpf'] ?? $_POST['cc_cpf'] ?? ''),
            ];
            $itemsForGateway = [];
            foreach ($items as $item) {
                $itemsForGateway[] = [
                    'id'         => (string) $item['product_id'],
                    'title'      => $item['name'] ?? 'Produto',
                    'quantity'   => (int) $item['quantity'],
                    'unit_price' => (float) $item['price'],
                ];
            }

            // Gera informações de pagamento por método
            if ($paymentMethod === 'pix') {
                if ($gatewayUsed !== 'mercadopago') {
                    throw new RuntimeException('Gateway de pagamento não configurado. Configure o Mercado Pago no painel admin.');
                }
                
                $pixResult = paymentMercadoPagoCreatePix($grandTotal, (string) $orderId, $itemsForGateway, $customerData);
                
                if (!$pixResult || !$pixResult['success']) {
                    throw new RuntimeException('Erro ao gerar código Pix: ' . ($pixResult['message'] ?? 'Gateway indisponível'));
                }
                
                $stmtUpd = $pdo->prepare('UPDATE e5_orders SET gateway_transaction_id = :tx WHERE id = :oid');
                $stmtUpd->execute([':tx' => $pixResult['data']['order_id'], ':oid' => $orderId]);
                
                $orderPaymentInfo = [
                    'method'       => 'Pix',
                    'instructions'=> 'Escaneie o QR Code abaixo ou copie o código Pix no app do seu banco.',
                    'br_code'     => $pixResult['data']['qr_code'],
                    'qr_data_uri' => $pixResult['data']['qr_data_uri'],
                    'expires'     => date('d/m/Y H:i', strtotime($pixResult['data']['expires_at'])),
                ];
            } elseif ($paymentMethod === 'boleto') {
                if ($gatewayUsed !== 'mercadopago') {
                    throw new RuntimeException('Gateway de pagamento não configurado. Configure o Mercado Pago no painel admin.');
                }
                
                $boletoResult = paymentMercadoPagoCreateBoleto($grandTotal, (string) $orderId, $itemsForGateway, $customerData);
                
                if (!$boletoResult || !$boletoResult['success']) {
                    throw new RuntimeException('Erro ao gerar boleto: ' . ($boletoResult['message'] ?? 'Gateway indisponível'));
                }
                
                $stmtUpd = $pdo->prepare('UPDATE e5_orders SET gateway_transaction_id = :tx WHERE id = :oid');
                $stmtUpd->execute([':tx' => $boletoResult['data']['order_id'], ':oid' => $orderId]);
                
                $orderPaymentInfo = [
                    'method'       => 'Boleto',
                    'instructions'=> 'Boleto gerado com sucesso. Clique no link para imprimir.',
                    'ticket_url'  => $boletoResult['data']['ticket_url'],
                    'barcode'     => $boletoResult['data']['barcode'],
                    'digitable'   => $boletoResult['data']['digitable_line'],
                    'expires'     => date('d/m/Y', strtotime($boletoResult['data']['due_date'])),
                ];
            } elseif ($paymentMethod === 'credit') {
                $ccInstallments = min(12, max(1, (int) ($_POST['cc_installments'] ?? 1)));
                $ccCpf = $customerData['cpf']; // já extraído do POST acima

                // Verificar se usou cartão salvo
                $savedCardId = (int) ($_POST['saved_card_id'] ?? 0);
                if (!$isGuest && $savedCardId > 0) {
                    $ccToken = savedCardGetToken($pdo, $userId, $savedCardId) ?? '';
                } else {
                    $ccToken = trim($_POST['cc_token'] ?? '');
                }

                $cardResult = paymentProcessCreditCard([
                    'token'        => $ccToken,
                    'installments' => $ccInstallments,
                    'items'        => $itemsForGateway,
                    'card_brand'   => trim($_POST['cc_brand'] ?? 'visa'),
                ], $grandTotal, (string) $orderId, [
                    'name'  => $customerData['name'],
                    'email' => $customerData['email'],
                    'cpf'   => $ccCpf,
                ]);

                if ($cardResult['success']) {
                    // Salvar cartão se checkbox marcado
                    if (!$isGuest && isset($_POST['save_card']) && !empty($ccToken)) {
                        try {
                            $lastFour = substr(preg_replace('/\D/', '', $_POST['cc_number'] ?? ''), -4);
                            $brand = trim($_POST['cc_brand'] ?? 'visa');
                            $name = $customerData['name'];
                            $hasCards = !empty(savedCardGetAll($pdo, $userId));
                            savedCardSave($pdo, $userId, $ccToken, $brand, $lastFour, $name, !$hasCards);
                        } catch (Throwable $e) {
                            error_log('checkout: falha ao salvar cartão: ' . $e->getMessage());
                        }
                    }
                    $txId = $cardResult['data']['transaction_id'] ?? null;
                    $stmtUpd = $pdo->prepare('UPDATE e5_orders SET payment_status = :ps, gateway_transaction_id = :tx WHERE id = :oid');
                    $stmtUpd->execute([':ps' => 'paid', ':tx' => $txId, ':oid' => $orderId]);

                    $installmentValue = $grandTotal / $ccInstallments;
                    $orderPaymentInfo = [
                        'method'      => 'Cartão de Crédito',
                        'instructions'=> 'Pagamento aprovado! ' . $ccInstallments . 'x de R$ ' . number_format($installmentValue, 2, ',', '.') . ' no cartão.',
                        'installments'=> $ccInstallments . 'x de R$ ' . number_format($installmentValue, 2, ',', '.'),
                        'gateway'     => $gatewaySnapshot,
                        'approved'    => true,
                    ];
                } else {
                    $stmtUpd = $pdo->prepare('UPDATE e5_orders SET payment_status = :ps, gateway_transaction_id = :tx WHERE id = :oid');
                    $stmtUpd->execute([':ps' => 'pending', ':tx' => null, ':oid' => $orderId]);

                    $installmentValue = $grandTotal / $ccInstallments;
                    $orderPaymentInfo = [
                        'method'      => 'Cartão de Crédito',
                        'instructions'=> 'Pagamento não aprovado: ' . ($cardResult['message'] ?? 'Erro desconhecido') . '. Verifique os dados do cartão ou tente outro método.',
                        'installments'=> $ccInstallments . 'x de R$ ' . number_format($installmentValue, 2, ',', '.'),
                        'gateway'     => $gatewaySnapshot,
                        'approved'    => false,
                    ];
                }
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

                <?php if (!empty($orderPaymentInfo['ticket_url'])): ?>
                    <div style="margin-top: 14px; text-align: center;">
                        <a href="<?php echo htmlspecialchars($orderPaymentInfo['ticket_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="ml-btn ml-btn-primary" style="display: inline-block;"><i class="fas fa-file-pdf"></i> Baixar Boleto (PDF)</a>
                        <?php if (!empty($orderPaymentInfo['digitable'])): ?>
                        <div style="margin-top: 10px;">
                            <small style="color: var(--ml-text-muted); display: block; margin-bottom: 4px;">Linha Digitável:</small>
                            <code id="boletoDigitable" style="font-size: 0.72rem; word-break: break-all; display: block; padding: 8px; background: var(--ml-bg-secondary); border-radius: 4px;"><?php echo htmlspecialchars($orderPaymentInfo['digitable'], ENT_QUOTES, 'UTF-8'); ?></code>
                            <button type="button" class="ml-btn" onclick="navigator.clipboard.writeText(document.getElementById('boletoDigitable').textContent);this.innerHTML='<i class=\'fas fa-check\'></i> Copiado!';setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy\'></i> Copiar Linha Digitável',2000);" style="margin-top:8px;"><i class="fas fa-copy"></i> Copiar Linha Digitável</button>
                        </div>
                        <?php endif; ?>
                        <small style="color: var(--ml-text-muted); display: block; margin-top: 8px;">Vencimento: <?php echo htmlspecialchars($orderPaymentInfo['expires'], ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                <?php elseif (isset($orderPaymentInfo['boleto_number'])): ?>
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
                        <i class="fas fa-info-circle"></i> <?php echo strip_tags($freteWarning, '<strong><em><b><i>'); ?>
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
                    <?php if ($isGuest): ?>
                    <p style="font-size: 0.85rem; color: var(--ml-text-muted); margin-bottom: 12px;">Preencha seus dados para entrega:</p>
                    <div class="auth-field"><label class="auth-label" for="guest_name">Nome completo</label><div class="auth-input-wrap"><input type="text" id="guest_name" name="guest_name" form="checkoutForm" required minlength="3" value="<?php echo htmlspecialchars($_POST['guest_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div></div>
                    <div class="auth-field"><label class="auth-label" for="guest_email">E-mail</label><div class="auth-input-wrap"><input type="email" id="guest_email" name="guest_email" form="checkoutForm" required value="<?php echo htmlspecialchars($_POST['guest_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div></div>
                    <div class="auth-field"><label class="auth-label" for="guest_street">Rua</label><div class="auth-input-wrap"><input type="text" id="guest_street" name="guest_street" form="checkoutForm" required minlength="4" value="<?php echo htmlspecialchars($_POST['guest_street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div></div>
                    <div style="display: flex; gap: 10px;">
                        <div class="auth-field" style="flex: 1;"><label class="auth-label" for="guest_number">Número</label><div class="auth-input-wrap"><input type="text" id="guest_number" name="guest_number" form="checkoutForm" required inputmode="numeric" value="<?php echo htmlspecialchars($_POST['guest_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div></div>
                        <div class="auth-field" style="flex: 2;"><label class="auth-label" for="guest_complement">Complemento</label><div class="auth-input-wrap"><input type="text" id="guest_complement" name="guest_complement" form="checkoutForm" value="<?php echo htmlspecialchars($_POST['guest_complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div></div>
                    </div>
                    <?php else: ?>
                    <p><strong><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <p style="color: var(--ml-text-secondary);"><?php echo htmlspecialchars($user['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(($user['number'] ?? 'S/N') ?: 'S/N', ENT_QUOTES, 'UTF-8'); ?><?php if ($user['complement']): ?> - <?php echo htmlspecialchars($user['complement'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></p>
                    <p style="color: var(--ml-text-secondary);">
                        <?php if ($freteAddress && $freteAddress['bairro']): ?>
                            <?php echo htmlspecialchars($freteAddress['bairro'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($freteAddress['cidade'], ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars($freteAddress['uf'], ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php endif; ?>
                        CEP: <?php echo htmlspecialchars($shippingCep, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <a href="../auth/profile.php" class="ml-btn" style="font-size: 0.85rem; padding: 8px 16px; margin-top: 10px;"><i class="fas fa-edit"></i> Alterar Endereço</a>
                    <?php endif; ?>
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
                    <?php endif; ?>
                    <?php if ($pixDiscount > 0): ?>
                    <div class="ml-summary-line discount">
                        <span>Desconto Pix (5%)</span>
                        <span>- R$ <?php echo number_format($pixDiscount, 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($couponDiscount > 0): ?>
                    <div class="ml-summary-line discount">
                        <span>Cupom <?php echo htmlspecialchars($couponApplied['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>- R$ <?php echo number_format($couponDiscount, 2, ',', '.'); ?></span>
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

                        <!-- Cupom de Desconto -->
                        <div style="margin-bottom: 16px; padding: 12px; border: 1px solid var(--ml-border); border-radius: 8px;">
                            <label style="font-size: 0.82rem; font-weight: 600; display: block; margin-bottom: 6px;"><i class="fas fa-tag"></i> Cupom de Desconto</label>
                            <?php if ($couponApplied): ?>
                                <div style="background: rgba(76,175,80,0.1); color: #2e7d32; padding: 8px 10px; border-radius: 4px; font-size: 0.85rem;">
                                    <i class="fas fa-check-circle"></i> Cupom <strong><?php echo htmlspecialchars($couponApplied['code'], ENT_QUOTES, 'UTF-8'); ?></strong> aplicado! Desconto: -R$ <?php echo number_format($couponDiscount, 2, ',', '.'); ?>
                                </div>
                            <?php else: ?>
                                <div style="display: flex; gap: 6px;">
                                    <input type="text" name="coupon_code" form="checkoutForm" value="<?php echo htmlspecialchars($couponCodeInput, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Código do cupom" style="flex:1; padding:8px; border:1px solid var(--ml-border); border-radius:6px; text-transform:uppercase; font-size:0.85rem;">
                                    <button type="submit" form="checkoutForm" class="ml-btn" name="apply_coupon" value="1" style="padding: 8px 12px; font-size: 0.82rem;"><i class="fas fa-check"></i> Aplicar</button>
                                </div>
                                <?php if ($couponCodeInput && !$couponApplied): ?>
                                    <p style="color: #c0392b; font-size: 0.82rem; margin-top: 6px;"><i class="fas fa-times-circle"></i> Cupom inválido ou expirado.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($paymentMethod === 'pix' || $paymentMethod === 'boleto'): ?>
                        <div id="paymentCpfField" style="margin-bottom: 16px; padding: 16px; border: 1px solid var(--ml-border); border-radius: 8px;">
                            <div class="auth-field">
                                <label class="auth-label" for="payment_cpf">CPF do Pagador</label>
                                <div class="auth-input-wrap">
                                    <input type="text" id="payment_cpf" name="payment_cpf" required placeholder="000.000.000-00" maxlength="14" inputmode="numeric" value="<?php echo htmlspecialchars($_POST['payment_cpf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <small style="color: var(--ml-text-muted); display: block; margin-top: 4px;">Necessário para <?php echo $paymentMethod === 'pix' ? 'gerar o QR Code Pix' : 'emissão do boleto'; ?>.</small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($paymentMethod === 'credit'): ?>
                        <!-- Campos de Cartão de Crédito — Checkout Transparente -->
                        <div id="creditCardFields" style="margin-bottom: 16px; padding: 16px; border: 1px solid var(--ml-border); border-radius: 8px; background: var(--ml-bg-secondary, #f8f9fa);">
                            <p style="font-size: 0.85rem; font-weight: 600; margin-bottom: 12px;"><i class="fas fa-lock"></i> Dados do Cartão de Crédito (pagamento seguro via <?php echo htmlspecialchars(strtoupper($gatewayUsed ?? 'gateway'), ENT_QUOTES, 'UTF-8'); ?>)</p>

                            <?php if (!$isGuest):
                                $savedCards = savedCardGetAll($pdo, $userId);
                                if (!empty($savedCards)):
                            ?>
                            <div style="margin-bottom: 14px;">
                                <label style="font-size: 0.82rem; font-weight: 600; display: block; margin-bottom: 6px;"><i class="fas fa-credit-card"></i> Cartão Salvo</label>
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <?php foreach ($savedCards as $sc): ?>
                                    <label class="payment-option" style="display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--ml-border); border-radius: 6px; cursor: pointer; font-size: 0.85rem;">
                                        <input type="radio" name="saved_card_id" value="<?php echo (int)$sc['id']; ?>" <?php echo $sc['is_default'] ? 'checked' : ''; ?>>
                                        <i class="fab fa-cc-<?php echo htmlspecialchars($sc['card_brand'], ENT_QUOTES, 'UTF-8'); ?>" style="font-size: 1.4rem; color: var(--ml-text-muted);"></i>
                                        <span>**** <?php echo htmlspecialchars($sc['last_four'], ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($sc['cardholder_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($sc['is_default']): ?><span style="background: var(--ml-accent); color: var(--ml-bg); padding: 1px 6px; border-radius: 3px; font-size: 0.65rem; font-weight: 600;">PADRÃO</span><?php endif; ?>
                                    </label>
                                    <?php endforeach; ?>
                                    <label class="payment-option" style="display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--ml-border); border-radius: 6px; cursor: pointer; font-size: 0.85rem;">
                                        <input type="radio" name="saved_card_id" value="new" checked>
                                        <i class="fas fa-plus-circle" style="font-size: 1.4rem; color: var(--ml-text-muted);"></i>
                                        <span>Usar novo cartão</span>
                                    </label>
                                </div>
                            </div>
                            <?php endif;
                            endif; ?>

                            <div id="ccFieldsNew" <?php echo (!$isGuest && !empty($savedCards)) ? 'style="display:none;"' : ''; ?>>
                            <div class="auth-field"><label class="auth-label" for="cc_name">Nome no Cartão</label><div class="auth-input-wrap"><input type="text" id="cc_name" name="cc_name" required autocomplete="cc-name" placeholder="Como está impresso no cartão"></div></div>
                            <div class="auth-field"><label class="auth-label" for="cc_number">Número do Cartão</label><div class="auth-input-wrap"><input type="text" id="cc_number" required autocomplete="cc-number" placeholder="0000 0000 0000 0000" maxlength="19" inputmode="numeric"></div></div>
                            <div style="display: flex; gap: 10px;">
                                <div class="auth-field" style="flex: 1;"><label class="auth-label" for="cc_exp">Validade</label><div class="auth-input-wrap"><input type="text" id="cc_exp" required placeholder="MM/AA" maxlength="5" inputmode="numeric"></div></div>
                                <div class="auth-field" style="flex: 1;"><label class="auth-label" for="cc_cvv">CVV</label><div class="auth-input-wrap"><input type="text" id="cc_cvv" required autocomplete="cc-csc" placeholder="123" maxlength="4" inputmode="numeric"></div></div>
                            </div>
                            <div class="auth-field"><label class="auth-label" for="cc_cpf">CPF do Titular</label><div class="auth-input-wrap"><input type="text" id="cc_cpf" name="cc_cpf" required placeholder="000.000.000-00" maxlength="14" inputmode="numeric"></div></div>
                            <div class="auth-field"><label class="auth-label" for="cc_installments">Parcelas</label><div class="auth-input-wrap"><select id="cc_installments" name="cc_installments" form="checkoutForm">
                                <?php
                                $maxInstallments = min(12, max(1, (int) floor($grandTotal / 50)));
                                for ($i = 1; $i <= $maxInstallments; $i++):
                                    $val = $grandTotal / $i;
                                ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?>x de R$ <?php echo number_format($val, 2, ',', '.'); ?><?php echo $i === 1 ? ' (à vista)' : ''; ?></option>
                                <?php endfor; ?>
                            </select></div></div>
                            <?php if (!$isGuest): ?>
                            <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 0.82rem; color: var(--ml-text-secondary);">
                                <input type="checkbox" name="save_card" value="1"> Salvar este cartão para próximas compras
                            </label>
                            <?php endif; ?>
                            </div><!-- /ccFieldsNew -->
                            <input type="hidden" id="cc_token" name="cc_token" value="">
                            <input type="hidden" id="cc_brand" name="cc_brand" value="visa">
                            <p id="cc_error" style="color: #c0392b; font-size: 0.82rem; margin-top: 8px; display: none;"></p>
                            <p id="cc_waiting" style="color: var(--ml-text-muted); font-size: 0.82rem; margin-top: 8px; display: none;"><i class="fas fa-spinner fa-spin"></i> Processando pagamento...</p>
                        </div>
                        <?php endif; ?>

                        <p style="margin-bottom: 15px; font-size: 0.85rem; color: var(--ml-text-muted);"><i class="fas fa-info-circle"></i> Ao finalizar, você concorda com nossos termos de compra.</p>
                        <?php if ($isGuest): ?>
                        <p style="margin-bottom: 12px; font-size: 0.85rem; color: var(--ml-text-muted);"><a href="../auth/login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" style="color: var(--ml-accent);"><i class="fas fa-sign-in-alt"></i> Já tem conta? Faça login</a></p>
                        <?php endif; ?>
                        <button type="submit" name="confirm_order" id="btnConfirmOrder" class="ml-btn ml-btn-primary ml-btn-block" style="padding: 14px; font-size: 1.05rem;" <?php echo !$selectedOption ? 'disabled' : ''; ?>><i class="fas fa-check"></i> Confirmar Pedido</button>
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
        // Toggle entre cartão salvo e novo
        document.querySelectorAll('input[name="saved_card_id"]').forEach(function(el) {
            el.addEventListener('change', function() {
                var ccNew = document.getElementById('ccFieldsNew');
                if (ccNew) ccNew.style.display = this.value === 'new' ? '' : 'none';
            });
        });
        // Format CPF (PIX/Boleto shared field)
        var paymentCpf = document.getElementById('payment_cpf');
        if (paymentCpf) {
            paymentCpf.addEventListener('input', function() {
                var v = this.value.replace(/\D/g, '').slice(0, 11);
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                this.value = v;
            });
        }
        </script>

        <?php if ($paymentMethod === 'credit'): ?>
        <!-- Mercado Pago SDK para Checkout Transparente (tokenização do cartão) -->
        <script src="https://sdk.mercadopago.com/js/v2"></script>
        <script>
        (function() {
            var form = document.getElementById('checkoutForm');
            var btnConfirm = document.getElementById('btnConfirmOrder');
            var ccTokenInput = document.getElementById('cc_token');
            var ccError = document.getElementById('cc_error');
            var ccWaiting = document.getElementById('cc_waiting');
            var ccNumber = document.getElementById('cc_number');
            var ccExp = document.getElementById('cc_exp');
            var ccCvv = document.getElementById('cc_cvv');
            var ccName = document.getElementById('cc_name');
            var ccCpf = document.getElementById('cc_cpf');

            // Format card number with spaces
            if (ccNumber) {
                ccNumber.addEventListener('input', function() {
                    var v = this.value.replace(/\D/g, '').slice(0, 16);
                    this.value = v.replace(/(.{4})/g, '$1 ').trim();
                });
            }
            // Format expiry
            if (ccExp) {
                ccExp.addEventListener('input', function() {
                    var v = this.value.replace(/\D/g, '').slice(0, 4);
                    if (v.length >= 2) v = v.slice(0, 2) + '/' + v.slice(2);
                    this.value = v;
                });
            }
            // Format CPF
            if (ccCpf) {
                ccCpf.addEventListener('input', function() {
                    var v = this.value.replace(/\D/g, '').slice(0, 11);
                    v = v.replace(/(\d{3})(\d)/, '$1.$2');
                    v = v.replace(/(\d{3})(\d)/, '$1.$2');
                    v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    this.value = v;
                });
            }

            if (form && btnConfirm && ccTokenInput) {
                form.addEventListener('submit', function(e) {
                    // Se cartão salvo está selecionado, não precisa tokenizar
                    var savedCardRadio = form.querySelector('input[name="saved_card_id"]:checked');
                    if (savedCardRadio && savedCardRadio.value !== 'new') return true;

                    // If already tokenized, allow normal submit
                    if (ccTokenInput.value) return true;

                    // Validate card fields before tokenizing
                    if (!ccName || !ccName.value.trim()) { showError('Informe o nome no cartão.'); return false; }
                    if (!ccNumber || ccNumber.value.replace(/\D/g,'').length < 13) { showError('Número do cartão inválido.'); return false; }
                    if (!ccExp || ccExp.value.length < 5) { showError('Informe a validade (MM/AA).'); return false; }
                    if (!ccCvv || ccCvv.value.length < 3) { showError('Informe o CVV.'); return false; }

                    e.preventDefault();
                    btnConfirm.disabled = true;
                    ccWaiting.style.display = 'block';
                    ccError.style.display = 'none';

                    // Tokenize via Mercado Pago SDK
                    try {
                        var mp = new MercadoPagos("<?php echo htmlspecialchars($mpPublicKey ?? '', ENT_QUOTES, 'UTF-8'); ?>");
                        var expParts = ccExp.value.split('/');
                        var cardTokenParams = {
                            cardholderName: ccName.value.trim(),
                            identificationType: 'CPF',
                            identificationNumber: (ccCpf ? ccCpf.value : '').replace(/\D/g, ''),
                            installments: parseInt(document.getElementById('cc_installments').value) || 1
                        };
                        mp.createCardToken({
                            cardNumber: ccNumber.value.replace(/\s/g, ''),
                            cardExpirationMonth: expParts[0],
                            cardExpirationYear: '20' + expParts[1],
                            securityCode: ccCvv.value,
                            cardholderName: ccName.value.trim(),
                            identificationType: 'CPF',
                            identificationNumber: (ccCpf ? ccCpf.value : '').replace(/\D/g, '')
                        }).then(function(token) {
                            ccTokenInput.value = token.id;
                            var brandInput = document.getElementById('cc_brand');
                            if (brandInput && token.card && token.card.brand) {
                                brandInput.value = token.card.brand;
                            }
                            ccWaiting.style.display = 'none';
                            form.submit();
                        }).catch(function(err) {
                            ccWaiting.style.display = 'none';
                            btnConfirm.disabled = false;
                            showError('Erro ao tokenizar cartão: ' + (err.message || 'Verifique os dados.'));
                        });
                    } catch (err) {
                        ccWaiting.style.display = 'none';
                        btnConfirm.disabled = false;
                        showError('SDK do gateway não disponível. Tente novamente.');
                    }
                    return false;
                });
            }

            function showError(msg) {
                if (ccError) { ccError.textContent = msg; ccError.style.display = 'block'; }
            }
        })();
        </script>
        <?php endif; ?>
    <?php endif; ?>
</div></section>
<?php include $base_path . 'components/footer.php'; ?>