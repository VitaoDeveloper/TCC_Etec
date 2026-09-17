<?php
// =============================================================================
// Fila de notificações transacionais (e5_notifications / e5_notifications_log).
//
// Fluxo: um gatilho (pagamento confirmado, mudança de status, cadastro...)
// chama notificationTrigger(), que renderiza o template e enfileira o e-mail em
// e5_notifications (com dedupe por evento). O worker
// (scripts/notifications-worker.php), disparado por cron, lê a fila e envia
// via SMTP (includes/mail.php), registrando cada tentativa em
// e5_notifications_log. Falhas retrocedem com backoff exponencial até
// max_attempts, quando viram 'failed'.
//
// WhatsApp: canal previsto no schema, porém DESATIVADO nesta versão (sem
// provedor configurado). Quando a preferência do usuário pede WhatsApp, a
// notificação é enfileirada como 'skipped' para ficar rastreável.
// =============================================================================

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/status_labels.php';

if (!defined('NOTIF_WHATSAPP_ENABLED')) {
    define('NOTIF_WHATSAPP_ENABLED', false);
}

// -----------------------------------------------------------------------------
// Infra
// -----------------------------------------------------------------------------

function notificationDb(?PDO $pdo = null): PDO
{
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }
    include_once dirname(__DIR__) . '/database/connection.php';
    return $GLOBALS['pdo'];
}

function notificationEmailEnabled(): bool
{
    return (string) store_config('notif_email_enabled') === '1';
}

function notificationBaseUrl(): string
{
    return app_base_url();
}

function notificationOrderUrl(int $orderId): string
{
    return notificationBaseUrl() . '/pages/auth/order-detail.php?id=' . $orderId;
}

function notificationPaymentUrl(int $orderId): string
{
    return notificationBaseUrl() . '/pages/cart/payment.php?id=' . $orderId;
}

