<?php

declare(strict_types=1);

require_once __DIR__ . '/validators.php';

/**
 * Acesso a dados do pedido.
 *
 * Fica separado de order_state.php de proposito: o estado (o que e
 * valido) nao mora junto com o SQL (como se le). Isso tambem permite
 * testar a maquina de estados sem banco.
 */

/** Snapshot do pedido + dados do usuario, para a tela de detalhe. */
function order_repo_find(PDO $pdo, int $orderId): ?array
{
    $st = $pdo->prepare(
        'SELECT o.*,
                u.name AS customer_name,
                u.email AS customer_email,
                u.cpf AS customer_cpf,
                u.phone AS customer_phone
           FROM e5_orders o
           JOIN e5_users u ON u.id = o.user_id
          WHERE o.id = :id
          LIMIT 1'
    );
    $st->execute([':id' => $orderId]);
    $row = $st->fetch();

    return $row === false ? null : $row;
}

/**
 * Carrega o pedido so se o usuario for o dono ou admin.
 *
 * Autorizacao por SQL, nao por "pegou o pedido e checou depois": o id
 * de outro usuario simplesmente nao volta, e a tela responde 404 — o
 * mesmo que acontece com um id inexistente. Nao da para distinguir
 * "nao existe" de "nao e seu", que e exatamente o desejado.
 */
function order_repo_find_authorized(PDO $pdo, int $orderId, int $userId, bool $isAdmin): ?array
{
    $order = order_repo_find($pdo, $orderId);
    if ($order === null) {
        return null;
    }
    if (!$isAdmin && (int) $order['user_id'] !== $userId) {
        return null;
    }
    return $order;
}

/** Itens com o snapshot do pedido (nome/precao da hora da compra). */
function order_repo_items(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare(
        'SELECT oi.*,
                COALESCE(oi.product_name, p.name) AS display_name,
                p.slug AS product_slug
           FROM e5_order_items oi
           LEFT JOIN e5_products p ON p.id = oi.product_id
          WHERE oi.order_id = :oid
          ORDER BY oi.id ASC'
    );
    $st->execute([':oid' => $orderId]);

    return $st->fetchAll();
}

/** Pagamento do pedido (o unico ativo, ou o terminal mais recente). */
function order_repo_payment(PDO $pdo, int $orderId): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM e5_payments
          WHERE order_id = :oid
          ORDER BY (status IN (\'pending\',\'paid\')) DESC, id DESC
          LIMIT 1'
    );
    $st->execute([':oid' => $orderId]);
    $row = $st->fetch();

    return $row === false ? null : $row;
}

/** Trilha de status em ordem cronologica. */
function order_repo_history(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare(
        'SELECT * FROM e5_order_history WHERE order_id = :oid ORDER BY id ASC'
    );
    $st->execute([':oid' => $orderId]);

    return $st->fetchAll();
}

/**
 * Grava uma etapa no historico.
 *
 * INSERT IGNORE porque existe indice unico (order_id, status): repetir
 * a mesma etapa nao pode falhar a transacao inteira nem duplicar o
 * circulo dourado na tela.
 *
 * O status gravado e sempre a etapa da tela ('placed', 'paid', ...), e
 * nao o status cru do banco ('pending', 'paid', ...). Sao dois
 * vocabularios que so coincidem a partir do segundo passo, e e
 * exatamente essa coincidencia que faria um pedido parecer completo
 * quando nao esta. A conversao fica num lugar so, aqui.
 *
 * 'canceled' nao e uma etapa: e um desvio, guardado com o proprio nome
 * para a faixa vermelha da tela encontrar.
 */
function order_repo_add_history(PDO $pdo, int $orderId, string $status, string $note = ''): void
{
    $step = order_status_to_step($status) ?? $status;

    $pdo->prepare(
        'INSERT IGNORE INTO e5_order_history (order_id, status, note, created_at)
         VALUES (:oid, :status, :note, NOW())'
    )->execute([
        ':oid'    => $orderId,
        ':status' => $step,
        ':note'   => $note !== '' ? limit_text($note, 180) : null,
    ]);
}

