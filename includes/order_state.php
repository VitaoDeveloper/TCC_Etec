<?php

declare(strict_types=1);

require_once __DIR__ . '/validators.php';

/**
 * Maquina de estados do pedido + do pagamento.
 *
 * Duas maquinas acopladas, com uma regra de seguranca entre elas:
 *
 *   REGRA: um pedido cancelado NUNCA exibe pagamento "Aguardando
 *   pagamento". No momento em que o pedido entra em 'canceled', o
 *   pagamento pendente vira 'canceled' (ou 'expired', se o motivo foi
 *   expiracao) na MESMA transacao. Sem isso a tela mostraria
 *   "Pedido cancelado" ao lado de "Aguardando pagamento" e o cliente
 *   tentaria pagar um pedido morto.
 *
 * Toda mudanca de status grava uma linha em e5_order_history, e a tela
 * de detalhe le a trilha do banco. O template nunca fixa as etapas.
 */

require_once __DIR__ . '/order_repo.php';

/* Carrega a fila de notificações se ainda não veio pelo autoload
   "files" do composer.json: quem chama este arquivo por require direto
   (testes, pages/auth/order-detail.php, api/account/orders.php) precisa
   das funções de notificação, e include de novo o mesmo arquivo não
   redeclara as funções graças ao guard function_exists. */
if (!function_exists('notification_enqueue_order_event')) {
    require_once __DIR__ . '/notification_functions.php';
}

/** Etapas do "Progresso do pedido", na ordem em que aparecem na tela. */
function order_timeline_steps(): array
{
    return [
        'placed'    => ['label' => 'Pedido Realizado',       'icon' => 'fa-clipboard-check'],
        'paid'      => ['label' => 'Pagamento Confirmado',   'icon' => 'fa-credit-card'],
        'preparing' => ['label' => 'Em Preparação',          'icon' => 'fa-boxes-stacked'],
        'shipped'   => ['label' => 'Enviado / Em Trânsito',  'icon' => 'fa-truck-fast'],
        'delivered' => ['label' => 'Entregue',               'icon' => 'fa-house'],
    ];
}

/**
 * Ordem canonica dos status de pedido. 'canceled' fica fora da linha:
 * e um desvio, nao uma etapa do caminho feliz.
 */
function order_status_sequence(): array
{
    return ['pending', 'paid', 'preparing', 'shipped', 'delivered'];
}

/** Status do pedido -> status da etapa correspondente do progresso. */
function order_status_to_step(string $status): ?string
{
    return [
        'pending'   => 'placed',
        'paid'      => 'paid',
        'preparing' => 'preparing',
        'shipped'   => 'shipped',
        'delivered' => 'delivered',
    ][$status] ?? null;
}

/** Rotulo e cor do pill de status. */
function order_status_meta(string $status): array
{
    return [
        'pending'   => ['label' => 'Pendente',      'tone' => 'warning', 'icon' => 'fa-clock'],
        'paid'      => ['label' => 'Pago',          'tone' => 'success', 'icon' => 'fa-circle-check'],
        'preparing' => ['label' => 'Em preparação', 'tone' => 'info',    'icon' => 'fa-boxes-stacked'],
        'shipped'   => ['label' => 'Enviado',       'tone' => 'purple',  'icon' => 'fa-truck-fast'],
        'delivered' => ['label' => 'Entregue',      'tone' => 'success', 'icon' => 'fa-house'],
        'canceled'  => ['label' => 'Cancelado',     'tone' => 'danger',  'icon' => 'fa-ban'],
    ][$status] ?? ['label' => $status, 'tone' => 'info', 'icon' => 'fa-circle-question'];
}

/**
 * Rotulo do status de pagamento (usado no card "Pagamento").
 *
 * Aceita null de proposito: e um helper de exibicao, e um status
 * inesperado (ou ausente) nao pode derrubar a pagina inteira — o
 * fallback mostra o valor cru em vez de um erro fatal.
 */
