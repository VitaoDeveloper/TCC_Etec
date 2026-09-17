<?php
$page_title = 'Perguntas Frequentes - Royal Tech';
$breadcrumb_title = 'Perguntas Frequentes';
$current_inst = 'faq';
$base_path = '../../';
$page_css = ['pages.css'];
include '../../components/header.php';

$storeEmail = store_config('store_email');
$storePhone = store_config('store_phone');
$freeThreshold = number_format((float) store_config('free_shipping_threshold'), 2, ',', '.');
$pixDiscount = (int) store_config('pix_discount_percent');
?>
<div class="inst-page">
    <div class="container">
        <section class="inst-hero">
            <h1>Perguntas Frequentes</h1>
            <p>Reunimos as dúvidas mais comuns sobre compras, pagamento, entrega e pós-venda na Royal Tech.</p>
        </section>

        <div class="inst-layout">
            <?php include '_inst_nav.php'; ?>

            <article class="inst-content">
                <h2>Compras e pagamento</h2>
                <div class="inst-faq">
                    <details open>
                        <summary>Quais formas de pagamento vocês aceitam?</summary>
                        <div class="inst-faq-body">
                            <p>Aceitamos <strong>PIX</strong>, <strong>cartão de crédito</strong> e <strong>boleto bancário</strong>. No PIX você garante <strong><?php echo $pixDiscount; ?>% de desconto</strong> sobre o total do pedido.</p>
                        </div>
                    </details>
                    <details>
                        <summary>Como funciona o desconto no PIX?</summary>
                        <div class="inst-faq-body">
                            <p>O desconto é aplicado automaticamente ao selecionar o PIX como meio de pagamento. A confirmação do pedido ocorre em poucos minutos após o pagamento do código gerado.</p>
                        </div>
                    </details>
                    <details>
                        <summary>Posso parcelar minha compra?</summary>
                        <div class="inst-faq-body">
                            <p>Sim. O parcelamento está disponível no cartão de crédito, com as condições calculadas no checkout de acordo com o valor do pedido.</p>
                        </div>
                    </details>
                    <details>
                        <summary>É seguro comprar na Royal Tech?</summary>
                        <div class="inst-faq-body">
                            <p>Todas as transações são processadas em ambiente seguro e seus dados são tratados conforme a nossa <a href="privacy.php">Política de Privacidade</a>. Não armazenamos dados sensíveis de cartão em nossos servidores.</p>
                        </div>
                    </details>
                </div>

                <h2>Entrega e frete</h2>
                <div class="inst-faq">
                    <details>
                        <summary>Qual o prazo de entrega?</summary>
                        <div class="inst-faq-body">
                            <p>O prazo é calculado no checkout a partir do seu CEP, considerando a transportadora escolhida e o endereço de entrega. O prazo começa a contar após a confirmação do pagamento.</p>
                        </div>
                    </details>
                    <details>
                        <summary>O frete é grátis?</summary>
                        <div class="inst-faq-body">
                            <p>Oferecemos <strong>frete grátis para pedidos acima de R$ <?php echo $freeThreshold; ?></strong>. Abaixo desse valor, o frete é calculado conforme o CEP e a modalidade selecionada.</p>
                        </div>
                    </details>
                    <details>
                        <summary>Como acompanho meu pedido?</summary>
                        <div class="inst-faq-body">
                            <p>Após o envio, o código de rastreio fica disponível em <strong>Minha Conta &rarr; Meus Pedidos</strong>. Também enviamos atualizações por e-mail a cada mudança de status.</p>
                        </div>
                    </details>
                </div>

                <h2>Trocas, devoluções e garantia</h2>
                <div class="inst-faq">
                    <details>
                        <summary>Posso trocar ou devolver um produto?</summary>
                        <div class="inst-faq-body">
                            <p>Sim. Você tem até <strong>7 dias corridos</strong> após o recebimento para solicitar a devolução por desistência (direito de arrependimento) e até <strong>30 dias</strong> para defeitos de fabricação. Veja os detalhes em <a href="returns.php">Trocas e Devoluções</a>.</p>
                        </div>
                    </details>
                    <details>
                        <summary>Os produtos têm garantia?</summary>
                        <div class="inst-faq-body">
                            <p>Todos os produtos acompanham a garantia do fabricante. O prazo varia conforme a marca e está descrito na página do produto.</p>
                        </div>
                    </details>
                    <details>
                        <summary>Recebo nota fiscal?</summary>
                        <div class="inst-faq-body">
                            <p>Sim, emitimos nota fiscal para todos os pedidos. O comprovante pode ser baixado em <strong>Minha Conta &rarr; Meus Pedidos</strong>.</p>
                        </div>
                    </details>
                </div>

                <div class="inst-callout">
                    <i class="fas fa-headset"></i>
                    <p>Não encontrou o que procurava? Fale com a gente em <a href="contact.php">Fale Conosco</a>, por e-mail <a href="mailto:<?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?></a> ou pelo telefone <?php echo htmlspecialchars($storePhone, ENT_QUOTES, 'UTF-8'); ?>.</p>
                </div>
            </article>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
