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
    private static string $page1Html = '';
    private static string $page2Html = '';
    private static string $clampedHtml = '';
    private static string $page2PaidHtml = '';
    private static array $meta = [];

    public static function setUpBeforeClass(): void
    {
        self::$listHtml     = self::render('list');
        self::$filteredHtml = self::render('list-filtered');
        self::$detailHtml   = self::render('detail');
        self::$page1Html    = self::render('list-page1');
        self::$page2Html    = self::render('list-page2');
        self::$clampedHtml  = self::render('list-page-clamped');
        self::$page2PaidHtml = self::render('list-page2-paid');
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
    //  Paginação (10 por página)
    // =================================================================

    /**
     * Ids dos pedidos da página, na ordem em que a tela os mostra.
     */
    private function orderIdsOf(string $html): array
    {
        preg_match_all('~order-detail\.php\?id=(\d+)~', $html, $matches);

        return array_map('intval', $matches[1]);
    }

    /**
     * Só os pedidos temporários da fixture, reconhecidos pelo total
     * R$ 1.234,56. Isolar assim deixa a contagem estável mesmo que o
     * banco de desenvolvimento já tenha pedidos do usuário 16.
     */
    private function tempOrderCount(string $html): int
    {
        return substr_count($html, 'R$ 1.234,56');
    }

    public function testFirstPageShowsTenOrdersAndNoMore(): void
    {
        $this->assertCount(
            10,
            $this->orderIdsOf(self::$page1Html),
            'a primeira página precisa trazer 10 pedidos'
        );
    }

    public function testEveryTempOrderAppearsExactlyOnceAcrossPages(): void
    {
        $page1 = $this->tempOrderCount(self::$page1Html);
        $page2 = $this->tempOrderCount(self::$page2Html);

        // A fixture cria 12 pedidos; some uma página inteira e parte da
        // outra. A soma tem de fechar em 12, senão a paginação perdeu
        // ou repetiu alguma linha.
        $this->assertSame(12, $page1 + $page2, 'as duas páginas não cobriram os 12 pedidos');

        $overlap = array_intersect($this->orderIdsOf(self::$page1Html), $this->orderIdsOf(self::$page2Html));
        $this->assertSame([], array_values($overlap), 'um pedido apareceu nas duas páginas');
    }

    public function testPaginationOffersBothPagesAndTheArrows(): void
    {
        $dom = $this->dom(self::$page1Html);
        $nav = $dom->query('//nav[contains(@class,"ml-pagination")]');

        $this->assertSame(1, $nav->length, 'o controle de paginação não apareceu com 13 pedidos');

        $numbers = [];
        foreach ($dom->query('//nav[contains(@class,"ml-pagination")]//a') as $link) {
            $text = trim($link->textContent);
            if ($text !== '') {
                $numbers[] = $text;
            }
        }

        $this->assertSame(['1', '2'], $numbers, 'na primeira página aparecem os números 1 e 2 com 12 pedidos');

        // Setas: anterior desabilitada na página 1, próxima habilitada.
        $this->assertSame(1, $dom->query('//nav[contains(@class,"ml-pagination")]//a[@rel="next"]')->length);
        $this->assertSame(0, $dom->query('//nav[contains(@class,"ml-pagination")]//a[@rel="prev"]')->length);
    }

    public function testSecondPageMarksItselfAsCurrentAndKeepsThePrevLink(): void
    {
        $dom = $this->dom(self::$page2Html);

        $this->assertSame(1, $dom->query('//nav[contains(@class,"ml-pagination")]')->length);

        $current = $dom->query('//nav[contains(@class,"ml-pagination")]//a[@aria-current="page"]');
        $this->assertSame(1, $current->length);
        $this->assertSame('2', trim($current->item(0)->textContent));

        $prev = $dom->query('//nav[contains(@class,"ml-pagination")]//a[@rel="prev"]');
        $this->assertSame(1, $prev->length);

        // Voltar para a página 1 é a URL sem ?page — o mesmo link de
        // "Meus Pedidos" da sidebar, que o teste de breadcrumb usa.
        $this->assertStringEndsWith(
            '/pages/auth/orders.php',
            $prev->item(0)->getAttribute('href')
        );
    }

    public function testPageBeyondTheLastFallsBackToTheLastOne(): void
    {
        // ?page=999 é o que aparece quando alguém edita a URL ou segue
        // um link velho depois de cancelar pedidos. Cada modo da
        // fixture cria os seus 12 pedidos, então a comparação é pelo
        // comportamento — o resto da lista, como na última página — e
        // não pelos ids, que mudam a cada execução.
        $this->assertSame(
            $this->tempOrderCount(self::$page2Html),
            $this->tempOrderCount(self::$clampedHtml),
            '?page=999 deveria mostrar o resto da lista, como a última página'
        );

        $this->assertGreaterThan(
            0,
            $this->tempOrderCount(self::$clampedHtml),
            '?page=999 caiu numa página vazia'
        );

        // E a página marcada como atual é a última de verdade, não a
        // 999 que veio na URL.
        $current = $this->dom(self::$clampedHtml)
            ->query('//nav[contains(@class,"ml-pagination")]//a[@aria-current="page"]');

        $this->assertSame(1, $current->length);
        $this->assertSame('2', trim($current->item(0)->textContent));
    }

    public function testPaginationStaysHiddenWhenEverythingFitsOnOnePage(): void
    {
        // O modo "list" cria 1 pedido temporário: junto do #0012 do seed
        // são 2, bem menos que uma página.
        $this->assertSame(
            0,
            $this->dom(self::$listHtml)->query('//nav[contains(@class,"ml-pagination")]')->length,
            'não deveria haver paginação com 2 pedidos'
        );
    }

    public function testPageLinksKeepTheStatusFilterAndTheSearch(): void
    {
        // Sem recarregar a query, trocar de aba ou buscar depois de
        // paginar jogaria o filtro fora: o cliente cairia na lista toda
        // sem saber por quê.
        $nav = $this->dom(self::$page2PaidHtml)->query('//nav[contains(@class,"ml-pagination")]');

        $this->assertSame(1, $nav->length, 'a aba filtrada deveria paginar também');

        foreach ($this->dom(self::$page2PaidHtml)->query('//nav[contains(@class,"ml-pagination")]//a') as $link) {
            $this->assertStringContainsString(
                'status=paid',
                $link->getAttribute('href'),
                'o link de paginação perdeu o filtro de status'
            );
        }
    }

    // =================================================================
    //  Sem card de política de cancelamento (Etapa 3 da spec)
    // =================================================================

    public function testListHasNoCancelPolicyCards(): void
    {
        // A spec proíbe os quatro cards de política nesta tela: a regra
        // vive só como frase discreta em order-detail.php.
        $this->assertSame(
            0,
            $this->dom(self::$listHtml)->query("//*[contains(@class,'account-cancel-card')]")->length,
            'a lista de pedidos nao pode ter card de política de cancelamento'
        );
        $this->assertSame(
            0,
            $this->dom(self::$listHtml)->query("//*[@id='secao-cancelamento']")->length,
            'a secao de politica de cancelamento deve ter sido removida'
        );
    }

    public function testListHasOrderCounterNextToTitle(): void
    {
        $counter = $this->dom(self::$listHtml)->query("//*[contains(@class,'account-order-count')]")->item(0);

        $this->assertNotNull($counter, 'faltou o contador de pedidos ao lado do titulo');
        $this->assertMatchesRegularExpression(
            '/\d+\s+pedido\(s\)/',
            trim($counter->textContent),
            'o contador deve seguir o padrao "N pedido(s)"'
        );
    }

    public function testListHasSearchFieldForOrderNumber(): void
    {
        $dom = $this->dom(self::$listHtml);

        $this->assertSame(
            1,
            $dom->query("//form[contains(@class,'account-search')]//input[@name='q']")->length,
            'faltou o campo de busca por numero do pedido'
        );
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

    public function testDetailBreadcrumbEndsOnTheScreenNotOnTheSection(): void
    {
        $labels = [];
        foreach ($this->dom(self::$detailHtml)->query('//nav[contains(@class,"account-breadcrumb")]//li') as $li) {
            $labels[] = trim(preg_replace('/\s+/', ' ', $li->textContent) ?? '');
        }

        // A sidebar continua em "Meus Pedidos", mas a última etapa do
        // breadcrumb é a tela, não a seção: "Início / Detalhes do Pedido".
        $this->assertSame(['Início', 'Detalhes do Pedido'], $labels);
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

    public function testDetailOfCancelableOrderShowsCancelFormAndRule(): void
    {
        // O pedido temporário nasce 'paid'. A regra de domínio
        // (order_can_cancel, OrderStateTest) permite cancelar em
        // pending/paid/preparing: o detalhe segue a regra central em vez
        // de duplicar a lógica na tela.
        $dom = $this->dom(self::$detailHtml);

        $this->assertSame(
            1,
            $dom->query("//form//input[@name='action'][@value='cancel']")->length,
            'pedido pago ainda permite cancelar e deve oferecer o form'
        );

        // A regra aparece como frase discreta, nunca como card de política.
        $this->assertGreaterThanOrEqual(
            1,
            $dom->query("//*[contains(@class,'account-cancel-rule')]")->length,
            'faltou a frase discreta de regra de cancelamento'
        );
        $this->assertSame(
            0,
            $dom->query("//*[contains(@class,'account-cancel-card')]")->length,
            'a tela de detalhe nao pode ter card de política de cancelamento'
        );
    }
}