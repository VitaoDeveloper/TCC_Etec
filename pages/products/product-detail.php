<?php
$page_title = 'Detalhes do Produto - Royal Tech';
$breadcrumb_title = 'Detalhes do Produto';
$current_page = 'produtos';
$base_path = '../../';

include '../../database/connection.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/review_functions.php';

if (!function_exists('reviewStars')) {
    function reviewStars(int $n): string
    {
        $s = '';
        for ($i = 1; $i <= 5; $i++) {
            $s .= $i <= $n ? '<i class="fas fa-star" style="color:var(--ml-accent);"></i>'
                           : '<i class="far fa-star" style="color:var(--ml-border-strong);"></i>';
        }
        return $s;
    }
}

$productId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM e5_products p INNER JOIN e5_categories c ON c.id = p.category_id WHERE p.id = :id LIMIT 1');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

$images = [];
if ($product) {
    $stmtImg = $pdo->prepare('SELECT id, image_path, is_primary FROM e5_product_images WHERE product_id = :pid ORDER BY is_primary DESC, id ASC');
    $stmtImg->execute([':pid' => $productId]);
    $images = $stmtImg->fetchAll();
}

$mainImage = !empty($images) ? renderProductImage($images[0]['image_path'], $base_path) : ($base_path . 'assets/img/placeholder-product.svg');

// =====================================================================
// AVALIAÇÕES — exibição + envio (login exigido para avaliar)
// =====================================================================
$reviewSummary = ['count' => 0, 'avg' => 0];
$reviewList = [];
$userCanReview = false;
$userReview = null;
$reviewMessage = null;
$reviewError = null;

if ($product) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $reviewSummary = reviewGetSummary($pdo, $productId);
    $reviewList = reviewGetList($pdo, $productId);

    if (!empty($_SESSION['user_id'])) {
        $reviewUserId = (int) $_SESSION['user_id'];
        $userCanReview = reviewCanUser($pdo, $reviewUserId, $productId);
        $userReview = reviewGetUser($pdo, $reviewUserId, $productId);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_require_valid();
            $reviewAction = (string) ($_POST['review_action'] ?? '');
            if ($reviewAction === 'save') {
                $res = reviewSave($pdo, $reviewUserId, $productId, (int) ($_POST['rating'] ?? 0), (string) ($_POST['comment'] ?? ''));
                if ($res['ok']) {
                    $reviewMessage = $res['message'];
                    $userReview = reviewGetUser($pdo, $reviewUserId, $productId);
                } else {
                    $reviewError = $res['message'];
                }
            } elseif ($reviewAction === 'delete') {
                $res = reviewDelete($pdo, $reviewUserId, $productId);
                $reviewMessage = $res['message'];
                $userReview = null;
                $userCanReview = reviewCanUser($pdo, $reviewUserId, $productId);
            }
            $reviewSummary = reviewGetSummary($pdo, $productId);
            $reviewList = reviewGetList($pdo, $productId);
        }
    }
}

include '../../components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;"><div class="container">
<?php if (!$product): ?>
    <div class="ml-empty">
        <i class="fas fa-box-open"></i>
        <h3>Produto não encontrado</h3>
        <p>Verifique o link ou volte para a listagem.</p>
        <p style="margin-top: 16px;"><a href="products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Ver Produtos</a></p>
    </div>
