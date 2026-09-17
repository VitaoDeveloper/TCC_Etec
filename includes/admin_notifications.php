<?php
// Notificações persistidas do sino do painel admin.
// Concilia eventos de origem (pedidos, contatos, estoque baixo) na tabela
// e5_admin_notifications e expõe leitura/contagem/estado lido.

function adminNotificationsSync(PDO $pdo): void
{
    // Se a tabela ainda não existir, não interrompe a página.
    try {
        $pdo->query('SELECT 1 FROM e5_admin_notifications LIMIT 1');
    } catch (Throwable $e) {
        return;
    }

    try {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO e5_admin_notifications (type, title, message, url, ref_id, dedupe_key)
             VALUES (:type, :title, :message, :url, :ref_id, :dedupe_key)'
        );

        // Novos pedidos (últimos 30 dias) não notificados ainda.
        $orders = $pdo->query(
            "SELECT o.id, o.total, u.name
             FROM e5_orders o INNER JOIN e5_users u ON u.id = o.user_id
             WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY o.id DESC LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as $o) {
            $insert->execute([
                ':type' => 'order',
                ':title' => 'Novo pedido',
                ':message' => sprintf('Pedido #%04d de %s — R$ %s', (int) $o['id'], $o['name'], number_format((float) $o['total'], 2, ',', '.')),
                ':url' => 'order-detail.php?id=' . (int) $o['id'],
                ':ref_id' => (int) $o['id'],
                ':dedupe_key' => 'order:' . (int) $o['id'],
            ]);
        }

        // Mensagens de contato (últimos 30 dias).
        $contacts = $pdo->query(
            "SELECT id, name, subject FROM e5_contacts
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY id DESC LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($contacts as $c) {
            $insert->execute([
                ':type' => 'contact',
                ':title' => 'Nova mensagem de contato',
                ':message' => $c['name'] . ': ' . ($c['subject'] !== '' ? $c['subject'] : '(sem assunto)'),
                ':url' => 'contacts.php',
                ':ref_id' => (int) $c['id'],
                ':dedupe_key' => 'contact:' . (int) $c['id'],
            ]);
        }

        // Estoque baixo (<= 5 unidades).
        $lowStock = $pdo->query(
            'SELECT id, name, stock FROM e5_products WHERE stock <= 5 ORDER BY stock ASC LIMIT 20'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lowStock as $p) {
            $insert->execute([
                ':type' => 'stock',
                ':title' => 'Estoque baixo',
                ':message' => $p['name'] . ' — ' . (int) $p['stock'] . ' un. restante(s)',
                ':url' => 'product-form.php?id=' . (int) $p['id'],
                ':ref_id' => (int) $p['id'],
                ':dedupe_key' => 'stock:' . (int) $p['id'],
            ]);
        }
    } catch (Throwable $e) {
        // Falha de conciliação não deve quebrar o painel.
        error_log('adminNotificationsSync: ' . $e->getMessage());
    }
}

function adminNotificationsUnreadCount(PDO $pdo): int
{
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM e5_admin_notifications WHERE is_read = 0')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function adminNotificationsLatest(PDO $pdo, int $limit = 6): array
{
    $limit = max(1, min($limit, 30));
    try {
        return $pdo->query(
            "SELECT id, type, title, message, url, is_read, created_at
             FROM e5_admin_notifications
             ORDER BY is_read ASC, created_at DESC
             LIMIT $limit"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function adminNotificationsMarkRead(PDO $pdo, int $id): void
{
    if ($id <= 0) {
        return;
    }
    try {
        $pdo->prepare('UPDATE e5_admin_notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND is_read = 0')
            ->execute([':id' => $id]);
    } catch (Throwable $e) {
        error_log('adminNotificationsMarkRead: ' . $e->getMessage());
    }
}

function adminNotificationsMarkAllRead(PDO $pdo): void
{
    try {
        $pdo->exec('UPDATE e5_admin_notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0');
    } catch (Throwable $e) {
        error_log('adminNotificationsMarkAllRead: ' . $e->getMessage());
    }
}

// Rótulo amigável de tempo relativo para o painel.
function adminNotificationsTimeAgo(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'agora';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd';
    }
    return date('d/m/Y', $ts);
}
