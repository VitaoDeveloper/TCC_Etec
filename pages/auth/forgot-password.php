<?php
$page_title = 'Recuperar Senha - Royal Tech';
$base_path = '../../';
require_once __DIR__ . '/../../includes/csrf.php';
$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'E-mail inválido.';
    } else {
        include '../../database/connection.php';
        $stmt = $pdo->prepare('SELECT id FROM e5_users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('INSERT INTO e5_password_reset_tokens (user_id, token, expires_at) VALUES (:uid, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
            $stmt->execute([':uid' => $user['id'], ':token' => $token]);

            $resetLink = ($base_path) . 'pages/auth/reset-password.php?token=' . $token;
            $successMessage = 'Link de recuperação gerado. Acesse: <a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" style="color:var(--color-primary);">' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '</a>';
        } else {
            $successMessage = 'Se o e-mail existir em nossa base, você receberá um link de recuperação.';
        }
    }
}

include '../../components/header.php';
?>
<section class="section"><div class="container max-w-480 mx-auto">
    <div class="section-header"><h2>Recuperar Senha</h2><p>Informe seu e-mail para receber o link de redefinição</p></div>
    <?php if ($successMessage): ?><div class="auth-feedback auth-feedback-success"><?php echo $successMessage; ?></div><?php endif; ?>
    <?php if ($errorMessage): ?><div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="POST" class="admin-table-container" style="padding:30px;">
        <div class="admin-form-group"><label for="email">E-mail</label><input type="email" id="email" name="email" placeholder="seu@email.com" required></div>
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Enviar Link</button>
        <div style="text-align:center; margin-top:15px;"><a href="login.php" style="color:var(--color-gray);">Voltar ao login</a></div>
    </form>
</div></section>
<?php include '../../components/footer.php'; ?>
