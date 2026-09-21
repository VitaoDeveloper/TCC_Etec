<?php
$page_title = 'Detalhe do Contato - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/contact_functions.php';

$contactId = (int) ($_GET['id'] ?? 0);

$contact = $pdo->prepare('
    SELECT c.*, u.name AS user_name,
        ru.name AS responded_by_name
    FROM e5_contacts c
    LEFT JOIN e5_users u ON u.id = c.user_id
    LEFT JOIN e5_users ru ON ru.id = c.responded_by
    WHERE c.id = :id LIMIT 1
');
$contact->execute([':id' => $contactId]);
$contact = $contact->fetch();

if (!$contact) {
    header('Location: contacts.php');
    exit;
}

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    csrf_require_valid();
    $responseMessage = trim((string) ($_POST['response_message'] ?? ''));
    $respondedBy = (int) $_SESSION['user_id'];

    if ($responseMessage === '') {
        $errorMessage = 'Escreva a resposta antes de salvar.';
    } else {
        // 1) Persiste a resposta (sempre salva, mesmo se o e-mail falhar).
        $pdo->prepare("UPDATE e5_contacts SET status = 'answered', response_message = :msg, responded_by = :by, responded_at = NOW() WHERE id = :id")
            ->execute([':msg' => $responseMessage, ':by' => $respondedBy, ':id' => $contactId]);

        // 2) Dispara o e-mail reutilizando o mecanismo existente do projeto.
        $emailSent = false;
        try {
            $emailSent = enviarRespostaContato($contactId, $contact['name'], $contact['email'], $contact['subject'] ?? '', $responseMessage);
        } catch (Throwable $e) {
            error_log('Contact reply email error: ' . $e->getMessage());
        }

        // 3) Registra o status de envio sem quebrar o salvamento da resposta.
        //    Persiste o detalhe REAL da falha (mensagem/código da PHPMailer), não só um texto genérico.
        $errorMsg = null;
        if (!$emailSent) {
            $realError = $GLOBALS['mail_last_error'] ?? null;
            $errorMsg = ($realError !== null && trim($realError) !== '')
                ? 'Falha no envio do e-mail (' . $realError . ')'
                : 'Falha no envio do e-mail (verifique logs do servidor)';
        }
        salvarStatusEmailContato($contactId, $emailSent ? 'sent' : 'failed', $errorMsg);

        $_SESSION['admin_message'] = $emailSent
            ? 'Resposta salva e enviada por e-mail para ' . htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') . '.'
            : 'Resposta salva, mas falhou o envio do e-mail. Verifique os logs.';
        header('Location: contact-detail.php?id=' . $contactId);
        exit;
    }
}

$statusInfo = $contactStatusLabels[$contact['status']] ?? ['label' => $contact['status'], 'class' => ''];
$adminMessage = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);

