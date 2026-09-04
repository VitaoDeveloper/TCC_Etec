<?php
$page_title = 'Newsletter - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['E-mail', 'Data de Cadastro']);
    $rows = $pdo->query('SELECT email, created_at FROM e5_newsletter ORDER BY created_at DESC')->fetchAll();
    foreach ($rows as $r) {
        fputcsv($output, [$r['email'], $r['created_at']]);
    }
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_require_valid();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM e5_newsletter WHERE id = :id')->execute([':id' => $id]);
        $_SESSION['admin_message'] = 'Inscrição removida.';
    }
    header('Location: newsletter.php');
    exit;
}

$subs = $pdo->query('SELECT id, email, created_at FROM e5_newsletter ORDER BY created_at DESC')->fetchAll();
$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);
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
        <?php $activePage = 'newsletter'; include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Inscritos na Newsletter</h2>
                    <p><?php echo count($subs); ?> inscrito(s)</p>
                </div>
                <div class="admin-actions">
                    <a href="?export=csv" class="btn btn-primary"><i class="fas fa-download"></i> Exportar CSV</a>
                    <?php include 'header_user_inc.php'; ?>
                </div>
            </header>
            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>E-mail</th>
                            <th>Data de Cadastro</th>
                            <th style="width:60px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subs)): ?>
                        <tr><td colspan="3" style="text-align:center; padding:40px; color:var(--color-gray);">Nenhum inscrito.</td></tr>
                        <?php else: foreach ($subs as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Remover este e-mail?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="table-actions" style="display:inline-flex;"><a href="#" onclick="this.closest('form').submit();return false;" style="color:#f44336;"><i class="fas fa-trash"></i></a></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
