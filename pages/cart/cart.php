<?php
$page_title = 'Carrinho - Royal Tech';
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

$userId = (int) $_SESSION['user_id'];
$items = cartGetItems($pdo, $userId);
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
</script>
<?php include $base_path . 'components/footer.php'; ?>
