<?php

declare(strict_types=1);

namespace TCC\Tests;

use PHPUnit\Framework\TestCase;

// Arquivos procedurais em includes/ (o projeto não os coloca no PSR-4).
require_once __DIR__ . '/../includes/url_helpers.php';
require_once __DIR__ . '/../includes/status_labels.php';

/**
 * Testes dos helpers de URL e de rótulo.
 *
 * Ambos os arquivos existem para impedir um defeito específico:
 *  - url_helpers.php substitui o $base_path = '../../' escrito à mão,
 *    que quebrava assets sempre que uma página mudava de diretório;
 *  - status_labels.php centraliza o PT-BR de status, que aparecia como
 *    "pending" cru na lista, no e-mail e no PDF.
 */
class HelpersTest extends TestCase
{
    // =================================================================
    //  base_url()
    // =================================================================

    /**
     * base_url() guarda o valor em static. Cada cenário precisa de um
     * processo limpo, senão o segundo caso leria o resultado do
     * primeiro. Rodar em subprocesso resolve sem precisar expor
     * um reset só para teste.
     */
    private function baseUrlFor(string $scriptName, ?string $path = null): string
    {
        $code = 'require "includes/url_helpers.php";'
            . ' echo base_url(' . var_export($path, true) . ');';

        $out = shell_exec(
            sprintf(
                '%s -d error_reporting=E_ALL -r %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(
                    '$_SERVER["SCRIPT_NAME"]=' . var_export($scriptName, true)
                    . ';' . $code
                )
            )
        );

        $this->assertIsString($out);
        $this->assertStringNotContainsString('Fatal error', $out, $out);
        $this->assertStringNotContainsString('Warning', $out, $out);

