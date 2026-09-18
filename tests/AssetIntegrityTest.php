<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Teste automatizado de integridade dos assets protegidos do tema.
 *
 * Verifica:
 *   1. O asset de imagem existe e tem assinatura PNG válida.
 *   2. Hash SHA-256 da imagem, do trecho JS e do trecho CSS conferem com o JSON de referência.
 *   3. O trecho JS contém os elementos esperados (criação da imagem, referência ao ícone, toast).
 *   4. O trecho CSS contém as regras de animação e classes esperadas.
 */
final class AssetIntegrityTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    private const HASHES_FILE = '.github/asset-integrity.json';

    private const ICON_PATH = 'assets/img/ui/effects/textures/selecao.png';

    private const HEADER_PATH = 'components/header.php';

    private const CSS_PATH = 'assets/css/mercadolivre-style.css';

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
    //  Imagem do icone
    // =====================================================================

    public function testSelecaoIconFileExists(): void
    {
        $this->assertFileExists(self::ROOT . '/' . self::ICON_PATH);
    }

    public function testSelecaoIconIsValidPng(): void
    {
        $raw = $this->readAsset(self::ICON_PATH);
        $this->assertStringStartsWith(self::PNG_MAGIC, $raw, 'Arquivo não é PNG válido.');
    }

    public function testSelecaoIconHashMatchesReference(): void
    {
        $raw   = $this->readAsset(self::ICON_PATH);
        $hash  = hash('sha256', $raw);
        $refs  = $this->hashes[self::ICON_PATH] ?? [];

        $this->assertArrayHasKey('sha256', $refs);
        $this->assertSame(
            $refs['sha256'],
            $hash,
            'Hash SHA-256 da imagem diverge do valor registrado em ' . self::HASHES_FILE
        );
    }

    // =====================================================================
    //  Trecho JS (components/header.php)
    // =====================================================================

    public function testHeaderSnippetHashMatchesReference(): void
    {
        $snippet = $this->extractSnippet(
            self::HEADER_PATH,
            $this->hashes[self::HEADER_PATH]['start_marker'] ?? '',
            $this->hashes[self::HEADER_PATH]['end_marker']   ?? '',
        );
        $this->assertNotEmpty($snippet, 'Trecho de proteção vazio em ' . self::HEADER_PATH);
        $hash = hash('sha256', $snippet);

        $this->assertSame(
            $this->hashes[self::HEADER_PATH]['sha256'],
            $hash,
            'Hash SHA-256 do trecho JS diverge do valor registrado.'
        );
    }

    public function testHeaderSnippetRendersSelecaoIcon(): void
    {
        $snippet = $this->extractSnippet(
            self::HEADER_PATH,
            $this->hashes[self::HEADER_PATH]['start_marker'] ?? '',
            $this->hashes[self::HEADER_PATH]['end_marker']   ?? '',
        );
        $this->assertStringContainsString("createElement('img')", $snippet);
        $this->assertMatchesRegularExpression(
            '/el\.src\s*=.*selecao\.png/',
            $snippet,
            'O trecho deve criar <img> apontando para selecao.png.'
        );
    }

    public function testHeaderSnippetContainsExpectedElements(): void
    {
        $snippet = $this->extractSnippet(
            self::HEADER_PATH,
            $this->hashes[self::HEADER_PATH]['start_marker'] ?? '',
            $this->hashes[self::HEADER_PATH]['end_marker']   ?? '',
        );
        $this->assertStringContainsString('fa-crown',          $snippet);
        $this->assertStringContainsString('royal-crown',       $snippet);
        $this->assertStringContainsString('royal-glow',        $snippet);
        $this->assertStringContainsString('Modo Realeza',      $snippet);
        $this->assertStringContainsString('triggerRoyalMode',  $snippet);
    }

    // =====================================================================
    //  Trecho CSS (assets/css/mercadolivre-style.css)
    // =====================================================================

    public function testCssSnippetHashMatchesReference(): void
    {
        $snippet = $this->extractSnippet(
            self::CSS_PATH,
            $this->hashes[self::CSS_PATH]['start_marker'] ?? '',
            $this->hashes[self::CSS_PATH]['end_marker']   ?? '',
        );
        $this->assertNotEmpty($snippet, 'Trecho de proteção vazio em ' . self::CSS_PATH);
        $hash = hash('sha256', $snippet);

        $this->assertSame(
            $this->hashes[self::CSS_PATH]['sha256'],
            $hash,
            'Hash SHA-256 do trecho CSS diverge do valor registrado.'
        );
    }

    public function testCssSnippetContainsAnimationRules(): void
    {
        $snippet = $this->extractSnippet(
            self::CSS_PATH,
            $this->hashes[self::CSS_PATH]['start_marker'] ?? '',
            $this->hashes[self::CSS_PATH]['end_marker']   ?? '',
        );
        $this->assertStringContainsString('@keyframes royal-fall',     $snippet);
        $this->assertStringContainsString('.royal-crown',             $snippet);
        $this->assertStringContainsString('.royal-troll',             $snippet);
        $this->assertStringContainsString('.royal-glow',              $snippet);
        $this->assertStringContainsString('.royal-toast',             $snippet);
        $this->assertStringContainsString('@keyframes royal-toast-in', $snippet);
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
