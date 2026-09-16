<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Teste automatizado dos efeitos visuais do tema (assets/theme-extras).
 *
 * Verifica:
 *   1. O asset de imagem existe e tem assinatura PNG válida.
 *   2. Hash SHA-256 dos arquivos e do trecho de carregamento conferem com o JSON de referência.
 *   3. O módulo JS contém o sequenciador e as classes esperadas do efeito.
 *   4. O trecho CSS contém as regras de animação e classes esperadas.
 */
final class ThemeExtrasTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    private const HASHES_FILE = '.github/easter-egg-hashes.json';

    private const JS_PATH = 'assets/js/theme-extras.js';

    private const CSS_PATH = 'assets/css/theme-extras.css';

    private const MARK_PATH = 'assets/img/theme/accent-mark.png';

    private const FOOTER_PATH = 'components/footer.php';

    private const PNG_MAGIC = "\x89PNG\r\n\x1a\n";

    private array $hashes;

    protected function setUp(): void
    {
        $jsonPath = self::ROOT . '/' . self::HASHES_FILE;
        $this->assertFileExists($jsonPath, 'Arquivo de referência ausente: ' . self::HASHES_FILE);

        $data = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('files', $data);

        $this->hashes = $data['files'];
    }

    // =====================================================================
    //  Imagem da marca de acento
    // =====================================================================

    public function testAccentMarkFileExists(): void
    {
        $this->assertFileExists(self::ROOT . '/' . self::MARK_PATH);
    }

    public function testAccentMarkIsValidPng(): void
    {
        $raw = $this->readAsset(self::MARK_PATH);
        $this->assertStringStartsWith(self::PNG_MAGIC, $raw, 'Arquivo não é PNG válido.');
    }

    public function testAccentMarkHashMatchesReference(): void
    {
        $raw  = $this->readAsset(self::MARK_PATH);
        $hash = hash('sha256', $raw);
        $refs = $this->hashes[self::MARK_PATH] ?? [];

        $this->assertArrayHasKey('sha256', $refs);
        $this->assertSame(
            $refs['sha256'],
            $hash,
            'Hash SHA-256 da imagem diverge do valor registrado em ' . self::HASHES_FILE
        );
    }

    // =====================================================================
    //  Módulo JS
    // =====================================================================

    public function testJsFileHashMatchesReference(): void
    {
        $raw  = $this->readAsset(self::JS_PATH);
        $hash = hash('sha256', $raw);
        $refs = $this->hashes[self::JS_PATH] ?? [];

        $this->assertArrayHasKey('sha256', $refs);
        $this->assertSame(
            $refs['sha256'],
            $hash,
            'Hash SHA-256 do módulo JS diverge do valor registrado.'
        );
    }

    public function testJsContainsSequenceTrigger(): void
    {
        $raw = $this->readAsset(self::JS_PATH);
        $this->assertMatchesRegularExpression("/SEQUENCE\s*=\s*'timao'/", $raw);
    }

    public function testJsContainsEffectClasses(): void
    {
        $raw = $this->readAsset(self::JS_PATH);
        $this->assertStringContainsString('tx-fx',          $raw);
        $this->assertStringContainsString('tx-fx-icon',     $raw);
        $this->assertStringContainsString('tx-fx-toast',    $raw);
        $this->assertStringContainsString('OVERLAY_MS',     $raw);
    }

    // =====================================================================
    //  Folha de estilos
    // =====================================================================

    public function testCssFileHashMatchesReference(): void
    {
        $raw  = $this->readAsset(self::CSS_PATH);
        $hash = hash('sha256', $raw);
        $refs = $this->hashes[self::CSS_PATH] ?? [];

        $this->assertArrayHasKey('sha256', $refs);
        $this->assertSame(
            $refs['sha256'],
            $hash,
            'Hash SHA-256 do CSS diverge do valor registrado.'
        );
    }

    public function testCssContainsAnimationRules(): void
    {
        $raw = $this->readAsset(self::CSS_PATH);
        $this->assertStringContainsString('body.tx-fx::after', $raw);
        $this->assertStringContainsString('@keyframes tx-fx-pulse', $raw);
        $this->assertStringContainsString('.tx-fx-icon', $raw);
        $this->assertStringContainsString('@keyframes tx-fx-cross', $raw);
        $this->assertStringContainsString('.tx-fx-toast', $raw);
        $this->assertStringContainsString('accent-mark.png', $raw);
    }

    // =====================================================================
    //  Trecho de carregamento (components/footer.php)
    // =====================================================================

    public function testFooterSnippetHashMatchesReference(): void
    {
        $snippet = $this->extractSnippet(
            self::FOOTER_PATH,
            $this->hashes[self::FOOTER_PATH]['start_marker'] ?? '',
            $this->hashes[self::FOOTER_PATH]['end_marker']   ?? '',
        );
        $this->assertNotEmpty($snippet, 'Trecho de proteção vazio em ' . self::FOOTER_PATH);
        $hash = hash('sha256', $snippet);

        $this->assertSame(
            $this->hashes[self::FOOTER_PATH]['sha256'],
            $hash,
            'Hash SHA-256 do trecho de carregamento diverge do valor registrado.'
        );
    }

    public function testFooterSnippetLoadsThemeAssets(): void
    {
        $snippet = $this->extractSnippet(
            self::FOOTER_PATH,
            $this->hashes[self::FOOTER_PATH]['start_marker'] ?? '',
            $this->hashes[self::FOOTER_PATH]['end_marker']   ?? '',
        );
        $this->assertStringContainsString('theme-extras.css', $snippet);
        $this->assertStringContainsString('theme-extras.js',  $snippet);
    }

    // =====================================================================
    //  Consistência do registro
    // =====================================================================

    public function testExpectedFileCountMatchesEntries(): void
    {
        $jsonPath = self::ROOT . '/' . self::HASHES_FILE;
        $data     = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('expected_file_count', $data);
        $this->assertSame(
            count($data['files']),
            (int) $data['expected_file_count'],
            'expected_file_count deve bater com o número de entradas em .files'
        );
    }

    // =====================================================================
    //  Helpers
    // =====================================================================

    private function readAsset(string $relativePath): string
    {
        $full = self::ROOT . '/' . $relativePath;
        $this->assertFileExists($full);
        return file_get_contents($full);
    }

    private function extractSnippet(string $relativePath, string $startMarker, string $endMarker): string
    {
        $lines  = file(self::ROOT . '/' . $relativePath);
        $out    = [];
        $on     = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === $startMarker) {
                $on = true;
            }
            if ($on) {
                $out[] = $line;
            }
            if ($trimmed === $endMarker) {
                $on = false;
            }
        }

        return implode('', $out);
    }
}