<?php
// Área de usuário exibida no canto direito do header de todas as páginas do admin.
// Deve ser incluída dentro (ou ao lado) de .admin-actions, após o carregamento do $pdo.
require_once __DIR__ . '/../../includes/admin_notifications.php';
require_once __DIR__ . '/../../includes/csrf.php';

$adminUserName = trim((string) ($_SESSION['user_name'] ?? ''));
if ($adminUserName === '') {
    $adminUserName = 'Administrador';
}

$msgCount = 0;
$notifCount = 0;
$notifItems = [];
if (isset($pdo)) {
    adminNotificationsSync($pdo);
    $notifItems = adminNotificationsLatest($pdo, 6);
    $notifCount = adminNotificationsUnreadCount($pdo);
    try {
        $msgCount = (int) $pdo->query(
            'SELECT COUNT(*) FROM e5_contacts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        )->fetchColumn();
    } catch (Throwable $e) {
        $msgCount = 0;
    }
}

$notifIcons = [
    'order' => 'fa-shopping-bag',
    'contact' => 'fa-envelope',
    'stock' => 'fa-box-open',
];
?>
<div class="admin-user-area">
    <div class="admin-action-buttons">
        <a href="contacts.php" class="action-btn" aria-label="Mensagens de contato" title="Mensagens de contato">
            <i class="fas fa-envelope"></i>
            <?php if ($msgCount > 0): ?>
            <span class="action-btn-badge"><?php echo $msgCount > 9 ? '9+' : $msgCount; ?></span>
            <?php endif; ?>
        </a>
        <div class="admin-notif">
            <button type="button" class="action-btn admin-notif-toggle" aria-label="Notificações" title="Notificações" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <?php if ($notifCount > 0): ?>
                <span class="action-btn-badge"><?php echo $notifCount > 9 ? '9+' : $notifCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="admin-notif-panel" id="admin-notif-panel">
                <div class="admin-notif-header">
                    <h4>Notificações</h4>
                    <?php if ($notifCount > 0): ?>
                    <form method="POST" action="notifications-read-all.php">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="admin-notif-mark">Marcar todas como lidas</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php if (empty($notifItems)): ?>
                <p class="admin-notif-empty">Tudo em dia por aqui. Nenhuma notificação.</p>
                <?php else: ?>
                <ul class="admin-notif-list">
                    <?php foreach ($notifItems as $n):
                        $icon = $notifIcons[$n['type']] ?? 'fa-bell';
                    ?>
                    <li class="<?php echo (int) $n['is_read'] === 1 ? 'is-read' : 'is-unread'; ?>">
                        <a href="notification-open.php?id=<?php echo (int) $n['id']; ?>">
                            <i class="fas <?php echo $icon; ?>"></i>
                            <span>
                                <strong><?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small><?php echo htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8'); ?></small>
                                <em><?php echo htmlspecialchars(adminNotificationsTimeAgo($n['created_at']), ENT_QUOTES, 'UTF-8'); ?></em>
                            </span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="admin-notif-footer">
                    <a href="notifications.php">Ver todas as notificações</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="admin-user">
        <img src="../../assets/img/placeholder-avatar.svg" alt="Foto do administrador">
        <span><?php echo htmlspecialchars($adminUserName, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <a href="logout.php" class="btn btn-secondary admin-logout-btn" aria-label="Sair do painel" title="Sair do painel">
        <i class="fas fa-sign-out-alt"></i>
        <span class="admin-logout-label">Sair</span>
    </a>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.querySelector('.admin-notif-toggle');
    var panel = document.getElementById('admin-notif-panel');
    if (toggle && panel) {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var open = !panel.classList.contains('open');
            panel.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function(e) {
            if (!panel.classList.contains('open')) return;
            if (!toggle.contains(e.target) && !panel.contains(e.target)) {
                panel.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && panel.classList.contains('open')) {
                panel.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
});
</script>