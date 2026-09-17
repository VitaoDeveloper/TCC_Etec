<?php
declare(strict_types=1);

// =============================================================================
// Ciclo de vida de envios SuperFrete.
//
// Centraliza: cotação com cache curto, criação/pagamento/impressão da etiqueta,
// sincronização de rastreio/status e cancelamento — usado pelo checkout e pelo
// painel admin. Nenhuma função lança exceção: falham com ['ok' => false].
// =============================================================================

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/order_history_functions.php';
require_once __DIR__ . '/notifications_functions.php';

use TCC\SuperFreteClient;
use TCC\Exception\SuperFreteException;

if (!defined('SHIPPING_CACHE_DIR')) {
    define('SHIPPING_CACHE_DIR', dirname(__DIR__) . '/storage/cache/shipping');
}

// -----------------------------------------------------------------------------
// CLIENTE E ENDEREÇO DE ORIGEM
// -----------------------------------------------------------------------------

function shippingClient(): SuperFreteClient
{
    return new SuperFreteClient($_ENV);
}

// Endereço do remetente: env tem prioridade; senão faz o parsing possível de
// store_address ("Av. Paulista, 1000 - São Paulo, SP") com defaults sensatos.
function shippingOriginAddress(): array
{
    $raw = trim((string) (store_config('store_address') ?? ''));
    $street = '';
    $number = '';
    $city = '';
    $state = '';

    $parts = array_map('trim', explode(' - ', $raw));
    if (isset($parts[0])) {
        $left = $parts[0];
        if (preg_match('/^(.*?)[,\s]+(\d+[A-Za-z]?)$/', $left, $m)) {
            $street = trim($m[1]);
            $number = $m[2];
        } else {
            $street = $left;
        }
    }
    if (isset($parts[1])) {
        $seg = array_map('trim', explode(',', $parts[1]));
        $city = $seg[0] ?? '';
        $state = $seg[1] ?? '';
    }

    $env = static function (string $key, string $default): string {
        $v = trim((string) ($_ENV[$key] ?? ''));
        return $v !== '' ? $v : $default;
    };

    return [
        'name'        => SuperFreteClient::ensureFullName($env('SUPERFRETE_ORIGIN_NAME', (string) (store_config('store_name') ?? 'Royal Tech'))),
        'address'     => $env('SUPERFRETE_ORIGIN_ADDRESS', $street !== '' ? $street : 'Av. Paulista'),
        'number'      => $env('SUPERFRETE_ORIGIN_NUMBER', $number !== '' ? $number : '1000'),
        'district'    => $env('SUPERFRETE_ORIGIN_DISTRICT', 'Centro'),
        'city'        => $env('SUPERFRETE_ORIGIN_CITY', $city !== '' ? $city : 'São Paulo'),
        'state_abbr'  => strtoupper($env('SUPERFRETE_ORIGIN_STATE', $state !== '' ? $state : 'SP')),
        'postal_code' => SuperFreteClient::normalizePostalCode($env('SUPERFRETE_ORIGIN_POSTAL_CODE', '01310100')),
        'document'    => preg_replace('/\D/', '', $env('SUPERFRETE_ORIGIN_DOCUMENT', (string) (store_config('store_cnpj') ?? ''))) ?? '',
        'phone'       => $env('SUPERFRETE_ORIGIN_PHONE', (string) (store_config('store_phone') ?? '')),
    ];
}

// -----------------------------------------------------------------------------
// COTAÇÃO (+ CACHE CURTO)
// -----------------------------------------------------------------------------

