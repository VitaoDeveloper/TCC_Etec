<?php
$page_title = 'Carrinho - Royal Tech';
$breadcrumb_title = 'Carrinho de Compras';
$current_page = 'carrinho';
$base_path = '../../';

session_start();
$isGuest = !isset($_SESSION['user_id']);
require_once $base_path . 'database/connection.php';
require_once $base_path . 'includes/cart_functions.php';

if ($isGuest) {
    $items = sessionCartGetItems($pdo);
} else {
    $userId = (int) $_SESSION['user_id'];
    $items = cartGetItems($pdo, $userId);
}
$total = 0;
foreach ($items as $item) {
    $total += (float) $item['price'] * (int) $item['quantity'];
}

include $base_path . 'components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;"><div class="container">
    <div class="ml-section-header">
        <h2 class="ml-section-title">Seu Carrinho</h2>
        <span class="ml-main-count"><?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'itens'; ?></span>
    </div>

    <?php if (empty($items)): ?>
        <div class="ml-empty">
            <i class="fas fa-shopping-cart"></i>
            <h3>Seu carrinho está vazio</h3>
            <p>Explore nossos produtos e encontre o que precisa.</p>
            <p style="margin-top: 16px;"><a href="../products/products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Ver Produtos</a></p>
        </div>
    <?php else: ?>
        <div class="ml-cart-grid">
            <div>
                <?php foreach ($items as $item):
                    $img = (string) ($item['image_path'] ?? '');
                    if ($img === '') {
                        $img = $base_path . 'assets/img/placeholder-product.svg';
                    } elseif (preg_match('#^/#', $img)) {
                        $img = $base_path . ltrim($img, '/');
                    } elseif (!preg_match('#^https?://#i', $img)) {
                        $img = $base_path . $img;
                    }
                    $subtotal = (float)$item['price'] * (int)$item['quantity'];
                ?>
                <div class="cart-item" data-product-id="<?php echo (int)$item['product_id']; ?>">
                    <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" class="cart-item-img">
                    <div class="cart-item-info">
                        <strong><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="cart-item-brand"><?php echo htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="cart-item-unit">R$ <?php echo number_format((float)$item['price'], 2, ',', '.'); ?> un.</span>
                    </div>
                    <div class="qty-stepper" style="margin-bottom: 0;">
                        <button type="button" class="cart-qty-btn" data-action="dec" aria-label="Diminuir quantidade">−</button>
                        <input type="number" class="cart-qty" value="<?php echo (int)$item['quantity']; ?>" min="0" max="<?php echo (int)$item['stock']; ?>" aria-label="Quantidade">
                        <button type="button" class="cart-qty-btn" data-action="inc" aria-label="Aumentar quantidade">+</button>
                    </div>
                    <span class="cart-item-subtotal cart-subtotal">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                    <button type="button" class="cart-remove" title="Remover" aria-label="Remover item do carrinho"><i class="fas fa-trash-alt"></i></button>
                </div>
                <?php endforeach; ?>
                <a href="../products/products.php" class="ml-btn"><i class="fas fa-arrow-left"></i> Continuar Comprando</a>
            </div>

            <div class="ml-summary-card">
                <h3>Resumo</h3>
                <div class="ml-summary-line total">
                    <span>Total</span>
                    <span>R$ <?php echo number_format($total, 2, ',', '.'); ?></span>
                </div>
                <div id="shipping-estimator" style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--ml-border);">
                    <label for="cartCep" style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 6px;">Calcular frete</label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" id="cartCep" name="cep" inputmode="numeric" maxlength="9" placeholder="00000-000" value="<?php echo htmlspecialchars($cep ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="flex:1; padding:8px; border:1px solid var(--ml-border); border-radius:6px;">
                        <button type="button" id="btnCalcFrete" class="ml-btn" style="padding: 8px 12px;"><i class="fas fa-calculator"></i> Calcular</button>
                    </div>
                    <div id="shippingResult" style="margin-top: 10px; font-size: 0.85rem;"></div>
                </div>
                <div class="ml-summary-actions">
                    <a href="../cart/checkout.php" class="ml-btn ml-btn-primary ml-btn-block"><i class="fas fa-credit-card"></i> Finalizar Pedido</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div></section>