function notificationMoney(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function notificationOrderNumber(int $orderId): string
{
    return '#' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
}

// -----------------------------------------------------------------------------
// Contexto e templates
// -----------------------------------------------------------------------------

// Carrega pedido + itens + preferências do cliente para montar o e-mail.
function notificationOrderContext(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT o.*, u.name AS user_name, u.email AS user_email,
                u.notify_email AS user_notify_email, u.notify_whatsapp AS user_notify_whatsapp
           FROM e5_orders o
          INNER JOIN e5_users u ON u.id = o.user_id
          WHERE o.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return null;
    }

    $itemsStmt = $pdo->prepare(
        'SELECT oi.quantity, oi.unit_price, p.name
           FROM e5_order_items oi
          INNER JOIN e5_products p ON p.id = oi.product_id
          WHERE oi.order_id = :oid'
    );
    $itemsStmt->execute([':oid' => $orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'order'  => $order,
        'items'  => $items,
        'number' => notificationOrderNumber($orderId),
        'total'  => notificationMoney((float) $order['total']),
        'name'   => (string) $order['user_name'],
    ];
}

// Casca HTML compartilhada por todos os e-mails (estilos inline por
// compatibilidade com clientes de e-mail).
function notificationLayout(string $heading, string $intro, string $rowsHtml, string $ctaUrl = '', string $ctaLabel = '', string $footnote = ''): string
{
    $store = htmlspecialchars((string) store_config('store_name'), ENT_QUOTES, 'UTF-8');
    $gold = '#d4af37';
    $cta = '';
    if ($ctaUrl !== '' && $ctaLabel !== '') {
        $cta = '<p style="text-align:center;margin:28px 0;">'
            . '<a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="background:' . $gold . ';color:#1a1a1a;text-decoration:none;font-weight:700;'
            . 'padding:14px 28px;border-radius:6px;display:inline-block;">'
            . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') . '</a></p>';
    }

    return '<!DOCTYPE html><html lang="pt-BR"><body style="margin:0;padding:0;background:#1a1a1a;">'
        . '<div style="max-width:600px;margin:0 auto;padding:32px 16px;font-family:Arial,Helvetica,sans-serif;color:#f0f0f0;">'
        . '<div style="background:#222;border:1px solid #333;border-radius:12px;overflow:hidden;">'
        . '<div style="background:#1a1a1a;padding:24px 32px;border-bottom:2px solid ' . $gold . ';text-align:center;">'
        . '<span style="font-size:22px;font-weight:700;color:' . $gold . ';letter-spacing:1px;">' . $store . '</span>'
        . '</div>'
        . '<div style="padding:32px;">'
        . '<h2 style="margin:0 0 16px;color:#ffffff;font-size:20px;">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<p style="margin:0 0 20px;color:#a8a8a8;line-height:1.6;">' . $intro . '</p>'
        . $rowsHtml
        . $cta
        . ($footnote !== '' ? '<p style="margin:20px 0 0;color:#7d7d7d;font-size:13px;line-height:1.5;">' . $footnote . '</p>' : '')
        . '</div>'
        . '<div style="background:#1a1a1a;padding:20px 32px;text-align:center;color:#7d7d7d;font-size:12px;">'
        . htmlspecialchars((string) store_config('store_address'), ENT_QUOTES, 'UTF-8') . '<br>'
        . htmlspecialchars((string) store_config('store_email'), ENT_QUOTES, 'UTF-8') . ' · '
        . htmlspecialchars((string) store_config('store_phone'), ENT_QUOTES, 'UTF-8')
        . '<br>Este é um e-mail automático, não responda diretamente.</div>'
        . '</div></div></body></html>';
}

// Tabela de detalhes do pedido usada pelos templates (linhas extras opcionais).
function notificationOrderRows(array $ctx, array $extraRows = []): string
{
    $statusLabels = is_array($GLOBALS['statusLabels'] ?? null) ? $GLOBALS['statusLabels'] : [];
    $paymentStatusLabels = is_array($GLOBALS['paymentStatusLabels'] ?? null) ? $GLOBALS['paymentStatusLabels'] : [];

    $order = $ctx['order'];
    $status = $statusLabels[$order['status']]['label'] ?? (string) $order['status'];
    $pay = $paymentStatusLabels[$order['payment_status']]['label'] ?? (string) $order['payment_status'];
    $itemsCount = 0;
    foreach ($ctx['items'] as $it) {
        $itemsCount += (int) $it['quantity'];
    }

    $rows = [
        'Pedido'            => $ctx['number'],
        'Status'            => $status,
        'Pagamento'         => $pay,
        'Itens'             => (string) $itemsCount,
        'Total'             => $ctx['total'],
    ];
    foreach ($extraRows as $label => $value) {
        if ($value !== '' && $value !== null) {
            $rows[$label] = (string) $value;
        }
    }

    $html = '<table style="width:100%;border-collapse:collapse;margin:0 0 8px;">';
    foreach ($rows as $label => $value) {
        $html .= '<tr>'
            . '<td style="padding:8px 0;color:#7d7d7d;font-size:14px;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px 0;color:#f0f0f0;font-size:14px;text-align:right;font-weight:600;">'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    }
    return $html . '</table>';
}

// Renderiza assunto + corpo de um evento. Retorna null para template inexistente.
function notificationRender(string $template, array $ctx, array $extra = []): ?array
{
    $order = $ctx['order'] ?? [];
    $name = htmlspecialchars((string) ($ctx['name'] ?? ($order['user_name'] ?? 'cliente')), ENT_QUOTES, 'UTF-8');
    $number = (string) ($ctx['number'] ?? '');
    $orderId = (int) ($order['id'] ?? 0);
    $url = $orderId > 0 ? notificationOrderUrl($orderId) : notificationBaseUrl();

    switch ($template) {
        case 'order_created':
            $payLabel = ['pix' => 'Pix', 'boleto' => 'Boleto', 'credit' => 'Cartão de crédito', 'delivery' => 'Pagar na entrega'][(string) ($order['payment_method'] ?? '')] ?? (string) ($order['payment_method'] ?? '');
            return [
                'subject' => 'Recebemos seu pedido ' . $number . ' — Royal Tech',
                'body' => notificationLayout(
                    'Recebemos seu pedido!',
                    'Olá ' . $name . ', seu pedido ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . ' foi registrado e está aguardando a confirmação do pagamento.',
                    notificationOrderRows($ctx, ['Forma de pagamento' => $payLabel]),
                    $url,
                    'Acompanhar pedido',
                    'Você receberá um novo e-mail assim que o pagamento for confirmado.'
                ),
            ];

        case 'order_paid':
            $shipping = (float) ($order['shipping_cost'] ?? 0) > 0 ? notificationMoney((float) $order['shipping_cost']) : 'Frete grátis';
            return [
                'subject' => 'Pagamento confirmado — pedido ' . $number,
                'body' => notificationLayout(
                    'Pagamento confirmado!',
                    'Boa notícia, ' . $name . '! O pagamento do pedido ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . ' foi confirmado e já estamos preparando o envio.',
                    notificationOrderRows($ctx, ['Frete' => $shipping]),
                    $url,
                    'Ver pedido',
                    'Assim que o pedido for postado, enviaremos o código de rastreio.'
                ),
            ];

        case 'order_failed':
            return [
                'subject' => 'Pagamento não aprovado — pedido ' . $number,
                'body' => notificationLayout(
                    'Não conseguimos aprovar seu pagamento',
                    'Olá ' . $name . ', o pagamento do pedido ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . ' não foi aprovado e o estoque foi liberado. Você pode tentar novamente por outro meio.',
                    notificationOrderRows($ctx),
                    notificationPaymentUrl($orderId),
                    'Tentar novamente'
                ),
            ];

        case 'order_expired':
            return [
                'subject' => 'Cobrança expirada — pedido ' . $number,
                'body' => notificationLayout(
                    'Sua cobrança expirou',
                    'Olá ' . $name . ', o prazo de pagamento do pedido ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . ' expirou e reserva foi liberada. Você pode gerar uma nova cobrança quando quiser.',
                    notificationOrderRows($ctx),
                    notificationPaymentUrl($orderId),
                    'Gerar nova cobrança'
                ),
            ];

        case 'order_canceled':
            $reason = trim((string) ($extra['reason'] ?? ($order['refund_reason'] ?? '')));
            return [
                'subject' => 'Pedido cancelado — ' . $number,
                'body' => notificationLayout(
                    'Seu pedido foi cancelado',
                    'Olá ' . $name . ', o pedido ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . ' foi cancelado e o estoque reservado foi devolvido.',
                    notificationOrderRows($ctx, ['Motivo' => $reason]),
                    $url,
                    'Ver pedido'
                ),
            ];

        case 'order_refunded':
            $reason = trim((string) ($extra['reason'] ?? ($order['refund_reason'] ?? '')));
            return [
                'subject' => 'Pagamento estornado — pedido ' . $number,
                'body' => notificationLayout(
                    'Pagamento estornado',
                    'Olá ' . $name . ', o pagamento do pedido ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . ' foi estornado. O valor retornará conforme os prazos da sua forma de pagamento.',
                    notificationOrderRows($ctx, ['Motivo' => $reason]),
                    $url,
                    'Ver pedido'
                ),
            ];

        case 'order_shipped':
            $tracking = (string) ($extra['tracking'] ?? ($order['tracking_code'] ?? ''));
            $carrier = (string) ($extra['carrier'] ?? '');
            $rows = ['Código de rastreio' => $tracking, 'Transportadora' => $carrier];
            return [
                'subject' => 'Seu pedido ' . $number . ' foi enviado',
                'body' => notificationLayout(
                    'Seu pedido saiu para entrega!',
                    'Olá ' . $name . ', o pedido ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . ' foi postado e já está a caminho.',
                    notificationOrderRows($ctx, $rows),
                    $url,
                    'Acompanhar entrega'
                ),
            ];

        case 'order_delivered':
            return [
                'subject' => 'Pedido ' . $number . ' entregue',
                'body' => notificationLayout(
                    'Pedido entregue!',
                    'Olá ' . $name . ', o pedido ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . ' foi entregue. Esperamos que você aproveite sua compra!',
                    notificationOrderRows($ctx),
                    $url . '#avaliar',
                    'Avaliar produtos'
                ),
            ];

        case 'order_reminder':
            $expires = (string) ($order['payment_expires_at'] ?? '');
            $expiresLabel = $expires !== '' ? date('d/m/Y H:i', strtotime($expires)) : '';
            $payLabel = ['pix' => 'Pix', 'boleto' => 'Boleto'][(string) ($order['payment_method'] ?? '')] ?? '';
            return [
                'subject' => 'Falta pouco para o seu pedido ' . $number,
                'body' => notificationLayout(
                    'Seu pagamento ainda está pendente',
                    'Olá ' . $name . ', notamos que o pagamento do pedido ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . ' ainda não foi confirmado.',
                    notificationOrderRows($ctx, ['Vence em' => $expiresLabel, 'Forma' => $payLabel]),
                    notificationPaymentUrl($orderId),
                    'Concluir pagamento',
                    'Se você já pagou, desconsidere este aviso — a confirmação pode levar alguns minutos.'
                ),
            ];

        case 'user_welcome':
            return [
                'subject' => 'Bem-vindo(a) à Royal Tech',
                'body' => notificationLayout(
                    'Bem-vindo(a), ' . $name . '!',
                    'Sua conta na Royal Tech foi criada com sucesso. Agora você pode comprar com segurança, acompanhar pedidos e salvar seus produtos favoritos.',
                    '',
                    notificationBaseUrl() . '/pages/products/products.php',
                    'Começar a comprar'
                ),
            ];

        case 'contact_received':
            $subjectLine = htmlspecialchars((string) ($extra['subject'] ?? 'seu contato'), ENT_QUOTES, 'UTF-8');
            return [
                'subject' => 'Recebemos sua mensagem — Royal Tech',
                'body' => notificationLayout(
                    'Recebemos sua mensagem',
                    'Olá ' . $name . ', obrigado por entrar em contato sobre <strong>' . $subjectLine . '</strong>. Nossa equipe responderá o mais breve possível.',
                    '',
                    notificationBaseUrl() . '/pages/products/contact.php',
                    'Voltar ao site'
                ),
            ];
    }

    return null;
}

// -----------------------------------------------------------------------------
// Enfileiramento
// -----------------------------------------------------------------------------

function notificationEnqueue(PDO $pdo, array $data): ?int
{
    $dedupe = (string) ($data['dedupe_key'] ?? '');
    if ($dedupe === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO e5_notifications
                (order_id, user_id, event_key, channel, recipient, template, subject, body,
                 status, attempts, max_attempts, last_error, dedupe_key, available_at)
             VALUES
                (:order_id, :user_id, :event_key, :channel, :recipient, :template, :subject, :body,
                 :status, 0, :max_attempts, :last_error, :dedupe_key, :available_at)'
        );
        $stmt->execute([
            ':order_id'     => $data['order_id'] ?? null,
            ':user_id'      => $data['user_id'] ?? null,
            ':event_key'    => (string) $data['event_key'],
            ':channel'      => (string) ($data['channel'] ?? 'email'),
            ':recipient'    => (string) $data['recipient'],
            ':template'     => $data['template'] ?? null,
            ':subject'      => $data['subject'] ?? null,
            ':body'         => $data['body'] ?? null,
            ':status'       => (string) ($data['status'] ?? 'pending'),
            ':max_attempts' => (int) ($data['max_attempts'] ?? 3),
            ':last_error'   => $data['last_error'] ?? null,
            ':dedupe_key'   => $dedupe,
            ':available_at' => (string) ($data['available_at'] ?? date('Y-m-d H:i:s')),
        ]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return null; // dedupe: já enfileirado
        }
        throw $e;
    }
}

