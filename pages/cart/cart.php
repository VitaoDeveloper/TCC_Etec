<?php
$page_title = 'Seu Carrinho - Royal Tech';
$breadcrumb_title = 'Carrinho de Compras';
$current_page = 'carrinho';
$base_path = '../../';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once $base_path . 'database/connection.php';
require_once $base_path . 'includes/cart_functions.php';
require_once $base_path . 'includes/image_helpers.php';
require_once $base_path . 'includes/config.php';
require_once $base_path . 'includes/coupon_functions.php';

$userId = (int) $_SESSION['user_id'];
$items = cartGetItems($pdo, $userId);

$freeThreshold = max(1, (float) (store_config('free_shipping_threshold') ?? 500));

$groups = [];
$totalItems = 0;
$subtotal = 0;
$subtotalOld = 0;
$productDiscounts = 0;
foreach ($items as $item) {
    $seller = trim((string) ($item['brand'] ?? ''));
    if ($seller === '') $seller = 'RoyalTech';
    $linePrice = (float) $item['price'];
    $qty = (int) $item['quantity'];
    $lineTotal = $linePrice * $qty;
    $lineOld = max((float) ($item['old_price'] ?? 0), $linePrice);
    $lineDisc = 0;
    if ($lineOld > $linePrice) {
        $lineDisc = ($lineOld - $linePrice) * $qty;
    }
    $groups[$seller][] = $item + ['line_total' => $lineTotal, 'line_discount' => $lineDisc];
    $totalItems += $qty;
    $subtotal += $lineTotal;
    $subtotalOld += $lineOld * $qty;
    $productDiscounts += $lineDisc;
}

$couponCode = '';
$couponDiscount = 0.0;
if (isset($_SESSION['cart_coupon_code'])) {
    $couponCode = trim((string) $_SESSION['cart_coupon_code']);
    if ($couponCode !== '' && $subtotal > 0) {
        $res = couponApply($pdo, $couponCode, $subtotal, $userId);
        if ($res['ok']) {
            $couponCode = $res['code'];
            $couponDiscount = (float) $res['discount'];

            $stmt = $pdo->prepare('SELECT type, value, max_discount, min_amount FROM e5_coupons WHERE code = :code LIMIT 1');
            $stmt->execute([':code' => $couponCode]);
            $couponRow = $stmt->fetch();
            $_SESSION['cart_coupon_meta'] = $couponRow ? [
                'type'         => $couponRow['type'],
                'value'        => (float) $couponRow['value'],
                'max_discount' => (float) ($couponRow['max_discount'] ?? 0),
                'min_amount'   => (float) ($couponRow['min_amount'] ?? 0),
            ] : null;
        }
    }
}

$freight = $subtotalOld >= $freeThreshold ? 'Grátis' : 'A calcular';
$totalToPay = max(0, $subtotal - $couponDiscount);

