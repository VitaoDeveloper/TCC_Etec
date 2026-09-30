<?php

declare(strict_types=1);

/**
 * Perfil — dados pessoais, endereço, notificações e senha.
 *
 * Reescrita da tela que era um formulário de 600px com a senha no meio
 * dos dados (duas vezes), sem sidebar e sem avatar. Agora mora no shell
 * da conta como um "card de menu": o avatar abre a troca de foto, o card
 * de menu guarda as três entradas (Dados Pessoais, Endereço,
 * Notificações e senha) e cada entrada abre o painel correspondente.
 *
 * A página funciona 100% sem JavaScript: cada painel é um POST com PRG
 * e o redirect devolve o visitante ao mesmo painel (#tab-*). Com JS, o
 * card de menu troca de painel na hora e o salvamento dos dados pessoais
 * é feito via fetch na API, sem recarregar a página.
 *
 * As seções internas preservam os ids/attrs usados pelos testes de
 * render (secao-dados, secao-endereco, secao-avisos, secao-senha,
 * passwordMasked, current_password, data-profile-form, data-save-status).
 */

require_once __DIR__ . '/../../includes/account_layout.php';
require_once __DIR__ . '/../../includes/validators.php';
require_once __DIR__ . '/../../database/connection.php';

$user = account_require_login($pdo);

// ---------------------------------------------------------------------
//  Flash (PRG): o redirect retorna com mensagem para ser exibida aqui.
// ---------------------------------------------------------------------
$flash = [
    'type'    => 'ok',
    'message' => '',
];
foreach (['error', 'success'] as $key) {
    if (isset($_SESSION[$key])) {
        $flash = [
            'type'    => $key === 'success' ? 'ok' : 'error',
            'message' => (string) $_SESSION[$key],
        ];
        unset($_SESSION[$key]);
        break;
    }
}

// ---------------------------------------------------------------------
//  Painel de retorno: cada form leva um hidden "panel" para o redirect
//  PRG voltar à entrada do card de menu em que o usuário estava.
// ---------------------------------------------------------------------
$savedPanel = 'dados';

function account_panel_from_post(?string $panel): string
{
    return in_array($panel, ['dados', 'endereco', 'preferencias'], true) ? $panel : 'dados';
}

// ---------------------------------------------------------------------
//  Avatar
// ---------------------------------------------------------------------
$avatarError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'avatar') {
    csrf_require_valid();

    if (empty($_SESSION['user_id'])) {
        $avatarError .= 'Sessão expirada.';
    } elseif (empty($_FILES['avatar']['name'])) {
        $avatarError = 'Escolha uma imagem antes de enviar.';
    } else {
        $file = $_FILES['avatar'];
        $errorCode = (int) $file['error'];

        if ($errorCode !== UPLOAD_ERR_OK) {
            $avatarError = match ($errorCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem excede o tamanho máximo de 2 MB.',
                UPLOAD_ERR_NO_FILE => 'Nenhuma imagem enviada.',
                UPLOAD_ERR_PARTIAL => 'O upload foi interrompido. Tente novamente.',
                default => 'Não foi possível enviar a imagem.',
            };
        } else {
            $tmpName = (string) $file['tmp_name'];
            $size    = (int) $file['size'];

            if ($size > 2 * 1024 * 1024) {
                $avatarError = 'A imagem excede o tamanho máximo de 2 MB.';
            } else {
                $info      = @getimagesize($tmpName);
                $mime      = $info['mime'] ?? '';
                $supported = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                if (!isset($supported[$mime])) {
                    $avatarError = 'Formato não aceito. Use JPG, PNG ou WebP.';
                } else {
                    $dir = dirname(__DIR__, 2) . '/assets/uploads/avatars';
                    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                        $avatarError = 'Não foi possível gravar a imagem no servidor.';
                    } else {
                        if (!is_writable($dir)) {
                            $avatarError = 'A pasta de avatares não tem permissão de escrita.';
                        } else {
                            $oldPath  = (string) ($user['avatar_path'] ?? '');
                            $filename = 'u' . $_SESSION['user_id'] . '-' . bin2hex(random_bytes(6)) . '.' . $supported[$mime];

                            if (!move_uploaded_file($tmpName, $dir . '/' . $filename)) {
                                $avatarError = 'Não foi possível salvar a imagem.';
                            } else {
                                // Limpa o avatar antigo se ele era upload
                                // nosso (nunca um placeholder do tema).
                                if ($oldPath !== '' && str_contains($oldPath, 'uploads/avatars/')) {
                                    $oldAbs = dirname(__DIR__, 2) . '/' . ltrim($oldPath, '/');
                                    if (is_file($oldAbs)) {
                                        @unlink($oldAbs);
                                    }
                                }

                                $relative = 'uploads/avatars/' . $filename;
                                $pdo->prepare('UPDATE e5_users SET avatar_path = :path WHERE id = :id')
                                    ->execute([':path' => $relative, ':id' => $_SESSION['user_id']]);
                                $user['avatar_path'] = $relative;

                                $_SESSION['success'] = 'Foto de perfil atualizada!';
                                header('Location: ' . base_url('pages/auth/profile.php'));
                                exit;
                            }
                        }
                    }
                }
            }
        }
    }

    if ($avatarError !== null) {
        $_SESSION['error'] = $avatarError;
        header('Location: ' . base_url('pages/auth/profile.php'));
        exit;
    }
}