// Gatilho de evento ligado a um pedido. Best-effort: nunca lança (uma falha de
// notificação jamais pode derrubar pagamento, envio ou webhook).
function notificationTrigger(string $eventKey, int $orderId, array $extra = [], ?PDO $pdo = null): array
{
    try {
        return notificationTriggerInternal($eventKey, $orderId, $extra, $pdo);
    } catch (Throwable $e) {
        error_log('notificationTrigger: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Falha ao enfileirar notificação.'];
    }
}

function notificationTriggerInternal(string $eventKey, int $orderId, array $extra = [], ?PDO $pdo = null): array
{
    $pdo = notificationDb($pdo);

    if (!notificationEmailEnabled()) {
        return ['ok' => false, 'message' => 'Notificações por e-mail desativadas.'];
    }

    $ctx = notificationOrderContext($pdo, $orderId);
    if (!$ctx) {
        return ['ok' => false, 'message' => 'Pedido não encontrado.'];
    }
    $order = $ctx['order'];

    $rendered = notificationRender($eventKey, $ctx, $extra);
    if ($rendered === null) {
        return ['ok' => false, 'message' => 'Template desconhecido: ' . $eventKey];
    }

    $recipient = trim((string) $order['user_email']);
    $userId = (int) $order['user_id'];
    $suffix = (string) ($extra['suffix'] ?? '');

    // Preferência do cliente: registra como 'skipped' para manter rastreável.
    if ((int) ($order['user_notify_email'] ?? 1) === 0) {
        notificationEnqueue($pdo, [
            'order_id' => $orderId,
            'user_id' => $userId,
            'event_key' => $eventKey,
            'channel' => 'email',
            'recipient' => $recipient !== '' ? $recipient : '(sem e-mail)',
            'template' => $eventKey,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'status' => 'skipped',
            'last_error' => 'Cliente desativou notificações por e-mail.',
            'dedupe_key' => 'order:' . $orderId . ':' . $eventKey . ($suffix !== '' ? ':' . $suffix : ''),
        ]);
        return ['ok' => true, 'id' => null, 'message' => 'Cliente optou por não receber e-mail.'];
    }

    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Pedido sem e-mail de destino válido.'];
    }

    $id = notificationEnqueue($pdo, [
        'order_id' => $orderId,
        'user_id' => $userId,
        'event_key' => $eventKey,
        'channel' => 'email',
        'recipient' => $recipient,
        'template' => $eventKey,
        'subject' => $rendered['subject'],
        'body' => $rendered['body'],
        'dedupe_key' => 'order:' . $orderId . ':' . $eventKey . ($suffix !== '' ? ':' . $suffix : ''),
    ]);

    // WhatsApp (desativado): mantém rastreabilidade da preferência do cliente.
    if ((int) ($order['user_notify_whatsapp'] ?? 0) === 1) {
        notificationEnqueue($pdo, [
            'order_id' => $orderId,
            'user_id' => $userId,
            'event_key' => $eventKey,
            'channel' => 'whatsapp',
            'recipient' => '(whatsapp)',
            'template' => $eventKey,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'status' => 'skipped',
            'last_error' => 'Canal WhatsApp desativado nesta versão (sem provedor configurado).',
            'dedupe_key' => 'order:' . $orderId . ':' . $eventKey . ':whatsapp' . ($suffix !== '' ? ':' . $suffix : ''),
        ]);
    }

    return [
        'ok' => true,
        'id' => $id,
        'message' => $id ? 'Notificação enfileirada.' : 'Notificação já estava enfileirada.',
    ];
}

