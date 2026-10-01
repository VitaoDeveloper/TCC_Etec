<?php

/**
 * Stage 5 — checklist de verificação das telas da conta.
 *
 * Renderiza as três telas reais (perfil, pedidos, detalhe) como o
 * usuário 16 e confere item por item o checklist de docs/UI_SPEC.md.
 *
 *   /opt/lampp/bin/php check_list.php
 *
 * Sai com 0 se tudo passar, 1 se algum item falhar.
 */

declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '';
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

// A sessão é montada APENAS no processo filho (render_page.php). Abrir
// sessão aqui trancaria o arquivo de sessão e os filhos ficariam
// bloqueados esperando — deadlock e timeout.

require_once __DIR__ . '/database/connection.php';

/** Renderiza uma página real de /pages/auth em processo isolado. */
function renderPage(string $script, array $get = []): string
{
    $out = tempnam(sys_get_temp_dir(), 'stage5_');

    $cmd = sprintf(
        '%s %s %s %s %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__DIR__ . '/tests/fixtures/render_page.php'),
        escapeshellarg($script),
        escapeshellarg((string) json_encode($get)),
        escapeshellarg($out)
    );

    exec($cmd, $lines, $code);
    $html = is_file($out) ? (string) file_get_contents($out) : '';
    @unlink($out);

    if ($code !== 0 || $html === '') {
        fwrite(STDERR, "falha ao renderizar {$script}:\n" . implode("\n", $lines) . "\n");
        exit(1);
    }

    return $html;
}

function xpath(string $html): DOMXPath
{
    static $cache = [];

    if (!isset($cache[$html])) {
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $cache[$html] = new DOMXPath($doc);
    }

    return $cache[$html];
}

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  OK   {$label}\n";
    } else {
        $fail++;
        echo " FAIL  {$label}\n";
    }
}

$profile = renderPage('profile.php');
$orders  = renderPage('orders.php');
$detail  = renderPage('order-detail.php', ['id' => '12']);

$xpProfile = xpath($profile);
$xpOrders  = xpath($orders);
$xpDetail  = xpath($detail);

echo "== Perfil ==\n";

check('título "Meu Perfil"', str_contains($profile, 'Meu Perfil'));
check('subtítulo "Gerencie seus dados pessoais e preferências atualizadas"',
    str_contains($profile, 'Gerencie seus dados pessoais e preferências atualizadas'));
check('sem card Menu/abas/avatar',
    $xpProfile->query("//*[@id='secao-menu']")->length === 0
    && $xpProfile->query("//*[@id='secao-avatar']")->length === 0
    && $xpProfile->query("//*[@id='tab-dados']")->length === 0);
check('seções na ordem da spec',
    array_map(
        fn(DOMElement $n): string => (string) $n->getAttribute('id'),
        iterator_to_array($xpProfile->query('//section[contains(@id,"secao-")]'))
    ) === [
        'secao-dados', 'secao-endereco', 'secao-enderecos-salvos',
        'secao-senha', 'secao-avisos', 'secao-cartoes-salvos',
    ]);
check('um único botão "Salvar Alterações"',
    substr_count($profile, 'Salvar Alterações') === 1);
check('sem botões de página "Salvar dados/endereço/preferências"',
    !preg_match('/>\s*Salvar (dados|preferências)\s*</', preg_replace('/<form[^>]*class="account-modal[\s\S]*?<\/form>/', '', $profile)));
check('nenhum campo "SENHA ATUAL" duplicado (1 campo disabled)',
    $xpProfile->query("//input[@type='password' and @disabled]")->length === 1
    && !str_contains($profile, 'Sua senha nunca aparece por aqui'));
check('card-header com ícone + título à esquerda',
    $xpProfile->query("//*[contains(@class,'account-card-head')]//*[contains(@class,'account-card-icon')]")->length >= 5
    && $xpProfile->query("//*[contains(@class,'account-card-head')]//*[contains(@class,'account-card-title')]")->length >= 5);
check('notificações: 2 toggles sem botão',
    $xpProfile->query("//form[@data-notifications]//input[@type='checkbox']")->length === 2
    && $xpProfile->query("//form[@data-notifications]//button[@type='submit']")->length === 0);
check('modais de endereço e cartão existem e começam ocultos',
    $xpProfile->query("//*[@id='modal-address' and @hidden]")->length === 1
    && $xpProfile->query("//*[@id='modal-card' and @hidden]")->length === 1);
check('sem textos de ajuda fixos sob os campos',
    !str_contains($profile, 'JPG, PNG ou WebP')
    && !str_contains($profile, 'Usado no rastreio do pedido e nos avisos'));

echo "\n== Sidebar (todas as telas) ==\n";