$cartIds = array_map(fn($i) => (int) $i['product_id'], $items);
$recommendations = [];
if (!empty($items)) {
    $fetchRecs = function ($catIds, $excludeIds, $limit) use ($pdo) {
        $sql = 'SELECT p.id, p.name, p.price, p.old_price, p.brand, p.stock,
                       (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path,
                       c.name AS category_name
                FROM e5_products p
                LEFT JOIN e5_categories c ON c.id = p.category_id
                WHERE p.stock > 0';
        $params = [];
        if (!empty($catIds)) {
            $in = implode(',', array_fill(0, count($catIds), '?'));
            $sql .= ' AND p.category_id IN (SELECT DISTINCT p2.category_id FROM e5_products p2 WHERE p2.id IN (' . $in . '))';
            array_push($params, ...$catIds);
        }
        if (!empty($excludeIds)) {
            $nx = implode(',', array_fill(0, count($excludeIds), '?'));
            $sql .= ' AND p.id NOT IN (' . $nx . ')';
            array_push($params, ...$excludeIds);
        }
        $sql .= ' ORDER BY p.is_featured DESC, p.id DESC LIMIT ' . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    };

    $recommendations = $fetchRecs($cartIds, $cartIds, 8);
    if (count($recommendations) < 8) {
        $exclude = array_merge($cartIds, array_column($recommendations, 'id'));
        $extra = $fetchRecs([], $exclude, 8 - count($recommendations));
        $recommendations = array_merge($recommendations, $extra);
    }
}

include $base_path . 'components/header.php';
?>
<section class="ml-section" style="padding: 4px 0 24px;">
<div class="container">

<div class="ml-cart-shell">
    <div class="ml-cart-head">
        <h2 class="ml-section-title"><i class="fas fa-shopping-cart" style="color: var(--ml-accent); margin-right: 8px;"></i>Seu Carrinho</h2>
        <span class="ml-cart-count"><strong id="cartCountHeader"><?php echo $totalItems; ?></strong> <?php echo $totalItems === 1 ? 'item' : 'itens'; ?></span>
    </div>

    <?php if (empty($items)): ?>
        <div class="ml-empty">
            <i class="fas fa-shopping-cart"></i>
            <h3>Seu carrinho está vazio</h3>
            <p>Explore nossos produtos e encontre o que precisa.</p>
            <p style="margin-top: 16px;"><a href="../products/products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Ver Produtos</a></p>
        </div>
    <?php else: ?>

    <div class="ml-cart-layout" id="mlCartLayout" data-freeship-threshold="<?php echo (float) $freeThreshold; ?>">

        <!-- ======= COLUNA ESQUERDA — LISTA DE PRODUTOS ======= -->
        <div class="ml-cart-main">
            <div class="ml-cart-selectbar">
                <label class="ml-cbox">
                    <input type="checkbox" id="selectAllItems" checked>
                    <span class="ml-cbox-box" aria-hidden="true"></span>
                </label>
                <span class="ml-cart-select-text">Todos os produtos</span>
                <span class="ml-cart-select-count" id="checkedCount"><?php echo $totalItems; ?> selecionados</span>
            </div>

            <?php foreach ($groups as $seller => $sellerItems):
                $gSub = array_sum(array_map(fn($i) => $i['line_total'], $sellerItems));
                $gDisc = array_sum(array_map(fn($i) => $i['line_discount'], $sellerItems));
                $gSubOld = $gSub + $gDisc;
                $gFree = $gSubOld >= $freeThreshold;
            ?>
            <div class="ml-seller-group" data-seller="<?php echo htmlspecialchars($seller, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="ml-seller-head">
                    <span class="ml-seller-star"><i class="fas fa-store"></i></span>
                    <span>Produtos <strong><?php echo htmlspecialchars($seller, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                    <span class="ml-seller-count"><?php echo count($sellerItems); ?> <?php echo count($sellerItems) === 1 ? 'item' : 'itens'; ?></span>
                </div>

                <div class="ml-seller-list">
                    <?php foreach ($sellerItems as $item):
                        $img = renderProductImage((string) ($item['image_path'] ?? ''), $base_path);
                        $qty = (int) $item['quantity'];
                        $stock = (int) $item['stock'];
                        $price = (float) $item['price'];
                        $old = (float) ($item['old_price'] ?? 0);
                        $hasDisc = $old > $price;
                        $variant = trim((string) ($item['brand'] ?? ''));
                    ?>
                    <div class="ml-item" data-product-id="<?php echo (int) $item['product_id']; ?>"
                         data-price="<?php echo number_format($price, 2, '.', ''); ?>"
                         data-old="<?php echo number_format($old, 2, '.', ''); ?>"
                         data-qty="<?php echo $qty; ?>"
                         data-stock="<?php echo $stock; ?>"
                         data-line="<?php echo number_format($item['line_total'], 2, '.', ''); ?>">
                        <label class="ml-cbox ml-item-cbox">
                            <input type="checkbox" class="item-check" checked>
                            <span class="ml-cbox-box" aria-hidden="true"></span>
                        </label>
                        <a class="ml-item-img" href="../products/product-detail.php?id=<?php echo (int) $item['product_id']; ?>" title="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 onerror="this.onerror=null;this.src='<?php echo $base_path; ?>assets/img/placeholder-product.svg'">
                        </a>
                        <div class="ml-item-body">
                            <a class="ml-item-name" href="../products/product-detail.php?id=<?php echo (int) $item['product_id']; ?>"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php if ($variant !== ''): ?>
                                <span class="ml-item-variant"><i class="fas fa-fill-drip"></i> <?php echo htmlspecialchars($variant, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <span class="ml-item-stock">+<?php echo max(0, $stock - $qty); ?> disponíveis</span>
                            <div class="ml-item-actions">
                                <div class="qty-stepper ml-item-qty">
                                    <button type="button" class="cart-qty-btn" data-action="dec" aria-label="Diminuir quantidade">−</button>
                                    <input type="number" class="cart-qty" value="<?php echo $qty; ?>" min="0" max="<?php echo $stock; ?>" aria-label="Quantidade">
                                    <button type="button" class="cart-qty-btn" data-action="inc" aria-label="Aumentar quantidade">+</button>
                                </div>
                                <button type="button" class="ml-item-wishlist" data-product-id="<?php echo (int) $item['product_id']; ?>" title="Mover para favoritos" aria-label="Mover para favoritos"><i class="far fa-heart"></i></button>
                                <button type="button" class="cart-remove ml-item-remove" title="Remover" aria-label="Remover item do carrinho"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                        <div class="ml-item-price">
                            <?php if ($hasDisc): ?>
                                <span class="ml-item-old">R$ <?php echo number_format($old * $qty, 2, ',', '.'); ?></span>
                            <?php endif; ?>
                            <span class="ml-item-total">R$ <?php echo number_format($item['line_total'], 2, ',', '.'); ?></span>
                            <span class="ml-item-unit">(R$ <?php echo number_format($price, 2, ',', '.'); ?> cada)</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="ml-seller-foot" data-group-total="<?php echo number_format($gSubOld, 2, '.', ''); ?>"
                      data-freeship-needed="<?php echo number_format(max(0, $freeThreshold - $gSubOld), 2, '.', ''); ?>">
                    <div class="ml-freight-line">
                        <span><i class="fas fa-truck"></i> Frete deste vendedor</span>
                        <span class="ml-freight-value"><?php echo $gFree ? 'Grátis' : 'A calcular'; ?></span>
                    </div>
                    <?php if (!$gFree): ?>
                    <div class="ml-freeship-progress">
                        <div class="ml-freeship-bar"><span class="ml-freeship-fill" style="width: <?php echo min(100, round(($gSubOld / $freeThreshold) * 100)); ?>%;"></span></div>
                        <div class="ml-freeship-note">
                            <i class="fas fa-truck"></i>
                            Aproveite o frete grátis adicionando mais produtos
                            <a href="../products/products.php">Ver produtos</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <a href="../products/products.php" class="ml-btn ml-continue-shopping"><i class="fas fa-arrow-left"></i> Continuar Comprando</a>
        </div>

        <!-- ======= COLUNA DIREITA — RESUMO DA COMPRA (sticky) ======= -->
        <aside class="ml-summary" id="mlSummary">
            <h3>Resumo da compra</h3>

            <div class="ml-summary-rows">
                <div class="ml-summary-row">
                    <span id="sumProductsLabel">Produtos (<?php echo $totalItems; ?>)</span>
                    <span class="ml-tnum" id="sumProducts">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                </div>
                <div class="ml-summary-row discount" id="rowCoupon" <?php echo $couponDiscount > 0 ? '' : 'hidden'; ?>>
                    <span>Cupom <?php if ($couponCode !== ''): ?>(<?php echo htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8'); ?>)<?php endif; ?></span>
                    <span class="ml-tnum" id="sumCoupon" data-value="<?php echo number_format($couponDiscount, 2, '.', ''); ?>">− R$ <?php echo number_format($couponDiscount, 2, ',', '.'); ?></span>
                </div>
                <div class="ml-summary-row">
                    <span>Frete</span>
                    <span class="ml-summary-freight" id="sumFreight"><?php echo $freight; ?></span>
                </div>
            </div>

            <div class="ml-coupon-block" id="couponBlock">
                <button type="button" class="ml-coupon-toggle" id="couponToggle"><i class="fas fa-ticket-alt"></i> Inserir código do cupom</button>
                <div class="ml-coupon-form" id="couponForm" <?php echo $couponCode !== '' ? '' : 'hidden'; ?>>
                    <input type="text" id="couponInput" class="ml-coupon-input" placeholder="Ex.: ROYAL10" value="<?php echo htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8'); ?>" maxlength="30">
                    <button type="button" class="ml-coupon-apply" id="couponApply">Aplicar</button>
                </div>
                <div class="ml-coupon-msg" id="couponMsg"></div>
            </div>

            <div class="ml-summary-total">
                <span>Total</span>
                <span class="ml-tnum" id="sumTotal">R$ <?php echo number_format($totalToPay, 2, ',', '.'); ?></span>
            </div>

            <div class="ml-summary-savings" id="sumSavingsWrap" <?php echo ($productDiscounts + $couponDiscount) > 0 ? '' : 'hidden'; ?>>
                <i class="fas fa-tag"></i> Você economiza <span class="ml-tnum" id="sumSavings">R$ <?php echo number_format($productDiscounts + $couponDiscount, 2, ',', '.'); ?></span> nesta compra
            </div>

            <form method="POST" action="checkout.php" id="checkoutGoForm" style="margin:0;">
                 <button type="submit" class="ml-buy-btn" id="buyBtn"><i class="fas fa-credit-card"></i> Continuar</button>
             </form>
            <p class="ml-summary-secure" style="margin-bottom:0;"><i class="fas fa-lock" style="color: var(--ml-accent);"></i> Compra segura com Royal Tech</p>
        </aside>
    </div>

    <?php if (!empty($recommendations)): ?>
    <div class="ml-recommend" style="margin-top: 24px;">
        <div class="ml-section-header" style="margin-bottom: 12px;">
            <h2 class="ml-section-title">Recomendações para você</h2>
            <a href="../products/products.php" class="ml-section-link">Ver todos</a>
        </div>
        <div class="ml-products-grid">
            <?php foreach ($recommendations as $rec): ?>
            <?php
                $product_id = (int) $rec['id'];
                $product_name = $rec['name'];
                $product_price = (float) $rec['price'];
                $product_old_price = $rec['old_price'] !== null ? (float) $rec['old_price'] : null;
                $product_image = (string) ($rec['image_path'] ?? '');
                $product_category = (string) ($rec['category_name'] ?? 'Eletrônicos');
                $product_brand = (string) ($rec['brand'] ?? 'Royal Tech');
                $product_stock = (int) $rec['stock'];
                include $base_path . 'components/product-card.php';
            ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
    </div><!-- /.ml-cart-shell -->
</div>
</section>

<script>
(function () {
    const round2 = v => Math.round((Number(v) || 0) * 100) / 100;
    const fmt = v => 'R$ ' + Number(v).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const $ = s => document.querySelector(s);
    const $$ = (s, ctx) => Array.from((ctx || document).querySelectorAll(s));
    const csrfHeaders = function() {
        var m = document.querySelector('meta[name="csrf-token"]');
        var h = {'Content-Type': 'application/x-www-form-urlencoded'};
        if (m && m.getAttribute('content')) h['X-CSRF-Token'] = m.getAttribute('content');
        return h;
    };
    const cartLayout = document.getElementById('mlCartLayout');
    const threshold = cartLayout ? parseFloat(cartLayout.dataset.freeshipThreshold) || 500 : 500;

    function getCheckedProducts() {
        return $$('.ml-item').filter(it => it.querySelector('.item-check').checked)
                             .map(it => parseInt(it.dataset.productId, 10))
                             .filter(Boolean);
    }

    const groupTotal = g => $$('.ml-item', g).reduce((acc, it) => {
        if (!it.querySelector('.item-check').checked) return acc;
        const qty = parseInt(it.dataset.qty, 10) || 0;
        const price = parseFloat(it.dataset.price) || 0;
        const old = parseFloat(it.dataset.old) || 0;
        return acc + qty * Math.max(price, old);
    }, 0);

    function updateHeaderBadge(qty) {
        const badge = document.querySelector('.ml-cart-link .ml-badge');
        const head = document.getElementById('cartCountHeader');
        if (head) head.textContent = qty;
        if (badge) {
            badge.textContent = qty;
            badge.style.display = qty > 0 ? '' : 'none';
        }
    }

    function showMsg(msg) {
        const el = document.getElementById('couponMsg');
        if (!el) return;
        el.textContent = msg;
        el.className = 'ml-coupon-msg show';
        clearTimeout(showMsg._t);
        showMsg._t = setTimeout(() => { el.className = 'ml-coupon-msg'; }, 4000);
    }

    // Recalcula o desconto do cupom no servidor (chamado após mudar quantidade/seleção)
    function recalcCoupon() {
        const couponEl = document.getElementById('sumCoupon');
        if (!couponEl) return;
        let body = 'action=recalc' + getCheckedProducts().map(id => '&selected[]=' + id).join('');
        fetch('coupon.php', {
            method: 'POST',
            headers: csrfHeaders(),
            body: body
        }).then(r => r.json()).then(res => {
            if (!couponEl.isConnected) return;
            couponEl.dataset.value = res.success && !res.expired ? (res.discount || 0) : 0;
            if (res.expired && res.success) {
                const input = document.getElementById('couponInput');
                if (input) input.value = '';
                showMsg('Este cupom não se aplica mais ao valor atual do carrinho.');
            }
            recalc();
        }).catch(() => recalc());
    }

function recalc() {
        let items = 0, prodTotal = 0, prodTotalOld = 0, discountTotal = 0;
        $$('.ml-item').forEach(it => {
            const qty = parseInt(it.dataset.qty, 10) || 0;
            const price = parseFloat(it.dataset.price) || 0;
            const old = parseFloat(it.dataset.old) || 0;
            const total = round2(qty * price);
            const disc = round2(qty * Math.max(0, old - price));
            it.querySelector('.ml-item-total').textContent = fmt(total);
            const oldEl = it.querySelector('.ml-item-old');
            if (oldEl) oldEl.textContent = 'R$ ' + round2(old * qty).toLocaleString('pt-BR', {minimumFractionDigits: 2});
            it.querySelector('.ml-item-stock').textContent = '+' + Math.max(0, parseInt(it.dataset.stock, 10) - qty) + ' disponíveis';
            if (it.querySelector('.item-check').checked) {
                items += qty;
                prodTotal = round2(prodTotal + total);
                prodTotalOld = round2(prodTotalOld + qty * Math.max(price, old));
                discountTotal = round2(discountTotal + disc);
            }
        });

        $$('.ml-seller-group').forEach(g => {
            const t = groupTotal(g);
            const foot = g.querySelector('.ml-seller-foot');
            const value = g.querySelector('.ml-freight-value');
            const bar = g.querySelector('.ml-freeship-fill');
            if (foot) {
                if (bar) bar.style.width = Math.min(100, Math.round((t / threshold) * 100)) + '%';
                if (value) value.textContent = t >= threshold ? 'Grátis' : 'A calcular';
            }
        });

        const couponEl = document.getElementById('sumCoupon');
        const coupon = couponEl ? round2(parseFloat(couponEl.dataset.value) || 0) : 0;
        const freight = prodTotalOld >= threshold ? 'Grátis' : 'A calcular';
        const total = round2(Math.max(0, prodTotal - coupon));

        document.getElementById('sumProducts').textContent = fmt(prodTotal);
        document.getElementById('sumProductsLabel').textContent = 'Produtos (' + items + ')';

        const cRow = document.getElementById('rowCoupon');
        if (coupon > 0) {
            cRow.hidden = false;
            document.getElementById('sumCoupon').textContent = '− ' + fmt(coupon);
        } else cRow.hidden = true;

        document.getElementById('sumFreight').textContent = freight;
        document.getElementById('sumTotal').textContent = fmt(total);
        const savings = round2(discountTotal + coupon);
        document.getElementById('sumSavings').textContent = fmt(savings);
        document.getElementById('sumSavingsWrap').hidden = savings <= 0;

        const checked = $$('.item-check').filter(c => c.checked).length;
        const all = $$('.item-check');
        const sel = document.getElementById('selectAllItems');
        sel.checked = all.length > 0 && checked === all.length;
        sel.indeterminate = checked > 0 && checked < all.length;
        document.getElementById('checkedCount').textContent = checked + ' selecionado' + (checked === 1 ? '' : 's');
        updateHeaderBadge(items);
    }

    // Qty
    $$('.ml-item-qty .cart-qty-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('.ml-item');
            const input = this.parentElement.querySelector('.cart-qty');
            let val = parseInt(input.value, 10) || 0;
            const max = parseInt(input.max, 10) || 999;
            if (this.dataset.action === 'inc' && val < max) val++;
            else if (this.dataset.action === 'dec' && val > 0) val--;
            else return;
            const pid = row.dataset.productId;
            fetch('update.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: 'product_id=' + pid + '&quantity=' + val
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    row.dataset.qty = val;
                    input.value = val;
                    recalcCoupon();
                } else {
                    showMsg(d.message || 'Não foi possível atualizar.');
                }
            });
        });
    });

    $$('.ml-item-qty .cart-qty').forEach(input => {
        input.addEventListener('change', function () {
            const row = this.closest('.ml-item');
            const max = parseInt(input.max, 10) || 999;
            let val = Math.min(Math.max(0, parseInt(this.value, 10) || 0), max);
            this.value = val;
            const pid = row.dataset.productId;
            fetch('update.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: 'product_id=' + pid + '&quantity=' + val
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    row.dataset.qty = val;
                    recalcCoupon();
                } else {
                    showMsg(d.message || 'Não foi possível atualizar.');
                }
            });
        });
    });

    // Remove
    $$('.ml-item-remove').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('.ml-item');
            const pid = row.dataset.productId;
            fetch('remove.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: 'product_id=' + pid
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    row.style.transition = 'opacity .3s, transform .3s';
                    row.style.opacity = '0';
                    row.style.transform = 'scale(.97)';
                    setTimeout(() => {
                        row.remove();
                        $$('.ml-seller-group').forEach(g => {
                            if (!g.querySelector('.ml-item')) g.remove();
                        });
                        if (!document.querySelector('#mlCartLayout .ml-seller-group')) {
                            location.reload();
                        } else {
                            recalcCoupon();
                        }
                    }, 300);
                }
            });
        });
    });

    // Mover para favoritos
    $$('.ml-item-wishlist').forEach(btn => {
        btn.addEventListener('click', function () {
            const self = this;
            const row = this.closest('.ml-item');
            const pid = row.dataset.productId;
            self.disabled = true;
            fetch('../wishlist/toggle.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: 'product_id=' + pid
            }).then(r => r.json()).then(d => {
                if (!d.success) {
                    self.disabled = false;
                    showMsg(d.message || 'Não foi possível favoritar.');
                    return;
                }
                const badge = document.querySelector('.ml-wishlist-link .ml-badge, .wishlist-btn .cart-badge');
                if (badge) badge.textContent = d.count;
                return fetch('remove.php', {
                    method: 'POST',
                    headers: csrfHeaders(),
                    body: 'product_id=' + pid
                }).then(r => r.json()).then(rd => {
                    if (!rd.success) {
                        self.disabled = false;
                        showMsg(rd.message || 'Erro ao remover do carrinho.');
                        return;
                    }
                    showMsg('Movido para favoritos.');
                    row.style.transition = 'opacity .3s, transform .3s';
                    row.style.opacity = '0';
                    row.style.transform = 'scale(.97)';
                    setTimeout(() => {
                        row.remove();
                        $$('.ml-seller-group').forEach(g => {
                            if (!g.querySelector('.ml-item')) g.remove();
                        });
                        if (!document.querySelector('#mlCartLayout .ml-seller-group')) {
                            location.reload();
                        } else {
                            recalcCoupon();
                        }
                    }, 300);
                });
            }).catch(() => {
                self.disabled = false;
                showMsg('Erro ao mover para favoritos.');
            });
        });
    });

    // Select All
    const selAll = document.getElementById('selectAllItems');
    if (selAll) selAll.addEventListener('change', function () {
        $$('.item-check').forEach(c => { c.checked = selAll.checked; });
        recalcCoupon();
    });
    $$('.item-check').forEach(c => c.addEventListener('change', recalcCoupon));

    // Coupon
    const couponToggle = document.getElementById('couponToggle');
    if (couponToggle) couponToggle.addEventListener('click', () => {
        document.getElementById('couponForm').hidden = false;
        couponToggle.style.display = 'none';
        document.getElementById('couponInput').focus();
    });
    const applyCoupon = () => {
        const input = document.getElementById('couponInput');
        const code = input.value.trim();
        if (!code) { showMsg('Digite um código de cupom.'); return; }
        let body = 'code=' + encodeURIComponent(code) + getCheckedProducts().map(id => '&selected[]=' + id).join('');
        fetch('coupon.php', {
            method: 'POST',
            headers: csrfHeaders(),
            body: body
        }).then(r => r.json()).then(d => {
            if (d.success) {
                input.value = d.code;
                document.getElementById('sumCoupon').dataset.value = d.discount;
                const l = document.querySelector('#rowCoupon span:first-child');
                if (l) l.textContent = 'Cupom (' + d.code + ')';
                showMsg('Cupom aplicado! Você economiza ' + fmt(d.discount) + '.');
                recalc();
            } else {
                showMsg(d.message || 'Cupom inválido.');
            }
        });
    };
    const apBtn = document.getElementById('couponApply');
    if (apBtn) apBtn.addEventListener('click', applyCoupon);
    const cpIn = document.getElementById('couponInput');
    if (cpIn) cpIn.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); } });

    // Ir para o checkout com apenas os produtos selecionados
    const checkoutGoForm = document.getElementById('checkoutGoForm');
    if (checkoutGoForm) checkoutGoForm.addEventListener('submit', function(e) {
        const selected = getCheckedProducts();
        if (selected.length === 0) {
            e.preventDefault();
            showMsg('Selecione ao menos um produto para continuar.');
            return;
        }
        checkoutGoForm.querySelectorAll('input[name="selected[]"]').forEach(el => el.remove());
        selected.forEach(function(id) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'selected[]';
            inp.value = id;
            checkoutGoForm.appendChild(inp);
        });
    });

    recalc();
})();
</script>
<?php include $base_path . 'components/footer.php'; ?>