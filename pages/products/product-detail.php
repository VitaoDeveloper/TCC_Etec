<?php
$page_title = 'Detalhes do Produto - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Detalhes do Produto';
$current_page = 'produtos';
$base_path = '../../';
include '../../components/header.php';
$productId = (int) ($_GET['id'] ?? 0);
?>
<section class="section">
  <div class="container">
    <h2>Detalhes do Produto</h2>
    <p>Você está visualizando o produto #<?php echo htmlspecialchars((string) $productId, ENT_QUOTES, 'UTF-8'); ?>.</p>
    <p>Esta página já está pronta para receber dados reais vindos do banco de dados.</p>
    <a class="btn btn-primary" href="products.php">Voltar para Produtos</a>
  </div>
</section>
<?php include '../../components/footer.php'; ?>
