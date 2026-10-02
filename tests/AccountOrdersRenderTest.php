<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Teste de renderização da lista e do detalhe de pedidos.
 *
 * Monta as páginas reais (pages/auth/orders.php e order-detail.php)
 * dentro do shell da conta, com pedidos temporários criados pela
 * fixture — as asserções valem para qualquer banco, mesmo sem pedidos
 * semeados para o usuário de teste.
 *
 * A lista é testada por cenário (pendente com Pix, enviado com
 * rastreio, entregue, cancelado, reembolsado, ordenações, buscas e a
 * página sem histórico nenhum), porque o redesign promete um card
 * diferente para cada estado: um teste que só enxerga "pago" não
 * provaria nenhum deles.
 */
class AccountOrdersRenderTest extends TestCase
{
    /** @var array<string, string> modo => HTML renderizado */
    private static array $html = [];

    /** @var array<string, array> modo => metadados da fixture */
    private static array $meta = [];

    /**
     * Modos renderizados uma vez por execução: cada um é um processo PHP
     * separado com inserts e deletes no banco, e repetir isso por
     * asserção transformaria a suíte em minutos.
     */
    private const MODES = [
        'list', 'list-no-orders', 'list-single-order', 'list-mixed', 'list-mixed-filtered',
        'list-page1', 'list-page2', 'list-page-clamped', 'list-page2-paid',
        'list-sort-oldest', 'list-sort-value', 'list-sort-invalid',
        'list-search-number', 'list-search-hash', 'list-search-product',
        'list-search-wildcard', 'list-search-none',
        'list-filtered', 'list-pending-pix', 'list-shipped',
        'list-delivered', 'list-canceled', 'list-status-refunded',
        'detail',
    ];

