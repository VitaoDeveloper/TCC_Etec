<?php

declare(strict_types=1);

/**
 * API de preferências de aviso.
 *
 * GET  api/account/notifications.php
 * POST api/account/notifications.php  -> notify_email, notify_whatsapp
 *
 * Os dois campos são booleanos que chegam como "1"/"0": o form da tela
 * usa checkboxes, e JSON não tem checkbox — o fetch manda o valor
 * explícito em vez de depender de "presente = ligado".
 */

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/api_response.php';
require_once __DIR__ . '/../../database/connection.php';

api_require_method('GET', 'POST');
api_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_require_csrf();
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT notify_email, notify_whatsapp FROM e5_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $prefs = $stmt->fetch();

    if (!$prefs) {
        api_error('Usuário não encontrado.', 404);
    }

    api_ok([
        'notify_email'    => (int) $prefs['notify_email'] === 1,
        'notify_whatsapp' => (int) $prefs['notify_whatsapp'] === 1,
    ], '');
}

$body = api_body();

$notifyEmail    = in_array($body['notify_email'] ?? null, [1, '1', true, 'true', 'on'], true) ? 1 : 0;
$notifyWhatsapp = in_array($body['notify_whatsapp'] ?? null, [1, '1', true, 'true', 'on'], true) ? 1 : 0;

$pdo->prepare('UPDATE e5_users SET notify_email = :email, notify_whatsapp = :whatsapp, updated_at = NOW() WHERE id = :id')
    ->execute([
        ':email'    => $notifyEmail,
        ':whatsapp' => $notifyWhatsapp,
        ':id'       => $userId,
    ]);

api_ok([
    'notify_email'    => $notifyEmail === 1,
    'notify_whatsapp' => $notifyWhatsapp === 1,
], 'Preferências de aviso atualizadas.');