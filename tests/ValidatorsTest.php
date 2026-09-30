<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/validators.php';

/**
 * Testes dos validadores de entrada do formulário de perfil e checkout.
 *
 * Estes rodam no navegador e no servidor; o PHP é a última linha de
 * defesa, então cada regra aqui é uma regra que o cliente não pode
 * furar com o devtools aberto.
 */
class ValidatorsTest extends TestCase
{
    // =================================================================
    //  CPF
    // =================================================================

    public function testAcceptsValidCpf(): void
    {
        $this->assertTrue(is_valid_cpf('529.982.247-25'));
        $this->assertTrue(is_valid_cpf('52998224725'));
    }

    public function testRejectsCpfWithWrongCheckDigit(): void
    {
        // 111.444.777-35 é matematicamente válido; 36 não é.
        $this->assertFalse(is_valid_cpf('111.444.777-36'));
    }

    public function testRejectsRepeatedDigitCpf(): void
    {
        foreach (['00000000000', '11111111111', '22222222222'] as $cpf) {
            $this->assertFalse(is_valid_cpf($cpf), $cpf);
        }
    }

    public function testRejectsMalformedCpf(): void
    {
        $this->assertFalse(is_valid_cpf('abc.def.ghi-jk'));
        $this->assertFalse(is_valid_cpf('529.982.247'));
        $this->assertFalse(is_valid_cpf(''));
        $this->assertFalse(is_valid_cpf(null));
    }

    public function testFormatsCpf(): void
    {
        $this->assertSame('529.982.247-25', format_cpf('52998224725'));
    }

    // =================================================================
    //  CEP
    // =================================================================

    public function testAcceptsEightDigitCep(): void
    {
        $this->assertTrue(is_valid_cep('12053831'));
        $this->assertTrue(is_valid_cep('12053-831'));
    }

    public function testRejectsCepWithWrongLength(): void
    {
        $this->assertFalse(is_valid_cep('1205383'), '7 dígitos');
        $this->assertFalse(is_valid_cep('120538310'), '9 dígitos');
        $this->assertFalse(is_valid_cep(''));
        $this->assertFalse(is_valid_cep(null));
    }

    public function testFormatsCep(): void
    {
        $this->assertSame('12053-831', format_cep('12053831'));
    }

    // =================================================================
    //  Telefone
    // =================================================================

    public function testAcceptsRealDddRange(): void
    {
        foreach ([11, 12, 21, 48, 61, 85, 99] as $ddd) {
            $this->assertTrue(is_valid_ddd($ddd), (string) $ddd);
        }
    }

    public function testRejectsUnassignedDdd(): void
    {
        foreach ([0, 1, 9, 10, 100] as $ddd) {
            $this->assertFalse(is_valid_ddd($ddd), (string) $ddd);
        }
    }

    public function testNormalizesCellphoneToElevenDigits(): void
    {
        $this->assertSame('12912345678', normalize_phone('(12) 91234-5678'));
        $this->assertSame('12912345678', normalize_phone('12 9 1234 5678'));
        $this->assertSame('12912345678', normalize_phone('+55 12 91234-5678'));
    }

    /**
     * Um fixo tem 8 dígitos de assinante e NÃO ganha o "9" da frente.
     * Acrescentar o 9 faria (12) 3456-7890 aparecer como
     * (12) 93456-7890 — um celular que o cliente nunca digitou — e a
     * gravação seguinte passaria a persistir esse número falso.
     */
    public function testLandlineKeepsTenDigitsWithoutInventedNine(): void
    {
        $this->assertSame('1234567890', normalize_phone('(12) 3456-7890'));
        $this->assertNotSame('12934567890', normalize_phone('(12) 3456-7890'));
    }

    public function testPhoneNormalizationIsIdempotent(): void
    {
        foreach (['(12) 91234-5678', '(12) 3456-7890'] as $input) {
            $once  = normalize_phone($input);
            $twice = normalize_phone($once);
            $this->assertSame($once, $twice, sprintf('reaplicar em "%s" mudou o número', $input));
        }
    }

    public function testRejectsPhoneWithUnusableLength(): void
    {
        $this->assertSame('', normalize_phone('1234'));
        $this->assertSame('', normalize_phone(''));
        $this->assertSame('', normalize_phone(null));
        $this->assertSame('', normalize_phone('0012345678'), 'DDD 00 não existe');
    }

    public function testFormatsBothPhoneLengths(): void
    {
        $this->assertSame('(12) 91234-5678', format_phone('12912345678'));
        $this->assertSame('(12) 3456-7890', format_phone('1234567890'));
    }

    public function testRoundTripsLandlineWithoutChangingDisplay(): void
    {
        $input = '(12) 3456-7890';
        $this->assertSame($input, format_phone(normalize_phone($input)));
    }

    // =================================================================
    //  Cartão
    // =================================================================

    public function testAcceptsLuhnValidNumbers(): void
    {
        $this->assertTrue(is_valid_card_number('4111111111111111'));
    }

    public function testRejectsLuhnInvalidNumber(): void
    {
        $this->assertFalse(is_valid_card_number('4111111111111112'));
    }

    public function testRejectsCardWithNonDigits(): void
    {
        $this->assertFalse(is_valid_card_number('4111-1111-1111-abcd'));
        $this->assertFalse(is_valid_card_number(''));
        $this->assertFalse(is_valid_card_number(null));
    }