$replyEmailStatus = $contact['response_email_status'] ?? null;
$replyEmailOk = $replyEmailStatus === 'sent';
$replyEmailFailed = $replyEmailStatus === 'failed';
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
                    <h2>Contato #<?php echo str_pad((string) $contact['id'], 4, '0', STR_PAD_LEFT); ?></h2>
                    <p><a href="contacts.php" style="color:var(--color-primary);">&larr; Voltar para Contatos</a></p>
                </div>
                <div class="admin-actions">
                    <span class="status-badge <?php echo $statusInfo['class']; ?>"><?php echo htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php include 'header_user_inc.php'; ?>
                </div>
            </header>

            <?php if ($adminMessage): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($adminMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
            <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:30px;">
                <div class="admin-table-container" style="padding:25px;">
                    <h3 style="margin-bottom:15px; font-size:1.1rem;">Mensagem Recebida</h3>
                    <table style="width:100%;">
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Data</td><td style="text-align:right;"><?php echo date('d/m/Y H:i', strtotime($contact['created_at'])); ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Nome</td><td style="text-align:right;"><?php echo htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'); ?><?php echo $contact['user_name'] ? ' <small style="color:var(--color-gray);">(cliente: ' . htmlspecialchars($contact['user_name'], ENT_QUOTES, 'UTF-8') . ')</small>' : ''; ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">E-mail</td><td style="text-align:right;"><a href="mailto:<?php echo htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--color-primary);"><?php echo htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8'); ?></a></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Telefone</td><td style="text-align:right;"><?php echo htmlspecialchars($contact['phone'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Assunto</td><td style="text-align:right;"><?php echo htmlspecialchars($contactSubjectLabels[$contact['subject']] ?? $contact['subject'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                        <tr><td style="color:var(--color-gray); padding:6px 0;">Vínculo</td><td style="text-align:right;"><?php echo $contact['user_id'] ? 'Área logada + e-mail' : 'Somente e-mail (envio público)'; ?></td></tr>
                        <tr>
                            <td colspan="2" style="padding-top:15px;">
                                <div style="background:var(--color-black); border:1px solid var(--color-border); border-radius:6px; padding:15px; color:var(--color-gray-light);">
                                    <?php echo nl2br(htmlspecialchars($contact['message'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="admin-table-container" style="padding:25px;">
                    <h3 style="margin-bottom:15px; font-size:1.1rem;">Resposta do Admin</h3>
                    <?php if ($contact['status'] === 'answered'): ?>
                        <table style="width:100%;">
                            <tr><td style="color:var(--color-gray); padding:6px 0;">Respondido em</td><td style="text-align:right;"><?php echo date('d/m/Y H:i', strtotime($contact['responded_at'])); ?></td></tr>
                            <tr><td style="color:var(--color-gray); padding:6px 0;">Respondido por</td><td style="text-align:right;"><?php echo htmlspecialchars($contact['responded_by_name'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr>
                                <td colspan="2" style="padding-top:15px;">
                                    <div style="background:var(--color-black); border:1px solid var(--color-border); border-left:4px solid var(--color-primary); border-radius:6px; padding:15px; color:var(--color-gray-light);">
                                        <?php if (!empty($contact['response_message'])): ?>
                                            <?php echo nl2br(htmlspecialchars($contact['response_message'], ENT_QUOTES, 'UTF-8')); ?>
                                        <?php else: ?>
                                            <em style="color:var(--color-gray);">Resposta registrada sem texto.</em>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($replyEmailStatus): ?>
                            <tr>
                                <td style="color:var(--color-gray); padding:6px 0;">Status do e-mail</td>
                                <td style="text-align:right;">
                                    <?php if ($replyEmailOk): ?>
                                    <span class="status-badge status-active">Enviado</span>
                                    <?php elseif ($replyEmailFailed): ?>
                                    <span class="status-badge status-inactive">Falha no envio</span>
                                    <?php else: ?>
                                    <span class="status-badge status-pending"><?php echo htmlspecialchars($replyEmailStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($replyEmailFailed && !empty($contact['response_email_error'])): ?>
                            <tr><td colspan="2" style="color:#a94442; font-size:0.85rem; padding-top:8px;"><?php echo htmlspecialchars($contact['response_email_error'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <?php endif; ?>
                            <?php endif; ?>
                        </table>
                        <hr style="border:none; border-top:1px solid var(--color-border); margin:20px 0;">
                        <p style="color:var(--color-gray); font-size:0.9rem; margin-bottom:12px;">Escreva abaixo para enviar uma nova resposta (substitui a anterior).</p>
                    <?php endif; ?>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reply">
                        <div class="admin-form-group">
                            <label for="response_message">Sua resposta</label>
                            <textarea id="response_message" name="response_message" rows="8" placeholder="Escreva o retorno para o cliente..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> <?php echo $contact['status'] === 'answered' ? 'Salvar nova resposta e enviar' : 'Salvar resposta e enviar'; ?></button>
                    </form>
                    <p style="color:var(--color-gray); font-size:0.85rem; margin-top:12px;">
                        Ao salvar, a resposta é registrada e um e-mail é enviado ao cliente usando o remetente configurado nas Configurações.
                    </p>
                </div>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>