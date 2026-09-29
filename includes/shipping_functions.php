<?php

declare(strict_types=1);

/**
 * Envio e rastreio (camada de negócio da SuperFrete).
 *
 * Até aqui o projeto tinha só o client HTTP (src/SuperFreteClient.php) e o
 * usava para COTAR frete no checkout. Faltava tudo o que vem depois:
 * criar a etiqueta de verdade, guardar o que a SuperFrete devolveu, e
 * atualizar o pedido quando o rastreio muda. e5_orders.tracking_code era
 * uma coluna que nenhum arquivo escrevia nem lia.
 *
 * Decisão: pedido PAGO pode ser enviado. Pedido pendente não — a etiqueta
 * seria comprada (o /checkout debita saldo da carteira) antes de existir
 * pagamento, e o estorno viria com a etiqueta já emitida.
 */

use TCC\SuperFreteClient;
use TCC\Exception\SuperFreteException;

require_once __DIR__ . '/config.php';

// A classe SuperFreteClient vive em src/ e é carregada pelo autoload do
// Composer. As páginas do admin incluem este arquivo direto, sem passar
// pelo vendor/autoload.php do checkout, então sem isto a classe não existe
// e o envio morre com "Class TCC\SuperFreteClient not found".
$shippingAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($shippingAutoload)) {
    require_once $shippingAutoload;
}

require_once __DIR__ . '/mail.php';

// Só se ainda não veio pelo autoload "files" do composer.json: quem chama
// este arquivo por require direto (as páginas do admin) precisa das funções
// de notificação, e include de novo o mesmo arquivo redeclara as funções.
if (!function_exists('notification_enqueue_order_event')) {
    require_once __DIR__ . '/notification_functions.php';
}

/** Rótulo do serviço SuperFrete a partir do id numérico usado no /cart. */
function superfrete_service_label(int|string|null $service): string
{
    return match ((string) $service) {
        '1'    => 'PAC',
        '2'    => 'Sedex',
        default => 'SuperFrete',
    };
}

/**
 * Traduz o status que a SuperFrete devolve para o ENUM de e5_shipments.
 *
 * O webhook já tem o seu próprio mapa, porque os eventos chegam nomeados.
 * Aqui é para a consulta pontual: quando o painel pede a etiqueta e a SuperFrete
 * responde "in_progress" ou "unauthorized", é preciso saber se isso muda algo
 * no banco. Status desconhecido devolve null de propósito — melhor não gravar
 * do que gravar errado.
 */
function shipping_map_remote_status(string $remote): ?string
{
    return match ($remote) {
        'pending', 'created', 'in_progress' => 'pending',
        'released', 'generated', 'posted', 'dispatched' => 'released',
        'delivered'                        => 'delivered',
        'cancelled', 'canceled'            => 'canceled',
        'error', 'failed'                  => 'error',
        default                            => null,
    };
}

/** Caminho inverso: id numérico a partir do rótulo gravado na etiqueta. */
function shipping_service_id(?string $label): ?string
{
    return match ($label) {
        'PAC'   => '1',
        'Sedex' => '2',
        default => null,
    };
}

/** Só dígitos, ou string vazia. CPF/CNPJ/CEP/ telefone entram assim na API. */
function shipping_only_digits(?string $value): string
{
    return preg_replace('/\D/', '', (string) $value) ?? '';
}

/**
 * Monta o payload de /cart a partir do pedido e dos itens.
 *
 * $order precisa trazer o join com e5_users (nome, email, cpf) e os campos
 * de endereço, que em e5_orders são parciais: bairro, cidade, UF e CEP
 * existem, mas a rua e o número vivem no cadastro do usuário.
 */
