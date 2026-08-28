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

// ponytail: simple CEP-based shipping, no external API call
function calcShipping($cep) {
    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) !== 8) return null;
    $prefix = (int) substr($cep, 0, 3);
    if ($prefix >= 10 && $prefix <= 199) {
        return [
            'pac' => ['method' => 'PAC', 'cost' => 14.90, 'days' => '5-10 úteis'],
            'sedex' => ['method' => 'Sedex', 'cost' => 29.90, 'days' => '1-2 úteis'],
        ];
    } elseif ($prefix >= 1 && $prefix <= 99) {
        return [
            'pac' => ['method' => 'PAC', 'cost' => 9.90, 'days' => '3-7 úteis'],
            'sedex' => ['method' => 'Sedex', 'cost' => 19.90, 'days' => '1 dia útil'],
        ];
    } else {
        return [
            'pac' => ['method' => 'PAC', 'cost' => 24.90, 'days' => '7-15 úteis'],
            'sedex' => ['method' => 'Sedex', 'cost' => 39.90, 'days' => '2-4 úteis'],
        ];
    }
}

$shippingOptions = null;
$selectedShipping = $_POST['shipping_method'] ?? ($_GET['shipping_method'] ?? 'pac');
$shippingCost = 0.00;
$shippingCep = $_POST['shipping_cep'] ?? ($user['postal_code'] ?? '');

if (!empty($shippingCep)) {
    $shippingOptions = calcShipping($shippingCep);
    if ($shippingOptions && isset($shippingOptions[$selectedShipping])) {
        $shippingCost = $subtotal >= 500 ? 0.00 : (float) $shippingOptions[$selectedShipping]['cost'];
    }
}

$paymentMethod = $_POST['payment_method'] ?? 'pix';
$paymentMethods = [
    'pix' => ['label' => 'Pix', 'icon' => 'fa-pix', 'desc' => 'Aprovação instantânea. 5% de desconto!'],
    'boleto' => ['label' => 'Boleto', 'icon' => 'fa-barcode', 'desc' => 'Vencimento em 3 dias úteis.'],
    'credit' => ['label' => 'Cartão de Crédito', 'icon' => 'fa-credit-card', 'desc' => 'Parcele em até 12x.'],
    'delivery' => ['label' => 'Pagar na Entrega', 'icon' => 'fa-money-bill-wave', 'desc' => 'Pague ao receber (dinheiro ou cartão).'],
];

$pixDiscount = 0;
$grandTotal = 0.0;
if ($paymentMethod === 'pix') {
    // Cálculo em centavos (inteiros) para evitar divergência de float
    $shippingCents = (int) round($shippingCost * 100);
    $discountCents = (int) round($subtotalCents * 0.05);
    $grandTotalCents = $subtotalCents + $shippingCents - $discountCents;
    $pixDiscount = $discountCents / 100;
    $grandTotal = $grandTotalCents / 100;
} else {
    $grandTotal = ( ($subtotalCents + (int) round($shippingCost * 100)) ) / 100;
}

$orderCreated = false;
$orderId = null;
$orderPaymentInfo = null;
$errorMessage = null;

$isConfirming = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order']);

