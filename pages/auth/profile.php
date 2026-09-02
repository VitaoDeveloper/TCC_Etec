<?php
$page_title = 'Meu Perfil - Royal Tech';
$breadcrumb_title = 'Meu Perfil';
$current_page = 'perfil';
$base_path = '../../';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once __DIR__ . '/../../includes/csrf.php';
include '../../database/connection.php';
$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
    $street = trim((string) ($_POST['street'] ?? ''));
    $numberRaw = trim((string) ($_POST['number'] ?? ''));
    if (preg_match('/^\d{1,6}$/', $numberRaw)) {
        $number = $numberRaw;
    } elseif (strtoupper($numberRaw) === 'S/N') {
        $number = 'S/N';
    } else {
        $number = $numberRaw ?: 'S/N';
    }
    $complement = trim((string) ($_POST['complement'] ?? ''));
    $currentPass = (string) ($_POST['current_password'] ?? '');
    $newPass = (string) ($_POST['new_password'] ?? '');

    if ($name === '' || $email === '' || $username === '') {
        $errorMessage = 'Nome, e-mail e usuário são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'E-mail inválido.';
    } else {
        try {
            $stmtCheck = $pdo->prepare('SELECT id FROM e5_users WHERE (email = :email OR username = :username) AND id != :id LIMIT 1');
            $stmtCheck->execute([':email' => $email, ':username' => $username, ':id' => $userId]);
            if ($stmtCheck->fetch()) {
                $errorMessage = 'E-mail ou usuário já em uso.';
            } else {
                $sql = 'UPDATE e5_users SET name = :name, email = :email, username = :username, postal_code = :postal_code, street = :street, number = :number, complement = :complement WHERE id = :id';
                $params = [':name' => $name, ':email' => $email, ':username' => $username, ':postal_code' => $postalCode, ':street' => $street, ':number' => $number, ':complement' => $complement ?: null, ':id' => $userId];

                if ($newPass !== '') {
                    if (!password_verify($currentPass, $user['password'])) {
                        $errorMessage = 'Senha atual incorreta.';
                    } elseif (strlen($newPass) < 6) {
                        $errorMessage = 'Nova senha deve ter no mínimo 6 caracteres.';
                    } else {
                        $sql = 'UPDATE e5_users SET name = :name, email = :email, username = :username, postal_code = :postal_code, street = :street, number = :number, complement = :complement, password = :password WHERE id = :id';
                        $params[':password'] = password_hash($newPass, PASSWORD_DEFAULT);
                    }
                }

                if (!$errorMessage) {
                    $pdo->prepare($sql)->execute($params);
                    $successMessage = 'Dados atualizados com sucesso!';
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['username'] = $username;
                    $user['postal_code'] = $postalCode;
                    $user['street'] = $street;
                    $user['number'] = $number;
                    $user['complement'] = $complement;
                }
            }
        } catch (Throwable $e) {
            $errorMessage = 'Erro ao atualizar perfil.';
            error_log('Profile error: ' . $e->getMessage());
        }
    }
}

include '../../components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;"><div class="container" style="max-width:600px; margin:0 auto;">
    <div class="ml-section-header">
        <h2 class="ml-section-title">Meu Perfil</h2>
    </div>
    <?php if ($successMessage): ?><div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <?php if ($errorMessage): ?><div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="POST" class="ml-card">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 15px;">
            <div class="auth-field"><label class="auth-label" for="name">Nome</label><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div class="auth-field"><label class="auth-label" for="email">E-mail</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div class="auth-field"><label class="auth-label" for="username">Usuário</label><input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div class="auth-field"><label class="auth-label" for="postal_code">CEP</label><div class="auth-input-wrap"><input type="text" id="postal_code" class="cep-mask" pattern="[0-9]{5}-?[0-9]{3}" inputmode="numeric" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div><div class="cep-feedback" id="cepFeedback" hidden></div></div>
            <div class="auth-field"><label class="auth-label" for="street">Rua</label><input type="text" id="street" name="street" value="<?php echo htmlspecialchars($user['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="auth-field"><label class="auth-label" for="number">Número</label><input type="text" id="number" name="number" inputmode="numeric" pattern="[0-9]{1,6}" maxlength="6" value="<?php echo (int)($user['number'] ?? 0); ?>"></div>
            <div class="auth-field"><label class="auth-label" for="complement">Complemento</label><input type="text" id="complement" name="complement" value="<?php echo htmlspecialchars($user['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
        </div>
        <hr style="border-color:var(--ml-border); margin:20px 0;">
        <h4 style="margin-bottom:15px;">Alterar Senha (opcional)</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 15px;">
            <div class="auth-field"><label class="auth-label" for="current_password">Senha Atual</label><input type="password" id="current_password" name="current_password" placeholder="Deixe em branco para manter"></div>
            <div class="auth-field"><label class="auth-label" for="new_password">Nova Senha</label><input type="password" id="new_password" name="new_password" placeholder="Mínimo 6 caracteres" minlength="6"></div>
        </div>
        <?php echo csrf_field(); ?>
        <button type="submit" class="ml-btn ml-btn-primary ml-btn-block"><i class="fas fa-save"></i> Salvar Alterações</button>
    </form>
    <div style="text-align:center; margin-top:15px;"><a href="orders.php" class="ml-btn"><i class="fas fa-box"></i> Meus Pedidos</a></div>
</div></section>
<?php include '../../components/footer.php'; ?>

<script>
  const cepInput = document.getElementById('postal_code');
  const streetInput = document.getElementById('street');
  const cepFeedback = document.getElementById('cepFeedback');
  let cepTimer = null;
  let cepController = null;

  function showCepFeedback(msg, type) {
    if (!cepFeedback) return;
    cepFeedback.hidden = false;
    cepFeedback.textContent = msg;
    cepFeedback.className = 'cep-feedback' + (type ? ' ' + type : '');
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