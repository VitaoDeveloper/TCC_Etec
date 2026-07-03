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
require_once $base_path . 'includes/cart_functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

$userId = (int) $_SESSION['user_id'];
$items = cartGetItems($pdo, $userId);

if (empty($items)) {
    header('Location: cart.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
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

$pixDiscount = $paymentMethod === 'pix' ? round($subtotal * 0.05, 2) : 0;
$grandTotal = $subtotal + $shippingCost - $pixDiscount;

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

            $stmt = $pdo->prepare('INSERT INTO e5_orders (user_id, status, total, shipping_method, shipping_cost, payment_method, payment_status, shipping_postal_code, shipping_neighborhood, shipping_city, shipping_state) VALUES (:uid, :status, :total, :ship, :shipcost, :pay, :paystatus, :cep, :neigh, :city, :state)');
            $stmt->execute([
                ':uid' => $userId,
                ':status' => 'pending',
                ':total' => $grandTotal,
                ':ship' => $shippingOptions ? ($shippingOptions[$selectedShipping]['method'] ?? null) : null,
                ':shipcost' => $shippingCost,
                ':pay' => $paymentMethod,
                ':paystatus' => $paymentMethod === 'delivery' ? 'pending' : ($paymentMethod === 'pix' ? 'paid' : 'pending'),
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
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errorMessage = 'Erro ao processar pedido. Tente novamente.';
            error_log('Checkout error: ' . $e->getMessage());
        }
    }
}

include $base_path . 'components/header.php';
?>
<section class="section"><div class="container">
    <div class="section-header"><h2>Finalizar Pedido</h2></div>

    <?php if ($orderCreated): ?>
        <div style="text-align:center; padding:60px 20px;">
            <i class="fas fa-check-circle" style="font-size:64px; color:#4caf50; margin-bottom:20px;"></i>
            <h3>Pedido Confirmado!</h3>
            <p>Seu pedido #<?php echo str_pad((string)$orderId, 4, '0', STR_PAD_LEFT); ?> foi criado com sucesso.</p>
            <?php if ($orderPaymentInfo): ?>
            <div class="payment-info-box">
                <h4><i class="fas fa-<?php echo $orderPaymentInfo['method'] === 'Pix' ? 'pix' : ($orderPaymentInfo['method'] === 'Boleto' ? 'barcode' : 'credit-card'); ?>"></i> <?php echo htmlspecialchars($orderPaymentInfo['method'], ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?php echo htmlspecialchars($orderPaymentInfo['instructions'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (isset($orderPaymentInfo['pix_code'])): ?>
                <div class="payment-code-box">
                    <code id="pixCode"><?php echo htmlspecialchars($orderPaymentInfo['pix_code'], ENT_QUOTES, 'UTF-8'); ?></code>
                    <button class="btn btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('pixCode').textContent);this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar',2000);" style="margin-top:10px;"><i class="fas fa-copy"></i> Copiar Código Pix</button>
                </div>
                <small style="color:var(--color-gray);">Válido até: <?php echo htmlspecialchars($orderPaymentInfo['expires'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                <?php if (isset($orderPaymentInfo['boleto_number'])): ?>
                <div class="payment-code-box">
                    <code><?php echo htmlspecialchars($orderPaymentInfo['boleto_number'], ENT_QUOTES, 'UTF-8'); ?></code>
                </div>
                <small style="color:var(--color-gray);">Vencimento: <?php echo htmlspecialchars($orderPaymentInfo['expires'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                <?php if (isset($orderPaymentInfo['installments'])): ?>
                <p style="font-size:1.1rem; color:var(--color-primary);"><strong><?php echo htmlspecialchars($orderPaymentInfo['installments'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <p style="margin-bottom:20px;">Você receberá um e-mail com os detalhes do pedido.</p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <a href="../products/products.php" class="btn btn-primary"><i class="fas fa-store"></i> Continuar Comprando</a>
                <a href="../auth/orders.php" class="btn btn-secondary"><i class="fas fa-list"></i> Meus Pedidos</a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($errorMessage): ?>
            <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="checkout-grid">
            <div class="checkout-left">
                <!-- Shipping -->
                <div class="checkout-section">
                    <h3><i class="fas fa-truck"></i> Frete</h3>
                    <div class="checkout-shipping">
                        <label for="shipping_cep">CEP de entrega</label>
                        <div class="cep-row">
                            <input type="text" id="shipping_cep" name="shipping_cep" form="checkoutForm" value="<?php echo htmlspecialchars($shippingCep, ENT_QUOTES, 'UTF-8'); ?>" placeholder="00000-000" maxlength="9" class="cep-input" oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{5})(\d)/,'$1-$2')">
                            <button type="submit" form="checkoutForm" class="btn btn-secondary" name="calc_shipping" value="1"><i class="fas fa-search"></i> Calcular</button>
                        </div>
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
                                <span class="shipping-cost"><?php echo $optCost > 0 ? 'R$ ' . number_format($optCost, 2, ',', '.') : '<strong style="color:#4caf50;">Grátis</strong>'; ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php elseif (!empty($shippingCep)): ?>
                    <p style="color:var(--color-gray); margin-top:10px;">CEP não encontrado. Verifique o número.</p>
                    <?php endif; ?>
                    <?php if ($subtotal >= 500): ?>
                    <p class="free-shipping-badge"><i class="fas fa-gift"></i> Frete Grátis! Compras acima de R$ 500,00.</p>
                    <?php endif; ?>
                </div>

                <!-- Payment -->
                <div class="checkout-section">
                    <h3><i class="fas fa-credit-card"></i> Pagamento</h3>
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

                <!-- Address -->
                <div class="checkout-section">
                    <h3><i class="fas fa-map-marker-alt"></i> Endereço de Entrega</h3>
                    <p><strong><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <p><?php echo htmlspecialchars($user['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)($user['number'] ?? 0); ?><?php if ($user['complement']): ?> - <?php echo htmlspecialchars($user['complement'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></p>
                    <p>CEP: <?php echo htmlspecialchars($user['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <a href="../auth/profile.php" class="btn btn-secondary" style="font-size:0.85rem; padding:6px 16px; margin-top:8px;"><i class="fas fa-edit"></i> Alterar Endereço</a>
                </div>
            </div>

            <div class="checkout-right">
                <div class="checkout-summary">
                    <h3>Resumo do Pedido</h3>
                    <div class="summary-items">
                        <?php foreach ($items as $item): ?>
                        <div class="summary-item">
                            <span class="summary-item-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?> <small>x<?php echo (int)$item['quantity']; ?></small></span>
                            <span class="summary-item-price">R$ <?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2, ',', '.'); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="summary-totals">
                        <div class="summary-line">
                            <span>Subtotal</span>
                            <span>R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                        </div>
                        <?php if ($shippingCost > 0): ?>
                        <div class="summary-line">
                            <span>Frete (<?php echo htmlspecialchars($selectedShipping === 'pac' ? 'PAC' : 'Sedex', ENT_QUOTES, 'UTF-8'); ?>)</span>
                            <span>R$ <?php echo number_format($shippingCost, 2, ',', '.'); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="summary-line">
                            <span>Frete</span>
                            <span style="color:#4caf50;">Grátis</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($pixDiscount > 0): ?>
                        <div class="summary-line discount">
                            <span>Desconto Pix</span>
                            <span>- R$ <?php echo number_format($pixDiscount, 2, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="summary-line total">
                            <span>Total</span>
                            <span>R$ <?php echo number_format($grandTotal, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <form method="POST" id="checkoutForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="shipping_cep" value="<?php echo htmlspecialchars($shippingCep, ENT_QUOTES, 'UTF-8'); ?>">
                        <p style="margin-bottom:15px; font-size:0.85rem; color:var(--color-gray);"><i class="fas fa-info-circle"></i> Ao finalizar, você concorda com nossos termos de compra.</p>
                        <button type="submit" name="confirm_order" class="btn btn-primary btn-block" style="padding:14px; font-size:1.1rem;"><i class="fas fa-check"></i> Confirmar Pedido</button>
                        <a href="cart.php" class="btn btn-secondary btn-block mt-20"><i class="fas fa-arrow-left"></i> Voltar ao Carrinho</a>
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
