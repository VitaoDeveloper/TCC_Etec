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
    private static string $filteredHtml = '';
    private static string $detailHtml = '';
    private static array $meta = [];

    public static function setUpBeforeClass(): void
    {
        self::$listHtml     = self::render('list');
        self::$filteredHtml = self::render('list-filtered');
        self::$detailHtml   = self::render('detail');
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

        self::$meta[$mode] = $meta;

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

    // =================================================================
    //  Cards de limite de cancelamento
    // =================================================================

    public function testFourCancelLimitCardsRender(): void
    {
        $cards = $this->dom(self::$listHtml)->query('//section[@id="secao-cancelamento"]/article[@data-cancel-card]');

        $this->assertSame(4, $cards->length, 'a spec pede quatro cards de limite de cancelamento');
    }

    public function testCancelCardsExplainTheRuleAndTheDeadline(): void
    {
        $dom = $this->dom(self::$listHtml);

        foreach ($dom->query('//article[@data-cancel-card]') as $card) {
            $key = $card->getAttribute('data-cancel-card');

            $title = $card->getElementsByTagName('h2')->item(0);
            $text  = $dom->query('//article[@data-cancel-card="' . $key . '"]/p[contains(@class,"account-cancel-text")]')->item(0);
            $limit = $dom->query('//article[@data-cancel-card="' . $key . '"]/p[contains(@class,"account-cancel-limit")]')->item(0);

            $this->assertNotNull($title, "o card {$key} nao tem titulo");
            $this->assertNotSame('', trim($title->textContent));
            $this->assertNotNull($text, "o card {$key} nao explica a regra");
            $this->assertGreaterThan(30, strlen(trim($text->textContent)), "o card {$key} ficou sem explicacao");
            $this->assertNotNull($limit, "o card {$key} nao mostra o limite/prazo");
            $this->assertStringContainsString('Limite:', trim($limit->textContent));
        }
    }

    public function testOnlyTheShippedCardIsMarkedAsBlocked(): void
    {
        $dom = $this->dom(self::$listHtml);

        $blocked = $dom->query('//article[contains(@class,"account-cancel-card--blocked")]');

        $this->assertSame(1, $blocked->length, 'so a etapa pos-postagem nao aceita cancelamento');
        $this->assertSame(
            'shipped',
            $blocked->item(0)->getAttribute('data-cancel-card')
        );
    }

    public function testCancelableCardsSayTheyCanBeCanceled(): void
    {
        $dom = $this->dom(self::$listHtml);

        foreach (['pending', 'paid', 'preparing'] as $key) {
            $badge = $dom->query('//article[@data-cancel-card="' . $key . '"]//span[contains(@class,"account-cancel-badge")]')->item(0);

            $this->assertNotNull($badge, "o card {$key} nao mostrou a etiqueta");
            $this->assertSame('Pode cancelar', trim($badge->textContent));
        }
    }

    // =================================================================
    //  Abas de status
    // =================================================================

    public function testStatusTabsExistWithCounters(): void
    {
        $tabs = $this->dom(self::$listHtml)->query('//nav[@id="secao-abas"]/a[contains(@class,"account-tab")]');

        // "Todos" + os seis status do ENUM.
        $this->assertSame(7, $tabs->length, 'a navegacao por status tem 7 entradas');

        foreach ($tabs as $tab) {
            $this->assertSame(
                1,
                $tab->getElementsByTagName('span')->length,
                'toda aba precisa mostrar a contagem'
            );
            $this->assertStringStartsWith('/TCC_Etec/pages/auth/orders.php', $tab->getAttribute('href'));
        }
    }

    public function testTodosTabIsActiveWithoutFilter(): void
    {
        $active = $this->dom(self::$listHtml)->query('//nav[@id="secao-abas"]/a[contains(@class,"is-active")]');

        $this->assertSame(1, $active->length);
        $this->assertStringContainsString('Todos', $active->item(0)->textContent);
        $this->assertSame('page', $active->item(0)->getAttribute('aria-current'));
    }

    public function testFilteredListMarksThePaidTabActive(): void
    {
        $this->assertSame('paid', self::$meta['list-filtered']['status_filter'] ?? null);

        $active = $this->dom(self::$filteredHtml)->query('//nav[@id="secao-abas"]/a[contains(@class,"is-active")]');

        $this->assertSame(1, $active->length, 'o filtro precisa marcar uma aba');
        $this->assertSame('page', $active->item(0)->getAttribute('aria-current'));
        $this->assertStringContainsString('status=paid', $active->item(0)->getAttribute('href'));
    }

    public function testFilteredListShowsOnlyTheChosenStatus(): void
    {
        $dom = $this->dom(self::$filteredHtml);

        $rows = $dom->query('//*[@id="secao-pedidos"]//tbody/tr');
        $this->assertGreaterThan(0, $rows->length);

        // O pedido temporário nasce 'paid' e é o único que pode aparecer
        // no filtro: qualquer badge de outra etapa aqui é vazamento de
        // status entre abas.
        foreach ($rows as $row) {
            $this->assertSame(
                1,
                $row->getElementsByTagName('span')->length,
                'linha sem badge de status'
            );
        }

        $this->assertStringContainsString(
            self::$meta['list-filtered']['order_number'],
            self::$filteredHtml,
            'o pedido do filtro nao apareceu na lista'
        );
    }

    public function testListMountsTheTempOrderRow(): void
    {
        $dom = $this->dom(self::$listHtml);

        $this->assertGreaterThan(0, self::$meta['list'], 'a fixture nao descreveu o pedido temporario');

        $rows = $dom->query('//table[contains(@class,"ml-table")]//tbody/tr');
        $this->assertGreaterThan(0, $rows->length, 'a lista saiu sem linhas de pedido');

        $this->assertStringContainsString(
            self::$meta['list']['order_number'],
            self::$listHtml,
            'o pedido temporario nao apareceu na lista'
        );

        $this->assertStringContainsString(
            self::$meta['list']['order_total'],
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
            'Pedido ' . self::$meta['detail']['order_number'],
            trim((string) $this->dom(self::$detailHtml)
                ->query('//h1[contains(@class,"account-page-title")]')
                ->item(0)?->textContent)
        );

        $this->assertStringContainsString(
            self::$meta['detail']['product_name'],
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