<?php
// Navegação lateral compartilhada das páginas institucionais.
// Define $current_inst na página antes de incluir para marcar o item ativo.
$instLinks = [
    'faq' => ['faq.php', 'fa-circle-question', 'Perguntas Frequentes'],
    'shipping' => ['shipping.php', 'fa-truck-fast', 'Frete e Entrega'],
    'returns' => ['returns.php', 'fa-rotate-left', 'Trocas e Devoluções'],
    'terms' => ['terms.php', 'fa-file-contract', 'Termos de Uso'],
    'privacy' => ['privacy.php', 'fa-shield-halved', 'Política de Privacidade'],
    'careers' => ['careers.php', 'fa-briefcase', 'Trabalhe Conosco'],
];
$current = $current_inst ?? '';
?>
<nav class="inst-nav" aria-label="Páginas institucionais">
    <?php foreach ($instLinks as $key => [$href, $icon, $label]): ?>
    <a href="<?php echo $href; ?>" class="<?php echo $current === $key ? 'active' : ''; ?>">
        <i class="fas <?php echo $icon; ?>"></i> <?php echo $label; ?>
    </a>
    <?php endforeach; ?>
</nav>
