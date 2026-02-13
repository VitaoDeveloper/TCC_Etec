<?php
$page_title = 'Sobre Nós - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Sobre Nós';

include '../components/header.php';
?>

<!-- About Hero -->
<section class="section" style="padding: 100px 0 60px;">
    <div class="container">
        <div class="section-header">
            <h2>Sobre a Royal Tech</h2>
            <p>Sua loja de tecnologia premium com os melhores produtos e atendimento diferenciado</p>
        </div>
    </div>
</section>

<!-- About Content -->
<section class="section">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;">
            <div>
                <h3 style="margin-bottom: 20px;">Nossa História</h3>
                <p style="color: var(--color-gray-light); margin-bottom: 20px;">
                    Fundada com a missão de democratizar o acesso à tecnologia de alta qualidade, a Royal Tech nasceu da paixão por inovação e do compromisso inabalável com a satisfação dos nossos clientes.
                </p>
                <p style="color: var(--color-gray-light); margin-bottom: 20px;">
                    Ao longo dos anos, construímos uma reputação sólida no mercado brasileiro, tornando-nos referência em tecnologia premium. Nossa equipe é formada por especialistas apaixonados pelo que fazem, sempre prontos para ajudar você a encontrar a melhor solução tecnológica para suas necessidades.
                </p>
                <p style="color: var(--color-gray-light);">
                    Trabalhamos com as melhores marcas do mundo, garantindo que cada produto em nosso catálogo passe por rigorosos testes de qualidade. Sua satisfação é nossa maior recompensa.
                </p>
            </div>
            <div>
                <div class="placeholder" style="min-height: 400px;">
                    <i class="fas fa-store"></i>
                    <h4>Imagem da Loja</h4>
                    <p>Insira uma foto da loja física ou equipe aqui</p>
                    <small>Dimensões: 600x400px</small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Values -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <h2>Nossos Valores</h2>
            <p>Os princípios que guiam todas as nossas ações</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h4>Qualidade Premium</h4>
                <p>Selecionamos apenas produtos dos melhores fabricantes mundiais</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h4>Atendimento Personalizado</h4>
                <p>Nossa equipe está pronta para oferecer as melhores soluções</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>Confiança e Segurança</h4>
                <p>Transações seguras e garantia em todos os produtos</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h4>Entrega Rápida</h4>
                <p>Logística eficiente para você receber onde estiver</p>
            </div>
        </div>
    </div>
</section>

<!-- Stats -->
<section class="section">
    <div class="container">
        <div class="admin-cards">
            <div class="admin-card">
                <div class="admin-card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="admin-card-value">10.000+</div>
                <div class="admin-card-label">Clientes Atendidos</div>
            </div>
            <div class="admin-card">
                <div class="admin-card-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="admin-card-value">5.000+</div>
                <div class="admin-card-label">Produtos Vendidos</div>
            </div>
            <div class="admin-card">
                <div class="admin-card-icon">
                    <i class="fas fa-award"></i>
                </div>
                <div class="admin-card-value">50+</div>
                <div class="admin-card-label">Marcas Parceiras</div>
            </div>
            <div class="admin-card">
                <div class="admin-card-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="admin-card-value">4.9/5</div>
                <div class="admin-card-label">Avaliação Média</div>
            </div>
        </div>
    </div>
</section>

<!-- Team -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <h2> Nossa Equipe</h2>
            <p>Profissionais dedicados ao seu sucesso</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px;">
            <div class="team-member" style="text-align: center;">
                <div class="placeholder" style="min-height: 250px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-user"></i>
                </div>
                <h4>João Silva</h4>
                <span style="color: var(--color-primary);">CEO & Fundador</span>
            </div>
            <div class="team-member" style="text-align: center;">
                <div class="placeholder" style="min-height: 250px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-user"></i>
                </div>
                <h4>Maria Santos</h4>
                <span style="color: var(--color-primary);">Diretora Comercial</span>
            </div>
            <div class="team-member" style="text-align: center;">
                <div class="placeholder" style="min-height: 250px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-user"></i>
                </div>
                <h4>Pedro Costa</h4>
                <span style="color: var(--color-primary);">Gerente de Produtos</span>
            </div>
            <div class="team-member" style="text-align: center;">
                <div class="placeholder" style="min-height: 250px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-user"></i>
                </div>
                <h4>Ana Oliveira</h4>
                <span style="color: var(--color-primary);">Atendimento ao Cliente</span>
            </div>
        </div>
    </div>
</section>

<?php
include '../components/footer.php';
?>