foreach (['perfil' => $profile, 'pedidos' => $orders, 'detalhe' => $detail] as $key => $html) {
    $xp = xpath($html);
    check("{$key}: 3 itens de menu sem seções",
        $xp->query("//nav[contains(@class,'account-nav')]//a")->length === 3
        && $xp->query("//nav[contains(@class,'account-nav')]//*[contains(@class,'account-nav-group')]|//nav[contains(@class,'account-nav')]//*[contains(@class,'account-nav-heading')]")->length === 0);
    check("{$key}: rótulos [Meu Perfil] [Meus Pedidos] [Sair]",
        str_contains($html, 'Meu Perfil') && str_contains($html, 'Meus Pedidos') && str_contains($html, '>Sair<'));
    check("{$key}: breadcrumb presente", $xp->query("//nav[contains(@class,'account-breadcrumb')]")->length === 1);
}

check('pill ADMINISTRADOR para admin', str_contains($profile, 'ADMINISTRADOR'));

echo "\n== Meus Pedidos ==\n";

check('sem cards de política de cancelamento',
    $xpOrders->query("//*[contains(@class,'account-cancel-card')]")->length === 0
    && $xpOrders->query("//*[@id='secao-cancelamento']")->length === 0);
check('contador "N pedido(s)"', (bool) preg_match('/\d+\s+pedido\(s\)/', $orders));
check('campo de busca por nº', $xpOrders->query("//form[contains(@class,'account-search')]//input[@name='q']")->length === 1);
check('uma única tabela em um único card',
    $xpOrders->query("//*[@id='secao-pedidos']//table")->length === 1
    && $xpOrders->query("//*[@id='secao-pedidos']//*[contains(@class,'account-card')]")->length === 0);
check('abas de status com contadores',
    $xpOrders->query("//*[@id='secao-abas']//a[contains(@class,'account-tab')]")->length === 7);
check('sem botões de rodapé Meu Perfil/Meus Contatos',
    $xpOrders->query("//a[contains(.,'Meus Contatos')]")->length === 0
    && !preg_match('/>\s*Meu Perfil\s*</', preg_replace('/<nav[\s\S]*?<\/nav>/', '', $orders)));

echo "\n== Detalhe do pedido #0012 ==\n";

check('título "Pedido #0012"',
    (bool) preg_match('/Pedido #0012/', $detail));
check('faixa vermelha "Pedido cancelado"',
    $xpDetail->query("//*[contains(@class,'account-banner--danger')]")->length === 1
    && str_contains($detail, 'Pedido cancelado'));
check('itens com snapshot (Smartphone Galaxy S25 256GB)',
    str_contains($detail, 'Smartphone Galaxy S25 256GB'));
check('total R$ 4.599,90', str_contains($detail, '4.599,90'));
check('cards Entrega e Pagamento lado a lado',
    str_contains($detail, '>Entrega<') && str_contains($detail, '>Pagamento<')
    && $xpDetail->query("//*[contains(@class,'account-pair')]")->length === 1);
check('endereço Esplanada Santa Helena, Taubaté/SP, 12053-831',
    str_contains($detail, 'Esplanada Santa Helena')
    && str_contains($detail, 'Taubaté/SP')
    && str_contains($detail, '12053-831'));
check('método PAC grátis', str_contains($detail, 'PAC') && str_contains($detail, 'Grátis'));
check('método Pagamento Pix', str_contains($detail, 'Pix'));
check('botões Voltar / Baixar comprovante / Reenviar por e-mail',
    preg_match('/>\s*Voltar\s*</', $detail) === 1
    && $xpDetail->query("//a[contains(@href,'download-comprovante.php')]")->length === 1
    && $xpDetail->query("//form[contains(@action,'comprovante-resend.php')]")->length === 1);
check('pedido pago/cancelado sem botão "Pagar agora"',
    $xpDetail->query("//a[contains(.,'Pagar agora')]")->length === 0);
// order_can_cancel() nega 'canceled': a tela não oferece cancelar de novo.
check('sem botão Cancelar em pedido cancelado', $xpDetail->query("//form//input[@name='action'][@value='cancel']")->length === 0);
check('sem frase da regra em pedido cancelado', $xpDetail->query("//*[contains(@class,'account-cancel-rule')]")->length === 0);
check('sem card de política no detalhe',
    $xpDetail->query("//*[contains(@class,'account-cancel-card')]")->length === 0);
check('status em português (nenhum texto de status em inglês)',
    !preg_match('/\b(pending|paid|shipped|delivered|canceled|processing)\b/', strip_tags($detail)));

echo "\n== worker.php ==\n";

// --help sai imediatamente e não toca em SMTP nem em banco: é o caminho
// mais rápido de provar que o script roda via CLI sem arriscar timeout
// em servidor de e-mail.
$workerOut = [];
exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/worker.php') . ' --help 2>&1',
    $workerOut,
    $workerRc
);
check('worker.php roda via CLI (exit 0)', $workerRc === 0);
check('worker.php documenta as flags', str_contains(implode("\n", $workerOut), '--only=emails|pix'));
check('worker.php recusa chamada web', str_contains(
    (string) @file_get_contents(__DIR__ . '/worker.php'),
    "PHP_SAPI !== 'cli'"
));

echo "\n----------------------------------------\n";
printf("aprovados: %d  reprovados: %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
