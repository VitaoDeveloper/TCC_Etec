<?php

declare(strict_types=1);

/**
 * API de ações do pedido, reutilizando a máquina de estados.
 *
 * POST api/account/orders.php action=cancel -> cancela pedido próprio
 * POST api/account/orders.php action=resend -> reenvia comprovante
 *
 * O cancelamento NÃO faz UPDATE direto (como o form legado de
 * order-detail.php fazia): passa por order_apply_status(), que cuida do
 * pagamento em conjunto (paid -> refunded, pendente -> canceled), do
 * estorno de estoque e da trilha e5_order_history. Se havia etiqueta
 * na transportadora, order_repo_cancel_label() a cancela — e a falha
 * externa não derruba o cancelamento.
 */

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/api_response.php';
require_once __DIR__ . '/../../includes/order_state.php';
require_once __DIR__ . '/../../includes/order_repo.php';
require_once __DIR__ . '/../../database/connection.php';

api_require_method('POST');
api_require_csrf();
$userId = api_require_login();

$action = (string) ($_POST['action'] ?? '');
$orderId = (int) ($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    api_error('Pedido inválido.', 400, ['order_id' => 'Pedido inválido.']);
}

$order = order_repo_find_authorized($pdo, $orderId, $userId, false);

if ($order === null) {
    // Idêntico para "não existe" e "de outro usuário": não vazamos
    // quais pedidos existem de verdade.
    api_error('Pedido não encontrado.', 404);
}

// ---------------------------------------------------------------------
//  Cancelar
// ---------------------------------------------------------------------
if ($action === 'cancel') {
    if (!order_can_cancel($order)) {
        api_error('Este pedido não pode mais ser cancelado.', 409);
    }

    $result = order_apply_status($pdo, $orderId, 'canceled', ['actor' => 'customer']);

    if (!$result['ok']) {
        api_error((string) $result['error'], 409);
    }

    // Etiqueta da transportadora, se existir. O pedido já está
    // cancelado; o erro da SuperFrete fica registrado e o cliente não
    // vê falha onde a ação principal já foi concluída.
    order_repo_cancel_label($pdo, $orderId);

    $fresh = order_repo_find($pdo, $orderId);

    api_ok([
        'order'   => $fresh,
        'message' => 'Pedido cancelado. O estoque foi devolvido.',
    ], 'Pedido cancelado com sucesso.');
}

// ---------------------------------------------------------------------
//  Reenviar comprovante
// ---------------------------------------------------------------------
if ($action === 'resend') {
    if (!function_exists('gerarComprovante')) {
        require_once __DIR__ . '/../../includes/comprovante_functions.php';
    }

    // Rate limit: email e o vetor de spam. Mesma política do
    // password_change — jamais deixar um ponto de envio ilimitado.
    api_rate_limit('comprovante_resend', 5, 10);

    $result = gerarComprovante($orderId);

    if (!$result['success']) {
        api_error('Não foi possível gerar o comprovante. Tente novamente em instantes.', 500);
    }

    $email = $pdo->prepare('SELECT u.email FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id WHERE o.id = :id');
    $email->execute([':id' => $orderId]);
    $to = $email->fetchColumn();

    $sent = is_string($to) && $to !== ''
        ? sendComprovanteEmail($orderId, $to, $result['filename'])
        : false;

    if (!$sent) {
        api_error('O comprovante foi gerado, mas não foi possível enviar o e-mail agora. Tente novamente.', 502);
    }

    api_ok(null, 'Comprovante enviado para o seu e-mail.');
}

api_error('Ação não reconhecida.', 400, ['action' => 'Valores válidos: cancel, resend.']);