// ---------------------------------------------------------------------
//  Dados pessoais e endereço (mesmo action; o painel vem no hidden)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'personal') {
    csrf_require_valid();

    $savedPanel   = account_panel_from_post((string) ($_POST['panel'] ?? ''));
    $returnTo     = base_url('pages/auth/profile.php') . '#tab-' . $savedPanel;

    $input = [
        'name'        => clean_text($_POST['name'] ?? ''),
        'email'       => strtolower(trim((string) ($_POST['email'] ?? ''))),
        'username'    => clean_text($_POST['username'] ?? ''),
        'cpf'         => only_digits($_POST['cpf'] ?? ''),
        'phone'       => only_digits($_POST['phone'] ?? ''),
        'postal_code' => format_cep(only_digits($_POST['postal_code'] ?? '')),
        'street'      => clean_text($_POST['street'] ?? ''),
        'number'      => clean_text($_POST['number'] ?? ''),
        'complement'  => clean_text($_POST['complement'] ?? ''),
        'neighborhood'=> clean_text($_POST['neighborhood'] ?? ''),
        'city'        => clean_text($_POST['city'] ?? ''),
        'state'       => strtoupper(clean_text($_POST['state'] ?? '')),
    ];

    $errors = [];

    // Username so e validado quando muda (contas legadas podem ter um
    // valor fora do formato novo enquanto nao for alterado).
    $stmt = $pdo->prepare('SELECT username FROM e5_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $currentUsername = (string) $stmt->fetchColumn();
    $usernameChanged = $input['username'] !== $currentUsername;

    if ($input['name'] === '') {
        $errors['name'] = 'Informe seu nome.';
    } elseif (mb_strlen($input['name']) > 80) {
        $errors['name'] = 'O nome deve ter no máximo 80 caracteres.';
    }

    if (!is_valid_email($input['email'])) {
        $errors['email'] = 'E-mail inválido.';
    }

    if ($usernameChanged && !is_valid_username($input['username'])) {
        $errors['username'] = 'Usuário inválido: use letras, números, ponto, hífen ou sublinhado, com 3 a 30 caracteres.';
    }

    if (!is_valid_cpf($input['cpf'])) {
        $errors['cpf'] = 'CPF inválido.';
    }

    if ($input['phone'] !== '' && !in_array(strlen($input['phone']), [10, 11, 13], true)) {
        $errors['phone'] = 'Telefone inválido: use DDD + número.';
    }

    if ($input['postal_code'] !== '' && !is_valid_cep(only_digits($input['postal_code']))) {
        $errors['postal_code'] = 'CEP inválido.';
    }

    if ($input['state'] !== '' && !is_valid_uf($input['state'])) {
        $errors['state'] = 'UF inválida.';
    }

    if ($errors === []) {
        // Unicidade por campo; o username legado só passa quando mudou.
        $stmt = $pdo->prepare('SELECT id FROM e5_users WHERE email = :email AND id != :id LIMIT 1');
        $stmt->execute([
            ':email' => $input['email'],
            ':id'    => (int) $_SESSION['user_id'],
        ]);

        if ($stmt->fetch()) {
            $errors['email'] = 'Este e-mail já está em uso por outra conta.';
        }

        if ($usernameChanged) {
            $stmt = $pdo->prepare('SELECT id FROM e5_users WHERE username = :username AND id != :id LIMIT 1');
            $stmt->execute([
                ':username' => $input['username'],
                ':id'       => (int) $_SESSION['user_id'],
            ]);

            if ($stmt->fetch()) {
                $errors['username'] = 'Este nome de usuário já está em uso por outra conta.';
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare(
                'UPDATE e5_users SET
                    name = :name, email = :email, username = :username, cpf = :cpf,
                    phone = :phone, postal_code = :postal_code, street = :street,
                    number = :number, complement = :complement,
                    neighborhood = :neighborhood, city = :city, state = :state,
                    updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name'         => $input['name'],
                ':email'        => $input['email'],
                ':username'     => $input['username'],
                ':cpf'          => $input['cpf'],
                ':phone'        => $input['phone'] !== '' ? $input['phone'] : null,
                ':postal_code'  => $input['postal_code'],
                ':street'       => $input['street'],
                ':number'       => $input['number'],
                ':complement'   => $input['complement'] !== '' ? $input['complement'] : null,
                ':neighborhood' => $input['neighborhood'] !== '' ? $input['neighborhood'] : null,
                ':city'         => $input['city'] !== '' ? $input['city'] : null,
                ':state'        => $input['state'] !== '' ? $input['state'] : null,
                ':id'           => (int) $_SESSION['user_id'],
            ]);

            $_SESSION['success'] = 'Seus dados foram atualizados.';
            header('Location: ' . $returnTo);
            exit;
        }
    }

    if ($errors !== []) {
        $message = reset($errors);
        $_SESSION['error'] = $message;
        header('Location: ' . $returnTo . '&focus=' . urlencode(array_key_first($errors)));
        exit;
    }
}

