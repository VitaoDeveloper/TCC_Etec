<?php

declare(strict_types=1);

/**
 * Shell das telas da área da conta (perfil, pedidos, detalhe do pedido).
 *
 * Existe para resolver três problemas concretos:
 *
 *  1. Cada tela repetia o <section class="ml-section"> com largura fixa
 *     e cada uma escolhia um layout diferente — o perfil ficava em
 *     600px e os pedidos não tinham coluna lateral nenhuma.
 *  2. A navegação da conta estava espalhada: o perfil linkava para
 *     pedidos e contatos em dois botões soltos no rodapé do formulário,
 *     e nenhuma tela da conta sabia onde estava.
 *  3. O $base_path = '../../' à mão quebrava os assets.
 *
 * Uso: account_layout_head() antes do conteúdo, account_layout_foot()
 * depois. Entre os dois fica o HTML da tela, sem <html>/<head>/<body>:
 * quem emite o documento é o header/footer que já têm o bloco de
 * integridade de assets protegido.
 */

require_once __DIR__ . '/url_helpers.php';
require_once __DIR__ . '/csrf.php';

/**
 * Exige sessão e devolve a linha do usuário.
 *
 * Fica como função, e não como guarda no topo do arquivo, por dois
 * motivos: um require com efeito colateral impede o arquivo de ser
 * incluído em teste ou em qualquer contexto que não seja a página, e o
 * exit() derrubaria o processo inteiro silenciosamente.
 *
 * Toda tela da conta chama isto antes de account_layout_head().
 */
function account_require_login(PDO $pdo): array
{
    if (empty($_SESSION['user_id'])) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . base_url('pages/auth/login.php') . '?next=' . urlencode($next));
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Sessão apontando para usuário que não existe mais: limpamos
        // em vez de manter um menu com nome vazio.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                (bool) $p['secure'],
                (bool) $p['httponly']
            );
        }
        session_destroy();

        header('Location: ' . base_url('pages/auth/login.php'));
        exit;
    }

    return $user;
}

/**
 * Links da navegação da conta, na ordem em que aparecem na sidebar.
 *
 * Cada item declara o script que o marca como ativo. A comparação é
 * pelo basename do script, e não pelo rótulo, para o item continuar
 * certo mesmo depois de reorganizar os arquivos.
 */
function account_nav_items(): array
{
    return [
        [
            'key'     => 'perfil',
            'label'   => 'Meu Perfil',
            'icon'    => 'fa-user',
            'script'  => 'profile.php',
            'section' => 'Conta',
        ],
        [
            'key'     => 'pedidos',
            'label'   => 'Meus Pedidos',
            'icon'    => 'fa-box-open',
            'script'  => 'orders.php',
            'section' => 'Compras',
        ],
        [
            'key'     => 'compras-sair',
            'label'   => 'Sair da Conta',
            'icon'    => 'fa-right-from-bracket',
            'script'  => 'logout.php',
            'section' => 'Conta',
        ],
    ];
}

/**
 * Iniciais para o fallback do avatar.
 *
 * Pega a primeira letra do primeiro e do último nome: "Kauã Caetano"
 * vira "KC". Uma letra só fica KC em vez de K, que é ambíguo demais
 * numa lista de clientes. Nome de uma palavra só também rende duas
 * letras ("Kauã" -> "KA") para manter o círculo com o mesmo peso.
 */
function avatar_initials(?string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');

    if ($name === '') {
        return '?';
    }

    $parts = array_values(array_filter(explode(' ', $name)));

    if (count($parts) === 1) {
        $first = mb_substr($parts[0], 0, 1, 'UTF-8');
        $last  = mb_substr($parts[0], 1, 1, 'UTF-8');

        return mb_strtoupper($first . $last, 'UTF-8');
    }

    $first = mb_substr($parts[0], 0, 1, 'UTF-8');
    $last  = mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');

    return mb_strtoupper($first . $last, 'UTF-8');
}

/**
 * URL do avatar, ou '' quando o usuário nunca subiu foto.
 *
 * Avatar gravado que não existe mais no disco também volta '': o
 * caminho quebrado viraria o ícone de imagem do navegador no lugar do
 * círculo com iniciais.
 */
