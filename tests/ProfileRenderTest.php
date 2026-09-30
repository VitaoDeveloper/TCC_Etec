<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;
use DOMDocument;
use DOMXPath;

/**
 * Teste de renderização da página de perfil.
 *
 * Monta a página real (pages/auth/profile.php) com o banco do projeto e
 * o usuário 16 do seed — ou seja, com os valores verdadeiros que o
 * usuário vê, e não com dados de mentira. É o único jeito de garantir
 * que o template não quebrou entre o HTML cru e o que a tela entrega.
 */
class ProfileRenderTest extends TestCase
{
    private static string $html = '';

    public static function setUpBeforeClass(): void
    {
        $out = tempnam(sys_get_temp_dir(), 'account-profile-');

        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/fixtures/render_profile.php'),
            escapeshellarg('render'),
            escapeshellarg($out)
        );

        exec($command, $lines, $code);

        $rendered = is_file($out) ? (string) file_get_contents($out) : '';
        @unlink($out);

        if ($code !== 0 || $rendered === '') {
            self::markTestSkipped(
                'nao foi possivel renderizar o perfil (precisa do MySQL): '
                . implode("\n", $lines)
            );
        }

        self::$html = $rendered;
    }

    private function dom(): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $doc->loadHTML(self::$html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    // =================================================================
    //  Estrutura geral
    // =================================================================

    public function testPageIsSingleDocument(): void
    {
        $this->assertSame(1, $this->dom()->query('/html')->length);
        $this->assertSame(1, $this->dom()->query('//head')->length);
        $this->assertSame(1, $this->dom()->query('//body')->length);
    }

    public function testTitleAndStylesheet(): void
    {
        $this->assertSame(
            'Meu Perfil - Royal Tech',
            trim((string) $this->dom()->query('//title')->item(0)?->textContent)
        );

        $links = $this->dom()->query('//link[@rel="stylesheet"]');
        $inHead = 0;
        foreach ($links as $link) {
            if (str_contains($link->getAttribute('href'), 'account.css')
                && $link->parentNode->localName === 'head') {
                $inHead++;
            }
        }
        $this->assertSame(1, $inHead, 'account.css precisa estar no <head>');
    }

    // =================================================================
    //  Seções do perfil
    // =================================================================

    public function testAllFourSectionsRender(): void
    {
        foreach (['secao-dados', 'secao-endereco', 'secao-avisos', 'secao-senha'] as $id) {
            $this->assertSame(
                1,
                $this->dom()->query("//*[@id='{$id}']")->length,
                "secao {$id} nao encontrada"
            );
        }
    }

    public function testAvatarCardWithCameraBadge(): void
    {
        $this->assertSame(1, $this->dom()->query("//*[@id='secao-avatar']")->length);
        $this->assertSame(1, $this->dom()->query("//*[@id='avatarTrigger']")->length);
        $this->assertSame(
            1,
            $this->dom()->query("//*[@id='avatarTrigger']//*[contains(@class,'account-avatar-camera')]")->length,
            'a camerinha deveria ficar sobre o avatar'
        );
        $this->assertSame(
            1,
            $this->dom()->query("//*[@id='avatarTrigger']//*[contains(@class,'account-avatar--initials')]")->length,
            'o avatar deveria manter as iniciais (data-initials)'
        );
    }

    public function testMenuCardHasThreeRows(): void
    {
        $this->assertSame(1, $this->dom()->query("//*[@id='secao-menu']")->length);

        $labels = [];
        foreach ($this->dom()->query("//nav[@data-account-menu]//*[contains(@class,'account-menu-item')]//*[contains(@class,'account-menu-label')]") as $label) {
            $labels[] = trim($label->textContent);
        }

        $this->assertSame(['Dados Pessoais', 'Endereço', 'Notificações e senha'], $labels);
    }

    public function testThreePanelsAndDadosActiveByDefault(): void
    {
        foreach (['tab-dados', 'tab-endereco', 'tab-preferencias'] as $id) {
            $this->assertSame(
                1,
                $this->dom()->query("//*[@id='{$id}'][@data-account-panel]")->length,
                "painel {$id} ausente"
            );
        }

        $active = $this->dom()->query("//*[@class='account-menu-item is-active']//*[contains(@class,'account-menu-label')]")->item(0);
        $this->assertSame('Dados Pessoais', trim((string) $active?->textContent));
    }

    public function testPersonalFormPostsToApiAndKeepsPanel(): void
    {
        $form = $this->dom()->query("//form[@data-profile-form]")->item(0);
        $this->assertNotNull($form, 'o form de dados pessoais deveria marcar data-profile-form');

        $this->assertStringContainsString('api/account/profile.php', (string) $form->getAttribute('action'));
        $this->assertSame(
            1,
            $this->dom()->query("//form[@data-profile-form]//input[@name='panel'][@value='dados']")->length,
            'o painel da aba deve ir junto no POST para voltar à mesma aba'
        );
        $this->assertSame(
            1,
            $this->dom()->query("//*[@data-save-status]")->length,
            'o status de salvamento deveria existir'
        );
    }

    public function testPersonalProfileFieldsPresent(): void
    {
        foreach (['name', 'username', 'email', 'cpf', 'phone'] as $field) {
            $this->assertSame(
                1,
                $this->dom()->query("//input[@name='{$field}']")->length,
                "campo {$field} ausente nos dados pessoais"
            );
        }
    }

    public function testAddressFieldsPresent(): void
    {
        foreach (['postal_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state'] as $field) {
            $this->assertSame(
                1,
                $this->dom()->query("//*[@name='{$field}']")->length,
                "campo {$field} ausente no endereco"
            );
        }
    }

    // =================================================================
    //  Segurança
    // =================================================================

    public function testPasswordIsMaskedAndDisabled(): void
    {
        $input = $this->dom()->query("//input[@id='passwordMasked']")->item(0);
        $this->assertNotNull($input, 'o campo mascarado de senha deveria existir');

        $this->assertSame('password', $input->getAttribute('type'));
        $this->assertTrue($input->hasAttribute('disabled'));
        $this->assertSame('', $input->getAttribute('name'), 'campo disabled não deve submeter valor');

        $mask = $input->getAttribute('value');
        $this->assertNotSame('', $mask);
        $this->assertStringContainsString('•', $mask);
    }

    public function testCurrentPasswordFieldIsEditable(): void
    {
        $input = $this->dom()->query("//input[@id='current_password']")->item(0);
        $this->assertNotNull($input, 'o campo de senha atual deveria existir');

        $this->assertSame('password', $input->getAttribute('type'));
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertSame('current-password', $input->getAttribute('autocomplete'));
    }

    // =================================================================
    //  Dados reais do seed
    // =================================================================

    public function testRealSeedValuesReachTheTemplate(): void
    {
        // Nome e usuário do seed precisam aparecer preenchidos; senão o
        // template está descolado do que a camada de dados devolve.
        $this->assertStringContainsString('value="', self::$html);

        $name = $this->dom()->query("//input[@name='name']")->item(0);
        $this->assertSame(
            'Kauã Caetano',
            $name?->getAttribute('value'),
            'o nome real do usuario 16 deveria vir preenchido'
        );
    }

    // =================================================================
    //  Proteções
    // =================================================================

    public function testNoRelativePathLeaksIntoOutput(): void
    {
        // Caminho relativo que escaparia do diretório da página.
        $this->assertStringNotContainsString('"../../', self::$html);
        $this->assertStringNotContainsString("'../../", self::$html);
    }

    public function testProtectedAssetBlockSurvives(): void
    {
        $this->assertStringContainsString(
            'BEGIN THEME EXTRAS ASSETS (PROTEGIDO)',
            self::$html
        );
    }

    public function testWhatsappButtonStillSeedsDigitsOnly(): void
    {
        $button = $this->dom()->query('//a[contains(@class,"account-whatsapp")]')->item(0);
        if ($button === null) {
            $this->assertTrue(true); // depende da configuração da loja
            return;
        }

        $this->assertMatchesRegularExpression(
            '#^https://wa\.me/[0-9]+$#',
            $button->getAttribute('href')
        );
    }
}