<?php
$page_title = 'Meu Perfil - Royal Tech';
$breadcrumb_title = 'Meu Perfil';
$current_page = 'perfil';
$base_path = '../../';
$page_css = ['account.css'];

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once __DIR__ . '/../../includes/csrf.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
require_once __DIR__ . '/../../includes/address_functions.php';
require_once __DIR__ . '/../../includes/saved_card_functions.php';
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
    $form = (string) ($_POST['form'] ?? 'profile');

    // Upload de avatar
    if ($form === 'avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
            $tmpPath = (string) ($_FILES['avatar']['tmp_name'] ?? '');
            $size = (int) ($_FILES['avatar']['size'] ?? 0);
            // Detecta o MIME real pelo conteúdo, ignorando o tipo informado pelo navegador
            $mime = '';
            if ($tmpPath !== '' && is_file($tmpPath)) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string) $finfo->file($tmpPath);
            }
            $imageInfo = ($tmpPath !== '' && is_file($tmpPath)) ? @getimagesize($tmpPath) : false;
            $isRealImage = $imageInfo !== false && (int) $imageInfo[0] > 0 && (int) $imageInfo[1] > 0;
            if (!isset($allowed[$mime]) || !$isRealImage) {
                $errorMessage = 'Formato de imagem não suportado. Use JPG, PNG ou WEBP.';
            } elseif ($size <= 0 || $size > 2 * 1024 * 1024) {
                $errorMessage = 'Imagem muito grande. O limite é 2 MB.';
            } else {
                $ext = $allowed[$mime];
                $name = 'avatar_' . $userId . '_' . bin2hex(random_bytes(4)) . $ext;
                $destDir = __DIR__ . '/../../assets/uploads/avatars/';
                $dest = $destDir . $name;
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0775, true);
                }
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                    if (!empty($user['avatar_path'])) {
                        $old = $destDir . basename((string) $user['avatar_path']);
                        if (is_file($old)) {
                            @unlink($old);
                        }
                    }
                    $pdo->prepare('UPDATE e5_users SET avatar_path = :path WHERE id = :id')
                        ->execute([':path' => 'assets/uploads/avatars/' . $name, ':id' => $userId]);
                    $user['avatar_path'] = 'assets/uploads/avatars/' . $name;
                    $successMessage = 'Foto de perfil atualizada com sucesso!';
                } else {
                    $errorMessage = 'Não foi possível salvar a imagem. Tente novamente.';
                }
            }
        } else {
            $errorMessage = 'Nenhuma imagem recebida para alterar a foto.';
        }
    } elseif ($form === 'address') {
        $addressId = !empty($_POST['address_id']) ? (int) $_POST['address_id'] : null;
        if (in_array(($_POST['intent'] ?? ''), ['new', 'edit'], true) || $addressId !== null) {
            $result = userAddressSave($pdo, $userId, $_POST, $addressId);
            if ($result['ok']) {
                $successMessage = $result['message'];
            } else {
                $errorMessage = $result['message'];
            }
        }
    } elseif ($form === 'delete_address') {
        $result = userAddressDelete($pdo, $userId, (int) ($_POST['address_id'] ?? 0));
        $result['ok'] ? $successMessage = $result['message'] : $errorMessage = $result['message'];
    } elseif ($form === 'set_default_address') {
        $result = userAddressSetDefault($pdo, $userId, (int) ($_POST['address_id'] ?? 0));
        $result['ok'] ? $successMessage = $result['message'] : $errorMessage = $result['message'];
    } elseif ($form === 'card') {
        $result = cardSave($pdo, $userId, $_POST);
        $result['ok'] ? $successMessage = $result['message'] : $errorMessage = $result['message'];
    } elseif ($form === 'delete_card') {
        $result = cardDelete($pdo, $userId, (int) ($_POST['card_id'] ?? 0));
        $result['ok'] ? $successMessage = $result['message'] : $errorMessage = $result['message'];
    } else {
        // Salvar dados do perfil
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $cpfRaw = preg_replace('/\D/', '', (string) ($_POST['cpf'] ?? ''));
        $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
        $street = trim((string) ($_POST['street'] ?? ''));
        $number = (int) ($_POST['number'] ?? 0);
        $complement = trim((string) ($_POST['complement'] ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($_POST['phone'] ?? ''));
        $currentPass = (string) ($_POST['current_password'] ?? '');
        $newPass = (string) ($_POST['new_password'] ?? '');
        $notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
        $notifyWhatsapp = isset($_POST['notify_whatsapp']) ? 1 : 0;

        function profileCpfValid(string $cpf): bool
        {
            $cpf = preg_replace('/\D/', '', $cpf);
            if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
            for ($t = 9; $t < 11; $t++) {
                $sum = 0;
                for ($i = 0; $i < $t; $i++) {
                    $sum += (int) $cpf[$i] * (($t + 1) - $i);
                }
                $digit = ((10 * $sum) % 11) % 10;
                if ((int) $cpf[$t] !== $digit) return false;
            }
            return true;
        }

        if ($name === '' || $email === '' || $username === '') {
                $errorMessage = 'Nome, e-mail e usuário são obrigatórios.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = 'E-mail inválido.';
            } elseif ($cpfRaw === '' || !profileCpfValid($cpfRaw)) {
                $errorMessage = 'CPF inválido. Verifique o número digitado.';
            } elseif ($phone !== '' && (strlen($phone) < 10 || strlen($phone) > 11)) {
                $errorMessage = 'Telefone inválido. Informe DDD + número (10 ou 11 dígitos).';
            } else {
                try {
                    $stmtCheck = $pdo->prepare('SELECT id FROM e5_users WHERE (email = :email OR username = :username) AND id != :id LIMIT 1');
                    $stmtCheck->execute([':email' => $email, ':username' => $username, ':id' => $userId]);
                    if ($stmtCheck->fetch()) {
                        $errorMessage = 'E-mail ou usuário já em uso.';
                    } else {
                        $sql = 'UPDATE e5_users SET name = :name, email = :email, username = :username, cpf = :cpf, phone = :phone, postal_code = :postal_code, street = :street, number = :number, complement = :complement, notify_email = :ne, notify_whatsapp = :nw WHERE id = :id';
                        $params = [':name' => $name, ':email' => $email, ':username' => $username, ':cpf' => $cpfRaw, ':phone' => $phone !== '' ? $phone : null, ':postal_code' => $postalCode, ':street' => $street, ':number' => $number, ':complement' => $complement ?: null, ':ne' => $notifyEmail, ':nw' => $notifyWhatsapp, ':id' => $userId];

                        if ($newPass !== '') {
                            if (!password_verify($currentPass, $user['password'])) {
                                $errorMessage = 'Senha atual incorreta.';
                            } elseif (strlen($newPass) < 6) {
                                $errorMessage = 'Nova senha deve ter no mínimo 6 caracteres.';
                            } else {
                                $sql = 'UPDATE e5_users SET name = :name, email = :email, username = :username, cpf = :cpf, phone = :phone, postal_code = :postal_code, street = :street, number = :number, complement = :complement, password = :password, notify_email = :ne, notify_whatsapp = :nw WHERE id = :id';
                                $params[':password'] = password_hash($newPass, PASSWORD_DEFAULT);
                            }
                        }

                        if (!$errorMessage) {
                            $pdo->prepare($sql)->execute($params);
                            $successMessage = 'Dados atualizados com sucesso!';
                            $user['name'] = $name;
                            $user['email'] = $email;
                            $user['username'] = $username;
                            $user['cpf'] = $cpfRaw;
                            $user['phone'] = $phone !== '' ? $phone : null;
                            $user['postal_code'] = $postalCode;
                            $user['street'] = $street;
                            $user['number'] = $number;
                            $user['complement'] = $complement;
                            $user['notify_email'] = $notifyEmail;
                            $user['notify_whatsapp'] = $notifyWhatsapp;
                            $_SESSION['user_name'] = $name;
                        }
                    }
                } catch (Throwable $e) {
                    $errorMessage = 'Erro ao atualizar perfil.';
                    error_log('Profile error: ' . $e->getMessage());
                }
            }
        }
    }