function payment_status_meta(?string $status): array
{
    return [
        'pending'    => ['label' => 'Aguardando pagamento', 'tone' => 'warning', 'icon' => 'fa-clock'],
        'processing' => ['label' => 'Processando',          'tone' => 'info',    'icon' => 'fa-arrows-rotate'],
        'paid'       => ['label' => 'Pago',                 'tone' => 'success', 'icon' => 'fa-circle-check'],
        'canceled'   => ['label' => 'Cancelado',            'tone' => 'danger',  'icon' => 'fa-ban'],
        'expired'    => ['label' => 'Expirado',             'tone' => 'danger',  'icon' => 'fa-clock-rotate-left'],
        'refunded'   => ['label' => 'Estornado',            'tone' => 'info',    'icon' => 'fa-rotate-left'],
        'failed'     => ['label' => 'Falhou',               'tone' => 'danger',  'icon' => 'fa-triangle-exclamation'],
    ][$status] ?? ['label' => (string) $status, 'tone' => 'info', 'icon' => 'fa-circle-question'];
}

/** Metodos de pagamento aceitos. */
function payment_method_label(string $method): string
{
    return [
        'pix'     => 'Pix',
        'boleto'  => 'Boleto',
        'cartao'  => 'Cartão de crédito',
        'delivery' => 'Pagamento na entrega',
    ][$method] ?? $method;
}

// =====================================================================
// Transicoes validas
// =====================================================================

/**
 * Mapa de transicoes do PEDIDO. Tudo que nao esta aqui e invalido e
 * order_can_transition() devolve false — nao existe "deixar passar" com
 * um if permissivo espalhado pelo codigo.
 */
function order_allowed_transitions(): array
{
    return [
        'pending'   => ['paid', 'canceled'],
        'paid'      => ['preparing', 'canceled'],
        'preparing' => ['shipped', 'canceled'],
        'shipped'   => ['delivered'],
        'delivered' => [],
        'canceled'  => [],
    ];
}

function order_can_transition(string $from, string $to): bool
{
    return in_array($to, order_allowed_transitions()[$from] ?? [], true);
}

/** Transicoes validas do PAGAMENTO. */
function payment_allowed_transitions(): array
{
    return [
        'pending'  => ['paid', 'canceled', 'expired', 'failed'],
        'paid'     => ['refunded'],
        'canceled' => [],
        'expired'  => [],
        'refunded' => [],
        'failed'   => [],
    ];
}

function payment_can_transition(string $from, string $to): bool
{
    return in_array($to, payment_allowed_transitions()[$from] ?? [], true);
}

/**
 * Coerencia entre pedido e pagamento.
 *
 * Retorna o status de pagamento que o pedido DEVE ter, ou null quando
 * nao ha conflito. Usado como rede de seguranca: se o codigo tentar
 * deixar "pending" num pedido "canceled", esta funcao denuncia.
 */
/**
 * Qual o status de pagamento que um pedido DEVE ter, dado o seu status.
 *
 * E a regra que impede a incoerencia que o cliente ve na tela: um pedido
 * "Em preparacao" exibindo "Aguardando pagamento" (ou um pedido
 * cancelado exibindo "Pago"). O pagamento segue o pedido, nunca o
 * contraio.
 *
 * - pending              => ainda nao foi pago
 * - paid/preparing/
 *   shipped/delivered    => dinheiro confirmado
 * - canceled             => sem pagamento, e sem estorno
 *                           (nada a devolver: nao houve PIX pago)
 *
 * @return string|null null se o status do pedido for desconhecido — nesse
 *                      caso o chamador nao deve gravar nada.
 */
function payment_status_required_for_order(string $orderStatus): ?string
{
    return match ($orderStatus) {
        'pending'              => 'pending',
        'paid',
        'preparing',
        'shipped',
        'delivered'            => 'paid',
        'canceled'             => 'canceled',
        default                => null,
    };
}

// =====================================================================
// Expiração preguiçosa (lazy): aplica ao ler order-detail / orders
// =====================================================================

/**
 * Expira pagamentos Pix pendentes cujo prazo venceu, e cancela o pedido
 * se necessário (invariante: payment_status=expired => order_status=canceled).
 *
 * Idempotente: só escreve se o pagamento ainda estiver pendente/processing
 * e o prazo tiver passado. Usa a mesma lógica do worker.php.
 *
 * @return array{order:array, payment:array|null, expired:bool}
 */
