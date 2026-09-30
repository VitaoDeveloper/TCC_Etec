<?php

declare(strict_types=1);

/**
 * Status de pedido em PT-BR, em um único lugar.
 *
 * Este arquivo atende dois contratos ao mesmo tempo, e isso é
 * proposital:
 *
 *  1. $statusLabels / $statusLabelsFlat — arrays usados por
 *     pages/admin/orders.php, pages/admin/order-detail.php,
 *     pages/admin/index.php e pages/auth/orders.php. Vieram primeiro e
 *     não podem quebrar: as classes CSS (.status-active,
 *     .status-pending, .status-processing, .status-inactive) já existem
 *     em admin.css e não vão ser renomeadas aqui.
 *
 *  2. As funções status_label(), payment_label() etc. — chamadas por
 *     nome, com plural e escape já resolvidos. São o que as telas novas
 *     (perfil, pedidos, detalhe) usam.
 *
 * A duplicação do texto entre os dois formatos é o preço de não quebrar
 * as páginas antigas. A regra de negócio NÃO mora aqui: ela está em
 * order_state.php. Aqui é só o dicionário de texto e de cor.
 *
 * Dois bugs corrigidos ao centralizar:
 *  - 'preparing' não existia no array, então o badge saía vazio;
 *  - 'delivered' aparecia como "Concluído" e o pedido pago cancelado
 *    como "Cancelado", sem indicar que o dinheiro foi devolvido.
 */

require_once __DIR__ . '/order_state.php';

// =========================================================================
//  Contrato legado: arrays lidos diretamente pelas páginas existentes
// =========================================================================

/**
 * Tabela de status: status => [rótulo singular, rótulo plural, classe].
 *
 * É a fonte única de verdade deste arquivo. As funções abaixo leem
 * direto daqui em vez de usar `global $statusLabels`, porque `global`
 * só acha a variável se o include aconteceu no escopo global — e este
 * arquivo também é incluído por arquivos com namespace, onde `global`
 * apontaria para outro lugar e o badge sairia vazio.
 */
function status_label_table(): array
{
    return [
        'pending'   => ['label' => 'Pendente',       'plural' => 'Pendente',      'class' => 'status-pending'],
        'paid'      => ['label' => 'Pago',           'plural' => 'Pagos',         'class' => 'status-active'],
        'preparing' => ['label' => 'Em preparação',  'plural' => 'Em preparação', 'class' => 'status-processing'],
        'shipped'   => ['label' => 'Enviado',        'plural' => 'Enviados',      'class' => 'status-processing'],
        'delivered' => ['label' => 'Entregue',       'plural' => 'Entregues',     'class' => 'status-active'],
        'canceled'  => ['label' => 'Cancelado',      'plural' => 'Cancelados',    'class' => 'status-inactive'],
    ];
}

/**
 * Variables de escopo de arquivo, mantidas para as páginas antigas.
 * Derivadas da tabela acima, nunca editadas à mão.
 */
$statusLabels = status_label_table();

/**
 * Só os rótulos, para quem já escapa por conta própria.
 *
 * Usa array_map e NÃO array_column de propósito: array_column
 * reindexa por posição e descarta as chaves, então
 * $statusLabelsFlat['pending'] voltava null. O consumidor usava
 * `?? $o['status']` e caía no valor cru do ENUM — era o que exibia
 * "PENDING" em inglês na lista de pedidos.
 */
$statusLabelsFlat = array_map(
    static fn(array $info): string => $info['label'],
    $statusLabels
);

// =========================================================================
//  Contrato novo: funções
// =========================================================================

/** Rótulos de status de pedido (ENUM de e5_orders.status). */
function status_labels(): array
{
    return array_map(
        static fn(array $info): string => $info['label'],
        status_label_table()
    );
}

/** Rótulos de status de pagamento (e5_payments.status). */
function payment_labels(): array
{
    return [
        'pending'    => 'Aguardando pagamento',
        'processing' => 'Processando',
        'paid'       => 'Pago',
        'canceled'   => 'Cancelado',
        'expired'    => 'Expirado',
        'refunded'   => 'Reembolsado',
        'failed'     => 'Falhou',
    ];
}

/** Classe CSS do badge de um status de pedido, para o tema da conta. */
function status_class(?string $status): string
{
    $table = status_label_table();

    return $table[(string) $status]['class'] ?? 'status-pending';
}

