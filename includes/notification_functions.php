<?php

declare(strict_types=1);

/**
 * Fila de notificações por evento de pedido.
 *
 * A ideia é não mandar e-mail dentro da requisição que confirma o
 * pagamento ou cria a etiqueta. A requisição grava a intenção em
 * e5_notifications e alguém drena a fila; se o SMTP cair, a notificação
 * fica 'pending' com a contagem de tentativas e o painel mostra o erro,
 * em vez de o cliente simplesmente não receber nada sem ninguém saber.
 *
 * Honestidade de canal: sem provedor de WhatsApp configurado, uma
 * notificação de WhatsApp NUNCA entra como 'sent'. Ela fica 'skipped' com
 * o link wa.me na mensagem, para alguém enviar na mão. Marcar como enviada
 * seria afirmar uma entrega que a loja não fez.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';

/** Títulos e textos por evento. Tudo em português, pronto para o cliente. */
function notification_templates(string $event, array $ctx): array
{
    $orderRef = '#' . ($ctx['order_ref'] ?? '?');
    $name     = (string) ($ctx['customer_name'] ?? '');
    $saudacao = $name !== '' ? "Olá, {$name}!" : 'Olá!';

    return match ($event) {
        'payment_confirmed' => [
            'Assunto' => "Pagamento confirmado — pedido {$orderRef}",
            "Corpo"   => "{$saudacao}\n\nRecebemos a confirmação do pagamento do seu pedido {$orderRef}.\n"
                       . "Assim que a etiqueta de envio for gerada você recebe o código de rastreio por aqui.\n\n"
                       . "— Equipe Royal Tech",
        ],
        'shipment_created' => [
            'Assunto' => "Seu pedido {$orderRef} foi enviado",
            "Corpo"   => "{$saudacao}\n\nO pedido {$orderRef} já tem etiqueta de envio.\n"
                       . 'Serviço: ' . ($ctx['service'] ?? 'SuperFrete') . "\n"
                       . 'Rastreio: ' . ($ctx['tracking'] ?? 'ainda não informado') . "\n\n"
                       . "Acompanhe a entrega pelo código acima.\n\n— Equipe Royal Tech",
        ],
        'order_shipped' => [
            'Assunto' => "Pedido {$orderRef} a caminho",
            "Corpo"   => "{$saudacao}\n\nSeu pedido {$orderRef} saiu para entrega.\n"
                       . 'Rastreio: ' . ($ctx['tracking'] ?? 'ainda não informado') . "\n\n— Equipe Royal Tech",
        ],
        'order_delivered' => [
            'Assunto' => "Pedido {$orderRef} entregue",
            // O código entra quando existe: o cliente guarda e-mail com
            // rastreio, e o número resolve qualquer dúvida sobre a entrega.
            'Corpo'   => "{$saudacao}\n\nSeu pedido {$orderRef} foi marcado como entregue.\n"
                       . (!empty($ctx['tracking']) ? 'Rastreio: ' . $ctx['tracking'] . "\n" : '')
                       . "Se algo não chegou corretamente, responda este e-mail.\n\n— Equipe Royal Tech",
        ],
        'order_canceled' => [
            'Assunto' => "Pedido {$orderRef} cancelado",
            "Corpo"   => "{$saudacao}\n\nSeu pedido {$orderRef} foi cancelado e o valor será devolvido.\n\n— Equipe Royal Tech",
        ],
        default => [
            'Assunto' => "Atualização do pedido {$orderRef}",
            "Corpo"   => "{$saudacao}\n\nHouve uma atualização no seu pedido {$orderRef}.\n\n— Equipe Royal Tech",
        ],
    };
}

/** Link wa.me já com o texto pronto para o cliente copiar e enviar. */
function notification_whatsapp_link(?string $phone, string $body): string
{
    $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

    if ($digits === '') {
        return '';
    }

    // 10 dígitos = DDD + número, sem o código do país: só o 55 falta.
    if (strlen($digits) === 10) {
        $digits = '55' . $digits;
    } elseif (strlen($digits) === 11) {
        $digits = '55' . $digits;
    } elseif (strlen($digits) !== 12 && strlen($digits) !== 13) {
        // Qualquer outro tamanho é número incompleto ou lixo. Gerar link assim
        // manda a mensagem para o contato errado.
        return '';
    }

    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($body);
}

/**
 * Enfileira as notificações de um evento para um pedido, respeitando as
 * preferências do cliente. Não envia nada: só grava a fila.
 */