function shipping_build_payload(array $order, array $items, array $config): array
{
    // Remetente: o painel grava o endereço num campo só, a API quer as partes.
    $from = shipping_parse_store_address((string) ($config['store_address'] ?? ''));
    $storeCep = shipping_only_digits($_ENV['SUPERFRETE_ORIGIN_POSTAL_CODE'] ?? '');

    // A SuperFrete recusa o /cart inteiro com "ocorreu um ou mais erros"
    // quando district vem vazio — erro genérico que não diz qual campo.
    // O endereço padrão da loja ("Av. Paulista, 1000 - São Paulo, SP") não tem
    // bairro, então sem este preenchimento nenhum envio funciona.
    if (trim($from['district']) === '') {
        $from['district'] = 'Centro';
    }

    if ($storeCep === '') {
        throw new RuntimeException('O CEP de origem da loja não está configurado (SUPERFRETE_ORIGIN_POSTAL_CODE).');
    }

    $fromDocument = shipping_only_digits($config['store_document'] ?? '');
    if (strlen($fromDocument) !== 14) {
        throw new RuntimeException('Cadastre o CNPJ da loja em Ajustes antes de criar etiquetas. A SuperFrete exige remetente identificado.');
    }

    $toCep = shipping_only_digits($order['shipping_postal_code'] ?? '');
    if ($toCep === '') {
        $toCep = shipping_only_digits($order['postal_code'] ?? '');
    }
    if (strlen($toCep) !== 8) {
        throw new RuntimeException('O pedido não tem CEP de entrega válido. Preencha o endereço antes de enviar.');
    }

    $document = shipping_only_digits($order['cpf'] ?? '');
    if ($document === '') {
        throw new RuntimeException('O cliente não tem CPF cadastrado. A SuperFrete exige documento do destinatário.');
    }

    // Telefone é obrigatório na etiqueta, e normalizePhone() lança exceção
    // em número inválido — por isso a validação vem antes da chamada, para
    // o painel mostrar uma mensagem em vez de um erro fatal.
    $phoneRaw = shipping_only_digits($order['phone'] ?? '');
    if (!in_array(strlen($phoneRaw), [10, 11, 12, 13], true)) {
        throw new RuntimeException('O cliente não tem telefone válido cadastrado. A etiqueta exige contato para entrega.');
    }
    $phone = SuperFreteClient::normalizePhone($phoneRaw);

    // Rua e número vêm do cadastro do usuário. Sem eles a SuperFrete rejeita
    // o /cart, e um "Não informado" no lugar só empurra o erro para depois.
    if (trim((string) ($order['street'] ?? '')) === '' || (int) ($order['number'] ?? 0) <= 0) {
        throw new RuntimeException('O endereço do cliente está incompleto. Preencha rua e número no perfil antes de enviar.');
    }

    $district = trim((string) ($order['shipping_neighborhood'] ?? ''));
    if ($district === '') {
        $district = 'Centro';
    }

    $products = [];
    $volumes = ['height' => 15.0, 'width' => 10.0, 'length' => 20.0, 'weight' => 0.5];

    foreach ($items as $item) {
        $qty = max(1, (int) ($item['quantity'] ?? 1));
        $name = (string) ($item['name'] ?? 'Produto');
        $value = (float) ($item['unit_price'] ?? $item['price'] ?? 0);

        $products[] = [
            'name'          => mb_substr($name, 0, 60),
            'quantity'      => $qty,
            'unitary_value' => $value,
        ];

        // A SuperFrete aceita um volume único por carrinho. Somar as
        // dimensões dos itens e a maior das pesos dá um volume conservador:
        // superestima um pouco, o que é o erro seguro — subestimar gera
        //cobranca de frete que não cobre o pacote.
        if (!empty($item['height_cm']) && !empty($item['width_cm']) && !empty($item['length_cm']) && !empty($item['weight_kg'])) {
            $volumes['height'] = max($volumes['height'], (float) $item['height_cm']);
            $volumes['width']  = max($volumes['width'],  (float) $item['width_cm']);
            $volumes['length'] = max($volumes['length'], (float) $item['length_cm']);
            $volumes['weight'] = max($volumes['weight'], (float) $item['weight_kg'] * $qty);
        }
    }

    if ($products === []) {
        throw new RuntimeException('O pedido não tem itens para enviar.');
    }

    $total = array_sum(array_map(
        static fn (array $i): float => (float) ($i['unit_price'] ?? $i['price'] ?? 0) * max(1, (int) ($i['quantity'] ?? 1)),
        $items
    ));

    $service = (int) ($config['service'] ?? 2);

    return [
        'from' => [
            'name'        => SuperFreteClient::ensureFullName((string) ($config['store_name'] ?? '')),
            'postal_code' => $storeCep,
            'address'     => $from['street'],
            'number'      => $from['number'],
            'district'    => $from['district'],
            'city'        => $from['city'],
            'state_abbr'  => $from['state'],
            'document'    => $fromDocument,
        ],
        'to' => [
            'name'        => SuperFreteClient::ensureFullName((string) ($order['name'] ?? '')),
            'postal_code' => $toCep,
            'address'     => (string) ($order['street'] ?? ''),
            'number'      => (string) ($order['number'] ?? ''),
            'district'    => $district,
            'city'        => (string) ($order['shipping_city'] ?? ''),
            'state_abbr'  => (string) ($order['shipping_state'] ?? ''),
            'email'       => (string) ($order['email'] ?? ''),
            'phone'       => $phone,
            'document'    => $document,
        ],
        'service'  => $service,
        'products' => $products,
        'volumes'  => $volumes,
        'options'  => [
            'insurance_value' => $total,
            'receipt'         => false,
            'own_hand'        => false,
            'non_comercial'   => false,
        ],
        'platform' => 'RoyalTech',
    ];
}