if ($isConfirming) {
    csrf_require_valid();

    foreach ($items as $item) {
        $check = validateStock($pdo, (int) $item['product_id'], (int) $item['quantity']);
        if (!$check['ok']) {
            $errorMessage = $check['msg'] . ' Remova o item do carrinho.';
            break;
        }
    }

    if (!$errorMessage) {
        try {
            $pdo->beginTransaction();

            // Snapshot: gateway and tax regime at order creation time
            $taxRegimeSnapshot = store_config('tax_regime') ?: 'CPF';
            $gatewaySnapshot = $gatewayUsed ?? 'mercadopago';
            
            $stmt = $pdo->prepare('INSERT INTO e5_orders (user_id, status, total, shipping_method, shipping_cost, payment_method, gateway_used, payment_status, tax_regime_snapshot, shipping_postal_code, shipping_neighborhood, shipping_city, shipping_state) VALUES (:uid, :status, :total, :ship, :shipcost, :pay, :gateway, :paystatus, :regime, :cep, :neigh, :city, :state)');
            $stmt->execute([
                ':uid' => $userId,
                ':status' => 'pending',
                ':total' => $grandTotal,
                ':ship' => $shippingOptions ? ($shippingOptions[$selectedShipping]['method'] ?? null) : null,
                ':shipcost' => $shippingCost,
                ':pay' => $paymentMethod,
                ':gateway' => $gatewaySnapshot,
                ':paystatus' => $paymentMethod === 'delivery' ? 'pending' : ($paymentMethod === 'pix' ? 'paid' : 'pending'),
                ':regime' => $taxRegimeSnapshot,
                ':cep' => $shippingCep,
                ':neigh' => null,
                ':city' => null,
                ':state' => null,
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

            if ($paymentMethod === 'pix') {
                $orderPaymentInfo = [
                    'method' => 'Pix',
                    'instructions' => 'Escaneie o QR Code abaixo ou copie o código Pix para pagamento.',
                    // ponytail: fake Pix payload, real gateway would generate dynamically
                    'pix_code' => '00020126580014BR.GOV.BCB.PIX0136' . bin2hex(random_bytes(20)) . '5204000053039865406' . number_format($grandTotal, 2, '', '') . '5802BR5913Royal Tech LTDA6009SAO PAULO62070503***6304' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4)),
                    'expires' => date('d/m/Y H:i', strtotime('+30 minutes')),
                ];
            } elseif ($paymentMethod === 'boleto') {
                $orderPaymentInfo = [
                    'method' => 'Boleto',
                    'instructions' => 'Pague o boleto em qualquer banco, casa lotérica ou app até o vencimento.',
                    'boleto_number' => '34191.79001 01043.510047 91020.150008 ' . random_int(100000000, 999999999) . ' ' . random_int(1, 9),
                    'expires' => date('d/m/Y', strtotime('+3 days')),
                ];
            } elseif ($paymentMethod === 'credit') {
                $orderPaymentInfo = [
                    'method' => 'Cartão de Crédito',
                    'instructions' => 'Seu pagamento será processado em até 2 dias úteis.',
                    'installments' => min(12, max(1, floor($grandTotal / 50))) . 'x de R$ ' . number_format($grandTotal / min(12, max(1, floor($grandTotal / 50))), 2, ',', '.'),
                ];
            } else {
                $orderPaymentInfo = [
                    'method' => 'Pagamento na Entrega',
                    'instructions' => 'Pague no momento da entrega. Aceitamos dinheiro, cartão de crédito e débito.',
                ];
            }

            $pdo->commit();
            $orderCreated = true;

            $itemsHtml = '';
            foreach ($items as $it) {
                $itemsHtml .= '<tr><td>' . htmlspecialchars($it['name'] ?? 'Produto', ENT_QUOTES, 'UTF-8') . '</td><td>' . (int)$it['quantity'] . '</td><td>R$ ' . number_format((float)$it['price'], 2, ',', '.') . '</td></tr>';
            }
            $payMethod = ['pix'=>'Pix','boleto'=>'Boleto','credit'=>'Cartão','delivery'=>'Entrega'];
            $body = '<h2>Pedido Confirmado!</h2><p>Olá ' . htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') . ',</p><p>Seu pedido #' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT) . ' foi criado com sucesso.</p><table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;"><thead><tr bgcolor="#d4af37"><th>Produto</th><th>Qtd</th><th>Preço</th></tr></thead><tbody>' . $itemsHtml . '</tbody></table><p><strong>Total:</strong> R$ ' . number_format($grandTotal, 2, ',', '.') . '</p><p><strong>Pagamento:</strong> ' . ($payMethod[$paymentMethod] ?? $paymentMethod) . '</p><p><strong>Frete:</strong> ' . htmlspecialchars($shippingOptions[$selectedShipping]['method'] ?? '—', ENT_QUOTES, 'UTF-8') . '</p><p><a href="https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/TCC_Etec/pages/auth/orders.php" style="display:inline-block;padding:12px 30px;background:#d4af37;color:#1a1a1a;text-decoration:none;font-weight:700;border-radius:30px;">Ver Meus Pedidos</a></p>';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = 'Erro ao processar pedido. Tente novamente.';
            error_log('Checkout error: ' . $e->getMessage());
        }

        if ($orderCreated) {
            // Gerar comprovante de compra (HTML + PDF)
            try {
                $comp = gerarComprovanteCompra($pdo, $orderId, true);
                $compNumero = $comp['numero'];
            } catch (Throwable $e) {
                error_log('Comprovante generation failed: ' . $e->getMessage());
                $compNumero = null;
            }

            // Enviar e-mail com comprovante + PDF anexado
            try {
                enviarComprovanteEmail($pdo, $orderId);
            } catch (Throwable $e) {
                error_log('Checkout comprovante email failed: ' . $e->getMessage());
            }
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
                <?php if (isset($orderPaymentInfo['pix_code'])): ?>
                <div class="payment-code-box">
                    <code id="pixCode"><?php echo htmlspecialchars($orderPaymentInfo['pix_code'], ENT_QUOTES, 'UTF-8'); ?></code>
                    <button type="button" class="ml-btn" onclick="navigator.clipboard.writeText(document.getElementById('pixCode').textContent);this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar Código Pix',2000);" style="margin-top:10px;"><i class="fas fa-copy"></i> Copiar Código Pix</button>
                </div>
                <small style="color: var(--ml-text-muted);">Válido até: <?php echo htmlspecialchars($orderPaymentInfo['expires'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                <?php if (isset($orderPaymentInfo['boleto_number'])): ?>
                <div class="payment-code-box">
                    <code><?php echo htmlspecialchars($orderPaymentInfo['boleto_number'], ENT_QUOTES, 'UTF-8'); ?></code>
                </div>
                <small style="color: var(--ml-text-muted);">Vencimento: <?php echo htmlspecialchars($orderPaymentInfo['expires'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                <?php if (isset($orderPaymentInfo['installments'])): ?>
                <p style="font-size: 1.1rem; color: var(--ml-accent); margin-top: 10px;"><strong><?php echo htmlspecialchars($orderPaymentInfo['installments'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
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
                    <?php if ($shippingOptions): ?>
                    <div class="shipping-options">
                        <?php foreach ($shippingOptions as $key => $opt):
                            $optCost = $subtotal >= 500 ? 0.00 : (float) $opt['cost'];
                        ?>
                        <label class="shipping-option <?php echo $selectedShipping === $key ? 'selected' : ''; ?>">
                            <input type="radio" name="shipping_method" form="checkoutForm" value="<?php echo $key; ?>" <?php echo $selectedShipping === $key ? 'checked' : ''; ?>>
                            <div class="shipping-option-content">
                                <strong><?php echo htmlspecialchars($opt['method'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="shipping-days"><?php echo htmlspecialchars($opt['days'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="shipping-cost"><?php echo $optCost > 0 ? 'R$ ' . number_format($optCost, 2, ',', '.') : '<strong style="color:var(--ml-green);">Grátis</strong>'; ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php elseif (!empty($shippingCep)): ?>
                    <p style="color: var(--ml-text-muted); margin-top: 10px;">CEP não encontrado. Verifique o número.</p>
                    <?php endif; ?>
                    <?php if ($subtotal >= 500): ?>
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
                        <?php foreach ($paymentMethods as $key => $pm): ?>
                        <label class="payment-option <?php echo $paymentMethod === $key ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" form="checkoutForm" value="<?php echo $key; ?>" <?php echo $paymentMethod === $key ? 'checked' : ''; ?>>
                            <div class="payment-option-content">
                                <strong><?php echo htmlspecialchars($pm['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="payment-desc"><?php echo htmlspecialchars($pm['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($key === 'pix'): ?><span class="pix-discount">5% OFF</span><?php endif; ?>
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
                    <p style="color: var(--ml-text-secondary);">CEP: <?php echo htmlspecialchars($user['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
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
                    <?php if ($shippingOptions): $shipDays = $shippingOptions[$selectedShipping]['days'] ?? ''; ?>
                    <div class="ml-summary-line">
                        <span>Frete <?php echo htmlspecialchars($selectedShipping === 'pac' ? 'PAC' : 'Sedex', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo $shippingCost > 0 ? 'R$ ' . number_format($shippingCost, 2, ',', '.') : '<span style="color:var(--ml-green);">Grátis</span>'; ?></span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--ml-text-muted); text-align: right; padding: 2px 0 6px;">Previsão: <?php echo htmlspecialchars($shipDays, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if ($pixDiscount > 0): ?>
                    <div class="ml-summary-line discount">
                        <span>Desconto Pix (5%)</span>
                        <span>- R$ <?php echo number_format($pixDiscount, 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="ml-summary-line total">
                        <span>Total</span>
                        <span>R$ <?php echo number_format($grandTotal, 2, ',', '.'); ?></span>
                    </div>
                    <?php if ($paymentMethod === 'credit' && $grandTotal > 0): $parc = min(12, max(1, floor($grandTotal / 50))); ?>
                    <div style="font-size: 0.85rem; color: var(--ml-text-secondary); text-align: center; padding-top: 10px; border-top: 1px solid var(--ml-border); margin-top: 8px;">
                        ou <strong><?php echo $parc; ?>x de R$ <?php echo number_format($grandTotal / $parc, 2, ',', '.'); ?></strong> sem juros
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="checkoutForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="shipping_cep" value="<?php echo htmlspecialchars($shippingCep, ENT_QUOTES, 'UTF-8'); ?>">
                        <p style="margin-bottom: 15px; font-size: 0.85rem; color: var(--ml-text-muted);"><i class="fas fa-info-circle"></i> Ao finalizar, você concorda com nossos termos de compra.</p>
                        <button type="submit" name="confirm_order" class="ml-btn ml-btn-primary ml-btn-block" style="padding: 14px; font-size: 1.05rem;"><i class="fas fa-check"></i> Confirmar Pedido</button>
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
        </script>
    <?php endif; ?>
</div></section>
<?php include $base_path . 'components/footer.php'; ?>