function notification_enqueue_order_event(PDO $pdo, int $orderId, string $event, array $ctx = []): int
{
    $st = $pdo->prepare(
        'SELECT o.id, o.status, u.id AS user_id, u.name, u.email, u.phone,
                u.notify_email, u.notify_whatsapp
         FROM e5_orders o
         JOIN e5_users u ON u.id = o.user_id
         WHERE o.id = :id LIMIT 1'
    );
    $st->execute([':id' => $orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return 0;
    }

    // e5_orders não tem coluna order_code: a referência é o id com zeros à
    // esquerda, exatamente como o painel mostra no título da página.
    $ctx['order_ref']     = str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT);
    $ctx['customer_name'] = (string) $row['name'];

    $tpl   = notification_templates($event, $ctx);
    $phone = (string) ($row['phone'] ?? '');

    $ins = $pdo->prepare(
        'INSERT INTO e5_notifications
            (order_id, user_id, channel, event_type, recipient, subject, body, status, error_message)
         VALUES (:o, :u, :c, :e, :r, :s, :b, :st, :err)'
    );

    $queued = 0;

    if ((int) $row['notify_email'] === 1 && (string) $row['email'] !== '') {
        $ins->execute([
            ':o'   => $orderId,
            ':u'   => (int) $row['user_id'],
            ':c'   => 'email',
            ':e'   => $event,
            ':r'   => (string) $row['email'],
            ':s'   => $tpl['Assunto'],
            ':b'   => $tpl['Corpo'],
            ':st'  => 'pending',
            ':err' => null,
        ]);
        $queued++;
    }

    if ((int) $row['notify_whatsapp'] === 1 && $phone !== '') {
        $link = notification_whatsapp_link($phone, $tpl['Corpo']);

        $ins->execute([
            ':o'   => $orderId,
            ':u'   => (int) $row['user_id'],
            ':c'   => 'whatsapp',
            ':e'   => $event,
            ':r'   => $phone,
            ':s'   => $tpl['Assunto'],
            // A mensagem guarda o link: quem tiver o WhatsApp da loja
            // clica e a conversa já vem com o texto pronto.
            ':b'   => $link !== '' ? $tpl['Corpo'] . "\n\nAbrir no WhatsApp:\n" . $link : $tpl['Corpo'],
            ':st'  => 'skipped',
            ':err' => $link !== ''
                ? 'Sem provedor de WhatsApp configurado. Enviar pelo link manualmente.'
                : 'Telefone do cliente não é um número válido.',
        ]);
        $queued++;
    }

    return $queued;
}

/**
 * Envia as notificações de e-mail pendentes. Chamar depois de gravar na
 * fila (best effort) ou de uma rotina programada. Devolve contadores.
 *
 * @return array{sent:int,failed:int,remaining:int}
 */
function notification_drain_email(PDO $pdo, int $limit = 10): array
{
    $st = $pdo->prepare(
        "SELECT * FROM e5_notifications
         WHERE channel = 'email' AND status = 'pending' AND attempts < 3
         ORDER BY created_at ASC LIMIT $limit"
    );
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $sent = 0;
    $failed = 0;

    foreach ($rows as $n) {
        $to = (string) $n['recipient'];

        // O transporte local (MailHog, porta 1025) aceita qualquer domínio, e
        // sem esta checagem um destino inválido entra como "sent" e o cliente
        // nunca recebe nada. filter_var não garante que a caixa exista, mas
        // pega o que é erro de digitação — que é o caso comum.
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $pdo->prepare(
                "UPDATE e5_notifications
                 SET status = 'failed', attempts = attempts + 1,
                     error_message = :err
                 WHERE id = :id"
            )->execute([
                ':err' => 'Endereço de e-mail inválido: ' . mb_substr($to, 0, 160),
                ':id'  => (int) $n['id'],
            ]);
            $failed++;
            continue;
        }

        $ok = sendMail($to, (string) $n['subject'], (string) $n['body']);

        if ($ok) {
            $pdo->prepare(
                "UPDATE e5_notifications
                 SET status = 'sent', sent_at = NOW(), attempts = attempts + 1, error_message = NULL
                 WHERE id = :id"
            )->execute([':id' => (int) $n['id']]);
            $sent++;
        } else {
            $pdo->prepare(
                "UPDATE e5_notifications
                 SET status = 'failed', attempts = attempts + 1,
                     error_message = 'SMTP recusou ou não respondeu'
                 WHERE id = :id"
            )->execute([':id' => (int) $n['id']]);
            $failed++;
        }
    }

    $remaining = (int) $pdo->query(
        "SELECT COUNT(*) FROM e5_notifications WHERE status = 'pending'"
    )->fetchColumn();

    return ['sent' => $sent, 'failed' => $failed, 'remaining' => $remaining];
}

/** Últimas notificações, para o painel. */
function notification_recent(PDO $pdo, int $limit = 20): array
{
    $st = $pdo->prepare(
        "SELECT n.*, o.id AS order_id
         FROM e5_notifications n
         LEFT JOIN e5_orders o ON o.id = n.order_id
         ORDER BY n.id DESC LIMIT $limit"
    );
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
