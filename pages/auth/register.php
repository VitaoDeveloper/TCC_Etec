<?php
session_start();
require_once __DIR__ . '/../../includes/csrf.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../products/products.php');
    exit;
}

$feedbackErrors = $_SESSION['auth_errors'] ?? [];
$old = $_SESSION['auth_old'] ?? [];
unset($_SESSION['auth_errors'], $_SESSION['auth_old']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Royal Tech - Cadastro de Usuário</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/register.css">
</head>
<body class="register-page">
  <div class="register-container">
    <div class="register-box">
      <div class="register-header">
        <div class="register-logo">
          <span class="logo-icon"><i class="fas fa-crown"></i></span>
          <span class="logo-text">Royal<span>Tech</span></span>
        </div>
        <h2>Criar Nova Conta</h2>
        <p>Preencha os dados para se cadastrar</p>
      </div>

      <?php if (!empty($feedbackErrors)): ?>
        <div class="auth-feedback auth-feedback-error" role="alert">
          <strong>Corrija os campos abaixo para continuar:</strong>
          <ul>
            <?php foreach ($feedbackErrors as $error): ?>
              <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form id="registerForm" class="register-form" method="POST" action="insertion.php">
        <?php echo csrf_field(); ?>
        <div class="admin-form-group"><label for="name">Nome completo</label><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required minlength="3"></div>
        <div class="admin-form-group"><label for="email">E-mail</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
        <div class="admin-form-group"><label for="username">Nome de usuário</label><input type="text" id="username" name="username" value="<?php echo htmlspecialchars($old['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required minlength="4"></div>
        <div class="admin-form-group"><label for="postalcode">CEP</label><input type="text" id="postalcode" pattern="[0-9]{5}-?[0-9]{3}" inputmode="numeric" name="postalcode" value="<?php echo htmlspecialchars($old['postalcode'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
        <div class="admin-form-group"><label for="street">Rua</label><input type="text" id="street" name="street" value="<?php echo htmlspecialchars($old['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required minlength="4"></div>
        <div class="admin-form-group"><label for="number">Número</label><input type="number" id="number" name="number" value="<?php echo htmlspecialchars($old['number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
        <div class="admin-form-group"><label for="complement">Complemento (Apto, etc)</label><input type="text" id="complement" name="complement" value="<?php echo htmlspecialchars($old['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="admin-form-group"><label for="senha">Senha</label><input type="password" id="senha" name="password" required minlength="8"><button type="button" class="password-toggle" id="togglePassword" aria-label="Mostrar ou ocultar senha"><i class="fas fa-eye"></i></button></div>
        <div class="admin-form-group"><label for="confirm_senha">Confirmar senha</label><input type="password" id="confirm_senha" required></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Cadastrar</button>
      </form>

      <div class="form-footer">Já possui uma conta? <a href="login.php">Faça login</a></div>
    </div>
  </div>

  <script>
  const toggleBtn = document.getElementById('togglePassword');
  const confirmPassword = document.getElementById('confirm_senha');
  const passwordInput = document.getElementById('senha');
  const form = document.getElementById('registerForm');

  if (toggleBtn && passwordInput) {
    toggleBtn.addEventListener('click', () => {
      passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
      const icon = toggleBtn.querySelector('i');
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    });
  }

  form.addEventListener('submit', (event) => {
    if (passwordInput.value !== confirmPassword.value) {
      event.preventDefault();
      alert('As senhas não coincidem.');
    }
  });
  </script>
</body>
</html>
