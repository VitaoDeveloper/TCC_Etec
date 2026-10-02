<?php

declare(strict_types=1);

/**
 * Worker da loja: fila de e-mail + expiração de Pix.
 *
 * Uso (CLI):
 *   php worker.php
 *   php worker.php --emails=20        (limite de e-mails por rodada)
 *   php worker.php --only=emails      (só a fila)
 *   php worker.php --only=pix         (só a expiração)
 *
 * Nao deve rodar via browser: a trava de arquivo impede execução
 * dupla, mas um HTTP request ainda estaria fora do cron. O guard abaixo
 * rejeita chamada web mesmo que alguém aponte o navegador pra cá.
 *
 * A expiração de Pix REUSA order_expire_pending_pix_lazy() em vez de
 * reescrever a regra aqui: a tela de pedido já aplica a mesma função na
 * leitura, e duas implementações divergentes significariam o pedido
 * expirando de um jeito no worker e de outro na tela.
 *
 * Antes este worker chamava payment_expire_if_due(), que só troca o
 * payment_status para 'expired'. Ele NÃO cancelava o pedido, NÃO gravava
 * histórico e NÃO devolvia estoque — então o pedido ficava 'pending' para
 * sempre com o Pix vencido, e a mercadoria reservada nunca voltava ao
 * catálogo. A função de order_state.php faz a transação completa.
 */

// ---------------------------------------------------------------------------
//  Guard: só CLI
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script roda apenas via linha de comando (CLI).\n");
}

$root = __DIR__;
require_once $root . '/includes/config.php';
loadEnv($root . '/.env');
require_once $root . '/includes/csrf.php';
require_once $root . '/includes/mail.php';
require_once $root . '/includes/order_repo.php';
require_once $root . '/includes/notification_functions.php';
require_once $root . '/includes/payment_functions.php';
require_once $root . '/includes/order_state.php';
require_once $root . '/database/connection.php';

// ---------------------------------------------------------------------------
//  Argumentos
// ---------------------------------------------------------------------------
$emailLimit = 25;
$only       = null;
$once       = false;

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--emails=(\d+)$/', $arg, $m)) {
        $emailLimit = max(1, (int) $m[1]);
    } elseif (preg_match('/^--only=(emails|pix)$/', $arg, $m)) {
        $only = $m[1];
    } elseif ($arg === '--once') {
        $once = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Uso: php worker.php [--emails=N] [--only=emails|pix] [--once]\n");
        exit(0);
    }
}

// ---------------------------------------------------------------------------
//  Trava contra execução dupla
//
//  flock() em arquivo de trava: se outro worker já está rodando, o LOCK_EX
//  falha e esta execução sai em silêncio com código 0. Sem isto dois crons
//  concorrentes enviavam o mesmo e-mail duas vezes e disputavam a fila.
// ---------------------------------------------------------------------------
$lockDir  = $root . '/storage';
$lockFile = $lockDir . '/worker.lock';

if (!is_dir($lockDir) && !mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "Não foi possível criar a pasta de trava: $lockDir\n");
    exit(1);
}

$lockHandle = fopen($lockFile, 'c');
if ($lockHandle === false) {
    fwrite(STDERR, "Não foi possível abrir a trava: $lockFile\n");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "worker: outra instância já está rodando. Saindo.\n");
    exit(0);
}

// Libera a trava no fim (também em exit() via shutdown, e no fatal error).
register_shutdown_function(static function () use ($lockHandle): void {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
});

fwrite(STDOUT, "worker: iniciado " . date('Y-m-d H:i:s') . "\n");

// ---------------------------------------------------------------------------
//  1. Fila de e-mail
// ---------------------------------------------------------------------------
if ($only === null || $only === 'emails') {
    // Roda até esvaziar (ou até o teto de rodadas), para um lote grande
    // não deixar e-mails parados esperando o próximo cron.
    $totalSent   = 0;
    $totalFailed = 0;
    $rounds      = 0;

    do {
        $result  = notification_drain_email($pdo, $emailLimit);
        $totalSent   += $result['sent'];
        $totalFailed += $result['failed'];
        $rounds++;
    } while (!$once
        && $result['remaining'] > 0
        && $result['sent'] + $result['failed'] > 0
        && $rounds < 20);

    fwrite(STDOUT, sprintf(
        "worker: e-mails -> enviados=%d falhas=%d restam=%d rodadas=%d\n",
        $totalSent,
        $totalFailed,
        $result['remaining'],
        $rounds
    ));
}

// ---------------------------------------------------------------------------
//  2. Expiração de Pix
//
//  Busca os pedidos com prazo vencido e aplica a MESMA função que a tela
//  usa na leitura. payment_expires_at foi gravado em +30 minutos no
//  checkout (payment_functions.php), então "expira em 30 minutos" é dado
//  de banco, não texto de tela.
// ---------------------------------------------------------------------------
if ($only === null || $only === 'pix') {
    $stmt = $pdo->query(
        "SELECT id
           FROM e5_orders
          WHERE payment_expires_at IS NOT NULL
            AND payment_expires_at < NOW()
            AND payment_status IN ('pending', 'processing')
          ORDER BY payment_expires_at ASC
          LIMIT 500"
    );

    $expired = 0;
    $restocked = 0;
    $canceled  = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $orderId) {
        $order = order_repo_find($pdo, (int) $orderId);
        if ($order === null) {
            continue;
        }

        $beforeStatus = (string) $order['status'];
        $beforePay    = (string) $order['payment_status'];

        // order_expire_pending_pix_lazy() faz a transação completa:
        // pedido -> canceled, pagamento -> expired, histórico e estoque.
        // payment_expire_if_due() (usado antes) só trocava o
        // payment_status e deixava o pedido pending com estoque preso.
        $result = order_expire_pending_pix_lazy($pdo, $order);
        $order  = $result['order'];

        if ($result['expired']) {
            $expired++;
            if ((string) $order['status'] !== $beforeStatus) {
                $canceled++;
            }
            if ((string) $order['payment_status'] !== $beforePay) {
                $restocked++;
            }
        }
    }

    fwrite(STDOUT, "worker: expiração de Pix -> expirados={$expired} "
        . "pedidos_cancelados={$canceled} pagamentos_vencidos={$restocked}\n");
}

fwrite(STDOUT, "worker: concluído " . date('Y-m-d H:i:s') . "\n");
exit(0);