// ---------------------------------------------------------------------
//  Endereço (painel Endereço; atualiza apenas os campos de endereco)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'address') {
    csrf_require_valid();

    $savedPanel = account_panel_from_post((string) ($_POST['panel'] ?? ''));
    $returnTo   = base_url('pages/auth/profile.php') . '#tab-endereco';

    $input = [
        'postal_code' => format_cep(only_digits($_POST['postal_code'] ?? '')),
        'street'      => clean_text($_POST['street'] ?? ''),
        'number'      => clean_text($_POST['number'] ?? ''),
        'complement'  => clean_text($_POST['complement'] ?? ''),
        'neighborhood'=> clean_text($_POST['neighborhood'] ?? ''),
        'city'        => clean_text($_POST['city'] ?? ''),
        'state'       => strtoupper(clean_text($_POST['state'] ?? '')),
    ];

    $errors = [];

    if ($input['postal_code'] !== '' && !is_valid_cep(only_digits($input['postal_code']))) {
        $errors['postal_code'] = 'CEP inválido.';
    }

    if ($input['street'] === '') {
        $errors['street'] = 'Informe a rua.';
    }

    if ($input['number'] === '') {
        $errors['number'] = 'Informe o número.';
    } elseif (!ctype_digit($input['number'])) {
        $errors['number'] = 'Número inválido: use apenas dígitos.';
    }

    if ($input['city'] === '') {
        $errors['city'] = 'Informe a cidade.';
    }

    if ($input['state'] !== '' && !is_valid_uf($input['state'])) {
        $errors['state'] = 'UF inválida.';
    }

    if ($errors !== []) {
        $message = reset($errors);
        $_SESSION['error'] = $message;
        header('Location: ' . $returnTo . '&focus=' . urlencode(array_key_first($errors)));
        exit;
    }

    $pdo->prepare(
        'UPDATE e5_users SET
            postal_code = :postal_code, street = :street, number = :number,
            complement = :complement, neighborhood = :neighborhood,
            city = :city, state = :state, updated_at = NOW()
         WHERE id = :id'
    )->execute([
        ':postal_code'  => $input['postal_code'],
        ':street'       => $input['street'],
        ':number'       => $input['number'],
        ':complement'   => $input['complement'] !== '' ? $input['complement'] : null,
        ':neighborhood' => $input['neighborhood'] !== '' ? $input['neighborhood'] : null,
        ':city'         => $input['city'],
        ':state'        => $input['state'] !== '' ? $input['state'] : null,
        ':id'           => (int) $_SESSION['user_id'],
    ]);

    $_SESSION['success'] = 'Endereço atualizado.';
    header('Location: ' . $returnTo);
    exit;
}

// ---------------------------------------------------------------------
//  Avisos
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'notifications') {
    csrf_require_valid();

    $pdo->prepare('UPDATE e5_users SET notify_email = :email, notify_whatsapp = :whatsapp, updated_at = NOW() WHERE id = :id')
        ->execute([
            ':email'    => isset($_POST['notify_email']) ? 1 : 0,
            ':whatsapp' => isset($_POST['notify_whatsapp']) ? 1 : 0,
            ':id'       => (int) $_SESSION['user_id'],
        ]);

    $_SESSION['success'] = 'Preferências de aviso atualizadas.';
    header('Location: ' . base_url('pages/auth/profile.php') . '#tab-preferencias');
    exit;
}