/** Devolve o estoque dos itens ao catalogo. Idempotente por design do fluxo. */
function order_repo_restock(PDO $pdo, int $orderId): int
{
    $st = $pdo->prepare(
        'SELECT product_id, quantity FROM e5_order_items WHERE order_id = :oid'
    );
    $st->execute([':oid' => $orderId]);

    $up = $pdo->prepare(
        'UPDATE e5_products SET stock = stock + :qty WHERE id = :pid'
    );

    $n = 0;
    foreach ($st->fetchAll() as $item) {
        // Item cujo produto foi apagado do catalogo: nao ha onde
        // devolver o estoque, e o pedido continua cancelado normalmente.
        if ($item['product_id'] === null) {
            continue;
        }
        $up->execute([
            ':qty' => (int) $item['quantity'],
            ':pid' => (int) $item['product_id'],
        ]);
        $n++;
    }

    return $n;
}

/** Pagamento pendente de um pedido, se houver. */
function order_repo_pending_payment(PDO $pdo, int $orderId): ?array
{
    $st = $pdo->prepare(
        "SELECT * FROM e5_payments WHERE order_id = :oid AND status = 'pending' LIMIT 1"
    );
    $st->execute([':oid' => $orderId]);
    $row = $st->fetch();

    return $row === false ? null : $row;
}

/**
 * Rotulos de rastreio da SuperFrete para o card "Entrega".
 * Devolve array vazio quando a etiqueta ainda nao foi gerada.
 */
function order_repo_tracking_events(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare(
        'SELECT tracking_code, carrier, service, label_url, status
           FROM e5_shipments WHERE order_id = :oid ORDER BY id DESC LIMIT 1'
    );
    $st->execute([':oid' => $orderId]);
    $row = $st->fetch();

    return $row === false ? [] : [$row];
}

/**
 * Cancela na transportadora a etiqueta de um pedido cancelado.
 *
 * A etiqueta foi gerada no checkout da SuperFrete; cancelar o pedido na
 * loja sem cancelar la deixa a coleta de uma encomenda que nao sera
 * entregue, e a loja pagando por um envio que nao existe.
 *
 * Deliberadamente NAO lanca excecao: a SuperFrete e uma dependencia
 * externa e a transacao do cancelamento nao pode falhar por causa
 * dela. O erro fica registrado em e5_shipments.error_message e o
 * pedido continua cancelado — que e o estado que o cliente espera.
 *
 * @return bool true se havia etiqueta e ela foi cancelada.
 */
function order_repo_cancel_label(PDO $pdo, int $orderId): bool
{
    // shipping_functions.php carrega o autoload do Composer (a classe
    // TCC\SuperFreteClient vive em src/) e traz shipping_record_error().
    // Reaproveitar evita uma segunda forma de falar com a transportadora.
    if (!function_exists('shipping_record_error')) {
        require_once __DIR__ . '/shipping_functions.php';
    }

    $st = $pdo->prepare(
        "SELECT * FROM e5_shipments
          WHERE order_id = :oid
            AND superfrete_id IS NOT NULL
            AND superfrete_id <> ''
            AND status <> 'canceled'
          ORDER BY id DESC LIMIT 1"
    );
    $st->execute([':oid' => $orderId]);
    $shipment = $st->fetch();

    if ($shipment === false) {
        return false;
    }

    $sfId = (string) $shipment['superfrete_id'];

    try {
        $client = new \TCC\SuperFreteClient($_ENV);
        $client->cancelOrder($sfId, 'Pedido cancelado pelo cliente');
    } catch (Throwable $e) {
        // Falha da transportadora não pode derrubar o cancelamento: o
        // cliente pediu para cancelar e o pedido TEM de ficar
        // cancelado. A etiqueta fica 'error' com o motivo, para o
        // admin reconciliar depois.
        shipping_record_error($pdo, $orderId, 'Falha ao cancelar etiqueta ' . $sfId . ': ' . $e->getMessage());
        return false;
    }

    $pdo->prepare(
        "UPDATE e5_shipments
            SET status = 'canceled', error_message = NULL, updated_at = NOW()
          WHERE id = :id"
    )->execute([':id' => (int) $shipment['id']]);

    return true;
}

