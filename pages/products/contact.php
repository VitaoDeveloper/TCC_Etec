<?php
$page_title = 'Contato - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Contato';
$current_page = 'contato';
$base_path = '../../';
require_once __DIR__ . '/../../includes/csrf.php';

$contactMessage = null;
$contactError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página.');
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $contactError = 'Preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'E-mail inválido.';
    } else {
        try {
            include $base_path . 'database/connection.php';
            $stmt = $pdo->prepare('INSERT INTO e5_contacts (name, email, phone, subject, message) VALUES (:name, :email, :phone, :subject, :message)');
            $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone ?: null, ':subject' => $subject, ':message' => $message]);
            $contactMessage = 'Mensagem enviada com sucesso! Responderemos em breve.';
        } catch (Throwable $e) {
            $contactError = 'Erro ao enviar mensagem. Tente novamente.';
            error_log('Contact error: ' . $e->getMessage());
        }
    }
}

include '../../components/header.php';
?>
<section class="section" style="padding: 100px 0 60px;">
    <div class="container">
        <div class="section-header">
            <h2>Fale Conosco</h2>
            <p>Estamos aqui para ajudar. Entre em contato conosco!</p>
        </div>
    </div>
</section>

<!-- Contact Content -->
<section class="section">
    <div class="container">
        <?php if ($contactMessage): ?><div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($contactMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($contactError): ?><div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($contactError, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px;">
            
            <!-- Contact Form -->
            <div>
                <h3 style="margin-bottom: 30px;">Enviar Mensagem</h3>
                <form class="contact-form" method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="admin-form-group">
                            <label for="name">Seu Nome *</label>
                            <input type="text" id="name" name="name" placeholder="Nome completo" required>
                        </div>
                        <div class="admin-form-group">
                            <label for="email">E-mail *</label>
                            <input type="email" id="email" name="email" placeholder="seu@email.com" required>
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label for="phone">Telefone</label>
                        <input type="tel" id="phone" name="phone" placeholder="(11) 99999-9999">
                    </div>
                    <div class="admin-form-group">
                        <label for="subject">Assunto *</label>
                        <select id="subject" name="subject" required>
                            <option value="">Selecione...</option>
                            <option value="support">Suporte Técnico</option>
                            <option value="sales">Vendas</option>
                            <option value="shipping">Frete e Entrega</option>
                            <option value="returns">Trocas e Devoluções</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label for="message">Mensagem *</label>
                        <textarea id="message" name="message" rows="5" placeholder="Sua mensagem..." required></textarea>
                    </div>
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Enviar Mensagem
                    </button>
                </form>
            </div>
            
            <!-- Contact Info -->
            <div>
                <h3 style="margin-bottom: 30px;">Informações de Contato</h3>
                <div class="contact-info-cards">
                    <div class="contact-info-card">
                        <div class="icon-box"><i class="fas fa-map-marker-alt"></i></div>
                        <h4>Endereço</h4>
                        <p>Av. Paulista, 1000<br>Bela Vista, São Paulo - SP<br>CEP: 01310-100</p>
                    </div>
                    <div class="contact-info-card">
                        <div class="icon-box"><i class="fas fa-phone-alt"></i></div>
                        <h4>Telefones</h4>
                        <p>(11) 99999-9999<br>(11) 3333-3333<br><small>WhatsApp disponível</small></p>
                    </div>
                    <div class="contact-info-card">
                        <div class="icon-box"><i class="fas fa-envelope"></i></div>
                        <h4>E-mail</h4>
                        <p>contato@royaltech.com.br<br>suporte@royaltech.com.br<br>vendas@royaltech.com.br</p>
                    </div>
                    <div class="contact-info-card">
                        <div class="icon-box"><i class="fas fa-clock"></i></div>
                        <h4>Horário de Funcionamento</h4>
                        <p>Segunda a Sexta: 09h às 18h<br>Sábado: 09h às 13h<br>Domingo: Fechado</p>
                    </div>
                </div>
                
                <!-- Social Media -->
                <div style="margin-top: 30px;">
                    <h4 style="margin-bottom: 20px;">Siga-nos</h4>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map Section -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <h2> Nossa Localização</h2>
        </div>
        <div class="placeholder" style="min-height: 400px;">
            <i class="fas fa-map-marked-alt"></i>
            <h4>Mapa do Google Maps</h4>
            <p>Insira o iframe do Google Maps aqui</p>
            <small>Dimensões: 1200x400px</small>
        </div>
    </div>
</section>

<?php
include '../../components/footer.php';
?>
