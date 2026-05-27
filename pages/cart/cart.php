<?php
$page_title = 'Carrinho - Royal Tech';
$show_breadcrumb = true;
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
<section class="section"><div class="container">
    <div class="section-header"><h2>Seu Carrinho</h2><p><?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'itens'; ?></p></div>

    <?php if (empty($items)): ?>
        <div style="text-align:center; padding:60px 20px;">
            <i class="fas fa-shopping-cart" style="font-size:64px; color:var(--color-gold); opacity:0.5; margin-bottom:20px;"></i>
            <h3>Seu carrinho está vazio</h3>
            <p style="margin-bottom:20px;">Explore nossos produtos e encontre o que precisa.</p>
            <a href="../products/products.php" class="btn btn-primary"><i class="fas fa-store"></i> Ver Produtos</a>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead><tr><th>Produto</th><th>Preço</th><th>Qtd</th><th>Subtotal</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($items as $item):
                    $img = (string) ($item['image_path'] ?? '');
                    if ($img === '' || !preg_match('#^(?:https?://|/)#', $img)) {
                        $img = $base_path . 'assets/img/placeholder-product.svg';
                    }
                    $subtotal = (float)$item['price'] * (int)$item['quantity'];
                ?>
                    <tr data-product-id="<?php echo (int)$item['product_id']; ?>">
                        <td style="display:flex; align-items:center; gap:12px;">
                            <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width:60px; height:60px; object-fit:cover; border-radius:6px;">
                            <div>
                                <strong><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <small><?php echo htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                        </td>
                        <td>R$ <?php echo number_format((float)$item['price'], 2, ',', '.'); ?></td>
                        <td>
                            <input type="number" class="cart-qty" value="<?php echo (int)$item['quantity']; ?>" min="0" max="<?php echo (int)$item['stock']; ?>" style="width:60px; padding:4px 8px; text-align:center;">
                        </td>
                        <td class="cart-subtotal">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></td>
                        <td><button class="cart-remove" title="Remover" style="background:none; border:none; color:#f44336; cursor:pointer; font-size:18px;"><i class="fas fa-trash-alt"></i></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" style="text-align:right; font-weight:600; font-size:1.1em;">Total:</td><td class="cart-total" style="font-weight:700; color:var(--color-gold); font-size:1.2em;">R$ <?php echo number_format($total, 2, ',', '.'); ?></td><td></td></tr>
                </tfoot>
            </table>
            <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:20px;">
                <a href="../products/products.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Continuar Comprando</a>
                <a href="../cart/checkout.php" class="btn btn-primary"><i class="fas fa-credit-card"></i> Finalizar Pedido</a>
            </div>
        </div>
    <?php endif; ?>
</div></section>

<script>
document.querySelectorAll('.cart-qty').forEach(input => {
    input.addEventListener('change', function() {
        const productId = this.closest('tr').dataset.productId;
        const qty = parseInt(this.value) || 0;
        fetch('<?php echo $base_path; ?>pages/cart/update.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'product_id=' + productId + '&quantity=' + qty
        }).then(r => r.json()).then(data => {
            if (data.success) location.reload();
        });
    });
});

document.querySelectorAll('.cart-remove').forEach(btn => {
    btn.addEventListener('click', function() {
        const productId = this.closest('tr').dataset.productId;
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
