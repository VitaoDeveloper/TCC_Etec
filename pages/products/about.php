<?php
$page_title = 'Sobre Nós - Royal Tech';
$current_page = 'sobre';
$base_path = '../../';

// Fotos da equipe: "frente" visivel por padrao, "lado" no hover.
// "slug" aponta para assets/img/sobre/<slug>-frente.jpeg e <slug>-lado.jpeg
$equipe = [
    ['nome' => 'Jônatas', 'slug' => 'jonatas'],
    ['nome' => 'Paulo Vitor', 'slug' => 'paulo-vitor'],
    ['nome' => 'Paulo Arthur', 'slug' => 'paulo-arthur'],
    ['nome' => 'Kauã Caitano', 'slug' => 'kaua-caitano'],
    ['nome' => 'Lucas', 'slug' => 'lucas'],
    ['nome' => 'Nicolas Jacinto', 'slug' => 'nicolas-jacinto'],
];

include '../../components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;">
    <div class="container">
        <div class="ml-section-header">
            <h2 class="ml-section-title">Sobre a Royal Tech</h2>
        </div>
        <p style="color: var(--ml-text-secondary); max-width: 700px; margin-top: -8px;">
            Sua loja de tecnologia premium com os melhores produtos e atendimento diferenciado
        </p>
    </div>
</section>

<!-- About Content -->
<section class="ml-section" style="padding-top: 0;">
    <div class="container">
        <div class="ml-contact-grid">
            <div>
                <h3 style="margin-bottom: 20px;">Nossa História</h3>
                <p style="color: var(--ml-text-secondary); margin-bottom: 20px;">
                    Fundada com a missão de democratizar o acesso à tecnologia de alta qualidade, a Royal Tech nasceu da paixão por inovação e do compromisso inabalável com a satisfação dos nossos clientes.
                </p>
                <p style="color: var(--ml-text-secondary); margin-bottom: 20px;">
                    Ao longo dos anos, construímos uma reputação sólida no mercado brasileiro, tornando-nos referência em tecnologia premium. Nossa equipe é formada por especialistas apaixonados pelo que fazem, sempre prontos para ajudar você a encontrar a melhor solução tecnológica para suas necessidades.
                </p>
                <p style="color: var(--ml-text-secondary);">
                    Trabalhamos com as melhores marcas do mundo, garantindo que cada produto em nosso catálogo passe por rigorosos testes de qualidade. Sua satisfação é nossa maior recompensa.
                </p>
            </div>
            <figure class="ml-store-figure">
                <img class="ml-store-photo" src="<?php echo $base_path; ?>assets/img/sobre/royaltech.jpeg" alt="Fachada da loja Royal Tech" loading="lazy" width="1280" height="853">
                <figcaption class="ml-store-caption">Loja Royal Tech</figcaption>
            </figure>
        </div>
    </div>
</section>

<!-- Values -->
<section class="ml-section">
    <div class="container">
        <div class="ml-section-header">
            <h2 class="ml-section-title">Nossos Valores</h2>
        </div>
        <p style="color: var(--ml-text-secondary); margin-top: -8px; margin-bottom: 20px;">Os princípios que guiam todas as nossas ações</p>
        <div class="ml-features-strip">
            <div class="ml-feature-item">
                <span class="ml-feature-icon"><i class="fas fa-star"></i></span>
                <div class="ml-feature-text">
                    <h4>Qualidade Premium</h4>
                    <p>Selecionamos apenas produtos dos melhores fabricantes mundiais</p>
                </div>
            </div>
            <div class="ml-feature-item">
                <span class="ml-feature-icon"><i class="fas fa-heart"></i></span>
                <div class="ml-feature-text">
                    <h4>Atendimento Personalizado</h4>
                    <p>Nossa equipe está pronta para oferecer as melhores soluções</p>
                </div>
            </div>
            <div class="ml-feature-item">
                <span class="ml-feature-icon"><i class="fas fa-shield-alt"></i></span>
                <div class="ml-feature-text">
                    <h4>Confiança e Segurança</h4>
                    <p>Transações seguras e garantia em todos os produtos</p>
                </div>
            </div>
            <div class="ml-feature-item">
                <span class="ml-feature-icon"><i class="fas fa-truck"></i></span>
                <div class="ml-feature-text">
                    <h4>Entrega Rápida</h4>
                    <p>Logística eficiente para você receber onde estiver</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats -->