/** Endereco do pedido em uma linha, como aparece no card "Entrega". */
function order_repo_address_line(array $order): string
{
    $parts = array_filter([
        trim((string) ($order['shipping_street'] ?? '')),
    ]);

    $street = $parts[0] ?? '';
    // "S/N" (sem numero) nao e informacao: escrever ", S/N" so ocupa
    // espaco e faz o cliente achar que o logradoupo esta incompleto.
    $number = trim((string) ($order['shipping_number'] ?? ''));
    if ($number !== '' && !in_array(mb_strtoupper($number), ['S/N', 'SN', '-'], true)) {
        $street .= ', ' . $number;
    }

    $city = trim((string) ($order['shipping_city'] ?? ''));
    $state = trim((string) ($order['shipping_state'] ?? ''));
    if ($city !== '') {
        $street .= ', ' . $city . ($state !== '' ? '/' . $state : '');
    }

    return $street;
}

/**
 * Endereço de entrega com fallback:
 * (a) shipping_* do pedido se existir
 * (b) endereço do usuário SE o CEP coincidir com o CEP do pedido
 * (c) "Endereço não registrado — CEP XXXXXXX"
 *
 * Retorna array com: line (string formatada), cep, method (serviço + valor)
 */
function order_repo_address_with_fallback(PDO $pdo, array $order): array
{
    // (a) Tem endereço no pedido?
    $hasShippingAddr = trim((string) ($order['shipping_street'] ?? '')) !== '';
    $orderCep = only_digits((string) ($order['shipping_postal_code'] ?? ''));
    
    if ($hasShippingAddr && $orderCep !== '') {
        return [
            'line'   => order_repo_address_line($order),
            'cep'    => format_cep($orderCep),
            'method' => shipping_method_label($order),
        ];
    }

    // (b) Fallback: endereço do usuário se CEP coincidir
    if ($orderCep !== '') {
        $stmt = $pdo->prepare('
            SELECT postal_code, street, number, complement, neighborhood, city, state
            FROM e5_users WHERE id = :uid LIMIT 1
        ');
        $stmt->execute([':uid' => (int) $order['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $userCep = only_digits((string) ($user['postal_code'] ?? ''));
            if ($userCep === $orderCep && trim((string) ($user['street'] ?? '')) !== '') {
                $line = trim((string) $user['street']);
                $num = trim((string) ($user['number'] ?? ''));
                if ($num !== '' && !in_array(mb_strtoupper($num), ['S/N', 'SN', '-'], true)) {
                    $line .= ', ' . $num;
                }
                $comp = trim((string) ($user['complement'] ?? ''));
                if ($comp !== '') {
                    $line .= ' — ' . $comp;
                }
                $neigh = trim((string) ($user['neighborhood'] ?? ''));
                $city = trim((string) ($user['city'] ?? ''));
                $state = trim((string) ($user['state'] ?? ''));
                if ($neigh !== '' || $city !== '') {
                    $line .= ' — ' . $neigh . ($neigh !== '' && $city !== '' ? ', ' : '') . $city . ($state !== '' ? '/' . $state : '');
                }
                
                return [
                    'line'   => $line,
                    'cep'    => format_cep($orderCep),
                    'method' => shipping_method_label($order),
                ];
            }
        }
    }

    // (c) Sem endereço registrado
    return [
        'line'   => 'Endereço não registrado — CEP ' . format_cep($orderCep),
        'cep'    => format_cep($orderCep),
        'method' => shipping_method_label($order),
    ];
}

function shipping_method_label(array $order): string
{
    $method = (string) ($order['shipping_method'] ?? '');
    $cost = (float) ($order['shipping_cost'] ?? 0);
    
    $label = match ($method) {
        'pac' => 'PAC',
        'sedex' => 'Sedex',
        'delivery' => 'Entrega própria',
        default => ucfirst($method),
    };
    
    if ($cost > 0) {
        $label .= ' · R$ ' . number_format($cost, 2, ',', '.');
    } else {
        $label .= ' · Grátis';
    }
    
    return $label;
}
