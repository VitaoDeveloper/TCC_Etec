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
        <span class="ml-main-count" id="cartCount"><?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'itens'; ?></span>
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
            <div id="cartItems">
                <?php
                $freeShippingThreshold = (float) (store_config('free_shipping_threshold') ?: 500);
                $progressPct = min(100, ($total / $freeShippingThreshold) * 100);
                $remaining = max(0, $freeShippingThreshold - $total);
                ?>
                <?php if ($total < $freeShippingThreshold): ?>
                <div style="background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.3); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 0.85rem; font-weight: 600; color: var(--ml-green, #4caf50);">
                            <i class="fas fa-truck"></i> Frete Grátis
                        </span>
                        <span style="font-size: 0.82rem; color: var(--ml-text-secondary);">
                            Faltam <strong>R$ <?php echo number_format($remaining, 2, ',', '.'); ?></strong>
                        </span>
                    </div>
                    <div style="background: var(--ml-border, #e0e0e0); border-radius: 4px; height: 6px; overflow: hidden;">
                        <div style="background: var(--ml-green, #4caf50); height: 100%; width: <?php echo $progressPct; ?>%; border-radius: 4px; transition: width 0.3s;"></div>
                    </div>
                </div>
                <?php else: ?>
                <div style="background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.3); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; text-align: center;">
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--ml-green, #4caf50);">
                        <i class="fas fa-gift"></i> Parabéns! Você ganhou <strong>Frete Grátis</strong>!
                    </span>
                </div>
                <?php endif; ?>

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
                <div class="cart-item" data-product-id="<?php echo (int)$item['product_id']; ?>" data-unit-price="<?php echo (float)$item['price']; ?>" data-stock="<?php echo (int)$item['stock']; ?>">
                    <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" class="cart-item-img" loading="lazy">
                    <div class="cart-item-info">
                        <strong><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="cart-item-brand"><?php echo htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="cart-item-unit">R$ <?php echo number_format((float)$item['price'], 2, ',', '.'); ?> un.</span>
                        <?php if ((int)$item['stock'] > 0 && (int)$item['stock'] <= 5): ?>
                            <span class="cart-item-stock-warning" style="color: var(--ml-orange, #ff9800); font-size: 0.75rem; display: block;">
                                <i class="fas fa-exclamation-triangle"></i> Apenas <?php echo (int)$item['stock']; ?> em estoque
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="qty-stepper">
                        <button type="button" class="cart-qty-btn" data-action="dec" aria-label="Diminuir quantidade">−</button>
                        <input type="number" class="cart-qty" value="<?php echo (int)$item['quantity']; ?>" min="1" max="<?php echo (int)$item['stock']; ?>" aria-label="Quantidade">
                        <button type="button" class="cart-qty-btn" data-action="inc" aria-label="Aumentar quantidade">+</button>
                    </div>
                    <span class="cart-item-subtotal cart-subtotal">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                        <?php if (!$isGuest): ?>
                        <button type="button" class="cart-move-wishlist" data-product-id="<?php echo (int)$item['product_id']; ?>" title="Salvar nos Favoritos" aria-label="Mover para favoritos" style="background:none; border:none; color:var(--ml-text-muted); cursor:pointer; padding:4px; font-size:0.85rem;"><i class="far fa-heart"></i></button>
                        <?php endif; ?>
                        <button type="button" class="cart-remove" title="Remover" aria-label="Remover item do carrinho"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div>
                <a href="../products/products.php" class="ml-btn" style="margin-bottom: 14px;"><i class="fas fa-arrow-left"></i> Continuar Comprando</a>

                <div class="ml-summary-card">
                    <h3>Resumo</h3>

                    <div id="couponBox" style="margin-bottom: 14px; padding: 12px; border: 1px solid var(--ml-border); border-radius: 8px;">
                        <label style="font-size: 0.82rem; font-weight: 600; display: block; margin-bottom: 6px;"><i class="fas fa-tag"></i> Cupom de Desconto</label>
                        <div style="display: flex; gap: 6px;">
                            <input type="text" id="couponCode" placeholder="Código do cupom" style="flex:1; padding:8px; border:1px solid var(--ml-border); border-radius:6px; text-transform:uppercase; font-size:0.85rem;">
                            <a href="#" id="btnApplyCoupon" class="ml-btn" style="padding: 8px 12px; font-size: 0.82rem; white-space:nowrap;"><i class="fas fa-check"></i> Aplicar</a>
                        </div>
                        <div id="couponResult" style="margin-top: 6px; font-size: 0.82rem;"></div>
                    </div>

                    <div class="ml-summary-line total">
                        <span>Total</span>
                        <span id="summaryTotal">R$ <?php echo number_format($total, 2, ',', '.'); ?></span>
                    </div>
                    <div id="shipping-estimator" style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--ml-border);">
                        <label for="cartCep" style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 6px;">Calcular frete</label>
                        <div style="display: flex; gap: 6px;">
                            <input type="text" id="cartCep" name="cep" inputmode="numeric" maxlength="9" placeholder="00000-000" value="<?php echo htmlspecialchars($cep ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="flex:1; padding:8px; border:1px solid var(--ml-border); border-radius:6px;">
                            <button type="button" id="btnCalcFrete" class="ml-btn" style="padding: 8px 12px;"><i class="fas fa-calculator"></i> Calcular</button>
                        </div>
                        <div id="shippingResult" style="margin-top: 10px; font-size: 0.85rem;"></div>
                    </div>
                    <div class="ml-summary-actions" style="margin-top: 14px;">
                        <a href="../cart/checkout.php" class="ml-btn ml-btn-primary ml-btn-block"><i class="fas fa-credit-card"></i> Finalizar Pedido</a>
                        <button type="button" id="btnClearCart" class="ml-btn ml-btn-block" style="margin-top: 8px; color: var(--ml-red, #c0392b);"><i class="fas fa-trash-alt"></i> Limpar Carrinho</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div></section>

