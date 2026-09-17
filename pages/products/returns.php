<?php
$page_title = 'Trocas e Devoluções - Royal Tech';
$breadcrumb_title = 'Trocas e Devoluções';
$current_inst = 'returns';
$base_path = '../../';
$page_css = ['pages.css'];
include '../../components/header.php';

$storeEmail = store_config('store_email');
$storePhone = store_config('store_phone');
?>
<div class="inst-page">
    <div class="container">
        <section class="inst-hero">
            <h1>Trocas e Devoluções</h1>
            <p>Veja como solicitar troca, devolução ou devolução por desistência, conforme o Código de Defesa do Consumidor.</p>
        </section>

        <div class="inst-layout">
            <?php include '_inst_nav.php'; ?>

            <article class="inst-content">
                <h2>Seus direitos</h2>
                <ul>
                    <li><strong>Desistência (direito de arrependimento):</strong> até <strong>7 dias corridos</strong> após o recebimento, conforme o Art. 49 do CDC.</li>
                    <li><strong>Produto com defeito:</strong> até <strong>30 dias</strong> para reclamação de vício aparente, sem prejuízo da garantia do fabricante.</li>
                    <li><strong>Produto divergente:</strong> se o item recebido for diferente do anunciado, resolvemos sem custo para você.</li>
                </ul>

                <div class="inst-callout">
                    <i class="fas fa-lock"></i>
                    <p>Para desistência, o produto deve estar sem sinais de uso, com todos os acessórios, manuais e a embalagem original.</p>
                </div>

                <h2>Como solicitar</h2>
                <ol class="inst-steps">
                    <li>
                        <strong>Abra a solicitação</strong>
                        Acesse <strong>Minha Conta &rarr; Meus Pedidos</strong>, localize o pedido e escolha <em>Trocar ou devolver</em>. Você também pode falar com o atendimento.
                    </li>
                    <li>
                        <strong>Envie os dados</strong>
                        Informe o motivo e, se possível, anexe fotos do produto e da embalagem para acelerar a análise.
                    </li>
                    <li>
                        <strong>Análise</strong>
                        Nossa equipe responde em até <strong>2 dias úteis</strong> com as instruções e, quando aplicável, o código de postagem para envio gratuito.
                    </li>
                    <li>
                        <strong>Envio e reembolso</strong>
                        Após recebermos e conferirmos o produto, o reembolso ou a troca é processado em até <strong>10 dias úteis</strong>.
                    </li>
                </ol>

                <h2>Reembolso</h2>
                <p>O reembolso é feito pelo mesmo meio de pagamento utilizado na compra:</p>
                <ul>
                    <li><strong>PIX:</strong> devolução via transferência para a chave informada.</li>
                    <li><strong>Cartão de crédito:</strong> estorno processado pela operadora, podendo aparecer em até duas faturas.</li>
                    <li><strong>Boleto:</strong> depósito em conta bancária de mesma titularidade do pedido.</li>
                </ul>

                <h2>Custos de envio</h2>
                <p>O frete da devolução é <strong>por nossa conta</strong> nos casos de desistência dentro do prazo legal, defeito de fabricação ou produto divergente. Em demais situações, os custos são informados antes de você confirmar.</p>

                <div class="inst-callout">
                    <i class="fas fa-headset"></i>
                    <p>Precisa de ajuda com uma solicitação? Fale em <a href="contact.php">Fale Conosco</a> ou pelo e-mail <a href="mailto:<?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?></a>. Telefone: <?php echo htmlspecialchars($storePhone, ENT_QUOTES, 'UTF-8'); ?>.</p>
                </div>
            </article>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
