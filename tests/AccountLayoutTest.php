<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/url_helpers.php';
require_once __DIR__ . '/../includes/account_layout.php';

/**
 * Testes do shell da área da conta.
 *
 * Concentram-se nas partes que quebram em silêncio: o fallback de
 * avatar (que aparece para todo mundo que não tem foto), a detecção do
 * item ativo da navegação (que depende do nome do arquivo e por isso
 * quebra quando os arquivos são reorganizados) e o número de WhatsApp
 * (que vem de configuração e às vezes vem sem o código do país).
 */
class AccountLayoutTest extends TestCase
{
    // =================================================================
    //  avatar_initials()
    // =================================================================

    public function testInitialsTakeFirstAndLastName(): void
    {
        $this->assertSame('KC', avatar_initials('Kauã Caetano'));
    }

    public function testInitialsIgnoreMiddleNames(): void
    {
        // Primeiro e do último: "João da Silva Junior" -> "JJ".
        $this->assertSame('JJ', avatar_initials('João da Silva Junior'));
    }

    public function testInitialsSkipExtraWhitespace(): void
    {
        // Espaco duplo entre nomes vinha de colunas do banco e quebrava
        // a contagem de partes, produzindo "J" em vez de "JS".
        $this->assertSame('JC', avatar_initials('  João   Caetano  '));
    }

    public function testSingleWordNameStillYieldsTwoLetters(): void
    {
        // "K" deixaria o círculo visualmente vazio e ambíguo numa
        // lista; duas letras mantêm o mesmo peso das demais.
        $this->assertSame('KA', avatar_initials('Kauã'));
    }

    public function testInitialsAreUppercaseAndAccentSafe(): void
    {
        $this->assertSame('JP', avatar_initials('joão paula'));
        $this->assertSame('ÁC', avatar_initials('ávila costa'));
    }

    public function testAccentedInitialsCountCharactersNotBytes(): void
    {
        $initials = avatar_initials('álvaro çosta');

        $this->assertSame(2, mb_strlen($initials, 'UTF-8'), 'as iniciais saíram com contagem de byte');
        $this->assertSame('ÁÇ', $initials);
    }

    public function testMultibyteNameIsNotCutInHalf(): void
    {
        // mb_substr conta caractere, não byte: cortar em byte
        // deixaria um caractere accentulado órfão e um glifo perdido.
        $this->assertSame('ÉC', avatar_initials('Érica Conceição'));
    }

    public function testEmptyNameFallsBackToQuestionMark(): void
    {
        $this->assertSame('?', avatar_initials(''));
        $this->assertSame('?', avatar_initials(null));
        $this->assertSame('?', avatar_initials('   '));
    }

    // =================================================================
    //  avatar_url()
    // =================================================================

    public function testEmptyStoredPathMeansNoPhoto(): void
    {
        $this->assertSame('', avatar_url(null));
        $this->assertSame('', avatar_url(''));
        $this->assertSame('', avatar_url('   '));
    }

    public function testMissingFileFallsBackInsteadOfBrokenImage(): void
    {
        // Avatar apontando para arquivo apagado: se devolvesse a URL,
        // a tela sairia com a imagem quebrada do navegador no lugar
        // das iniciais.
        $this->assertSame('', avatar_url('uploads/avatars/nao-existe-12345.jpg'));
    }

    public function testAbsoluteStoredPathThatExistsIsAccepted(): void
    {
        // Caminho guardado com barra inicial, como o admin salva.
        $this->assertSame(
            '/TCC_Etec/assets/img/placeholder-avatar.svg',
            $this->urlFor('/assets/img/placeholder-avatar.svg')
        );
    }

    public function testRelativeStoredPathIsPrefixedWithAssets(): void
    {
        $this->assertSame(
            '/TCC_Etec/assets/img/placeholder-avatar.svg',
            $this->urlFor('img/placeholder-avatar.svg')
        );
    }

    /** Roda base_url() num subprocesso com SCRIPT_NAME controlado. */
    private function urlFor(string $storedPath): string
    {
        $out = shell_exec(
            sprintf(
                '%s -r %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(
                    '$_SESSION=[];'
                    . '$_SERVER["SCRIPT_NAME"]="/TCC_Etec/pages/auth/profile.php";'
                    . 'require "includes/account_layout.php";'
                    . 'echo avatar_url(' . var_export($storedPath, true) . ');'
                )
            )
        );

        $this->assertIsString($out);
        $this->assertStringNotContainsString('Fatal error', $out, $out);
        $this->assertStringNotContainsString('Warning', $out, $out);