// Recarrega dados após POST para refletir mudanças
$stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

// Dados auxiliares
$initials = '';
$words = preg_split('/\s+/', trim((string) ($user['name'] ?? '')));
foreach ($words as $w) {
    if ($w !== '') {
        $initials .= mb_strtoupper(mb_substr($w, 0, 1));
    }
}
$initials = mb_substr($initials, 0, 2) ?: 'RT';

$cpfFormatted = ($user['cpf'] ?? '') !== ''
    ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', (string) $user['cpf'])
    : '';

$phoneDigits = preg_replace('/\D/', '', (string) ($user['phone'] ?? ''));
$phoneFormatted = '';
if (strlen($phoneDigits) === 11) {
    $phoneFormatted = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phoneDigits);
} elseif (strlen($phoneDigits) === 10) {
    $phoneFormatted = preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phoneDigits);
}

$avatarPath = $base_path . (!empty($user['avatar_path'])
    ? $user['avatar_path']
    : 'assets/img/placeholder-avatar.svg');

$isAdminProfile = (($_SESSION['user_role'] ?? '') === 'admin');

$savedAddresses = userAddressGetAll($pdo, $userId);
$savedCards = cardGetAll($pdo, $userId);

function profileAddressLine(array $address): string
{
    $cep = preg_replace('/\D/', '', (string) ($address['postal_code'] ?? ''));
    $cepFmt = strlen($cep) === 8 ? preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep) : (string) ($address['postal_code'] ?? '');
    $parts = [];
    $parts[] = trim(($address['street'] ?? '') . ', ' . ($address['number'] ?? ''));
    if (!empty($address['complement'])) $parts[] = (string) $address['complement'];
    if (!empty($address['neighborhood'])) $parts[] = (string) $address['neighborhood'];
    $cityState = trim(($address['city'] ?? '') . (($address['state'] ?? '') !== '' ? '/' . $address['state'] : ''));
    if ($cityState !== '') $parts[] = $cityState;
    if ($cepFmt !== '') $parts[] = 'CEP ' . $cepFmt;
    return implode(' — ', array_filter($parts));
}

function profileAddressFormFields(?array $a): string
{
    $a = $a ?? [];
    $val = function (string $k, string $default = '') use ($a): string {
        return htmlspecialchars((string) ($a[$k] ?? $default), ENT_QUOTES, 'UTF-8');
    };
    $isDefault = !empty($a['is_default']);
    $states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
    $options = '';
    $sel = strtoupper((string) ($a['state'] ?? ''));
    foreach ($states as $uf) {
        $options .= '<option value="' . $uf . '"' . ($sel === $uf ? ' selected' : '') . '>' . $uf . '</option>';
    }

    return '<div class="ac-grid">'
        . '<div class="ac-field"><label>Rótulo</label><input type="text" name="label" maxlength="40" placeholder="Ex.: Casa, Trabalho" value="' . $val('label', 'Entrega') . '"></div>'
        . '<div class="ac-field"><label>CEP</label><input type="text" name="postal_code" maxlength="9" inputmode="numeric" placeholder="00000-000" value="' . $val('postal_code') . '" required></div>'
        . '<div class="ac-field"><label>Número</label><input type="text" name="number" maxlength="10" placeholder="Ex.: 123 ou S/N" value="' . $val('number') . '" required></div>'
        . '<div class="ac-field ac-full"><label>Rua</label><input type="text" name="street" placeholder="Preenche via CEP se deixado em branco" value="' . $val('street') . '"></div>'
        . '<div class="ac-field"><label>Bairro</label><input type="text" name="neighborhood" placeholder="Opcional" value="' . $val('neighborhood') . '"></div>'
        . '<div class="ac-field"><label>Cidade</label><input type="text" name="city" placeholder="Preenche via CEP se deixado em branco" value="' . $val('city') . '"></div>'
        . '<div class="ac-field"><label>UF</label><select name="state"><option value="">--</option>' . $options . '</select></div>'
        . '<div class="ac-field"><label>Complemento</label><input type="text" name="complement" maxlength="80" placeholder="Opcional" value="' . $val('complement') . '"></div>'
        . '<div class="ac-field ac-full"><label class="ac-inline-check"><input type="checkbox" name="is_default" value="1"' . ($isDefault ? ' checked' : '') . '> Usar como endereço padrão</label></div>'
        . '</div>';
}

