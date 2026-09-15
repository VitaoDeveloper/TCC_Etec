<?php
$page_title = 'Mensagens de Contato - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/contact_functions.php';

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

$filter = (string) ($_GET['status'] ?? '');
$allowedStatus = ['pending', 'answered'];
$sql = 'SELECT id, name, email, phone, subject, message, status, created_at FROM e5_contacts';
$params = [];
if ($filter !== '' && in_array($filter, $allowedStatus, true)) {
    $sql .= ' WHERE status = :status';
    $params[':status'] = $filter;
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contacts = $stmt->fetchAll();
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
                <div class="admin-actions">
                    <a href="?status=" class="btn btn-secondary <?php echo $filter === '' ? 'active' : ''; ?>">Todas</a>
                    <?php foreach ($contactStatusLabels as $sk => $sinfo): ?>
                    <a href="?status=<?php echo $sk; ?>" class="btn btn-secondary <?php echo $filter === $sk ? 'active' : ''; ?>"><?php echo htmlspecialchars($sinfo['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
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
                            <th>Data</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Assunto</th>
                            <th>Mensagem</th>
                            <th>Status</th>
                            <th style="width:90px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                        <tr><td colspan="8" style="text-align:center; padding:40px; color:var(--color-gray);">Nenhuma mensagem recebida.</td></tr>
                        <?php else: foreach ($contacts as $c):
                            $cStatusInfo = $contactStatusLabels[$c['status']] ?? ['label' => $c['status'], 'class' => ''];
                        ?>
                        <tr>
                            <td style="white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--color-primary);"><?php echo htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                            <td><?php echo htmlspecialchars($c['phone'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($contactSubjectLabels[$c['subject']] ?? $c['subject'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="max-width:300px;"><?php echo nl2br(htmlspecialchars($c['message'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                            <td><span class="status-badge <?php echo $cStatusInfo['class']; ?>"><?php echo htmlspecialchars($cStatusInfo['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td>
                                <div class="table-actions" style="display:inline-flex; gap:8px; align-items:center;">
                                    <a href="contact-detail.php?id=<?php echo (int) $c['id']; ?>" title="<?php echo $c['status'] === 'answered' ? 'Ver resposta' : 'Responder'; ?>"><i class="fas fa-reply"></i></a>
                                    <form method="POST" onsubmit="return confirm('Excluir esta mensagem?');" style="display:inline; margin:0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="delete" title="Excluir mensagem"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
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
