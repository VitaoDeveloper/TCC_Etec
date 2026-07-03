<?php
/**
 * Componente de Cartão de Produto
 * 
 * Variáveis esperadas:
 * - $product_id: ID do produto
 * - $product_name: Nome do produto
 * - $product_price: Preço do produto
 * - $product_image: URL da imagem
 * - $product_category: Categoria do produto
 * - $product_brand: Marca do produto
 * - $product_installments: Parcelamento
 * - $product_is_new: Se é produto novo (boolean)
 * - $product_is_featured: Se é produto em destaque (boolean)
 * - $product_stock: Quantidade em estoque (int)
 */
?>


<?php
$imageCandidate = (string) ($product_image ?? '');
$base = $base_path ?? '';
if ($imageCandidate === '') {
    $imageCandidate = $base . 'assets/img/placeholder-product.svg';
} elseif (preg_match('#^/#', $imageCandidate)) {
    $imageCandidate = $base . ltrim($imageCandidate, '/');
} elseif (!preg_match('#^https?://#i', $imageCandidate)) {
    $imageCandidate = $base . $imageCandidate;
}
?>

<article class="product-card" data-product-id="<?php echo $product_id ?? 0; ?>">
    <div class="product-image">
        <?php if (isset($product_is_new) && $product_is_new): ?>
        <span class="product-badge new">Novo</span>
        <?php endif; ?>
        
        <?php if (isset($product_is_featured) && $product_is_featured): ?>
        <span class="product-badge featured">Destaque</span>
        <?php endif; ?>
        <?php if (isset($product_stock) && $product_stock <= 0): ?>
        <span class="product-badge out-of-stock">Esgotado</span>
        <?php endif; ?>
        
        <img src="<?php echo htmlspecialchars($imageCandidate, ENT_QUOTES, 'UTF-8'); ?>" style="width:100%; height:100%;" 
             alt="<?php echo $product_name ?? 'Produto'; ?>">
        
        <div class="product-actions">
            <button class="action-btn" title="Adicionar aos favoritos" aria-label="Adicionar aos favoritos">
                <i class="far fa-heart"></i>
            </button>
            <button class="action-btn" title="Adicionar ao carrinho" aria-label="Adicionar ao carrinho">
                <i class="fas fa-shopping-cart"></i>
            </button>
            <button class="action-btn" title="Visualizar" aria-label="Visualizar produto">
                <i class="fas fa-eye"></i>
            </button>
        </div>
    </div>
    
    <div class="product-info">
        <span class="product-category"><?php echo $product_category ?? 'Eletrônicos'; ?></span>
        <h3 class="product-name">
            <a href="<?php echo ($base_path ?? ''); ?>pages/products/product-detail.php?id=<?php echo $product_id ?? 0; ?>">
                <?php echo $product_name ?? 'Nome do Produto'; ?>
            </a>
        </h3>
        <span class="product-brand"><?php echo $product_brand ?? 'Marca'; ?></span>
        
        <div class="product-price">
            <span class="current-price">R$ <?php echo number_format($product_price ?? 0, 2, ',', '.'); ?></span>
            <?php if (isset($product_old_price)): ?>
            <span class="old-price">R$ <?php echo number_format($product_old_price, 2, ',', '.'); ?></span>
            <?php endif; ?>
        </div>
        
        <?php if (isset($product_installments)): ?>
        <div class="product-installments">
            <span>ou em <strong><?php echo $product_installments; ?></strong> de R$ <?php echo number_format(($product_price ?? 0) / ($product_installments == '12x' ? 12 : 10), 2, ',', '.'); ?></span>
        </div>
        <?php endif; ?>
        
        <?php $btnDisabled = (isset($product_stock) && $product_stock <= 0); ?>
        <button class="btn-add-cart <?php echo $btnDisabled ? '' : 'js-require-auth'; ?>" data-auth-target="carrinho" <?php echo $btnDisabled ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''; ?> aria-label="Adicionar ao carrinho">
            <i class="fas fa-shopping-bag"></i>
            <?php echo $btnDisabled ? 'Indisponível' : 'Adicionar ao Carrinho'; ?>
        </button>
    </div>
</article>