// ---------------------------------------------------------------------
//  Senha
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    csrf_require_valid();

    $current      = (string) ($_POST['current_password'] ?? '');
    $newPassword  = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['confirm_password'] ?? '');

    $errors = [];

    if (!password_verify($current, $user['password'])) {
        $errors['current_password'] = 'A senha atual está incorreta.';
    }

    if (mb_strlen($newPassword) < 6) {
        $errors['new_password'] = 'A nova senha precisa de no mínimo 6 caracteres.';
    } elseif (strlen($newPassword) > 72) {
        // O hash bcrypt ignora tudo depois de 72 bytes; validar evita
        // senha que "funciona" mas é mais longa do que o hash guarda.
        $errors['new_password'] = 'A nova senha pode ter no máximo 72 caracteres.';
    } elseif ($newPassword !== $confirmation) {
        $errors['confirm_password'] = 'A confirmação não confere com a nova senha.';
    }

    if ($errors === []) {
        $pdo->prepare('UPDATE e5_users SET password = :hash, updated_at = NOW() WHERE id = :id')
            ->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => (int) $_SESSION['user_id']]);

        $_SESSION['success'] = 'Senha alterada com sucesso.';
        header('Location: ' . base_url('pages/auth/profile.php') . '#tab-preferencias');
        exit;
    }

    if ($errors !== []) {
        $_SESSION['error'] = reset($errors);
        header('Location: ' . base_url('pages/auth/profile.php') . '#tab-preferencias&focus=' . urlencode(array_key_first($errors)));
        exit;
    }
}

// ---------------------------------------------------------------------
//  Render
// ---------------------------------------------------------------------
$page_title = 'Meu Perfil - Royal Tech';
$current_page = 'perfil';

$maskedPassword = str_repeat('•', 8);
$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

account_layout_head($user, 'perfil');
?>

<div class="account-page-header">
    <h1 class="account-page-title">Perfil</h1>
    <p class="account-page-subtitle">Gerencie seus dados, endereços e preferências de contato em um só lugar.</p>
</div>

<?php if ($flash['message'] !== ''): ?>
    <div class="account-alert account-alert--<?php echo e($flash['type']); ?>" role="status">
        <i class="fas <?php echo $flash['type'] === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>" aria-hidden="true"></i>
        <span><?php echo e($flash['message']); ?></span>
    </div>
<?php endif; ?>

<div class="account-profile-grid">
    <!-- ============================ Card: Meu avatar ============================ -->
    <section class="account-card account-card--avatar" id="secao-avatar">
        <header class="account-card-head">
            <h2 class="account-card-title"><i class="fas fa-camera-retro" aria-hidden="true"></i> Meu avatar</h2>
        </header>
        <div class="account-card-body account-avatar-editor">
            <button type="button" class="account-avatar-btn" id="avatarTrigger"
                    title="Clique para trocar a foto" aria-label="Trocar foto de perfil">
                <?php echo render_avatar($user, 'account-avatar--xl'); ?>
                <span class="account-avatar-camera" aria-hidden="true"><i class="fas fa-camera"></i></span>
            </button>

            <form class="account-avatar-editor-actions" method="post" enctype="multipart/form-data" id="avatarForm">
                <input type="hidden" name="action" value="avatar">
                <?php echo csrf_field(); ?>
                <label class="account-btn account-btn--primary account-btn--sm" for="avatarInput">
                    <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i> Trocar foto
                </label>
                <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" hidden>
                <span class="account-hint">JPG, PNG ou WebP de até 2 MB. Vale clicar na foto ou no botão.</span>
            </form>
        </div>
    </section>

    <!-- ============================ Card: Menu ============================ -->
    <section class="account-card account-card--menu" id="secao-menu">
        <header class="account-card-head">
            <h2 class="account-card-title"><i class="fas fa-list-check" aria-hidden="true"></i> Menu</h2>
        </header>
        <div class="account-card-body">
            <nav class="account-menu" data-account-menu aria-label="Opções do perfil">
                <button type="button" class="account-menu-item is-active" data-account-tab="dados"
                        aria-controls="tab-dados" aria-selected="true">
                    <i class="fas fa-user" aria-hidden="true"></i>
                    <span class="account-menu-label">Dados Pessoais</span>
                    <i class="fas fa-chevron-right account-menu-arrow" aria-hidden="true"></i>
                </button>
                <button type="button" class="account-menu-item" data-account-tab="endereco"
                        aria-controls="tab-endereco" aria-selected="false">
                    <i class="fas fa-map-location-dot" aria-hidden="true"></i>
                    <span class="account-menu-label">Endereço</span>
                    <i class="fas fa-chevron-right account-menu-arrow" aria-hidden="true"></i>
                </button>
                <button type="button" class="account-menu-item" data-account-tab="preferencias"
                        aria-controls="tab-preferencias" aria-selected="false">
                    <i class="fas fa-bell" aria-hidden="true"></i>
                    <span class="account-menu-label">Notificações e senha</span>
                    <i class="fas fa-chevron-right account-menu-arrow" aria-hidden="true"></i>
                </button>
            </nav>
        </div>
    </section>
