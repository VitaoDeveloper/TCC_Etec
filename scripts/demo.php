<?php

declare(strict_types=1);

/**
 * Demo interativa da integração SuperFrete (sandbox).
 *
 * Uso:
 *   php scripts/demo.php
 *
 * Percorre todos os endpoints reais (requer SUPERFRETE_TOKEN no .env).
 * Cria um pedido, imprime link da etiqueta, lista, consulta e cancela.
 * O /checkout é tentado e o erro de falta de saldo é capturado como evidência.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/config.php';

loadEnv(__DIR__ . '/../.env');

use TCC\SuperFreteClient;
use TCC\Exception\SuperFreteException;
use TCC\Webhook\WebhookHandler;

const BAR = '----------------------------------------------';

$env = [
    'SUPERFRETE_TOKEN'     => $_ENV['SUPERFRETE_TOKEN'] ?? '',
    'SUPERFRETE_BASE_URL'  => $_ENV['SUPERFRETE_BASE_URL'] ?? 'https://sandbox.superfrete.com/',
    'SUPERFRETE_USER_AGENT'=> $_ENV['SUPERFRETE_USER_AGENT'] ?? 'RoyalTech 1.0 (integracao@superfrete.com)',
];

$client = new SuperFreteClient($env, __DIR__ . '/../storage/superfrete-demo.log');

echo "Ambiente : {$env['SUPERFRETE_BASE_URL']}\n";
echo "Token    : " . SuperFreteClient::maskToken($env['SUPERFRETE_TOKEN']) . "\n";
echo BAR . "\n";

// 1) HEALTH CHECK -----------------------------------------------------------
echo "▶ HEALTH CHECK (GET /me/orders?page=1&per_page=1)\n";
$health = $client->healthCheck();
printf("  OK — total de etiquetas na conta: %d\n\n", $health['meta']['total'] ?? 0);

// 2) COTAÇÃO ----------------------------------------------------------------
echo "▶ COTAÇÃO (POST /calculator) com products[]\n";
$quote = $client->calculateShipping([
    'from'     => ['postal_code' => '01310100'],
    'to'       => ['postal_code' => '20020050'],
    'services' => '1,2,17',
    'options'  => [
        'own_hand' => false,
        'receipt'  => false,
        'insurance_value' => 0,
        'use_insurance_value' => false,
    ],
    'products' => [
        ['quantity' => 1, 'height' => 15, 'width' => 10, 'length' => 20, 'weight' => 0.5],
        ['quantity' => 2, 'height' => 10, 'width' => 5,  'length' => 12, 'weight' => 0.3],
    ],
]);
foreach ($quote as $option) {
    $name = $option['name'] ?? '?';
    if (($option['has_error'] ?? false) || isset($option['error'])) {
        printf("  - %-12s erro: %s\n", $name, $option['error'] ?? 'Serviço indisponível');
        continue;
    }
    $box = $option['packages'][0]['dimensions'] ?? null;
    printf(
        "  - %-12s R$ %.2f | prazo %d dias | caixa ideal %sx%sx%s cm / %s kg\n",
        $name,
        $option['price'],
        $option['delivery_time'] ?? 0,
        $box['height'] ?? '-', $box['width'] ?? '-', $box['length'] ?? '-',
        $option['packages'][0]['weight'] ?? '-'
    );
}
echo "\n";

// 3) CRIAR FRETE ------------------------------------------------------------
// Escolhe a opção de frete que retornou preço (e a caixa ideal) no /calculator
$service = null;
$dims = ['height' => 2, 'width' => 11, 'length' => 16];
$wgt  = 0.3;
foreach ($quote as $option) {
    if (isset($option['packages'][0], $option['id']) && empty($option['error'])) {
        $service = $option['id'];
        $box = $option['packages'][0]['dimensions'];
        $dims = ['height' => $box['height'], 'width' => $box['width'], 'length' => $box['length']];
        $wgt  = $option['packages'][0]['weight'];
        break;
    }
}
if ($service === null) {
    fwrite(STDERR, "Nenhum serviço disponível no trecho. Abortando.\n");
    exit(1);
}

echo "▶ CRIAR FRETE (POST /cart) — caixa ideal do /calculator\n";
$order = $client->createShipping([
    'from' => [
        'name'        => SuperFreteClient::ensureFullName('Royal Tech'),
        'address'     => 'Av Paulista',
        'number'      => '1000',
        'district'    => 'Bela Vista',
        'city'        => 'Sao Paulo',
        'state_abbr'  => 'SP',
        'postal_code' => SuperFreteClient::normalizePostalCode('01310-100'),
        'document'    => '49698132805',
    ],
    'to' => [
        'name'        => 'Cliente Demo',
        'address'     => 'Rua B',
        'number'      => '2',
        'district'    => 'Centro',
        'city'        => 'Rio de Janeiro',
        'state_abbr'  => 'RJ',
        'postal_code' => SuperFreteClient::normalizePostalCode('20020-050'),
        'email'       => 'cliente@demo.com.br',
        'phone'       => SuperFreteClient::normalizePhone('(11) 98888-7777'),
        'document'    => '11144477735',
    ],
    'service'  => $service,
    'products' => [
        ['name' => 'Notebook Gamer', 'quantity' => 1, 'unitary_value' => 350.00],
        ['name' => 'Mouse Gamer',    'quantity' => 2, 'unitary_value' => 45.50],
    ],
    'volumes'  => ['height' => $dims['height'], 'width' => $dims['width'], 'length' => $dims['length'], 'weight' => (float) $wgt],
    'options'  => ['insurance_value' => 441.00, 'receipt' => false, 'own_hand' => false],
    'platform' => 'RoyalTech',
]);
$orderId = $order['id'];
printf("  Pedido criado: %s | preço R$ %.2f | status: %s\n\n", $orderId, $order['price'], $order['status']);

// 4) CHECKOUT (evidência de erro sem saldo) ----------------------------------
echo "▶ CHECKOUT (POST /checkout) — requer saldo na carteira\n";
try {
    $checkout = $client->checkout(['orders' => [$orderId]]);
    printf("  OK: status %s\n\n", $checkout['purchase']['status'] ?? '?');
} catch (SuperFreteException $e) {
    printf("  ⚠️ Limitação real: HTTP %d — %s\n\n", $e->getHttpStatus(), $e->getMessage());
}

// 5) INFORMAÇÕES DO PEDIDO ---------------------------------------------------
echo "▶ INFORMAÇÕES (GET /order/info/{id})\n";
$info = $client->getOrderInfo($orderId);
printf("  id=%s status=%s price=%s delivery=%d serviço=%d\n", $info['id'], $info['status'], $info['price'], $info['delivery'] ?? 0, $info['service_id'] ?? 0);

// 6) LINK DE IMPRESSÃO --------------------------------------------------------
echo "\n▶ IMPRESSÃO (POST /tag/print)\n";
$print = $client->getPrintLink(['orders' => [$orderId]]);
printf("  URL PDF: %s\n", $print['url']);

// 7) LISTAGEM -----------------------------------------------------------------
echo "\n▶ LISTAR ETIQUETAS (GET /me/orders) com filtros\n";
$list = $client->listOrders(['status' => 'pending', 'page' => 1, 'per_page' => 5]);
printf("  %d etiquetas na página 1 de %d\n", count($list['data'] ?? []), $list['meta']['last_page'] ?? 0);

// 8) WEBHOOKS (CRUD) ----------------------------------------------------------
echo "\n▶ WEBHOOKS (CRUD completo)\n";
$wh = null;
try {
    $wh = $client->createWebhook('TCC Demo Webhook', 'https://example.com/webhook/superfrete', ['order.created']);
    printf("  Criado: %s (secret mascarado: %s)\n", $wh['id'], SuperFreteClient::maskToken($wh['secret_token'] ?? ''));
    $client->updateWebhook($wh['id'], ['name' => 'TCC Demo Webhook Atualizado']);
    echo "  Atualizado ✓\n";
    $client->deleteWebhook($wh['id']);
    echo "  Deletado ✓\n";
    echo "  ATENÇÃO: salve o secret_token no .env → SUPERFRETE_WEBHOOK_SECRET\n";
} catch (SuperFreteException $e) {
    printf("  Webhook: HTTP %d — %s\n", $e->getHttpStatus(), $e->getMessage());
}

// 9) CANCELAMENTO -------------------------------------------------------------
echo "\n▶ CANCELAR PEDIDO (POST /order/cancel)\n";
$cancel = $client->cancelOrder($orderId, 'Cancelado pela demo');
printf("  %s\n\n", json_encode($cancel, JSON_UNESCAPED_UNICODE));

echo "Fim da demo. Log em storage/superfrete-demo.log (tokens mascarados).\n";