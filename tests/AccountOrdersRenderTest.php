<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;
use DOMDocument;
use DOMXPath;

/**
 * Teste de renderização da lista e do detalhe de pedidos.
 *
 * Monta as páginas reais (pages/auth/orders.php e order-detail.php)
 * dentro do shell da conta, com um pedido temporário criado pela
 * fixture — as asserções valem para qualquer banco, mesmo sem pedidos
 * semeados para o usuário de teste.
 */
class AccountOrdersRenderTest extends TestCase
{
    private static string $listHtml = '';
    private static string $detailHtml = '';
    private static array $listMeta = [];
    private static array $detailMeta = [];

    public static function setUpBeforeClass(): void
    {
        self::$listHtml   = self::render('list');
        self::$detailHtml = self::render('detail');
    }

    private static function render(string $mode): string
    {
        $out = tempnam(sys_get_temp_dir(), 'account-orders-');

        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/fixtures/render_orders.php'),
            escapeshellarg($mode),
            escapeshellarg($out)
        );

        exec($command, $lines, $code);

        $html = is_file($out) ? (string) file_get_contents($out) : '';
        $metaFile = $out . '.meta.json';
        $meta = is_file($metaFile)
            ? (array) json_decode((string) file_get_contents($metaFile), true)
            : [];

        @unlink($out);
        @unlink($metaFile);

        if ($code !== 0 || $html === '') {
            self::markTestSkipped(
                "nao foi possivel renderizar {$mode} (precisa do MySQL): "
                . implode("\n", $lines)
            );
        }

        if ($mode === 'detail') {
            self::$detailMeta = $meta;
        } else {
            self::$listMeta = $meta;
        }

        return $html;
    }

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $doc->loadHTML($html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    // =================================================================
    //  Lista
    // =================================================================

    public function testListIsASingleDocumentInTheAccountShell(): void
    {
        $dom = $this->dom(self::$listHtml);

        $this->assertSame(1, $dom->query('/html')->length);
        $this->assertSame(1, $dom->query('//div[contains(@class,"account-shell")]')->length);
        $this->assertSame(1, $dom->query('//aside[contains(@class,"account-sidebar")]')->length);
        $this->assertSame(1, $dom->query('//main[contains(@class,"account-main")]')->length);
        $this->assertSame('Meus Pedidos - Royal Tech', trim((string) $dom->query('//title')->item(0)?->textContent));
    }

    public function testOrdersIsTheCurrentSidebarItem(): void
    {
        $current = $this->dom(self::$listHtml)
            ->query('//nav[contains(@class,"account-nav")]//a[contains(@class,"is-current")]');

        $this->assertSame(1, $current->length, 'a sidebar precisa marcar exatamente um item');
        $this->assertSame('Meus Pedidos', trim($current->item(0)->textContent));
        $this->assertSame('page', $current->item(0)->getAttribute('aria-current'));
    }

    public function testListHasTitleAndOrderSection(): void
    {
        $dom = $this->dom(self::$listHtml);

        $this->assertSame(
            'Meus Pedidos',
            trim((string) $dom->query('//h1[contains(@class,"account-page-title")]')->item(0)?->textContent)
        );
        $this->assertSame(1, $dom->query("//*[@id='secao-pedidos']")->length);
    }

    public function testListMountsTheTempOrderRow(): void
    {
        $dom = $this->dom(self::$listHtml);

        $this->assertGreaterThan(0, self::$listMeta, 'a fixture nao descreveu o pedido temporario');

        $rows = $dom->query('//table[contains(@class,"ml-table")]//tbody/tr');
        $this->assertGreaterThan(0, $rows->length, 'a lista saiu sem linhas de pedido');

        $this->assertStringContainsString(
            self::$listMeta['order_number'],
            self::$listHtml,
            'o pedido temporario nao apareceu na lista'
        );

        $this->assertStringContainsString(
            self::$listMeta['order_total'],
            self::$listHtml,
            'o total do pedido temporario nao apareceu na lista'
        );
    }

    public function testListStatusBadgeShows(): void
    {
        $this->assertGreaterThan(
            0,
            $this->dom(self::$listHtml)->query('//td/span[contains(@class,"status-badge")]')->length,
            'a lista nao marcou o status do pedido'
        );
    }

    public function testDetailLinkPointsToOrderDetail(): void
    {
        $dom = $this->dom(self::$listHtml);

        $link = $dom->query("//a[contains(@href,'order-detail.php?id=')]")->item(0);

        $this->assertNotNull($link, 'a lista nao linka para o detalhe');
        $this->assertStringStartsWith('/TCC_Etec/', $link->getAttribute('href'));
    }

    // =================================================================
    //  Detalhe
    // =================================================================

    public function testDetailIsASingleDocumentInTheAccountShell(): void
    {
        $dom = $this->dom(self::$detailHtml);

        $this->assertSame(1, $dom->query('/html')->length);
        $this->assertSame(1, $dom->query('//div[contains(@class,"account-shell")]')->length);
        $this->assertSame(1, $dom->query('//main[contains(@class,"account-main")]')->length);
        $this->assertSame('Detalhes do Pedido - Royal Tech', trim((string) $dom->query('//title')->item(0)?->textContent));
    }

    public function testDetailMarksOrdersAsCurrentSidebarItem(): void
    {
        $current = $this->dom(self::$detailHtml)
            ->query('//nav[contains(@class,"account-nav")]//a[contains(@class,"is-current")]');

        $this->assertSame(1, $current->length);
        $this->assertSame('Meus Pedidos', trim($current->item(0)->textContent));
    }

    public function testDetailShowsOrderNumberItemsAndTotal(): void
    {
        $this->assertSame(
            'Pedido ' . self::$detailMeta['order_number'],
            trim((string) $this->dom(self::$detailHtml)
                ->query('//h1[contains(@class,"account-page-title")]')
                ->item(0)?->textContent)
        );

        $this->assertStringContainsString(
            self::$detailMeta['product_name'],
            self::$detailHtml,
            'o item do pedido nao apareceu no detalhe'
        );

        $this->assertSame(1, $this->dom(self::$detailHtml)->query("//*[@id='secao-detalhe']")->length);

        $this->assertStringContainsString(
            'R$ 1.234,56',
            self::$detailHtml,
            'o total do pedido nao apareceu no detalhe'
        );
    }

    public function testDetailHasBackDownloadAndResendActions(): void
    {
        $dom = $this->dom(self::$detailHtml);

        $this->assertSame(
            1,
            $dom->query("//*[@id='secao-detalhe']//a[contains(@href,'/pages/auth/orders.php')]")->length,
            'faltou o Voltar'
        );
        $this->assertSame(
            1,
            $dom->query("//*[@id='secao-detalhe']//a[contains(@href,'download-comprovante.php')]")->length,
            'faltou o Baixar Comprovante'
        );
        $this->assertSame(
            1,
            $dom->query("//*[@id='secao-detalhe']//form[contains(@action,'comprovante-resend.php')]")->length,
            'faltou o form de reenvio do comprovante'
        );
    }

    public function testDetailOfPaidOrderHasNoCancelForm(): void
    {
        // O pedido temporário nasce 'paid': o botão de cancelar só
        // existe para pedidos pendentes.
        $this->assertStringNotContainsString(
            'name="action" value="cancel"',
            self::$detailHtml,
            'pedido pago nao pode oferecer cancelamento'
        );
    }
}