</div>

<!-- ============================ Painel: Dados pessoais ============================ -->
<section class="account-panel is-active" id="tab-dados" data-account-panel="dados" role="tabpanel">
    <div class="account-panel-head">
        <h2 class="account-panel-title">Dados pessoais</h2>
        <p class="account-panel-hint">Essas informações aparecem no comprovante e no rastreio. Os valores salvos ficam destacados.</p>
    </div>
    <div class="account-panel-body">
        <section class="account-card" id="secao-dados">
            <form method="post" novalidate data-account-form data-profile-form data-endpoint="<?php echo e(base_url('api/account/profile.php')); ?>" action="<?php echo e(base_url('pages/auth/profile.php')); ?>">
                <input type="hidden" name="action" value="personal">
                <input type="hidden" name="panel" value="dados">
                <?php echo csrf_field(); ?>

                <div class="account-grid account-grid--2">
                    <div class="account-field">
                        <label class="account-label" for="name">Nome completo</label>
                        <input class="account-input" type="text" id="name" name="name" maxlength="80"
                               value="<?php echo e($user['name'] ?? ''); ?>" required>
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="username">Usuário</label>
                        <input class="account-input" type="text" id="username" name="username" maxlength="30"
                               value="<?php echo e($user['username'] ?? ''); ?>" required>
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="email">E-mail</label>
                        <input class="account-input" type="email" id="email" name="email" maxlength="120"
                               value="<?php echo e($user['email'] ?? ''); ?>" required>
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="cpf">CPF</label>
                        <input class="account-input" type="text" id="cpf" name="cpf" inputmode="numeric" maxlength="14"
                               value="<?php echo e($user['cpf'] ? format_cpf($user['cpf']) : ''); ?>" required
                               placeholder="000.000.000-00">
                        <span class="account-hint">Não conseguimos alterar o CPF sozinho — escreva para o suporte.</span>
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="phone">Telefone (WhatsApp)</label>
                        <input class="account-input" type="tel" id="phone" name="phone" inputmode="numeric" maxlength="16"
                               value="<?php echo e($user['phone'] ? format_phone($user['phone']) : ''); ?>"
                               placeholder="(12) 97814-9392">
                        <span class="account-hint">Usado no rastreio do pedido e nos avisos de entrega.</span>
                    </div>
                </div>

                <div class="account-actions">
                    <button type="submit" class="account-btn account-btn--primary">
                        <i class="fas fa-save" aria-hidden="true"></i> Salvar dados
                    </button>
                    <span class="account-save-status" data-save-status role="status" aria-live="polite"></span>
                </div>
            </form>
        </section>
    </div>
</section>

<!-- ============================ Painel: Endereço ============================ -->
<section class="account-panel" id="tab-endereco" data-account-panel="endereco" role="tabpanel">
    <div class="account-panel-head">
        <h2 class="account-panel-title">Endereço de entrega</h2>
        <p class="account-panel-hint">O CEP preenche rua, bairro, cidade e UF automaticamente.</p>
    </div>
    <div class="account-panel-body">
        <section class="account-card" id="secao-endereco">
            <form method="post" novalidate data-account-form data-endpoint="<?php echo e(base_url('pages/auth/profile.php')); ?>" action="<?php echo e(base_url('pages/auth/profile.php')); ?>">
                <input type="hidden" name="action" value="address">
                <input type="hidden" name="panel" value="endereco">
                <?php echo csrf_field(); ?>

                <div class="account-field">
                    <label class="account-label" for="postal_code">CEP</label>
                    <div class="account-input-group">
                        <input class="account-input" type="text" id="postal_code" name="postal_code" inputmode="numeric"
                               maxlength="9" value="<?php echo e($user['postal_code'] ?? ''); ?>"
                               placeholder="00000-000" autocomplete="postal-code">
                        <button type="button" class="account-btn account-btn--sm" id="cepLookup">
                            <i class="fas fa-location-arrow" aria-hidden="true"></i> Buscar
                        </button>
                    </div>
                    <span class="account-hint" id="cepStatus" role="status" aria-live="polite"></span>
                </div>

                <div class="account-grid account-grid--2">
                    <div class="account-field">
                        <label class="account-label" for="street">Rua</label>
                        <input class="account-input" type="text" id="street" name="street" maxlength="120"
                               value="<?php echo e($user['street'] ?? ''); ?>" autocomplete="address-line1">
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="number">Número</label>
                        <input class="account-input" type="text" id="number" name="number" maxlength="10"
                               value="<?php echo e($user['number'] ?? ''); ?>" autocomplete="address-line2">
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="complement">Complemento</label>
                        <input class="account-input" type="text" id="complement" name="complement" maxlength="80"
                               value="<?php echo e($user['complement'] ?? ''); ?>" placeholder="Apto., bloco, andar...">
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="neighborhood">Bairro</label>
                        <input class="account-input" type="text" id="neighborhood" name="neighborhood" maxlength="80"
                               value="<?php echo e($user['neighborhood'] ?? ''); ?>">
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="city">Cidade</label>
                        <input class="account-input" type="text" id="city" name="city" maxlength="80"
                               value="<?php echo e($user['city'] ?? ''); ?>" autocomplete="address-level2">
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="state">UF</label>
                        <select class="account-select" id="state" name="state" autocomplete="address-level1">
                            <option value="">—</option>
                            <?php foreach ($ufs as $uf): ?>
                                <option value="<?php echo e($uf); ?>" <?php echo $user['state'] === $uf ? 'selected' : ''; ?>><?php echo e($uf); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="account-actions">
                    <button type="submit" class="account-btn account-btn--primary">
                        <i class="fas fa-save" aria-hidden="true"></i> Salvar endereço
                    </button>
                    <span class="account-save-status" data-save-status role="status" aria-live="polite"></span>
                </div>
            </form>
        </section>
    </div>
