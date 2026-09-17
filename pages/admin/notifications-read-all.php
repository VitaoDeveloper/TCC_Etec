<?php
// Marca todas as notificações do sino como lidas e volta para a página de origem.
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    adminNotificationsMarkAllRead($pdo);
    $_SESSION['admin_message'] = 'Todas as notificações foram marcadas como lidas.';
}

// Volta para a mesma origem quando o Referer for do próprio host.
$redirect = 'notifications.php';
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($referer !== '' && $host !== '' && strpos($referer, '//' . $host) !== false) {
    $redirect = $referer;
}

header('Location: ' . $redirect);
exit;
