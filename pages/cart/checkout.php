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

require_once $base_path . 'vendor/autoload.php';
require_once $base_path . 'database/connection.php';
require_once $base_path . 'includes/cart_functions.php';
require_once $base_path . 'includes/coupon_functions.php';
require_once $base_path . 'includes/image_helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../includes/comprovante_functions.php';

use TCC\SuperFreteClient;
use TCC\Exception\SuperFreteException;

function formatCpf(string $cpf): string
{
    $digits = preg_replace('/\D/', '', $cpf);
    if (strlen($digits) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
    }
    return $cpf;
}

function fmtMoney(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

$userId = (int) $_SESSION['user_id'];
$items = cartGetItems($pdo, $userId);

if (empty($items)) {
    header('Location: cart.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

// =====================================================================
// VALORES — subtotais (original, atual, desconto de produto)
// =====================================================================
$subtotal = 0.00;      // subtotal com o preço atual (pós-promoção)
$subtotalOld = 0.00;   // subtotal com o preço original (riscado)
$productDiscount = 0.00;
foreach ($items as $item) {
    $price   = (float) $item['price'];
    $old     = ($item['old_price'] !== null && (float) $item['old_price'] > 0) ? (float) $item['old_price'] : null;
    $qty     = (int) $item['quantity'];
    $unitOld = max($old ?? $price, $price);

    $subtotal       += round($price * $qty, 2);
    $subtotalOld    += round($unitOld * $qty, 2);
    $productDiscount += round(max(0, $unitOld - $price) * $qty, 2);
}

// =====================================================================
// FRETE — cotação real via SuperFrete (/api/v0/calculator)
// Usa products[] para a API calcular a caixa ideal e o preço por CEP.
// =====================================================================
function superfreteCalcShipping(string $cep, array $items): array
{
    $cep = SuperFreteClient::normalizePostalCode($cep);
    if (strlen($cep) !== 8) {
        throw new RuntimeException('CEP inválido para cotação');
    }

    $client = new SuperFreteClient($_ENV);

    $products = [];
    foreach ($items as $item) {
        $qty = max(1, (int) $item['quantity']);
        $usePreset = !empty($item['package_size_id']);

        $hasCustom = fn (mixed $v): bool => trim((string) ($v ?? '')) !== '' && (float) $v > 0;

        $height = $usePreset ? (float) ($item['preset_height_cm'] ?? 15.0)
                            : ($hasCustom($item['height_cm'] ?? null) ? (float) $item['height_cm'] : 15.0);
        $width  = $usePreset ? (float) ($item['preset_width_cm'] ?? 10.0)
                            : ($hasCustom($item['width_cm'] ?? null) ? (float) $item['width_cm'] : 10.0);
        $length = $usePreset ? (float) ($item['preset_length_cm'] ?? 20.0)
                            : ($hasCustom($item['length_cm'] ?? null) ? (float) $item['length_cm'] : 20.0);
        $weight = $hasCustom($item['weight_kg'] ?? null) ? (float) $item['weight_kg']
                     : ($usePreset ? (float) ($item['preset_max_weight_kg'] ?? 0.5) : 0.5);

        $products[] = [
            'quantity' => $qty,
            'height'   => $height,
            'width'    => $width,
            'length'   => $length,
            'weight'   => $weight,
        ];
    }

    $result = $client->calculateShipping([
        'from'     => [
            'postal_code' => SuperFreteClient::normalizePostalCode($_ENV['SUPERFRETE_ORIGIN_POSTAL_CODE'] ?? '01310100'),
        ],
        'to'       => ['postal_code' => $cep],
        'services' => '1,2', // PAC + SEDEX (Correios)
        'options'  => [
            'own_hand' => false,
            'receipt'  => false,
            'insurance_value' => 0,
            'use_insurance_value' => false,
        ],
        'products' => $products,
    ]);

    $options = [];
    foreach ($result as $row) {
        if (empty($row['name']) || !empty($row['error']) || ($row['has_error'] ?? false)) {
            continue; // serviço indisponível para o trecho
        }
        $key = strtolower((string) $row['name']);
        if (!in_array($key, ['pac', 'sedex'], true)) {
            continue; // UI atual só exibe PAC e SEDEX
        }
        $min = $row['delivery_range']['min'] ?? $row['delivery_time'] ?? '';
        $max = $row['delivery_range']['max'] ?? $row['delivery_time'] ?? '';
        $days = ($min === '') ? '' : (($min === $max) ? "$min dia útil" : "$min-$max dias úteis");
        $options[$key] = [
            'method' => $row['name'],
            'cost'   => (float) $row['price'],
            'days'   => $days,
        ];
    }

    if ($options === []) {
        throw new RuntimeException('Nenhuma opção de frete disponível para o CEP informado');
    }

    return $options;
}

// Fallback local (simulado) — só usado se a API SuperFrete falhar/indisponível.
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

// =====================================================================
// ENTRADA DO FORM
// =====================================================================
$shippingType      = ($_POST['shipping_type'] ?? $_GET['shipping_type'] ?? 'entrega') === 'retirada' ? 'retirada' : 'entrega';
$shippingCep       = $_POST['shipping_cep'] ?? ($user['postal_code'] ?? '');
$selectedShipping  = $_POST['shipping_method'] ?? ($_GET['shipping_method'] ?? 'pac');
$couponCode        = mb_strtoupper(trim((string) ($_POST['coupon_code'] ?? '')));
$paymentMethod     = $_POST['payment_method'] ?? ($_GET['payment_method'] ?? 'pix');
$selectedCardId    = (int) ($_POST['card_id'] ?? 0);

// Endereço resolvido via ViaCEP (autopreenchido no cliente e enviado no POST)
$shipAddress = [
    'neighborhood' => trim((string) ($_POST['ship_neighborhood'] ?? '')),
    'city'         => trim((string) ($_POST['ship_city'] ?? '')),
    'state'        => trim((string) ($_POST['ship_state'] ?? '')),
    'street'       => trim((string) ($_POST['ship_street'] ?? '')),
    'number'       => trim((string) ($_POST['ship_number'] ?? '')),
    'complement'   => trim((string) ($_POST['ship_complement'] ?? '')),
];

$removeCoupon = isset($_POST['remove_coupon']);

$shippingOptions = null;
$shippingQuoteError = false;
$shipOriginalCost = 0.00;
$couponError = null;
$couponDiscount = 0.00;
$appliedCoupon = '';

$freeThreshold = (float) (store_config('free_shipping_threshold') ?? 500);

if ($shippingType === 'entrega' && !empty($shippingCep)) {
    try {
        $shippingOptions = superfreteCalcShipping($shippingCep, $items);
    } catch (Throwable $e) {
        $shippingQuoteError = true;
        $shippingOptions = calcShipping($shippingCep);
    }
    if ($shippingOptions) {
        if (!isset($shippingOptions[$selectedShipping])) {
            $selectedShipping = array_key_first($shippingOptions);
        }
        $shipOriginalCost = (float) $shippingOptions[$selectedShipping]['cost'];
    }
}

// =====================================================================
// CUPOM DE DESCONTO
// =====================================================================
if ($removeCoupon) {
    $couponCode = '';
    unset($_SESSION['cart_coupon_code']);
}

// Cupom aplicado no carrinho persiste via sessão quando o form não traz código novo
if ($couponCode === '' && !$removeCoupon && !empty($_SESSION['cart_coupon_code'])) {
    $couponCode = mb_strtoupper(trim((string) $_SESSION['cart_coupon_code']));
}

if ($couponCode !== '') {
    $couponResult = couponApply($pdo, $couponCode, $subtotal, $userId);
    if ($couponResult['ok']) {
        $appliedCoupon = $couponResult['code'];
        $couponDiscount = (float) $couponResult['discount'];
        $_SESSION['cart_coupon_code'] = $appliedCoupon;
    } else {
        $couponError = $couponResult['msg'];
        $couponCode = ''; // código inválido → não mantém no campo
        unset($_SESSION['cart_coupon_code']);
    }
} elseif (!empty($_POST['coupon_code']) && isset($_POST['apply_coupon'])) {
    $couponError = 'Digite um código de cupom.';
}

// =====================================================================
// MEIOS DE PAGAMENTO + CARTÕES SALVOS
// =====================================================================
$pixPercent = max(0, (float) (store_config('pix_discount_percent') ?? 5));
$paymentMethods = [
    'pix'    => ['label' => 'Pix', 'icon' => 'fa-pix', 'desc' => 'Aprovação instantânea. ' . trim(rtrim(rtrim(number_format($pixPercent, 1, ',', '.'), '0'), ',')) . '% de desconto.'],
    'boleto' => ['label' => 'Boleto', 'icon' => 'fa-barcode', 'desc' => 'Vencimento em 3 dias úteis.'],
    'credit' => ['label' => 'Cartão de Crédito', 'icon' => 'fa-credit-card', 'desc' => 'Parcele em até 12x sem juros.'],
    'delivery' => ['label' => 'Pagar na Entrega', 'icon' => 'fa-money-bill-wave', 'desc' => 'Pague ao receber (dinheiro ou cartão).'],
];

// "Pagar na Entrega" só se aplica à retirada/loja; oculta no modo Entrega
if ($shippingType === 'entrega') {
    unset($paymentMethods['delivery']);
    if (!isset($paymentMethods[$paymentMethod])) {
        $paymentMethod = 'pix';
    }
}

$savedCards = $pdo->prepare('SELECT * FROM e5_saved_cards WHERE user_id = :uid AND is_active = 1 ORDER BY id ASC');
$savedCards->execute([':uid' => $userId]);
$savedCards = $savedCards->fetchAll();

$installmentsHint = $savedCards ? (int) max(array_column($savedCards, 'max_installments')) : 12;

if ($paymentMethod === 'credit' && $savedCards) {
    $validIds = array_column($savedCards, 'id');
    if (!in_array($selectedCardId, array_map('intval', $validIds), true)) {
        $selectedCardId = (int) $savedCards[0]['id'];
    }
}
$selectedCard = null;
if ($paymentMethod === 'credit' && $savedCards) {
    foreach ($savedCards as $card) {
        if ((int) $card['id'] === $selectedCardId) {
            $selectedCard = $card;
            break;
        }
    }
}

function cardBrandIcon(string $brand): string
{
    $map = [
        'visa'       => 'fa-brands fa-cc-visa',
        'mastercard' => 'fa-brands fa-cc-mastercard',
        'amex'       => 'fa-brands fa-cc-amex',
        'diners'     => 'fa-brands fa-cc-diners-club',
        'discover'   => 'fa-brands fa-cc-discover',
        'jcb'        => 'fa-brands fa-cc-jcb',
    ];
    $b = strtolower($brand);
    return $map[$b] ?? 'fa-regular fa-credit-card';
}

// =====================================================================
// VALORES FINAIS
// =====================================================================
$promoBase = $subtotal;
$afterCoupon = max(0, $promoBase - $couponDiscount);
$pixDiscount = $paymentMethod === 'pix' ? round($afterCoupon * ($pixPercent / 100), 2) : 0.00;

// Frete: retirada = grátis; entrega no metro livre = grátis (valor original riscado)
$shippingMethodLabel = null;
$shipPaid = 0.00;
$shipSaved = 0.00;
$shipDays = '';
if ($shippingType === 'retirada') {
    $shippingMethodLabel = 'Retirada na Loja';
} elseif ($shippingOptions) {
    $shippingMethodLabel = $shippingOptions[$selectedShipping]['method'] ?? 'Correios';
    $shipDays = $shippingOptions[$selectedShipping]['days'] ?? '';
    $isFree = $subtotalOld >= $freeThreshold;
    $shipPaid = $isFree ? 0.00 : $shipOriginalCost;
    $shipSaved = max(0, $shipOriginalCost - $shipPaid);
}

$grandTotal = round(max(0, $afterCoupon - $pixDiscount) + $shipPaid, 2);
$originalTotal = round($subtotalOld + $shipOriginalCost, 2);
$totalSaved = round(max(0, $originalTotal - $grandTotal), 2);

// Parcelas (somente crédito)
$maxInstallments = $selectedCard ? (int) $selectedCard['max_installments'] : 12;
$installmentCount = $grandTotal > 0 ? min($maxInstallments, max(1, (int) floor($grandTotal / 50))) : 1;
$installmentValue = $installmentCount > 0 ? round($grandTotal / $installmentCount, 2) : 0;

// =====================================================================
// CONFIRMAÇÃO DO PEDIDO
// =====================================================================
$orderCreated = false;
$orderId = null;
$orderPaymentInfo = null;
$errorMessage = null;

$isConfirming = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['confirm_order']);

if ($isConfirming) {
    csrf_require_valid();

    foreach ($items as $item) {
        $check = validateStock($pdo, (int) $item['product_id'], (int) $item['quantity']);
        if (!$check['ok']) {
            $errorMessage = ($item['name'] ?? 'Produto') . ': ' . $check['msg'] . ' Remova o item do carrinho ou reduza a quantidade.';
            break;
        }
        if ((int) $item['stock'] < (int) $item['quantity']) {
            $errorMessage = ($item['name'] ?? 'Produto') . ': apenas ' . (int) $item['stock'] . ' unidade(s) em estoque, mas você pediu ' . (int) $item['quantity'] . '. Reduza a quantidade no carrinho.';
            break;
        }
    }

    if ($couponError) {
        $errorMessage = $errorMessage ?: 'Cupom inválido: ' . $couponError;
    }

    if (!$errorMessage) {
        try {
            $pdo->beginTransaction();

            // Endereço de entrega: ViaCEP (checkout) ou endereço cadastrado
            $shipNeighborhood = $shipAddress['neighborhood'] !== '' ? $shipAddress['neighborhood'] : null;
            $shipCity = $shipAddress['city'] !== '' ? $shipAddress['city'] : null;
            $shipState = $shipAddress['state'] !== '' ? $shipAddress['state'] : null;
            $shipCepDb = null;
            if ($shippingType === 'entrega') {
                $shipCepDb = preg_replace('/\D/', '', $shippingCep);
                if (!$shipNeighborhood && !empty($user['street'])) {
                    $shipNeighborhood = $user['street'];
                }
                if (!$shipCity && !empty($user['postal_code'])) {
                    $shipCity = null;
                }
            }

            $stmt = $pdo->prepare('INSERT INTO e5_orders (user_id, status, total, shipping_method, shipping_cost, payment_method, coupon_code, payment_card_last_four, payment_status, shipping_postal_code, shipping_neighborhood, shipping_city, shipping_state) VALUES (:uid, :status, :total, :ship, :shipcost, :pay, :coupon, :card, :paystatus, :cep, :neigh, :city, :state)');
            $stmt->execute([
                ':uid' => $userId,
                ':status' => 'pending',
                ':total' => $grandTotal,
                ':ship' => $shippingMethodLabel,
                ':shipcost' => $shipPaid,
                ':pay' => $paymentMethod,
                ':coupon' => $appliedCoupon !== '' ? $appliedCoupon : null,
                ':card' => $selectedCard ? $selectedCard['last_four'] : null,
                ':paystatus' => 'pending',
                ':cep' => $shipCepDb,
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
                $stockAffected = decrementStock($pdo, (int) $item['product_id'], (int) $item['quantity']);
                if ($stockAffected <= 0) {
                    throw new RuntimeException('Estoque insuficiente para "' . $item['name'] . '". Reduza a quantidade.');
                }
            }

            if ($appliedCoupon !== '') {
                couponMarkUsed($pdo, $appliedCoupon);
            }

            cartClear($pdo, $userId);

            if ($paymentMethod === 'pix') {
                $orderPaymentInfo = [
                    'method' => 'Pix',
                    'instructions' => 'Escaneie o QR Code abaixo ou copie o código Pix para pagamento.',
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
                    'installments' => $installmentCount . 'x de R$ ' . number_format($installmentValue, 2, ',', '.'),
                ];
            } else {
                $orderPaymentInfo = [
                    'method' => 'Pagamento na Entrega',
                    'instructions' => 'Pague no momento da entrega. Aceitamos dinheiro, cartão de crédito e débito.',
                ];
            }

            $pdo->commit();
            $orderCreated = true;

            $compResult = gerarComprovante($orderId);

            $userEmail = $user['email'];
            if ($userEmail) {
                $emailSent = sendComprovanteEmail($orderId, $userEmail, $compResult['filename'] ?? '');
                $emailStatus = $emailSent ? 'sent' : 'failed';
                salvarStatusEmail($orderId, $emailStatus);
            } else {
                $emailStatus = 'skipped';
                salvarStatusEmail($orderId, $emailStatus);
            }
        } catch (Throwable $e) {
            $pdo->rollBack();
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

                <!-- ============================================================
                     PRODUTOS DA COMPRA (miniaturas no topo da coluna)
                ============================================================ -->
                <div class="ml-card ml-checkout-block ml-products-card<?php echo count($items) === 1 ? ' single-product' : ''; ?>">
                    <div class="ml-collage">
                        <div class="ml-collage-thumbs">
                            <?php foreach (array_slice($items, 0, 4) as $i => $item):
                                $img = renderProductImage((string) ($item['image_path'] ?? ''), $base_path);
                            ?>
                            <img class="ml-collage-thumb" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endforeach; ?>
                            <?php if (count($items) > 4): ?>
                            <span class="ml-collage-more">+<?php echo count($items) - 4; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="ml-collage-info">
                            <p class="ml-collage-title"><?php echo count($items); ?> <?php echo count($items) === 1 ? 'produto' : 'produtos'; ?> na sua compra</p>
                            <button type="button" class="ml-collage-toggle" data-toggle-details><i class="fas fa-chevron-down"></i> <span>Mostrar detalhes</span></button>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     FORMA DE ENTREGA
                ============================================================ -->
                <div class="ml-card ml-checkout-block">
                    <h3 class="ml-block-title"><i class="fas fa-truck"></i> Forma de entrega</h3>

                    <div class="ml-delivery-tabs">
                        <label class="ml-delivery-tab <?php echo $shippingType === 'entrega' ? 'active' : ''; ?>">
                            <input type="radio" name="shipping_type" form="checkoutForm" value="entrega" <?php echo $shippingType === 'entrega' ? 'checked' : ''; ?>>
                            <i class="fas fa-truck"></i> Frete
                        </label>
                        <label class="ml-delivery-tab <?php echo $shippingType === 'retirada' ? 'active' : ''; ?>">
                            <input type="radio" name="shipping_type" form="checkoutForm" value="retirada" <?php echo $shippingType === 'retirada' ? 'checked' : ''; ?>>
                            <i class="fas fa-shop"></i> Retirada
                        </label>
                    </div>

                    <?php if ($shippingType === 'retirada'): ?>
                        <div class="ml-pickup-box">
                            <div class="ml-pickup-head">
                                <span class="ml-pickup-icon"><i class="fas fa-shop"></i></span>
                                <div>
                                    <strong>Retire na loja sem pagar frete</strong>
                                    <p style="color: var(--ml-text-secondary); font-size: 0.9rem; margin-top: 2px;">
                                        <?php echo htmlspecialchars(store_config('store_address') ?? 'Av. Paulista, 1000 - São Paulo, SP', ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                    <p class="ml-pickup-free"><i class="fas fa-check"></i> Sem custo de envio</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>

                        <div class="ml-address-card" id="addressCard">
                            <div class="ml-address-body">
                                <strong class="ml-address-name"><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <div class="ml-address-line">
                                    <i class="fas fa-map-pin" aria-hidden="true"></i>
                                    <div class="ml-address-text">
                                        <p class="ml-address-street" id="deliveryAddressStreet">
                                            <?php if (!empty($user['street'])): ?>
                                                <?php echo htmlspecialchars($user['street'], ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) ($user['number'] ?? 0); ?><?php if ($user['complement']): ?> - <?php echo htmlspecialchars($user['complement'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                            <?php else: ?>
                                                Informe um endereço de entrega
                                            <?php endif; ?>
                                        </p>
                                        <p class="ml-address-region" id="deliveryAddressRegion">
                                            <?php if ($shipAddress['neighborhood'] !== ''): ?>
                                                <?php echo htmlspecialchars($shipAddress['neighborhood'] . ' - ' . $shipAddress['city'] . ' - ' . $shipAddress['state'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php else: ?>
                                                CEP: <span id="deliveryAddressCep"><?php echo htmlspecialchars($shippingCep !== '' ? $shippingCep : ($user['postal_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="ml-address-edit" id="addressEditBtn"><i class="fas fa-pen"></i> Alterar endereço</button>
                        </div>

                        <div class="ml-cep-edit" id="cepEdit" <?php echo $shippingOptions ? 'hidden' : ''; ?>>
                            <label class="auth-label" for="shipping_cep">CEP de entrega</label>
                            <div style="display: flex; gap: 10px; align-items: stretch;">
                                <div class="auth-input-wrap" style="flex: 1;">
                                    <input type="text" id="shipping_cep" name="shipping_cep" form="checkoutForm" value="<?php echo htmlspecialchars($shippingCep, ENT_QUOTES, 'UTF-8'); ?>" placeholder="00000-000" maxlength="9" autocomplete="postal-code" oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{5})(\d)/,'$1-$2')">
                                </div>
                                <button type="submit" form="checkoutForm" class="ml-btn" name="calc_shipping" value="1"><i class="fas fa-search"></i> Calcular</button>
                            </div>
                            <div class="cep-feedback" id="cepFeedback" hidden></div>
                        </div>
                        <?php if ($shippingQuoteError): ?>
                            <div class="auth-feedback auth-feedback-error" style="margin-top:12px;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Ops, estamos passando por complicações técnicas.</strong>
                                Não foi possível calcular o frete online agora — já estamos cuidando disso e a cotação deve voltar em instantes.
                                Enquanto isso, exibimos abaixo um <em>valor estimado</em> para você conseguir seguir com o pedido. Tente novamente mais tarde para confirmar o valor real.
                            </div>
                        <?php endif; ?>

                        <?php if ($shippingOptions): ?>
                        <div class="shipping-options">
                            <?php foreach ($shippingOptions as $key => $opt):
                                $optCost = $subtotalOld >= $freeThreshold ? 0.00 : (float) $opt['cost'];
                            ?>
                            <label class="shipping-option <?php echo $selectedShipping === $key ? 'selected' : ''; ?>">
                                <input type="radio" name="shipping_method" form="checkoutForm" value="<?php echo $key; ?>" <?php echo $selectedShipping === $key ? 'checked' : ''; ?>>
                                <span class="shipping-opt-icon"><i class="fas <?php echo $key === 'sedex' ? 'fa-truck-fast' : 'fa-box'; ?>"></i></span>
                                <span class="shipping-option-content">
                                    <span class="shipping-meta">
                                        <strong><?php echo htmlspecialchars($opt['method'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="shipping-days"><?php echo htmlspecialchars($opt['days'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                    <span class="shipping-cost">
                                        <?php if ($optCost > 0): ?>
                                            <?php echo fmtMoney($optCost); ?>
                                        <?php else: ?>
                                            <span class="ml-ship-free-row"><span class="ml-old-price"><?php echo fmtMoney($opt['cost']); ?></span> <strong class="ml-ship-free-text">Grátis</strong></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php elseif (!empty($shippingCep) && !$shippingQuoteError): ?>
                        <p style="color: var(--ml-text-muted); margin-top: 10px;">CEP não encontrado. Verifique o número.</p>
                        <?php endif; ?>
                        <?php if ($subtotalOld >= $freeThreshold): ?>
                        <p class="ml-ship-free-note"><i class="fas fa-check-circle"></i> Frete grátis: compras acima de R$ <?php echo number_format($freeThreshold, 2, ',', '.'); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- ============================================================
                     MEIOS DE PAGAMENTO
                ============================================================ -->
                <div class="ml-card ml-checkout-block">
                    <h3 class="ml-block-title"><i class="fas fa-credit-card"></i> Meios de pagamento</h3>
                    <div class="payment-options">
                        <?php foreach ($paymentMethods as $key => $pm):
                            $pmIcon = $key === 'pix' ? 'fa-brands fa-pix' : 'fa-solid ' . $pm['icon'];
                        ?>
                        <label class="payment-option <?php echo $paymentMethod === $key ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" form="checkoutForm" value="<?php echo $key; ?>" <?php echo $paymentMethod === $key ? 'checked' : ''; ?>>
                            <span class="payment-icon"><i class="<?php echo $pmIcon; ?>"></i></span>
                            <span class="payment-option-content">
                                <span class="payment-name"><?php echo htmlspecialchars($pm['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="payment-desc"><?php echo htmlspecialchars($pm['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($key === 'pix' && $pixPercent > 0): ?>
                                    <span class="payment-badge ml-badge-gold"><?php echo trim(rtrim(rtrim(number_format($pixPercent, 1, ',', '.'), '0'), ',')); ?>% OFF</span>
                                <?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($paymentMethod === 'credit'): ?>
                        <?php if ($savedCards): ?>
                            <div class="ml-saved-cards">
                                <p class="ml-cards-label"><i class="fas fa-wallet"></i> Seus cartões salvos</p>
                                <?php $visibleCount = min(2, count($savedCards)); ?>
                                <?php foreach ($savedCards as $i => $card): ?>
                                <label class="ml-card-option <?php echo $selectedCardId === (int) $card['id'] ? 'selected' : ''; ?> <?php echo $i >= 2 ? 'ml-card-extra' : ''; ?>">
                                    <input type="radio" name="card_id" form="checkoutForm" value="<?php echo (int) $card['id']; ?>" <?php echo $selectedCardId === (int) $card['id'] ? 'checked' : ''; ?>>
                                    <i class="ml-card-icon <?php echo cardBrandIcon($card['card_brand']); ?>"></i>
                                    <span class="ml-card-num">•••• •••• •••• <?php echo htmlspecialchars($card['last_four'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="ml-card-install"><i class="fas fa-check-circle"></i> Até <?php echo (int) $card['max_installments']; ?>x sem juros</span>
                                </label>
                                <?php endforeach; ?>
                                <?php if (count($savedCards) > 2): ?>
                                <button type="button" class="ml-cards-more-link" id="toggleAllCards"><i class="fas fa-chevron-down" id="toggleAllCardsIcon"></i> <span id="toggleAllCardsText">Mostrar todos os cartões</span></button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--ml-text-secondary); font-size: 0.9rem; margin-top: 12px;">
                                Você ainda não tem cartões salvos.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- ============================================================
                     FATURAMENTO
                ============================================================ -->
                <div class="ml-card ml-checkout-block">
                    <h3 class="ml-block-title"><i class="fas fa-file-invoice"></i> Faturamento</h3>
                    <div class="ml-billing-row">
                        <div>
                            <strong><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p style="color: var(--ml-text-secondary); font-size: 0.9rem; margin-top: 2px;">
                                <?php if (!empty($user['cpf'])): ?>
                                    CPF: <span id="billingCpf" class="ml-tabnum"><?php echo htmlspecialchars(formatCpf($user['cpf']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php else: ?>
                                    CPF: <em style="color: var(--ml-text-muted);">não informado</em>
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="../auth/profile.php" class="ml-link-gold"><i class="fas fa-pen"></i> Alterar</a>
                    </div>
                </div>
            </div>

            <div class="checkout-right">
                <div class="ml-summary-card">

                    <!-- ==================================================
                         RESUMO DA COMPRA — breakdown de preços + botão
                    ================================================== -->
                    <h3 class="ml-summary-title">Resumo da Compra</h3>

                    <!-- ==================================================
                         BREAKDOWN DE PREÇO
                    ================================================== -->
                    <div class="ml-summary-line">
                        <span>Produtos (<?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'itens'; ?>)</span>
                        <span class="ml-tabnum"><?php echo fmtMoney($subtotal); ?></span>
                    </div>

                    <div class="ml-summary-line">
                        <span>Frete<?php if ($shippingMethodLabel): ?> <?php echo htmlspecialchars($shippingMethodLabel, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></span>
                        <span class="ml-tabnum">
                            <?php if ($shipPaid > 0): ?>
                                <?php echo fmtMoney($shipPaid); ?>
                            <?php else: ?>
                                <?php if ($shipOriginalCost > 0): ?><span class="ml-old-price"><?php echo fmtMoney($shipOriginalCost); ?></span> <?php endif; ?>
                                <strong class="ml-ship-free-text">Grátis</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($shipDays !== ''): ?>
                    <div style="font-size: 0.8rem; color: var(--ml-text-muted); text-align: right; padding: 2px 0 6px;">Previsão: <?php echo htmlspecialchars($shipDays, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <!-- Cupom -->
                    <?php if ($appliedCoupon !== ''): ?>
                        <div class="ml-summary-line discount">
                            <span><i class="fas fa-tag"></i> Cupom <?php echo htmlspecialchars($appliedCoupon, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="ml-tabnum">- <?php echo fmtMoney($couponDiscount); ?></span>
                        </div>
                        <div class="ml-coupon-applied-row">
                            <span style="color: var(--ml-green);"><i class="fas fa-check-circle"></i> Cupom aplicado com sucesso!</span>
                            <button type="submit" form="checkoutForm" name="remove_coupon" value="1" class="ml-coupon-remove">Remover</button>
                        </div>
                    <?php else: ?>
                        <button type="button" class="ml-coupon-toggle" id="couponToggle"><i class="fas fa-tag"></i> Inserir código do cupom</button>
                        <div class="ml-coupon-row" id="couponRow" <?php echo ($couponError || !empty($_POST['coupon_code'])) ? '' : 'hidden'; ?>>
                            <div class="auth-input-wrap" style="flex: 1;">
                                <input type="text" id="coupon_code" name="coupon_code" form="checkoutForm" placeholder="CUPOM10" value="<?php echo htmlspecialchars($appliedCoupon, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                            </div>
                            <button type="submit" form="checkoutForm" name="apply_coupon" value="1" class="ml-btn">Aplicar</button>
                        </div>
                        <?php if ($couponError): ?>
                        <p style="color: var(--ml-red); font-size: 0.82rem; margin-top: 6px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($couponError, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($pixDiscount > 0): ?>
                    <div class="ml-summary-line discount">
                        <span>Desconto Pix (<?php echo rtrim(rtrim(number_format($pixPercent, 1, ',', '.'), '0'), ','); ?>%)</span>
                        <span class="ml-tabnum">- <?php echo fmtMoney($pixDiscount); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="ml-summary-line total">
                        <span>Total</span>
                        <span class="ml-summary-total-right ml-tabnum">
                            <?php if ($totalSaved > 0): ?>
                                <span class="ml-old-price ml-total-old"><?php echo fmtMoney($originalTotal); ?></span>
                            <?php endif; ?>
                            <span class="ml-total-final"><?php echo fmtMoney($grandTotal); ?></span>
                        </span>
                    </div>

                    <?php if ($totalSaved > 0): ?>
                    <div class="ml-savings-box">
                        <i class="fas fa-tag"></i>
                        Você economizou <strong class="ml-tabnum"><?php echo fmtMoney($totalSaved); ?></strong> nesta compra
                    </div>
                    <?php endif; ?>

                    <?php if ($paymentMethod === 'credit' && $grandTotal > 0 && !$savedCards): ?>
                    <div style="font-size: 0.85rem; color: var(--ml-text-secondary); text-align: center; padding-top: 10px; border-top: 1px solid var(--ml-border); margin-top: 8px;">
                        ou <strong class="ml-tabnum"><?php echo $installmentCount; ?>x de R$ <?php echo number_format($installmentValue, 2, ',', '.'); ?></strong> sem juros
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="checkoutForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="shipping_cep" value="<?php echo htmlspecialchars($shippingCep, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="coupon_code" value="<?php echo htmlspecialchars($appliedCoupon, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="card_id" value="<?php echo $selectedCardId; ?>">
                        <input type="hidden" name="ship_neighborhood" value="<?php echo htmlspecialchars($shipAddress['neighborhood'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="ship_city" value="<?php echo htmlspecialchars($shipAddress['city'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="ship_state" value="<?php echo htmlspecialchars($shipAddress['state'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="ship_street" value="<?php echo htmlspecialchars($shipAddress['street'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="ship_number" value="<?php echo htmlspecialchars($shipAddress['number'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="ship_complement" value="<?php echo htmlspecialchars($shipAddress['complement'], ENT_QUOTES, 'UTF-8'); ?>">
                        <p style="margin-bottom: 15px; font-size: 0.85rem; color: var(--ml-text-muted);"><i class="fas fa-info-circle"></i> Ao finalizar, você concorda com nossos termos de compra.</p>
                        <button type="submit" name="confirm_order" class="ml-pay-btn"><i class="fas fa-lock"></i> Pagar e finalizar</button>
                    </form>
                    <a href="cart.php" class="ml-btn-back"><i class="fas fa-arrow-left"></i> Voltar ao Carrinho</a>
                </div>
            </div>
        </div>

            <!-- ============================================================
                 MODAL "Mostrar detalhes" da compra
            ============================================================ -->
            <div class="ml-modal" id="detailsModal" hidden>
                <div class="ml-modal-overlay" data-close-modal></div>
                <div class="ml-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="detailsModalTitle">
                    <button type="button" class="ml-modal-close" data-close-modal aria-label="Fechar"><i class="fas fa-times"></i></button>
                    <h3 class="ml-modal-title" id="detailsModalTitle">Detalhes dos produtos</h3>
                    <p class="ml-modal-subtitle"><?php echo count($items); ?> <?php echo count($items) === 1 ? 'produto' : 'produtos'; ?> em 1 envio</p>
                    <div class="ml-modal-body">
                        <div class="ml-modal-ship">
                            <h4 class="ml-modal-ship-title"><i class="fas fa-box"></i> Envio 1</h4>
                            <?php foreach ($items as $item):
                                $imgM = renderProductImage((string) ($item['image_path'] ?? ''), $base_path);
                                $colorM = trim((string) ($item['color'] ?? ''));
                            ?>
                            <div class="ml-modal-item">
                                <img class="ml-modal-item-thumb" src="<?php echo htmlspecialchars($imgM, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="ml-modal-item-info">
                                    <p class="ml-modal-item-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="ml-modal-item-meta">Quantidade: <?php echo (int) $item['quantity']; ?><?php if ($colorM !== ''): ?> – Cor: <?php echo htmlspecialchars($colorM, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></p>
                                </div>
                                <span class="ml-modal-item-price ml-tabnum"><?php echo fmtMoney((float) $item['price'] * (int) $item['quantity']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        <script>
        // =============================================================
        // Card de endereço — abre/edita o CEP
        // =============================================================
        var addressCard = document.getElementById('addressCard');
        var addressEditBtn = document.getElementById('addressEditBtn');
        function toggleCepEdit() {
            if (!cepEdit) return;
            cepEdit.hidden = !cepEdit.hidden;
            if (!cepEdit.hidden && cepInput) { cepInput.focus(); cepInput.select(); }
        }
        if (addressEditBtn) addressEditBtn.addEventListener('click', function(e) { e.stopPropagation(); toggleCepEdit(); });
        if (addressCard) addressCard.addEventListener('click', function() { if (cepEdit) cepEdit.hidden = false; });

        // =============================================================
        // Auto-submit ao trocar meio de entrega / pagamento
        // =============================================================
        ['shipping_type', 'shipping_method', 'payment_method', 'card_id'].forEach(function(name) {
            document.querySelectorAll('input[name="' + name + '"]').forEach(function(el) {
                el.addEventListener('change', function() {
                    var f = this.form;
                    if (f) f.submit();
                });
            });
        });

        // =============================================================
        // ViaCEP — autopreenchimento ao digitar 8 dígitos (PARTE A)
        // =============================================================
        var cepInput = document.getElementById('shipping_cep');
        var cepFeedback = document.getElementById('cepFeedback');
        var cepEdit = document.getElementById('cepEdit');
        var cepTimer = null;
        var cepController = null;
        var fallbackAddress = {
            street: <?php echo json_encode($user['street'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
            number: <?php echo json_encode((string)($user['number'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
            complement: <?php echo json_encode($user['complement'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
            neighborhood: '',
        };

        function showCepFeedback(message, type) {
            if (!cepFeedback) return;
            cepFeedback.textContent = message;
            cepFeedback.className = 'cep-feedback' + (type ? ' ' + type : '');
            cepFeedback.hidden = false;
        }
        function hideCepFeedback() {
            if (!cepFeedback) return;
            cepFeedback.hidden = true;
            cepFeedback.textContent = '';
            cepFeedback.className = 'cep-feedback';
        }
        function setField(name, value) {
            var el = document.querySelector('input[name="' + name + '"]');
            if (el) el.value = value;
        }
        function updateDeliveryAddress(data) {
            var street = document.getElementById('deliveryAddressStreet');
            var cepEl = document.getElementById('deliveryAddressCep');
            var region = document.getElementById('deliveryAddressRegion');
            var cep = (cepInput.value || '').replace(/\D/g, '');
            var addrStreet = (data && data.logradouro ? data.logradouro : fallbackAddress.street) + ', ' + fallbackAddress.number
                            + (fallbackAddress.complement ? ' - ' + fallbackAddress.complement : '');
            if (street) street.textContent = addrStreet;
            if (cepEl) cepEl.textContent = cep;
            if (region) {
                if (data && data.bairro) {
                    region.textContent = data.bairro + ' - ' + data.localidade + ' - ' + data.uf;
                } else if (data && data.localidade) {
                    region.textContent = data.localidade + ' - ' + data.uf;
                } else {
                    region.textContent = '';
                }
            }
        }
        function fillCepHidden(data) {
            setField('ship_neighborhood', data.bairro || '');
            setField('ship_city', data.localidade || '');
            setField('ship_state', data.uf || '');
            setField('ship_street', data.logradouro || fallbackAddress.street);
            setField('ship_number', fallbackAddress.number);
            setField('ship_complement', fallbackAddress.complement);
        }
        function lookupCep(manual) {
            var cep = (cepInput.value || '').replace(/\D/g, '');
            if (cep.length !== 8) {
                hideCepFeedback();
                return;
            }
            if (cepController) cepController.abort();
            cepController = new AbortController();
            var myController = cepController;
            var requestedCep = cep;
            var timeoutId = setTimeout(function() { myController.abort(); }, 6000);
            showCepFeedback('Consultando CEP...', '');
            fetch('https://viacep.com.br/ws/' + cep + '/json/', { signal: myController.signal })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    clearTimeout(timeoutId);
                    if (myController !== cepController) return;
                    if ((cepInput.value || '').replace(/\D/g, '') !== requestedCep) return;
                    if (data.erro) {
                        showCepFeedback('CEP não encontrado. Verifique o número digitado.', 'error');
                        return;
                    }
                    fillCepHidden(data);
                    updateDeliveryAddress(data);
                    showCepFeedback('Endereço encontrado: ' + [data.logradouro, data.bairro, data.localidade].filter(Boolean).join(', ') + (data.uf ? ' - ' + data.uf : ''), 'ok');
                    if (cepEdit) cepEdit.hidden = true;
                    var noOptions = !document.querySelector('.shipping-options');
                    var entregaRadio = document.querySelector('input[name="shipping_type"][value="entrega"]');
                    if (noOptions && entregaRadio && entregaRadio.checked) {
                        var f = document.getElementById('checkoutForm');
                        if (f) setTimeout(function() { f.submit(); }, 60);
                    }
                })
                .catch(function(err) {
                    clearTimeout(timeoutId);
                    if (myController !== cepController) return;
                    if (err && err.name === 'AbortError') {
                        showCepFeedback('A consulta do CEP demorou demais. Verifique o CEP manualmente.', 'error');
                    } else {
                        showCepFeedback('Não foi possível consultar o CEP agora.', 'error');
                    }
                });
        }

        if (cepInput) {
            cepInput.addEventListener('input', function() {
                clearTimeout(cepTimer);
                cepTimer = setTimeout(function() { lookupCep(false); }, 400);
            });
            cepInput.addEventListener('blur', function() {
                clearTimeout(cepTimer);
                lookupCep(false);
            });
        }

        // =============================================================
        // "Mostrar detalhes" da compra — abre o modal de detalhes
        // =============================================================
        var detailsModal = document.getElementById('detailsModal');
        function openDetailsModal() {
            if (!detailsModal) return;
            detailsModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function closeDetailsModal() {
            if (!detailsModal) return;
            detailsModal.hidden = true;
            document.body.style.overflow = '';
        }
        document.querySelectorAll('.ml-collage-toggle[data-toggle-details]').forEach(function(btn) {
            btn.addEventListener('click', openDetailsModal);
        });
        if (detailsModal) {
            detailsModal.querySelectorAll('[data-close-modal]').forEach(function(el) {
                el.addEventListener('click', closeDetailsModal);
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !detailsModal.hidden) closeDetailsModal();
            });
        }

        // =============================================================
        // Cupom (PARTE C) — mostrar/ocultar o campo
        // =============================================================
        var couponToggle = document.getElementById('couponToggle');
        var couponRow = document.getElementById('couponRow');
        if (couponToggle && couponRow) {
            var couponCodeInput = couponRow.querySelector('input');
            var couponClosePending = false;
            function couponSetVisible(visible) {
                couponRow.hidden = !visible;
                var icon = couponToggle.querySelector('i');
                if (icon) icon.style.transform = visible ? 'rotate(180deg)' : '';
                if (visible && couponCodeInput) couponCodeInput.focus();
            }
            couponToggle.addEventListener('click', function() {
                if (couponClosePending) { couponClosePending = false; return; }
                couponSetVisible(couponRow.hidden);
            });
            document.addEventListener('click', function(e) {
                couponClosePending = false;
                if (couponRow.hidden) return;
                if (couponRow.contains(e.target) || couponToggle.contains(e.target)) return;
                couponSetVisible(false);
            });
            if (couponCodeInput) {
                couponCodeInput.addEventListener('blur', function(e) {
                    if (couponCodeInput.value.trim() !== '') return;
                    couponSetVisible(false);
                    if (e.relatedTarget === couponToggle) couponClosePending = true;
                });
            }
        }

        // =============================================================
        // "Mostrar todos os cartões" (PARTE D)
        // =============================================================
        var toggleAllCards = document.getElementById('toggleAllCards');
        if (toggleAllCards) {
            toggleAllCards.addEventListener('click', function() {
                var extras = document.querySelectorAll('.ml-card-extra');
                var showing = false;
                extras.forEach(function(el) {
                    el.hidden = !el.hidden;
                    if (!el.hidden) showing = true;
                });
                var icon = document.getElementById('toggleAllCardsIcon');
                var text = document.getElementById('toggleAllCardsText');
                if (icon) icon.classList.toggle('fa-chevron-down', !showing) && icon.classList.toggle('fa-chevron-up', showing);
                if (text) text.textContent = showing ? 'Ocultar cartões' : 'Mostrar todos os cartões';
            });
        }
        </script>
    <?php endif; ?>
</div></section>

<?php if (!function_exists('formatCpf')) {
    function formatCpf(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf);
        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }
        return $cpf;
    }
} ?>
<?php include $base_path . 'components/footer.php'; ?>