    public static function setUpBeforeClass(): void
    {
        foreach (self::MODES as $mode) {
            self::$html[$mode] = self::render($mode);
        }
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

    // =================================================================
    //  Utilidades
    // =================================================================

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $doc->loadHTML($html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function meta(string $mode): array
    {
        return self::$meta[$mode] ?? [];
    }

    /** Ids dos pedidos em cards, na ordem em que a tela os mostra. */
    private function orderIds(string $html): array
    {
        preg_match_all(
            '~<article class="order-card"[^>]*aria-labelledby="order-title-(\d+)"~',
            $html,
            $matches
        );

        return array_map('intval', $matches[1]);
    }

    /**
     * Só os pedidos temporários do cenário, na ordem da tela.
     *
     * Isolar por id (e não por total) mantém a comparação estável mesmo
     * que o banco de desenvolvimento já tenha pedidos do usuário: o
     * id é único e é o que a fixture devolveu.
     */
    private function tempIds(string $html, string $mode): array
    {
        return array_values(array_intersect(
            $this->orderIds($html),
            $this->meta($mode)['temp_ids'] ?? []
        ));
    }

    private function firstTempCard(string $html, string $mode): ?DOMElement
    {
        $ids  = $this->tempIds($html, $mode);
        $id   = $ids[0] ?? null;
        if ($id === null) {
            return null;
        }

        $card = $this->dom($html)->query("//article[@class='order-card'][@aria-labelledby='order-title-{$id}']")->item(0);

        return $card instanceof DOMElement ? $card : null;
    }

    // =================================================================
    //  Shell da conta
    // =================================================================

    public function testListIsASingleDocumentInTheAccountShell(): void
    {
        $dom = $this->dom(self::$html['list']);

        $this->assertSame(1, $dom->query('/html')->length);
        $this->assertSame(1, $dom->query('//div[contains(@class,"account-shell")]')->length);
        $this->assertSame(1, $dom->query('//aside[contains(@class,"account-side")]')->length);
        $this->assertSame(1, $dom->query('//main[contains(@class,"account-main")]')->length);
        $this->assertSame('Meus Pedidos - Royal Tech', trim((string) $dom->query('//title')->item(0)?->textContent));
    }

    public function testOrdersIsTheCurrentSidebarItem(): void
    {
        $current = $this->dom(self::$html['list'])
            ->query('//nav[contains(@class,"side-menu")]//a[contains(@class,"active")]');

        $this->assertSame(1, $current->length, 'a sidebar precisa marcar exatamente um item');
        $this->assertSame('Meus Pedidos', trim($current->item(0)->textContent));
        $this->assertSame('page', $current->item(0)->getAttribute('aria-current'));
    }

    public function testBreadcrumbKeepsTheFullWidthContainer(): void
    {
        $dom = $this->dom(self::$html['list']);

        $labels = [];
        foreach ($dom->query('//nav[contains(@class,"account-breadcrumb")]//li') as $li) {
            $labels[] = trim(preg_replace('/\s+/', ' ', $li->textContent) ?? '');
        }

        $this->assertSame(['Início', 'Meus Pedidos'], $labels);
    }

    public function testListHasTitleAndOrderSection(): void
    {
        $dom = $this->dom(self::$html['list']);

        $this->assertSame(
            'Meus Pedidos',
            trim((string) $dom->query('//h1[contains(@class,"account-page-title")]')->item(0)?->textContent)
        );
        $this->assertSame(1, $dom->query("//*[@id='secao-pedidos']")->length);
    }

    public function testListHasNoCountPillNextToTitle(): void
    {
        // O redesign trocou a pílula "N pedido(s)" ao lado do título pelos
        // quatro cards de resumo: repetir o número nos dois lugares era
        // redundância, não informação.
        $this->assertSame(
            0,
            $this->dom(self::$html['list'])->query("//*[contains(@class,'account-order-count')]")->length,
            'o contador ao lado do titulo foi removido pelo redesign'
        );
    }

    // =================================================================
    //  Resumo (4 cards)
    // =================================================================

    public function testSummaryShowsTheFourCards(): void
    {
        $dom = $this->dom(self::$html['list']);

        $this->assertSame(
            1,
            $dom->query('//section[contains(@class,"orders-stats")]')->length,
            'faltou a faixa de resumo'
        );
        // ' stat-card ' com espaço nos dois lados: contains(@class,"stat-card")
        // também casaria com stat-card__icon e stat-card__value, e a
        // contagem daria 8 em vez de 4.
        $statCards = $dom->query(
            '//section[contains(@class,"orders-stats")]//div'
            . '[contains(concat(" ", normalize-space(@class), " "), " stat-card ")]'
        );

        $this->assertSame(4, $statCards->length);

        $labels = [];
        foreach ($dom->query('//section[contains(@class,"orders-stats")]//span[contains(@class,"stat-card__label")]') as $label) {
            $labels[] = trim($label->textContent);
        }

        $this->assertSame(
            ['Pedidos totais', 'Em andamento', 'Entregues', 'Total comprado'],
            $labels,
            'os quatro cards do resumo, na ordem do redesign'
        );
    }

    public function testSummaryIsAllZeroWithoutHistory(): void
    {
        $dom = $this->dom(self::$html['list-no-orders']);

        $values = [];
        foreach ($dom->query('//section[contains(@class,"orders-stats")]//span[contains(@class,"stat-card__value")]') as $value) {
            $values[] = trim($value->textContent);
        }

        $this->assertSame(['0', '0', '0', 'R$ 0,00'], $values);
    }

    public function testSummaryWithASinglePaidOrder(): void
    {
        $dom = $this->dom(self::$html['list-single-order']);

        $values = [];
        foreach ($dom->query('//section[contains(@class,"orders-stats")]//span[contains(@class,"stat-card__value")]') as $value) {
            $values[] = trim($value->textContent);
        }

        $this->assertSame(['1', '1', '0', 'R$ 1.234,56'], $values);

        $hint = $dom->query('//section[contains(@class,"orders-stats")]//span[contains(@class,"stat-card__hint")]')->item(0);
        $this->assertSame('1 pedido pago', trim((string) $hint?->textContent));
    }

    public function testSummaryCountsOnlyPaidOrdersAsPurchased(): void
    {
        // Cenário list-mixed: 1 pendente + 1 pago (R$ 100) + 1 entregue
        // (R$ 200) + 1 cancelado reembolsado (R$ 400).
        $dom = $this->dom(self::$html['list-mixed']);

        $values = [];
        foreach ($dom->query('//section[contains(@class,"orders-stats")]//span[contains(@class,"stat-card__value")]') as $value) {
            $values[] = trim($value->textContent);
        }

        $this->assertSame(
            ['4', '2', '1', 'R$ 300,00'],
            $values,
            'pendente nao conta como em andamento comprado, e cancelado/reembolsado nao entra no total'
        );

        $hint = $dom->query('//section[contains(@class,"orders-stats")]//span[contains(@class,"stat-card__hint")]')->item(0);
        $this->assertSame('2 pedidos pagos', trim((string) $hint?->textContent));
    }

    public function testSummaryDoesNotMoveWithTheFilter(): void
    {
        // O resumo é a fotografia da conta, não uma métrica do recorte:
        // o cliente precisa de uma referência estável enquanto navega
        // entre os chips.
        $this->assertSame(
            $this->summaryValues('list'),
            $this->summaryValues('list-filtered'),
            'o resumo nao pode mudar ao filtrar por status'
        );
    }

    /** @return string[] */
    private function summaryValues(string $mode): array
    {
        $values = [];
        $dom    = $this->dom(self::$html[$mode]);
        foreach ($dom->query('//section[contains(@class,"orders-stats")]//span[contains(@class,"stat-card__value")]') as $value) {
            $values[] = trim($value->textContent);
        }

        return $values;
    }

    // =================================================================
    //  Chips de status
    // =================================================================

    public function testStatusChipsExistWithCounters(): void
    {
        $dom  = $this->dom(self::$html['list-mixed']);
        $chips = $dom->query('//nav[contains(@class,"orders-chips")]/a[contains(@class,"chip")]');

        // "Todos" + pendente, pago, em preparacao, enviado, entregue,
        // cancelado e reembolsado.
        $this->assertSame(8, $chips->length, 'a navegacao por status tem 8 entradas');

        foreach ($chips as $chip) {
            $this->assertSame(
                1,
                $chip->getElementsByTagName('span')->length,
                'todo chip precisa mostrar a contagem'
            );
            $this->assertStringStartsWith('/TCC_Etec/pages/auth/orders.php', $chip->getAttribute('href'));
        }
    }

    public function testChipsPartitionTheWholeHistory(): void
    {
        // Se os sete chips somassem mais que "Todos", algum pedido
        // estaria em dois grupos ao mesmo tempo — foi o que aconteceria
        // se o cancelado reembolsado contasse também como cancelado.
        $dom  = $this->dom(self::$html['list-mixed']);
        $sum  = 0;
        $todo = null;

        foreach ($dom->query('//nav[contains(@class,"orders-chips")]/a') as $chip) {
            $count = (int) trim(
                (string) $chip->getElementsByTagName('span')->item(0)?->textContent
            );

            if (str_contains($chip->textContent, 'Todos')) {
                $todo = $count;
                continue;
            }

            $sum += $count;
        }

        $this->assertSame(4, $todo);
        $this->assertSame($todo, $sum, 'os chips de status precisam cobrir o historico uma vez so');
    }

    public function testCanceledWithRefundCountsOnlyAsRefunded(): void
    {
        $dom    = $this->dom(self::$html['list-mixed']);
        $counts = $this->chipCounts($dom);

        $this->assertSame(1, $counts['Reembolsados'], 'o cancelado com estorno e um reembolsado');
        $this->assertSame(0, $counts['Cancelados'], 'e nao pode aparecer tambem como cancelado');
    }

    /** @return array<string, int> rótulo do chip => contagem */
    private function chipCounts(DOMXPath $dom): array
    {
        $counts = [];
        foreach ($dom->query('//nav[contains(@class,"orders-chips")]/a') as $chip) {
            $label = trim(preg_replace('/\s*\d+$/', '', trim($chip->textContent)) ?? '');
            $counts[$label] = (int) trim(
                (string) $chip->getElementsByTagName('span')->item(0)?->textContent
            );
        }

        return $counts;
    }

    public function testTodosChipIsActiveWithoutFilter(): void
    {
        $active = $this->dom(self::$html['list'])
            ->query('//nav[contains(@class,"orders-chips")]/a[contains(@class,"is-active")]');

        $this->assertSame(1, $active->length);
        $this->assertStringContainsString('Todos', $active->item(0)->textContent);
        $this->assertSame('page', $active->item(0)->getAttribute('aria-current'));
    }

    public function testFilteredListMarksThePaidChipActive(): void
    {
        $this->assertSame('paid', $this->meta('list-filtered')['status_filter'] ?? null);

        $active = $this->dom(self::$html['list-filtered'])
            ->query('//nav[contains(@class,"orders-chips")]/a[contains(@class,"is-active")]');

        $this->assertSame(1, $active->length, 'o filtro precisa marcar um chip');
        $this->assertSame('page', $active->item(0)->getAttribute('aria-current'));
        $this->assertStringContainsString('status=paid', $active->item(0)->getAttribute('href'));
    }

    public function testFilteredListShowsOnlyTheChosenStatus(): void
    {
        $dom   = $this->dom(self::$html['list-filtered']);
        $cards = $dom->query("//*[@id='secao-pedidos']//article[contains(@class,'order-card')]");

        $this->assertGreaterThan(0, $cards->length);

        foreach ($cards as $card) {
            $pill = $card->getElementsByTagName('span')->item(0);
            $this->assertNotNull($pill);
            $this->assertStringContainsString('pill--paid', (string) $pill->getAttribute('class'));
        }
    }

    public function testRefundedChipShowsTheRefundedPill(): void
    {
        $card = $this->firstTempCard(self::$html['list-status-refunded'], 'list-status-refunded');

        $this->assertNotNull($card, 'o pedido reembolsado nao virou card');
        $this->assertStringContainsString(
            'pill--refunded',
            (string) $card->getElementsByTagName('span')->item(0)?->getAttribute('class')
        );
        $this->assertStringContainsString('Reembolsado', (string) $card->textContent);
    }

    public function testChipsKeepTheSearchAndTheSort(): void
    {
        // Trocar de status não pode jogar fora o que o cliente digitou
        // nem a ordenação escolhida: seria voltar silenciosamente para a
        // lista padrão.
        $dom = $this->dom(self::$html['list-sort-value']);

        foreach ($dom->query('//nav[contains(@class,"orders-chips")]/a') as $chip) {
            $href = $chip->getAttribute('href');

            $this->assertStringContainsString('sort=value_desc', $href);
            $this->assertStringNotContainsString('page=', $href, 'trocar de chip volta para a primeira pagina');
        }
    }

    public function testChipCountsIgnoreTheChipThatIsSelected(): void
    {
        // Regressão: a query dos contadores reaproveitava o mesmo WHERE da
        // lista, então ao escolher "Pagos" o "Todos" caía para o número de
        // pagos e o cliente perdia a leitura do histórico completo. O
        // contador tem que ignorar o chip ativo — e só ele.
        $counts = function (string $html): array {
            $found = [];

            foreach ($this->dom($html)->query('//nav[contains(@class,"orders-chips")]/a') as $chip) {
                $count = $chip->getElementsByTagName('span')->item(0);
                if ($count === null || !str_contains((string) $count->getAttribute('class'), 'chip-count')) {
                    continue;
                }

                // Chave pela query do link, não pelo href inteiro: o chip
                // "Todos" aponta para a URL limpa (sem ?status=), e um
                // caminho de arquivo não diria qual chip é qual.
                $query = (string) parse_url($chip->getAttribute('href'), PHP_URL_QUERY);
                parse_str($query, $params);

                $found[(string) ($params['status'] ?? '')] = trim($count->textContent);
            }

            return $found;
        };

        $unfiltered = $counts(self::$html['list-mixed']);
        $filtered   = $counts(self::$html['list-mixed-filtered']);

        // 4 pedidos: 1 pendente, 1 pago, 1 entregue, 1 cancelado/estornado.
        $this->assertSame('4', $unfiltered[''] ?? null, '"Todos" precisa somar os quatro estados');
        $this->assertSame('1', $unfiltered['paid'] ?? null);
        $this->assertSame('1', $unfiltered['pending'] ?? null);
        $this->assertSame('1', $unfiltered['delivered'] ?? null);
        $this->assertSame('1', $unfiltered['refunded'] ?? null);

        // Com "Pagos" ativo os outros números não podem mudar.
        foreach (['', 'pending', 'delivered', 'refunded'] as $key) {
            $this->assertSame(
                $unfiltered[$key] ?? null,
                $filtered[$key] ?? null,
                "o contador de \"{$key}\" mudou ao escolher um chip"
            );
        }
    }

    // =================================================================
    //  Busca
    // =================================================================

    public function testSearchFormHasFieldForNumberOrProduct(): void
    {
        $dom = $this->dom(self::$html['list']);

        $this->assertSame(
            1,
            $dom->query("//form[contains(@class,'orders-toolbar')]//input[@name='q']")->length,
            'faltou o campo de busca'
        );
        $this->assertSame(
            1,
            $dom->query("//form[contains(@class,'orders-toolbar')]//select[@name='sort']")->length,
            'faltou o seletor de ordenacao'
        );
    }

    public function testSearchByOrderNumber(): void
    {
        $ids = $this->tempIds(self::$html['list-search-number'], 'list-search-number');

        $this->assertSame(
            [$this->meta('list-search-number')['first_id']],
            $ids,
            'buscar pelo numero do pedido tem que trazer o proprio pedido'
        );
    }

    public function testSearchAcceptsTheHashPrefixedNumber(): void
    {
        // "#0006", "0006" e "6" sao o mesmo pedido 6: o que o cliente
        // copia da tela precisa funcionar colado direto na busca.
        $this->assertSame(
            '#' . $this->meta('list-search-hash')['first_id'],
            (string) ($this->meta('list-search-hash')['query']['q'] ?? ''),
            'a fixture precisa exercitar o formato com #'
        );

        $ids = $this->tempIds(self::$html['list-search-hash'], 'list-search-hash');

        $this->assertSame([$this->meta('list-search-hash')['first_id']], $ids);
    }

    public function testSearchByProductName(): void
    {
        $ids = $this->tempIds(self::$html['list-search-product'], 'list-search-product');

        $this->assertNotEmpty(
            $ids,
            'buscar pelo nome do produto tem que trazer o pedido que o comprou'
        );
    }

    public function testSearchTreatsWildcardAsText(): void
    {
        // "%" é o curinga do LIKE. Sem escape, a busca por "%" traria o
        // histórico inteiro e o cliente receberia uma lista que nada tem
        // a ver com o que ele digitou.
        $this->assertSame('%', (string) ($this->meta('list-search-wildcard')['query']['q'] ?? ''));

        $this->assertSame(
            [],
            $this->orderIds(self::$html['list-search-wildcard']),
            'o curinga digitado não pode virar "todos os pedidos"'
        );
        $this->assertStringContainsString(
            'Nenhum pedido encontrado',
            self::$html['list-search-wildcard']
        );
    }

    public function testSearchFormKeepsTheStatusFilter(): void
    {
        // Buscar de dentro de um chip não pode devolver a lista toda.
        $form = $this->dom(self::$html['list-filtered'])
            ->query("//form[contains(@class,'orders-toolbar')]")
            ->item(0);

        $this->assertNotNull($form);

        $hidden = (new DOMXPath($form->ownerDocument))
            ->query(".//input[@name='status']", $form)
            ->item(0);

        $this->assertNotNull($hidden, 'a busca perdeu o filtro de status');
        $this->assertSame('paid', $hidden->getAttribute('value'));
    }

    // =================================================================
    //  Ordenação
    // =================================================================

    public function testSortByOldestPutsTheEarliestFirst(): void
    {
        $ids = $this->tempIds(self::$html['list-sort-oldest'], 'list-sort-oldest');

        $this->assertSame(
            [$this->meta('list-sort-oldest')['temp_ids'][0]],
            [$ids[0] ?? null],
            'em "mais antigos" o primeiro card é o pedido mais velho'
        );
    }

    public function testSortByValuePutsTheHighestFirst(): void
    {
        $meta = $this->meta('list-sort-value');
        $ids  = $this->tempIds(self::$html['list-sort-value'], 'list-sort-value');

        // A fixture cria R$ 300, R$ 900 e R$ 100.
        $this->assertSame(
            $meta['temp_ids'][1],
            $ids[0] ?? null,
            'em "maior valor" o primeiro card é o de R$ 900'
        );

        // Confirma pelo total gravado, e não só pela posição: os dois
        // cards podem sair na ordem certa e o total vir errado.
        $this->assertSame(
            900.00,
            (float) ($meta['temp_totals'][$ids[0] ?? 0] ?? 0),
            'o primeiro card em "maior valor" precisa exibir R$ 900'
        );
    }

    public function testValueSortDoesNotGroupByMonth(): void
    {
        // Agrupar por mês numa lista ordenada por valor colocaria um
        // cabeçalho de mês no meio de valores que não estão em ordem
        // cronológica.
        $this->assertSame(
            0,
            $this->dom(self::$html['list-sort-value'])
                ->query("//h2[contains(@class,'orders-group')]")->length,
            'ordenar por valor nao pode agrupar por mes'
        );

        $this->assertGreaterThan(
            0,
            $this->dom(self::$html['list-sort-oldest'])
                ->query("//h2[contains(@class,'orders-group')]")->length,
            'ordenar por data tem que agrupar por mes'
        );
    }

    public function testMonthGroupIsWrittenInPortuguese(): void
    {
        $months = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
        ];

        foreach ($this->dom(self::$html['list-sort-oldest'])->query("//h2[contains(@class,'orders-group')]") as $group) {
            $this->assertMatchesRegularExpression(
                '/^(' . implode('|', $months) . ') de \d{4}$/',
                trim($group->textContent),
                'o grupo de mes precisa sair por extenso em portugues'
            );
        }
    }

    public function testInvalidSortFallsBackToTheDefault(): void
    {
        // ?sort=lixo não pode gerar erro de SQL nem bagunçar a lista: o
        // padrão é "mais recentes".
        $meta = $this->meta('list-sort-invalid');
        $ids  = $this->tempIds(self::$html['list-sort-invalid'], 'list-sort-invalid');

        $this->assertSame('lixo', (string) ($meta['query']['sort'] ?? ''));

        $selected = $this->dom(self::$html['list-sort-invalid'])
            ->query("//select[@name='sort']/option[@selected]")->item(0);

        $this->assertNotNull($selected, 'o seletor precisa refletir o valor efetivo');
        $this->assertSame('recent', $selected->getAttribute('value'));
        $this->assertSame(
            $meta['temp_ids'][1],
            $ids[0] ?? null,
            'sort invalido tem que cair em "mais recentes"'
        );
    }

    // =================================================================
    //  Cards
    // =================================================================

    public function testEachCardIsAnArticleNamedByItsTitle(): void
    {
        $dom = $this->dom(self::$html['list']);

        foreach ($dom->query("//*[@id='secao-pedidos']//article[contains(@class,'order-card')]") as $card) {
            $labelledBy = (string) $card->getAttribute('aria-labelledby');
            $this->assertNotSame('', $labelledBy, 'todo card precisa ser nomeado para o leitor de tela');

            $id = (string) $card->getElementsByTagName('h3')->item(0)?->getAttribute('id');
            $this->assertSame($labelledBy, $id);
            $this->assertMatchesRegularExpression(
                '/^order-title-\d+$/',
                $labelledBy
            );
        }
    }

    public function testCardShowsNumberStatusMetaItemsAndTotal(): void
    {
        $meta = $this->meta('list');
        $card = $this->firstTempCard(self::$html['list'], 'list');

        $this->assertNotNull($card);
        $this->assertStringContainsString($meta['temp_number'], (string) $card->textContent);
        $this->assertStringContainsString($meta['order_total'], (string) $card->textContent);
        $this->assertStringContainsString('Pix', (string) $card->textContent);

        $xpath = new DOMXPath($card->ownerDocument);

        $this->assertSame(1, $xpath->query(".//span[contains(@class,'pill--paid')]", $card)->length);
        $this->assertSame(1, $xpath->query(".//div[contains(@class,'order-thumb')]", $card)->length);
        $this->assertGreaterThanOrEqual(3, $xpath->query(".//span[contains(@class,'order-meta__item')]", $card)->length);
        $this->assertSame(1, $xpath->query(".//span[contains(@class,'order-items__name')]", $card)->length);
        $this->assertSame(
            1,
            $xpath->query(".//span[contains(@class,'order-items__more')]", $card)->length,
            'o segundo item do pedido tem que virar o badge +1'
        );
    }

    public function testCardThumbnailIsDecorative(): void
    {
        // A miniatura repete o nome do produto que vem logo ao lado: com
        // alt="" o leitor de tela não lê a mesma coisa duas vezes.
        foreach ($this->dom(self::$html['list'])->query("//img[contains(@class,'order-thumb__img')]") as $img) {
            $this->assertSame('', $img->getAttribute('alt'));
            $this->assertSame('lazy', $img->getAttribute('loading'));
        }
    }

    public function testTrackerHasFiveStepsOnlyWhenNotCanceled(): void
    {
        $paid = $this->firstTempCard(self::$html['list'], 'list');
        $this->assertNotNull($paid);

        $xpath = new DOMXPath($paid->ownerDocument);
        $steps = $xpath->query(".//ol[contains(@class,'tracker')]/li", $paid);

        $this->assertSame(5, $steps->length, 'o tracker tem 5 etapas');
        $this->assertSame(2, $xpath->query(".//ol[contains(@class,'tracker')]/li[contains(@class,'is-done')]", $paid)->length,
            'um pedido pago marcou realizado + pago');

        $canceled = $this->firstTempCard(self::$html['list-canceled'], 'list-canceled');
        $this->assertNotNull($canceled);

        $this->assertSame(
            0,
            (new DOMXPath($canceled->ownerDocument))
                ->query(".//ol[contains(@class,'tracker')]", $canceled)->length,
            'pedido cancelado nao mostra linha do tempo'
        );
    }

    public function testDeliveredCardKeepsTheTracker(): void
    {
        // Regressão: a condição escondia o tracker também em "entregue",
        // e é justamente no pedido entregue que o cliente olha para ver
        // a data da entrega. Só cancelado (e, portanto, reembolsado)
        // perde a linha do tempo.
        $delivered = $this->firstTempCard(self::$html['list-delivered'], 'list-delivered');
        $this->assertNotNull($delivered);

        $xpath = new DOMXPath($delivered->ownerDocument);
        $steps = $xpath->query(".//ol[contains(@class,'tracker')]/li", $delivered);

        $this->assertSame(5, $steps->length, 'o tracker do pedido entregue tem 5 etapas');
        $this->assertSame(
            5,
            $xpath->query(".//ol[contains(@class,'tracker')]/li[contains(@class,'is-done')]", $delivered)->length,
            'um pedido entregue marcou as cinco etapas'
        );

        $refunded = $this->firstTempCard(self::$html['list-status-refunded'], 'list-status-refunded');
        $this->assertNotNull($refunded);
        $this->assertSame(
            0,
            (new DOMXPath($refunded->ownerDocument))
                ->query(".//ol[contains(@class,'tracker')]", $refunded)->length,
            'pedido reembolsado nao mostra linha do tempo'
        );
    }

    public function testPendingPixCardShowsCountdownAndPayAction(): void
    {
        $card = $this->firstTempCard(self::$html['list-pending-pix'], 'list-pending-pix');

        $this->assertNotNull($card);
        $xpath = new DOMXPath($card->ownerDocument);

        // Qualquer elemento: o relogio é um <strong>, e procurar por <span>
        // daria zero e o teste passaria a falhar por elemento errado.
        $countdown = $xpath->query(".//*[@data-pix-deadline]", $card)->item(0);
        $this->assertNotNull($countdown, 'pedido com Pix valido precisa mostrar o relogio');

        // O atributo é timestamp, não string: o JS lê o número e não
        // precisa adivinhar o fuso da data.
        $this->assertMatchesRegularExpression('/^\d{9,11}$/', (string) $countdown->getAttribute('data-pix-deadline'));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}(:\d{2})?$/', trim($countdown->textContent));

        $this->assertSame('', $countdown->getAttribute('aria-live'), 'o relogio nao pode anunciar a cada segundo');

        $pay = $xpath->query(".//a[contains(@href,'/pages/cart/payment.php?order=')]", $card)->item(0);
        $this->assertNotNull($pay, 'faltou o botao de pagar');
        $this->assertStringContainsString('Pagar agora', (string) $pay->textContent);
    }

