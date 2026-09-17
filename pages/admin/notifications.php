<?php
$page_title = 'Notificações - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/admin_notifications.php';
require_once __DIR__ . '/../../includes/notifications_functions.php';
require_once __DIR__ . '/../../includes/pagination.php';

adminNotificationsSync($pdo);

$queueStats = notificationQueueStats($pdo);
$queueRecent = notificationQueueRecent($pdo, 10);

$typeFilter = (string) ($_GET['type'] ?? '');
$validTypes = ['order', 'contact', 'stock'];
if (!in_array($typeFilter, $validTypes, true)) {
    $typeFilter = '';
}
$onlyUnread = isset($_GET['unread']) && $_GET['unread'] === '1';

$where = [];
$params = [];
if ($typeFilter !== '') {
    $where[] = 'type = :type';
    $params[':type'] = $typeFilter;
}
if ($onlyUnread) {
    $where[] = 'is_read = 0';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM e5_admin_notifications $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$page = pagination_page();
$limit = pagination_limit(25);
$totalPages = max(1, (int) ceil($total / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = pagination_offset($page, $limit);

$stmt = $pdo->prepare("SELECT * FROM e5_admin_notifications $whereSql ORDER BY is_read ASC, created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unreadTotal = adminNotificationsUnreadCount($pdo);

$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);

$typeMeta = [
    'order' => ['label' => 'Pedido', 'icon' => 'fa-shopping-bag'],
    'contact' => ['label' => 'Contato', 'icon' => 'fa-envelope'],
    'stock' => ['label' => 'Estoque', 'icon' => 'fa-box-open'],
];
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include 'head_inc.php'; ?>
</head>
<body>
    <button class="sidebar-toggle" aria-label="Abrir menu"><i class="fas fa-bars"></i></button>
    <div class="admin-wrapper">
        <?php $activePage = 'notifications'; include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Notificações</h2>
                    <p><?php echo $unreadTotal; ?> não lida(s) de <?php echo $total; ?> registro(s)</p>
                </div>
                <div class="admin-actions">
                    <?php if ($unreadTotal > 0): ?>
                    <form method="POST" action="notifications-read-all.php">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check-double"></i> Marcar todas como lidas</button>
                    </form>
                    <?php endif; ?>
                    <?php include 'header_user_inc.php'; ?>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h3>Histórico <span class="pagination-summary">(<?php echo $total; ?>)</span></h3>
                    <div class="admin-filter-bar">
                        <a href="notifications.php" class="btn btn-secondary <?php echo $typeFilter === '' && !$onlyUnread ? 'active' : ''; ?>">Todas</a>
                        <a href="?unread=1" class="btn btn-secondary <?php echo $onlyUnread ? 'active' : ''; ?>">Não lidas</a>
                        <?php foreach ($typeMeta as $key => $meta): ?>
                        <a href="?type=<?php echo $key; ?>" class="btn btn-secondary <?php echo $typeFilter === $key ? 'active' : ''; ?>"><i class="fas <?php echo $meta['icon']; ?>"></i> <?php echo $meta['label']; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Notificação</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="5" class="empty-state">Nenhuma notificação encontrada.</td></tr>
                        <?php else: foreach ($items as $n):
                            $meta = $typeMeta[$n['type']] ?? ['label' => $n['type'], 'icon' => 'fa-bell'];
                        ?>
                        <tr>
                            <td><span class="status-badge"><i class="fas <?php echo $meta['icon']; ?>"></i> <?php echo $meta['label']; ?></span></td>
                            <td>
                                <strong><?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <small style="color:var(--color-gray);"><?php echo htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8'); ?></small>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?></td>
                            <td>
                                <?php if ((int) $n['is_read'] === 1): ?>
                                <span class="status-badge status-active">Lida</span>
                                <?php else: ?>
                                <span class="status-badge status-pending">Não lida</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="notification-open.php?id=<?php echo (int) $n['id']; ?>" class="btn btn-secondary" style="padding:6px 12px;"><i class="fas fa-arrow-right"></i> Abrir</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <?php echo pagination_render($page, $totalPages, array_filter(['type' => $typeFilter, 'unread' => $onlyUnread ? '1' : ''])); ?>
            </div>

            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h3>Fila de e-mails transacionais</h3>
                    <div class="admin-filter-bar">
                        <span class="status-badge status-pending">Pendentes: <?php echo (int) ($queueStats['pending'] ?? 0); ?></span>
                        <span class="status-badge status-active">Enviados: <?php echo (int) ($queueStats['sent'] ?? 0); ?></span>
                        <span class="status-badge status-inactive">Falhas: <?php echo (int) ($queueStats['failed'] ?? 0); ?></span>
                        <span class="status-badge">Ignorados: <?php echo (int) ($queueStats['skipped'] ?? 0); ?></span>
                    </div>
                </div>
                <p style="color:var(--color-gray); font-size:0.85rem; margin:0 0 14px;">
                    A fila é processada por cron: <code>php scripts/notifications-worker.php</code> (recomendado a cada 5 min).
                    WhatsApp está desativado nesta versão.
                </p>
                <table class="admin-table">
                    <thead>
                        <tr><th>Evento</th><th>Destinatário</th><th>Canal</th><th>Tentativas</th><th>Status</th><th>Data</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($queueRecent)): ?>
                        <tr><td colspan="6" class="empty-state">Nenhuma notificação na fila ainda.</td></tr>
                        <?php else: foreach ($queueRecent as $q):
                            $qStatus = (string) $q['status'];
                            $qClass = ['sent' => 'status-active', 'failed' => 'status-inactive', 'pending' => 'status-pending', 'skipped' => ''][$qStatus] ?? '';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($q['event_key'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($q['order_id'])): ?><br><small style="color:var(--color-gray);">Pedido #<?php echo (int) $q['order_id']; ?></small><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) $q['recipient'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="status-badge"><i class="fas <?php echo $q['channel'] === 'whatsapp' ? 'fa-whatsapp' : 'fa-envelope'; ?>"></i> <?php echo htmlspecialchars($q['channel'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo (int) $q['attempts']; ?></td>
                            <td><span class="status-badge <?php echo $qClass; ?>"><?php echo htmlspecialchars($qStatus, ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo date('d/m/Y H:i', strtotime((string) ($q['sent_at'] ?: $q['created_at']))); ?></td>
                        </tr>
                        <?php if (!empty($q['last_error'])): ?>
                        <tr><td colspan="6" style="color:var(--color-gray); font-size:0.8rem; padding-top:0;"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars((string) $q['last_error'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <?php endif; ?>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