function shippingBuildProducts(array $items): array
{
    $products = [];
    foreach ($items as $item) {
        $qty = max(1, (int) ($item['quantity'] ?? 1));
        $usePreset = !empty($item['package_size_id']);

        $hasCustom = static fn (mixed $v): bool => trim((string) ($v ?? '')) !== '' && (float) $v > 0;

        $height = $usePreset ? (float) ($item['preset_height_cm'] ?? 15.0)
                            : ($hasCustom($item['height_cm'] ?? null) ? (float) $item['height_cm'] : 15.0);
        $width  = $usePreset ? (float) ($item['preset_width_cm'] ?? 10.0)
                            : ($hasCustom($item['width_cm'] ?? null) ? (float) $item['width_cm'] : 10.0);
        $length = $usePreset ? (float) ($item['preset_length_cm'] ?? 20.0)
                            : ($hasCustom($item['length_cm'] ?? null) ? (float) $item['length_cm'] : 20.0);
        $weight = $hasCustom($item['weight_kg'] ?? null) ? (float) $item['weight_kg']
                     : ($usePreset ? (float) ($item['preset_max_weight_kg'] ?? 0.5) : 0.5);

        $products[] = [
            'quantity' => $qty,
            'height'   => $height,
            'width'    => $width,
            'length'   => $length,
            'weight'   => $weight,
        ];
    }
    return $products;
}