/** Carrega pedido + itens + usuário. Escopo por id (uso do admin). */
function shipping_load_order(PDO $pdo, int $orderId): array
{
    // A lista é explícita porque "o.*" mais "u.*" colidiria em name, email e
    // id: quem vem depois sobrescreve, e um phone do usuário sumiria atrás de
    // uma coluna homônima (ou o contrário, dependendo da ordem). Escrever as
    // colunas evita a ambiguidade silenciosa.
    $st = $pdo->prepare(
        'SELECT o.id, o.user_id, o.status, o.payment_status, o.total, o.shipping_cost,
                o.shipping_neighborhood, o.shipping_city, o.shipping_state, o.shipping_postal_code,
                u.name, u.email, u.cpf, u.phone, u.street, u.number, u.complement, u.postal_code
         FROM e5_orders o
         JOIN e5_users u ON u.id = o.user_id
         WHERE o.id = :id LIMIT 1'
    );
    $st->execute([':id' => $orderId]);
    $order = $st->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException('Pedido não encontrado.');
    }

    $items = $pdo->prepare(
        'SELECT oi.product_id, oi.quantity, oi.unit_price, p.name,
                p.height_cm, p.width_cm, p.length_cm, p.weight_kg
         FROM e5_order_items oi
         JOIN e5_products p ON p.id = oi.product_id
         WHERE oi.order_id = :o'
    );
    $items->execute([':o' => $orderId]);

    return ['order' => $order, 'items' => $items->fetchAll(PDO::FETCH_ASSOC)];
}

/**
 * Cria a etiqueta na SuperFrete e grava em e5_shipments.
 *
 * Idempotente por pedido: se já existe envio ativo, devolve o existente em
 * vez de comprar uma segunda etiqueta. Sem isso, o F5 do admin no botão
 * "Enviar" gerava duas etiquetas e debitava a carteira duas vezes.
 *
 * @return array{ok:bool,msg:string,shipment?:array}
 */
