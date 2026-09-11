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
        $info = [
            'method' => 'Pix',
            'instructions' => 'Escaneie o QR Code abaixo ou copie o código Pix para pagamento.',
            'pix_code' => '00020126580014BR.GOV.BCB.PIX0136' . bin2hex(random_bytes(20))
                . '5204000053039865406' . number_format($total, 2, '', '')
                . '5802BR5913Royal Tech LTDA6009SAO PAULO62070503***6304'
                . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4)),
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
function orderRestoreStock($pdo, $orderId) {
    foreach (orderGetItems($pdo, $orderId) as $item) {
        $pdo->prepare('UPDATE e5_products SET stock = stock + :qty WHERE id = :pid')
            ->execute([':qty' => (int) $item['quantity'], ':pid' => (int) $item['product_id']]);
    }
}

// Marca o pedido como pago (pagamento aprovado).
function orderMarkPaid($pdo, $orderId): bool {
    $stmt = $pdo->prepare(
        "UPDATE e5_orders
            SET payment_status = 'paid', status = 'paid', payment_expires_at = NULL, payment_details = NULL
          WHERE id = :id AND payment_status <> 'paid'"
    );
    $stmt->execute([':id' => (int) $orderId]);
    return $stmt->rowCount() > 0;
}

// Simula falha de pagamento (devolve o estoque reservado). Idempotente.
function orderMarkFailed($pdo, $orderId): bool {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE e5_orders SET payment_status = 'failed'
              WHERE id = :id AND status = 'pending' AND payment_status IN ('pending','processing')"
        );
        $stmt->execute([':id' => (int) $orderId]);
        if ($stmt->rowCount() > 0) {
            orderRestoreStock($pdo, $orderId);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}

// Marca como expirado (pagamento não realizado dentro do prazo). Idempotente.
function orderMarkExpired($pdo, $orderId): bool {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE e5_orders SET payment_status = 'expired', payment_expires_at = NULL
              WHERE id = :id AND status = 'pending' AND payment_status IN ('pending','processing')"
        );
        $stmt->execute([':id' => (int) $orderId]);
        if ($stmt->rowCount() > 0) {
            orderRestoreStock($pdo, $orderId);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}

// Automaticamente marca como expirado se a janela de pagamento venceu.
function orderAutoExpire($pdo, $orderId) {
    $order = orderGetById($pdo, $orderId);
    if (!$order) return false;
    if ($order['status'] !== 'pending') return false;
    if (!in_array($order['payment_status'], ['pending', 'processing'], true)) return false;
    $expires = $order['payment_expires_at'];
    if (!$expires) return false;
    if (strtotime($expires) > time()) return false;
    return orderMarkExpired($pdo, $orderId);
}

// Reativa um pedido com pagamento falho/expirado: nova cobrança, novo prazo e
// re-reserva de estoque. Retorna [bool, dado|mensagem].
function orderRetryPayment($pdo, $orderId, string $method, float $total): array {
    $order = orderGetById($pdo, $orderId);
    if (!$order || $order['status'] !== 'pending') {
        return [false, 'Este pedido não pode ser retentado.'];
    }
    if (!in_array($order['payment_status'], ['failed', 'expired'], true)) {
        return [false, 'Pagamento já está aguardando confirmação.'];
    }

    $gen = generatePaymentDetails($method, $total);

    $pdo->beginTransaction();
    try {
        orderReserveStock($pdo, $orderId);
        $stmt = $pdo->prepare(
            "UPDATE e5_orders
                SET payment_status = 'pending', payment_details = :det, payment_expires_at = :exp
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

    return [true, $gen];
}

// Cancelamento pelo cliente (só de pedidos ainda não pagos).
function orderCancelCustomer($pdo, $orderId): bool {
    $order = orderGetById($pdo, $orderId);
    if (!$order || $order['status'] !== 'pending') return false;

    $restoreStock = in_array($order['payment_status'], ['pending', 'processing'], true);

    $stmt = $pdo->prepare(
        "UPDATE e5_orders SET status = 'canceled', payment_expires_at = NULL, payment_details = NULL
          WHERE id = :id AND status = 'pending'"
    );
    $stmt->execute([':id' => (int) $orderId]);
    if ($stmt->rowCount() > 0 && $restoreStock) {
        orderRestoreStock($pdo, $orderId);
    }
    return true;
}