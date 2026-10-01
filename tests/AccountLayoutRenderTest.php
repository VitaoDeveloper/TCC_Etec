<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;
use DOMDocument;
use DOMXPath;

/**
 * Teste de renderização do shell da conta.
 *
 * Os testes de AccountLayoutTest cobrem as funções isoladas. Este aqui
 * monta a página inteira — header.php e footer.php legados inclusos —
 * e confere o HTML resultante.
 *
 * O motivo é concreto: header.php e footer.php leem a variável
 * $base_path com `?? ''`, e cada um é incluído de dentro de uma função
 * diferente. Quando $base_path ficava só em uma delas, o rodapé inteiro
 * saía com caminho relativo: o navegador pedia /assets/js/script.js e
 * recebia 404, sem nenhum erro no log do PHP. Nenhum teste de função
 * unitária pega isso; só a página montada pega.
 */
class AccountLayoutRenderTest extends TestCase
{
    private static string $html = '';
    private static string $adminHtml = '';

    public static function setUpBeforeClass(): void
    {
        self::$html      = self::render('customer');
        self::$adminHtml = self::render('admin');
    }

    private static function render(string $role): string
    {
        $out = tempnam(sys_get_temp_dir(), 'account-layout-');

        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/fixtures/render_account_layout.php'),
            escapeshellarg($out),
            escapeshellarg($role)
        );

        exec($command, $lines, $code);

        $rendered = is_file($out) ? (string) file_get_contents($out) : '';
        @unlink($out);

        if ($code !== 0 || $rendered === '') {
            self::markTestSkipped(
                'nao foi possivel renderizar o shell (precisa do MySQL): '
                . implode("\n", $lines)
            );
        }

