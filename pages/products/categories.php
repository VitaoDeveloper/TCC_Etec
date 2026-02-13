<?php
$page_title = 'Categorias - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Categorias';

include '../../pages/components/header.php';
?>

<!-- Categories Hero -->
<section class="section" style="padding: 100px 0 60px;">
    <div class="container">
        <div class="section-header">
            <h2>Categorias</h2>
            <p>Navegue por todas as categorias e encontre os melhores produtos tecnológicos</p>
        </div>
    </div>
</section>

<!-- Categories Grid -->
<section class="section">
    <div class="container">
        <div class="categories-grid">
            <a href="products.php?category=notebooks" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-laptop"></i>
                </div>
                <h4>Notebooks</h4>
                <span>Os melhores computadores portáteis para trabalho e entretenimento</span>
                <div class="category-count">24 produtos</div>
            </a>
            
            <a href="products.php?category=smartphones" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h4>Smartphones</h4>
                <span>Celulares de última geração com as melhores tecnologias</span>
                <div class="category-count">18 produtos</div>
            </a>
            
            <a href="products.php?category=tablets" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-tablet-alt"></i>
                </div>
                <h4>Tablets</h4>
                <span>Praticidade e potência em um dispositivo portátil</span>
                <div class="category-count">12 produtos</div>
            </a>
            
            <a href="products.php?category=perifericos" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-keyboard"></i>
                </div>
                <h4>Periféricos</h4>
                <span>Teclados, mouses, webcams e muito mais</span>
                <div class="category-count">36 produtos</div>
            </a>
            
            <a href="products.php?category=audio" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-headphones"></i>
                </div>
                <h4>Áudio</h4>
                <span>Fones, caixas de som e equipamentos de som premium</span>
                <div class="category-count">28 produtos</div>
            </a>
            
            <a href="products.php?category=games" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-gamepad"></i>
                </div>
                <h4>Games</h4>
                <span>Consoles, jogos e acessórios gamers</span>
                <div class="category-count">22 produtos</div>
            </a>
            
            <a href="products.php?category=cameras" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <h4>Câmeras</h4>
                <span>Capture os melhores momentos com qualidade profissional</span>
                <div class="category-count">15 produtos</div>
            </a>
            
            <a href="products.php?category=acessorios" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h4>Acessórios</h4>
                <span>Cabos, carregadores, capas e muito mais</span>
                <div class="category-count">45 produtos</div>
            </a>
            
            <a href="products.php?category=wearables" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-watch"></i>
                </div>
                <h4>Wearables</h4>
                <span>Smartwatches e pulseiras inteligentes</span>
                <div class="category-count">16 produtos</div>
            </a>
            
            <a href="products.php?category=monitores" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-desktop"></i>
                </div>
                <h4>Monitores</h4>
                <span>Telas de alta resolução para trabalho e games</span>
                <div class="category-count">20 produtos</div>
            </a>
            
            <a href="products.php?category=armazenamento" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-hdd"></i>
                </div>
                <h4>Armazenamento</h4>
                <span>HDs, SSDs, pendrives e cartões de memória</span>
                <div class="category-count">30 produtos</div>
            </a>
            
            <a href="products.php?category=redes" class="category-card">
                <div class="category-icon">
                    <i class="fas fa-wifi"></i>
                </div>
                <h4>Redes</h4>
                <span>Roteadores, extensores e equipamentos de rede</span>
                <div class="category-count">14 produtos</div>
            </a>
        </div>
    </div>
</section>

<style>
.category-count {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--color-border);
    color: var(--color-primary);
    font-weight: 600;
    font-size: 0.9rem;
}

.category-card span:not(.category-count) {
    color: var(--color-gray);
    font-size: 0.9rem;
    line-height: 1.5;
}
</style>

<?php
include '../../pages/components/footer.php';
?>
