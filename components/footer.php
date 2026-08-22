<?php require_once dirname(__DIR__) . '/includes/config.php'; ?>
    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-top">
            <div class="container">
                <div class="footer-grid">
                    <div class="footer-col">
                        <div class="footer-logo">
                            <span class="logo-icon"><i class="fas fa-crown"></i></span>
                            <span class="logo-text">Royal<span>Tech</span></span>
                        </div>
                        <p class="footer-desc">Sua loja de tecnologia premium. Oferecemos os melhores produtos tecnológicos com qualidade e atendimento diferenciado.</p>
                        <div class="footer-social">
                            <a href="<?php echo htmlspecialchars($socialLinks['facebook'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>"><i class="fab fa-facebook-f"></i></a>
                            <a href="<?php echo htmlspecialchars($socialLinks['instagram'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>"><i class="fab fa-instagram"></i></a>
                            <a href="<?php echo htmlspecialchars($socialLinks['twitter'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>"><i class="fab fa-twitter"></i></a>
                            <a href="<?php echo htmlspecialchars($socialLinks['youtube'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                    
                    <div class="footer-col">
                        <h4>Institucional</h4>
                        <ul>
                            <li><a href="<?php echo $base_path ?? ''; ?>pages/products/about.php">Sobre Nós</a></li>
                            <li><a href="#">Trabalhe Conosco</a></li>
                            <li><a href="#">Termos de Uso</a></li>
                            <li><a href="#">Política de Privacidade</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-col">
                        <h4>Atendimento</h4>
                        <ul>
                            <li><a href="<?php echo $base_path ?? ''; ?>pages/products/contact.php">Fale Conosco</a></li>
                            <li><a href="#">Perguntas Frequentes</a></li>
                            <li><a href="#">Frete e Entrega</a></li>
                            <li><a href="#">Trocas e Devoluções</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-col">
                        <h4>Contato</h4>
                        <ul class="contact-info">
                            <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(store_config('store_address'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars(store_config('store_phone'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars(store_config('store_email'), ENT_QUOTES, 'UTF-8'); ?></li>
                            <li><i class="fas fa-clock"></i> Seg-Sex: 09h às 18h</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="container">
                <div class="footer-bottom-content">
                    <p>&copy; 2026 Royal Tech. Todos os direitos reservados.</p>
                    <div class="payment-methods">
                        <span><i class="fab fa-cc-visa"></i></span>
                        <span><i class="fab fa-cc-mastercard"></i></span>
                        <span><i class="fab fa-cc-amex"></i></span>
                        <span><i class="fab fa-cc-paypal"></i></span>
                        <span><i class="fab fa fa-barcode"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Flutuante -->
    <a href="https://wa.me/5511999999999" class="whatsapp-float" target="_blank">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Scripts -->
    <script src="<?php echo $base_path ?? ''; ?>assets/js/script.js"></script>
</body>
</html>