function avatar_url(?string $storedPath): string
{
    $storedPath = trim((string) $storedPath);

    if ($storedPath === '') {
        return '';
    }

    $relative = str_starts_with($storedPath, '/')
        ? ltrim($storedPath, '/')
        : 'assets/' . ltrim($storedPath, 'uploads/');

    $absolute = dirname(__DIR__) . '/' . $relative;

    return is_file($absolute) ? base_url($relative) : '';
}

/**
 * Markup do avatar: foto quando existe, iniciais quando não.
 *
 * As iniciais vão em data-initials em vez de serem escritas direto no
 * onerror: interpolar "KC" dentro de onerror="..." fecha o atributo no
 * primeiro aspas e transforma o resto em atributo inválido, o que
 * quebra o HTML e abre espaço para injeção via nome do usuário.
 */
function render_avatar(array $user, string $sizeClass = 'account-avatar--lg'): string
{
    $name     = (string) ($user['name'] ?? '');
    $initials = avatar_initials($name);
    $url      = avatar_url($user['avatar_path'] ?? null);

    $classes = 'account-avatar ' . $sizeClass;
    $label   = 'Avatar de ' . ($name !== '' ? $name : 'usuário');

    if ($url === '') {
        return sprintf(
            '<span class="%s account-avatar--initials" data-initials="%s" role="img" aria-label="%s">%s</span>',
            e($classes),
            e($initials),
            e($label),
            e($initials)
        );
    }

    // Sem aspas dentro do onerror: tudo o que precisa é lido do DOM.
    $onError = "var a=this.parentNode;"
        . "a.classList.add('account-avatar--initials');"
        . "a.textContent=a.dataset.initials;"
        . "this.remove();";

    return sprintf(
        '<span class="%s" data-initials="%s">'
        . '<img src="%s" alt="%s" class="account-avatar-img" onerror="%s">'
        . '</span>',
        e($classes),
        e($initials),
        e($url),
        e($label),
        e($onError)
    );
}

/**
 * Normaliza um telefone de WhatsApp para o formato do wa.me: só dígitos,
 * com código do país.
 *
 * Separado da leitura de configuração de propósito: store_config() lê
 * o banco, o que torna a função impossível de testar sem montar uma
 * conexão. A regra de formatação é pura e fica testável aqui.
 */
function account_normalize_whatsapp(?string $raw): string
{
    $digits = preg_replace('/\D/', '', (string) $raw) ?? '';

    if ($digits === '') {
        return '';
    }

    // Configuração brasileira com DDD + número (10 ou 11 dígitos) não
    // traz o código do país, então o 55 é acrescentado. Um número que
    // já vem com 55 (13 dígitos) é deixado como está.
    if (strlen($digits) === 10 || strlen($digits) === 11) {
        return '55' . $digits;
    }

    return $digits;
}

/**
 * WhatsApp da loja, para o link wa.me do botão flutuante.
 * Cai para o telefone da loja quando não há WhatsApp configurado.
 */
function account_support_whatsapp(): string
{
    $phone = (string) (store_config('store_whatsapp') ?: '');

    if (trim($phone) === '') {
        $phone = (string) (store_config('store_phone') ?: '');
    }

    return account_normalize_whatsapp($phone);
}

/**
 * Define $base_path para os componentes legados.
 *
 * Precisa ser chamado nas DUAS funções, e não só em head(): o
 * header.php e o footer.php leem $base_path com `?? ''`, e cada um é
 * incluído de dentro de uma função diferente. Deixar só em head()
 * fazia o rodapé inteiro sair com caminho relativo — script.js e
 * theme-extras.css iam pedidos em /assets/... e recebiam 404.
 */
function account_prepare_base_path(): string
{
    $base_path = base_url();
    $GLOBALS['base_path'] = $base_path;

    return $base_path;
}

/**
 * Abre o documento e a grade da conta.
 *
 * @param array $user   linha de e5_users do visitante
 * @param string $active chave de account_nav_items() correspondente à tela
 */
