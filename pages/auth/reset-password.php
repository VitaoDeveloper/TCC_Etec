<?php
$page_title = 'Redefinir Senha - Royal Tech';
$base_path = '../../';
require_once __DIR__ . '/../../includes/csrf.php';
$errorMessage = null;
$successMessage = null;

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    $errorMessage = 'Token inválido.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    if (!csrf_verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página.');
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($password) < 6) {
        $errorMessage = 'A senha deve ter no mínimo 6 caracteres.';
    } elseif ($password !== $confirm) {
        $errorMessage = 'As senhas não conferem.';
    } else {
        include '../../database/connection.php';
        $stmt = $pdo->prepare('SELECT user_id FROM e5_password_reset_tokens WHERE token = :token AND used = 0 AND expires_at > NOW() LIMIT 1');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();

        if ($row) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE e5_users SET password = :pass WHERE id = :id')->execute([':pass' => $hash, ':id' => $row['user_id']]);
            $pdo->prepare('UPDATE e5_password_reset_tokens SET used = 1 WHERE token = :token')->execute([':token' => $token]);
            $successMessage = 'Senha redefinida com sucesso! <a href="login.php" style="color:var(--color-primary);">Fazer login</a>';
        } else {
            $errorMessage = 'Token inválido ou expirado.';
        }
    }
}

include '../../components/header.php';
?>
<section class="section"><div class="container" style="max-width:480px; margin:0 auto;">
    <div class="section-header"><h2>Redefinir Senha</h2></div>
    <?php if ($successMessage): ?><div class="auth-feedback auth-feedback-success"><?php echo $successMessage; ?></div><?php endif; ?>
    <?php if ($errorMessage): ?><div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if (!$successMessage && $token !== ''): ?>
    <form method="POST" class="admin-table-container" style="padding:30px;">
        <div class="admin-form-group"><label for="password">Nova Senha</label><input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" required minlength="6"></div>
        <div class="admin-form-group"><label for="confirm_password">Confirmar Senha</label><input type="password" id="confirm_password" name="confirm_password" placeholder="Repita a senha" required minlength="6"></div>
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-check"></i> Redefinir Senha</button>
    </form>
    <?php endif; ?>
</div></section>
<?php include '../../components/footer.php'; ?>
