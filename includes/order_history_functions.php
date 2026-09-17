<?php
// =============================================================================
// Histórico de status do pedido (tabela e5_order_status_history).
//
// Cada transição relevante (criação, pagamento, envio, cancelamento, estorno,
// nota fiscal) é registrada aqui para montar a linha do tempo auditável exibida
// no detalhe do pedido (cliente e admin).
// =============================================================================

// Registra um evento no histórico. Nunca lança exceção: falha no log não pode
// derrubar o fluxo principal do pedido.
function orderHistoryAdd($pdo, int $orderId, string $status, ?string $paymentStatus = null, ?string $note = null, string $changedBy = 'Sistema'): bool
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO e5_order_status_history (order_id, status, payment_status, note, changed_by)
             VALUES (:oid, :st, :ps, :note, :by)'
        );
        return $stmt->execute([
            ':oid'  => $orderId,
            ':st'   => $status,
            ':ps'   => $paymentStatus,
            ':note' => $note !== null ? mb_substr($note, 0, 255) : null,
            ':by'   => mb_substr($changedBy !== '' ? $changedBy : 'Sistema', 0, 80),
        ]);
    } catch (Throwable $e) {
        error_log('orderHistoryAdd error: ' . $e->getMessage());
        return false;
    }
}

// Eventos do pedido em ordem cronológica.
function orderHistoryGet($pdo, int $orderId): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT status, payment_status, note, changed_by, created_at
               FROM e5_order_status_history
              WHERE order_id = :oid
           ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([':oid' => $orderId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('orderHistoryGet error: ' . $e->getMessage());
        return [];
    }
}

// Se o pedido é antigo e não tem histórico, sintetiza o evento inicial a partir
// da data de criação, para a linha do tempo não ficar vazia.
function orderHistoryWithFallback($pdo, array $order): array
{
    $history = orderHistoryGet($pdo, (int) $order['id']);
    if ($history) {
        return $history;
    }

    $status = (string) ($order['status'] ?? 'pending');
    $pay    = (string) ($order['payment_status'] ?? 'pending');
    $note   = $status === 'paid' ? 'Pedido realizado e pagamento confirmado.' : 'Pedido realizado.';

    return [[
        'status'         => $status === 'canceled' ? 'pending' : $status,
        'payment_status' => $pay,
        'note'           => $note,
        'changed_by'     => 'Sistema',
        'created_at'     => $order['created_at'] ?? date('Y-m-d H:i:s'),
    ]];
}

// Rótulos legíveis dos status usados na linha do tempo.
function orderHistoryStatusLabel(string $status): string
{
    return [
        'pending'   => 'Pedido realizado',
        'paid'      => 'Pagamento confirmado',
        'shipped'   => 'Pedido enviado',
        'delivered' => 'Pedido entregue',
        'canceled'  => 'Pedido cancelado',
    ][$status] ?? ucfirst($status);
}

function orderHistoryPaymentLabel(?string $status): ?string
{
    if ($status === null || $status === '') {
        return null;
    }
    return [
        'pending'    => 'Aguardando pagamento',
        'processing' => 'Processando',
        'paid'       => 'Pago',
        'failed'     => 'Pagamento falhou',
        'expired'    => 'Pagamento expirado',
        'refunded'   => 'Estornado',
    ][$status] ?? ucfirst($status);
}

// Renderiza a linha do tempo auditável. HTML autocontido (estilos inline) para
// funcionar tanto no tema do cliente quanto no painel administrativo.
function orderHistoryRender(array $history): string
{
    if (!$history) {
        return '';
    }

    $html = '<div style="position:relative; padding-left:6px;">';
    $last = count($history) - 1;

    foreach ($history as $i => $event) {
        $status  = (string) ($event['status'] ?? 'pending');
        $pay     = orderHistoryPaymentLabel($event['payment_status'] ?? null);
        $note    = trim((string) ($event['note'] ?? ''));
        $by      = trim((string) ($event['changed_by'] ?? ''));
        $when    = !empty($event['created_at']) ? date('d/m/Y H:i', strtotime($event['created_at'])) : '';
        $isLast  = $i === $last;

        $isCancel = $status === 'canceled';
        $isPaid   = $status === 'paid';
        $color    = $isCancel ? '#ff6b5e' : ($isPaid ? '#37d67a' : '#d4af37');
        $icon     = $isCancel ? 'fa-times-circle' : ($isPaid ? 'fa-check-circle' : 'fa-circle');

        $html .= '<div style="position:relative; padding:0 0 20px 30px;' . ($isLast ? 'padding-bottom:0;' : '') . '">';
        if (!$isLast) {
            $html .= '<span style="position:absolute; left:9px; top:20px; bottom:-2px; width:2px; background:rgba(255,255,255,0.12);"></span>';
        }
        $html .= '<span style="position:absolute; left:0; top:1px; width:20px; height:20px; border-radius:50%; display:grid; place-items:center; font-size:0.65rem; color:#151515; background:' . $color . ';"><i class="fas ' . $icon . '"></i></span>';
        $html .= '<div style="font-weight:700; font-size:0.92rem; color:#f0f0f0;">' . htmlspecialchars(orderHistoryStatusLabel($status), ENT_QUOTES, 'UTF-8');
        if ($pay !== null) {
            $html .= ' <span style="font-weight:600; font-size:0.72rem; color:' . $color . '; border:1px solid ' . $color . '55; border-radius:100px; padding:1px 8px; margin-left:4px;">' . htmlspecialchars($pay, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $html .= '</div>';
        $html .= '<div style="font-size:0.78rem; color:#8a8a8a; margin-top:2px;">' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . ($by !== '' ? ' &middot; ' . htmlspecialchars($by, ENT_QUOTES, 'UTF-8') : '') . '</div>';
        if ($note !== '') {
            $html .= '<div style="font-size:0.82rem; color:#b6b6b6; margin-top:4px; line-height:1.5;">' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $html .= '</div>';
    }

    return $html . '</div>';
}