<?php else:
$stock = (int) ($product['stock'] ?? 0);
$stockOk = $stock > 0;
$price = (float) $product['price'];
$oldPrice = $product['old_price'] !== null ? (float) $product['old_price'] : null;
$discount = ($oldPrice !== null && $oldPrice > $price && $oldPrice > 0) ? round((($oldPrice - $price) / $oldPrice) * 100) : 0;
$maxQty = max(1, $stock);
?>
<div class="ml-detail-grid" data-product-id="<?php echo $productId; ?>">
    <div>
        <div class="ml-gallery-main">
            <img id="galleryMain" src="<?php echo htmlspecialchars($mainImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.onerror=null;this.src='<?php echo $base_path; ?>assets/img/placeholder-product.svg'">
        </div>
        <?php if (count($images) > 1): ?>
        <div class="ml-gallery-thumbs">
            <?php foreach ($images as $i => $img):
                $thumb = renderProductImage($img['image_path'], $base_path);
            ?>
            <div class="ml-gallery-thumb <?php echo $i === 0 ? 'active' : ''; ?>" data-img="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumbnail <?php echo $i + 1; ?>" onerror="this.onerror=null;this.src='<?php echo $base_path; ?>assets/img/placeholder-product.svg'">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <span class="ml-card-category"><?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
        <h1 class="ml-detail-title"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="ml-detail-brand">Marca: <?php echo htmlspecialchars($product['brand'] ?? 'Royal Tech', ENT_QUOTES, 'UTF-8'); ?></p>

        <div class="ml-detail-price-box">
            <span class="ml-detail-price">R$ <?php echo number_format($price, 2, ',', '.'); ?></span>
            <?php if ($oldPrice !== null && $oldPrice > $price): ?>
            <span class="ml-detail-old-price">R$ <?php echo number_format($oldPrice, 2, ',', '.'); ?></span>
            <?php if ($discount > 0): ?><span class="ml-discount-tag">-<?php echo $discount; ?>%</span><?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($stockOk): ?>
        <p class="ml-detail-stock-ok"><i class="fas fa-check-circle"></i> <?php echo $stock; ?> unidade(s) disponível(is)</p>
        <?php else: ?>
        <p class="ml-detail-stock-out"><i class="fas fa-times-circle"></i> Esgotado</p>
        <?php endif; ?>

        <div class="ml-detail-desc">
            <h4>Descrição</h4>
            <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'Sem descrição.', ENT_QUOTES, 'UTF-8')); ?></p>
        </div>

        <?php if ($stockOk): ?>
        <div class="qty-stepper">
            <button type="button" class="cart-qty-btn" id="pdpQtyDec" aria-label="Diminuir quantidade">−</button>
            <input type="number" id="pdp-qty" class="cart-qty" value="1" min="1" max="<?php echo $maxQty; ?>" aria-label="Quantidade">
            <button type="button" class="cart-qty-btn" id="pdpQtyInc" aria-label="Aumentar quantidade">+</button>
        </div>
        <div class="ml-pdp-actions">
            <button class="ml-btn ml-btn-primary ml-btn-block btn-add-cart js-require-auth" data-auth-target="carrinho"><i class="fas fa-shopping-bag"></i> Adicionar ao Carrinho</button>
            <button class="ml-btn ml-btn-buy-now ml-btn-block btn-buy-now js-require-auth" data-auth-target="comprar agora"><i class="fas fa-bolt"></i> Comprar Agora</button>
        </div>
        <?php else: ?>
        <button class="ml-btn ml-btn-primary ml-btn-block" disabled style="padding:14px; font-size:1.05rem;">Indisponível</button>
        <?php endif; ?>
    </div>
</div>

