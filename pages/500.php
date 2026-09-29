<?php
/**
 * Página de erro 500 (Erro Interno do Servidor).
 *
 * IMPORTANTE: esta página é intencionalmente AUTOCONTIDA — não usa
 * components/header.php, footer.php, session_start() nemincludes/*.php.
 * Ela precisa renderizar mesmo quando a falha que a disparou veio de
 * um desses arquivos. Se ela dependesse do mesmo código quebrado, o
 * usuário receberia um 500 dentro de um 500.
 *
 * Servida via ErrorDocument no .htaccess.
 */

http_response_code(500);

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath   = '/';

// Descobre a raiz da aplicação a partir do caminho do script.
// pages/500.php fica um nível abaixo da raiz, então o app vive em dirname(dirname()).
if (is_string($scriptName) && $scriptName !== '' && $scriptName[0] === '/') {
    $dir = str_replace('\\', '/', dirname(dirname($scriptName)));
    if ($dir !== '/' && $dir !== '.' && $dir !== '..') {
        $basePath = rtrim($dir, '/') . '/';
    }
}

$e = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

// Só mostra o detalhe do erro em desenvolvimento. Nunca vazar stack trace
// ou caminho absoluto em produção: isso entrega estrutura de pastas,
// nomes de tabelas e versões de biblioteca para quem está sondando o app.
//
// Usa getenv() como fallback porque, sob Apache/mod_php, o $_ENV quase
// sempre vem vazio (variables_order costuma ser "GPCS", sem o "E").
$showDebug = filter_var(getenv('APP_DEBUG') ?: ($_ENV['APP_DEBUG'] ?? 'false'), FILTER_VALIDATE_BOOL);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Erro interno - Royal Tech</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background-color: #1a1a1a; color: #e0e0e0; padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        .rt-500 { max-width: 620px; width: 100%; text-align: center; }
        .rt-500__code {
            font-family: 'Playfair Display', Georgia, serif; font-size: 120px; font-weight: 700;
            line-height: 1; color: #d4af37; margin-bottom: 20px;
        }
        .rt-500 h1 { font-size: 28px; margin: 0 0 15px; color: #e0e0e0; }
        .rt-500 p { color: #999999; font-size: 16px; line-height: 1.6; margin: 0 0 30px; }
        .rt-500__actions { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .rt-btn {
            display: inline-block; padding: 12px 26px; border-radius: 6px; text-decoration: none;
            font-size: 15px; font-weight: 600; border: 1px solid #333333; color: #e0e0e0;
            transition: background-color .2s, border-color .2s;
        }
        .rt-btn:hover { background-color: #2a2a2a; border-color: #555555; }
        .rt-btn--primary { background-color: #d4af37; border-color: #d4af37; color: #1a1a1a; }
        .rt-btn--primary:hover { background-color: #b8962e; border-color: #b8962e; }
        .rt-500__ref {
            margin-top: 28px; padding-top: 20px; border-top: 1px solid #333333;
            color: #777777; font-size: 13px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .rt-500__debug {
            margin-top: 20px; padding: 14px; background-color: #222222; border: 1px solid #333333;
            border-radius: 6px; text-align: left; color: #ff6161; font-size: 12.5px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            white-space: pre-wrap; word-break: break-word; max-height: 260px; overflow: auto;
        }
    </style>
</head>
<body>
    <main class="rt-500">
        <div class="rt-500__code">500</div>
        <h1>Algo deu errado do nosso lado</h1>
        <p>
            Não conseguimos concluir sua solicitação por um erro interno. O problema já
            foi registrado e estamos trabalhando para resolvê-lo. Tente novamente em instantes.
        </p>

        <div class="rt-500__actions">
            <a href="<?= $e($basePath) ?>index.php" class="rt-btn rt-btn--primary">Ir para o in&iacute;cio</a>
            <a href="<?= $e($basePath) ?>pages/products/products.php" class="rt-btn">Ver produtos</a>
            <a href="<?= $e($basePath) ?>pages/products/contact.php" class="rt-btn">Falar com o suporte</a>
        </div>

        <div class="rt-500__ref">
            <?php if ($showDebug): ?>
                <?= $e($scriptName) ?><br>
                <?= $e(PHP_SAPI) ?><br>
                <?= $e($_SERVER['REQUEST_METHOD'] ?? '?') ?>
                <?= isset($_SERVER['REQUEST_URI']) ? ' ' . $e($_SERVER['REQUEST_URI']) : '' ?>
            <?php else: ?>
                Se o problema persistir, informe o hor&aacute;rio e a p&aacute;gina ao suporte.
            <?php endif; ?>
        </div>

        <?php if ($showDebug): ?>
            <div class="rt-500__debug"><?= $e($scriptName) ?>

status: <?= $e((string) ($_SERVER['REDIRECT_REDIRECT_STATUS'] ?? $_SERVER['REDIRECT_STATUS'] ?? '500')) ?>

sapi: <?= $e(PHP_SAPI) ?></div>
        <?php endif; ?>
    </main>
</body>
</html>
