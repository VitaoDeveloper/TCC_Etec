<?php
$page_title = 'Trabalhe Conosco - Royal Tech';
$breadcrumb_title = 'Trabalhe Conosco';
$current_inst = 'careers';
$base_path = '../../';
$page_css = ['pages.css'];
include '../../components/header.php';

$storeName = store_config('store_name');
$storeEmail = store_config('store_email');
?>
<div class="inst-page">
    <div class="container">
        <section class="inst-hero">
            <h1>Trabalhe Conosco</h1>
            <p>Na <?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> acreditamos em tecnologia com atendimento humano. Venha construir o futuro do e-commerce com a gente.</p>
        </section>

        <div class="inst-layout">
            <?php include '_inst_nav.php'; ?>

            <article class="inst-content">
                <h2>Nossa cultura</h2>
                <p>Valorizamos aprendizado contínuo, colaboração e foco no cliente. Aqui você terá espaço para propor ideias, assumir responsabilidades e crescer junto com o time.</p>

                <h2>Por que trabalhar aqui</h2>
                <ul>
                    <li>Ambiente colaborativo e cultura de feedback;</li>
                    <li>Contato direto com tecnologia de ponta e produtos premium;</li>
                    <li>Oportunidades de crescimento e capacitação;</li>
                    <li>Descontos exclusivos em produtos da loja.</li>
                </ul>

                <h2>Vagas abertas</h2>
                <p>Não há vagas abertas no momento, mas mantemos seu currículo em nosso banco de talentos para futuras oportunidades.</p>

                <div class="inst-callout">
                    <i class="fas fa-paper-plane"></i>
                    <p>Envie seu currículo para <a href="mailto:<?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?>?subject=Curr%C3%ADculo%20-%20Trabalhe%20Conosco"><?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?></a> com o assunto <strong>"Trabalhe Conosco"</strong>, informando a área de interesse.</p>
                </div>

                <h2>O que esperamos</h2>
                <ul>
                    <li>Vontade de aprender e trabalhar em equipe;</li>
                    <li>Comprometimento com prazos e qualidade;</li>
                    <li>Boa comunicação e atenção aos detalhes.</li>
                </ul>

                <h2>Processo seletivo</h2>
                <ol class="inst-steps">
                    <li><strong>Inscrição</strong> Envio do currículo por e-mail.</li>
                    <li><strong>Triagem</strong> Análise do perfil e das experiências.</li>
                    <li><strong>Entrevista</strong> Conversa com o time da área.</li>
                    <li><strong>Retorno</strong> Comunicamos o resultado a todos os participantes.</li>
                </ol>
            </article>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