</section>

<!-- ==================== Painel: Notificações e senha ==================== -->
<section class="account-panel" id="tab-preferencias" data-account-panel="preferencias" role="tabpanel">
    <div class="account-panel-head">
        <h2 class="account-panel-title">Notificações e senha</h2>
        <p class="account-panel-hint">Avisos de entrega e a segurança da sua conta, lado a lado.</p>
    </div>
    <div class="account-panel-body">

        <!-- ==================== Avisos ==================== -->
        <section class="account-card" id="secao-avisos">
            <header class="account-card-head">
                <div>
                    <h2 class="account-card-title"><i class="fas fa-bell" aria-hidden="true"></i> Avisos</h2>
                    <p class="account-card-hint">Escolha como quer ser avisado durante a entrega.</p>
                </div>
            </header>

            <form method="post" data-account-form data-notifications data-endpoint="<?php echo e(base_url('api/account/notifications.php')); ?>" action="<?php echo e(base_url('pages/auth/profile.php')); ?>">
                <input type="hidden" name="action" value="notifications">
                <input type="hidden" name="panel" value="preferencias">
                <?php echo csrf_field(); ?>

                <div class="account-toggle-row">
                    <div class="account-toggle-text">
                        <span class="account-toggle-label">Avisos por e-mail</span>
                        <span class="account-toggle-hint">Status do pedido, comprovante e novidades.</span>
                    </div>
                    <label class="account-toggle">
                        <input type="checkbox" name="notify_email" value="1"
                               <?php echo (int) ($user['notify_email'] ?? 1) === 1 ? 'checked' : ''; ?>>
                        <span class="account-toggle-track" aria-hidden="true"></span>
                        <span class="sr-only">Avisos por e-mail</span>
                    </label>
                </div>

                <div class="account-toggle-row">
                    <div class="account-toggle-text">
                        <span class="account-toggle-label">Avisos no WhatsApp</span>
                        <span class="account-toggle-hint">A loja não envia WhatsApp automático: o aviso fica na fila do painel e a equipe envia pelo link pronto.</span>
                    </div>
                    <label class="account-toggle">
                        <input type="checkbox" name="notify_whatsapp" value="1"
                               <?php echo (int) ($user['notify_whatsapp'] ?? 1) === 1 ? 'checked' : ''; ?>>
                        <span class="account-toggle-track" aria-hidden="true"></span>
                        <span class="sr-only">Avisos no WhatsApp</span>
                    </label>
                </div>

                <div class="account-actions">
                    <button type="submit" class="account-btn account-btn--primary">
                        <i class="fas fa-save" aria-hidden="true"></i> Salvar preferências
                    </button>
                    <span class="account-save-status" data-save-status role="status" aria-live="polite"></span>
                </div>
            </form>
        </section>

        <!-- ==================== Segurança ==================== -->
        <section class="account-card" id="secao-senha">
            <header class="account-card-head">
                <div>
                    <h2 class="account-card-title"><i class="fas fa-lock" aria-hidden="true"></i> Segurança</h2>
                    <p class="account-card-hint">Troque a senha da sua conta aqui. Nenhum histórico é guardado.</p>
                </div>
            </header>

            <div class="account-field">
                <label class="account-label" for="passwordMasked">Senha atual</label>
                <input class="account-input" type="password" id="passwordMasked" value="<?php echo e($maskedPassword); ?>"
                       disabled aria-describedby="masksHint">
                <span class="account-hint" id="masksHint">Sua senha nunca aparece por aqui. Para trocar, use o formulário abaixo.</span>
            </div>

            <form method="post" novalidate data-account-form data-endpoint="<?php echo e(base_url('api/account/password.php')); ?>" data-clear-passwords action="<?php echo e(base_url('pages/auth/profile.php')); ?>">
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="panel" value="preferencias">
                <?php echo csrf_field(); ?>

                <div class="account-grid account-grid--3">
                    <div class="account-field">
                        <label class="account-label" for="current_password">Senha atual</label>
                        <input class="account-input" type="password" id="current_password" name="current_password"
                               autocomplete="current-password" required>
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="new_password">Nova senha</label>
                        <input class="account-input" type="password" id="new_password" name="new_password"
                               minlength="6" maxlength="72" autocomplete="new-password"
                               aria-describedby="passwordRules" required>
                        <span class="account-hint" id="passwordRules">Mínimo 6 caracteres, máximo 72.</span>
                    </div>

                    <div class="account-field">
                        <label class="account-label" for="confirm_password">Confirmar nova senha</label>
                        <input class="account-input" type="password" id="confirm_password" name="confirm_password"
                               minlength="6" maxlength="72" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="account-actions">
                    <button type="submit" class="account-btn">
                        <i class="fas fa-key" aria-hidden="true"></i> Alterar senha
                    </button>
                    <span class="account-save-status" data-save-status role="status" aria-live="polite"></span>
                </div>
            </form>
        </section>
    </div>
