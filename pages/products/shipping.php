<?php
$page_title = 'Frete e Entrega - Royal Tech';
$breadcrumb_title = 'Frete e Entrega';
$current_inst = 'shipping';
$base_path = '../../';
$page_css = ['pages.css'];
include '../../components/header.php';

$freeThreshold = number_format((float) store_config('free_shipping_threshold'), 2, ',', '.');
$storeEmail = store_config('store_email');
$storePhone = store_config('store_phone');
?>
<div class="inst-page">
    <div class="container">
        <section class="inst-hero">
            <h1>Frete e Entrega</h1>
            <p>Entenda como calculamos o frete, quais são os prazos e como acompanhar a entrega do seu pedido.</p>
        </section>

        <div class="inst-layout">
            <?php include '_inst_nav.php'; ?>

            <article class="inst-content">
                <h2>Cálculo do frete</h2>
                <p>O valor e o prazo do frete são calculados em tempo real no checkout, a partir do seu <strong>CEP</strong>, do <strong>peso e dimensões</strong> dos produtos e da <strong>modalidade</strong> escolhida. Basta informar o CEP para ver as opções disponíveis antes de finalizar a compra.</p>

                <div class="inst-cards">
                    <div class="inst-card">
                        <i class="fas fa-truck-fast"></i>
                        <h4>Frete grátis</h4>
                        <p>Para pedidos acima de R$ <?php echo $freeThreshold; ?>.</p>
                    </div>
                    <div class="inst-card">
                        <i class="fas fa-boxes-stacked"></i>
                        <h4>Vários itens</h4>
                        <p>O frete é calculado pelo conjunto do carrinho, não por item.</p>
                    </div>
                    <div class="inst-card">
                        <i class="fas fa-location-dot"></i>
                        <h4>Entrega nacional</h4>
                        <p>Enviamos para todo o Brasil, com rastreamento.</p>
                    </div>
                </div>

                <h2>Prazos de entrega</h2>
                <p>O prazo informado no checkout considera dias úteis e começa a contar <strong>após a confirmação do pagamento</strong>:</p>
                <ul>
                    <li><strong>PIX:</strong> confirmação em minutos, agilizando o envio.</li>
                    <li><strong>Cartão de crédito:</strong> confirmação em até 1 dia útil.</li>
                    <li><strong>Boleto:</strong> confirmação em até 3 dias úteis após o pagamento.</li>
                </ul>
                <p>Eventos externos como greves, condições climáticas ou restrições da transportadora podem alterar o prazo. Nesses casos, avisamos pelos canais de contato cadastrados.</p>

                <h2>Acompanhamento do pedido</h2>
                <ol class="inst-steps">
                    <li>
                        <strong>Confirmação do pagamento</strong>
                        Você recebe um e-mail confirmando que o pedido foi aprovado.
                    </li>
                    <li>
                        <strong>Separação e envio</strong>
                        Após a emissão da etiqueta, o código de rastreio é gerado.
                    </li>
                    <li>
                        <strong>Rastreio</strong>
                        Acompanhe o trajeto em <strong>Minha Conta &rarr; Meus Pedidos</strong> ou no site da transportadora.
                    </li>
                    <li>
                        <strong>Entrega</strong>
                        Confira o produto no recebimento e, se houver qualquer problema, fale conosco em até 7 dias.
                    </li>
                </ol>

                <div class="inst-callout">
                    <i class="fas fa-circle-info"></i>
                    <p>Recebeu um aviso de tentativa de entrega? Entre em contato pelo e-mail <a href="mailto:<?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?></a> ou telefone <?php echo htmlspecialchars($storePhone, ENT_QUOTES, 'UTF-8'); ?> para reagendarmos.</p>
                </div>
            </article>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
