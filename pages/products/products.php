<?php
$page_title = 'Produtos - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Produtos';

include '../../components/header.php';
?>

<!-- Products Section -->
<section class="section">
    <div class="container">
        <div style="display: grid; grid-template-columns: 280px 1fr; gap: 40px;">
            
            <!-- Sidebar de Filtros -->
            <aside class="products-sidebar">
                <div class="filter-section">
                    <h4>Categorias</h4>
                    <ul class="filter-list">
                        <li><a href="#" class="active">Todos os Produtos</a></li>
                        <li><a href="#">Notebooks</a></li>
                        <li><a href="#">Smartphones</a></li>
                        <li><a href="#">Tablets</a></li>
                        <li><a href="#">Periféricos</a></li>
                        <li><a href="#">Áudio</a></li>
                        <li><a href="#">Games</a></li>
                        <li><a href="#">Câmeras</a></li>
                        <li><a href="#">Acessórios</a></li>
                    </ul>
                </div>
                
                <div class="filter-section">
                    <h4>Marcas</h4>
                    <ul class="filter-list">
                        <li><label><input type="checkbox"> Royal Tech</label></li>
                        <li><label><input type="checkbox"> Apple</label></li>
                        <li><label><input type="checkbox"> Samsung</label></li>
                        <li><label><input type="checkbox"> Sony</label></li>
                        <li><label><input type="checkbox"> LG</label></li>
                        <li><label><input type="checkbox"> Microsoft</label></li>
                    </ul>
                </div>
                
                <div class="filter-section">
                    <h4>Faixa de Preço</h4>
                    <div class="price-range">
                        <input type="number" placeholder="Mín" min="0">
                        <span>-</span>
                        <input type="number" placeholder="Máx" min="0">
                    </div>
                    <button class="btn btn-secondary" style="width: 100%; margin-top: 15px;">Aplicar</button>
                </div>
                
                <div class="filter-section">
                    <h4>Status</h4>
                    <ul class="filter-list">
                        <li><label><input type="checkbox" checked> Em Estoque</label></li>
                        <li><label><input type="checkbox"> Promoção</label></li>
                        <li><label><input type="checkbox"> Lançamentos</label></li>
                        <li><label><input type="checkbox"> Mais Vendidos</label></li>
                    </ul>
                </div>
            </aside>
            
            <!-- Conteúdo Principal -->
            <main class="products-main">
                <!-- Header da Seção -->
                <div class="products-header">
                    <div class="results-count">
                        <span>Mostrando <strong>1-12</strong> de <strong>48</strong> produtos</span>
                    </div>
                    <div class="products-sort">
                        <label>Ordenar por:</label>
                        <select>
                            <option>Relevância</option>
                            <option>Menor Preço</option>
                            <option>Maior Preço</option>
                            <option>Mais Vendidos</option>
                            <option>Lançamentos</option>
                        </select>
                    </div>
                    <div class="products-view">
                        <button class="view-btn active"><i class="fas fa-th"></i></button>
                        <button class="view-btn"><i class="fas fa-list"></i></button>
                    </div>
                </div>
                
                <!-- Grid de Produtos -->
                <div class="products-grid">
                    <?php
                    // Produtos de exemplo
                    $all_products = [
                        ['id' => 1, 'name' => 'Notebook Premium Pro', 'price' => 8999.00, 'old_price' => 9999.00, 'category' => 'Notebooks', 'brand' => 'Royal Tech', 'installments' => '12x', 'new' => true, 'featured' => true],
                        ['id' => 2, 'name' => 'Smartphone Ultra X', 'price' => 4999.00, 'category' => 'Smartphones', 'brand' => 'Royal Tech', 'installments' => '12x', 'featured' => true],
                        ['id' => 3, 'name' => 'Fone Premium Wireless', 'price' => 899.00, 'category' => 'Áudio', 'brand' => 'Royal Audio', 'installments' => '6x', 'new' => true],
                        ['id' => 4, 'name' => 'Teclado Mecânico RGB', 'price' => 449.00, 'category' => 'Periféricos', 'brand' => 'Royal Gear', 'installments' => '6x', 'featured' => true],
                        ['id' => 5, 'name' => 'Smartwatch Elite', 'price' => 2499.00, 'category' => 'Wearables', 'brand' => 'Royal Tech', 'installments' => '10x', 'new' => true],
                        ['id' => 6, 'name' => 'Tablet Pro 12"', 'price' => 5999.00, 'category' => 'Tablets', 'brand' => 'Royal Tech', 'installments' => '12x', 'new' => true],
                        ['id' => 7, 'name' => 'Monitor 4K Premium', 'price' => 3299.00, 'category' => 'Monitores', 'brand' => 'Royal Vision', 'installments' => '10x', 'featured' => true],
                        ['id' => 8, 'name' => 'Console Gaming Pro', 'price' => 4499.00, 'category' => 'Games', 'brand' => 'Royal Play', 'installments' => '12x', 'new' => true],
                        ['id' => 9, 'name' => 'Mouse Gamer Pro', 'price' => 299.00, 'category' => 'Periféricos', 'brand' => 'Royal Gear', 'installments' => '6x'],
                        ['id' => 10, 'name' => 'Caixa de Som Bluetooth', 'price' => 799.00, 'category' => 'Áudio', 'brand' => 'Royal Audio', 'installments' => '6x', 'new' => true],
                        ['id' => 11, 'name' => 'Webcam HD Pro', 'price' => 449.00, 'category' => 'Câmeras', 'brand' => 'Royal Vision', 'installments' => '6x'],
                        ['id' => 12, 'name' => 'Carregador Turbo', 'price' => 149.00, 'category' => 'Acessórios', 'brand' => 'Royal Tech', 'installments' => '4x', 'featured' => true],
                    ];
                    
                    foreach ($all_products as $p) {
                        $product_id = $p['id'];
                        $product_name = $p['name'];
                        $product_price = $p['price'];
                        $product_old_price = $p['old_price'] ?? null;
                        $product_image = 'assets/img/placeholder-product.jpg';
                        $product_category = $p['category'];
                        $product_brand = $p['brand'];
                        $product_installments = $p['installments'];
                        $product_is_new = $p['new'] ?? false;
                        $product_is_featured = $p['featured'] ?? false;
                        
                        include '../../components/product-card.php';
                    }
                    ?>
                </div>
                
                <!-- Paginação -->
                <div class="pagination">
                    <a href="#" class="page-btn active">1</a>
                    <a href="#" class="page-btn">2</a>
                    <a href="#" class="page-btn">3</a>
                    <a href="#" class="page-btn">4</a>
                    <span>...</span>
                    <a href="#" class="page-btn">8</a>
                    <a href="#" class="page-btn next">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </main>
        </div>
    </div>
