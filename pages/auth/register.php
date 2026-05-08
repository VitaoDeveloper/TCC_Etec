<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ../products/products.php');
    exit;
}
$feedbackMessage = $_SESSION['auth_error'] ?? null;
unset($_SESSION['auth_error']);
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0"/><title>Royal Tech - Cadastro de Usuário</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><link rel="stylesheet" href="../../assets/css/style.css"><link rel="stylesheet" href="../../assets/css/admin.css"><link rel="stylesheet" href="../../assets/css/register.css"><link rel="stylesheet" href="../../assets/css/login.css"></head><body>
<div class="register-wrapper"><div class="register-logo"><span class="logo-icon"><i class="fas fa-crown"></i></span><span class="logo-text">Royal<span>Tech</span></span></div>
<h2 class="form-title">Criar Nova Conta</h2>
<?php if ($feedbackMessage): ?><div class="auth-feedback auth-feedback-error" role="alert"><?php echo htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form id="registerForm" method="POST" action="insertion.php">
<div class="admin-form-group"><label for="nome">Nome completo</label><input type="text" id="nome" name="name" required minlength="3"></div>
<div class="admin-form-group"><label for="email">E-mail</label><input type="email" id="email" name="email" required></div>
<div class="admin-form-group"><label for="username">Nome de usuário</label><input type="text" id="username" name="username" required minlength="4"></div>
<div class="admin-form-group"><label for="postalcode">CEP</label><input type="text" id="postalcode" pattern="[0-9]{5}-?[0-9]{3}" inputmode="numeric" name="postalcode" required minlength="8" maxlength="9"></div>
<div class="admin-form-group"><label for="street">Rua</label><input type="text" id="street" name="street" required minlength="4"></div>
<div class="admin-form-group"><label for="number">Número</label><input type="number" id="number" name="number" required></div>
<div class="admin-form-group"><label for="complement">Complemento (Apto, etc)</label><input type="text" id="complement" name="complement"></div>
<div class="admin-form-group"><label for="senha">Senha</label><input type="password" id="senha" name="password" required minlength="8"><button type="button" class="password-toggle" id="togglePassword"><i class="fas fa-eye"></i></button></div>
<div class="admin-form-group"><label for="confirm_senha">Confirmar senha</label><input type="password" id="confirm_senha" required></div>
<button type="submit" class="btn-register register"><i class="fas fa-user-plus"></i> Cadastrar</button></form>
<div class="form-footer">Já possui uma conta? <a href="login.php">Faça login</a></div></div>
<script>
const t=document.getElementById('togglePassword');const c=document.getElementById('confirm_senha');const s=document.getElementById('senha');document.getElementById('registerForm').addEventListener('submit',e=>{if(s.value!==c.value){e.preventDefault();alert('As senhas não coincidem')}});if(t&&s){t.addEventListener('click',()=>{s.type=s.type==='password'?'text':'password';t.querySelector('i').classList.toggle('fa-eye');t.querySelector('i').classList.toggle('fa-eye-slash');});}
</script></body></html>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Royal Tech - Cadastro de Usuário</title>

  <!-- Fontes Google -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Seus estilos -->
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/admin.css">
  <link rel="stylesheet" href="../../assets/css/register.css">
  <link rel="stylesheet" href="../../assets/css/login.css">
</head>
<body>

  <div class="register-wrapper">

    <!-- Logo centralizada -->
    <div class="register-logo">
      <span class="logo-icon"><i class="fas fa-crown"></i></span>
      <span class="logo-text">Royal<span>Tech</span></span>
    </div>

    <h2 class="form-title">Criar Nova Conta</h2>

    <form id="registerForm" method="POST" action="insertion.php">

      <div class="admin-form-group">
        <label for="nome">Nome completo</label>
        <input type="text" id="nome" name="name" placeholder="João Silva" required minlength="3">
        <div class="error-message" id="nome-error">Nome muito curto</div>
      </div>

      <div class="admin-form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" placeholder="seuemail@royaltech.com" required>
        <div class="error-message" id="email-error">E-mail inválido</div>
      </div>

      <div class="admin-form-group">
        <label for="username">Nome de usuário</label>
        <input type="text" id="username" name="username" placeholder="joaosilva" required minlength="4">
        <div class="error-message" id="username-error">Mínimo 4 caracteres</div>
      </div>

      <div class="admin-form-group">
        <label for="cep">CEP</label>
        <input type="text" id="postalcode" pattern="[0-9]{5}-?[0-9]{3}" inputmode="numeric" name="postalcode" placeholder="00000-000" required minlength="8" maxlength="8">
        <div class="error-message" id="username-error">Mínimo 4 caracteres</div>
      </div>

      <div class="admin-form-group">
        <label for="street">Rua</label>
        <input type="text" id="street" name="street" placeholder="Rua Clóvis Basílio" required minlength="4">
        <div class="error-message" id="username-error">Mínimo 4 caracteres</div>
      </div>

      <div class="admin-form-group">
        <label for="number">Número</label>
        <input type="number" id="number" name="number" placeholder="000" required maxlength="5">
        <div class="error-message" id="username-error">Mínimo 4 caracteres</div>
      </div>

      <div class="admin-form-group">
        <label for="complement">Complemento (Apto, etc)</label>
        <input type="text" id="complement" name="complement" placeholder="Opcional" minlength="4">
        <div class="error-message" id="username-error">Mínimo 4 caracteres</div>
      </div>

      <div class="admin-form-group">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="password" placeholder="••••••••••••" required minlength="8">
        <button type="button" class="password-toggle" id="togglePassword">
          <i class="fas fa-eye"></i>
        </button>
        <div class="error-message" id="senha-error">Mínimo 8 caracteres</div>
      </div>

      <div class="admin-form-group">
        <label for="confirm_senha">Confirmar senha</label>
        <input type="password" id="confirm_senha" placeholder="••••••••••••" required>
        <div class="error-message" id="confirm-error">As senhas não coincidem</div>
      </div>

      <button type="submit" name="btn" class="btn-register register" value="register">
        <i class="fas fa-user-plus"></i> Cadastrar
      </button>

    </form>

    <div class="form-footer">
      Já possui uma conta? <a href="login.php">Faça login</a>
    </div>

  </div>

  <script>
    // Toggle de visualização da senha
    const toggleBtn = document.getElementById('togglePassword');
    const confirmarSenha = document.getElementById('confirm_senha')
    const senhaInput = document.getElementById('senha');
    const form = document.getElementById('registerForm')

    if (toggleBtn && senhaInput) {
      toggleBtn.addEventListener('click', () => {
        const type = senhaInput.type === 'password' ? 'text' : 'password';
        senhaInput.type = type;
        
        const icon = toggleBtn.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
      });
    }

    form.addEventListener("submit", function(event) {
    if (senhaInput.value !== confirmarSenha.value) {
      event.preventDefault(); // Impede envio do form
      alert('As senhas não coincidem')
    } 
  });
  </script>

</body>
</html>
