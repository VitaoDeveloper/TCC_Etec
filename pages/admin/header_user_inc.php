<?php
// Área de usuário exibida no canto direito do header de todas as páginas do admin.
// Deve ser incluída dentro (ou ao lado) de .admin-actions, após o carregamento do $pdo.
$adminUserName = trim((string) ($_SESSION['user_name'] ?? ''));
if ($adminUserName === '') {
    $adminUserName = 'Administrador';
}

$msgCount = 0;
$notifCount = 0;
$notifItems = [];
if (isset($pdo)) {
    try {
        $lowStock = $pdo->query(
            'SELECT name, stock FROM e5_products WHERE stock <= 5 ORDER BY stock ASC LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lowStock as $item) {
            $notifItems[] = [
                'text' => 'Estoque baixo: ' . $item['name'] . ' (' . (int) $item['stock'] . ' un.)',
                'url' => 'products.php',
            ];
        }

        $recentContacts = $pdo->query(
            'SELECT id, name, subject FROM e5_contacts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY created_at DESC LIMIT 4'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($recentContacts as $c) {
            $notifItems[] = [
                'text' => 'Nova mensagem de ' . $c['name'] . ': ' . $c['subject'],
                'url' => 'contacts.php',
            ];
        }

        $msgCount = (int) $pdo->query(
            'SELECT COUNT(*) FROM e5_contacts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        )->fetchColumn();
    } catch (Throwable $e) {
        $notifItems = [];
        $msgCount = 0;
    }
    $notifCount = count($notifItems);
}
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
                <div class="admin-notif-header"><h4>Notificações</h4></div>
                <?php if (empty($notifItems)): ?>
                <p class="admin-notif-empty">Tudo em dia por aqui. Nenhuma notificação.</p>
                <?php else: ?>
                <ul class="admin-notif-list">
                    <?php foreach ($notifItems as $n): ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($n['url'], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-circle"></i>
                            <span><?php echo htmlspecialchars($n['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="admin-notif-footer">
                    <a href="contacts.php">Ver todas as mensagens</a>
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