function order_expire_pending_pix_lazy(PDO $pdo, array $order): array
{
    $payStatus = (string) ($order['payment_status'] ?? '');
    $expiresAt = $order['payment_expires_at'] ?? null;
    $orderStatus = (string) $order['status'];

    // Só age se: pagamento pendente/processing, tem prazo, prazo passou
    if ($expiresAt === null
        || !in_array($payStatus, ['pending', 'processing'], true)
        || strtotime((string) $expiresAt) > time()) {
        return ['order' => $order, 'payment' => null, 'expired' => false];
    }

    // Já cancelado: garantir payment_status = canceled (invariante)
    if ($orderStatus === 'canceled') {
        $pdo->prepare("UPDATE e5_orders SET payment_status = 'canceled' WHERE id = :id AND payment_status IN ('pending','processing')")
            ->execute([':id' => (int) $order['id']]);
        $order['payment_status'] = 'canceled';
        return ['order' => $order, 'payment' => null, 'expired' => true];
    }

    // Transação: cancelar pedido + pagamento expirado + histórico + estoque
    try {
        $pdo->beginTransaction();

        // 1. Atualiza pedido para canceled
        $pdo->prepare("UPDATE e5_orders SET status = 'canceled', payment_status = 'expired', updated_at = NOW() WHERE id = :id")
            ->execute([':id' => (int) $order['id']]);

        // 2. Atualiza pagamento para expired (se existir)
        $payment = order_repo_payment($pdo, (int) $order['id']);
        if ($payment !== null) {
            $pdo->prepare("UPDATE e5_payments SET status = 'expired', canceled_at = NOW(), expires_at = NULL WHERE id = :id")
                ->execute([':id' => (int) $payment['id']]);
        }

        // 3. Histórico
        order_repo_add_history(
            $pdo,
            (int) $order['id'],
            'canceled',
            'Pix expirado em ' . date('d/m/Y H:i', strtotime((string) $expiresAt)) . ' (estoque liberado)'
        );

        // 4. Estoque
        order_repo_restock($pdo, (int) $order['id']);

        // 5. Etiqueta SuperFrete se houver
        order_repo_cancel_label($pdo, (int) $order['id']);

        $pdo->commit();

        // Recarrega
        $freshOrder = order_repo_find($pdo, (int) $order['id']);
        $freshPayment = order_repo_payment($pdo, (int) $order['id']);

        return ['order' => $freshOrder, 'payment' => $freshPayment, 'expired' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('order_expire_pending_pix_lazy: ' . $e->getMessage());
        return ['order' => $order, 'payment' => null, 'expired' => false];
    }
}

/**
 * Aplica uma transicao de status do pedido dentro de uma transacao ja
 * aberta, mantendo pagamento e historico coerentes.
 *
 * O chamador e responsavel por beginTransaction/commit: varias
 * transicoes costumam acontecer na mesma unidade (ex.: "marcar como pago"
 * grava pedido + pagamento + historico + e-mail).
 *
 * @return array{ok:bool, error?:string, order?:array, payment?:array}
 */
function order_apply_status(PDO $pdo, int $orderId, string $to, array $opts = []): array
{
    $order = order_repo_find($pdo, $orderId);
    if (!$order) {
        return ['ok' => false, 'error' => 'Pedido não encontrado.'];
    }

    $from = (string) $order['status'];

    if ($from === $to) {
        return ['ok' => true, 'order' => $order, 'payment' => null];
    }

    if (!order_can_transition($from, $to)) {
        return [
            'ok' => false,
            'error' => sprintf(
                'Não é possível mudar o pedido de "%s" para "%s".',
                $from,
                $to
            ),
        ];
    }

    $payment = order_repo_payment($pdo, $orderId);

    // --- pedido + pagamento em conjunto -------------------------------
    $pdo->prepare('UPDATE e5_orders SET status = :status, updated_at = NOW() WHERE id = :id')
        ->execute([':status' => $to, ':id' => $orderId]);

    // Regra critica: o pagamento segue o pedido. Sem esta linha um
    // pedido "Entregue" manteria payment_status 'pending' e a tela
    // mostraria "Aguardando pagamento" numa compra ja paga.
    //
    // Ao cancelar, o desfecho depende do que ja tinha acontecido:
    //   - pagamento ainda pendente -> 'canceled' (nada a devolver)
    //   - pagamento ja confirmado  -> 'refunded' (o dinheiro volta)
    // Marcar 'canceled' num pagamento que ja foi pago seria uma mentira
    // na ficha do cliente e quebraria a conciliacao financeira.
    $fromPay   = $payment !== null ? (string) $payment['status'] : null;
    $targetPay = $to === 'canceled'
        ? ($fromPay === 'paid' ? 'refunded' : 'canceled')
        : payment_status_required_for_order($to);

    if ($targetPay !== null) {
        if ($payment !== null && $fromPay !== $targetPay && payment_can_transition($fromPay, $targetPay)) {
            // Monta o SET conforme o desfecho: cada carimbo de data e
            // incluido so quando faz sentido, para nao gravar
            // '0000-00-00' numa coluna que deve ficar NULL.
            $set    = ['status = :status'];
            $params = [':status' => $targetPay];

            if ($targetPay === 'paid') {
                $set[] = 'paid_at = NOW()';
            }
            if (in_array($targetPay, ['canceled', 'expired', 'refunded'], true)) {
                $set[] = 'canceled_at = NOW()';
                $set[] = 'expires_at = NULL';
            }

            $params[':id'] = (int) $payment['id'];
            $pdo->prepare('UPDATE e5_payments SET ' . implode(', ', $set) . ' WHERE id = :id')
                ->execute($params);
        }

        // Mantem a coluna denormalizada de e5_orders em dia, mesmo
        // quando o pedido nao tem linha em e5_payments.
        $pdo->prepare('UPDATE e5_orders SET payment_status = :ps WHERE id = :id')
            ->execute([':ps' => $targetPay, ':id' => $orderId]);
    }

    // Estorno de estoque: acontece na saida para 'canceled' e apenas
    // se o pedido ainda nao tinha sido enviado (apos o envio a mercadoria
    // saiu e o caminho passa a ser devolucao, nao cancelamento).
    $restocked = false;
    if ($to === 'canceled' && !in_array($from, ['shipped', 'delivered'], true)) {
        order_repo_restock($pdo, $orderId);
        $restocked = true;
    }

    // --- historico ----------------------------------------------------
    order_repo_add_history(
        $pdo,
        $orderId,
        $to,
        trim(($opts['note'] ?? '') . ' ' . ($restocked ? '(estoque liberado)' : ''))
    );

    // --- superfrete: etiqueta gerada antes do envio e cancelada junto -
    $labelCanceled = false;
    if ($to === 'canceled') {
        $labelCanceled = order_repo_cancel_label($pdo, $orderId);
    }

    // --- notificações -------------------------------------------------
    // Enfileira o e-mail/WhatsApp respeitando as preferências do cliente.
    // O template usado vem do evento mapeado abaixo; se não houver evento
    // para o status alvo (ex.: 'preparing'), não enviamos nada para
    // evitar ruído — o histórico já registra a mudança.
    $eventMap = [
        'paid'      => 'payment_confirmed',
        'shipped'   => 'order_shipped',
        'delivered' => 'order_delivered',
        'canceled'  => 'order_canceled',
    ];
    $event = $eventMap[$to] ?? null;
    if ($event !== null) {
        $freshOrder = order_repo_find($pdo, $orderId);
        $ctx = [
            'tracking' => (string) ($freshOrder['tracking_code'] ?? ''),
            'service'  => ($freshOrder['shipping_method'] ?? '') === 'delivery'
                ? 'Entrega própria' : 'SuperFrete',
        ];
        // A função guarda a intenção na fila; quem drena (worker.php, cron)
        // é quem realmente envia. Se o INSERT falhar (ex.: tabela não
        // existe no SQLite dos testes), ignoramos para não derrubar a
        // mudança de status que é a operação principal.
        try {
            notification_enqueue_order_event($pdo, $orderId, $event, $ctx);
        } catch (Throwable $e) {
            error_log('order_apply_status: falha ao enfileirar notificação: ' . $e->getMessage());
        }
    }

    return [
        'ok' => true,
        'order' => order_repo_find($pdo, $orderId),
        'payment' => order_repo_payment($pdo, $orderId),
        'restocked' => $restocked,
        'label_canceled' => $labelCanceled,
    ];
}

// =====================================================================
// Progresso (leitura da trilha)
// =====================================================================

/**
 * Monta o progresso do pedido a partir de e5_order_history.
 *
 * A ordem de exibicao vem de order_timeline_steps(); o que existe no
 * banco marca dourado com data/hora, o que nao existe fica cinza. Um
 * pedido cancelado nunca avanca etapas: o historico nao ganha linhas
 * novas e a faixa vermelha aparece acima da linha do tempo.
 */
function order_progress(PDO $pdo, array $order): array
{
    $history = order_repo_history($pdo, (int) $order['id']);
    $byStatus = [];
    foreach ($history as $h) {
        $byStatus[(string) $h['status']] = $h;
    }

    $isCanceled = (string) $order['status'] === 'canceled';
    $current    = (string) $order['status'];

    // Pedidos criados antes desta tela não têm a etapa inicial gravada.
    // Sem esta reconstituição, a linha de tempo de um pedido antigo
    // mostraria "Pedido realizado" em cinza mesmo com o pedido pago —
    // e é a primeira etapa que o cliente reconhece. Reconstituímos a
    // partir do próprio status do pedido, que é a fonte confiável.
    //
    // O passo corrente vem de order_status_to_step(), e não de
    // array_search na sequência: a primeira etapa da tela chama-se
    // 'placed', enquanto o status no banco é 'pending', então a busca
    // direta nunca encontraria o passo 1.
    $currentStep = order_status_to_step($current);
    $reachedIdx  = $currentStep === null
        ? false
        : array_search($currentStep, array_keys(order_timeline_steps()), true);
    if ($reachedIdx !== false) {
        $reachedIdx = (int) $reachedIdx;
    }

    $stepKeys = array_keys(order_timeline_steps());

    $steps = [];
    foreach ($stepKeys as $pos => $key) {
        $row    = $byStatus[$key] ?? null;
        $passed = $reachedIdx !== false && $pos <= $reachedIdx;

        // A data real do histórico tem prioridade; sem ela, cai para a
        // data do próprio pedido — que é a data correta para "Pedido
        // realizado" e uma aproximação honesta para as demais.
        $at = $row['created_at']
            ?? ($passed ? ($order['created_at'] ?? null) : null);

        $meta = order_timeline_steps()[$key];
        $steps[] = [
            'key'        => $key,
            'label'      => $meta['label'],
            'icon'       => $meta['icon'],
            'done'       => $passed && !$isCanceled,
            'date_label' => ($passed && !$isCanceled && $at)
                ? date('d/m/Y H:i', strtotime((string) $at))
                : null,
        ];
    }

    return [
        'steps'        => $steps,
        'canceled'     => $isCanceled,
        'canceled_at'  => $byStatus['canceled']['created_at'] ?? null,
        'canceled_note' => $byStatus['canceled']['note'] ?? null,
    ];
}

/** O cliente pode cancelar este pedido? (antes do envio) */
function order_can_cancel(array $order): bool
{
    return in_array((string) $order['status'], ['pending', 'paid', 'preparing'], true);
}

/** Botoes condicionais da tela de detalhe. */
function order_actions_available(array $order): array
{
    $status = (string) $order['status'];
    $pay    = (string) ($order['payment_status'] ?? '');

    return [
        'pay'         => $pay === 'pending' && $status === 'pending',
        'cancel'      => order_can_cancel($order),
        'rebuy'       => true,
        'track'       => trim((string) ($order['tracking_code'] ?? '')) !== '',
        'receipt'     => true,
        'resend'      => true,
    ];
}
