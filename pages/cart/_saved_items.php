<?php
// Bloco "Salvos para depois" do carrinho.
// Espera $savedItems (cartGetSavedItems) e $base_path definidos.
$savedItems = $savedItems ?? [];
$savedCount = count($savedItems);
$savedBase = $base_path ?? '../../';
?>
<div class="ml-saved-block" id="savedBlock"<?php echo $savedCount === 0 ? ' hidden' : ''; ?>>
    <div class="ml-saved-head">
        <h3 class="ml-saved-title">
            <i class="far fa-bookmark"></i> Salvos para depois
            <span class="ml-saved-count" id="savedCount"><?php echo $savedCount; ?></span>
        </h3>
        <span class="ml-saved-hint">Estes itens não entram no total do carrinho.</span>
    </div>
    <div class="ml-saved-list" id="savedList">
        <?php foreach ($savedItems as $s):
            $img = renderProductImage((string) ($s['image_path'] ?? ''), $savedBase);
            $sPrice = (float) $s['price'];
            $sOld = (float) ($s['old_price'] ?? 0);
            $sQty = (int) $s['quantity'];
            $sStock = (int) $s['stock'];
            $detailUrl = '../products/product-detail.php?id=' . (int) $s['product_id'];
        ?>
        <div class="ml-saved-item" data-product-id="<?php echo (int) $s['product_id']; ?>"
             data-price="<?php echo number_format($sPrice, 2, '.', ''); ?>"
             data-old="<?php echo number_format($sOld, 2, '.', ''); ?>"
             data-qty="<?php echo $sQty; ?>"
             data-stock="<?php echo $sStock; ?>">
            <a class="ml-saved-img" href="<?php echo $detailUrl; ?>" title="<?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?>"
                     onerror="this.onerror=null;this.src='<?php echo $savedBase; ?>assets/img/placeholder-product.svg'">
            </a>
            <div class="ml-saved-body">
                <a class="ml-saved-name" href="<?php echo $detailUrl; ?>"><?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                <div class="ml-saved-prices">
                    <?php if ($sOld > $sPrice): ?>
                        <span class="ml-saved-old">R$ <?php echo number_format($sOld, 2, ',', '.'); ?></span>
                    <?php endif; ?>
                    <span class="ml-saved-price">R$ <?php echo number_format($sPrice, 2, ',', '.'); ?></span>
                </div>
                <?php if ($sStock <= 0): ?>
                    <span class="ml-saved-out"><i class="fas fa-ban"></i> Indisponível no momento</span>
                <?php else: ?>
                    <span class="ml-saved-qty">Qtd.: <?php echo $sQty; ?></span>
                <?php endif; ?>
            </div>
            <div class="ml-saved-actions">
                <button type="button" class="ml-saved-restore"<?php echo $sStock <= 0 ? ' disabled' : ''; ?>>
                    <i class="fas fa-cart-plus"></i> Mover para o carrinho
                </button>
                <button type="button" class="ml-saved-remove cart-remove" title="Remover" aria-label="Remover item dos salvos">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="ml-saved-empty" id="savedEmpty"<?php echo $savedCount === 0 ? '' : ' hidden'; ?>>
        <i class="far fa-bookmark"></i> Nenhum item salvo para depois.
    </div>
</div>