        return $out;
    }

    // =================================================================
    //  render_avatar()
    // =================================================================

    public function testAvatarWithInitialsWhenNoPhoto(): void
    {
        $html = render_avatar(['name' => 'Kauã Caetano', 'avatar_path' => null]);

        $this->assertStringContainsString('account-avatar--initials', $html);
        $this->assertStringContainsString('>KC<', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function testAvatarWithPhotoCarriesOnerrorFallback(): void
    {
        // O onerror é o que troca a foto quebrada pelas iniciais em
        // tempo real, sem recarregar a página.
        $html = render_avatar(
            ['name' => 'Kauã Caetano', 'avatar_path' => '/assets/img/placeholder-avatar.svg']
        );

        $this->assertStringContainsString('onerror=', $html);
        $this->assertStringContainsString('account-avatar--initials', $html);
    }

    /**
     * Regressão: as iniciais eram interpoladas dentro de onerror="...".
     * O primeiro " encerrava o atributo, o HTML saía malformado e o
     * resto virava atributo solto — além de permitir injeção pelo nome.
     * O teste parseia de verdade, em vez de contar aspas.
     */
    public function testAvatarHtmlIsWellFormedWithExactlyTheExpectedAttributes(): void
    {
        $html = render_avatar(
            ['name' => 'Ana "The Rock" <b>Silva</b>', 'avatar_path' => '/assets/img/placeholder-avatar.svg']
        );

        $doc = new \DOMDocument();
        $this->assertTrue(
            @$doc->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>'),
            'o HTML do avatar não pôde ser interpretado'
        );

        $img = $doc->getElementsByTagName('img')->item(0);
        $this->assertNotNull($img, 'a imagem do avatar não foi criada');

        $names = [];
        foreach ($img->attributes as $attribute) {
            $names[] = $attribute->nodeName;
        }
        sort($names);

        $this->assertSame(
            ['alt', 'class', 'onerror', 'src'],
            $names,
            'o <img> ganhou ou perdeu atributos: o onerror está vazando texto para atributo'
        );

        $onError = $img->getAttribute('onerror');
        $this->assertStringContainsString('dataset.initials', $onError);
        // Nenhuma aspa pode sobrar dentro do valor do atributo.
        $this->assertStringNotContainsString('"', $onError);
    }

    public function testInitialsSurviveQuotesInTheNameWithoutBreakingTheAttribute(): void
    {
        $doc = new \DOMDocument();
        $html = render_avatar(['name' => 'Ana "The Rock" Silva', 'avatar_path' => null]);

        @$doc->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');

        $span = $doc->getElementsByTagName('span')->item(0);
        $this->assertNotNull($span);
        $this->assertSame('AS', $span->getAttribute('data-initials'));
    }

    public function testAvatarAltTextIsEscaped(): void
    {
        // Nome vindo do banco entra no alt; sem escape, uma aspa
        // quebraria o atributo.
        $html = render_avatar(['name' => 'Ana "The Rock" Silva', 'avatar_path' => null]);

        $this->assertStringNotContainsString('"The Rock"', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function testAvatarIsNotBrokenByScriptTagInName(): void
    {
        $html = render_avatar(['name' => '<script>alert(1)</script>', 'avatar_path' => null]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // =================================================================
    //  WhatsApp
    // =================================================================

    public function testLocalTenDigitPhoneGetsCountryCode(): void
    {
        // (12) 97814-9392 -> 5512978149392
        $this->assertSame('5512978149392', account_normalize_whatsapp('(12) 97814-9392'));
    }

    public function testElevenDigitLocalPhoneGetsCountryCode(): void
    {
        $this->assertSame('5511978149392', account_normalize_whatsapp('11 97814-9392'));
    }

    public function testAlreadyInternationalNumberIsNotPrefixedTwice(): void
    {
        $this->assertSame('5511978149392', account_normalize_whatsapp('5511978149392'));
    }

    public function testForeignNumberIsLeftAlone(): void
    {
        // 44 é o DDI do Reino Unido: não é número local, não ganha 55.
        $this->assertSame('447700900123', account_normalize_whatsapp('+44 7700 900123'));
    }

    public function testEmptyPhoneYieldsEmptySoButtonIsNotRendered(): void
    {
        $this->assertSame('', account_normalize_whatsapp(''));
        $this->assertSame('', account_normalize_whatsapp(null));
        $this->assertSame('', account_normalize_whatsapp('---'));
    }

    // =================================================================
    //  Navegação
    // =================================================================

    public function testEveryNavItemPointsAtAnExistingScript(): void
    {
        // A sidebar é montada a partir deste array; um item com script
        // inexistente vira link 404 sem erro em lugar nenhum.
        foreach (account_nav_items() as $item) {
            $this->assertFileExists(
                dirname(__DIR__) . '/pages/auth/' . $item['script'],
                "'{$item['script']}' está na sidebar mas não existe em pages/auth/"
            );
        }
    }

    public function testNavItemsAreUniqueByScript(): void
    {
        $scripts = array_column(account_nav_items(), 'script');

        $this->assertSame(
            count($scripts),
            count(array_unique($scripts)),
            'a sidebar tem itens apontando para o mesmo script'
        );
    }

    public function testNavIconsAreFontAwesomeNotMaterial(): void
    {
        // O projeto carrega só o Font Awesome 6. Um ícone "material_" ou
        // "icon-" sai como caixa vazia, sem aviso no console.
        foreach (account_nav_items() as $item) {
            $this->assertMatchesRegularExpression(
                '/^(fa[srlbd] )?fa-[a-z0-9-]+$/',
                $item['icon'],
                "ícone '{$item['icon']}' não é do Font Awesome 6"
            );
        }
    }

    public function testNavIconsExistInTheLoadedFontAwesome(): void
    {
        // Confere contra as classes que o projeto realmente usa no
        // lugar; um nome de ícone escrito errado só aparece em branco.
        $used = [
            'fa-user'            => true,
            'fa-box-open'        => true,
            'fa-address-book'    => true,
            'fa-right-from-bracket' => true,
        ];

        foreach (account_nav_items() as $item) {
            $this->assertArrayHasKey(
                $item['icon'],
                $used,
                "classe '{$item['icon']}' não é uma das classes FA em uso no projeto"
            );
        }
    }
}