function account_layout_head(array $user, string $active): void
{
    $page_title    = $GLOBALS['page_title'] ?? 'Minha Conta - Royal Tech';
    $page_description = $GLOBALS['page_description'] ?? 'Gerencie seu perfil, pedidos e dados na Royal Tech.';

    // O header.php legado lê $base_path; agora ele sai de base_url(),
    // que é calculado a partir do SCRIPT_NAME e não quebra se a página
    // mudar de diretório.
    $base_path = account_prepare_base_path();

    $navItems    = account_nav_items();
    $currentFile = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    // Agrupa por seção preservando a ordem do array.
    $sections = [];
    foreach ($navItems as $item) {
        $sections[$item['section']][] = $item;
    }

    // O header.php lê este array; sem ele a folha da conta acabaria
    // emitida no <body>, onde o navegador ainda a aplica, mas o HTML
    // fica inválido e o devtools mostra o aviso.
    $extra_head_css = [asset_url('assets/css/account.css')];

    require_once dirname(__DIR__) . '/components/header.php';
    ?>

    <div class="account-shell" data-account-active="<?php echo e($active); ?>">
        <aside class="account-sidebar" id="accountSidebar">
            <div class="account-identity">
                <?php echo render_avatar($user); ?>
                <div class="account-identity-text">
                    <span class="account-identity-name"><?php echo e($user['name'] ?? ''); ?></span>
                    <span class="account-identity-mail"><?php echo e($user['email'] ?? ''); ?></span>
                </div>
            </div>

            <nav class="account-nav" aria-label="Navegação da conta">
                <?php foreach ($sections as $sectionLabel => $items): ?>
                    <div class="account-nav-group">
                        <span class="account-nav-heading"><?php echo e($sectionLabel); ?></span>
                        <?php foreach ($items as $item):
                            $isCurrent = $item['script'] === $currentFile;
                        ?>
                            <a href="<?php echo e(base_url('pages/auth/' . $item['script'])); ?>"
                               class="account-nav-link<?php echo $isCurrent ? ' is-current' : ''; ?>"
                               <?php echo $isCurrent ? 'aria-current="page"' : ''; ?>>
                                <i class="fas <?php echo e($item['icon']); ?>" aria-hidden="true"></i>
                                <span><?php echo e($item['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div class="account-overlay" id="accountOverlay" hidden></div>

        <main class="account-main" id="accountMain">
            <button type="button" class="account-menu-toggle" id="accountMenuToggle"
                    aria-controls="accountSidebar" aria-expanded="false">
                <i class="fas fa-bars" aria-hidden="true"></i>
                <span>Menu da conta</span>
            </button>
    <?php
}

/** Fecha a grade e emite o WhatsApp flutuante. */
function account_layout_foot(): void
{
    // O footer.php também lê $base_path; sem repetir aqui, os scripts
    // e a navegação do rodapé saem relativos.
    $base_path = account_prepare_base_path();

    $whatsapp = account_support_whatsapp();
    ?>
        </main>
    </div>

    <?php if ($whatsapp !== ''): ?>
        <a class="account-whatsapp"
           href="https://wa.me/<?php echo e($whatsapp); ?>"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="Falar com a Royal Tech no WhatsApp">
            <i class="fab fa-whatsapp" aria-hidden="true"></i>
            <span class="account-whatsapp-label">Fale conosco</span>
        </a>
    <?php endif; ?>

    <script>
    (function () {
        var toggle = document.getElementById('accountMenuToggle');
        var sidebar = document.getElementById('accountSidebar');
        var overlay = document.getElementById('accountOverlay');

        if (!toggle || !sidebar || !overlay) return;

        function setOpen(open) {
            sidebar.classList.toggle('is-open', open);
            overlay.hidden = !open;
            document.body.classList.toggle('account-nav-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        toggle.addEventListener('click', function () {
            setOpen(!sidebar.classList.contains('is-open'));
        });

        overlay.addEventListener('click', function () { setOpen(false); });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setOpen(false);
        });
    })();
    </script>
    <?php

    require_once dirname(__DIR__) . '/components/footer.php';
}