    public function testShippedCardShowsTrackingAndNotice(): void
    {
        $card = $this->firstTempCard(self::$html['list-shipped'], 'list-shipped');

        $this->assertNotNull($card);
        $xpath = new DOMXPath($card->ownerDocument);

        $track = $xpath->query(".//a[contains(@href,'correios.com.br')]", $card)->item(0);
        $this->assertNotNull($track, 'pedido enviado com etiqueta precisa do link de rastreio');
        $this->assertSame('_blank', (string) $track->getAttribute('target'));
        $this->assertStringContainsString('noopener', (string) $track->getAttribute('rel'));

        $this->assertStringContainsString('BR123456789BR', (string) $card->textContent);
        $this->assertSame(
            1,
            $xpath->query(".//p[contains(@class,'order-notice--info')]", $card)->length
        );
    }

    public function testDeliveredCardOffersReceiptAndReorder(): void
    {
        $card = $this->firstTempCard(self::$html['list-delivered'], 'list-delivered');

        $this->assertNotNull($card);
        $xpath = new DOMXPath($card->ownerDocument);

        $this->assertStringContainsString(
            $this->meta('list-delivered')['temp_number'],
            (string) $xpath->query(".//h3", $card)->item(0)?->textContent
        );
        $this->assertSame(1, $xpath->query(".//a[contains(@href,'download-comprovante.php')]", $card)->length);
        $this->assertSame(1, $xpath->query(".//a[contains(@href,'order-detail.php?id=')]", $card)->length);
        $this->assertSame(1, $xpath->query(".//form[@data-reorder]/button", $card)->length);
        $this->assertSame(
            1,
            $xpath->query(".//p[contains(@class,'order-notice--success')]", $card)->length
        );
    }