<section class="ml-section">
    <div class="container">
        <div class="ml-stats-grid">
            <div class="ml-stat-card">
                <i class="fas fa-users"></i>
                <div class="ml-stat-value">10.000+</div>
                <div class="ml-stat-label">Clientes Atendidos</div>
            </div>
            <div class="ml-stat-card">
                <i class="fas fa-box"></i>
                <div class="ml-stat-value">5.000+</div>
                <div class="ml-stat-label">Produtos Vendidos</div>
            </div>
            <div class="ml-stat-card">
                <i class="fas fa-award"></i>
                <div class="ml-stat-value">50+</div>
                <div class="ml-stat-label">Marcas Parceiras</div>
            </div>
            <div class="ml-stat-card">
                <i class="fas fa-star"></i>
                <div class="ml-stat-value">4.9/5</div>
                <div class="ml-stat-label">Avaliação Média</div>
            </div>
        </div>
    </div>
</section>

<!-- Team -->
<section class="ml-section">
    <div class="container">
        <div class="ml-section-header">
            <h2 class="ml-section-title">Nossa Equipe</h2>
        </div>
        <p style="color: var(--ml-text-secondary); margin-top: -8px; margin-bottom: 20px;">Profissionais dedicados ao seu sucesso</p>
        <div class="ml-team-grid">
            <?php foreach ($equipe as $membro): ?>
                <div class="ml-team-card">
                    <div class="ml-team-photo" title="Passe o mouse para ver a foto de lado">
                        <img class="ml-team-photo-front" src="<?php echo $base_path; ?>assets/img/sobre/<?php echo $membro['slug']; ?>-frente.jpeg" alt="Foto de frente de <?php echo htmlspecialchars($membro['nome'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" width="1204" height="1600">
                        <img class="ml-team-photo-side" src="<?php echo $base_path; ?>assets/img/sobre/<?php echo $membro['slug']; ?>-lado.jpeg" alt="" aria-hidden="true" loading="lazy" width="1204" height="1600">
                    </div>
                    <h4><?php echo htmlspecialchars($membro['nome'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    <span class="ml-team-role">Desenvolvedor</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';
    var SEQUENCE = 'SP';
    var WINDOW_MS = 1500;
    var basePath = document.body.getAttribute('data-base-path') || '../../';
    var buffer = '';
    var seqStart = 0;
    var resetTimer = null;

    function isTypingTarget(el) {
        return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
    }

    function reset() {
        buffer = '';
        seqStart = 0;
        if (resetTimer) {
            clearTimeout(resetTimer);
            resetTimer = null;
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.metaKey || e.ctrlKey || e.altKey) return;
        if (isTypingTarget(e.target)) return;
        if (e.getModifierState && e.getModifierState('CapsLock')) { reset(); return; }
        if (e.key !== 'S' && e.key !== 'P') { reset(); return; }

        var now = Date.now();
        if (buffer.length === 0) {
            seqStart = now;
        } else if (now - seqStart > WINDOW_MS) {
            reset();
            seqStart = now;
        }

        buffer += e.key;

        if (SEQUENCE.indexOf(buffer) !== 0) {
            reset();
            return;
        }

        if (buffer === SEQUENCE) {
            reset();
            window.open(basePath + 'pages/conteudo/especial.php', '_blank');
            return;
        }

        if (resetTimer) clearTimeout(resetTimer);
        resetTimer = setTimeout(reset, WINDOW_MS);
    });
})();
</script>

<?php
include '../../components/footer.php';
?>
