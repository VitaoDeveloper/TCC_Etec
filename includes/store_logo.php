<?php
// =============================================================================
// LOGO DA TELA DE PAGAMENTO — facilmente substituível.
//
// PARA TROCAR O LOGO (sem mexer em código):
//   Basta colocar um arquivo de imagem em assets/img/ na ordem de prioridade:
//     1. assets/img/logo-payment.svg
//     2. assets/img/logo-payment.png
//     3. assets/img/logo.svg
//     4. assets/img/logo.png
//   O primeiro que existir é usado automaticamente no header e no card.
//   Nenhum arquivo = marca geométrica dourada padrão (SVG inline).
//
//   Dica: para ficar perfeito dentro do quadrado dourado, use um asset com
//   fundo transparente e o símbolo centralizado com margem.
// =============================================================================

function rt_payment_logo(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $root = dirname(__DIR__);
    $overrides = ['logo-payment.svg', 'logo-payment.png', 'logo.svg', 'logo.png'];

    foreach ($overrides as $name) {
        if (file_exists($root . '/assets/img/' . $name)) {
            $src = htmlspecialchars('../../assets/img/' . $name, ENT_QUOTES, 'UTF-8');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $alt = htmlspecialchars('Logotipo Royal Tech', ENT_QUOTES, 'UTF-8');
            $cache = '<img src="' . $src . '" alt="' . $alt . '" class="rt-logo-img"' . ($ext === 'svg' ? '' : ' loading="lazy"') . '>';
            return $cache;
        }
    }

    // Padrão: marca geométrica dourada (hexágono + R)
    $cache = '<svg class="rt-logo-svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
        . '<path d="M12 2.6 20 7.3v9.4l-8 4.7-8-4.7V7.3L12 2.6Z" fill="#3a2b00"/>'
        . '<path d="M12 6.1 17.2 8.9v5.8L12 17.5 6.8 14.7V8.9L12 6.1Z" fill="#ffdf7e"/>'
        . '<path d="M12 9.2 14.6 10.6v2.4L12 14.4 9.4 13V10.6L12 9.2Z" fill="#2a1f00"/>'
        . '</svg>';
    return $cache;
}