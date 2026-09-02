<?php
/**
 * Componente de Cartão de Produto — Estilo Mercado Livre
 * 
 * Variáveis esperadas:
 * - $product_id: ID do produto
 * - $product_name: Nome do produto
 * - $product_price: Preço do produto
 * - $product_old_price: Preço anterior (para mostrar desconto)
 * - $product_image: URL da imagem
 * - $product_category: Categoria do produto
 * - $product_brand: Marca do produto
 * - $product_installments: Parcelamento
 * - $product_is_new: Se é produto novo (boolean)
 * - $product_is_featured: Se é produto em destaque (boolean)
 * - $product_stock: Quantidade em estoque (int)
 */

$_pid = (int) ($product_id ?? 0);
$_pname = $product_name ?? 'Produto';
$_pprice = (float) ($product_price ?? 0);
$_pold = isset($product_old_price) ? (float) $product_old_price : null;
$_pimage = $product_image ?? '';
$_pcat = $product_category ?? 'Eletrônicos';
$_pbrand = $product_brand ?? 'Royal Tech';
$_pstock = isset($product_stock) ? (int) $product_stock : 1;
$_pis_new = !empty($product_is_new);
$_pis_feat = !empty($product_is_featured);

$base = $base_path ?? '';
require_once __DIR__ . '/../includes/image_helpers.php';
$imageCandidate = renderProductImage((string) $_pimage, $base);

$_discount = 0;
if ($_pold !== null && $_pold > $_pprice && $_pold > 0) {
    $_discount = round((($_pold - $_pprice) / $_pold) * 100);
}

$_pix_price = round($_pprice * 0.95, 2);
$_freeShipping = $_pprice >= 500;
$_installmentValue = $_pprice > 0 ? round($_pprice / 12, 2) : 0;
$_outOfStock = $_pstock <= 0;
?>

<article class="ml-product-card" data-product-id="<?php echo $_pid; ?>">
    <div class="ml-card-image">
        <?php if ($_discount > 0): ?>
            <span class="ml-card-discount">-<?php echo $_discount; ?>%</span>
        <?php endif; ?>

        <?php if ($_freeShipping && !$_outOfStock): ?>
            <span class="ml-card-freeship">Frete grátis</span>
        <?php endif; ?>

        <button class="ml-card-wishlist js-require-auth" data-auth-target="favoritos"
                data-product-id="<?php echo $_pid; ?>" title="Favoritar" aria-label="Favoritar produto">
            <i class="far fa-heart"></i>
        </button>

        <a href="<?php echo $base; ?>pages/products/product-detail.php?id=<?php echo $_pid; ?>">
            <img src="<?php echo htmlspecialchars($imageCandidate, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo htmlspecialchars($_pname, ENT_QUOTES, 'UTF-8'); ?>"
                 loading="lazy"
                 onerror="this.onerror=null;this.src='<?php echo $base; ?>assets/img/placeholder-product.svg'">
        </a>
    </div>

    <div class="ml-card-body">
        <?php if ($_pcat): ?>
            <span class="ml-card-category"><?php echo htmlspecialchars($_pcat, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>

        <h3 class="ml-card-title">
            <a href="<?php echo $base; ?>pages/products/product-detail.php?id=<?php echo $_pid; ?>">
                <?php echo htmlspecialchars($_pname, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </h3>

        <div class="ml-card-prices">
            <?php if ($_pold !== null && $_pold > $_pprice): ?>
                <span class="ml-card-old-price">R$ <?php echo number_format($_pold, 2, ',', '.'); ?></span>
            <?php endif; ?>

            <span class="ml-card-price">R$ <?php echo number_format($_pprice, 2, ',', '.'); ?></span>

            <?php if ($_pprice > 0): ?>
                <span class="ml-card-installments">em 12x R$ <?php echo number_format($_installmentValue, 2, ',', '.'); ?> sem juros</span>
                <span class="ml-card-pix">
                    <i class="fas fa-qrcode"></i>
                    R$ <?php echo number_format($_pix_price, 2, ',', '.'); ?> <strong>no PIX</strong>
                </span>
            <?php endif; ?>
        </div>
    </div>
</article>