    public function testDetectsBrandByBinRange(): void
    {
        $this->assertSame('visa', detect_card_brand('4111111111111111'));
        $this->assertSame('mastercard', detect_card_brand('5299822470123451'));
        $this->assertSame('elo', detect_card_brand('6362970000457013'));
        $this->assertSame('hipercard', detect_card_brand('6062825624252501'));
    }

    public function testDetectsEloAndHipercardBeforeOtherRanges(): void
    {
        // Elo e Hipercard vivem dentro de faixas que outras bandeiras
        // também usam; sem testar a precedência, um cartão Elo 6362
        // apareceria com a bandeira errada.
        $this->assertSame('elo', detect_card_brand('6362970000457013'));
        $this->assertNotSame('mastercard', detect_card_brand('6362970000457013'));
    }

    public function testValidatesExpiryAgainstCurrentDate(): void
    {
        $this->assertTrue(is_valid_card_expiry('12/29'));
        $this->assertFalse(is_valid_card_expiry('01/20'), 'vencido');
        $this->assertFalse(is_valid_card_expiry('13/29'), 'mês inexistente');
        $this->assertFalse(is_valid_card_expiry('00/29'), 'mês 00');
    }

    public function testExpiryAcceptsCurrentMonthAsStillValid(): void
    {
        // O cartão vale até o FIM do mês: cartão que vence neste mês
        // ainda funciona hoje. Rejeitar seria erro.
        $this->assertTrue(is_valid_card_expiry('12/29', 2029, 12));
    }

    public function testExtractsLastFourDigits(): void
    {
        $this->assertSame('1111', card_last_four('4111 1111 1111 1111'));
        $this->assertSame('1234', card_last_four('5299822470101234'));
    }

    public function testCardDisplayNeverRevealsFullNumber(): void
    {
        $display = card_display('visa', '1111', 12, 2029);

        $this->assertStringNotContainsString('4111', $display, 'só os 4 últimos dígitos');
        $this->assertStringContainsString('1111', $display);
        $this->assertStringContainsString('Visa', $display);
    }

    // =================================================================
    //  Demais campos do perfil
    // =================================================================

    public function testValidatesUsername(): void
    {
        $this->assertTrue(is_valid_username('kaua.caitano'));
        $this->assertTrue(is_valid_username('kaua_1'));
        $this->assertFalse(is_valid_username('kaua caitano'), 'espaço');
        $this->assertFalse(is_valid_username('.kaua'), 'ponto no início');
        $this->assertFalse(is_valid_username('ab'), 'curto demais');
        $this->assertFalse(is_valid_username(''));
    }

    public function testNormalizesLegacyUsername(): void
    {
        $this->assertSame('kaua.caetano', normalize_legacy_username('Kauã.Caetano'));
        $this->assertSame('kaua', normalize_legacy_username('KAUA'));
        $this->assertSame('joao.silva', normalize_legacy_username('João Silva'));
        $this->assertSame('cajamar', normalize_legacy_username('Cajamar'));
        $this->assertSame('f.m.c', normalize_legacy_username('F.M.C'));
        $this->assertSame('', normalize_legacy_username('   '), 'só espaço -> vazio (fallback da migração)');
        $this->assertSame('', normalize_legacy_username('@@@'), 'só símbolos -> vazio');
        $this->assertSame('kaua', normalize_legacy_username('.kaua.'));
    }

    public function testNormalizedLegacyUsernamePassesCurrentRules(): void
    {
        foreach (['Kauã.Caetano', 'João Silva', 'maria_estevam', 'Luiz.Fernando'] as $legacy) {
            $normalized = normalize_legacy_username($legacy);
            $this->assertTrue(is_valid_username($normalized), "{$legacy} -> {$normalized}");
        }
    }

    public function testValidatesEmail(): void
    {
        $this->assertTrue(is_valid_email('k@royaltech.com'));
        $this->assertFalse(is_valid_email('k.royaltech.com'), 'sem @');
        $this->assertFalse(is_valid_email('k@royaltech'), 'sem TLD');
        $this->assertFalse(is_valid_email(''));
    }

    public function testValidatesUf(): void
    {
        $this->assertTrue(is_valid_uf('SP'));
        $this->assertTrue(is_valid_uf('sp'));
        $this->assertFalse(is_valid_uf('XX'));
        $this->assertFalse(is_valid_uf('S'));
        $this->assertFalse(is_valid_uf('São Paulo'));
    }

    // =================================================================
    //  Utilitários
    // =================================================================

    public function testOnlyDigitsStripsNonNumericCharacters(): void
    {
        $this->assertSame('12053831', only_digits('12053-831'));
        $this->assertSame('52998224725', only_digits('529.982.247-25'));
        $this->assertSame('', only_digits(null));
    }

    public function testLimitTextTruncatesWithoutBreakingUtf8(): void
    {
        $this->assertSame('xxxxxxxxxx', limit_text(str_repeat('x', 300), 10));
        // mb_* não pode cortar no meio de um caractere multibyte.
        $this->assertSame(6, mb_strlen(limit_text('São Paulo é grande', 6)));
    }

    public function testOrderReferenceIsZeroPadded(): void
    {
        $this->assertSame('0012', order_reference(12));
        $this->assertSame('0007', order_reference(7));
    }
}
