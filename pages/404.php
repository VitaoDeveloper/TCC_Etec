<?php
$page_title = 'Página Não Encontrada - Royal Tech';
$show_breadcrumb = false;
$current_page = '';

$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = rtrim(dirname($scriptDir), '/\\') . '/';

http_response_code(404);

include __DIR__ . '/../components/header.php';
?>
<section class="ml-section" style="padding: 100px 0 80px;">
    <div class="container">
        <div style="max-width: 600px; margin: 0 auto; text-align: center;">
            <div style="font-size: 120px; font-weight: 700; color: var(--ml-accent); line-height: 1; margin-bottom: 20px; font-family: 'Playfair Display', serif;">
                404
            </div>
            <h1 style="margin-bottom: 15px; font-size: 28px; color: var(--ml-text);">Página Não Encontrada</h1>
            <p style="color: var(--ml-text-secondary); margin-bottom: 30px; font-size: 16px;">
                A página que você procura pode ter sido removida, renomeada ou está temporariamente indisponível.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo $base_path; ?>index.php" class="ml-btn ml-btn-primary">
                    <i class="fas fa-home"></i> Ir para o Início
                </a>
                <a href="<?php echo $base_path; ?>pages/products/products.php" class="ml-btn">
                    <i class="fas fa-box"></i> Ver Produtos
                </a>
                <a href="<?php echo $base_path; ?>pages/products/contact.php" class="ml-btn">
                    <i class="fas fa-envelope"></i> Fale Conosco
                </a>
            </div>
        </div>
    </div>
</section>

<?php
include __DIR__ . '/../components/footer.php';
?>