    public function testCanceledCardOffersReorderButNoReceipt(): void
    {
        $card = $this->firstTempCard(self::$html['list-canceled'], 'list-canceled');

        $this->assertNotNull($card);
        $xpath = new DOMXPath($card->ownerDocument);

        $this->assertSame(1, $xpath->query(".//form[@data-reorder]/button", $card)->length);
        $this->assertSame(
            0,
            $xpath->query(".//a[contains(@href,'download-comprovante.php')]", $card)->length,
            'pedido cancelado nao tem comprovante para baixar'
        );
        $this->assertSame(
            0,
            $xpath->query(".//a[contains(@href,'/pages/cart/payment.php')]", $card)->length,
            'pedido cancelado nao pode oferecer pagamento'
        );
    }

    public function testReorderFormCarriesCsrfAndSnapshotItems(): void
    {
        $card = $this->firstTempCard(self::$html['list-canceled'], 'list-canceled');

        $this->assertNotNull($card);
        $xpath = new DOMXPath($card->ownerDocument);

        $form = $xpath->query(".//form[@data-reorder]", $card)->item(0);
        $this->assertNotNull($form);

        $this->assertStringContainsString(
            '/pages/cart/add.php',
            (string) $form->getAttribute('action'),
            'o reenvio ao carrinho usa o mesmo endpoint do detalhe do pedido'
        );

        $csrf = $xpath->query(".//input[@name='_csrf_token']", $form)->item(0);
        $this->assertNotNull($csrf, 'POST sem CSRF seria uma vulnerabilidade');
        $this->assertNotSame('', trim((string) $csrf->getAttribute('value')));

        // Um par product_id/quantity por item, na mesma ordem: são os
        // arrays que o endpoint do carrinho lê.
        $this->assertSame(
            count($xpath->query(".//input[@name='product_id[]']", $form)),
            count($xpath->query(".//input[@name='quantity[]']", $form))
        );
        $this->assertGreaterThanOrEqual(2, $xpath->query(".//input[@name='product_id[]']", $form)->length);
    }