<script>
function updateCartQty(productId, qty) {
    fetch('<?php echo $base_path; ?>pages/cart/update.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + productId + '&quantity=' + qty
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
    });
}

document.querySelectorAll('.cart-qty-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = this.parentElement.querySelector('.cart-qty');
        const productId = this.closest('[data-product-id]').dataset.productId;
        let val = parseInt(input.value) || 0;
        if (this.dataset.action === 'inc') {
            const max = parseInt(input.max) || 999;
            if (val < max) val++;
        } else {
            if (val > 0) val--;
        }
        input.value = val;
        updateCartQty(productId, val);
    });
});

document.querySelectorAll('.cart-qty').forEach(input => {
    input.addEventListener('change', function() {
        const productId = this.closest('[data-product-id]').dataset.productId;
        updateCartQty(productId, parseInt(this.value) || 0);
    });
});

document.querySelectorAll('.cart-remove').forEach(btn => {
    btn.addEventListener('click', function() {
        const productId = this.closest('[data-product-id]').dataset.productId;
        fetch('<?php echo $base_path; ?>pages/cart/remove.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'product_id=' + productId
        }).then(r => r.json()).then(data => {
            if (data.success) location.reload();
        });
    });
});

const btnCalcFrete = document.getElementById('btnCalcFrete');
const cartCep = document.getElementById('cartCep');
const shippingResult = document.getElementById('shippingResult');

function formatCepDigits(v) {
    return v.replace(/\D/g, '').slice(0, 8);
}

if (btnCalcFrete && cartCep) {
    cartCep.addEventListener('input', function() {
        const d = formatCepDigits(this.value);
        this.value = d ? d.replace(/^(\d{5})(\d{0,3}).*/, '$1-$2') : '';
    });

    btnCalcFrete.addEventListener('click', function() {
        const cep = formatCepDigits(cartCep.value);
        if (cep.length !== 8) {
            shippingResult.innerHTML = '<span style="color:#c0392b;">Informe um CEP válido.</span>';
            return;
        }
        btnCalcFrete.disabled = true;
        shippingResult.innerHTML = '<span style="color:var(--ml-text-muted);">Calculando... <i class="fas fa-spinner fa-spin"></i></span>';
        function escHtml(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
        fetch('<?php echo $base_path; ?>pages/cart/shipping-estimate.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'cep=' + encodeURIComponent(cep)
        }).then(r => r.json()).then(data => {
            btnCalcFrete.disabled = false;
            if (!data.success) {
                shippingResult.innerHTML = '<span style="color:#c0392b;">' + escHtml(data.error || 'Não foi possível calcular o frete.') + '</span>';
                return;
            }
            let html = '';
            if (data.warning) {
                html += '<div style="background:rgba(255,152,0,0.12); color:#e65100; padding:6px 8px; border-radius:4px; margin-bottom:8px;">' + escHtml(data.warning) + '</div>';
            }
            if (data.options && data.options.length) {
                data.options.forEach(function(opt) {
                    const est = opt.estimated ? ' <span style="background:rgba(255,152,0,0.2); color:#e65100; padding:1px 5px; border-radius:3px; font-size:0.65rem;">ESTIMADO</span>' : '';
                    html += '<div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed var(--ml-border);">' +
                        '<span><strong>' + escHtml(opt.method) + '</strong>' + est + '<br><small style="color:var(--ml-text-muted);">' + escHtml(opt.days) + '</small></span>' +
                        '<strong>' + (opt.cost > 0 ? 'R$ ' + Number(opt.cost).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : 'Grátis') + '</strong>' +
                        '</div>';
                });
            } else {
                html += '<span style="color:var(--ml-text-muted);">Nenhuma opção disponível para este CEP.</span>';
            }
            shippingResult.innerHTML = html;
        }).catch(function() {
            btnCalcFrete.disabled = false;
            shippingResult.innerHTML = '<span style="color:#c0392b;">Falha ao calcular frete. Tente novamente.</span>';
        });
    });
}
</script>
<?php include $base_path . 'components/footer.php'; ?>