function shipping_dispatch(PDO $pdo, array $order, array $items, ?string $serviceOverride = null): array
{
    $orderId = (int) $order['id'];

    if ($order['payment_status'] !== 'paid') {
        return ['ok' => false, 'msg' => 'Só é possível enviar um pedido com o pagamento confirmado.'];
    }

    if ($order['status'] === 'delivered') {
        return ['ok' => false, 'msg' => 'Este pedido já foi entregue.'];
    }

    if ($order['status'] === 'canceled') {
        return ['ok' => false, 'msg' => 'Este pedido está cancelado.'];
    }

    // Envio em aberto: devolve o existente, não compra outro.
    //
    // A consulta vem ANTES do teste de "já enviado" porque o próprio
    // despacho muda o pedido para "shipped". Consultando na outra ordem, o
    // segundo clique no botão cairia no "já foi enviado" e o F5 do admin
    // gastaria uma etiqueta sem o painel explicar por quê.
    $existing = $pdo->prepare(
        "SELECT * FROM e5_shipments WHERE order_id = :o AND status IN ('pending','released') ORDER BY id DESC LIMIT 1"
    );
    $existing->execute([':o' => $orderId]);
    $found = $existing->fetch(PDO::FETCH_ASSOC);

    if ($found) {
        return [
            'ok'       => true,
            'msg'      => 'Este pedido já tem uma etiqueta. Use o link de impressão abaixo.',
            'shipment' => $found,
        ];
    }

    if ($order['status'] === 'shipped') {
        // "shipped" sem etiqueta em aberto só acontece quando a transportadora
        // cancelou a etiqueta anterior. Recusar aqui deixaria o pedido preso
        // sem caminho para uma etiqueta nova.
        $hadCanceled = $pdo->prepare(
            "SELECT 1 FROM e5_shipments WHERE order_id = :o AND status = 'canceled' LIMIT 1"
        );
        $hadCanceled->execute([':o' => $orderId]);

        if (!$hadCanceled->fetch()) {
            return ['ok' => false, 'msg' => 'Este pedido já foi enviado.'];
        }
    }

    $config = shipping_store_config();
    if ($serviceOverride !== null && $serviceOverride !== '') {
        $config['service'] = $serviceOverride;
    }

    try {
        $payload = shipping_build_payload($order, $items, $config);
    } catch (RuntimeException $e) {
        shipping_record_error($pdo, $orderId, $e->getMessage());
        return ['ok' => false, 'msg' => $e->getMessage()];
    }

    try {
        $client = new SuperFreteClient($_ENV);
        $cart = $client->createShipping($payload);
    } catch (SuperFreteException $e) {
        $msg = 'A SuperFrete recusou a criação da etiqueta: ' . $e->getMessage();
        shipping_record_error($pdo, $orderId, $msg);
        return ['ok' => false, 'msg' => $msg];
    } catch (Throwable $e) {
        error_log('shipping_dispatch: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Falha de comunicação com a SuperFrete. Tente novamente.'];
    }

    $superfreteId = (string) ($cart['id'] ?? '');

    // O /cart cria a etiqueta em "pending": ela só vira "released" (e passa
    // a ser cobrada) depois do /checkout. Sem saldo na carteira do sandbox,
    // o checkout falha — por isso o envio nasce "pending" e a etiqueta é
    // impressa mesmo assim, deixando claro no painel que falta liberar.
    $status = 'pending';

    $st = $pdo->prepare(
        'INSERT INTO e5_shipments
            (order_id, superfrete_id, carrier, service, tracking_code, status, price,
             delivery_min_days, delivery_max_days, created_at, updated_at)
         VALUES (:o, :sf, :carrier, :service, :track, :status, :price, :min, :max, NOW(), NOW())'
    );
    $st->execute([
        ':o'       => $orderId,
        ':sf'      => $superfreteId !== '' ? $superfreteId : null,
        ':carrier' => 'correios',
        ':service' => superfrete_service_label($config['service'] ?? 2),
        ':track'   => (string) ($cart['self_tracking'] ?? ''),
        ':status'  => $status,
        ':price'   => isset($cart['price']) ? (float) $cart['price'] : null,
        ':min'     => isset($cart['delivery_min']) ? (int) $cart['delivery_min'] : null,
        ':max'     => isset($cart['delivery_max']) ? (int) $cart['delivery_max'] : null,
    ]);

    $shipmentId = (int) $pdo->lastInsertId();

    // O PDF e a previsão de prazo não vêm no /cart. A etiqueta começa com
    // prazo vazio e sem PDF justamente porque a SuperFrete ainda não
    // calculou: buscar no getOrderInfo evita mostrar um prazo estimado que
    // o cliente pode guardar e cobrar depois.
    //
    // Sem o /checkout, a etiqueta fica "pending" e a loja não paga por ela.
    // O PDF ainda sai (o sandbox gerou acima), mas o envio segue pendente até
    // haver saldo para liberar — o painel mostra isso explicitamente.
    $labelUrl = null;
    if ($superfreteId !== '') {
        try {
            $client = new SuperFreteClient($_ENV);
            $info = $client->getOrderInfo($superfreteId);

            $labelUrl = (string) ($info['print']['url'] ?? '') ?: null;

            if (isset($info['delivery_min'], $info['delivery_max'])) {
                $pdo->prepare(
                    'UPDATE e5_shipments SET delivery_min_days = :min, delivery_max_days = :max WHERE id = :i'
                )->execute([
                    ':min' => (int) $info['delivery_min'],
                    ':max' => (int) $info['delivery_max'],
                    ':i'  => $shipmentId,
                ]);
            }

            // Status real, se a etiqueta já tiver sido liberada.
            $remoteStatus = shipping_map_remote_status((string) ($info['status'] ?? ''));
            if ($remoteStatus !== null && $remoteStatus !== $status) {
                $pdo->prepare('UPDATE e5_shipments SET status = :s WHERE id = :i')
                    ->execute([':s' => $remoteStatus, ':i' => $shipmentId]);
                $status = $remoteStatus;
            }
        } catch (Throwable $e) {
            $labelUrl = null; // sem etiqueta ainda; o painel mostra o motivo
        }
    }

    if ($labelUrl !== null) {
        $pdo->prepare('UPDATE e5_shipments SET label_url = :u WHERE id = :i')
            ->execute([':u' => $labelUrl, ':i' => $shipmentId]);
    }

    // O pedido vai para "shipped" porque a etiqueta foi criada e o estoque
    // saiu; o rastreio real chega pelo webhook.
    $pdo->prepare("UPDATE e5_orders SET status = 'shipped' WHERE id = :id AND status IN ('pending','paid')")
        ->execute([':id' => $orderId]);

    $saved = $pdo->prepare('SELECT * FROM e5_shipments WHERE id = :i');
    $saved->execute([':i' => $shipmentId]);

    notification_enqueue_order_event($pdo, $orderId, 'shipment_created', [
        'tracking' => (string) ($cart['self_tracking'] ?? ''),
        'service'  => superfrete_service_label($config['service'] ?? 2),
    ]);

    return [
        'ok'       => true,
        'msg'      => $labelUrl !== null
            ? 'Etiqueta criada. Imprima o PDF para o transporte.'
            : 'Etiqueta criada, mas o PDF ainda não liberou. Tente gerar novamente em instantes.',
        'shipment' => $saved->fetch(PDO::FETCH_ASSOC),
    ];
}