    public function testEveryOrderLinkIsAbsoluteAndInsideTheApp(): void
    {
        // Link quebrado ou javascript: no card é falha de segurança, não
        // de estilo: é o caminho que o cliente usa para agir.
        foreach (['list', 'list-pending-pix', 'list-delivered', 'list-canceled'] as $mode) {
            foreach ($this->dom(self::$html[$mode])->query('//div[contains(@class,"order-actions")]//a') as $link) {
                $href = (string) $link->getAttribute('href');

                $this->assertNotSame('', $href, "{$mode}: acao sem destino");
                $this->assertStringNotContainsString('javascript:', $href);
                $this->assertTrue(
                    str_starts_with($href, '/TCC_Etec/') || str_starts_with($href, 'https://'),
                    "{$mode}: link fora do app ou do dominio esperado: {$href}"
                );
            }
        }
    }

    // =================================================================
    //  Paginação (10 por página)
    // =================================================================

    public function testFirstPageShowsTenOrdersAndNoMore(): void
    {
        $this->assertCount(
            10,
            $this->tempIds(self::$html['list-page1'], 'list-page1'),
            'a primeira pagina precisa trazer 10 pedidos'
        );
    }

    public function testEveryTempOrderAppearsExactlyOnceAcrossPages(): void
    {
        $page1 = $this->tempIds(self::$html['list-page1'], 'list-page1');
        $page2 = $this->tempIds(self::$html['list-page2'], 'list-page2');

        // A fixture cria 12 pedidos; some uma página inteira e parte da
        // outra. A soma tem de fechar em 12, senão a paginação perdeu
        // ou repetiu alguma linha.
        $this->assertSame(12, count($page1) + count($page2), 'as duas paginas nao cobriram os 12 pedidos');

        $overlap = array_intersect($page1, $page2);
        $this->assertSame([], array_values($overlap), 'um pedido apareceu nas duas paginas');
    }

