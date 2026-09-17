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
require_once __DIR__ . '/../../includes/order_payment_functions.php';
require_once __DIR__ . '/../../includes/saved_card_functions.php';
require_once __DIR__ . '/../../includes/shipping_functions.php';

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

// =====================================================================
// SELEÇÃO — o carrinho envia only os itens marcados (checkout parcial).
// Se nenhum id for enviado (ex.: "Comprar agora"), assume o carrinho todo.
// =====================================================================
$selectedFlipped = array_flip(array_filter(array_map('intval', (array) ($_POST['selected'] ?? $_GET['selected'] ?? []))));
if ($selectedFlipped !== []) {
    $items = array_values(array_filter($items, function ($it) use ($selectedFlipped) {
        return isset($selectedFlipped[(int) $it['product_id']]);
    }));
}

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
// Cache curto + normalização ficam em includes/shipping_functions.php.
// =====================================================================
function superfreteCalcShipping(string $cep, array $items): array
{
    return shippingQuote($cep, $items);
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

// Resolução servidor-side do CEP (caso o ViaCEP do cliente não tenha sido acionado
// ou o usuário tenha vindo direto no checkout sem digitar o endereço).
if ($shippingType === 'entrega' && $shipAddress['city'] === '') {
    $cepDigitsResolve = preg_replace('/\D/', '', (string) $shippingCep);
    if (strlen($cepDigitsResolve) === 8) {
        $viaRaw = @file_get_contents('https://viacep.com.br/ws/' . $cepDigitsResolve . '/json/');
        $viaData = $viaRaw !== false ? json_decode($viaRaw, true) : null;
        if (is_array($viaData) && empty($viaData['erro'])) {
            if ($shipAddress['neighborhood'] === '') $shipAddress['neighborhood'] = (string) ($viaData['bairro'] ?? '');
            if ($shipAddress['city']        === '') $shipAddress['city']        = (string) ($viaData['localidade'] ?? '');
            if ($shipAddress['state']       === '') $shipAddress['state']       = (string) ($viaData['uf'] ?? '');
            if ($shipAddress['street']      === '') $shipAddress['street']      = (string) ($viaData['logradouro'] ?? '');
        }
    }
}

$removeCoupon = isset($_POST['remove_coupon']);

$shippingOptions = null;
$shippingQuoteError = false;
$shipOriginalCost = 0.00;
$couponError = null;
$couponDiscount = 0.00;
$appliedCoupon = '';

$freeThreshold = (float) (store_config('free_shipping_threshold') ?? 500);
$shippingFallback = false;

if ($shippingType === 'entrega' && !empty($shippingCep)) {
    try {
        $shippingOptions = superfreteCalcShipping($shippingCep, $items);
    } catch (Throwable $e) {
        $shippingQuoteError = true;
        $shippingOptions = null;
        error_log('Frete indisponível (usando fallback): ' . $e->getMessage());
    }
    // Fallback: se a cotação em tempo real falhar, usa o valor/prazo configurados
    // para não bloquear a finalização do pedido (somente para CEP válido).
    $cepDigitsCheck = preg_replace('/\D/', '', (string) $shippingCep);
    if ($shippingOptions === null && strlen($cepDigitsCheck) === 8) {
        $fallbackCost = max(0, (float) (store_config('frete_fallback_cost') ?? 25));
        $fallbackDays = trim((string) (store_config('frete_fallback_days') ?? ''));
        $shippingOptions = [
            'pac' => [
                'method' => 'PAC (estimativa)',
                'cost'   => $fallbackCost,
                'days'   => $fallbackDays,
                'fallback' => true,
            ],
        ];
        $shippingFallback = true;
        $shippingQuoteError = false;
        $selectedShipping = 'pac';
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
// Cartão preenchido na hora do checkout (novo cartão) vs. cartão salvo
$cardNumberRaw = preg_replace('/\D/', '', (string) ($_POST['card_number'] ?? ''));
$newCardActive = $paymentMethod === 'credit' && $cardNumberRaw !== '';

$maxInstallments = 12;
if ($selectedCard && !$newCardActive) {
    $maxInstallments = (int) $selectedCard['max_installments'];
} elseif ($newCardActive) {
    $maxInstallments = max(1, min(12, (int) ($_POST['installments'] ?? 12)));
}

if ($newCardActive) {
    $installmentCount = max(1, min($maxInstallments, (int) ($_POST['installments'] ?? 12)));
} else {
    $installmentCount = $grandTotal > 0 ? min($maxInstallments, max(1, (int) floor($grandTotal / 50))) : 1;
}
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

    if (!$errorMessage && $shippingType === 'entrega') {
        $cepDigitsVal = preg_replace('/\D/', '', (string) $shippingCep);
        if (strlen($cepDigitsVal) !== 8) {
            $errorMessage = $errorMessage ?: 'Informe um CEP válido para entrega.';
        } elseif ($shipAddress['city'] === '') {
            $errorMessage = $errorMessage ?: 'Não foi possível localizar o endereço para o CEP informado. Verifique o CEP.';
        } elseif ($shippingQuoteError) {
            $errorMessage = $errorMessage ?: 'Estamos passando por problemas ao calcular o frete. Tente novamente em instantes.';
        }
    }

    // CPF de faturamento: obrigatório no crédito, opcional nos demais meios
    $checkoutCpfRaw = preg_replace('/\D/', '', (string) ($_POST['cpf'] ?? ''));
    if ($paymentMethod === 'credit' && $checkoutCpfRaw === '') {
        $errorMessage = $errorMessage ?: 'Informe um CPF válido para faturamento.';
    } elseif ($checkoutCpfRaw !== '' && strlen($checkoutCpfRaw) !== 11) {
        $errorMessage = $errorMessage ?: 'CPF de faturamento inválido. Verifique os dígitos.';
    }

    if (!$errorMessage) {
        try {
            $pdo->beginTransaction();

            // Endereço de entrega: ViaCEP (checkout), resolução servidor-side ou endereço cadastrado
            $shipNeighborhood = $shipAddress['neighborhood'] !== '' ? $shipAddress['neighborhood'] : null;
            $shipCity = $shipAddress['city'] !== '' ? $shipAddress['city'] : null;
            $shipState = $shipAddress['state'] !== '' ? $shipAddress['state'] : null;
            $shipCepDb = null;
            if ($shippingType === 'entrega') {
                $shipCepDb = preg_replace('/\D/', '', $shippingCep);
                if ($shipNeighborhood === null && !empty($user['street'])) {
                    $shipNeighborhood = $user['street'];
                }
            }

            // Gerar dados de pagamento (QR Pix, boleto, etc.) ANTES do INSERT para persistir
            $paymentGen = generatePaymentDetails($paymentMethod, $grandTotal);
            $paymentDetailsJson = json_encode($paymentGen['info'], JSON_UNESCAPED_UNICODE);
            $paymentExpiresAt   = $paymentGen['expires_at'];

            // Cartão usado no pedido: salvo (selecionado) ou novo (digitado agora)
            $confirmCardLastFour = $selectedCard ? (string) $selectedCard['last_four'] : null;
            $confirmCardBrand    = $selectedCard ? (string) ($selectedCard['card_brand'] ?? '') : '';
            if ($paymentMethod === 'credit' && $cardNumberRaw !== '') {
                $cardCheck = cardSave($pdo, $userId, $_POST);
                if (!$cardCheck['ok']) {
                    throw new RuntimeException($cardCheck['message']);
                }
                $confirmCardLastFour = substr($cardNumberRaw, -4);
                $confirmCardBrand    = cardDetectBrand($cardNumberRaw);
            }

            // Cartão de crédito: aprovação simulada instantânea
            if ($paymentMethod === 'credit') {
                $orderStatus   = 'paid';
                $orderPayStatus = 'paid';
                $paymentExpiresAt = null;
                $paymentDetailsJson = json_encode([
                    'method'         => 'Cartão de Crédito',
                    'installments'   => $installmentCount . 'x de R$ ' . number_format($installmentValue, 2, ',', '.'),
                    'card_brand'     => $confirmCardBrand,
                    'card_last_four' => $confirmCardLastFour,
                ], JSON_UNESCAPED_UNICODE);
            } else {
                $orderStatus   = 'pending';
                $orderPayStatus = 'pending';
            }

            $stmt = $pdo->prepare('INSERT INTO e5_orders (user_id, status, total, shipping_method, shipping_cost, payment_method, coupon_code, payment_card_last_four, payment_status, payment_details, payment_expires_at, shipping_postal_code, shipping_neighborhood, shipping_city, shipping_state) VALUES (:uid, :status, :total, :ship, :shipcost, :pay, :coupon, :card, :paystatus, :paydet, :payexp, :cep, :neigh, :city, :state)');
            $stmt->execute([
                ':uid'       => $userId,
                ':status'    => $orderStatus,
                ':total'     => $grandTotal,
                ':ship'      => $shippingMethodLabel,
                ':shipcost'  => $shipPaid,
                ':pay'       => $paymentMethod,
                ':coupon'    => $appliedCoupon !== '' ? $appliedCoupon : null,
                ':card'      => $confirmCardLastFour,
                ':paystatus' => $orderPayStatus,
                ':paydet'    => $paymentDetailsJson,
                ':payexp'    => $paymentExpiresAt,
                ':cep'       => $shipCepDb,
                ':neigh'     => $shipNeighborhood,
                ':city'      => $shipCity,
                ':state'     => $shipState,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            // Registrar evento inicial no histórico de status
            orderHistoryAdd($pdo, $orderId, $orderStatus, $orderPayStatus, null, $user['name'] ?? 'Sistema');

            // Salvar endereço de entrega no perfil do usuário (para próximas compras)
            if ($shippingType === 'entrega' && $shipCepDb !== '') {
                $updParts = ['postal_code = :cep'];
                $updParams = [':cep' => $shipCepDb, ':uid' => $userId];
                if ($shipAddress['street'] !== '') {
                    $updParts[] = 'street = :street';
                    $updParams[':street'] = $shipAddress['street'];
                }
                $numClean = preg_replace('/\D/', '', $shipAddress['number']);
                if ($numClean !== '' && (int) $numClean > 0) {
                    $updParts[] = 'number = :number';
                    $updParams[':number'] = (int) $numClean;
                }
                if ($shipAddress['complement'] !== '') {
                    $updParts[] = 'complement = :complement';
                    $updParams[':complement'] = $shipAddress['complement'];
                }
                $pdo->prepare('UPDATE e5_users SET ' . implode(', ', $updParts) . ' WHERE id = :uid')->execute($updParams);
            }

            // Persiste o CPF de faturamento no perfil (se informado e diferente)
            if ($checkoutCpfRaw !== '' && preg_replace('/\D/', '', (string) ($user['cpf'] ?? '')) !== $checkoutCpfRaw) {
                $pdo->prepare('UPDATE e5_users SET cpf = :cpf WHERE id = :id')
                    ->execute([':cpf' => $checkoutCpfRaw, ':id' => $userId]);
            }

            $stmtItem = $pdo->prepare('INSERT INTO e5_order_items (order_id, product_id, quantity, unit_price) VALUES (:oid, :pid, :qty, :price)');
            $orderedProductIds = [];
            foreach ($items as $item) {
                $stmtItem->execute([
                    ':oid'   => $orderId,
                    ':pid'   => (int) $item['product_id'],
                    ':qty'   => (int) $item['quantity'],
                    ':price' => (float) $item['price'],
                ]);
                $stockAffected = decrementStock($pdo, (int) $item['product_id'], (int) $item['quantity']);
                if ($stockAffected <= 0) {
                    throw new RuntimeException('Estoque insuficiente para "' . $item['name'] . '". Reduza a quantidade.');
                }
                $orderedProductIds[] = (int) $item['product_id'];
            }

            if ($appliedCoupon !== '') {
                if (!couponMarkUsed($pdo, $appliedCoupon)) {
                    throw new RuntimeException('Cupom atingiu o limite de usos.');
                }
            }

            // Remove do carrinho apenas os itens comprados; mantém não selecionados e "salvos para depois".
            cartRemoveItems($pdo, $userId, $orderedProductIds);

            $pdo->commit();
            $orderCreated = true;
            $orderPaymentInfo = $paymentGen['info'];

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

            // Fallback: se o comprovante não pôde ser enviado, enfileira um
            // e-mail simples de confirmação para o cliente não ficar sem aviso.
            if ($emailStatus !== 'sent') {
                notificationTrigger('order_created', $orderId, [], $pdo);
            }

            // Redirecionar para a página de pagamento (fluxo realista com countdown/retry)
            header('Location: payment.php?id=' . $orderId);
            exit;
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
                                <strong>Estamos passando por problemas ao calcular o frete.</strong>
                                Não foi possível calcular o valor do frete no momento. Por favor, tente novamente em instantes — já estamos cuidando disso.
                            </div>
                        <?php endif; ?>
                        <?php if ($shippingFallback): ?>
                            <div class="auth-feedback" style="margin-top:12px; background:#fff8ec; color:#8a5a00;">
                                <i class="fas fa-info-circle"></i>
                                <strong>Frete estimado.</strong> A cotação em tempo real está indisponível no momento, então usamos um valor médio. O valor final pode ser ajustado na confirmação do envio.
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

                    <?php if ($paymentMethod === 'credit'):
                        $cardNumberValue = htmlspecialchars(trim((string) ($_POST['card_number'] ?? '')), ENT_QUOTES, 'UTF-8');
                        $cardHolderValue = htmlspecialchars(trim((string) ($_POST['holder_name'] ?? '')), ENT_QUOTES, 'UTF-8');
                        $cardMonthValue  = htmlspecialchars(trim((string) ($_POST['exp_month'] ?? '')), ENT_QUOTES, 'UTF-8');
                        $cardYearValue   = htmlspecialchars(trim((string) ($_POST['exp_year'] ?? '')), ENT_QUOTES, 'UTF-8');
                        $cardNewMarked   = $newCardActive || ($_POST['card_mode'] ?? '') === 'new';
                        $cardOpen        = !$savedCards || $cardNewMarked;
                    ?>
                        <div class="ml-saved-cards">
                            <?php if ($savedCards): ?>
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
                            <?php else: ?>
                                <p style="color: var(--ml-text-secondary); font-size: 0.9rem; margin: 8px 0 10px;">
                                    Você ainda não tem cartões salvos — cadastre um cartão abaixo.
                                </p>
                            <?php endif; ?>

                            <label class="ml-card-option ml-new-card-toggle <?php echo $cardOpen ? 'selected' : ''; ?>">
                                <input type="radio" name="card_mode" value="new" form="checkoutForm" <?php echo $cardNewMarked ? 'checked' : ''; ?>>
                                <i class="ml-card-icon fas fa-plus"></i>
                                <span class="ml-card-num">Usar outro cartão</span>
                            </label>

                            <div class="ml-new-card" id="newCardBox" <?php echo $cardOpen ? '' : 'hidden'; ?>>
                                <div class="ml-new-card-grid">
                                    <label class="ml-new-card-field ml-new-card-full">
                                        <span>Número do cartão</span>
                                        <input type="text" name="card_number" form="checkoutForm" inputmode="numeric" maxlength="19" placeholder="0000 0000 0000 0000" value="<?php echo $cardNumberValue; ?>" oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{4})(?=\d)/g,'$1 ')">
                                    </label>
                                    <label class="ml-new-card-field ml-new-card-full">
                                        <span>Nome impresso no cartão</span>
                                        <input type="text" name="holder_name" form="checkoutForm" maxlength="80" placeholder="Como está no cartão" value="<?php echo $cardHolderValue; ?>">
                                    </label>
                                    <label class="ml-new-card-field">
                                        <span>Validade (MM/AAAA)</span>
                                        <input type="text" name="exp_month" form="checkoutForm" inputmode="numeric" maxlength="2" placeholder="MM" value="<?php echo $cardMonthValue; ?>" oninput="this.value=this.value.replace(/\D/g,'').slice(0,2)">
                                    </label>
                                    <label class="ml-new-card-field">
                                        <span></span>
                                        <input type="text" name="exp_year" form="checkoutForm" inputmode="numeric" maxlength="4" placeholder="AAAA" value="<?php echo $cardYearValue; ?>" oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)">
                                    </label>
                                    <label class="ml-new-card-field">
                                        <span>Código de segurança</span>
                                        <input type="text" name="card_cvc" form="checkoutForm" inputmode="numeric" maxlength="4" placeholder="CVC" value="<?php echo htmlspecialchars(trim((string) ($_POST['card_cvc'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)">
                                    </label>
                                    <label class="ml-new-card-field">
                                        <span>Parcelas</span>
                                        <select name="installments" form="checkoutForm">
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i === $installmentCount ? 'selected' : ''; ?>><?php echo $i; ?>x de R$ <?php echo number_format(round($grandTotal / $i, 2), 2, ',', '.'); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </label>
                                    <label class="ml-new-card-field ml-new-card-full ml-new-card-save">
                                        <input type="checkbox" name="save_card" value="1" form="checkoutForm" <?php echo !empty($_POST['save_card']) ? 'checked' : ''; ?>>
                                        <span>Salvar este cartão na minha conta para as próximas compras</span>
                                    </label>
                                </div>
                            </div>
                        </div>
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
                    <label class="ml-new-card-field ml-new-card-full" style="margin-top:10px;">
                        <span>CPF do titular para faturamento e nota fiscal</span>
                        <?php
                        $checkoutCpfDisplay = preg_replace('/\D/', '', (string) ($_POST['cpf'] ?? ($user['cpf'] ?? '')));
                        if (strlen($checkoutCpfDisplay) === 11) {
                            $checkoutCpfDisplay = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $checkoutCpfDisplay);
                        }
                        ?>
                        <input type="text" name="cpf" form="checkoutForm" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?php echo htmlspecialchars($checkoutCpfDisplay, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value=this.value.replace(/\D/g,'').replace(/^(\d{3})(\d)/,'$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1-$2').slice(0,14)">
                    </label>
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

                    <?php if ($paymentMethod === 'credit' && $grandTotal > 0): ?>
                    <div style="font-size: 0.85rem; color: var(--ml-text-secondary); text-align: center; padding-top: 10px; border-top: 1px solid var(--ml-border); margin-top: 8px;">
                        <strong class="ml-tabnum"><?php echo $installmentCount; ?>x de R$ <?php echo number_format($installmentValue, 2, ',', '.'); ?></strong> sem juros
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="checkoutForm">
                        <?php echo csrf_field(); ?>
                        <?php foreach ($items as $it): ?>
                        <input type="hidden" name="selected[]" value="<?php echo (int) $it['product_id']; ?>">
                        <?php endforeach; ?>
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
                        <button type="submit" name="confirm_order" class="ml-pay-btn" <?php echo $shippingQuoteError ? 'disabled style="opacity:.6;cursor:not-allowed;"' : ''; ?>><i class="fas fa-lock"></i> Pagar e finalizar</button>
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

        <style>
        /* ---- Novo cartão no checkout ---- */
        .ml-new-card-toggle { opacity: .92; margin-top: 4px; }
        .ml-new-card-toggle.selected { border-style: dashed; background: rgba(255, 167, 38, 0.04); }
        .ml-new-card {
            margin-top: 4px;
            padding: 14px;
            background: var(--ml-bg);
            border: 1px solid var(--ml-border);
            border-left: 3px solid var(--ml-accent);
            border-radius: 8px;
        }
        .ml-new-card[hidden] { display: none; }
        .ml-new-card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .ml-new-card-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--ml-text);
        }
        .ml-new-card-field input,
        .ml-new-card-field select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--ml-border);
            border-radius: 6px;
            background: #fff;
            color: var(--ml-text);
            font-size: 0.92rem;
            font-family: var(--font-primary);
            transition: border-color 0.2s;
        }
        .ml-new-card-field input:focus,
        .ml-new-card-field select:focus {
            outline: none;
            border-color: var(--ml-accent);
            box-shadow: 0 0 0 2px rgba(255, 167, 38, 0.15);
        }
        .ml-new-card-full { grid-column: 1 / -1; }
        .ml-new-card-save { flex-direction: row; align-items: center; font-weight: 500; }
        .ml-new-card-save input { width: auto; accent-color: var(--ml-accent); }
        .ml-new-card-field input[type="checkbox"] { width: auto; }
        @media (max-width: 480px) {
            .ml-new-card-grid { grid-template-columns: 1fr; }
            .ml-new-card-full { grid-column: auto; }
        }
        </style>
        <script>
        // =============================================================
        // "Usar outro cartão" — revela/oculta o formulário de novo cartão
        // =============================================================
        (function () {
            var modeNew = document.querySelector('input[name="card_mode"][value="new"]');
            var box = document.getElementById('newCardBox');
            var savedInputs = document.querySelectorAll('input[name="card_id"]');
            if (!modeNew || !box) return;
            function sync() {
                var open = modeNew.checked;
                box.hidden = !open;
                var toggle = modeNew.closest('.ml-new-card-toggle');
                if (toggle) toggle.classList.toggle('selected', open);
                savedInputs.forEach(function (el) {
                    if (el.checked) {
                        var row = el.closest('.ml-card-option');
                        if (row) row.classList.toggle('selected', !open);
                    }
                });
            }
            modeNew.addEventListener('change', sync);
            savedInputs.forEach(function (el) {
                el.addEventListener('change', function () { modeNew.checked = false; sync(); });
            });
            sync();
        })();
        </script>
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