<script>
(function() {
    const basePath = <?php echo json_encode($base_path); ?>;
    const cartItemsEl = document.getElementById('cartItems');
    const summaryTotalEl = document.getElementById('summaryTotal');
    const cartCountEl = document.getElementById('cartCount');

    function escHtml(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }

    function showToast(msg, type) {
        if (window.showToast) { window.showToast(msg, type); return; }
        var t = document.createElement('div');
        t.className = 'toast toast-' + (type || 'info');
        t.textContent = msg;
        var c = document.getElementById('toastContainer');
        if (c) { c.appendChild(t); setTimeout(function(){ t.remove(); }, 3500); }
    }

    function recalcTotal() {
        let total = 0;
        cartItemsEl.querySelectorAll('.cart-item').forEach(function(el) {
            const price = parseFloat(el.dataset.unitPrice) || 0;
            const qty = parseInt(el.querySelector('.cart-qty').value) || 0;
            const sub = price * qty;
            el.querySelector('.cart-item-subtotal').textContent = 'R$ ' + sub.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            total += sub;
        });
        summaryTotalEl.textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    }

    function setItemLoading(el, loading) {
        el.style.opacity = loading ? '0.5' : '1';
        el.style.pointerEvents = loading ? 'none' : '';
    }

    function removeItem(productId) {
        const el = cartItemsEl.querySelector('[data-product-id="' + productId + '"]');
        if (el) setItemLoading(el, true);
        fetch(basePath + 'pages/cart/remove.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'product_id=' + productId
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                if (el) {
                    var savedQty = parseInt(el.querySelector('.cart-qty').value) || 1;
                    var savedPrice = parseFloat(el.dataset.unitPrice) || 0;
                    el.style.transition = 'opacity 0.2s, transform 0.2s';
                    el.style.opacity = '0';
                    el.style.transform = 'translateX(30px)';
                    setTimeout(function(){ el.remove(); recalcTotal(); updateCount(data.count); }, 200);
                    showUndoToast(productId, savedQty, savedPrice, data.count);
                } else recalcTotal();
            } else {
                if (el) setItemLoading(el, false);
                showToast(data.message || 'Erro ao remover item.', 'error');
            }
        }).catch(function() {
            if (el) setItemLoading(el, false);
            showToast('Falha de conexão.', 'error');
        });
    }

    function showUndoToast(productId, qty, price, currentCount) {
        var toast = document.createElement('div');
        toast.style.cssText = 'position:fixed; bottom:24px; left:50%; transform:translateX(-50%); background:#333; color:#fff; padding:12px 20px; border-radius:8px; z-index:9999; display:flex; align-items:center; gap:12px; font-size:0.85rem; box-shadow:0 4px 12px rgba(0,0,0,0.3);';
        toast.innerHTML = '<span>Item removido</span><button style="background:var(--ml-accent,#d4af37);color:#000;border:none;padding:4px 12px;border-radius:4px;cursor:pointer;font-weight:600;font-size:0.8rem;">Desfazer</button>';
        document.body.appendChild(toast);
        var timer = setTimeout(function() { toast.remove(); }, 5000);
        toast.querySelector('button').addEventListener('click', function() {
            clearTimeout(timer);
            toast.remove();
            fetch(basePath + 'pages/cart/add.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'product_id=' + productId + '&quantity=' + qty
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) { location.reload(); }
                else showToast(data.message || 'Erro ao desfazer.', 'error');
            });
        });
    }

    function updateQty(productId, qty) {
        const el = cartItemsEl.querySelector('[data-product-id="' + productId + '"]');
        if (el) setItemLoading(el, true);
        fetch(basePath + 'pages/cart/update.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'product_id=' + productId + '&quantity=' + qty
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                recalcTotal();
                updateCount(data.count);
            } else {
                if (el) {
                    setItemLoading(el, false);
                    showToast(data.message || 'Erro ao atualizar quantidade.', 'error');
                }
            }
        }).catch(function() {
            if (el) setItemLoading(el, false);
            showToast('Falha de conexão.', 'error');
        });
    }

    function updateCount(count) {
        if (cartCountEl) cartCountEl.textContent = count + (count === 1 ? ' item' : ' itens');
        const headerBadge = document.querySelector('.ml-cart-link .ml-badge');
        if (headerBadge) { if (count > 0) { headerBadge.textContent = count; } else { headerBadge.remove(); } }
        else if (count > 0) { const b = document.createElement('span'); b.className = 'ml-badge'; b.textContent = count; const link = document.querySelector('.ml-cart-link'); if (link) link.appendChild(b); }
    }

    // Limpar carrinho
    var btnClear = document.getElementById('btnClearCart');
    if (btnClear) {
        btnClear.addEventListener('click', function() {
            if (!confirm('Tem certeza que deseja esvaziar o carrinho?')) return;
            fetch(basePath + 'pages/cart/clear.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: ''
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
                else showToast('Erro ao limpar carrinho.', 'error');
            }).catch(function() { showToast('Falha de conexão.', 'error'); });
        });
    }

    // Event delegation
    if (cartItemsEl) {
        cartItemsEl.addEventListener('click', function(e) {
            const btn = e.target.closest('.cart-qty-btn');
            if (btn) {
                const container = btn.closest('.cart-item');
                const input = container.querySelector('.cart-qty');
                const max = parseInt(input.max) || 999;
                let val = parseInt(input.value) || 1;
                val = btn.dataset.action === 'inc' ? Math.min(val + 1, max) : Math.max(val - 1, 1);
                input.value = val;
                updateQty(container.dataset.productId, val);
                return;
            }
            const wlBtn = e.target.closest('.cart-move-wishlist');
            if (wlBtn) {
                var pid = wlBtn.dataset.productId;
                wlBtn.style.pointerEvents = 'none';
                wlBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                fetch(basePath + 'pages/wishlist/toggle.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'product_id=' + pid
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) {
                        removeItem(pid);
                        showToast('Produto movido para favoritos.', 'success');
                    } else {
                        wlBtn.innerHTML = '<i class="far fa-heart"></i>';
                        wlBtn.style.pointerEvents = '';
                        showToast(data.message || 'Erro ao favoritar.', 'error');
                    }
                }).catch(function() {
                    wlBtn.innerHTML = '<i class="far fa-heart"></i>';
                    wlBtn.style.pointerEvents = '';
                    showToast('Falha de conexão.', 'error');
                });
                return;
            }
            const rm = e.target.closest('.cart-remove');
            if (rm) {
                removeItem(rm.closest('.cart-item').dataset.productId);
            }
        });

        cartItemsEl.addEventListener('change', function(e) {
            if (e.target.classList.contains('cart-qty')) {
                const container = e.target.closest('.cart-item');
                const val = Math.max(1, parseInt(e.target.value) || 1);
                e.target.value = val;
                updateQty(container.dataset.productId, val);
            }
        });
    }

    // Frete
    var btnCalcFrete = document.getElementById('btnCalcFrete');
    var cartCep = document.getElementById('cartCep');
    var shippingResult = document.getElementById('shippingResult');

    if (btnCalcFrete && cartCep) {
        cartCep.addEventListener('input', function() {
            var d = this.value.replace(/\D/g, '').slice(0, 8);
            this.value = d ? d.replace(/^(\d{5})(\d{0,3}).*/, '$1-$2') : '';
        });

        btnCalcFrete.addEventListener('click', function() {
            var cep = cartCep.value.replace(/\D/g, '');
            if (cep.length !== 8) { shippingResult.innerHTML = '<span style="color:#c0392b;">Informe um CEP válido.</span>'; return; }
            btnCalcFrete.disabled = true;
            shippingResult.innerHTML = '<span style="color:var(--ml-text-muted);">Calculando... <i class="fas fa-spinner fa-spin"></i></span>';
            fetch(basePath + 'pages/cart/shipping-estimate.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'cep=' + encodeURIComponent(cep)
            }).then(function(r) { return r.json(); }).then(function(data) {
                btnCalcFrete.disabled = false;
                if (!data.success) { shippingResult.innerHTML = '<span style="color:#c0392b;">' + escHtml(data.error || 'Erro ao calcular frete.') + '</span>'; return; }
                var html = '';
                if (data.warning) html += '<div style="background:rgba(255,152,0,0.12); color:#e65100; padding:6px 8px; border-radius:4px; margin-bottom:8px;">' + escHtml(data.warning) + '</div>';
                if (data.options && data.options.length) {
                    data.options.forEach(function(opt) {
                        var est = opt.estimated ? ' <span style="background:rgba(255,152,0,0.2); color:#e65100; padding:1px 5px; border-radius:3px; font-size:0.65rem;">ESTIMADO</span>' : '';
                        html += '<div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed var(--ml-border);">' +
                            '<span><strong>' + escHtml(opt.method) + '</strong>' + est + '<br><small style="color:var(--ml-text-muted);">' + escHtml(opt.days) + '</small></span>' +
                            '<strong>' + (opt.cost > 0 ? 'R$ ' + opt.cost.toLocaleString('pt-BR', {minimumFractionDigits: 2}) : 'Grátis') + '</strong></div>';
                    });
                } else html += '<span style="color:var(--ml-text-muted);">Nenhuma opção disponível.</span>';
                shippingResult.innerHTML = html;
            }).catch(function() { btnCalcFrete.disabled = false; shippingResult.innerHTML = '<span style="color:#c0392b;">Falha ao calcular frete.</span>'; });
        });
    }

    // Cupom de desconto
    var btnCoupon = document.getElementById('btnApplyCoupon');
    if (btnCoupon) {
        btnCoupon.addEventListener('click', function(e) {
            e.preventDefault();
            var code = document.getElementById('couponCode').value.trim();
            if (!code) { document.getElementById('couponResult').innerHTML = '<span style="color:#c0392b;">Digite um código.</span>'; return; }
            window.location.href = 'checkout.php?coupon_code=' + encodeURIComponent(code);
        });
    }
})();
</script>
<?php include $base_path . 'components/footer.php'; ?>
