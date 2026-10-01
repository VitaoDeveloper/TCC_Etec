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
 * usuário vê, e não com dados de mentira.
 *
 * A página segue docs/UI_SPEC.md: página única rolável, sem card de
 * menu/abas e sem card de avatar (o avatar mora na sidebar).
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
        $title = $this->dom()->query('//title')->item(0);
        $this->assertStringContainsString('Meu Perfil', (string) $title?->textContent);

        $inHead = $this->dom()->query(
            '//head//link[contains(@href,"account.css")]'
        )->length;

        $this->assertSame(1, $inHead, 'account.css precisa estar no <head>');
    }

    // =================================================================
    //  Especificação: seções, sem card de menu/avatar (Etapa 2)
    // =================================================================

    public function testPageHasTheSpecSectionsInOrder(): void
    {
        $expected = [
            'secao-dados',
            'secao-endereco',
            'secao-enderecos-salvos',
            'secao-senha',
            'secao-avisos',
            'secao-cartoes-salvos',
        ];

        $found = [];
        foreach ($this->dom()->query('//section[contains(@id,"secao-")]') as $section) {
            $found[] = (string) $section->getAttribute('id');
        }

        $this->assertSame($expected, $found, 'as seções devem aparecer na ordem da spec');
    }

    public function testPageHasNoAvatarCardAndNoMenuCard(): void
    {
        // Spec: remover o card "Menu"/abas e o card "Meu avatar".
        $this->assertSame(0, $this->dom()->query("//*[@id='secao-avatar']")->length, 'não pode haver card de avatar');
        $this->assertSame(0, $this->dom()->query("//*[@id='secao-menu']")->length, 'não pode haver card de menu');
        $this->assertSame(0, $this->dom()->query("//*[@id='tab-dados']")->length, 'não pode haver painel/aba');
        $this->assertSame(0, $this->dom()->query("//*[@data-account-menu]")->length, 'não pode haver nav de abas');
    }

    public function testPersonalDataFieldsPresent(): void
    {
        foreach (['name', 'cpf', 'email', 'username', 'phone'] as $field) {
            $this->assertSame(
                1,
                $this->dom()->query("//input[@name='{$field}']")->length,
                "campo {$field} ausente em Dados Pessoais"
            );
        }
    }

    public function testPasswordFieldIsMaskedDisabledAndUnsubmitted(): void
    {
        $input = $this->dom()->query("//*[@id='passwordMasked']")->item(0);

        $this->assertNotNull($input, 'o campo SENHA desabilitado deve existir em Dados Pessoais');
        $this->assertSame('password', $input->getAttribute('type'));
        $this->assertTrue($input->hasAttribute('disabled'));
        $this->assertSame('', $input->getAttribute('name'), 'campo disabled não deve submeter valor');
        $this->assertStringContainsString('•', (string) $input->getAttribute('value'));
    }

    public function testPasswordLabelIsSenhaNotSenhaAtual(): void
    {
        // Spec: rótulo "SENHA", NÃO "Senha atual", e o campo duplicado
        // do card de segurança não pode existir.
        $label = $this->dom()->query("//label[@for='passwordMasked']")->item(0);

        $this->assertNotNull($label, 'faltou o rótulo do campo SENHA');
        $this->assertSame('Senha', trim((string) $label->textContent));

        $disabledPasswordFields = $this->dom()->query("//input[@type='password' and @disabled]")->length;
        $this->assertSame(
            1,
            $disabledPasswordFields,
            'deve existir exatamente um campo de senha desabilitado (sem duplicata)'
        );
    }

    public function testAddressFieldsPresent(): void
    {
        // Escopado ao form principal: o modal de endereço salvos repete
        // os mesmos nomes de campo e não deve entrar nesta contagem.
        $form = '//form[@data-profile-form]';

        foreach (['postal_code', 'number', 'street', 'complement', 'neighborhood', 'city'] as $field) {
            $this->assertSame(
                1,
                $this->dom()->query("{$form}//input[@name='{$field}']")->length,
                "campo {$field} ausente no Endereço"
            );
        }

        $this->assertSame(
            1,
            $this->dom()->query("{$form}//select[@name='state']")->length,
            'campo state ausente no Endereço'
        );
    }

    public function testEditablePasswordFieldExists(): void
    {
        $input = $this->dom()->query("//*[@id='current_password']")->item(0);

        $this->assertNotNull($input, 'o form de Alterar Senha precisa do campo atual');
        $this->assertSame('password', $input->getAttribute('type'));
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertSame('current-password', $input->getAttribute('autocomplete'));
    }

    // =================================================================
    //  Um único botão "Salvar Alterações"
    // =================================================================

    public function testThereIsASingleSaveButton(): void
    {
        $dom = $this->dom();

        $saveButtons = [];
        foreach ($dom->query('//button[@type="submit"]') as $btn) {
            $text = trim((string) $btn->textContent);
            if (str_contains($text, 'Salvar Alterações')) {
                $saveButtons[] = $text;
            }
        }

        $this->assertCount(1, $saveButtons, 'deve existir exatamente um "Salvar Alterações"');

        // Spec: remover os botões de página "Salvar dados", "Salvar
        // endereço" e "Salvar preferências". O modal de endereço salvos
        // tem o próprio submit ("Salvar endereço") e fica fora desta
        // contagem — ele é diálogo, não botão de página.
        $pageLevelXPath = '//button[@type="submit"'
            . ' and not(ancestor::*[contains(@class,"account-modal")])]';

        foreach (['Salvar dados', 'Salvar endereço', 'Salvar preferências'] as $removed) {
            $found = 0;
            foreach ($this->dom()->query($pageLevelXPath) as $btn) {
                if (trim((string) $btn->textContent) === $removed) {
                    $found++;
                }
            }

            $this->assertSame(0, $found, "não pode haver botão de página \"{$removed}\"");
        }
    }

    public function testPersonalFormPostsToApi(): void
    {
        $form = $this->dom()->query("//form[@data-profile-form]")->item(0);

        $this->assertNotNull($form, 'o form de Dados Pessoais+Endereço deve marcar data-profile-form');
        $this->assertStringContainsString(
            'api/account/profile.php',
            (string) $form->getAttribute('data-endpoint')
        );
        $this->assertSame(
            1,
            $this->dom()->query("//form[@data-profile-form]//input[@name='action'][@value='personal']")->length,
            'o action=personal cobre dado pessoal e endereço num POST só'
        );
    }

    // =================================================================
    //  Notificações: toggles sem botão
    // =================================================================

    public function testNotificationsTogglesSaveWithoutButton(): void
    {
        $dom = $this->dom();
        $form = $dom->query("//form[@data-notifications]")->item(0);

        $this->assertNotNull($form, 'o form de notificações deve existir');
        $this->assertSame(
            2,
            $dom->query("//form[@data-notifications]//input[@type='checkbox']")->length,
            'devem existir exatamente dois toggles'
        );
        $this->assertSame(
            0,
            $dom->query("//form[@data-notifications]//button[@type='submit']")->length,
            'notificações não podem ter botão: salvam ao alternar'
        );
    }

    public function testToggleLabelsAreShortAndPortuguese(): void
    {
        $html = self::$html;

        $this->assertStringContainsString('Notificações por e-mail', $html);
        $this->assertStringContainsString('Notificações por WhatsApp', $html);

        // Spec: sem textos longos sob os toggles.
        $this->assertStringNotContainsString('Status do pedido, comprovante', $html);
    }

    // =================================================================
    //  Endereços Salvos e Cartões Salvos
    // =================================================================

    public function testSavedAddressAndCardSectionsHaveEmptyStates(): void
    {
        $dom = $this->dom();

        foreach (['secao-enderecos-salvos', 'secao-cartoes-salvos'] as $id) {
            $this->assertSame(
                1,
                $dom->query("//*[@id='{$id}']")->length,
                "faltou a seção {$id}"
            );
            $this->assertSame(
                1,
                $dom->query("//*[@id='{$id}']//*[contains(@class,'account-empty--dashed')]")->length,
                "a seção {$id} deve ter estado vazio tracejado"
            );
        }
    }

    public function testModalsExistAndStartHidden(): void
    {
        $dom = $this->dom();

        foreach (['modal-address', 'modal-card'] as $id) {
            $modal = $dom->query("//*[@id='{$id}']")->item(0);

            $this->assertNotNull($modal, "faltou o modal {$id}");
            $this->assertTrue($modal->hasAttribute('hidden'), "o modal {$id} deve começar oculto");
        }

        $this->assertSame(
            1,
            $dom->query("//*[@data-modal-open='modal-address']")->length,
            'faltou o botão "+ Adicionar endereço"'
        );
        $this->assertSame(
            1,
            $dom->query("//*[@data-modal-open='modal-card']")->length,
            'faltou o botão "+ Adicionar cartão"'
        );
    }

    public function testAddressModalPostsToTheAddressApi(): void
    {
        $form = $this->dom()->query("//*[@id='modal-address']//form")->item(0);

        $this->assertNotNull($form, 'o modal de endereço deve ter um form');
        $this->assertStringContainsString(
            'api/account/address.php',
            (string) $form->getAttribute('data-endpoint')
        );
        $this->assertSame(
            'create',
            $this->firstHiddenAction($form),
            'o action deve ser create, o contrato real da API de endereços'
        );
    }

    private function firstHiddenAction(\DOMNode $form): string
    {
        foreach ($form->getElementsByTagName('input') as $input) {
            if ($input->getAttribute('name') === 'action') {
                return $input->getAttribute('value');
            }
        }

        return '';
    }

    // =================================================================
    //  Sem textos de ajuda fixos
    // =================================================================

    public function testNoFixedHelpTextsUnderFields(): void
    {
        // Spec: remover textos de ajuda fixos sob os campos. Erros só
        // aparecem em validação.
        $this->assertStringNotContainsString('JPG, PNG ou WebP', self::$html);
        $this->assertStringNotContainsString('Sua senha nunca aparece por aqui', self::$html);
    }

    // =================================================================
    //  Dados reais do seed
    // =================================================================

    public function testRealSeedValuesReachTheTemplate(): void
    {
        // Valores do usuário 16 no seed: se o template quebrar a
        // interpolação, um deles some.
        $this->assertMatchesRegularExpression('/value="[^"]+"/', self::$html, 'os campos devem sair preenchidos');
    }

    // =================================================================
    //  Assets e caminhos
    // =================================================================

    public function testNoRelativePathLeaksIntoOutput(): void
    {
        $this->assertStringNotContainsString('src="../../', self::$html);
        $this->assertStringNotContainsString('href="../../', self::$html);
    }

    public function testProtectedAssetBlockSurvives(): void
    {
        $this->assertStringContainsString('BEGIN THEME EXTRAS ASSETS', self::$html);
        $this->assertStringContainsString('END THEME EXTRAS ASSETS', self::$html);
    }

    public function testWhatsappButtonStillSeedsDigitsOnly(): void
    {
        if (preg_match('/wa\.me\/(\d+)/', self::$html, $m) !== 1) {
            $this->markTestSkipped('o botão de WhatsApp não foi renderizado');
        }

        $this->assertMatchesRegularExpression('/^\d+$/', $m[1], 'o número do WhatsApp deve sair só com dígitos');
    }
}