<style>
        /* ---- Avaliações ---- */
        .ml-review-summary {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin: 18px 0;
            padding: 18px;
            background: var(--ml-bg);
            border: 1px solid var(--ml-border);
            border-radius: 8px;
        }
        .ml-review-score {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .ml-review-big {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--ml-text);
            line-height: 1;
        }
        .ml-review-count {
            font-size: 0.85rem;
            color: var(--ml-text-muted);
            width: 100%;
        }
        .ml-review-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--ml-border);
            border-radius: 6px;
            background: #fff;
            color: var(--ml-text);
            font-family: var(--font-primary);
            font-size: 0.92rem;
            resize: vertical;
            min-height: 90px;
        }
        .ml-review-form textarea:focus {
            outline: none;
            border-color: var(--ml-accent);
        }
        .ml-review-star-input {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
        }
        .ml-star-label {
            cursor: pointer;
            font-size: 1.35rem;
            color: var(--ml-border-strong);
            transition: color 0.15s;
        }
        .ml-star-label:hover,
        .ml-star-label:hover ~ .ml-star-label,
        .ml-star-label:has(input:checked) {
            color: var(--ml-accent);
        }
        .ml-star-label input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .ml-review-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .ml-review-item {
            padding: 14px 16px;
            background: var(--ml-bg);
            border: 1px solid var(--ml-border);
            border-radius: 8px;
        }
        .ml-review-head {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .ml-review-date {
            font-size: 0.78rem;
            color: var(--ml-text-muted);
            margin-left: auto;
        }
        </style>
<div class="ml-card ml-checkout-block" style="margin-top:24px;">
            <h3 class="ml-block-title"><i class="fas fa-star"></i> Avaliações dos clientes</h3>

            <?php if ($reviewMessage): ?>
                <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($reviewMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($reviewError): ?>
                <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($reviewError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="ml-review-summary">
                <div class="ml-review-score">
                    <span class="ml-review-big"><?php echo number_format($reviewSummary['avg'], 1, ',', '.'); ?></span>
                    <span class="ml-stars"><?php echo reviewStars((int) round($reviewSummary['avg'])); ?></span>
                    <span class="ml-review-count"><?php echo (int) $reviewSummary['count']; ?> avaliação(ões)</span>
                </div>

                <?php if ($userCanReview): ?>
                    <form method="POST" class="ml-review-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="review_action" value="save">
                        <div class="ml-review-star-input" data-star-input>
                            <?php $curRating = $userReview ? (int) $userReview['rating'] : 5; ?>
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                            <label class="ml-star-label">
                                <input type="radio" name="rating" value="<?php echo $s; ?>" <?php echo $s === $curRating ? 'checked' : ''; ?>>
                                <i class="fas fa-star"></i>
                            </label>
                            <?php endfor; ?>
                        </div>
                        <textarea name="comment" rows="3" maxlength="1000" placeholder="Conte sua experiência com o produto (opcional)"><?php echo htmlspecialchars((string) ($userReview['comment'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div style="display:flex; gap:10px; align-items:center; margin-top:10px; flex-wrap:wrap;">
                            <button type="submit" class="ml-btn ml-btn-primary"><i class="fas fa-paper-plane"></i> <?php echo $userReview ? 'Atualizar avaliação' : 'Enviar avaliação'; ?></button>
                            <?php if ($userReview): ?>
                            <button type="submit" name="review_action" value="delete" class="ml-btn" style="background:none; color:#dc3545; border:1px solid currentColor;" onclick="return confirm('Remover sua avaliação?');"><i class="fas fa-trash"></i> Remover</button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php elseif (!empty($_SESSION['user_id'])): ?>
                    <p style="color: var(--ml-text-muted); font-size: 0.92rem;"><i class="fas fa-info-circle"></i> Você poderá avaliar este produto depois de receber seu pedido.</p>
                <?php else: ?>
                    <p style="color: var(--ml-text-muted); font-size: 0.92rem;"><i class="fas fa-sign-in-alt"></i> <a href="../auth/login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="ml-link-gold">Entre na sua conta</a> para avaliar.</p>
                <?php endif; ?>
            </div>

            <?php if ($reviewList): ?>
                <div class="ml-review-list">
                    <?php foreach ($reviewList as $rv): ?>
                    <div class="ml-review-item">
                        <div class="ml-review-head">
                            <strong><?php echo htmlspecialchars((string) $rv['user_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo reviewStars((int) $rv['rating']); ?></span>
                            <span class="ml-review-date"><?php echo date('d/m/Y', strtotime($rv['created_at'])); ?></span>
                        </div>
                        <?php if (!empty($rv['comment'])): ?>
                            <p style="color: var(--ml-text-secondary); font-size: 0.92rem;"><?php echo nl2br(htmlspecialchars((string) $rv['comment'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($reviewSummary['count'] === 0): ?>
                <p style="color: var(--ml-text-muted);">Nenhuma avaliação ainda. Seja o primeiro a avaliar!</p>
            <?php endif; ?>
        </div>

        <script>
        // Seleção visual das estrelas (destaque das anteriores)
        document.querySelectorAll('[data-star-input]').forEach(function (box) {
            var labels = Array.prototype.slice.call(box.querySelectorAll('.ml-star-label'));
            function paint() {
                var val = 0;
                var checked = box.querySelector('input[type="radio"]:checked');
                if (checked) val = parseInt(checked.value, 10) || 0;
                labels.forEach(function (l, i) {
                    l.style.color = (i < val) ? 'var(--ml-accent)' : 'var(--ml-border-strong)';
                });
            }
            labels.forEach(function (l) {
                l.addEventListener('mouseenter', function () {
                    var idx = labels.indexOf(l);
                    labels.forEach(function (x, i) { x.style.color = (i <= idx) ? 'var(--ml-accent)' : 'var(--ml-border-strong)'; });
                });
            });
            box.addEventListener('mouseleave', paint);
            box.addEventListener('change', paint);
            paint();
        });
        </script>
<script>
(function() {
    var qtyInput = document.getElementById('pdp-qty');
    var dec = document.getElementById('pdpQtyDec');
    var inc = document.getElementById('pdpQtyInc');
    if (!qtyInput || !dec || !inc) return;
    dec.addEventListener('click', function() {
        var v = parseInt(qtyInput.value, 10) || 1;
        qtyInput.value = Math.max(parseInt(qtyInput.min, 10) || 1, v - 1);
    });
    inc.addEventListener('click', function() {
        var v = parseInt(qtyInput.value, 10) || 1;
        qtyInput.value = Math.min(parseInt(qtyInput.max, 10) || 999, v + 1);
    });
})();
</script>

<?php if (count($images) > 1): ?>
<script>
document.querySelectorAll('.ml-gallery-thumb').forEach(function(thumb) {
    thumb.addEventListener('click', function() {
        document.querySelectorAll('.ml-gallery-thumb').forEach(function(t) { t.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('galleryMain').src = this.dataset.img;
    });
});
</script>
<?php endif; ?>

<?php endif; ?>
</div></section>
<?php include '../../components/footer.php'; ?>
