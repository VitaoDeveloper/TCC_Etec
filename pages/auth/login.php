<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ../products/products.php');
    exit;
}

$page_title = 'Login de Usuário';
$feedbackErrors = $_SESSION['auth_errors'] ?? [];
$feedbackSuccess = $_SESSION['auth_success'] ?? null;
$old = $_SESSION['auth_old'] ?? [];
unset($_SESSION['auth_errors'], $_SESSION['auth_success'], $_SESSION['auth_old']);
$next = $_GET['next'] ?? '../products/products.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo $page_title; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/login.css">
    <link rel="stylesheet" href="../../assets/css/register.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-logo"><span class="logo-icon"><i class="fas fa-crown"></i></span><span class="logo-text">Royal<span>Tech</span></span></div>
    <h2 class="form-title">Área de Login</h2>

    <?php if (!empty($feedbackErrors)): ?>
        <div class="auth-feedback auth-feedback-error" role="alert">
            <strong>Não foi possível entrar:</strong>
            <ul>
                <?php foreach ($feedbackErrors as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($feedbackSuccess): ?>
        <div class="auth-feedback auth-feedback-success" role="status"><?php echo htmlspecialchars($feedbackSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form class="login-form" action="authentication.php" method="POST">
        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="admin-form-group">
            <label for="identifier">E-mail ou Nome de Usuário</label>
            <input type="text" id="identifier" name="identifier" value="<?php echo htmlspecialchars($old['identifier'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="admin-form-group">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="password" required minlength="8">
            <button type="button" class="password-toggle" id="togglePassword"><i class="fas fa-eye"></i></button>
        </div>
        <button type="submit" class="btn-auth"><i class="fas fa-sign-in-alt"></i> Entrar</button>
    </form>

    <div class="auth-footer">Ainda não tem conta? <a href="register.php">Crie uma agora</a></div>
    <a href="../../index.php" class="back-to-site"><i class="fas fa-arrow-left"></i> Voltar para o site</a>
</div>
<script>
const toggleBtn = document.getElementById('togglePassword');
const passwordInput = document.getElementById('senha');
if (toggleBtn && passwordInput) {
  toggleBtn.addEventListener('click', () => {
    passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
    const icon = toggleBtn.querySelector('i');
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
  });
}
</script>
</body>
</html>
