<?php
$page_title = 'Gerenciar Clientes - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/pagination.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_require_valid();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        // FK fk_orders_user é RESTRICT: preserva o histórico de pedidos do cliente.
        $linked = $pdo->prepare('SELECT COUNT(*) FROM e5_orders WHERE user_id = :id');
        $linked->execute([':id' => $id]);
        $linkedCount = (int) $linked->fetchColumn();
        if ($linkedCount > 0) {
            $_SESSION['admin_error'] = 'Não é possível excluir este cliente: há ' . $linkedCount . ' pedido(s) vinculado(s) ao histórico.';
        } else {
            $pdo->prepare('DELETE FROM e5_users WHERE id = :id AND role = :role')->execute([':id' => $id, ':role' => 'customer']);
            $_SESSION['admin_message'] = 'Cliente excluído.';
        }
    }
    header('Location: customers.php');
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$where = "WHERE role = :role";
$params = [':role' => 'customer'];
if ($search !== '') {
    $where .= ' AND (name LIKE :q_name OR email LIKE :q_email OR username LIKE :q_username)';
    $pattern = '%' . $search . '%';
    $params[':q_name'] = $pattern;
    $params[':q_email'] = $pattern;
    $params[':q_username'] = $pattern;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM e5_users $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$page = pagination_page();
$limit = pagination_limit(20);
$totalPages = max(1, (int) ceil($total / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = pagination_offset($page, $limit);

$sql = "SELECT id, name, email, username, created_at FROM e5_users $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$customers = $pdo->prepare($sql);
$customers->execute($params);
$customers = $customers->fetchAll();

$message = $_SESSION['admin_message'] ?? null;
$error = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_message'], $_SESSION['admin_error']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include 'head_inc.php'; ?>
</head>
<body>
    <div class="admin-wrapper">
        <?php $activePage = 'customers'; include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Gerenciar Clientes</h2>
                    <p><?php echo $total; ?> cliente(s) cadastrado(s)</p>
                </div>
                <div class="admin-actions">
                    <a class="btn btn-secondary" href="customers-export.php?q=<?php echo urlencode($search); ?>" aria-label="Exportar clientes em CSV"><i class="fas fa-file-export"></i> Exportar</a>
                    <?php include 'header_user_inc.php'; ?>
                </div>
            </header>
            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="admin-table-container">
                <div class="admin-table-header">
                    <form method="GET" class="admin-filter-bar">
                        <input type="text" name="q" placeholder="Buscar por nome, e-mail ou usuário..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-secondary" aria-label="Buscar clientes"><i class="fas fa-search"></i></button>
                        <?php if ($search !== ''): ?>
                        <a href="customers.php" class="btn btn-secondary" aria-label="Limpar busca"><i class="fas fa-times"></i> Limpar</a>
                        <?php endif; ?>
                    </form>
                    <span class="pagination-summary"><?php echo $total; ?> registro(s)</span>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>E-mail</th>
                            <th>Usuário</th>
                            <th>Membro Desde</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                        <tr><td colspan="5" style="text-align:center; color:var(--color-gray); padding:40px;">Nenhum cliente encontrado.</td></tr>
                        <?php else: foreach ($customers as $c):
                            $name = htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8');
                            $initials = '';
                            foreach (explode(' ', $name) as $part) {
                                if ($part !== '') $initials .= strtoupper($part[0]);
                                if (strlen($initials) >= 2) break;
                            }
                            $created = date('d/m/Y', strtotime($c['created_at']));
                            $since = date('M/Y', strtotime($c['created_at']));
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; background: var(--color-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--color-black); font-weight: 600;"><?php echo $initials; ?></div>
                                    <div>
                                        <strong><?php echo $name; ?></strong>
                                        <br><small style="color: var(--color-gray);">Desde <?php echo $since; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($c['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $created; ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="mailto:<?php echo htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary" style="padding:6px 12px;"><i class="fas fa-envelope"></i></a>
                                    <form method="POST" onsubmit="return confirm('Excluir cliente #<?php echo (int) $c['id']; ?>? Esta ação não pode ser desfeita.');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="btn btn-secondary" style="padding:6px 12px; color:#f44336;" aria-label="Excluir cliente"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <?php echo pagination_render($page, $totalPages, ['q' => $search]); ?>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