function shippingCacheGet(string $key, int $ttlSeconds): ?array
{
    if ($ttlSeconds <= 0) {
        return null;
    }
    $file = SHIPPING_CACHE_DIR . '/' . $key . '.json';
    if (!is_file($file)) {
        return null;
    }
    if (filemtime($file) === false || (time() - (int) filemtime($file)) > $ttlSeconds) {
        @unlink($file);
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function shippingCachePut(string $key, array $value): void
{
    if (!is_dir(SHIPPING_CACHE_DIR)) {
        @mkdir(SHIPPING_CACHE_DIR, 0777, true);
    }
    if (!is_dir(SHIPPING_CACHE_DIR)) {
        return;
    }
    @file_put_contents(SHIPPING_CACHE_DIR . '/' . $key . '.json', json_encode($value), LOCK_EX);
}

// Normaliza a resposta do /calculator em um mapa de opções exibíveis (PAC/SEDEX).
function shippingNormalizeQuote(array $result): array
{
    $options = [];
    foreach ($result as $row) {
        if (empty($row['name']) || !empty($row['error']) || ($row['has_error'] ?? false)) {
            continue;
        }
        $key = strtolower((string) $row['name']);
        if (!in_array($key, ['pac', 'sedex'], true)) {
            continue;
        }
        $min = $row['delivery_range']['min'] ?? $row['delivery_time'] ?? '';
        $max = $row['delivery_range']['max'] ?? $row['delivery_time'] ?? '';
        $days = ($min === '') ? '' : (($min === $max) ? "$min dia útil" : "$min-$max dias úteis");
        $options[$key] = [
            'method' => $row['name'],
            'cost'   => (float) $row['price'],
            'days'   => $days,
        ];
    }
    return $options;
}

// Cotação com cache curto (padrão 5 min) para não bater na API a cada submit.
function shippingQuote(string $cep, array $items, int $ttlSeconds = 300): array
{
    $cep = SuperFreteClient::normalizePostalCode($cep);
    if (strlen($cep) !== 8) {
        throw new RuntimeException('CEP inválido para cotação');
    }

    $products = shippingBuildProducts($items);
    $origin = SuperFreteClient::normalizePostalCode($_ENV['SUPERFRETE_ORIGIN_POSTAL_CODE'] ?? '01310100');
    $cacheKey = hash('sha256', json_encode([$origin, $cep, $products]));

    $cached = shippingCacheGet($cacheKey, $ttlSeconds);
    if ($cached !== null) {
        return $cached;
    }

    $result = shippingClient()->calculateShipping([
        'from'     => ['postal_code' => $origin],
        'to'       => ['postal_code' => $cep],
        'services' => '1,2',
        'options'  => [
            'own_hand' => false,
            'receipt'  => false,
            'insurance_value' => 0,
            'use_insurance_value' => false,
        ],
        'products' => $products,
    ]);

    $options = shippingNormalizeQuote($result);
    if ($options === []) {
        throw new RuntimeException('Nenhuma opção de frete disponível para o CEP informado');
    }

    shippingCachePut($cacheKey, $options);
    return $options;
}

// -----------------------------------------------------------------------------
// PERSISTÊNCIA DO ENVIO
// -----------------------------------------------------------------------------

function shippingGetForOrder(PDO $pdo, int $orderId): ?array
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM e5_shippings WHERE order_id = :oid ORDER BY id DESC LIMIT 1');
        $stmt->execute([':oid' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        error_log('shippingGetForOrder: ' . $e->getMessage());
        return null;
    }
}

// -----------------------------------------------------------------------------
// CRIAR ETIQUETA (POST /cart)
// -----------------------------------------------------------------------------

function shippingCreateLabel(PDO $pdo, int $orderId, string $serviceId = '1'): array
{
    $existing = shippingGetForOrder($pdo, $orderId);
    if ($existing && !empty($existing['superfrete_order_id']) && (int) $existing['canceled'] === 0) {
        return ['ok' => true, 'message' => 'Etiqueta já gerada para este pedido.', 'shipping' => $existing];
    }

    $order = $pdo->prepare('SELECT o.*, u.name AS user_name, u.email AS user_email, u.cpf AS user_cpf,
            u.phone AS user_phone, u.postal_code AS user_postal_code, u.street AS user_street,
            u.number AS user_number, u.complement AS user_complement
        FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
    $order->execute([':id' => $orderId]);
    $order = $order->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return ['ok' => false, 'message' => 'Pedido não encontrado.'];
    }

    $itemsStmt = $pdo->prepare('SELECT oi.quantity, oi.unit_price, p.name, p.package_size_id,
            p.weight_kg, p.height_cm, p.width_cm, p.length_cm,
            ps.height_cm AS preset_height_cm, ps.width_cm AS preset_width_cm,
            ps.length_cm AS preset_length_cm, ps.max_weight_kg AS preset_max_weight_kg
        FROM e5_order_items oi
        INNER JOIN e5_products p ON p.id = oi.product_id
        LEFT JOIN e5_package_sizes ps ON ps.id = p.package_size_id
        WHERE oi.order_id = :oid');
    $itemsStmt->execute([':oid' => $orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        return ['ok' => false, 'message' => 'Pedido sem itens para envio.'];
    }

    $phoneDigits = preg_replace('/\D/', '', (string) ($order['user_phone'] ?? '')) ?? '';
    if (strlen($phoneDigits) !== 11) {
        return ['ok' => false, 'message' => 'Telefone do cliente deve ter 11 dígitos (DDD + número) para gerar a etiqueta.'];
    }
    $document = preg_replace('/\D/', '', (string) ($order['user_cpf'] ?? '')) ?? '';
    if ($document === '') {
        return ['ok' => false, 'message' => 'CPF/CNPJ do cliente é obrigatório para gerar a etiqueta.'];
    }

    $origin = shippingOriginAddress();
    $originDoc = preg_replace('/\D/', '', (string) $origin['document']) ?? '';

    $from = [
        'name'        => $origin['name'],
        'address'     => $origin['address'],
        'number'      => $origin['number'],
        'district'    => $origin['district'],
        'city'        => $origin['city'],
        'state_abbr'  => $origin['state_abbr'],
        'postal_code' => $origin['postal_code'],
    ];
    if ($originDoc !== '') {
        $from['document'] = $originDoc;
    }

    $to = [
        'name'        => SuperFreteClient::ensureFullName((string) $order['user_name']),
        'address'     => (string) ($order['user_street'] ?: 'Não informado'),
        'number'      => (string) ($order['user_number'] ?: ''),
        'district'    => (string) ($order['shipping_neighborhood'] ?: 'Centro'),
        'city'        => (string) ($order['shipping_city'] ?: 'São Paulo'),
        'state_abbr'  => strtoupper((string) ($order['shipping_state'] ?: 'SP')),
        'postal_code' => SuperFreteClient::normalizePostalCode((string) ($order['shipping_postal_code'] ?: $order['user_postal_code'])),
        'email'       => (string) ($order['user_email'] ?? ''),
        'phone'       => $phoneDigits,
        'document'    => $document,
    ];
    if (!empty($order['user_complement'])) {
        $to['complement'] = (string) $order['user_complement'];
    }

    // Produtos para declaração (nome + quantidade + valor unitário).
    $products = [];
    foreach ($items as $it) {
        $products[] = [
            'name'          => (string) $it['name'],
            'quantity'      => (string) max(1, (int) $it['quantity']),
            'unitary_value' => (string) number_format((float) $it['unit_price'], 2, '.', ''),
        ];
    }

    $volumes = shippingBuildVolumes($items);

    $payload = [
        'from'     => $from,
        'to'       => $to,
        'service'  => (int) $serviceId,
        'products' => $products,
        'volumes'  => $volumes,
        'options'  => [
            // Seguro automático desabilitado: o valor segurado explícito pode
            // ultrapassar o limite do serviço e impedir a emissão da etiqueta.
            // O valor declarado dos produtos segue em products[].unitary_value.
            'insurance_value' => 0.0,
            'receipt'         => false,
            'own_hand'        => false,
            'non_comercial'   => false,
            'tags'            => [
                ['tag' => 'ORDER-' . $orderId, 'url' => ''],
            ],
        ],
        'platform' => 'RoyalTech',
    ];

    try {
        $res = shippingClient()->createShipping($payload);
    } catch (SuperFreteException $e) {
        return ['ok' => false, 'message' => 'SuperFrete: ' . $e->getMessage()];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Falha ao gerar etiqueta: ' . $e->getMessage()];
    }

    $sid = (string) ($res['id'] ?? '');
    if ($sid === '') {
        return ['ok' => false, 'message' => 'Resposta inesperada da SuperFrete ao criar a etiqueta.'];
    }

    shippingUpsert($pdo, $orderId, [
        'superfrete_order_id' => $sid,
        'service_id'          => (string) $serviceId,
        'service_name'        => (string) ($order['shipping_method'] ?? ''),
        'price'               => (float) ($res['price'] ?? $order['shipping_cost'] ?? 0),
        'status'              => (string) ($res['status'] ?? 'pending'),
    ]);

    orderHistoryAdd(
        $pdo,
        $orderId,
        (string) $order['status'],
        (string) $order['payment_status'],
        'Etiqueta SuperFrete gerada (ID ' . $sid . ').',
        'SuperFrete'
    );

    return ['ok' => true, 'message' => 'Etiqueta gerada (ID ' . $sid . ').', 'superfrete_order_id' => $sid];
}

// Consolida os itens em volumes (usa as dimensões individuais e a maior caixa).
function shippingBuildVolumes(array $items): array
{
    $volumes = [];
    foreach ($items as $it) {
        $usePreset = !empty($it['package_size_id']);
        $hasCustom = static fn (mixed $v): bool => trim((string) ($v ?? '')) !== '' && (float) $v > 0;

        $height = $usePreset ? (float) ($it['preset_height_cm'] ?? 15.0)
                            : ($hasCustom($it['height_cm'] ?? null) ? (float) $it['height_cm'] : 15.0);
        $width  = $usePreset ? (float) ($it['preset_width_cm'] ?? 10.0)
                            : ($hasCustom($it['width_cm'] ?? null) ? (float) $it['width_cm'] : 10.0);
        $length = $usePreset ? (float) ($it['preset_length_cm'] ?? 20.0)
                            : ($hasCustom($it['length_cm'] ?? null) ? (float) $it['length_cm'] : 20.0);
        $weight = $hasCustom($it['weight_kg'] ?? null) ? (float) $it['weight_kg']
                     : ($usePreset ? (float) ($it['preset_max_weight_kg'] ?? 0.5) : 0.5);

        // 1 volume por unidade (respeita a quantidade do item).
        for ($i = 0; $i < max(1, (int) $it['quantity']); $i++) {
            $volumes[] = [
                'height' => $height,
                'width'  => $width,
                'length' => $length,
                'weight' => $weight,
            ];
        }
    }
    return $volumes;
}

// Insere/atualiza o envio do pedido (1 por order_id via superfrete_order_id único).
function shippingUpsert(PDO $pdo, int $orderId, array $data): void
{
    $existing = shippingGetForOrder($pdo, $orderId);
    $fields = [
        'superfrete_order_id' => $data['superfrete_order_id'] ?? null,
        'service_id'          => $data['service_id'] ?? null,
        'service_name'        => $data['service_name'] ?? null,
        'carrier'             => $data['carrier'] ?? null,
        'tracking_code'       => $data['tracking_code'] ?? null,
        'label_url'           => $data['label_url'] ?? null,
        'price'               => array_key_exists('price', $data) ? (float) $data['price'] : null,
        'delivery_days'       => $data['delivery_days'] ?? null,
        'status'              => $data['status'] ?? 'pending',
        'last_event'          => $data['last_event'] ?? null,
        'last_event_at'       => $data['last_event_at'] ?? null,
    ];

    try {
        if ($existing) {
            $set = [];
            $params = [':oid' => $orderId];
            foreach ($fields as $col => $val) {
                if ($val === null) {
                    continue; // preserva valor atual quando não enviado
                }
                $set[] = "$col = :$col";
                $params[":$col"] = $val;
            }
            if ($set !== []) {
                $stmt = $pdo->prepare('UPDATE e5_shippings SET ' . implode(', ', $set) . ' WHERE order_id = :oid');
                $stmt->execute($params);
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO e5_shippings
                (order_id, superfrete_order_id, service_id, service_name, carrier, tracking_code, label_url, price, delivery_days, status, last_event, last_event_at)
                VALUES (:order_id, :superfrete_order_id, :service_id, :service_name, :carrier, :tracking_code, :label_url, :price, :delivery_days, :status, :last_event, :last_event_at)');
            $stmt->execute([
                ':order_id'           => $orderId,
                ':superfrete_order_id' => $fields['superfrete_order_id'],
                ':service_id'         => $fields['service_id'],
                ':service_name'       => $fields['service_name'],
                ':carrier'            => $fields['carrier'],
                ':tracking_code'      => $fields['tracking_code'],
                ':label_url'          => $fields['label_url'],
                ':price'              => $fields['price'] ?? 0,
                ':delivery_days'      => $fields['delivery_days'],
                ':status'             => $fields['status'],
                ':last_event'         => $fields['last_event'],
                ':last_event_at'      => $fields['last_event_at'],
            ]);
        }
    } catch (Throwable $e) {
        error_log('shippingUpsert: ' . $e->getMessage());
    }
}

// -----------------------------------------------------------------------------
// PAGAR ETIQUETA (POST /checkout)
// -----------------------------------------------------------------------------

function shippingPayLabel(PDO $pdo, int $orderId): array
{
    $shipping = shippingGetForOrder($pdo, $orderId);
    $sid = (string) ($shipping['superfrete_order_id'] ?? '');
    if ($sid === '') {
        return ['ok' => false, 'message' => 'Gere a etiqueta antes de pagá-la.'];
    }

    try {
        $res = shippingClient()->checkout(['orders' => [$sid]]);
    } catch (SuperFreteException $e) {
        return ['ok' => false, 'message' => 'SuperFrete: ' . $e->getMessage()];
    }

    if (empty($res['success'])) {
        return ['ok' => false, 'message' => 'A SuperFrete recusou o checkout (verifique o saldo da carteira).'];
    }

    shippingUpsert($pdo, $orderId, ['status' => 'released', 'last_event' => 'order.released', 'last_event_at' => date('Y-m-d H:i:s')]);
    $curStmt = $pdo->prepare('SELECT status FROM e5_orders WHERE id = :id LIMIT 1');
    $curStmt->execute([':id' => $orderId]);
    orderHistoryAdd(
        $pdo,
        $orderId,
        (string) ($curStmt->fetchColumn() ?: 'paid'),
        null,
        'Etiqueta paga na SuperFrete (status released).',
        'SuperFrete'
    );

    return ['ok' => true, 'message' => 'Etiqueta paga e liberada para postagem.'];
}

// -----------------------------------------------------------------------------
// IMPRIMIR ETIQUETA (POST /tag/print)
// -----------------------------------------------------------------------------

function shippingPrintLabel(PDO $pdo, int $orderId): array
{
    $shipping = shippingGetForOrder($pdo, $orderId);
    $sid = (string) ($shipping['superfrete_order_id'] ?? '');
    if ($sid === '') {
        return ['ok' => false, 'message' => 'Gere a etiqueta antes de imprimi-la.'];
    }

    try {
        $res = shippingClient()->getPrintLink(['orders' => [$sid]]);
    } catch (SuperFreteException $e) {
        return ['ok' => false, 'message' => 'SuperFrete: ' . $e->getMessage()];
    }

    $url = (string) ($res['url'] ?? '');
    if ($url === '') {
        return ['ok' => false, 'message' => 'A SuperFrete não retornou o link de impressão.'];
    }

    shippingUpsert($pdo, $orderId, ['label_url' => $url]);
    return ['ok' => true, 'message' => 'Link da etiqueta gerado.', 'url' => $url];
}

// -----------------------------------------------------------------------------
// SINCRONIZAR RASTREIO/STATUS (GET /order/info/{id})
// -----------------------------------------------------------------------------

function shippingRefresh(PDO $pdo, int $orderId): array
{
    $shipping = shippingGetForOrder($pdo, $orderId);
    if (!$shipping) {
        return ['ok' => false, 'message' => 'Nenhuma etiqueta gerada para este pedido.'];
    }

    try {
        $info = shippingClient()->getOrderInfo((string) $shipping['superfrete_order_id']);
    } catch (SuperFreteException $e) {
        return ['ok' => false, 'message' => 'SuperFrete: ' . $e->getMessage()];
    }

    return shippingApplyEvent($pdo, $orderId, $info);
}

// Aplica um payload (webhook ou /order/info) ao envio + pedido.
// Retorna ['ok' => bool, 'message' => string, 'changed' => bool].
function shippingApplyEvent(PDO $pdo, int $orderId, array $data, string $eventType = ''): array
{
    $shipping = shippingGetForOrder($pdo, $orderId);

    $status   = strtolower((string) ($data['status'] ?? $shipping['status'] ?? ''));
    $tracking = (string) ($data['tracking'] ?? $data['tracking_code'] ?? $shipping['tracking_code'] ?? '');
    $carrier  = (string) ($data['carrier'] ?? $data['service']['name'] ?? $shipping['carrier'] ?? '');

    $updates = ['status' => $status !== '' ? $status : 'pending'];
    if ($tracking !== '') {
        $updates['tracking_code'] = $tracking;
    }
    if ($carrier !== '') {
        $updates['carrier'] = $carrier;
    }
    if ($status !== '') {
        $updates['last_event'] = $eventType !== '' ? $eventType : $status;
        $updates['last_event_at'] = date('Y-m-d H:i:s');
    }
    shippingUpsert($pdo, $orderId, $updates);

    // Mapeia o status SuperFrete para o status do pedido (só avança).
    $orderStmt = $pdo->prepare('SELECT status, payment_status, tracking_code FROM e5_orders WHERE id = :id LIMIT 1');
    $orderStmt->execute([':id' => $orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return ['ok' => false, 'message' => 'Pedido não encontrado.'];
    }

    $orderRank = ['pending' => 0, 'paid' => 1, 'shipped' => 2, 'delivered' => 3, 'canceled' => 4];
    $current = (string) $order['status'];
    $changed = false;

    $applyOrderStatus = static function (string $newStatus, string $note) use ($pdo, $orderId, $order, $tracking, &$changed, $orderRank, $current): void {
        if ($newStatus === 'canceled') {
            if ($current !== 'canceled') {
                $pdo->prepare("UPDATE e5_orders SET status = 'canceled' WHERE id = :id")->execute([':id' => $orderId]);
                orderHistoryAdd($pdo, $orderId, 'canceled', (string) $order['payment_status'], $note, 'SuperFrete');
                $changed = true;
            }
            return;
        }
        if (($orderRank[$newStatus] ?? 0) <= ($orderRank[$current] ?? 0)) {
            return; // não regride o status
        }
        $params = [':id' => $orderId];
        $sql = 'UPDATE e5_orders SET status = :status';
        $params[':status'] = $newStatus;
        if ($tracking !== '' && empty($order['tracking_code'])) {
            $sql .= ', tracking_code = :track';
            $params[':track'] = $tracking;
        }
        $sql .= ' WHERE id = :id';
        $pdo->prepare($sql)->execute($params);
        orderHistoryAdd($pdo, $orderId, $newStatus, (string) $order['payment_status'], $note, 'SuperFrete');
        $changed = true;
    };

    switch ($status) {
        case 'posted':
            $applyOrderStatus('shipped', 'Pedido postado na transportadora' . ($tracking !== '' ? '. Rastreio: ' . $tracking : '.'));
            notificationTrigger('order_shipped', $orderId, ['tracking' => $tracking, 'carrier' => $carrier], $pdo);
            break;
        case 'delivered':
            $applyOrderStatus('delivered', 'Pedido entregue ao destinatário.');
            notificationTrigger('order_delivered', $orderId, [], $pdo);
            break;
        case 'canceled':
        case 'cancelled':
            $applyOrderStatus('canceled', 'Etiqueta cancelada na SuperFrete.');
            $pdo->prepare('UPDATE e5_shippings SET canceled = 1 WHERE order_id = :oid')->execute([':oid' => $orderId]);
            break;
    }

    return [
        'ok'      => true,
        'message' => $changed ? 'Envio sincronizado e pedido atualizado.' : 'Envio sincronizado.',
        'changed' => $changed,
        'status'  => $status,
    ];
}

// -----------------------------------------------------------------------------
// CANCELAR ETIQUETA (POST /order/cancel)
// -----------------------------------------------------------------------------

function shippingCancelLabel(PDO $pdo, int $orderId, string $reason = 'Cancelado pelo usuario'): array
{
    $shipping = shippingGetForOrder($pdo, $orderId);
    $sid = (string) ($shipping['superfrete_order_id'] ?? '');
    if ($sid === '') {
        return ['ok' => false, 'message' => 'Nenhuma etiqueta para cancelar.'];
    }
    if ((int) ($shipping['canceled'] ?? 0) === 1) {
        return ['ok' => true, 'message' => 'Etiqueta já está cancelada.'];
    }

    try {
        shippingClient()->cancelOrder($sid, $reason);
    } catch (SuperFreteException $e) {
        return ['ok' => false, 'message' => 'SuperFrete: ' . $e->getMessage()];
    }

    $pdo->prepare("UPDATE e5_shippings SET canceled = 1, status = 'canceled', last_event = 'order.cancelled', last_event_at = :now WHERE order_id = :oid")
        ->execute([':now' => date('Y-m-d H:i:s'), ':oid' => $orderId]);
    $curStmt = $pdo->prepare('SELECT status FROM e5_orders WHERE id = :id LIMIT 1');
    $curStmt->execute([':id' => $orderId]);
    orderHistoryAdd($pdo, $orderId, (string) ($curStmt->fetchColumn() ?: 'paid'), null, 'Etiqueta SuperFrete cancelada: ' . $reason, 'SuperFrete');

    return ['ok' => true, 'message' => 'Etiqueta cancelada na SuperFrete.'];
}