</section>

<style>
/* Styles específicos para página de produtos */
.products-sidebar {
    background: var(--color-black-light);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    padding: 30px;
    height: fit-content;
    position: sticky;
    top: 120px;
}

.filter-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--color-border);
}

.filter-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.filter-section h4 {
    font-size: 1.1rem;
    margin-bottom: 20px;
    color: var(--color-white);
}

.filter-list li {
    margin-bottom: 12px;
}

.filter-list a {
    color: var(--color-gray-light);
    transition: var(--transition);
}

.filter-list a:hover,
.filter-list a.active {
    color: var(--color-primary);
}

.filter-list label {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--color-gray-light);
    cursor: pointer;
    transition: var(--transition);
}

.filter-list label:hover {
    color: var(--color-primary);
}

.filter-list input[type="checkbox"] {
    accent-color: var(--color-primary);
}

.price-range {
    display: flex;
    align-items: center;
    gap: 10px;
}

.price-range input {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    background: var(--color-black);
    color: var(--color-white);
    font-family: var(--font-primary);
}

.products-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--color-border);
}

.results-count {
    color: var(--color-gray-light);
}

.products-sort {
    display: flex;
    align-items: center;
    gap: 10px;
}

.products-sort label {
    color: var(--color-gray-light);
}

.products-sort select {
    padding: 10px 20px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    background: var(--color-black-light);
    color: var(--color-white);
    font-family: var(--font-primary);
    cursor: pointer;
}

.products-view {
    display: flex;
    gap: 10px;
}

.view-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    background: transparent;
    color: var(--color-gray-light);
    cursor: pointer;
    transition: var(--transition);
}

.view-btn:hover,
.view-btn.active {
    color: var(--color-primary);
    border-color: var(--color-primary);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 50px;
}

.page-btn {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    color: var(--color-gray-light);
    font-weight: 600;
    transition: var(--transition);
}

.page-btn:hover,
.page-btn.active {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: var(--color-black);
}

.page-btn.next {
    width: auto;
    padding: 0 20px;
}

@media (max-width: 992px) {
    .products-sidebar {
        display: none;
    }
    
    .products-main {
        grid-column: 1 / -1;
    }
}
</style>

<?php
include '../../components/footer.php';
?>
