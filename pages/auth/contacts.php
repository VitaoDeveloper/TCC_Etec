<?php
$page_title = 'Meus Contatos - Royal Tech';
$breadcrumb_title = 'Meus Contatos';
$current_page = 'contatos';
$base_path = '../../';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

include '../../database/connection.php';
require_once __DIR__ . '/../../includes/contact_functions.php';

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('
    SELECT c.id, c.subject, c.message, c.status, c.created_at,
        c.response_message, c.responded_at
    FROM e5_contacts c
    WHERE c.user_id = :uid
    ORDER BY c.created_at DESC
');
$stmt->execute([':uid' => $userId]);
$contacts = $stmt->fetchAll();

include '../../components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;"><div class="container" style="max-width:820px; margin:0 auto;">
    <div class="ml-section-header">
        <h2 class="ml-section-title">Meus Contatos</h2>
        <span class="ml-main-count"><?php echo count($contacts); ?> mensagem(ns)</span>
    </div>

    <?php if (empty($contacts)): ?>
        <div class="ml-empty">
            <i class="fas fa-envelope-open-text"></i>
            <h3>Nenhum contato enviado</h3>
            <p>Quando você enviar uma mensagem pela página de contato, ela aparecerá aqui com o retorno da nossa equipe.</p>
            <p style="margin-top: 16px;"><a href="../products/contact.php" class="ml-btn ml-btn-primary"><i class="fas fa-paper-plane"></i> Falar Conosco</a></p>
        </div>
    <?php else: ?>
        <div class="ml-table-wrap">
            <table class="ml-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Assunto</th>
                        <th>Status</th>
                        <th>Resposta</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($contacts as $c):
                    $label = $contactSubjectLabels[$c['subject']] ?? $c['subject'];
                    $statusInfo = $contactStatusLabels[$c['status']] ?? ['label' => $c['status'], 'class' => ''];
                ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div style="max-width:280px; color:var(--ml-text-secondary); font-size:0.85rem; margin-top:4px;"><?php echo nl2br(htmlspecialchars($c['message'], ENT_QUOTES, 'UTF-8')); ?></div>
                        </td>
                        <td><span class="status-badge <?php echo $statusInfo['class']; ?>"><?php echo htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td style="max-width:300px;">
                            <?php if ($c['status'] === 'answered' && $c['response_message']): ?>
                                <div class="ml-reply-box" style="background:#f9f9f9; border-left:4px solid var(--ml-accent, #d4af37); padding:10px 12px; border-radius:4px;">
                                    <small style="color:var(--ml-text-muted); display:block; margin-bottom:4px;"><i class="fas fa-reply"></i> Resposta em <?php echo date('d/m/Y H:i', strtotime($c['responded_at'])); ?></small>
                                    <?php echo nl2br(htmlspecialchars($c['response_message'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--ml-text-muted);">Aguardando resposta</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div style="text-align:center; margin-top:15px;"><a href="profile.php" class="ml-btn"><i class="fas fa-user"></i> Meu Perfil</a></div>
</div></section>
<?php include '../../components/footer.php'; ?>