include '../../components/header.php';
?>
<section class="profile-page ac-page">
    <div class="container">
        <div class="ac-wrap">

            <!-- Sidebar -->
            <aside class="ac-sidebar">
                <div class="ac-identity">
                    <div class="ac-avatar" id="avatarBox" title="Alterar foto de perfil" onclick="openAvatarModal()" role="button" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openAvatarModal();}">
                        <?php
                        $avatarSrc = $avatarPath;
                        $isSvg = str_ends_with($avatarSrc, '.svg');
                        if (!$isSvg && !empty($user['avatar_path'])):
                        ?>
                            <img class="ac-avatar-img" src="<?php echo htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de perfil">
                        <?php else: ?>
                            <span class="ac-avatar-initials"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <span class="ac-avatar-edit"><i class="fas fa-camera"></i></span>
                        <span class="ac-avatar-overlay"><i class="fas fa-camera"></i><span class="ac-avatar-overlay-text">Trocar foto</span></span>
                    </div>
                    <h2><?php echo htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <span class="ac-role-badge"><?php echo $isAdminProfile ? 'Administrador' : 'Cliente'; ?></span>
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <input type="hidden" name="form" value="avatar">
                        <?php echo csrf_field(); ?>
                        <input type="file" name="avatar" id="avatarFile" accept="image/jpeg,image/png,image/webp" hidden>
                    </form>
                </div>
                <nav class="ac-nav">
                    <a href="profile.php" class="is-active"><i class="fas fa-user-edit"></i> Meu Perfil</a>
                    <a href="orders.php"><i class="fas fa-box-open"></i> Meus Pedidos</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                </nav>
            </aside>

            <!-- Main -->
            <div class="ac-main">
                <div class="ac-main-head">
                    <div>
                        <h1>Meu Perfil</h1>
                        <p>Mantenha seus dados pessoais e preferências atualizados.</p>
                    </div>
                    <span class="ac-count"><i class="fas fa-user-check"></i> Conta verificada</span>
                </div>

                <?php if ($successMessage): ?>
                    <div class="ac-feedback ac-feedback-success"><i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <?php endif; ?>
                <?php if ($errorMessage): ?>
                    <div class="ac-feedback ac-feedback-error"><i class="fas fa-exclamation-triangle"></i> <span><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <!-- Dados Pessoais -->
                    <div class="ac-card">
                        <div class="ac-card-head">
                            <span class="ac-card-icon"><i class="fas fa-id-card"></i></span>
                            <div>
                                <h2 class="ac-card-title">Dados Pessoais</h2>
                                <p class="ac-card-desc">Informações da sua conta e identificação.</p>
                            </div>
                        </div>
                        <div class="ac-grid">
                            <div class="ac-field ac-full">
                                <label for="name">Nome completo</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="ac-field">
                                <label for="cpf_profile">CPF</label>
                                <div class="ac-input-wrap">
                                    <input type="text" id="cpf_profile" name="cpf" placeholder="000.000.000-00" maxlength="14" inputmode="numeric" value="<?php echo htmlspecialchars($cpfFormatted, ENT_QUOTES, 'UTF-8'); ?>" required oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})\.(\d{3})(\d)/,'$1.$2.$3').replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/,'$1.$2.$3-$4')">
                                    <span class="ac-input-icon"><i class="fas fa-id-card"></i></span>
                                </div>
                            </div>
                            <div class="ac-field">
                                <label for="email">E-mail</label>
                                <div class="ac-input-wrap">
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <span class="ac-input-icon"><i class="fas fa-envelope"></i></span>
                                </div>
                            </div>
                            <div class="ac-field">
                                <label for="username">Nome de usuário</label>
                                <div class="ac-input-wrap">
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <span class="ac-input-icon"><i class="fas fa-user"></i></span>
                                </div>
                            </div>
                            <div class="ac-field">
                                <label for="phone">Telefone / Celular</label>
                                <div class="ac-input-wrap">
                                    <input type="text" id="phone" name="phone" placeholder="(11) 99999-9999" maxlength="16" inputmode="tel" value="<?php echo htmlspecialchars($phoneFormatted, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value=this.value.replace(/[^\d()\s-]/g,'')">
                                    <span class="ac-input-icon"><i class="fas fa-phone"></i></span>
                                </div>
                            </div>
                            <div class="ac-field">
                                <label for="password_placeholder">Senha</label>
                                <div class="ac-input-wrap">
                                    <input type="text" id="password_placeholder" value="••••••••" disabled>
                                    <span class="ac-input-icon"><i class="fas fa-lock"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Endereço principal -->
                    <div class="ac-card">
                        <div class="ac-card-head">
                            <span class="ac-card-icon"><i class="fas fa-map-marker-alt"></i></span>
                            <div>
                                <h2 class="ac-card-title">Endereço</h2>
                                <p class="ac-card-desc">Usado nos pedidos e na entrega dos produtos.</p>
                            </div>
                        </div>
                        <div class="ac-grid">
                            <div class="ac-field">
                                <label for="postal_code">CEP</label>
                                <div class="ac-input-wrap">
                                    <input type="text" id="postal_code" name="postal_code" placeholder="00000-000" pattern="[0-9]{5}-?[0-9]{3}" inputmode="numeric" value="<?php echo htmlspecialchars($user['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <span class="ac-input-icon"><i class="fas fa-location-arrow"></i></span>
                                </div>
                                <div class="ac-cep-note" id="cepFeedback" hidden></div>
                            </div>
                            <div class="ac-field">
                                <label for="number">Número</label>
                                <div class="ac-input-wrap">
                                    <input type="text" id="number" name="number" placeholder="Ex.: 123" inputmode="numeric" pattern="[0-9]{1,6}" maxlength="6" value="<?php echo $user['number'] !== null && $user['number'] !== '' ? (int) $user['number'] : ''; ?>">
                                    <span class="ac-input-icon"><i class="fas fa-hashtag"></i></span>
                                </div>
                            </div>
                            <div class="ac-field ac-full">
                                <label for="street">Rua</label>
                                <div class="ac-input-wrap">
                                    <input type="text" id="street" name="street" placeholder="Preenche automaticamente com o CEP" value="<?php echo htmlspecialchars($user['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="ac-input-icon"><i class="fas fa-road"></i></span>
                                </div>
                            </div>
                            <div class="ac-field ac-full">
                                <label for="complement">Complemento</label>
                                <input type="text" id="complement" name="complement" placeholder="Apto., bloco, referência (opcional)" value="<?php echo htmlspecialchars($user['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Endereços salvos -->
                    <div class="ac-card">
                        <div class="ac-card-head">
                            <span class="ac-card-icon"><i class="fas fa-map-pin"></i></span>
                            <div>
                                <h2 class="ac-card-title">Endereços Salvos</h2>
                                <p class="ac-card-desc">Cadastre quantos endereços quiser e defina o padrão para as entregas.</p>
                            </div>
                        </div>
                        <?php if (empty($savedAddresses)): ?>
                            <p class="ac-empty-note" style="margin-bottom:0;"><i class="fas fa-map-marker-alt"></i> Nenhum endereço salvo ainda. Adicione o primeiro abaixo.</p>
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                <?php foreach ($savedAddresses as $addr): ?>
                                    <div class="ac-card-item" style="align-items:flex-start; flex-wrap:wrap; gap:10px;">
                                        <div style="flex:1; min-width:220px;">
                                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                                <strong><?php echo htmlspecialchars((string) $addr['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <?php if ((int) $addr['is_default'] === 1): ?>
                                                    <span class="ac-role-badge"><i class="fas fa-star"></i> Padrão</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size:0.85rem; color:var(--color-gray); margin-top:4px;"><?php echo htmlspecialchars(profileAddressLine($addr), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                            <?php if ((int) $addr['is_default'] !== 1): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="form" value="set_default_address">
                                                    <input type="hidden" name="address_id" value="<?php echo (int) $addr['id']; ?>">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="ac-btn-ghost" style="padding:6px 12px; font-size:0.8rem;"><i class="fas fa-star"></i> Tornar padrão</button>
                                                </form>
                                            <?php endif; ?>
                                            <details style="display:inline-block;">
                                                <summary class="ac-btn-ghost" style="padding:6px 12px; font-size:0.8rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px; list-style:none;"><i class="fas fa-pen"></i> Editar</summary>
                                                <form method="POST" style="margin-top:12px; width:100%;">
                                                    <input type="hidden" name="form" value="address">
                                                    <input type="hidden" name="intent" value="edit">
                                                    <input type="hidden" name="address_id" value="<?php echo (int) $addr['id']; ?>">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo profileAddressFormFields($addr); ?>
                                                    <button type="submit" class="ac-btn-save" style="margin-top:10px;"><i class="fas fa-save"></i> Atualizar endereço</button>
                                                </form>
                                            </details>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este endereço?');">
                                                <input type="hidden" name="form" value="delete_address">
                                                <input type="hidden" name="address_id" value="<?php echo (int) $addr['id']; ?>">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="ac-btn-ghost" style="padding:6px 12px; font-size:0.8rem; color:#ff6b5e;"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <details style="margin-top:16px;">
                            <summary class="ac-btn-ghost" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; list-style:none;"><i class="fas fa-plus"></i> Adicionar endereço</summary>
                            <form method="POST" style="margin-top:14px;">
                                <input type="hidden" name="form" value="address">
                                <input type="hidden" name="intent" value="new">
                                <?php echo csrf_field(); ?>
                                <?php echo profileAddressFormFields(null); ?>
                                <button type="submit" class="ac-btn-save" style="margin-top:10px;"><i class="fas fa-save"></i> Salvar endereço</button>
                            </form>
                        </details>
                    </div>

                    <!-- Alterar Senha -->
                    <div class="ac-card">
                        <div class="ac-card-head">
                            <span class="ac-card-icon"><i class="fas fa-shield-alt"></i></span>
                            <div>
                                <h2 class="ac-card-title">Alterar Senha</h2>
                                <p class="ac-card-desc">Opcional — deixe em branco para manter a senha atual.</p>
                            </div>
                        </div>
                        <div class="ac-grid">
                            <div class="ac-field">
                                <label for="current_password">Senha atual</label>
                                <div class="ac-input-wrap has-toggle">
                                    <input type="password" id="current_password" name="current_password" placeholder="Digite a senha atual">
                                    <button type="button" class="ac-pw-toggle" data-toggle-target="current_password" aria-label="Mostrar senha"><i class="far fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="ac-field">
                                <label for="new_password">Nova senha</label>
                                <div class="ac-input-wrap has-toggle">
                                    <input type="password" id="new_password" name="new_password" placeholder="Mínimo 6 caracteres" minlength="6">
                                    <button type="button" class="ac-pw-toggle" data-toggle-target="new_password" aria-label="Mostrar senha"><i class="far fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preferências de notificação -->
                    <div class="ac-card">
                        <div class="ac-card-head">
                            <span class="ac-card-icon"><i class="fas fa-bell"></i></span>
                            <div>
                                <h2 class="ac-card-title">Preferências de Notificação</h2>
                                <p class="ac-card-desc">Escolha como quer receber novidades e atualizações.</p>
                            </div>
                        </div>
                        <div class="ac-toggles">
                            <div class="ac-toggle-row">
                                <span class="ac-toggle-label"><i class="fas fa-envelope"></i> Notificações por e-mail</span>
                                <label class="ac-toggle-switch">
                                    <input type="checkbox" name="notify_email" <?php echo ($user['notify_email'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="ac-toggle-slider"></span>
                                </label>
                            </div>
                            <div class="ac-toggle-row">
                                <span class="ac-toggle-label"><i class="fab fa-whatsapp"></i> Notificações por WhatsApp</span>
                                <label class="ac-toggle-switch">
                                    <input type="checkbox" name="notify_whatsapp" <?php echo ($user['notify_whatsapp'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="ac-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <?php echo csrf_field(); ?>

                    <div class="ac-actions">
                        <button type="submit" class="ac-btn-save"><i class="fas fa-save"></i> Salvar Alterações</button>
                        <a href="orders.php" class="ac-btn-ghost"><i class="fas fa-box-open"></i> Meus Pedidos</a>
                    </div>
                </form>

                <!-- Cartões salvos -->
                <div class="ac-card">
                    <div class="ac-card-head">
                        <span class="ac-card-icon"><i class="fas fa-credit-card"></i></span>
                        <div>
                            <h2 class="ac-card-title">Cartões Salvos</h2>
                            <p class="ac-card-desc">Formas de pagamento cadastradas na sua conta.</p>
                        </div>
                    </div>
                    <?php if (empty($savedCards)): ?>
                        <p class="ac-empty-note" style="margin-bottom:0;"><i class="fas fa-credit-card"></i> Nenhum cartão salvo. Você pode salvar um cartão aqui ou na hora de finalizar o pedido.</p>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <?php foreach ($savedCards as $card): ?>
                                <div class="ac-card-item" style="gap:10px;">
                                    <span class="ac-card-brand">
                                        <?php
                                        $brandColor = match (strtolower((string) ($card['card_brand'] ?? ''))) {
                                            'visa' => 'font-size:0.8rem;font-weight:700;',
                                            'mastercard' => 'font-size:0.75rem;font-weight:700;',
                                            'amex' => 'font-size:0.7rem;font-weight:700;',
                                            default => 'font-size:0.8rem;',
                                        };
                                        ?>
                                        <span style="<?php echo $brandColor; ?>"><?php echo htmlspecialchars($card['card_brand'] ?? 'CARD', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                    <span class="ac-card-number">•••• •••• •••• <?php echo htmlspecialchars($card['last_four'] ?? '0000', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span style="font-size:0.8rem; color:var(--color-gray);"><?php echo htmlspecialchars((string) ($card['holder_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if (!empty($card['exp_month'])): ?>
                                        <span class="ac-card-exp"><?php echo str_pad((string) $card['exp_month'], 2, '0', STR_PAD_LEFT); ?>/<?php echo htmlspecialchars((string) ($card['exp_year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <form method="POST" style="margin-left:auto;" onsubmit="return confirm('Remover este cartão?');">
                                        <input type="hidden" name="form" value="delete_card">
                                        <input type="hidden" name="card_id" value="<?php echo (int) $card['id']; ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="ac-btn-ghost" style="padding:6px 12px; font-size:0.8rem; color:#ff6b5e;"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <details style="margin-top:16px;">
                        <summary class="ac-btn-ghost" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; list-style:none;"><i class="fas fa-plus"></i> Adicionar cartão</summary>
                        <form method="POST" autocomplete="off" style="margin-top:14px;">
                            <input type="hidden" name="form" value="card">
                            <input type="hidden" name="save_card" value="1">
                            <?php echo csrf_field(); ?>
                            <div class="ac-grid">
                                <div class="ac-field ac-full">
                                    <label for="card_number">Número do cartão</label>
                                    <input type="text" id="card_number" name="card_number" inputmode="numeric" maxlength="19" placeholder="0000 0000 0000 0000" required oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{4})(?=\d)/g,'$1 ')">
                                </div>
                                <div class="ac-field ac-full">
                                    <label for="card_holder">Nome impresso no cartão</label>
                                    <input type="text" id="card_holder" name="holder_name" maxlength="80" placeholder="Como está no cartão" required>
                                </div>
                                <div class="ac-field">
                                    <label for="card_month">Mês (MM)</label>
                                    <input type="text" id="card_month" name="exp_month" inputmode="numeric" maxlength="2" placeholder="MM" required>
                                </div>
                                <div class="ac-field">
                                    <label for="card_year">Ano (AAAA)</label>
                                    <input type="text" id="card_year" name="exp_year" inputmode="numeric" maxlength="4" placeholder="AAAA" required>
                                </div>
                                <div class="ac-field">
                                    <label for="card_installments">Parcelas máximas</label>
                                    <select id="card_installments" name="max_installments">
                                        <?php for ($i = 1; $i <= 12; $i++): ?><option value="<?php echo $i; ?>"<?php echo $i === 12 ? ' selected' : ''; ?>><?php echo $i; ?>x</option><?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="ac-btn-save" style="margin-top:10px;"><i class="fas fa-save"></i> Salvar cartão</button>
                        </form>
                    </details>
                </div>
                <!-- fim cartões -->
            </div>

        </div>
    </div>
</section>

<!-- Modal: alterar foto de perfil -->
<div class="ac-modal-overlay" id="avatarModal">
    <div class="ac-modal">
        <div class="ac-modal-head">
            <h3><i class="fas fa-camera"></i> Alterar foto de perfil</h3>
            <button type="button" class="ac-modal-close" onclick="closeAvatarModal()" aria-label="Fechar"><i class="fas fa-times"></i></button>
        </div>
        <div class="ac-modal-body">
            <div class="ac-avatar-preview" id="avatarPreview">
                <span class="ac-avatar-initials" id="avatarPreviewInitials"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                <img class="ac-crop-img" id="cropImg" alt="Imagem para reposicionar" draggable="false" hidden>
                <div class="ac-crop-frame" id="cropFrame" hidden></div>
            </div>
            <div class="ac-crop-controls" id="cropControls" hidden>
                <button type="button" class="ac-crop-btn" id="cropZoomOut" title="Diminuir zoom" aria-label="Diminuir zoom"><i class="fas fa-minus"></i></button>
                <input type="range" id="cropZoom" min="0" max="100" step="1" aria-label="Zoom">
                <button type="button" class="ac-crop-btn" id="cropZoomIn" title="Aumentar zoom" aria-label="Aumentar zoom"><i class="fas fa-plus"></i></button>
            </div>
            <label class="ac-avatar-file-btn" for="avatarFile"><i class="fas fa-image"></i> Selecionar imagem</label>
            <p class="ac-avatar-hint">Arraste para posicionar e use o zoom. O círculo mostra como a foto ficará.</p>
            <div class="ac-avatar-dropzone" id="avatarDropzone">ou arraste e solte a imagem aqui</div>
            <p class="ac-avatar-error" id="avatarError" hidden></p>
        </div>
        <div class="ac-modal-foot">
            <button type="button" class="ac-btn-ghost" onclick="closeAvatarModal()" style="flex:1;"><i class="fas fa-times"></i> Cancelar</button>
            <button type="button" class="ac-btn-save" id="avatarSaveBtn" disabled style="flex:1;"><i class="fas fa-check"></i> Salvar foto</button>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>

<script>
  // ---------- Alterar foto de perfil ----------
  var avatarFile = document.getElementById('avatarFile');
  var avatarForm = document.getElementById('avatarForm');
  var avatarModal = document.getElementById('avatarModal');
  var avatarPreview = document.getElementById('avatarPreview');
  var avatarError = document.getElementById('avatarError');
  var avatarSaveBtn = document.getElementById('avatarSaveBtn');
  var avatarDropzone = document.getElementById('avatarDropzone');
  var avatarBox = document.getElementById('avatarBox');
  var avatarOrigInner = avatarBox ? avatarBox.innerHTML : '';

  var avatarImg = <?php echo (!empty($user['avatar_path']) && !str_ends_with((string) $avatarPath, '.svg')) ? json_encode($avatarPath) : 'null'; ?>;
  var avatarInitials = <?php echo json_encode($initials, JSON_UNESCAPED_UNICODE); ?>;

  function avatarShowPreview(src) {
    if (!avatarPreview || !cropImg || !avatarPreviewInitials) return;
    if (src) {
      cropImg.src = src;
      cropImg.hidden = false;
      avatarPreviewInitials.style.display = 'none';
    } else {
      cropImg.hidden = true;
      avatarPreviewInitials.style.display = '';
    }
  }

  function avatarShowSidebarPreview(src) {
    if (!avatarBox) return;
    var currentSrc = src;
    if (currentSrc) {
      var oldImg = avatarBox.querySelector('.ac-avatar-img');
      if (oldImg) {
        oldImg.src = currentSrc;
      } else {
        var initials = avatarBox.querySelector('.ac-avatar-initials');
        if (initials) initials.remove();
        var img = document.createElement('img');
        img.className = 'ac-avatar-img';
        img.src = currentSrc;
        img.alt = 'Foto de perfil';
        avatarBox.insertBefore(img, avatarBox.querySelector('.ac-avatar-edit'));
      }
    } else {
      avatarRestoreSidebarAvatar();
    }
  }

  function avatarRestoreSidebarAvatar() {
    if (!avatarBox || avatarOrigInner === '') return;
    avatarBox.innerHTML = avatarOrigInner;
  }

  function avatarShowError(msg) {
    if (!avatarError) return;
    avatarError.hidden = false;
    avatarError.textContent = msg;
  }

  function avatarValidate(file) {
    var okTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (okTypes.indexOf(file.type) === -1) return 'Formato não suportado. Use JPG, PNG ou WEBP.';
    if (file.size > 2 * 1024 * 1024) return 'A imagem excede 2 MB. Escolha um arquivo menor.';
    return null;
  }

  // ---------- Recorte / reposicionamento da imagem (estilo ML/Shopee) ----------
  var avatarPreviewInitials = document.getElementById('avatarPreviewInitials');
  var cropImg = document.getElementById('cropImg');
  var cropFrame = document.getElementById('cropFrame');
  var cropControls = document.getElementById('cropControls');
  var cropZoom = document.getElementById('cropZoom');
  var cropZoomIn = document.getElementById('cropZoomIn');
  var cropZoomOut = document.getElementById('cropZoomOut');

  var crop = { img: null, _nat: null, zoom: 1, cover: 1, tx: 0, ty: 0, viewW: 0, viewH: 0, natW: 0, natH: 0, _pvTimer: null };
  var cropDrag = null;

  function cropLensSize() {
    if (!avatarPreview) return { w: 220, h: 220 };
    var r = avatarPreview.getBoundingClientRect();
    return { w: r.width || 220, h: r.height || 220 };
  }

  function cropClamp() {
    var effW = crop.zoom * crop.natW;
    var effH = crop.zoom * crop.natH;
    if (effW >= crop.viewW) {
      if (crop.tx > 0) crop.tx = 0;
      if (crop.tx < crop.viewW - effW) crop.tx = crop.viewW - effW;
    } else {
      crop.tx = (crop.viewW - effW) / 2;
    }
    if (effH >= crop.viewH) {
      if (crop.ty > 0) crop.ty = 0;
      if (crop.ty < crop.viewH - effH) crop.ty = crop.viewH - effH;
    } else {
      crop.ty = (crop.viewH - effH) / 2;
    }
  }

  function cropSetZoom(z, anchorX, anchorY) {
    if (!crop.img) return;
    var maxZ = crop.cover * 6;
    if (z < crop.cover) z = crop.cover;
    if (z > maxZ) z = maxZ;
    var ax = (anchorX == null) ? crop.viewW / 2 : anchorX;
    var ay = (anchorY == null) ? crop.viewH / 2 : anchorY;
    var ratio = z / crop.zoom;
    crop.tx = ax - ratio * (ax - crop.tx);
    crop.ty = ay - ratio * (ay - crop.ty);
    crop.zoom = z;
    cropClamp();
    cropRender();
  }

  function cropRender() {
    if (!crop.img || !cropImg) return;
    var k = crop.zoom / crop.cover;
    cropImg.style.width = (crop.natW * crop.cover) + 'px';
    cropImg.style.height = (crop.natH * crop.cover) + 'px';
    cropImg.style.transform = 'matrix(' + k + ', 0, 0, ' + k + ', ' + crop.tx + ', ' + crop.ty + ')';
    if (cropZoom) cropZoom.value = Math.max(0, Math.min(100, Math.round(((crop.zoom - crop.cover) / (crop.cover * 5)) * 100)));
    cropScheduleSidebar();
  }

  function cropScheduleSidebar() {
    if (crop._pvTimer || !crop.img) return;
    crop._pvTimer = setTimeout(function () {
      crop._pvTimer = null;
      cropUpdateSidebar();
    }, 60);
  }

  function cropSourceRect() {
    var d = Math.min(crop.viewW, crop.viewH);
    var n = d / crop.zoom;
    var cx = (-crop.tx + d / 2) / crop.zoom;
    var cy = (-crop.ty + d / 2) / crop.zoom;
    var sx = cx - n / 2;
    var sy = cy - n / 2;
    if (sx < 0) sx = 0;
    if (sy < 0) sy = 0;
    if (sx + n > crop.natW) sx = crop.natW - n;
    if (sy + n > crop.natH) sy = crop.natH - n;
    if (n < 1) return null;
    return { sx: sx, sy: sy, size: n };
  }

  function cropUpdateSidebar() {
    if (!crop._nat || !avatarBox) return;
    var r = cropSourceRect();
    if (!r) return;
    var cv = document.createElement('canvas');
    cv.width = 96;
    cv.height = 96;
    var c = cv.getContext('2d');
    c.beginPath();
    c.arc(48, 48, 48, 0, Math.PI * 2);
    c.clip();
    c.drawImage(crop._nat, r.sx, r.sy, r.size, r.size, 0, 0, 96, 96);
    avatarShowSidebarPreview(cv.toDataURL('image/png'));
  }

  function cropExport() {
    var r = cropSourceRect();
    if (!r || !crop._nat) return null;
    var OUT = 512;
    var cv = document.createElement('canvas');
    cv.width = OUT;
    cv.height = OUT;
    var c = cv.getContext('2d');
    c.beginPath();
    c.arc(OUT / 2, OUT / 2, OUT / 2, 0, Math.PI * 2);
    c.clip();
    c.drawImage(crop._nat, r.sx, r.sy, r.size, r.size, 0, 0, OUT, OUT);
    return cv;
  }

  function cropShowImage(dataURL) {
    var img = new Image();
    img.onload = function () {
      crop._nat = img;
      crop.img = img;
      crop.natW = img.naturalWidth;
      crop.natH = img.naturalHeight;
      var st = cropLensSize();
      crop.viewW = st.w;
      crop.viewH = st.h;
      crop.cover = Math.max(crop.viewW / crop.natW, crop.viewH / crop.natH);
      crop.zoom = crop.cover;
      crop.tx = (crop.viewW - crop.natW * crop.zoom) / 2;
      crop.ty = (crop.viewH - crop.natH * crop.zoom) / 2;
      cropImg.src = dataURL;
      cropImg.hidden = false;
      if (avatarPreviewInitials) avatarPreviewInitials.style.display = 'none';
      if (cropFrame) cropFrame.hidden = false;
      if (cropControls) cropControls.hidden = false;
      if (avatarDropzone) avatarDropzone.hidden = true;
      cropRender();
      if (avatarSaveBtn) avatarSaveBtn.disabled = false;
    };
    img.src = dataURL;
  }

  function cropResetUI() {
    crop._nat = null;
    crop.img = null;
    if (cropImg) {
      cropImg.removeAttribute('src');
      cropImg.hidden = true;
    }
    if (cropFrame) cropFrame.hidden = true;
    if (cropControls) cropControls.hidden = true;
    if (avatarDropzone) avatarDropzone.hidden = false;
  }

  function avatarHandleFile(file) {
    if (!avatarError || !avatarSaveBtn) return;
    avatarError.hidden = true;
    var err = avatarValidate(file);
    if (err) {
      avatarShowError(err);
      avatarSaveBtn.disabled = true;
      return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
      cropShowImage(e.target.result);
    };
    reader.readAsDataURL(file);
  }

  function setAvatarFiles(files) {
    if (!avatarFile || !files || !files.length) return;
    try {
      var dt = new DataTransfer();
      dt.items.add(files[0]);
      avatarFile.files = dt.files;
    } catch (e) {
      avatarShowError('Não foi possível anexar a imagem. Use o botão "Selecionar imagem".');
      return;
    }
    avatarHandleFile(avatarFile.files[0]);
  }

  function openAvatarModal() {
    if (!avatarModal) return;
    avatarRestoreSidebarAvatar();
    cropResetUI();
    if (avatarError) avatarError.hidden = true;
    if (avatarSaveBtn) avatarSaveBtn.disabled = true;
    if (avatarFile) avatarFile.value = '';
    avatarShowPreview(avatarImg);
    avatarModal.classList.add('is-open');
  }

  function closeAvatarModal() {
    avatarRestoreSidebarAvatar();
    if (avatarModal) avatarModal.classList.remove('is-open');
  }

  if (avatarModal) {
    avatarModal.addEventListener('click', function (e) {
      if (e.target === avatarModal) closeAvatarModal();
    });
  }

  if (avatarFile) {
    avatarFile.addEventListener('change', function () {
      if (avatarFile.files && avatarFile.files.length) avatarHandleFile(avatarFile.files[0]);
    });
  }

  if (avatarPreview) {
    avatarPreview.addEventListener('pointerdown', function (e) {
      if (!crop.img || e.button === 2) return;
      cropDrag = { x: e.clientX, y: e.clientY, tx: crop.tx, ty: crop.ty };
      if (avatarPreview.setPointerCapture) avatarPreview.setPointerCapture(e.pointerId);
      e.preventDefault();
    });
    avatarPreview.addEventListener('pointermove', function (e) {
      if (!cropDrag || !crop.img) return;
      crop.tx = cropDrag.tx + (e.clientX - cropDrag.x);
      crop.ty = cropDrag.ty + (e.clientY - cropDrag.y);
      cropClamp();
      cropRender();
    });
    avatarPreview.addEventListener('pointerup', function () { cropDrag = null; });
    avatarPreview.addEventListener('pointercancel', function () { cropDrag = null; });

    avatarPreview.addEventListener('wheel', function (e) {
      if (!crop.img) return;
      e.preventDefault();
      var r = avatarPreview.getBoundingClientRect();
      var z = crop.zoom * (e.deltaY < 0 ? 1.12 : 1 / 1.12);
      cropSetZoom(z, e.clientX - r.left, e.clientY - r.top);
    }, { passive: false });
  }

  if (cropZoomIn) {
    cropZoomIn.addEventListener('click', function () { cropSetZoom(crop.zoom * 1.25); });
  }
  if (cropZoomOut) {
    cropZoomOut.addEventListener('click', function () { cropSetZoom(crop.zoom * 0.8); });
  }
  if (cropZoom) {
    cropZoom.addEventListener('input', function () {
      if (!crop.img) return;
      var pct = (parseInt(cropZoom.value, 10) || 0) / 100;
      cropSetZoom(crop.cover + pct * crop.cover * 5);
    });
  }

  if (avatarSaveBtn) {
    avatarSaveBtn.addEventListener('click', function () {
      if (!crop._nat) return;
      var cv = cropExport();
      if (!cv) return;
      cv.toBlob(function (blob) {
        if (!blob) {
          avatarShowError('Não foi possível gerar a imagem. Tente novamente.');
          return;
        }
        try {
          var dt = new DataTransfer();
          var oldName = (avatarFile.files && avatarFile.files[0]) ? avatarFile.files[0].name : 'avatar';
          var base = String(oldName).replace(/\.[^.]+$/, '') || 'avatar';
          dt.items.add(new File([blob], base + '.png', { type: 'image/png' }));
          avatarFile.files = dt.files;
        } catch (e) {
          avatarShowError('Não foi possível anexar a imagem recortada. Use o botão "Selecionar imagem" ou outro navegador.');
          return;
        }
        avatarShowSidebarPreview(cv.toDataURL('image/png'));
        avatarForm.submit();
      }, 'image/png');
    });
  }

  if (avatarDropzone) {
    avatarDropzone.addEventListener('dragover', function (e) { e.preventDefault(); avatarDropzone.classList.add('is-drag'); });
    avatarDropzone.addEventListener('dragleave', function () { avatarDropzone.classList.remove('is-drag'); });
    avatarDropzone.addEventListener('drop', function (e) {
      e.preventDefault();
      avatarDropzone.classList.remove('is-drag');
      setAvatarFiles(e.dataTransfer.files);
    });
  }

  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAvatarModal(); });

  // Mostrar/ocultar senhas
  document.querySelectorAll('.ac-pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-toggle-target'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show ? '<i class="far fa-eye-slash"></i>' : '<i class="far fa-eye"></i>';
    });
  });

  // CEP
  const cepInput = document.getElementById('postal_code');
  const streetInput = document.getElementById('street');
  const cepFeedback = document.getElementById('cepFeedback');
  let cepTimer = null;
  let cepController = null;

  function showCepFeedback(msg, type) {
    if (!cepFeedback) return;
    cepFeedback.hidden = false;
    cepFeedback.textContent = msg;
    cepFeedback.className = 'ac-cep-note' + (type ? ' ' + type : '');
  }

  function hideCepFeedback() {
    if (!cepFeedback) return;
    cepFeedback.hidden = true;
    cepFeedback.textContent = '';
    cepFeedback.className = 'ac-cep-note';
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
    const requestedCep = cep;
    const timeoutId = setTimeout(() => myController.abort(), 6000);
    showCepFeedback('Consultando CEP...', '');
    fetch('https://viacep.com.br/ws/' + cep + '/json/', { signal: myController.signal })
      .then(function (response) {
        if (response.status !== 200) {
          showCepFeedback('CEP não encontrado. Você pode preencher a rua manualmente.', 'error');
          return null;
        }
        return response.json();
      })
      .then(function (data) {
        clearTimeout(timeoutId);
        if (myController !== cepController) return;
        if (cepInput.value.replace(/\D/g, '') !== requestedCep) return;
        if (!data) return;
        if (data.erro) {
          showCepFeedback('CEP não encontrado. Preencha a rua manualmente se preferir.', 'error');
          if (streetInput) streetInput.value = '';
          return;
        }
        if (streetInput) streetInput.value = data.logradouro || '';
        var parts = [];
        if (data.bairro) parts.push(data.bairro);
        if (data.localidade) parts.push(data.localidade);
        var summary = parts.join(', ') + (data.uf ? ' - ' + data.uf : '');
        showCepFeedback(summary ? 'Endereço encontrado: ' + summary : 'CEP encontrado.', 'ok');
      })
      .catch(function (err) {
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
    cepInput.addEventListener('input', function () {
      clearTimeout(cepTimer);
      cepTimer = setTimeout(lookupCep, 400);
    });
    cepInput.addEventListener('blur', function () {
      clearTimeout(cepTimer);
      lookupCep();
    });
  }
</script>