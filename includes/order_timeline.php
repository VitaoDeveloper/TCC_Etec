<?php
// =============================================================================
// Timeline de progresso do pedido — estilo Mercado Livre / Shopee.
//
// Etapas:
//   0 → Pedido Realizado
//   1 → Pagamento Confirmado
//   2 → Em Preparação
//   3 → Enviado / Em Trânsito
//   4 → Entregue
//
// Estado por etapa: done (verde) · current (dourado) · warn (vermelho) · future (cinza).
// API:
//   orderTimelineSteps()         → [ ['label' =>, 'icon' =>], ... ]
//   orderTimelineState($order)   → ['steps'=>, 'state'=>, 'current'=>, 'mode'=>, 'note'=>?, 'noteClass'=>?, 'fill'=>%, 'compactLabel'=>]
//   renderOrderTimeline($order, $compact = false) → HTML
// =============================================================================

function orderTimelineSteps(): array
{
    return [
        ['label' => 'Pedido Realizado',   'icon' => 'fas fa-clipboard-check'],
        ['label' => 'Pagamento Confirmado','icon' => 'fas fa-credit-card'],
        ['label' => 'Em Preparação',       'icon' => 'fas fa-box-open'],
        ['label' => 'Enviado / Em Trânsito','icon' => 'fas fa-shipping-fast'],
        ['label' => 'Entregue',            'icon' => 'fas fa-home'],
    ];
}

function orderTimelineState(array $order): array
{
    $steps  = orderTimelineSteps();
    $status = (string) ($order['status'] ?? 'pending');
    $pay    = (string) ($order['payment_status'] ?? 'pending');
    $payFail = in_array($pay, ['failed', 'expired'], true);

    // Pedido cancelado → só a etapa de realização concluída, resto cinza.
    if ($status === 'canceled') {
        return [
            'steps'        => $steps,
            'state'        => ['done', 'future', 'future', 'future', 'future'],
            'current'      => -1,
            'mode'         => 'canceled',
            'note'         => 'Pedido cancelado',
            'noteClass'    => 'warn',
            'fill'         => 0,
            'compactLabel' => 'Pedido cancelado',
        ];
    }

    $current = match ($status) {
        'delivered' => 4,
        'shipped'   => 3,
        'paid'      => 2,
        default     => 1, // pending → parado no pagamento
    };

    $state = [];
    for ($i = 0; $i < 5; $i++) {
        if ($i < $current) {
            $state[] = 'done';
        } elseif ($i === $current) {
            $state[] = ($status === 'pending' && $payFail) ? 'warn' : 'current';
        } else {
            $state[] = 'future';
        }
    }

    $note        = null;
    $noteClass   = null;
    if ($status === 'pending') {
        if ($pay === 'processing') {
            $note      = 'Processando pagamento, aguarde a confirmação do banco.';
            $noteClass = 'info';
        } elseif ($pay === 'failed') {
            $note      = 'Pagamento falhou. Clique em "Pagar agora" para tentar novamente.';
            $noteClass = 'warn';
        } elseif ($pay === 'expired') {
            $note      = 'Pagamento expirado. Refaça o pagamento para continuar o pedido.';
            $noteClass = 'warn';
        } else {
            $note      = 'Aguardando pagamento — o pedido avança somente após a confirmação.';
            $noteClass = 'wait';
        }
    } elseif ($status === 'delivered') {
        $note      = 'Pedido entregue. Obrigado pela compra!';
        $noteClass = 'done';
    }

    $compactLabel = match ($status) {
        'delivered' => 'Entregue',
        'shipped'   => 'Em trânsito',
        'paid'      => 'Em preparação',
        default     => $note ?: 'Aguardando pagamento',
    };

    // Percentual da linha preenchida (bolinhas em 0/25/50/75/100%).
    $fill = $current > 0 ? round($current / 4 * 100) : 0;

    return [
        'steps'        => $steps,
        'state'        => $state,
        'current'      => $current,
        'mode'         => ($status === 'pending') ? 'wait' : $status,
        'note'         => $note,
        'noteClass'    => $noteClass,
        'fill'         => $fill,
        'compactLabel' => $compactLabel,
    ];
}

function renderOrderTimeline(array $order, bool $compact = false): string
{
    $t         = orderTimelineState($order);
    $stateList = $t['state'];
    $fillPct   = (int) $t['fill'];
    $hasWarn   = in_array('warn', $stateList, true);

    if ($t['mode'] === 'canceled') {
        $fillBg = 'linear-gradient(90deg, #5a5a5a, #8a8a8a)';
    } elseif ($hasWarn) {
        $fillBg = 'linear-gradient(90deg, #ff6b5e, #ff5c5c)';
    } else {
        $fillBg = 'linear-gradient(90deg, #e0a91f, #d4af37)';
    }

    // ---------------- Versão compacta ----------------
    if ($compact) {
        $dots = '';
        foreach ($stateList as $st) {
            $cls = $st === 'done' ? 'done' : ($st === 'current' || $st === 'warn' ? 'current ' . ($st === 'warn' ? 'warn' : '') : '');
            $dots .= '<span class="ac-tl-compact-dot ' . $cls . '"></span>';
        }
        $noteClass = $t['noteClass'] ?? 'default';

        return '<div class="ac-tl-compact">'
            . '<div class="ac-tl-compact-track">'
            . '<span class="ac-tl-compact-fill" style="width:' . $fillPct . '%; background:' . $fillBg . ';"></span>'
            . '<div class="ac-tl-compact-dots">' . $dots . '</div>'
            . '</div>'
            . '<span class="ac-tl-compact-label ac-tl-compact-label-' . $noteClass . '">' . htmlspecialchars($t['compactLabel'], ENT_QUOTES, 'UTF-8') . '</span>'
            . '</div>';
    }

    // ---------------- Versão completa ----------------
    $html = '<div class="ac-timeline">';

    if ($t['note']) {
        $icon = $hasWarn ? 'fa-exclamation-triangle' : ($t['noteClass'] === 'done' ? 'fa-check-circle' : 'fa-hourglass-half');
        $html .= '<div class="ac-tl-note ac-tl-note-' . ($t['noteClass'] ?? 'default') . '"><i class="fas ' . $icon . '"></i> '
            . htmlspecialchars($t['note'], ENT_QUOTES, 'UTF-8') . '</div>';
    }

    $html .= '<div class="ac-timeline-track"><span class="ac-timeline-fill" style="width:' . $fillPct . '%; background:' . $fillBg . ';"></span></div>';

    $html .= '<div class="ac-timeline-steps">';
    foreach ($t['steps'] as $i => $step) {
        $cls = $stateList[$i] ?? 'future';
        $html .= '<div class="ac-tl-step ac-tl-' . $cls . '">'
            . '<div class="ac-tl-dot"><i class="' . $step['icon'] . '"></i></div>'
            . '<div class="ac-tl-label">' . htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div>';
    }
    $html .= '</div>';

    return $html . '</div>';
}