    public function testPaginationOffersBothPagesAndTheArrows(): void
    {
        $dom = $this->dom(self::$html['list-page1']);
        $nav = $dom->query('//nav[contains(@class,"ml-pagination")]');

        $this->assertSame(1, $nav->length, 'o controle de paginacao nao apareceu com 12 pedidos');

        $numbers = [];
        foreach ($dom->query('//nav[contains(@class,"ml-pagination")]//a') as $link) {
            $text = trim($link->textContent);
            if ($text !== '') {
                $numbers[] = $text;
            }
        }

        $this->assertSame(['1', '2'], $numbers, 'na primeira pagina aparecem os numeros 1 e 2 com 12 pedidos');

        // Setas: anterior desabilitada na página 1, próxima habilitada.
        $this->assertSame(1, $dom->query('//nav[contains(@class,"ml-pagination")]//a[@rel="next"]')->length);
        $this->assertSame(0, $dom->query('//nav[contains(@class,"ml-pagination")]//a[@rel="prev"]')->length);
    }

    public function testSecondPageMarksItselfAsCurrentAndKeepsThePrevLink(): void
    {
        $dom = $this->dom(self::$html['list-page2']);

        $this->assertSame(1, $dom->query('//nav[contains(@class,"ml-pagination")]')->length);

        $current = $dom->query('//nav[contains(@class,"ml-pagination")]//a[@aria-current="page"]');
        $this->assertSame(1, $current->length);
        $this->assertSame('2', trim($current->item(0)->textContent));

        $prev = $dom->query('//nav[contains(@class,"ml-pagination")]//a[@rel="prev"]');
        $this->assertSame(1, $prev->length);

        // Voltar para a página 1 é a URL sem ?page — o mesmo link de
        // "Meus Pedidos" da sidebar.
        $this->assertStringEndsWith(
            '/pages/auth/orders.php',
            $prev->item(0)->getAttribute('href')
        );
    }