</section>

<script>
(function () {
    'use strict';

    // --- card de menu: troca de painel ---------------------------------
    var menu = document.querySelector('[data-account-menu]');
    var panels = document.querySelectorAll('[data-account-panel]');

    function activateTab(name) {
        var selected = 'tab-' + name;

        menu.querySelectorAll('.account-menu-item').forEach(function (item) {
            var on = item.getAttribute('data-account-tab') === name;
            item.classList.toggle('is-active', on);
            item.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        panels.forEach(function (panel) {
            var on = panel.id === selected;
            panel.hidden = !on;
            panel.classList.toggle('is-active', on);
        });

        if (window.history && history.replaceState) {
            history.replaceState(null, '', '#tab-' + name);
        }
    }

    if (menu && panels.length) {
        menu.addEventListener('click', function (event) {
            var item = event.target.closest('.account-menu-item');
            if (item) activateTab(item.getAttribute('data-account-tab'));
        });

        // Sem JS todos os painéis aparecem; com JS começa no do hash.
        var initial = (location.hash || '').replace('#tab-', '');
        activateTab(['dados', 'endereco', 'preferencias'].indexOf(initial) !== -1 ? initial : 'dados');
    }

    // --- mascara de CPF e telefone sem lib externa ----------------------
    var cpf = document.getElementById('cpf');
    if (cpf) {
        cpf.addEventListener('input', function () {
            var v = cpf.value.replace(/\D/g, '').slice(0, 11);
            cpf.value = v.replace(/(\d{3})(\d)/, '$1.$2')
                         .replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                         .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
        });
    }

    var phone = document.getElementById('phone');
    if (phone) {
        phone.addEventListener('input', function () {
            var v = phone.value.replace(/\D/g, '').slice(0, 11);
            phone.value = v.replace(/(\d{2})(\d)/, '($1) $2')
                           .replace(/(\d)(\d{4})$/, '$1-$2');
        });
    }

    // --- clicar no avatar abre o seletor de arquivo ---------------------
    var avatarTrigger = document.getElementById('avatarTrigger');
    var avatarInput = document.getElementById('avatarInput');
    var avatarForm = document.getElementById('avatarForm');
    if (avatarTrigger && avatarInput) {
        avatarTrigger.addEventListener('click', function () { avatarInput.click(); });
    }
    if (avatarInput && avatarForm) {
        avatarInput.addEventListener('change', function () {
            if (avatarInput.files && avatarInput.files.length) {
                avatarForm.submit();
            }
        });
    }

    // --- preenchimento do endereço pelo CEP ----------------------------
    // Consulta passa pelo proxy do servidor (api/account/cep.php).
    var cepInput = document.getElementById('postal_code');
    var cepButton = document.getElementById('cepLookup');
    var cepStatus = document.getElementById('cepStatus');

    function cepField(id) { return document.getElementById(id); }

    function setCepStatus(msg, type) {
        if (!cepStatus) return;
        cepStatus.textContent = msg || '';
        cepStatus.className = 'account-hint cep-' + (type || 'idle');
    }

    function lookupCep(cep) {
        var basePath = document.body.getAttribute('data-base-path') || '';
        var url = basePath + 'api/account/cep.php?cep=' + encodeURIComponent(cep);
        setCepStatus('Buscando endereço...');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok || !data.data) {
                    setCepStatus(
                        (data && data.message) ? data.message : 'CEP não encontrado. Você pode preencher a rua manualmente.',
                        'error'
                    );
                    return;
                }

                var map = {
                    street: data.data.street || '',
                    neighborhood: data.data.neighborhood || '',
                    city: data.data.city || '',
                    state: data.data.state || ''
                };

                for (var key in map) {
                    if (Object.prototype.hasOwnProperty.call(map, key)) {
                        var field = cepField(key);
                        if (field && map[key] !== '') field.value = map[key];
                    }
                }

                setCepStatus('Endereço encontrado.', 'ok');
            })
            .catch(function () {
                setCepStatus('Não foi possível consultar o CEP agora. Preencha manualmente.', 'error');
            });
    }

    if (cepInput && cepButton) {
        cepInput.addEventListener('input', function () {
            var v = cepInput.value.replace(/\D/g, '').slice(0, 8);
            cepInput.value = v.replace(/(\d{5})(\d)/, '$1-$2');
            if (v.length === 8) lookupCep(v);
        });
        cepInput.addEventListener('blur', function () {
            var v = cepInput.value.replace(/\D/g, '');
            if (v.length === 8) lookupCep(v);
        });
        cepButton.addEventListener('click', function () {
            var v = cepInput.value.replace(/\D/g, '');
            if (v.length === 8) lookupCep(v);
        });
    }

    // --- salvamento genérico de todos os formulários data-account-form ---
    function wireForm(form) {
        var status = form.querySelector('[data-save-status]');
        var endpoint = form.getAttribute('data-endpoint') || form.action;
        var clearPasswords = form.hasAttribute('data-clear-passwords');
        var isPersonal = form.hasAttribute('data-profile-form');
        var isNotifications = form.hasAttribute('data-notifications');

        function setSave(message, type) {
            if (!status) return;
            status.textContent = message || '';
            status.className = 'account-save-status' + (type ? ' account-save-status--' + type : '');
        }

        function markFieldErrors(errors) {
            form.querySelectorAll('.account-field').forEach(function (field) {
                var input = field.querySelector('input, select');
                if (!input || !input.name) return;
                var has = Object.prototype.hasOwnProperty.call(errors || {}, input.name);
                field.classList.toggle('account-field--error', has);
                if (has) {
                    field.querySelectorAll('.account-hint').forEach(function (hint) { hint.remove(); });
                    var err = document.createElement('span');
                    err.className = 'account-hint account-hint--error';
                    err.textContent = errors[input.name];
                    field.appendChild(err);
                }
            });
        }

        function savedFlash(field) {
            field.classList.remove('account-field--saved');
            void field.offsetWidth;
            field.classList.add('account-field--saved');
        }

        function applySavedValues(data) {
            if (!data) return;
            form.querySelectorAll('input, select').forEach(function (input) {
                if (!input.name) return;
                if (!Object.prototype.hasOwnProperty.call(data, input.name)) return;

                var val = data[input.name];
                if (input.type === 'checkbox') {
                    input.checked = !!val;
                    savedFlash(input.closest('.account-field'));
                    return;
                }
                if (input.type === 'radio') {
                    input.checked = (input.value == val);
                    savedFlash(input.closest('.account-field'));
                    return;
                }

                // Personal form: format CPF and phone
                if (isPersonal) {
                    if (input.name === 'cpf' && val) {
                        var c = val.replace(/\D/g, '').slice(0, 11);
                        val = c.replace(/(\d{3})(\d)/, '$1.$2')
                              .replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                              .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
                    }
                    if (input.name === 'phone' && val) {
                        var p = val.replace(/\D/g, '').slice(0, 11);
                        val = p.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d)(\d{4})$/, '$1-$2');
                    }
                }

                input.value = val;
                savedFlash(input.closest('.account-field'));
            });
        }

        if (!window.fetch) return;

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            setSave('Salvando...', 'pending');

            fetch(endpoint, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        return { status: response.status, json: json };
                    });
                })
                .then(function (result) {
                    if (result.json && result.json.ok) {
                        setSave('Salvo!', 'ok');
                        applySavedValues(result.json.data);
                        if (clearPasswords) {
                            form.querySelectorAll('input[type="password"]').forEach(function (pw) {
                                pw.value = '';
                            });
                        }
                    } else {
                        setSave((result.json && result.json.message) || 'Não foi possível salvar.', 'error');
                        markFieldErrors(result.json.errors);
                    }
                })
                .catch(function () {
                    setSave('Sem conexão com o servidor agora. Tente de novo.', 'error');
                })
                .then(function () {
                    if (button) button.disabled = false;
                });
        });
    }

    document.querySelectorAll('form[data-account-form]').forEach(wireForm);
})();
</script>

<?php account_layout_foot(); ?>