// Enfileira o e-mail de boas-vindas para um usuário recém-cadastrado.
function notificationWelcome(int $userId, ?PDO $pdo = null): array
{
    try {
        return notificationWelcomeInternal($userId, $pdo);
    } catch (Throwable $e) {
        error_log('notificationWelcome: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Falha ao enfileirar boas-vindas.'];
    }
}

function notificationWelcomeInternal(int $userId, ?PDO $pdo = null): array
{
    $pdo = notificationDb($pdo);
    if (!notificationEmailEnabled()) {
        return ['ok' => false, 'message' => 'Notificações por e-mail desativadas.'];
    }

    $stmt = $pdo->prepare('SELECT id, name, email, notify_email FROM e5_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['ok' => false, 'message' => 'Usuário não encontrado.'];
    }

    $ctx = ['name' => (string) $user['name']];
    $rendered = notificationRender('user_welcome', $ctx, []);
    if ($rendered === null) {
        return ['ok' => false, 'message' => 'Template indisponível.'];
    }

    $email = trim((string) $user['email']);
    $status = (int) $user['notify_email'] === 1 ? 'pending' : 'skipped';

    $id = notificationEnqueue($pdo, [
        'user_id' => (int) $user['id'],
        'event_key' => 'user_welcome',
        'channel' => 'email',
        'recipient' => $email !== '' ? $email : '(sem e-mail)',
        'template' => 'user_welcome',
        'subject' => $rendered['subject'],
        'body' => $rendered['body'],
        'status' => $status,
        'last_error' => $status === 'skipped' ? 'Cliente desativou notificações por e-mail.' : null,
        'dedupe_key' => 'user:' . (int) $user['id'] . ':user_welcome',
    ]);

    return ['ok' => true, 'id' => $id, 'message' => 'Boas-vindas enfileiradas.'];
}

