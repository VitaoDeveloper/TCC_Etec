<?php

declare(strict_types=1);

/**
 * Pagamento: geração do BR Code Pix, expiração real e cancelamento.
 *
 * Concentrado aqui porque checkout.php (cria o pedido), payment.php (tela de
 * pagamento) e payment_status.php (polling) precisam concordar sobre o que é
 * "pago", "expirado" e "cancelado". Regra em um lugar só, ou o polling mente.
 */

/**
 * CRC16-CCITT, polinômio 0x1021, init 0xFFFF.
 *
 * Exigido pelo padrão EMV do BR Code: o payload é todo concatenado até o
 * campo 63, mais o literal "6304", e o CRC entra nesses 4 hexadecimais.
 * Sem ele, nenhum app de banco lê o QR.
 */
function pix_crc16(string $payload): string
{
    $crc = 0xFFFF;

    for ($i = 0; $i < strlen($payload); $i++) {
        $crc ^= ord($payload[$i]) << 8;
        for ($bit = 0; $bit < 8; $bit++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }

    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/** Campo TLV do EMV: identificador (2) + tamanho (2) + valor. */
function pix_tlv(string $id, string $value): string
{
    return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

/**
 * Monta o BR Code Pix (EMV QRCPS-MPM).
 *
 * Retorna string vazia quando não há chave Pix configurada. Chamar QR Code
 * em payload inventado parece funcionar e não é pago por ninguém: melhor o
 * chamador saber que falta configuração.
 */
function pix_brcode(string $txid, float $amount, string $key, string $merchantName, string $merchantCity): string
{
    $key = trim($key);
    if ($key === '') {
        return '';
    }

    // Limites do padrão, não estética: campo maior que o limite é rejeitado.
    $merchantName = rtrim(mb_substr($merchantName ?: 'LOJA', 0, 25), ' ');
    $merchantCity = rtrim(mb_substr($merchantCity ?: 'SAO PAULO', 0, 15), ' ');
    $txid         = substr(preg_replace('/[^A-Za-z0-9]/', '', $txid) ?: '', 0, 25);

    $merchantAccount = pix_tlv('00', 'BR.GOV.BCB.PIX')
        . pix_tlv('01', $key)
        . pix_tlv('02', $merchantName);

    $payload = pix_tlv('00', '01')                       // Payload Format Indicator
        . pix_tlv('26', $merchantAccount)               // Merchant Account Info
        . pix_tlv('52', '0000')                          // MCC
        . pix_tlv('53', '986')                           // BRL
        . pix_tlv('54', number_format($amount, 2, '.', ''))
        . pix_tlv('58', 'BR')
        . pix_tlv('59', $merchantName)
        . pix_tlv('60', $merchantCity)
        . pix_tlv('62', pix_tlv('05', $txid))            // txid
        . '6304';                                        // campo 63, só o rótulo

    return $payload . pix_crc16($payload);
}

/**
 * Gera os dados de pagamento do pedido e persiste em e5_orders.
 *
 * Persistir é o que torna a expiração real: sem payment_expires_at no
 * banco, "expira em 30 minutos" é texto de tela, e recarregar a página
 * renova o prazo. Chamado uma vez só — `payment_details` já existente é
 * preservado, para o Pix não mudar debaixo do cliente que já copiou o
 * código.
 *
 * @return array<string,mixed> os detalhes gerados
 */
function payment_create(PDO $pdo, int $orderId, string $method, float $total, ?string $txid = null): array
{
    $existing = payment_details($pdo, $orderId);
    if ($existing !== null) {
        return $existing;
    }

    $txid   = $txid ?: ('RT' . str_pad((string) $orderId, 8, '0', STR_PAD_LEFT));
    $method = $method ?: 'pix';
    $details = [];
    $expiresAt = null;

    if ($method === 'pix') {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $details = [
            'method'  => 'pix',
            'txid'    => $txid,
            'code'    => pix_brcode(
                $txid,
                $total,
                (string) store_config('pix_key'),
                (string) store_config('store_name'),
                'SAO PAULO'
            ),
            'expires' => $expiresAt,
        ];
    } elseif ($method === 'boleto') {
        // O boleto saiu da vitrine (pages/cart/checkout.php) porque a linha
        // digitável de verdade só existe com banco emissor: são 47 dígitos e
        // cada bloco tem dígito verificador calculado pelo banco. Não há
        // biblioteca local que gere isso, e um número com aparência plausível
        // que o banco recusa é pior para o cliente do que não ter boleto.
        //
        // Se algum pedido antigo ainda disser "boleto" no banco, cai no Pix:
        // assim o cliente recebe um código que funciona em vez de uma
        // instrução impossível de cumprir.
        error_log('payment_create: pedido pediu boleto, que saiu da vitrine; emitindo Pix no lugar');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $details = [
            'method'  => 'pix',
            'txid'    => $txid,
            'code'    => pix_brcode(
                $txid,
                $total,
                (string) store_config('pix_key'),
                (string) store_config('store_name'),
                'SAO PAULO'
            ),
            'expires' => $expiresAt,
        ];
    } else {
        return ['method' => $method, 'immediate' => true];
    }

    $stmt = $pdo->prepare(
        'UPDATE e5_orders SET payment_details = :d, payment_expires_at = :e WHERE id = :id'
    );
    $stmt->execute([
        ':d' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':e' => $expiresAt,
        ':id' => $orderId,
    ]);

    return $details;
}

/** Lê payment_details de forma tolerante (JSON invalido nao derruba a tela). */
function payment_details(PDO $pdo, int $orderId): ?array
{
    $st = $pdo->prepare('SELECT payment_details FROM e5_orders WHERE id = :id LIMIT 1');
    $st->execute([':id' => $orderId]);
    $raw = $st->fetchColumn();

    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Expira o pagamento se o prazo passou.
 *
 * Roda na leitura (GET da tela e no polling), não em cron: o TCC não tem
 * agendador, e um pagamento que "expira" só quando alguém olha a página
 * deixa o status mentindo. Idempotente: só escreve se ainda estiver
 * pendente.
 *
 * @param array<string,mixed> $order linha de e5_orders
 * @return array<string,mixed> a linha com payment_status possivelmente expirado
 */
function payment_expire_if_due(PDO $pdo, array $order): array
{
    $status = (string) ($order['payment_status'] ?? '');
    $expiresAt = $order['payment_expires_at'] ?? null;

    if ($expiresAt === null || $status === '' || in_array($status, ['paid', 'refunded', 'failed', 'expired'], true)) {
        return $order;
    }

    if (strtotime((string) $expiresAt) > time()) {
        return $order;
    }

    $st = $pdo->prepare("UPDATE e5_orders SET payment_status = 'expired' WHERE id = :id AND payment_status = :s");
    $st->execute([':id' => (int) $order['id'], ':s' => $status]);

    $order['payment_status'] = $st->rowCount() > 0 ? 'expired' : $status;
    return $order;
}

/**
 * Cancela o pedido, devolvendo o estoque.
 *
 * Idempotente por construção: o SELECT ... FOR UPDATE trava a linha, e o
 * segundo cancelamento concorrente espera o commit e já encontra o status
 * 'canceled', devolvendo sem tocar no estoque. Sem o FOR UPDATE, dois
 * cliques em "cancelar" devolviam o estoque duas vezes — o risco apontado
 * na auditoria.
 *
 * @return array{ok:bool,msg:string,order_status?:string}
 */
function order_cancel(PDO $pdo, int $orderId, int $userId, bool $isAdmin = false): array
{
    try {
        $pdo->beginTransaction();

        $sql = 'SELECT id, status, payment_status FROM e5_orders WHERE id = :id';
        if (!$isAdmin) {
            $sql .= ' AND user_id = :u';
        }
        $sql .= ' FOR UPDATE';

        $st = $pdo->prepare($sql);
        $st->execute($isAdmin ? [':id' => $orderId] : [':id' => $orderId, ':u' => $userId]);
        $order = $st->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $pdo->rollBack();
            return ['ok' => false, 'msg' => 'Pedido não encontrado.'];
        }

        if ($order['status'] === 'canceled') {
            $pdo->commit();
            return ['ok' => false, 'msg' => 'Este pedido já estava cancelado.', 'order_status' => 'canceled'];
        }

        if (in_array($order['status'], ['shipped', 'delivered'], true)) {
            $pdo->rollBack();
            return ['ok' => false, 'msg' => 'Pedido já enviado não pode mais ser cancelado por aqui.'];
        }

        $paymentStatus = $order['payment_status'] === 'paid' ? 'refunded' : $order['payment_status'];

        $pdo->prepare("UPDATE e5_orders SET status = 'canceled', payment_status = :ps WHERE id = :id")
            ->execute([':ps' => $paymentStatus, ':id' => $orderId]);

        // Estoque de volta, na mesma transação da mudança de status.
        $items = $pdo->prepare('SELECT product_id, quantity FROM e5_order_items WHERE order_id = :o');
        $items->execute([':o' => $orderId]);
        $restock = $pdo->prepare('UPDATE e5_products SET stock = stock + :q WHERE id = :p');
        foreach ($items->fetchAll() as $item) {
            $restock->execute([':q' => (int) $item['quantity'], ':p' => (int) $item['product_id']]);
        }

        $pdo->commit();

        return ['ok' => true, 'msg' => 'Pedido cancelado e estoque devolvido.', 'order_status' => 'canceled'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('order_cancel: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Não foi possível cancelar agora. Tente de novo.'];
    }
}

/** Rótulo/amostra de e5_orders.payment_status para a tela. */
function payment_status_label(string $status): array
{
    return match ($status) {
        'paid'       => ['Pagamento aprovado', 'var(--ml-green)', 'check-circle'],
        'processing' => ['Pagamento em processamento', 'var(--ml-blue, #2196f3)', 'clock'],
        'failed'     => ['Pagamento recusado', 'var(--ml-red, #e53935)', 'times-circle'],
        'expired'    => ['Pagamento expirado', 'var(--ml-text-muted)', 'hourglass-end'],
        'refunded'   => ['Pagamento estornado', 'var(--ml-text-muted)', 'undo'],
        default      => ['Aguardando pagamento', 'var(--ml-text-muted)', 'clock'],
    };
}