/** Grava uma tentativa de envio que falhou, para o painel mostrar o motivo. */
function shipping_record_error(PDO $pdo, int $orderId, string $message): void
{
    $pdo->prepare(
        'INSERT INTO e5_shipments (order_id, status, error_message) VALUES (:o, "error", :e)'
    )->execute([':o' => $orderId, ':e' => mb_substr($message, 0, 2000)]);
}

/** Envios de um pedido, mais recente primeiro. */
function shipping_for_order(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare('SELECT * FROM e5_shipments WHERE order_id = :o ORDER BY id DESC');
    $st->execute([':o' => $orderId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Configuração de origem do envio.
 *
 * Só o que existe de verdade no painel entra aqui. A SuperFrete exige CNPJ
 * válido do remetente: o placeholder 00.000.000/0001-00 do cadastro é
 * recusado pela API, então shipping_dispatch() falha cedo com uma mensagem
 * clara em vez de mandar requisição inválida e esconder o motivo real.
 */
function shipping_store_config(): array
{
    return [
        'store_name'     => (string) store_config('store_name'),
        'store_address'  => (string) store_config('store_address'),
        'store_document' => (string) store_config('store_cnpj'),
        'service'        => '2',
    ];
}

/**
 * Separa o endereço completo cadastrado em rua, número, bairro, cidade e UF.
 * O painel grava "Av. Paulista, 1000 - Bela Vista" em um campo só; a API
 * quer as partes separadas.
 */
function shipping_parse_store_address(string $address): array
{
    $parts = [
        'street'   => '',
        'number'   => '',
        'district' => '',
        'city'     => '',
        'state'    => '',
    ];

    $address = trim($address);
    if ($address === '') {
        return $parts;
    }

    // "Rua, 123 - Bairro, Cidade/UF"
    $head = $address;
    $tail = '';
    if (str_contains($address, ' - ')) {
        [$head, $tail] = explode(' - ', $address, 2);
    }

    if (preg_match('/^(.*?)[,\s]+(\d+[A-Za-z]?)$/u', $head, $m)) {
        $parts['street'] = trim($m[1]);
        $parts['number'] = trim($m[2]);
    } else {
        $parts['street'] = trim($head);
    }

    if ($tail !== '') {
        $tailParts = array_values(array_filter(
            array_map('trim', explode(',', $tail)),
            static fn (string $p): bool => $p !== ''
        ));

        // A UF pode vir colada ("São Paulo/SP") ou solta ("São Paulo, SP").
        $last = (string) end($tailParts);
        if (preg_match('#^([A-Za-zÀ-ú][A-Za-zÀ-ú\s]*?)\s*/?\s*([A-Za-z]{2})$#u', $last, $m)
            && strlen(trim($m[2])) === 2
        ) {
            $parts['city'] = trim($m[1]);
            $parts['state'] = strtoupper(trim($m[2]));
            array_pop($tailParts);
        } elseif (preg_match('/^[A-Za-z]{2}$/', $last) && count($tailParts) > 1) {
            $parts['city'] = (string) $tailParts[count($tailParts) - 2];
            $parts['state'] = strtoupper($last);
            array_pop($tailParts);
            array_pop($tailParts);
        }

        if ($tailParts !== []) {
            $parts['district'] = (string) $tailParts[0];
        }
    }

    return $parts;
}
