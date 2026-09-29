<?php
// Endpoint JSON da tela de pagamento. Serve o polling (o admin pode
// confirmar o pagamento pelo painel enquanto o cliente está na tela) e as
// ações do simulador de demonstração.
//
// Mesmo arquivo atende GET e POST porque o TCC não tem gateway: em produção
// isto seria substituído por um webhook autenticado do provedor.

declare(strict_types=1);

$base_path = '../../';

require_once __DIR__ . '/../../includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'nao autenticado']);
    exit;
}

require_once $base_path . 'database/connection.php';
require_once $base_path . 'includes/payment_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $orderId = (int) ($_GET['order'] ?? 0);
    $order = load_customer_order($pdo, $orderId, $userId);
    echo json_encode(success($order));
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'metodo nao permitido']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

if (!csrf_verify(csrf_token_from_request())) {
    http_response_code(403);
    echo json_encode(['error' => 'token csrf invalido']);
    exit;
}

$action = (string) ($payload['action'] ?? 'poll');
$orderId = (int) ($payload['order_id'] ?? 0);
$order = load_customer_order($pdo, $orderId, $userId);

// Polling é leitura pura: devolve o estado atual, aplicando a expiração para
// que o cliente veja "expirado" sem precisar de F5.
if ($action === 'poll') {
    echo json_encode(success(payment_expire_if_due($pdo, $order)));
    exit;
}

// A partir daqui, escrita. Estados terminais não voltam atrás: um pedido
// pago não pode ser recusado por um clique atrasado na tela.
if (in_array($order['payment_status'], ['paid', 'refunded', 'canceled'], true)) {
    echo json_encode(success($order));
    exit;
}

$transition = [
    'approve' => 'paid',
    'fail'    => 'failed',
    'expire'  => 'expired',
][$action] ?? null;

if ($transition === null) {
    http_response_code(400);
    echo json_encode(['error' => 'acao desconhecida']);
    exit;
}

// As transições de status são do painel, não do cliente. O polling continua
// liberado para qualquer comprador do pedido — é ele que faz a tela
// atualizar — mas approve/fail/expire exigiriam que o cliente pudesse
// confirmar o próprio pagamento, o que anula o pagamento existir.
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'somente administrador pode alterar o status do pagamento']);
    exit;
}

// Trava a linha antes de ler: duas abas clicando em "aprovar" ao mesmo tempo
// não podem gravar dois estados diferentes. Também garante que a troca de
// expired para paid só exista se o prazo ainda não tiver passado — o
// countdown da tela é cosmético, não autoridade.
$pdo->beginTransaction();
$st = $pdo->prepare('SELECT payment_status, payment_expires_at FROM e5_orders WHERE id = :id AND user_id = :u FOR UPDATE');
$st->execute([':id' => $orderId, ':u' => $userId]);
$locked = $st->fetch(PDO::FETCH_ASSOC);

if (!$locked) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['error' => 'pedido nao encontrado']);
    exit;
}

$current = (string) $locked['payment_status'];

if (in_array($current, ['paid', 'refunded', 'expired', 'failed'], true) && $current !== $transition) {
    $pdo->rollBack();
    echo json_encode(success($order));
    exit;
}

if ($transition === 'paid' && !empty($locked['payment_expires_at'])) {
    if (strtotime((string) $locked['payment_expires_at']) <= time()) {
        $pdo->prepare("UPDATE e5_orders SET payment_status = 'expired' WHERE id = :id")
            ->execute([':id' => $orderId]);
        $pdo->commit();
        $order = payment_expire_if_due($pdo, $order);
        echo json_encode(success($order));
        exit;
    }
}

$pdo->prepare('UPDATE e5_orders SET payment_status = :s WHERE id = :id')
    ->execute([':s' => $transition, ':id' => $orderId]);
$pdo->commit();

// Relê o pedido: $order foi carregado antes da escrita, então devolvê-lo
// aqui responderia "pending" logo após ter gravado "paid" — o polling
// compararia o status novo contra o antigo e recarregaria a tela sem parar.
$fresh = load_customer_order($pdo, $orderId, $userId);
echo json_encode(success(payment_expire_if_due($pdo, $fresh)));

function success(array $order): array
{
    return [
        'ok'               => true,
        'order_id'         => (int) $order['id'],
        'status'           => (string) $order['status'],
        'payment_status'   => (string) $order['payment_status'],
        'payment_method'   => (string) $order['payment_method'],
        'payment_expires_at' => $order['payment_expires_at'] ?? null,
    ];
}

function load_customer_order(PDO $pdo, int $orderId, int $userId): array
{
    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'pedido invalido']);
        exit;
    }

    $st = $pdo->prepare(
        'SELECT id, user_id, status, payment_status, payment_method, payment_expires_at
         FROM e5_orders WHERE id = :id AND user_id = :u LIMIT 1'
    );
    $st->execute([':id' => $orderId, ':u' => $userId]);
    $order = $st->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'pedido nao encontrado']);
        exit;
    }

    return $order;
}
