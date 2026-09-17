<?php
// =============================================================================
// Pagamento simulado realista — estados, expiração e retry.
//
// Estados de pagamento (payment_status):
//   pending    → "Aguardando pagamento" (janela de expiração contando)
//   processing → "Processando pagamento..." (transição simulada)
//   paid       → pago (payment_status='paid' + status='paid')
//   failed     → erro no pagamento (estoque devolvido; permite retry)
//   expired    → expirado (sem pagamento dentro do prazo; estoque devolvido)
//   refunded   → estorno (administrativo)
// =============================================================================

require_once __DIR__ . '/pix_functions.php';
require_once __DIR__ . '/order_history_functions.php';

function orderGetById($pdo, $orderId) {
    $stmt = $pdo->prepare('SELECT * FROM e5_orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $orderId]);
    return $stmt->fetch() ?: null;
}

function orderGetItems($pdo, $orderId) {
    $stmt = $pdo->prepare(
        'SELECT oi.*, p.name AS name, p.stock, p.price AS current_price,
               (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
           FROM e5_order_items oi
           INNER JOIN e5_products p ON p.id = oi.product_id
          WHERE oi.order_id = :oid'
    );
    $stmt->execute([':oid' => (int) $orderId]);
    return $stmt->fetchAll() ?: [];
}

// Gera os dados de pagamento simulados e a data de expiração da cobrança.
// Retorna ['info' => array, 'expires_at' => string|null (Y-m-d H:i:s)].
function generatePaymentDetails(string $method, float $total): array {
    $info = ['method' => $method];
    $expiresAt = null;

    if ($method === 'pix') {
        // Chave Pix configurada no painel; se vazia, usa o CNPJ da loja (apenas dígitos).
        $pixKey = trim((string) store_config('pix_key'));
        if ($pixKey === '') {
            $pixKey = preg_replace('/\D/', '', (string) store_config('store_cnpj'));
        }
        $city = 'Sao Paulo';
        if (preg_match('/-\s*([^,]+)/', (string) store_config('store_address'), $m)) {
            $city = trim($m[1]);
        }
        $info = [
            'method' => 'Pix',
            'instructions' => 'Escaneie o QR Code abaixo ou copie o código Pix para pagamento.',
            'pix_code' => pixBuildBrCode([
                'key' => $pixKey,
                'name' => (string) store_config('store_name'),
                'city' => $city,
                'amount' => $total,
                'txid' => 'RT' . strtoupper(bin2hex(random_bytes(5))),
            ]),
        ];
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    } elseif ($method === 'boleto') {
        $info = [
            'method' => 'Boleto',
            'instructions' => 'Pague o boleto em qualquer banco, casa lotérica ou app até o vencimento.',
            'boleto_number' => '34191.79001 01043.510047 91020.150008 ' . random_int(100000000, 999999999) . ' ' . random_int(1, 9),
        ];
        $expiresAt = date('Y-m-d H:i:s', strtotime('+3 days'));
    }

    if ($expiresAt !== null) {
        // Data legível exibida na tela de sucesso do checkout.
        $info['expires'] = date('d/m/Y H:i', strtotime($expiresAt));
    }

    return ['info' => $info, 'expires_at' => $expiresAt];
}

// Reserva (decrementa) o estoque dos itens do pedido.
function orderReserveStock($pdo, $orderId) {
    foreach (orderGetItems($pdo, $orderId) as $item) {
        $ok = decrementStock($pdo, (int) $item['product_id'], (int) $item['quantity']);
        if ($ok <= 0) {
            throw new RuntimeException('Estoque insuficiente para "' . ($item['name'] ?? 'produto') . '".');
        }
    }
}

// Devolve ao estoque os itens do pedido (chamado quando o pagamento não vem).
// Idempotente via coluna stock_restored — nunca devolve duas vezes o mesmo pedido.
function orderRestoreStock($pdo, $orderId) {
    $order = orderGetById($pdo, $orderId);
    if (!$order) return false;
    if (isset($order['stock_restored']) && (int) $order['stock_restored'] === 1) {
        return false;
    }
    foreach (orderGetItems($pdo, $orderId) as $item) {
        $pdo->prepare('UPDATE e5_products SET stock = stock + :qty WHERE id = :pid')
            ->execute([':qty' => (int) $item['quantity'], ':pid' => (int) $item['product_id']]);
    }
    $pdo->prepare('UPDATE e5_orders SET stock_restored = 1 WHERE id = :id')
        ->execute([':id' => (int) $orderId]);
    return true;
}

// Marca o pedido como pago (pagamento aprovado).
function orderMarkPaid($pdo, $orderId, string $changedBy = 'Sistema'): bool {
    $stmt = $pdo->prepare(
        "UPDATE e5_orders
            SET payment_status = 'paid', status = 'paid', payment_expires_at = NULL, payment_details = NULL
          WHERE id = :id AND payment_status <> 'paid'"
    );
    $stmt->execute([':id' => (int) $orderId]);
    if ($stmt->rowCount() > 0) {
        orderHistoryAdd($pdo, (int) $orderId, 'paid', 'paid', 'Pagamento confirmado.', $changedBy);
        return true;
    }
    return false;
}

// Simula falha de pagamento (devolve o estoque reservado). Idempotente.
function orderMarkFailed($pdo, $orderId, string $changedBy = 'Sistema'): bool {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE e5_orders SET payment_status = 'failed'
              WHERE id = :id AND status = 'pending' AND payment_status IN ('pending','processing')"
        );
        $stmt->execute([':id' => (int) $orderId]);
        if ($stmt->rowCount() > 0) {
            orderRestoreStock($pdo, $orderId);
            orderHistoryAdd($pdo, (int) $orderId, 'pending', 'failed', 'Pagamento não aprovado. Estoque devolvido.', $changedBy);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}

// Marca como expirado (pagamento não realizado dentro do prazo). Idempotente.
function orderMarkExpired($pdo, $orderId, string $changedBy = 'Sistema'): bool {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE e5_orders SET payment_status = 'expired', payment_expires_at = NULL
              WHERE id = :id AND status = 'pending' AND payment_status IN ('pending','processing')"
        );
        $stmt->execute([':id' => (int) $orderId]);
        if ($stmt->rowCount() > 0) {
            orderRestoreStock($pdo, $orderId);
            orderHistoryAdd($pdo, (int) $orderId, 'pending', 'expired', 'Pagamento expirado dentro do prazo. Estoque devolvido.', $changedBy);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}

// Automaticamente marca como expirado se a janela de pagamento venceu.
function orderAutoExpire($pdo, $orderId, string $changedBy = 'Sistema') {
    $order = orderGetById($pdo, $orderId);
    if (!$order) return false;
    if ($order['status'] !== 'pending') return false;
    if (!in_array($order['payment_status'], ['pending', 'processing'], true)) return false;
    $expires = $order['payment_expires_at'];
    if (!$expires) return false;
    if (strtotime($expires) > time()) return false;
    return orderMarkExpired($pdo, $orderId, $changedBy);
}

// Reativa um pedido com pagamento falho/expirado: nova cobrança, novo prazo e
// re-reserva de estoque. Retorna [bool, dado|mensagem].
function orderRetryPayment($pdo, $orderId, string $method, float $total, string $changedBy = 'Sistema'): array {
    $order = orderGetById($pdo, $orderId);
    if (!$order || $order['status'] !== 'pending') {
        return [false, 'Este pedido não pode ser retentado.'];
    }
    if (!in_array($order['payment_status'], ['failed', 'expired'], true)) {
        return [false, 'Pagamento já está aguardando confirmação.'];
    }

    try {
        $gen = generatePaymentDetails($method, $total);
    } catch (Throwable $e) {
        return [false, 'Não foi possível gerar a cobrança. Verifique a configuração de pagamento.'];
    }

    $pdo->beginTransaction();
    try {
        orderReserveStock($pdo, $orderId);
        $stmt = $pdo->prepare(
            "UPDATE e5_orders
                SET payment_status = 'pending', payment_details = :det, payment_expires_at = :exp, stock_restored = 0
              WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([
            ':det' => json_encode($gen['info'], JSON_UNESCAPED_UNICODE),
            ':exp' => $gen['expires_at'],
            ':id' => (int) $orderId,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return [false, 'Estoque insuficiente para processar o pagamento. ' . $e->getMessage()];
    }

    orderHistoryAdd($pdo, (int) $orderId, 'pending', 'pending', 'Nova cobrança gerada.', $changedBy);
    return [true, $gen];
}

// Cancelamento pelo cliente (só de pedidos ainda não pagos).
function orderCancelCustomer($pdo, $orderId, string $changedBy = 'Cliente'): bool {
    $order = orderGetById($pdo, $orderId);
    if (!$order || $order['status'] !== 'pending') return false;

    $stmt = $pdo->prepare(
        "UPDATE e5_orders SET status = 'canceled', payment_expires_at = NULL, payment_details = NULL
          WHERE id = :id AND status = 'pending'"
    );
    $stmt->execute([':id' => (int) $orderId]);
    if ($stmt->rowCount() > 0) {
        orderRestoreStock($pdo, $orderId);
        orderHistoryAdd($pdo, (int) $orderId, 'canceled', (string) $order['payment_status'], 'Pedido cancelado pelo cliente.', $changedBy);
    }
    return true;
}

// Gera um código de rastreio simulado no padrão dos Correios (BR + 9 dígitos + BR).
function generateTrackingCode(float|int $orderId): string {
    $mid = str_pad((string) ((int) $orderId), 7, '0', STR_PAD_LEFT);
    return 'BR' . $mid . random_int(100, 999) . 'BR';
}

// Cancelamento pelo administrador. Restaura estoque (idempotente) e, se o
// pagamento já foi confirmado, registra estorno (payment_status = 'refunded').
function orderCancelAdmin($pdo, $orderId, string $adminName = '', string $reason = ''): array {
    $order = orderGetById($pdo, $orderId);
    if (!$order) return ['ok' => false, 'message' => 'Pedido não encontrado.'];
    if ($order['status'] === 'canceled') return ['ok' => false, 'message' => 'Pedido já está cancelado.'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE e5_orders
                SET status = 'canceled',
                    payment_status = CASE WHEN payment_status = 'paid' THEN 'refunded' ELSE payment_status END,
                    refund_reason = :reason, refunded_at = NOW(), refunded_by = :admin,
                    payment_expires_at = NULL, payment_details = NULL
              WHERE id = :id"
        );
        $stmt->execute([
            ':reason' => $reason !== '' ? $reason : null,
            ':admin' => $adminName,
            ':id' => (int) $orderId,
        ]);
        orderRestoreStock($pdo, $orderId);
        $newPayStatus = $order['payment_status'] === 'paid' ? 'refunded' : (string) $order['payment_status'];
        orderHistoryAdd($pdo, (int) $orderId, 'canceled', $newPayStatus, 'Pedido cancelado pelo administrador.' . ($reason !== '' ? ' Motivo: ' . $reason : ''), $adminName);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'message' => 'Falha ao cancelar: ' . $e->getMessage()];
    }
    return ['ok' => true, 'message' => 'Pedido cancelado' . ($order['payment_status'] === 'paid' ? ' e estornado' : '') . '.'];
}

// Estorno administrativo de pagamento já confirmado. Restaura estoque
// (idempotente) e marca payment_status = 'refunded' mantendo o status do pedido.
function orderRefundAdmin($pdo, $orderId, string $adminName = '', string $reason = ''): array {
    $order = orderGetById($pdo, $orderId);
    if (!$order) return ['ok' => false, 'message' => 'Pedido não encontrado.'];
    if ($order['payment_status'] !== 'paid') {
        return ['ok' => false, 'message' => 'Só é possível estornar pedidos com pagamento confirmado.'];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE e5_orders
                SET payment_status = 'refunded', refund_reason = :reason, refunded_at = NOW(), refunded_by = :admin
              WHERE id = :id AND payment_status = 'paid'"
        );
        $stmt->execute([
            ':reason' => $reason !== '' ? $reason : null,
            ':admin' => $adminName,
            ':id' => (int) $orderId,
        ]);
        orderRestoreStock($pdo, $orderId);
        orderHistoryAdd($pdo, (int) $orderId, (string) $order['status'], 'refunded', 'Pagamento estornado pelo administrador.' . ($reason !== '' ? ' Motivo: ' . $reason : ''), $adminName);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'message' => 'Falha ao estornar: ' . $e->getMessage()];
    }
    return ['ok' => true, 'message' => 'Pagamento estornado.'];
}