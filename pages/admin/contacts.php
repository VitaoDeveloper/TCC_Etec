<?php
$page_title = 'Mensagens de Contato - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_require_valid();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM e5_contacts WHERE id = :id')->execute([':id' => $id]);
        $_SESSION['admin_message'] = 'Mensagem excluída.';
    }
    header('Location: contacts.php');
    exit;
}

$contacts = $pdo->query('SELECT id, name, email, phone, subject, message, created_at FROM e5_contacts ORDER BY created_at DESC')->fetchAll();
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
        <?php $activePage = 'contacts'; include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Mensagens de Contato</h2>
                    <p><?php echo count($contacts); ?> mensagen(s)</p>
                </div>
            </header>
            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Assunto</th>
                            <th>Mensagem</th>
                            <th style="width:60px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:40px; color:var(--color-gray);">Nenhuma mensagem recebida.</td></tr>
                        <?php else: foreach ($contacts as $c): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--color-primary);"><?php echo htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                            <td><?php echo htmlspecialchars($c['phone'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($c['subject'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="max-width:300px;"><?php echo nl2br(htmlspecialchars($c['message'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Excluir esta mensagem?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
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