    public function testPageBeyondTheLastFallsBackToTheLastOne(): void
    {
        // ?page=999 é o que aparece quando alguém edita a URL ou segue
        // um link velho depois de cancelar pedidos.
        //
        // A comparação é de contagem, não de id: cada cenário roda a
        // fixture em um processo próprio e cria pedidos novos, então os
        // ids da página 2 e os de ?page=999 nunca podem ser os mesmos.
        // O que precisa bater é "as duas Mostram o resto da lista".
        $page2     = $this->tempIds(self::$html['list-page2'], 'list-page2');
        $clamped   = $this->tempIds(self::$html['list-page-clamped'], 'list-page-clamped');

        $this->assertSame(
            count($page2),
            count($clamped),
            '?page=999 deveria mostrar o mesmo resto da lista que a pagina 2'
        );

        $this->assertGreaterThan(
            0,
            count($clamped),
            '?page=999 caiu numa pagina vazia'
        );

        // E a página marcada como atual é a última de verdade, não a
        // 999 que veio na URL.
        $current = $this->dom(self::$html['list-page-clamped'])
            ->query('//nav[contains(@class,"ml-pagination")]//a[@aria-current="page"]');

        $this->assertSame(1, $current->length);
        $this->assertSame('2', trim($current->item(0)->textContent));
    }

    public function testPaginationStaysHiddenWhenEverythingFitsOnOnePage(): void
    {
        $this->assertSame(
            0,
            $this->dom(self::$html['list'])->query('//nav[contains(@class,"ml-pagination")]')->length,
            'nao deveria haver paginacao com poucos pedidos'
        );
    }

    public function testPageLinksKeepTheStatusFilter(): void
    {
        // Sem recarregar a query, paginar depois de escolher um chip
        // jogaria o filtro fora: o cliente cairia na lista toda sem saber
        // por quê.
        $nav = $this->dom(self::$html['list-page2-paid'])->query('//nav[contains(@class,"ml-pagination")]');

        $this->assertSame(1, $nav->length, 'o chip filtrado tambem pagina');

        foreach ($this->dom(self::$html['list-page2-paid'])->query('//nav[contains(@class,"ml-pagination")]//a') as $link) {
            $this->assertStringContainsString(
                'status=paid',
                $link->getAttribute('href'),
                'o link de paginacao perdeu o filtro de status'
            );
        }
    }

    // =================================================================
    //  Estados vazios
    // =================================================================

    public function testFirstRunEmptyStateOffersProducts(): void
    {
        $dom = $this->dom(self::$html['list-no-orders']);

        $this->assertSame(0, $dom->query("//article[contains(@class,'order-card')]")->length);

        $empty = $dom->query("//div[contains(@class,'orders-empty')]")->item(0);
        $this->assertNotNull($empty, 'sem historico a tela precisa de um estado vazio proprio');

        $this->assertStringContainsString(
            'Você ainda não fez nenhum pedido',
            (string) $empty?->textContent
        );

        $xpath = new DOMXPath($empty->ownerDocument);
        $link  = $xpath->query(".//a[contains(@href,'/pages/products/products.php')]", $empty)->item(0);

        $this->assertNotNull($link, 'o estado vazio precisa oferecer onde comprar');
    }

    public function testNoResultsEmptyStateOffersClearFilters(): void
    {
        $dom = $this->dom(self::$html['list-search-none']);

        $empty = $dom->query("//div[contains(@class,'orders-empty')]")->item(0);
        $this->assertNotNull($empty);
        $this->assertStringContainsString('Nenhum pedido encontrado', (string) $empty?->textContent);

        $xpath = new DOMXPath($empty->ownerDocument);
        $link  = $xpath->query(".//a[contains(@href,'/pages/auth/orders.php')]", $empty)->item(0);

        $this->assertNotNull($link, 'sem resultado, o cliente precisa de um caminho para sair do filtro');

        // O link de limpar tem que apontar para a lista sem parâmetros.
        $this->assertStringEndsWith('/pages/auth/orders.php', (string) $link?->getAttribute('href'));
    }