/**
 * Rótulo de status de pedido já escapado para o HTML.
 *
 * Plurais explícitos em vez de concatenar 's': "Em preparação" + 's'
 * daria "Em preparações", que não é palavra. "Pendente" e
 * "Em preparação" são invariáveis no plural.
 */
function status_label(?string $status, int $plural = 1): string
{
    $table = status_label_table();
    $key   = (string) $status;
    $info  = $table[$key] ?? null;

    if ($info !== null) {
        $label = $plural > 1 ? $info['plural'] : $info['label'];
    } else {
        // Status desconhecido (coluna nova, dado sujo): mostra o que
        // veio, capitalizado, em vez de badge vazio.
        $label = ucfirst(strtolower(str_replace('_', ' ', $key)));
    }

    return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
}

/** Versão sem escape, para e-mail em texto puro e PDF. */
function status_label_raw(?string $status, int $plural = 1): string
{
    return html_entity_decode(status_label($status, $plural), ENT_QUOTES, 'UTF-8');
}

/** Rótulo de status de pagamento já escapado para o HTML. */
function payment_label(?string $status): string
{
    return htmlspecialchars(payment_label_raw($status), ENT_QUOTES, 'UTF-8');
}

function payment_label_raw(?string $status): string
{
    $labels = payment_labels();
    $key    = (string) $status;

    return $labels[$key] ?? ucfirst(strtolower(str_replace('_', ' ', $key)));
}

/**
 * Rótulo da situação do pagamento tal como o cliente deve ler.
 *
 * Um pedido cancelado que já estava pago mostra "Reembolsado", e não
 * "Cancelado": o dinheiro voltou, e dizer só "cancelado" deixaria o
 * cliente sem saber se precisa esperar o estorno. A decisão de qual
 * status o pagamento fica é de order_state.php; aqui é só a palavra.
 */
function payment_situation_label(?string $orderStatus, ?string $paymentStatus): string
{
    $orderStatus   = (string) $orderStatus;
    $paymentStatus = (string) $paymentStatus;

    if ($orderStatus === 'canceled') {
        return $paymentStatus === 'refunded' ? 'Reembolsado' : 'Cancelado';
    }

    return payment_label_raw($paymentStatus);
}

/** Versão escapada de payment_situation_label(). */
function payment_situation_label_escaped(?string $orderStatus, ?string $paymentStatus): string
{
    return htmlspecialchars(
        payment_situation_label($orderStatus, $paymentStatus),
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Rótulo do método de pagamento.
 *
 * Plurais escritos à mão porque concatenar 's' erra em rótulo com
 * preposição: "Cartão de crédito" viraria "Cartão de créditos".
 * "Pix" é nome próprio e não pluraliza.
 */
function payment_method_raw(?string $method, int $plural = 1): string
{
    $singular = [
        'pix'      => 'Pix',
        'boleto'   => 'Boleto',
        'cartao'   => 'Cartão de crédito',
        'delivery' => 'Dinheiro na entrega',
    ];

    $pluralLabels = [
        'pix'      => 'Pix',
        'boleto'   => 'Boletos',
        'cartao'   => 'Cartões de crédito',
        'delivery' => 'Dinheiro na entrega',
    ];

    $key   = (string) $method;
    $table = $plural > 1 ? $pluralLabels : $singular;

    return $table[$key] ?? ucfirst(strtolower(str_replace('_', ' ', $key)));
}

function payment_method_escaped(?string $method, int $plural = 1): string
{
    return htmlspecialchars(payment_method_raw($method, $plural), ENT_QUOTES, 'UTF-8');
}

/** Rótulo do serviço de entrega. */
function shipping_method_raw(?string $method, int $plural = 1): string
{
    $singular = [
        'PAC'    => 'PAC',
        'Sedex'  => 'Sedex',
        'pickup' => 'Retirar na loja',
    ];

    $pluralLabels = [
        'PAC'    => 'PACs',
        'Sedex'  => 'Sedexes',
        'pickup' => 'Retiradas na loja',
    ];

    $key   = (string) $method;
    $table = $plural > 1 ? $pluralLabels : $singular;

    return $table[$key] ?? $key;
}

function shipping_method_escaped(?string $method, int $plural = 1): string
{
    return htmlspecialchars(shipping_method_raw($method, $plural), ENT_QUOTES, 'UTF-8');
}