        return $out;
    }

    public function testBaseUrlResolvesAppRootWhenScriptIsAtRoot(): void
    {
        $this->assertSame('/TCC_Etec/', $this->baseUrlFor('/TCC_Etec/index.php'));
    }

    public function testBaseUrlStaysSameFromNestedPage(): void
    {
        // Este é o ponto do arquivo: pages/auth/profile.php está dois
        // níveis abaixo, e mesmo assim precisa da MESMA raiz que o index.
        $this->assertSame(
            '/TCC_Etec/',
            $this->baseUrlFor('/TCC_Etec/pages/auth/profile.php')
        );
    }

    public function testBaseUrlStaysSameFromOtherAreas(): void
    {
        foreach (['products', 'cart'] as $area) {
            $this->assertSame(
                '/TCC_Etec/',
                $this->baseUrlFor("/TCC_Etec/pages/{$area}/index.php"),
                "área {$area} deveria resolver a mesma raiz"
            );
        }
    }

    public function testBaseUrlHandlesAppAtServerRoot(): void
    {
        $this->assertSame('/', $this->baseUrlFor('/index.php'));
    }

    public function testBaseUrlNeverReturnsDoubledSlash(): void
    {
        $this->assertStringNotContainsString(
            '//',
            $this->baseUrlFor('/TCC_Etec/pages/auth/orders.php')
        );
    }

    public function testBaseUrlAppendsPathWithSingleSlash(): void
    {
        $this->assertSame(
            '/TCC_Etec/pages/auth/profile.php',
            $this->baseUrlFor('/TCC_Etec/index.php', 'pages/auth/profile.php')
        );
    }

    public function testBaseUrlTrimsLeadingSlashFromPath(): void
    {
        // Chamar com "/assets/..." é o erro mais fácil de cometer;
        // o resultado seria "//assets" e o navegador pediria a porta 80.
        $this->assertSame(
            '/TCC_Etec/assets/css/account.css',
            $this->baseUrlFor('/TCC_Etec/index.php', '/assets/css/account.css')
        );
    }

    public function testAssetUrlCarriesCacheBuster(): void
    {
        $out = shell_exec(
            sprintf(
                '%s -r %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(
                    '$_SERVER["SCRIPT_NAME"]="/TCC_Etec/index.php";'
                    . 'require "includes/url_helpers.php";'
                    . 'echo asset_url("assets/js/script.js");'
                )
            )
        );

        $this->assertIsString($out);
        $this->assertStringContainsString('/TCC_Etec/assets/js/script.js', $out);
        $this->assertStringContainsString('?v=' . ASSET_VERSION, $out);
    }

    // =================================================================
    //  e() / query_string()
    // =================================================================

    public function testEEscapesQuotesSoAttributeCannotBreakOut(): void
    {
        // O motivo de ENT_QUOTES: com aspas duplas não escapadas, o
        // resto do nome viraria atributo e o resto do nome viraria tag.
        $out = e('Silva" onload="alert(1)');
        $this->assertStringNotContainsString('"', $out);
        $this->assertStringContainsString('&quot;', $out);
    }

    public function testEEscapesSingleQuotes(): void
    {
        $this->assertStringContainsString('&#039;', e("O'Brien"));
    }

    public function testEHandlesNullAsEmptyString(): void
    {
        $this->assertSame('', e(null));
    }

    public function testEIsIdempotentSafeForAlreadyValidText(): void
    {
        $this->assertSame('Kauã Caetano', e('Kauã Caetano'));
    }

    public function testQueryStringReturnsEmptyForNoParams(): void
    {
        $this->assertSame('', query_string([]));
    }

    public function testQueryStringDropsEmptyValues(): void
    {
        // Sem o filtro, apareceria "?status=&q=" na URL e a página
        // trataria isso como filtro ativo.
        $this->assertSame('?q=notebook', query_string(['status' => '', 'q' => 'notebook']));
    }

    public function testQueryStringEncodesSpacesAsPlus(): void
    {
        $this->assertSame('?q=galaxy+s25', query_string(['q' => 'galaxy s25']));
    }

    public function testUrlWithQueryEscapesAmpersand(): void
    {
        $out = url_with_query('orders.php', ['status' => 'paid', 'q' => 'a"b']);
        $this->assertStringContainsString('status=paid', $out);
        $this->assertStringNotContainsString('a"b', $out);
    }

    // =================================================================
    //  Contrato legado de $statusLabels
    // =================================================================

    /**
     * Lê as variáveis de escopo de arquivo como uma página real faz:
     * num processo novo, em escopo global.
     *
     * Não dá para testar daqui com `global`, porque este arquivo de
     * teste tem namespace e o include acontece no escopo
     * TCC\Tests — as variáveis ficam onde uma página comum não
     * procuraria. O subprocesso é o que reproduz o uso real.
     */
    private function legacyGlobals(): array
    {
        $out = shell_exec(
            sprintf(
                '%s -r %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(
                    'require "includes/status_labels.php";'
                    . 'echo json_encode(["labels" => $statusLabels, "flat" => $statusLabelsFlat]);'
                )
            )
        );

        $this->assertIsString($out);
        $this->assertStringNotContainsString('Fatal error', $out, $out);
        $this->assertStringNotContainsString('Warning', $out, $out);

        $decoded = json_decode($out, true);

        $this->assertIsArray(
            $decoded,
            'as páginas antigas dependem de $statusLabels em escopo global: ' . $out
        );

        return $decoded;
    }

    /**
     * pages/admin/orders.php, pages/admin/order-detail.php,
     * pages/admin/index.php e pages/auth/orders.php leem
     * $statusLabels[$status]['label'] e ['class'] diretamente. Um teste
     * para o formato evita que alguém "limpe" o array e deixe quatro
     * telas com badge vazio — que foi como o 'preparing' sumiu.
     */
    public function testLegacyFlatArrayKeepsStringKeys(): void
    {
        // Regressão do bug do "PENDING" em inglês: array_column
        // reindexa e $statusLabelsFlat['pending'] virava null, então o
        // `?? $o['status']` da lista de pedidos vazava o valor do ENUM.
        $flat = $this->legacyGlobals()['flat'];

        foreach (order_status_sequence() as $status) {
            $this->assertArrayHasKey(
                $status,
                $flat,
                "\$statusLabelsFlat['{$status}'] não existe: a tela cairia no ENUM cru"
            );
            $this->assertIsString($flat[$status]);
        }
    }

    public function testLegacyArrayContractIsIntact(): void
    {
        $legacy = $this->legacyGlobals();

        $this->assertIsArray($legacy['labels']);
        $this->assertIsArray($legacy['flat']);

        foreach (order_status_sequence() as $status) {
            $this->assertArrayHasKey(
                $status,
                $legacy['labels'],
                "'{$status}' ausente de \$statusLabels: as páginas admin cairiam no fallback"
            );
            $this->assertArrayHasKey('label', $legacy['labels'][$status]);
            $this->assertArrayHasKey('class', $legacy['labels'][$status]);
            $this->assertSame(
                $legacy['labels'][$status]['label'],
                $legacy['flat'][$status]
            );
        }
    }

    public function testLegacyClassesAreTheOnesThatExistInAdminCss(): void
    {
        // Se alguém inventar uma classe nova, o badge sai sem cor até
        // existir CSS correspondente — por isso a lista é fechada.
        $allowed = [
            'status-active',
            'status-inactive',
            'status-pending',
            'status-processing',
        ];

        foreach ($this->legacyGlobals()['labels'] as $status => $info) {
            $this->assertContains(
                $info['class'],
                $allowed,
                "'{$status}' usa a classe '{$info['class']}', que não existe em admin.css"
            );
        }
    }

    public function testLegacyAndFunctionLabelsAgree(): void
    {
        // Duas formas de ler o mesmo texto não podem divergir: é o
        // que faria a lista admin e o detalhe do pedido discordarem.
        $legacy = $this->legacyGlobals()['labels'];

        $this->assertNotEmpty($legacy);

        foreach ($legacy as $status => $info) {
            $this->assertSame(
                $info['label'],
                status_label_raw($status),
                "rótulo de '{$status}' diverge entre o array e a função"
            );
            $this->assertSame($info['class'], status_class($status));
        }
    }

    public function testDeliveredLabelIsEntregueNotConcluido(): void
    {
        // "Concluído" descreve a operação; o cliente entende
        // "Entregue". E o estado do ENUM se chama delivered.
        $this->assertSame('Entregue', $this->legacyGlobals()['labels']['delivered']['label']);
    }

    public function testLabelFunctionsDoNotDependOnIncludeScope(): void
    {
        // Regressão: as funções usavam `global $statusLabels`, que
        // só acha a variável quando o include é no escopo global.
        // Este arquivo tem namespace, então `global` não a encontraria
        // e todo rótulo cairia no fallback em maiúsculas.
        $this->assertSame('Pago', status_label_raw('paid'));
        $this->assertSame('Em preparação', status_label_raw('preparing'));
        $this->assertSame('Entregue', status_label_raw('delivered'));
        $this->assertNotSame('PAID', status_label_raw('paid'));
    }

    // =================================================================
    //  Rótulos PT-BR
    // =================================================================

    public function testEveryOrderStatusHasAPtBrLabel(): void
    {
        $labels = status_labels();

        foreach (order_status_sequence() as $status) {
            $this->assertArrayHasKey(
                $status,
                $labels,
                "status de pedido '{$status}' ficou sem rótulo em português"
            );
        }
    }

    public function testEveryPaymentStatusHasAPtBrLabel(): void
    {
        $labels = payment_labels();

        foreach (payment_allowed_transitions() as $from => $targets) {
            $this->assertArrayHasKey(
                $from,
                $labels,
                "status de pagamento '{$from}' ficou sem rótulo em português"
            );
            foreach ($targets as $to) {
                $this->assertArrayHasKey(
                    $to,
                    $labels,
                    "status de pagamento '{$to}' ficou sem rótulo em português"
                );
            }
        }
    }

    public function testNoLabelLeaksTheEnglishEnumValue(): void
    {
        foreach (status_labels() as $status => $label) {
            $this->assertStringNotContainsStringIgnoringCase(
                $status,
                $label,
                "o rótulo de '{$status}' está expondo o valor cru do ENUM"
            );
        }
    }

    public function testStatusLabelIsEscaped(): void
    {
        $this->assertStringNotContainsString('<', status_label('<script>'));
    }

    public function testStatusLabelPluralizesExceptInvariantWords(): void
    {
        // "Pendentes"/"Em preparações" não são palavras: o 's' cego
        // sobre "Em preparação" foi um bug real, por isso a lista é
        // explícita e estes casos ficam fixados.
        $this->assertSame('Pagos', status_label('paid', 2));
        $this->assertSame('Enviados', status_label('shipped', 2));
        $this->assertSame('Entregues', status_label('delivered', 2));
        $this->assertSame('Cancelados', status_label('canceled', 2));
        $this->assertSame('Pendente', status_label('pending', 2));
        $this->assertSame('Em preparação', status_label('preparing', 2));
    }

    public function testPluralFormIsNeverAnUnknownWord(): void
    {
        // Rede de segurança genérica: se alguém voltar a concatenar
        // 's', os plurais inventados aparecem aqui.
        $invented = ['preparações', 'preparacaos', 'Pendencies'];
        foreach (status_labels() as $status => $label) {
            $plural = strtolower(status_label_raw($status, 2));
            foreach ($invented as $bad) {
                $this->assertStringNotContainsString(
                    $bad,
                    $plural,
                    "plural de '{$status}' ficou '{$plural}', que não existe"
                );
            }
        }
    }

    public function testRefundedLabelIsReembolsadoNotEstornado(): void
    {
        // O cliente precisa ler "Reembolsado": é o termo que ele
        // reconhece no extrato do cartão.
        $this->assertSame('Reembolsado', payment_label('refunded'));
    }

    public function testCanceledOrderWithRefundReadsAsReembolsado(): void
    {
        $this->assertSame(
            'Reembolsado',
            payment_situation_label('canceled', 'refunded')
        );
    }

    public function testCanceledOrderWithoutRefundReadsAsCancelado(): void
    {
        $this->assertSame(
            'Cancelado',
            payment_situation_label('canceled', 'canceled')
        );
    }

    public function testCanceledOrderWithoutPaymentRowReadsAsCancelado(): void
    {
        $this->assertSame('Cancelado', payment_situation_label('canceled', null));
    }

    public function testActiveOrderUsesPlainPaymentLabel(): void
    {
        $this->assertSame(
            'Aguardando pagamento',
            payment_situation_label('pending', 'pending')
        );
    }

    public function testPaymentSituationLabelIsEscaped(): void
    {
        $this->assertStringNotContainsString(
            '<',
            payment_situation_label_escaped('canceled', '<b>')
        );
    }

    public function testPaymentMethodKeepsBrandNamesUnchanged(): void
    {
        // "Pixes" e "Boletos" não existem em português.
        $this->assertSame('Pix', payment_method_raw('pix', 1));
        $this->assertSame('Pix', payment_method_raw('pix', 2));
    }

    public function testPaymentMethodPluralizesGenericNames(): void
    {
        // "Cartão de créditos" foi o bug que o teste pegou: pluralizar
        // a última palavra de um rótulo com preposição.
        $this->assertSame('Cartões de crédito', payment_method_raw('cartao', 2));
        $this->assertSame('Cartão de crédito', payment_method_raw('cartao', 1));
    }

    public function testShippingMethodIsEscalated(): void
    {
        $this->assertSame('PAC', shipping_method_escaped('PAC'));
        $this->assertSame('PACs', shipping_method_escaped('PAC', 2));
    }
}
