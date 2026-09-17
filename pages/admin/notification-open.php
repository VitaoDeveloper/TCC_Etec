<?php
// Marca uma notificação do sino como lida e redireciona para o destino salvo.
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/admin_notifications.php';

$id = (int) ($_GET['id'] ?? 0);
$redirect = 'notifications.php';

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT url FROM e5_admin_notifications WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $stored = (string) ($stmt->fetchColumn() ?: '');
    adminNotificationsMarkRead($pdo, $id);

    // Só aceita caminho relativo interno (nunca URL absoluta ou com "..").
    if ($stored !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $stored) && strpos($stored, '..') === false) {
        $redirect = $stored;
    }
}

header('Location: ' . $redirect);
exit;
