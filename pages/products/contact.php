<?php
$page_title = 'Contato - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Contato';
$current_page = 'contato';
$base_path = '../../';

include '../../components/header.php';
?>
<link rel="stylesheet" href="<?php echo $base_path.'assets/css/style.css' ?>">
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
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px;">
            
            <!-- Contact Form -->
            <div>
                <h3 style="margin-bottom: 30px;">Enviar Mensagem</h3>
                <form class="contact-form">
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
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Enviar Mensagem
                    </button>
                </form>
            </div>
            
            <!-- Contact Info -->
            <div>
                <h3 style="margin-bottom: 30px;">Informações de Contato</h3>
                
                <div class="contact-info-cards" style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="admin-table-container" style="padding: 30px;">
                        <div style="display: flex; align-items: flex-start; gap: 20px;">
                            <div style="width: 60px; height: 60px; background: var(--color-black); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-map-marker-alt" style="font-size: 1.5rem; color: var(--color-primary);"></i>
                            </div>
                            <div>
                                <h4 style="margin-bottom: 10px;">Endereço</h4>
                                <p style="color: var(--color-gray-light);">
                                    Av. Paulista, 1000<br>
                                    Bela Vista, São Paulo - SP<br>
                                    CEP: 01310-100
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="admin-table-container" style="padding: 30px;">
                        <div style="display: flex; align-items: flex-start; gap: 20px;">
                            <div style="width: 60px; height: 60px; background: var(--color-black); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-phone-alt" style="font-size: 1.5rem; color: var(--color-primary);"></i>
                            </div>
                            <div>
                                <h4 style="margin-bottom: 10px;">Telefones</h4>
                                <p style="color: var(--color-gray-light);">
                                    (11) 99999-9999<br>
                                    (11) 3333-3333<br>
                                    <small style="color: var(--color-gray);">WhatsApp disponível</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="admin-table-container" style="padding: 30px;">
                        <div style="display: flex; align-items: flex-start; gap: 20px;">
                            <div style="width: 60px; height: 60px; background: var(--color-black); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-envelope" style="font-size: 1.5rem; color: var(--color-primary);"></i>
                            </div>
                            <div>
                                <h4 style="margin-bottom: 10px;">E-mail</h4>
                                <p style="color: var(--color-gray-light);">
                                    contato@royaltech.com.br<br>
                                    suporte@royaltech.com.br<br>
                                    vendas@royaltech.com.br
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="admin-table-container" style="padding: 30px;">
                        <div style="display: flex; align-items: flex-start; gap: 20px;">
                            <div style="width: 60px; height: 60px; background: var(--color-black); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-clock" style="font-size: 1.5rem; color: var(--color-primary);"></i>
                            </div>
                            <div>
                                <h4 style="margin-bottom: 10px;">Horário de Funcionamento</h4>
                                <p style="color: var(--color-gray-light);">
                                    Segunda a Sexta: 09h às 18h<br>
                                    Sábado: 09h às 13h<br>
                                    Domingo: Fechado
                                </p>
                            </div>
                        </div>
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
