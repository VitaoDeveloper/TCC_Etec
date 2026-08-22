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
  <link rel="stylesheet" href="../../assets/css/mercadolivre-style.css">
  <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body class="auth-page">
  <main class="auth-shell">
    <aside class="auth-brand">
      <a href="../../index.php" class="auth-brand-logo" aria-label="Royal Tech — Página inicial">
        <span class="logo-icon"><i class="fas fa-crown"></i></span>
        <span class="logo-text">Royal<span>Tech</span></span>
      </a>
      <p class="auth-brand-tagline">Tecnologia premium para quem exige o melhor.</p>
      <ul class="auth-brand-perks">
        <li><i class="fas fa-shield-halved"></i> Compra 100% segura</li>
        <li><i class="fas fa-truck-fast"></i> Frete grátis acima de R$ 500</li>
        <li><i class="fas fa-qrcode"></i> 5% de desconto no PIX</li>
      </ul>
    </aside>

    <section class="auth-panel">
      <div class="auth-card">
        <h1 class="auth-title">Criar Nova Conta</h1>
        <p class="auth-subtitle">Preencha os dados para se cadastrar</p>

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

        <form id="registerForm" class="auth-form" method="POST" action="insertion.php">
          <?php echo csrf_field(); ?>
          <div class="auth-field"><label class="auth-label" for="name">Nome completo</label><div class="auth-input-wrap"><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required minlength="3"></div></div>
          <div class="auth-field"><label class="auth-label" for="email">E-mail</label><div class="auth-input-wrap"><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div></div>
          <div class="auth-field"><label class="auth-label" for="username">Nome de usuário</label><div class="auth-input-wrap"><input type="text" id="username" name="username" value="<?php echo htmlspecialchars($old['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required minlength="4"></div></div>
          <div class="auth-field"><label class="auth-label" for="postalcode">CEP</label><div class="auth-input-wrap"><input type="text" id="postalcode" pattern="[0-9]{5}-?[0-9]{3}" inputmode="numeric" name="postalcode" value="<?php echo htmlspecialchars($old['postalcode'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div><div class="cep-feedback" id="cepFeedback" hidden></div></div>
          <div class="auth-field"><label class="auth-label" for="street">Rua</label><div class="auth-input-wrap"><input type="text" id="street" name="street" value="<?php echo htmlspecialchars($old['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required minlength="4"></div></div>
          <div class="auth-field"><label class="auth-label" for="number">Número</label><div class="auth-input-wrap"><input type="text" id="number" name="number" inputmode="numeric" pattern="[0-9]{1,6}" maxlength="6" value="<?php echo htmlspecialchars($old['number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div></div>
          <div class="auth-field"><label class="auth-label" for="complement">Complemento (Apto, etc)</label><div class="auth-input-wrap"><input type="text" id="complement" name="complement" value="<?php echo htmlspecialchars($old['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div></div>
          <div class="auth-field"><label class="auth-label" for="senha">Senha</label><div class="auth-input-wrap"><input type="password" id="senha" name="password" required minlength="8"><button type="button" class="password-toggle" id="togglePassword" aria-label="Mostrar ou ocultar senha"><i class="fas fa-eye"></i></button></div></div>
          <div class="auth-field"><label class="auth-label" for="confirm_senha">Confirmar senha</label><div class="auth-input-wrap"><input type="password" id="confirm_senha" required></div></div>
          <button type="submit" class="auth-submit"><i class="fas fa-user-plus"></i> Cadastrar</button>
        </form>

        <div class="auth-alt">Já possui uma conta? <a href="login.php">Faça login</a></div>

        <div class="auth-back">
          <a href="../../index.php"><i class="fas fa-arrow-left"></i> Voltar para o site</a>
        </div>
      </div>
    </section>
  </main>

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

  const cepInput = document.getElementById('postalcode');
  const streetInput = document.getElementById('street');
  const cepFeedback = document.getElementById('cepFeedback');
  let cepTimer = null;
  let cepController = null;

  function showCepFeedback(message, type) {
    if (!cepFeedback) return;
    cepFeedback.textContent = message;
    cepFeedback.className = 'cep-feedback' + (type ? ' ' + type : '');
    cepFeedback.hidden = false;
  }

  function hideCepFeedback() {
    if (!cepFeedback) return;
    cepFeedback.hidden = true;
    cepFeedback.textContent = '';
    cepFeedback.className = 'cep-feedback';
  }

  function lookupCep() {
    const cep = (cepInput.value || '').replace(/\D/g, '');
    if (cep.length !== 8) {
      hideCepFeedback();
      return;
    }
    if (cepController) cepController.abort();
    cepController = new AbortController();
    const myController = cepController;
    const timeoutId = setTimeout(() => cepController.abort(), 6000);
    showCepFeedback('Consultando CEP...', '');
    fetch('https://viacep.com.br/ws/' + cep + '/json/', { signal: myController.signal })
      .then((response) => response.json())
      .then((data) => {
        clearTimeout(timeoutId);
        if (data.erro) {
          showCepFeedback('CEP não encontrado. Verifique o número digitado — você ainda pode preencher a rua manualmente.', 'error');
          return;
        }
        if (data.logradouro && streetInput) streetInput.value = data.logradouro;
        const parts = [];
        if (data.bairro) parts.push(data.bairro);
        if (data.localidade) parts.push(data.localidade);
        const summary = parts.join(', ') + (data.uf ? ' - ' + data.uf : '');
        showCepFeedback(summary ? 'Endereço encontrado: ' + summary : 'CEP encontrado.', 'ok');
      })
      .catch((err) => {
        clearTimeout(timeoutId);
        if (myController !== cepController) return;
        if (err && err.name === 'AbortError') {
          showCepFeedback('A consulta do CEP demorou demais. Preencha a rua manualmente se preferir.', 'error');
        } else {
          showCepFeedback('Não foi possível consultar o CEP agora. Preencha a rua manualmente.', 'error');
        }
      });
  }

  if (cepInput) {
    cepInput.addEventListener('input', () => {
      clearTimeout(cepTimer);
      cepTimer = setTimeout(lookupCep, 400);
    });
    cepInput.addEventListener('blur', () => {
      clearTimeout(cepTimer);
      lookupCep();
    });
  }
  </script>
</body>
</html>