        return $rendered;
    }

    private function dom(?string $html = null): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $doc->loadHTML($html ?? self::$html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    // =================================================================
    //  Shell
    // =================================================================

    public function testPageHasExactlyOneHtmlHeadAndBody(): void
    {
        $doc = $this->dom()->query('/html')->item(0);
        $this->assertNotNull($doc, 'o shell nao abriu <html>');

        $this->assertSame(1, $this->dom()->query('//html')->length);
        $this->assertSame(1, $this->dom()->query('//head')->length);
        $this->assertSame(1, $this->dom()->query('//body')->length);
    }

    public function testTitleComesFromThePage(): void
    {
        $title = $this->dom()->query('//title')->item(0);

        $this->assertNotNull($title);
        $this->assertSame('Meu Perfil - Royal Tech', trim($title->textContent));
    }

    public function testAccountStylesheetIsInsideHeadNotBody(): void
    {
        $links = $this->dom()->query('//link[@rel="stylesheet"]');

        $inHead = 0;
        $found  = false;

        foreach ($links as $link) {
            if (!str_contains($link->getAttribute('href'), 'account.css')) {
                continue;
            }

            $found = true;
            if ($link->parentNode->localName === 'head') {
                $inHead++;
            }
        }

        $this->assertTrue($found, 'a folha da conta nao foi vinculada');
        $this->assertSame(1, $inHead, 'a folha da conta precisa estar no <head>');
    }

    public function testPageContentSitsBetweenHeadAndFoot(): void
    {
        $this->assertSame(1, $this->dom()->query('//main[@class="account-main"]')->length);
        $this->assertSame(
            1,
            $this->dom()->query('//h1[contains(@class,"account-page-title")]')->length,
            'o conteudo da pagina foi perdido entre head() e foot()'
        );
    }

    // =================================================================
    //  Grade e navegação
    // =================================================================

    public function testGridHasSidebarMainAndOverlay(): void
    {
        $this->assertSame(1, $this->dom()->query('//div[contains(@class,"account-shell")]')->length);
        $this->assertSame(1, $this->dom()->query('//aside[contains(@class,"account-sidebar")]')->length);
        $this->assertSame(1, $this->dom()->query('//div[@id="accountOverlay"]')->length);
    }

    public function testEverySidebarLinkIsAbsolute(): void
    {
        foreach ($this->dom()->query('//nav[@class="account-nav"]//a') as $link) {
            $href = $link->getAttribute('href');

            $this->assertStringStartsWith(
                '/TCC_Etec/',
                $href,
                "link da sidebar saiu relativo: {$href}"
            );
        }
    }

    public function testCurrentPageIsMarkedInTheSidebar(): void
    {
        $current = $this->dom()->query('//nav[@class="account-nav"]//a[contains(@class,"is-current")]');

        $this->assertSame(1, $current->length, 'a sidebar precisa marcar exatamente um item');
        $this->assertSame('Meu Perfil', trim($current->item(0)->textContent));
        $this->assertSame('page', $current->item(0)->getAttribute('aria-current'));
    }

    public function testIdentityShowsTheLoggedUser(): void
    {
        $name = $this->dom()->query('//span[contains(@class,"account-identity-name")]')->item(0);

        $this->assertNotNull($name, 'a sidebar nao mostrou o nome do usuario');
        $this->assertNotSame('', trim($name->textContent));
    }

    public function testAdminPillOnlyShowsForAdminRole(): void
    {
        $this->assertSame(
            0,
            $this->dom()->query('//span[@data-account-pill="admin"]')->length,
            'cliente comum nao pode ver a pilula de admin'
        );

        $pill = $this->dom(self::$adminHtml)->query('//span[@data-account-pill="admin"]')->item(0);

        $this->assertNotNull($pill, 'a pilula de admin nao apareceu para role=admin');
        $this->assertSame('ADMINISTRADOR', trim(preg_replace('/\s+/', ' ', $pill->textContent) ?? ''));
    }

    // =================================================================
    //  Avatar
    // =================================================================

    public function testAvatarFallsBackToInitialsForUserWithoutPhoto(): void
    {
        // O usuario 16 e o do seed, sem avatar: e o caso que todo
        // cliente novo cai.
        $avatar = $this->dom()->query('//span[contains(@class,"account-avatar--initials")]')->item(0);

        $this->assertNotNull($avatar, 'sem avatar, o fallback para iniciais nao apareceu');
        $this->assertSame(
            2,
            mb_strlen(trim($avatar->textContent), 'UTF-8'),
            'as iniciais do fallback deveriam ter duas letras'
        );
    }

    // =================================================================
    //  Assets — a regressão que motivou este arquivo
    // =================================================================

    /**
     * @dataProvider assetProvider
     */
    public function testLocalAssetUsesAbsolutePath(string $pattern, string $label): void
    {
        $found = false;

        foreach ($this->dom()->query('//link[@rel="stylesheet"] | //script[@src] | //img[@src]') as $node) {
            $attr  = $node->hasAttribute('href') ? 'href' : 'src';
            $value = $node->getAttribute($attr);

            if (!preg_match($pattern, $value)) {
                continue;
            }

            $found = true;
            $this->assertStringStartsWith(
                '/TCC_Etec/',
                $value,
                "{$label} saiu com caminho relativo e vai pedir 404: {$value}"
            );
        }

        if (!$found) {
            $this->markTestIncomplete("{$label} nao aparece no shell renderizado");
        }
    }

    public static function assetProvider(): array
    {
        return [
            'script principal' => ['#script\.js#', 'assets/js/script.js'],
            'theme extras'     => ['#theme-extras#', 'assets de theme-extras'],
            'tokens'           => ['#tokens\.css#', 'assets/css/tokens.css'],
            'conta'            => ['#account\.css#', 'assets/css/account.css'],
        ];
    }

    public function testFooterNavigationIsAlsoAbsolute(): void
    {
        // O rodapé tem os mesmos links que a barra de categorias; ele
        // é incluído de outra função e foi justamente o que ficou
        // relativo quando $base_path só era definido em head().
        $footerLinks = $this->dom()->query('//footer//a[@href]');

        $this->assertGreaterThan(0, $footerLinks->length, 'o rodapé saiu sem links');

        foreach ($footerLinks as $link) {
            $href = $link->getAttribute('href');

            if ($href === '#' || $href === '' || str_starts_with($href, 'http')) {
                continue;
            }

            $this->assertStringStartsWith(
                '/TCC_Etec/',
                $href,
                "link do rodapé saiu relativo: {$href}"
            );
        }
    }

    public function testProtectedAssetIntegrityBlockSurvives(): void
    {
        // O bloco de theme-extras é conferido por hash no CI; o shell
        // da conta não pode ter mexido nele.
        $this->assertStringContainsString(
            'BEGIN THEME EXTRAS ASSETS (PROTEGIDO)',
            self::$html
        );
    }

    // =================================================================
    //  WhatsApp flutuante
    // =================================================================

    public function testWhatsappButtonPointsToWaMeWithDigitsOnly(): void
    {
        $button = $this->dom()->query('//a[contains(@class,"account-whatsapp")]')->item(0);

        if ($button === null) {
            $this->markTestSkipped('loja sem WhatsApp e sem telefone configurados');
        }

        $href = $button->getAttribute('href');

        $this->assertMatchesRegularExpression(
            '#^https://wa\.me/[0-9]+$#',
            $href,
            "o link do WhatsApp precisa ser wa.me so com digitos: {$href}"
        );
        $this->assertSame('_blank', $button->getAttribute('target'));
    }
}