    // =================================================================
    //  N+1 e integridade da renderização
    // =================================================================

    public function testListDoesNotQueryOncePerCard(): void
    {
        // A fixture conta as SELECTs da própria renderização (ver
        // Com_select no fixture). Uma página com 10 cards que chamasse
        // order_repo_items()/order_repo_history() por card passaria de 30
        // SELECTs; o número tem que ser o mesmo com 2 cards e com 10.
        $fewCards  = (int) ($this->meta('list')['queries'] ?? 0);
        $manyCards = (int) ($this->meta('list-page1')['queries'] ?? 0);

        $this->assertGreaterThan(0, $manyCards, 'a fixture nao contou as queries');
        $this->assertSame(
            $fewCards,
            $manyCards,
            'o custo da consulta subiu junto com a quantidade de cards'
        );
        $this->assertLessThanOrEqual(
            14,
            $manyCards,
            'a lista excedeu o orçamento de consultas (provável N+1)'
        );
    }

    public function testTemplateNeverCallsThePerOrderHelpers(): void
    {
        // Rede de segurança do teste acima: se alguém reintroduzir a
        // chamada por pedido no template, o contador de queries só
        // denunciaria no caminho feliz.
        $source = (string) file_get_contents(__DIR__ . '/../pages/auth/orders.php');

        foreach ([
            'order_repo_items(',
            'order_repo_history(',
            'order_repo_payment(',
            'order_repo_tracking_events(',
        ] as $helper) {
            $this->assertStringNotContainsString(
                $helper,
                $source,
                "{$helper} consulta um pedido por vez: use a variante _batch"
            );
        }
    }

    public function testRenderedPageLeaksNoPhpError(): void
    {
        foreach (self::MODES as $mode) {
            $html = self::$html[$mode];

            foreach (['Warning:', 'Fatal error', 'Notice:', 'Deprecated:', 'Uncaught'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $html,
                    "{$mode}: a pagina vazou um erro do PHP para o HTML"
                );
            }
        }
    }

    public function testOrderListHasNoCancelPolicyCards(): void
    {
        // A regra de cancelamento vive no detalhe do pedido, que é onde
        // o cliente age. Na lista ela seria texto sem ação.
        $this->assertSame(
            0,
            $this->dom(self::$html['list'])
                ->query("//*[contains(@class,'account-cancel-card')]")->length
        );
        $this->assertSame(0, $this->dom(self::$html['list'])->query("//*[@id='secao-cancelamento']")->length);
    }

    // =================================================================
    //  Detalhe
    // =================================================================

    public function testDetailIsASingleDocumentInTheAccountShell(): void
    {
        $dom = $this->dom(self::$html['detail']);

        $this->assertSame(1, $dom->query('/html')->length);
        $this->assertSame(1, $dom->query('//div[contains(@class,"account-shell")]')->length);
        $this->assertSame(1, $dom->query('//main[contains(@class,"account-main")]')->length);
        $this->assertSame('Detalhes do Pedido - Royal Tech', trim((string) $dom->query('//title')->item(0)?->textContent));
    }

    public function testDetailMarksOrdersAsCurrentSidebarItem(): void
    {
        $current = $this->dom(self::$html['detail'])
            ->query('//nav[contains(@class,"side-menu")]//a[contains(@class,"active")]');

        $this->assertSame(1, $current->length);
        $this->assertSame('Meus Pedidos', trim($current->item(0)->textContent));
    }

    public function testDetailBreadcrumbEndsOnTheScreenNotOnTheSection(): void
    {
        $labels = [];
        foreach ($this->dom(self::$html['detail'])->query('//nav[contains(@class,"account-breadcrumb")]//li') as $li) {
            $labels[] = trim(preg_replace('/\s+/', ' ', $li->textContent) ?? '');
        }

        // A sidebar continua em "Meus Pedidos", mas a última etapa do
        // breadcrumb é a tela, não a seção.
        $this->assertSame(['Início', 'Detalhes do Pedido'], $labels);
    }

    public function testDetailShowsOrderNumberItemsAndTotal(): void
    {
        $meta = $this->meta('detail');
        $dom  = $this->dom(self::$html['detail']);

        $this->assertSame(
            'Pedido ' . $meta['temp_number'],
            trim((string) $dom->query('//h1[contains(@class,"account-page-title")]')->item(0)?->textContent)
        );

        $this->assertStringContainsString($meta['product_name'], self::$html['detail']);
        $this->assertSame(1, $dom->query("//*[@id='secao-detalhe']")->length);
        $this->assertStringContainsString('R$ 1.234,56', self::$html['detail']);
    }

    public function testDetailHasBackDownloadAndResendActions(): void
    {
        $dom = $this->dom(self::$html['detail']);

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
        $dom = $this->dom(self::$html['detail']);

        $this->assertSame(
            1,
            $dom->query("//form//input[@name='action'][@value='cancel']")->length,
            'pedido pago ainda permite cancelar e deve oferecer o form'
        );

        $this->assertGreaterThanOrEqual(
            1,
            $dom->query("//*[contains(@class,'account-cancel-rule')]")->length,
            'faltou a frase discreta de regra de cancelamento'
        );
        $this->assertSame(
            0,
            $dom->query("//*[contains(@class,'account-cancel-card')]")->length,
            'a tela de detalhe nao pode ter card de politica de cancelamento'
        );
    }
}