<?php
$page_title = 'Política de Privacidade - Royal Tech';
$breadcrumb_title = 'Política de Privacidade';
$current_inst = 'privacy';
$base_path = '../../';
$page_css = ['pages.css'];
include '../../components/header.php';

$storeName = store_config('store_name');
$storeEmail = store_config('store_email');
$storeAddress = store_config('store_address');
?>
<div class="inst-page">
    <div class="container">
        <section class="inst-hero">
            <h1>Política de Privacidade</h1>
            <p>Como a <?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> coleta, usa, armazena e protege seus dados pessoais, em conformidade com a LGPD (Lei 13.709/2018).</p>
        </section>

        <div class="inst-layout">
            <?php include '_inst_nav.php'; ?>

            <article class="inst-content">
                <h2>1. Dados que coletamos</h2>
                <ul>
                    <li><strong>Cadastro:</strong> nome, e-mail, telefone, CPF e data de nascimento.</li>
                    <li><strong>Entrega:</strong> endereço e CEP informados no checkout.</li>
                    <li><strong>Pagamento:</strong> dados necessários à cobrança. Dados de cartão são processados pelo gateway; não armazenamos o número completo do cartão.</li>
                    <li><strong>Navegação:</strong> endereço IP, tipo de dispositivo/navegador e páginas acessadas, para segurança e melhoria do site.</li>
                </ul>

                <h2>2. Finalidades e bases legais</h2>
                <ul>
                    <li><strong>Execução de contrato:</strong> processar pedidos, emitir nota, entregar produtos e prestar suporte.</li>
                    <li><strong>Obrigação legal:</strong> guarda de registros fiscais e de acesso.</li>
                    <li><strong>Legítimo interesse:</strong> prevenção a fraudes e segurança do site.</li>
                    <li><strong>Consentimento:</strong> comunicações de marketing, revogável a qualquer momento.</li>
                </ul>

                <h2>3. Compartilhamento</h2>
                <p>Compartilhamos dados apenas com quem é necessário para operar a compra: transportadoras e Correios (entrega), intermediadores de pagamento (cobrança), provedores de hospedagem e ferramentas de e-mail. Não vendemos dados pessoais.</p>

                <h2>4. Cookies</h2>
                <p>Usamos cookies essenciais para manter sua sessão e o carrinho. Cookies de análise e de marketing só são ativados com o seu consentimento, conforme aviso exibido no site.</p>

                <h2>5. Seus direitos (LGPD)</h2>
                <p>Você pode, a qualquer momento, solicitar:</p>
                <ul>
                    <li>Confirmação da existência de tratamento e acesso aos dados;</li>
                    <li>Correção de dados incompletos, inexatos ou desatualizados;</li>
                    <li>Anonimização, bloqueio ou eliminação de dados desnecessários;</li>
                    <li>Portabilidade e informação sobre compartilhamentos;</li>
                    <li>Revogação do consentimento.</li>
                </ul>
                <p>Para exercer seus direitos, escreva para <a href="mailto:<?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?></a>. Responderemos no prazo legal.</p>

                <h2>6. Segurança e retenção</h2>
                <p>Adotamos medidas técnicas e administrativas para proteger os dados, como senhas com hash, conexão segura e controle de acesso. Mantemos os dados apenas pelo tempo necessário às finalidades acima ou pelo prazo exigido por lei.</p>

                <h2>7. Encarregado (DPO)</h2>
                <p>Contato do encarregado de dados: <a href="mailto:<?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($storeEmail, ENT_QUOTES, 'UTF-8'); ?></a> &mdash; <?php echo htmlspecialchars($storeAddress, ENT_QUOTES, 'UTF-8'); ?>.</p>

                <div class="inst-meta">
                    <i class="fas fa-clock"></i>
                    <span>Última atualização: <?php echo date('d/m/Y'); ?></span>
                </div>
            </article>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