// Auto-resposta para quem enviou o formulário de contato.
function notificationContactAutoReply(array $contact, ?PDO $pdo = null): array
{
    try {
        return notificationContactAutoReplyInternal($contact, $pdo);
    } catch (Throwable $e) {
        error_log('notificationContactAutoReply: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Falha ao enfileirar auto-resposta.'];
    }
}

function notificationContactAutoReplyInternal(array $contact, ?PDO $pdo = null): array
{
    $pdo = notificationDb($pdo);
    if (!notificationEmailEnabled()) {
        return ['ok' => false, 'message' => 'Notificações por e-mail desativadas.'];
    }

    $email = trim((string) ($contact['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'E-mail de contato inválido.'];
    }

    $ctx = ['name' => (string) ($contact['name'] ?? 'cliente')];
    $rendered = notificationRender('contact_received', $ctx, ['subject' => (string) ($contact['subject'] ?? '')]);
    if ($rendered === null) {
        return ['ok' => false, 'message' => 'Template indisponível.'];
    }

    $dedupe = 'contact:' . (int) ($contact['id'] ?? 0) . ':contact_received';
    if (empty($contact['id'])) {
        $dedupe = 'contact:' . md5($email . '|' . (string) ($contact['subject'] ?? '') . '|' . date('Y-m-d H:i'));
    }

    $id = notificationEnqueue($pdo, [
        'event_key' => 'contact_received',
        'channel' => 'email',
        'recipient' => $email,
        'template' => 'contact_received',
        'subject' => $rendered['subject'],
        'body' => $rendered['body'],
        'dedupe_key' => $dedupe,
    ]);

    return ['ok' => true, 'id' => $id, 'message' => 'Auto-resposta enfileirada.'];
}

// -----------------------------------------------------------------------------
// Processamento da fila (worker)
// -----------------------------------------------------------------------------

function notificationLog(PDO $pdo, int $notificationId, string $channel, string $status, string $provider, string $detail): void
{
    try {
        $isError = $status !== 'sent';
        $pdo->prepare(
            'INSERT INTO e5_notifications_log (notification_id, channel, status, provider, response, error)
             VALUES (:n, :c, :s, :p, :r, :e)'
        )->execute([
            ':n' => $notificationId,
            ':c' => $channel,
            ':s' => $status,
            ':p' => $provider,
            ':r' => $isError ? null : $detail,
            ':e' => $isError ? $detail : null,
        ]);
    } catch (Throwable $e) {
        error_log('notificationLog: ' . $e->getMessage());
    }
}

// Envia uma única notificação. Retorna 'sent' | 'failed' | 'skipped' | 'retry'.
function notificationDispatch(PDO $pdo, array $n): string
{
    $id = (int) $n['id'];
    $channel = (string) $n['channel'];

    if ($channel === 'whatsapp') {
        $error = 'Canal WhatsApp desativado nesta versão (sem provedor configurado).';
        $pdo->prepare("UPDATE e5_notifications SET status = 'skipped', last_error = :e WHERE id = :id")
            ->execute([':e' => $error, ':id' => $id]);
        notificationLog($pdo, $id, $channel, 'skipped', 'none', $error);
        return 'skipped';
    }

    $ok = false;
    $error = '';
    try {
        $ok = sendMail((string) $n['recipient'], (string) $n['subject'], (string) $n['body']);
        if (!$ok) {
            $error = 'Falha no envio SMTP.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    if ($ok) {
        $pdo->prepare("UPDATE e5_notifications SET status = 'sent', attempts = attempts + 1, sent_at = NOW(), last_error = NULL WHERE id = :id")
            ->execute([':id' => $id]);
        notificationLog($pdo, $id, $channel, 'sent', 'smtp', 'OK');
        return 'sent';
    }

    $attempts = (int) $n['attempts'] + 1;
    $maxAttempts = max(1, (int) $n['max_attempts']);

    if ($attempts >= $maxAttempts) {
        $pdo->prepare("UPDATE e5_notifications SET status = 'failed', attempts = :a, last_error = :e WHERE id = :id")
            ->execute([':a' => $attempts, ':e' => $error, ':id' => $id]);
        notificationLog($pdo, $id, $channel, 'failed', 'smtp', $error);
        return 'failed';
    }

    // Backoff exponencial: 60s, 120s, 240s... (limitado a 1h).
    $delay = min(3600, 60 * (2 ** ($attempts - 1)));
    $availableAt = date('Y-m-d H:i:s', time() + $delay);
    $pdo->prepare("UPDATE e5_notifications SET status = 'pending', attempts = :a, last_error = :e, available_at = :t WHERE id = :id")
        ->execute([':a' => $attempts, ':e' => $error, ':t' => $availableAt, ':id' => $id]);
    notificationLog($pdo, $id, $channel, 'failed', 'smtp', $error);
    return 'retry';
}

// Processa a fila de pendentes prontos para envio.
function notificationProcessPending(?PDO $pdo = null, int $limit = 50): array
{
    $pdo = notificationDb($pdo);
    $limit = max(1, min(500, $limit));

    $stmt = $pdo->prepare(
        "SELECT * FROM e5_notifications
          WHERE status = 'pending'
            AND (available_at IS NULL OR available_at <= NOW())
            AND attempts < max_attempts
          ORDER BY id ASC
          LIMIT $limit"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $summary = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'retry' => 0, 'skipped' => 0];
    foreach ($rows as $row) {
        $result = notificationDispatch($pdo, $row);
        $summary['processed']++;
        $summary[$result] = ($summary[$result] ?? 0) + 1;
    }
    return $summary;
}

// Enfileira lembretes de pagamento (Pix/boleto ainda pendentes e dentro do prazo).
// Dedupe diário: no máximo um lembrete por pedido por dia.
function notificationEnqueuePaymentReminders(?PDO $pdo = null, int $hoursBefore = 24): int
{
    try {
        return notificationEnqueuePaymentRemindersInternal($pdo, $hoursBefore);
    } catch (Throwable $e) {
        error_log('notificationEnqueuePaymentReminders: ' . $e->getMessage());
        return 0;
    }
}

function notificationEnqueuePaymentRemindersInternal(?PDO $pdo = null, int $hoursBefore = 24): int
{
    $pdo = notificationDb($pdo);
    if (!notificationEmailEnabled()) {
        return 0;
    }
    $hoursBefore = max(1, $hoursBefore);

    $stmt = $pdo->prepare(
        "SELECT o.id
           FROM e5_orders o
          INNER JOIN e5_users u ON u.id = o.user_id
          WHERE o.status = 'pending'
            AND o.payment_status IN ('pending','processing')
            AND o.payment_expires_at IS NOT NULL
            AND o.payment_expires_at > NOW()
            AND o.payment_expires_at <= DATE_ADD(NOW(), INTERVAL :h HOUR)
            AND o.payment_method IN ('pix','boleto')
            AND u.notify_email = 1
            AND u.email <> ''
          ORDER BY o.id ASC
          LIMIT 100"
    );
    $stmt->execute([':h' => $hoursBefore]);

    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $orderId = (int) $row['id'];
        $ctx = notificationOrderContext($pdo, $orderId);
        if (!$ctx) {
            continue;
        }
        $rendered = notificationRender('order_reminder', $ctx, []);
        if ($rendered === null) {
            continue;
        }
        $id = notificationEnqueue($pdo, [
            'order_id' => $orderId,
            'user_id' => (int) $ctx['order']['user_id'],
            'event_key' => 'order_reminder',
            'channel' => 'email',
            'recipient' => (string) $ctx['order']['user_email'],
            'template' => 'order_reminder',
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'dedupe_key' => 'order:' . $orderId . ':order_reminder:' . date('Y-m-d'),
        ]);
        if ($id !== null) {
            $count++;
        }
    }
    return $count;
}

// -----------------------------------------------------------------------------
// Consulta (painel)
// -----------------------------------------------------------------------------

function notificationQueueStats(?PDO $pdo = null): array
{
    $pdo = notificationDb($pdo);
    $stats = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
    try {
        $rows = $pdo->query(
            'SELECT status, COUNT(*) AS total FROM e5_notifications GROUP BY status'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($rows as $status => $total) {
            $stats[(string) $status] = (int) $total;
        }
    } catch (Throwable $e) {
        // Tabela ausente: mantém zeros.
    }
    return $stats;
}

function notificationQueueRecent(?PDO $pdo = null, int $limit = 15): array
{
    $pdo = notificationDb($pdo);
    $limit = max(1, min(100, $limit));
    try {
        return $pdo->query(
            "SELECT n.id, n.event_key, n.channel, n.recipient, n.subject, n.status,
                    n.attempts, n.last_error, n.created_at, n.sent_at, o.id AS order_id
               FROM e5_notifications n
               LEFT JOIN e5_orders o ON o.id = n.order_id
              ORDER BY n.id DESC
              LIMIT $limit"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
