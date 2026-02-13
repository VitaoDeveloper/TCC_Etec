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

  <style>
    /* Estilos específicos da página de registro */
    body {
      background: var(--color-black);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      overflow: hidden;           /* remove scroll da página inteira */
    }

    .register-wrapper {
      width: 100%;
      max-width: 520px;
      background: var(--color-black-light);
      border: 1px solid var(--color-border);
      border-radius: 12px;
      padding: 45px 40px;
      box-shadow: var(--shadow);
      max-height: 95vh;
      overflow-y: auto;           /* scroll só dentro do card se precisar */
    }

    .register-logo {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }

    .register-logo .logo-icon {
      font-size: 2.8rem;
      color: var(--color-primary);
    }

    .register-logo .logo-text {
      font-size: 2.6rem;
      font-weight: 700;
      letter-spacing: 1px;
    }

    .register-logo .logo-text span {
      color: var(--color-primary);
    }

    .form-title {
      font-size: 1.6rem;
      margin: 0 0 35px;
      text-align: center;
      color: var(--color-white);
    }

    .admin-form-group {
      position: relative;
      margin-bottom: 25px;
    }

    .admin-form-group input:focus,
    .admin-form-group select:focus {
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
    }

    .password-toggle {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--color-gray-light);
      cursor: pointer;
      font-size: 1.3rem;
      background: transparent;
      border: none;
    }

    .btn-register {
      width: 100%;
      padding: 16px;
      background: var(--color-primary);
      color: var(--color-black);
      border: none;
      border-radius: 30px;
      font-size: 1.05rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      cursor: pointer;
      transition: var(--transition);
      margin-top: 20px;
    }

    .btn-register:hover {
      background: var(--color-primary-dark);
      transform: translateY(-2px);
      box-shadow: var(--shadow-hover);
    }

    .form-footer {
      text-align: center;
      margin-top: 30px;
      color: var(--color-gray-light);
      font-size: 0.95rem;
    }

    .form-footer a {
      color: var(--color-primary);
      font-weight: 500;
      text-decoration: none;
    }

    .form-footer a:hover {
      text-decoration: underline;
    }

    .error-message {
      color: #ff6b6b;
      font-size: 0.85rem;
      margin-top: 6px;
      display: none;
    }

    @media (max-width: 576px) {
      .register-wrapper {
        padding: 35px 25px;
      }
      .register-logo .logo-text {
        font-size: 2.2rem;
      }
      .register-logo .logo-icon {
        font-size: 2.4rem;
      }
    }
  </style>
</head>
<body>

  <div class="register-wrapper">

    <!-- Logo centralizada -->
    <div class="register-logo">
      <span class="logo-icon"><i class="fas fa-crown"></i></span>
      <span class="logo-text">Royal<span>Tech</span></span>
    </div>

    <h2 class="form-title">Criar Nova Conta</h2>

    <form id="registerForm" novalidate>

      <div class="admin-form-group">
        <label for="nome">Nome completo</label>
        <input type="text" id="nome" name="nome" placeholder="João Silva" required minlength="3">
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
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" placeholder="••••••••••••" required minlength="8">
        <button type="button" class="password-toggle" id="togglePassword">
          <i class="fas fa-eye"></i>
        </button>
        <div class="error-message" id="senha-error">Mínimo 8 caracteres</div>
      </div>

      <div class="admin-form-group">
        <label for="confirm_senha">Confirmar senha</label>
        <input type="password" id="confirm_senha" name="confirm_senha" placeholder="••••••••••••" required>
        <div class="error-message" id="confirm-error">As senhas não coincidem</div>
      </div>

      <button type="submit" class="btn-register">
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
    const senhaInput = document.getElementById('senha');

    if (toggleBtn && senhaInput) {
      toggleBtn.addEventListener('click', () => {
        const type = senhaInput.type === 'password' ? 'text' : 'password';
        senhaInput.type = type;
        
        const icon = toggleBtn.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
      });
    }
  </script>

</body>
</html>