<?php
$page_title = 'Termos de Uso - Royal Tech';
$breadcrumb_title = 'Termos de Uso';
$current_inst = 'terms';
$base_path = '../../';
$page_css = ['pages.css'];
include '../../components/header.php';

$storeName = store_config('store_name');
$storeEmail = store_config('store_email');
$storeAddress = store_config('store_address');
$storeCnpj = store_config('store_cnpj');
?>
<div class="inst-page">
    <div class="container">
        <section class="inst-hero">
            <h1>Termos de Uso</h1>
            <p>Estes termos regulam o uso do site e a compra de produtos na <?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?>. Ao navegar ou comprar, você concorda com as condições abaixo.</p>
        </section>

        <div class="inst-layout">
            <?php include '_inst_nav.php'; ?>

            <article class="inst-content">
                <h2>1. Identificação</h2>
                <p>Este site é operado por <strong><?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?></strong>, CNPJ <?php echo htmlspecialchars($storeCnpj, ENT_QUOTES, 'UTF-8'); ?>, com sede em <?php echo htmlspecialchars($storeAddress, ENT_QUOTES, 'UTF-8'); ?>. Contato: <a href="mailto:<?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?></a>.</p>

                <h2>2. Aceitação dos termos</h2>
                <p>Ao acessar, cadastrar-se ou realizar um pedido, o usuário declara ter lido e aceito integralmente estes Termos de Uso e a <a href="privacy.php">Política de Privacidade</a>. Caso não concorde, deve interromper o uso do site.</p>

                <h2>3. Cadastro e conta</h2>
                <ul>
                    <li>O usuário é responsável pela veracidade dos dados informados no cadastro.</li>
                    <li>As credenciais de acesso são pessoais e intransferíveis; o titular responde pelas atividades realizadas em sua conta.</li>
                    <li>É proibido criar contas com dados de terceiros ou com informações falsas.</li>
                </ul>

                <h2>4. Produtos e preços</h2>
                <ul>
                    <li>As imagens são ilustrativas e podem apresentar pequenas variações em relação ao produto entregue.</li>
                    <li>Preços, disponibilidade e promoções podem ser alterados sem aviso prévio.</li>
                    <li>Em caso de erro evidente de preço ou de sistema, o pedido pode ser cancelado com reembolso integral, respeitado o CDC.</li>
                </ul>

                <h2>5. Pedidos e pagamento</h2>
                <p>O pedido é confirmado após a aprovação do pagamento. Aceitamos PIX, cartão de crédito e boleto, conforme as opções exibidas no checkout. O prazo de entrega é calculado a partir da confirmação do pagamento.</p>

                <h2>6. Entrega</h2>
                <p>A entrega depende de endereço correto e de pessoa apta a receber o produto. Tentativas frustradas e endereço incorreto podem gerar novos custos de frete, informados previamente.</p>

                <h2>7. Trocas, devoluções e garantia</h2>
                <p>As regras estão detalhadas em <a href="returns.php">Trocas e Devoluções</a> e seguem o Código de Defesa do Consumidor e a garantia do fabricante.</p>

                <h2>8. Uso do site</h2>
                <p>É vedado utilizar o site para fins ilícitos, tentar burlar mecanismos de segurança, extrair dados em massa (scraping) ou reproduzir conteúdo sem autorização. Todo o conteúdo (marca, textos, layout e imagens) é protegido por direitos autorais.</p>

                <h2>9. Responsabilidade</h2>
                <p>Empregamos boas práticas de segurança, mas não nos responsabilizamos por indisponibilidades temporárias decorrentes de causas externas, como falhas de conexão, manutenção de terceiros ou caso fortuito e força maior.</p>

                <h2>10. Foro e legislação</h2>
                <p>Estes termos são regidos pelas leis brasileiras. Eventuais controvérsias serão resolvidas preferencialmente por atendimento direto, sem prejuízo do foro do domicílio do consumidor.</p>

                <div class="inst-meta">
                    <i class="fas fa-clock"></i>
                    <span>Última atualização: <?php echo date('d/m/Y'); ?></span>
                </div>
            </article>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
