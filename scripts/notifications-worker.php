<?php

declare(strict_types=1);

/**
 * Worker da fila de notificações transacionais (e5_notifications).
 *
 * Uso:
 *   php scripts/notifications-worker.php [--limit=50] [--no-reminders] [--dry-run]
 *
 * - Enfileira lembretes de pagamento (Pix/boleto dentro do prazo e ainda pendentes).
 * - Processa a fila de pendentes via SMTP, com backoff exponencial em falhas.
 *
 * Recomendado no crontab (a cada 5 minutos):
 *   *\/5 * * * * /opt/lampp/bin/php /opt/lampp/htdocs/TCC_Etec/scripts/notifications-worker.php >> /opt/lampp/htdocs/TCC_Etec/storage/logs/notifications-cron.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script deve ser executado via linha de comando (CLI).\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/notifications_functions.php';

$options = getopt('', ['limit::', 'no-reminders', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo "Uso: php scripts/notifications-worker.php [--limit=50] [--no-reminders] [--dry-run]\n";
    exit(0);
}

$limit = (int) ($options['limit'] ?? 50);
if ($limit <= 0) {
    $limit = 50;
}
$enqueueReminders = !isset($options['no-reminders']);
$dryRun = isset($options['dry-run']);

$stamp = date('Y-m-d H:i:s');
echo "[$stamp] Worker de notificações iniciado (limit=$limit" . ($dryRun ? ', dry-run' : '') . ").\n";

// Garante que a fila existe (banco recém-criado pode não ter as tabelas).
try {
    $GLOBALS['pdo']->query('SELECT 1 FROM e5_notifications LIMIT 1');
} catch (Throwable $e) {
    fwrite(STDERR, "Tabela e5_notifications indisponível. Rode o database.sql.\n");
    exit(1);
}

if (!notificationEmailEnabled()) {
    echo "Notificações por e-mail estão desativadas nas configurações da loja. Nada a fazer.\n";
    exit(0);
}

if ($enqueueReminders) {
    $queued = notificationEnqueuePaymentReminders($GLOBALS['pdo']);
    echo "Lembretes de pagamento enfileirados: $queued\n";
}

if ($dryRun) {
    $pending = notificationQueueStats($GLOBALS['pdo'])['pending'] ?? 0;
    echo "Modo dry-run: $pending notificação(ões) pendente(s) não foram enviadas.\n";
    exit(0);
}

$summary = ['processed' => 0, 'sent' => 0, 'retry' => 0, 'failed' => 0, 'skipped' => 0];
try {
    $summary = notificationProcessPending($GLOBALS['pdo'], $limit);
} catch (Throwable $e) {
    fwrite(STDERR, "Falha ao processar a fila: " . $e->getMessage() . "\n");
    exit(1);
}
printf(
    "Processadas: %d | enviadas: %d | retry: %d | falhas: %d | ignoradas: %d\n",
    $summary['processed'],
    $summary['sent'],
    $summary['retry'],
    $summary['failed'],
    $summary['skipped']
);

exit($summary['failed'] > 0 ? 2 : 0);
