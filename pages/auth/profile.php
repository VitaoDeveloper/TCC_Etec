<?php

declare(strict_types=1);

/**
 * Meu Perfil — página única rolável.
 *
 * Sem card de menu/abas e sem card de avatar: o avatar (e o badge de
 * câmera que troca a foto) mora na sidebar, e os painéis viraram
 * seções empilhadas nesta ordem — Dados Pessoais, Endereço,
 * Endereços Salvos, Alterar Senha, Notificações, Cartões Salvos.
 *
 * Um único botão "Salvar Alterações" grava Dados Pessoais + Endereço
 * num mesmo POST. A senha tem botão próprio; as notificações salvam ao
 * alternar (AJAX), sem botão.
 *
 * Especificação: docs/UI_SPEC.md (fonte de verdade).
 */

require_once __DIR__ . '/../../includes/account_layout.php';
require_once __DIR__ . '/../../includes/validators.php';
require_once __DIR__ . '/../../database/connection.php';

// Incluir Cropper.js para o editor de avatar
$GLOBALS['extra_head_css'] = [
    asset_url('assets/vendor/cropperjs/cropper.min.css'),
];

$user = account_require_login($pdo);

// ---------------------------------------------------------------------
//  Flash (PRG)
// ---------------------------------------------------------------------
$flash = ['type' => 'ok', 'message' => ''];
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
//  Avatar (badge da sidebar; sem card de avatar nesta página)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'avatar') {
    csrf_require_valid();

    if (empty($_FILES['avatar']['name'])) {
        $_SESSION['error'] = 'Escolha uma imagem antes de enviar.';
        header('Location: ' . base_url('pages/auth/profile.php'));
        exit;
    }

    $file     = $_FILES['avatar'];
    $tmpName  = (string) $file['tmp_name'];
    $size     = (int) $file['size'];
    $ext      = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $extMap   = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];
    $mimeMap  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    if ($size > 2 * 1024 * 1024) {
        $_SESSION['error'] = 'A imagem excede o tamanho máximo de 2 MB.';
    } elseif (!isset($extMap[$ext])) {
        $_SESSION['error'] = 'Formato não aceito. Use JPG, PNG ou WebP.';
    } else {
        // Validar MIME type real do arquivo (não confiar na extensão)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpName);
        if ($mime !== $mimeMap[$ext]) {
            $_SESSION['error'] = 'O arquivo não é uma imagem válida.';
        } else {
            $dir = dirname(__DIR__, 2) . '/assets/uploads/avatars';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $filename = 'user_' . (int) $_SESSION['user_id'] . '_' . bin2hex(random_bytes(6)) . '.' . $extMap[$ext];
            if (move_uploaded_file($tmpName, $dir . '/' . $filename)) {
                $oldPath = (string) ($user['avatar_path'] ?? '');
                if ($oldPath !== '' && (str_contains($oldPath, 'uploads/avatars/') || str_contains($oldPath, 'assets/uploads/avatars/'))) {
                    $oldAbs = dirname(__DIR__, 2) . '/' . ltrim($oldPath, '/');
                    if (is_file($oldAbs)) {
                        @unlink($oldAbs);
                    }
                }

                $relative = 'assets/uploads/avatars/' . $filename;
                $pdo->prepare('UPDATE e5_users SET avatar_path = :path WHERE id = :id')
                    ->execute([':path' => $relative, ':id' => $_SESSION['user_id']]);
                $user['avatar_path'] = $relative;

                $_SESSION['success'] = 'Foto de perfil atualizada!';
            } else {
                $_SESSION['error'] = 'Não foi possível salvar a imagem.';
            }
        }
    }

    header('Location: ' . base_url('pages/auth/profile.php'));
    exit;
}

// ---------------------------------------------------------------------
//  Avatar Crop (recorte via Cropper.js — recebe Blob via AJAX)
// ---------------------------------------------------------------------
$input = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_starts_with($contentType, 'application/json')) {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
    }
}

$action = $_POST['action'] ?? ($input['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'avatar_crop') {
    csrf_require_valid();
    if (!$input || empty($input['image'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Imagem não fornecida.']);
        exit;
    }

    // Decodifica base64 (formato: data:image/jpeg;base64,....)
    $base64 = $input['image'];
    if (str_starts_with($base64, 'data:image/')) {
        $base64 = preg_replace('#^data:image/[^;]+;base64,#', '', $base64);
    }
    $binary = base64_decode($base64);
    if ($binary === false) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Imagem inválida (base64).']);
        exit;
    }

    // Valida tamanho do recorte (máx 2 MB)
    if (strlen($binary) > 2 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'message' => 'O recorte excede 2 MB.']);
        exit;
    }

    // Salva temporariamente para validar com finfo/getimagesize
    $tmpName = sys_get_temp_dir() . '/avatar_crop_' . bin2hex(random_bytes(8)) . '.jpg';
    file_put_contents($tmpName, $binary);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMime, true)) {
        @unlink($tmpName);
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Formato não suportado. Use JPG, PNG ou WebP.']);
        exit;
    }

    $imgInfo = getimagesize($tmpName);
    if (!$imgInfo) {
        @unlink($tmpName);
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Arquivo não é uma imagem válida.']);
        exit;
    }

    // Reprocessa a imagem no servidor (remove EXIF, redimensiona para 512x512, converte para JPEG)
    $src = null;
    switch ($mime) {
        case 'image/jpeg': $src = imagecreatefromjpeg($tmpName); break;
        case 'image/png':  $src = imagecreatefrompng($tmpName); break;
        case 'image/webp': $src = imagecreatefromwebp($tmpName); break;
    }
    if (!$src) {
        @unlink($tmpName);
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Não foi possível processar a imagem.']);
        exit;
    }

    // Cria canvas 512x512 com fundo branco
    $dst = imagecreatetruecolor(512, 512);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    // Copia redimensionando mantendo proporção (crop centralizado já feito no front)
    imagecopyresampled($dst, $src, 0, 0, 0, 0, 512, 512, $imgInfo[0], $imgInfo[1]);

    // Remove arquivo temporário
    @unlink($tmpName);
    imagedestroy($src);

    // Salva em assets/uploads/avatars/
    $dir = dirname(__DIR__, 2) . '/assets/uploads/avatars';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    // Remove avatar antigo
    $oldPath = (string) ($user['avatar_path'] ?? '');
    if ($oldPath !== '' && (str_contains($oldPath, 'uploads/avatars/') || str_contains($oldPath, 'assets/uploads/avatars/'))) {
        $oldAbs = dirname(__DIR__, 2) . '/' . ltrim($oldPath, '/');
        if (is_file($oldAbs)) {
            @unlink($oldAbs);
        }
    }

    // Gera nome aleatório e salva como JPEG qualidade 90
    $filename = 'user_' . (int) $_SESSION['user_id'] . '_' . bin2hex(random_bytes(6)) . '.jpg';
    $savePath = $dir . '/' . $filename;
    $saved = imagejpeg($dst, $savePath, 90);
    imagedestroy($dst);

    if (!$saved) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Não foi possível salvar a imagem.']);
        exit;
    }

    // Atualiza banco
    $relative = 'assets/uploads/avatars/' . $filename;
    $pdo->prepare('UPDATE e5_users SET avatar_path = :path WHERE id = :id')
        ->execute([':path' => $relative, ':id' => $_SESSION['user_id']]);

    // Retorna URL com cache-busting
    $avatarUrl = base_url($relative) . '?v=' . time();
    echo json_encode([
        'ok' => true,
        'message' => 'Foto de perfil atualizada!',
        'avatar_url' => $avatarUrl,
    ]);
    exit;
}

// ---------------------------------------------------------------------
//  Avatar Remove (volta às iniciais)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'avatar_remove') {
    csrf_require_valid();

    $oldPath = (string) ($user['avatar_path'] ?? '');
    if ($oldPath !== '' && (str_contains($oldPath, 'uploads/avatars/') || str_contains($oldPath, 'assets/uploads/avatars/'))) {
        $oldAbs = dirname(__DIR__, 2) . '/' . ltrim($oldPath, '/');
        if (is_file($oldAbs)) {
            @unlink($oldAbs);
        }
    }

    $pdo->prepare('UPDATE e5_users SET avatar_path = NULL WHERE id = :id')
        ->execute([':id' => $_SESSION['user_id']]);

    echo json_encode(['ok' => true, 'message' => 'Foto removida.']);
    exit;
}

// ---------------------------------------------------------------------
//  Salvar Alterações (Dados Pessoais + Endereço, num POST só)
//
//  Salvar Alterações (Dados Pessoais apenas — Endereço é gerenciado
//  separadamente em "Endereços Salvos" via API).
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'personal') {
    csrf_require_valid();

    $input = [
        'name'     => clean_text($_POST['name'] ?? ''),
        'email'    => strtolower(trim((string) ($_POST['email'] ?? ''))),
        'username' => clean_text($_POST['username'] ?? ''),
        'cpf'      => only_digits($_POST['cpf'] ?? ''),
        'phone'    => only_digits($_POST['phone'] ?? ''),
    ];

    $errors = [];

    // Username só é validado quando muda (contas legadas podem ter um
    // valor fora do formato novo enquanto não for alterado).
    $stmt = $pdo->prepare('SELECT username FROM e5_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $currentRow      = $stmt->fetch() ?: [];
    $currentUsername = (string) ($currentRow['username'] ?? '');
    $usernameChanged = $input['username'] !== $currentUsername;

    if ($input['name'] === '') {
        $errors['name'] = 'Informe seu nome.';
    } elseif (mb_strlen($input['name']) > 80) {
        $errors['name'] = 'O nome deve ter no máximo 80 caracteres.';
    }

    if (!is_valid_email($input['email'])) {
        $errors['email'] = 'E-mail inválido.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM e5_users WHERE email = :email AND id != :id LIMIT 1');
        $stmt->execute([':email' => $input['email'], ':id' => (int) $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Este e-mail já está em uso por outra conta.';
        }
    }

    if ($usernameChanged) {
        if (!is_valid_username($input['username'])) {
            $errors['username'] = '3 a 30 caracteres';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM e5_users WHERE username = :username AND id != :id LIMIT 1');
            $stmt->execute([':username' => $input['username'], ':id' => (int) $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $errors['username'] = 'Este nome de usuário já está em uso.';
            }
        }
    }

    if ($input['cpf'] !== '' && !is_valid_cpf($input['cpf'])) {
        $errors['cpf'] = 'CPF inválido.';
    }

    if ($errors !== []) {
        $_SESSION['error'] = reset($errors);
        header('Location: ' . base_url('pages/auth/profile.php'));
        exit;
    }

    $pdo->prepare(
        'UPDATE e5_users SET
            name = :name, email = :email, username = :username, cpf = :cpf,
            phone = :phone, updated_at = NOW()
         WHERE id = :id'
    )->execute([
        ':name'     => $input['name'],
        ':email'    => $input['email'],
        ':username' => $input['username'],
        ':cpf'      => $input['cpf'],
        ':phone'    => $input['phone'] !== '' ? $input['phone'] : null,
        ':id'       => (int) $_SESSION['user_id'],
    ]);

    $_SESSION['success'] = 'Dados pessoais atualizados.';
    header('Location: ' . base_url('pages/auth/profile.php'));
    exit;
}

// ---------------------------------------------------------------------
//  Notificações (AJAX; o POST server-side fica como fallback sem JS)
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
    header('Location: ' . base_url('pages/auth/profile.php'));
    exit;
}

// ---------------------------------------------------------------------
//  Alterar senha
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
        $errors['new_password'] = 'A nova senha pode ter no máximo 72 caracteres.';
    } elseif ($newPassword !== $confirmation) {
        $errors['confirm_password'] = 'A confirmação não confere com a nova senha.';
    }

    if ($errors === []) {
        $pdo->prepare('UPDATE e5_users SET password = :hash, updated_at = NOW() WHERE id = :id')
            ->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => (int) $_SESSION['user_id']]);

        $_SESSION['success'] = 'Senha alterada com sucesso.';
        header('Location: ' . base_url('pages/auth/profile.php'));
        exit;
    }

    $_SESSION['error'] = reset($errors);
    header('Location: ' . base_url('pages/auth/profile.php'));
    exit;
}

// ---------------------------------------------------------------------
//  Endereços salvos — fallback sem JS (espelha api/account/address.php)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['create', 'address', 'set_default', 'update', 'delete'], true)) {
    csrf_require_valid();

    $action = $_POST['action'];

    if ($action === 'create' || $action === 'address') {
        $cep    = only_digits($_POST['postal_code'] ?? '');
        $label  = clean_text($_POST['label'] ?? '');
        $street = clean_text($_POST['street'] ?? '');
        $number = clean_text($_POST['number'] ?? '');
        $city   = clean_text($_POST['city'] ?? '');
        $state  = strtoupper(clean_text($_POST['state'] ?? ''));

        $errors = [];

        if ($label === '') {
            $errors['label'] = 'Informe um apelido para o endereço.';
        }
        if (!is_valid_cep($cep)) {
            $errors['postal_code'] = 'CEP inválido.';
        }
        if ($street === '') {
            $errors['street'] = 'Informe a rua.';
        }
        if ($number === '') {
            $errors['number'] = 'Informe o número.';
        }
        if ($city === '') {
            $errors['city'] = 'Informe a cidade.';
        }
        if (!is_valid_uf($state)) {
            $errors['state'] = 'UF inválida.';
        }

        // Teto de endereços por cliente (a API também aplica).
        $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM e5_addresses WHERE user_id = :uid');
        $stmtCount->execute([':uid' => (int) $_SESSION['user_id']]);
        $count = (int) $stmtCount->fetchColumn();

        if ($count >= 10) {
            $errors['label'] = 'Você já atingiu o limite de 10 endereços salvos.';
        }

        if ($errors !== []) {
            $_SESSION['error'] = reset($errors);
            header('Location: ' . base_url('pages/auth/profile.php'));
            exit;
        }

        // Sem endereço padrão ainda: este vira o padrão.
        $stmtDefault = $pdo->prepare('SELECT COUNT(*) FROM e5_addresses WHERE user_id = :uid');
        $stmtDefault->execute([':uid' => (int) $_SESSION['user_id']]);
        $isDefault = (int) $stmtDefault->fetchColumn() === 0 ? 1 : 0;

        $pdo->prepare(
            'INSERT INTO e5_addresses
                (user_id, label, postal_code, street, number, complement,
                 neighborhood, city, state, is_default, created_at)
             VALUES (:uid, :label, :cep, :street, :number, :complement,
                     :neighborhood, :city, :state, :is_default, NOW())'
        )->execute([
            ':uid'          => (int) $_SESSION['user_id'],
            ':label'        => $label,
            ':cep'          => format_cep($cep),
            ':street'       => $street,
            ':number'       => $number,
            ':complement'   => clean_text($_POST['complement'] ?? '') ?: null,
            ':neighborhood' => clean_text($_POST['neighborhood'] ?? '') ?: null,
            ':city'         => $city,
            ':state'        => $state,
            ':is_default'   => $isDefault,
        ]);

        $_SESSION['success'] = 'Endereço salvo.';
        header('Location: ' . base_url('pages/auth/profile.php'));
        exit;
    }

    if ($action === 'set_default') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            // Verifica se o endereço pertence ao usuário
            $stmt = $pdo->prepare('SELECT id FROM e5_addresses WHERE id = :id AND user_id = :uid');
            $stmt->execute([':id' => $id, ':uid' => (int) $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $pdo->prepare('UPDATE e5_addresses SET is_default = 0 WHERE user_id = :uid')
                    ->execute([':uid' => (int) $_SESSION['user_id']]);
                $pdo->prepare('UPDATE e5_addresses SET is_default = 1 WHERE id = :id')
                    ->execute([':id' => $id]);
                $_SESSION['success'] = 'Endereço padrão atualizado.';
            } else {
                $_SESSION['error'] = 'Endereço não encontrado.';
            }
        }
        header('Location: ' . base_url('pages/auth/profile.php'));
        exit;
    }

    if ($action === 'update') {
        $id      = (int) ($_POST['id'] ?? 0);
        $cep     = only_digits($_POST['postal_code'] ?? '');
        $label   = clean_text($_POST['label'] ?? '');
        $street  = clean_text($_POST['street'] ?? '');
        $number  = clean_text($_POST['number'] ?? '');
        $city    = clean_text($_POST['city'] ?? '');
        $state   = strtoupper(clean_text($_POST['state'] ?? ''));

        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT id FROM e5_addresses WHERE id = :id AND user_id = :uid');
            $stmt->execute([':id' => $id, ':uid' => (int) $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $errors = [];
                if ($label === '') { $errors['label'] = 'Informe um apelido para o endereço.'; }
                if (!is_valid_cep($cep)) { $errors['postal_code'] = 'CEP inválido.'; }
                if ($street === '') { $errors['street'] = 'Informe a rua.'; }
                if ($number === '') { $errors['number'] = 'Informe o número.'; }
                if ($city === '') { $errors['city'] = 'Informe a cidade.'; }
                if (!is_valid_uf($state)) { $errors['state'] = 'UF inválida.'; }

                if ($errors === []) {
                    $pdo->prepare(
                        'UPDATE e5_addresses SET
                            label = :label, postal_code = :cep, street = :street,
                            number = :number, complement = :complement,
                            neighborhood = :neighborhood, city = :city, state = :state,
                            updated_at = NOW()
                         WHERE id = :id'
                    )->execute([
                        ':label'        => $label,
                        ':cep'          => format_cep($cep),
                        ':street'       => $street,
                        ':number'       => $number,
                        ':complement'   => clean_text($_POST['complement'] ?? '') ?: null,
                        ':neighborhood' => clean_text($_POST['neighborhood'] ?? '') ?: null,
                        ':city'         => $city,
                        ':state'        => $state,
                        ':id'           => $id,
                    ]);
                    $_SESSION['success'] = 'Endereço atualizado.';
                } else {
                    $_SESSION['error'] = reset($errors);
                }
            } else {
                $_SESSION['error'] = 'Endereço não encontrado.';
            }
        }
        header('Location: ' . base_url('pages/auth/profile.php'));
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT is_default FROM e5_addresses WHERE id = :id AND user_id = :uid');
            $stmt->execute([':id' => $id, ':uid' => (int) $_SESSION['user_id']]);
            $row = $stmt->fetch();
            if ($row) {
                $wasDefault = (int) $row['is_default'] === 1;
                $pdo->prepare('DELETE FROM e5_addresses WHERE id = :id')
                    ->execute([':id' => $id]);
                if ($wasDefault) {
                    // Define outro como padrão
                    $pdo->prepare('UPDATE e5_addresses SET is_default = 1 WHERE user_id = :uid ORDER BY id ASC LIMIT 1')
                        ->execute([':uid' => (int) $_SESSION['user_id']]);
                }
                $_SESSION['success'] = 'Endereço removido.';
            } else {
                $_SESSION['error'] = 'Endereço não encontrado.';
            }
        }
        header('Location: ' . base_url('pages/auth/profile.php'));
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

// Endereços salvos: mesma ordenação da API (padrão primeiro, depois
// mais recentes). O empty-state da especificação só aparece quando a
// lista fica realmente vazia.
$addrSt = $pdo->prepare(
    'SELECT id, label, postal_code, street, number, complement,
            neighborhood, city, state, is_default
       FROM e5_addresses
      WHERE user_id = :uid
      ORDER BY is_default DESC, id DESC'
);
$addrSt->execute([':uid' => (int) $user['id']]);
$addresses = $addrSt->fetchAll();

// A lista é impressa também como JSON para o botão "Editar" preencher o
// modal sem um request extra.
$addressesJson = json_encode(
    array_map(static function (array $a): array {
        return [
            'id'           => (int) $a['id'],
            'label'        => (string) $a['label'],
            'postal_code'  => (string) $a['postal_code'],
            'street'       => (string) $a['street'],
            'number'       => (string) $a['number'],
            'complement'   => (string) ($a['complement'] ?? ''),
            'neighborhood' => (string) ($a['neighborhood'] ?? ''),
            'city'         => (string) $a['city'],
            'state'        => (string) $a['state'],
            'is_default'   => (int) $a['is_default'] === 1,
        ];
    }, $addresses),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

account_layout_head($user, 'perfil');
?>

<main class="account-content">

<header class="account-page-header">
    <h1 class="account-page-title">Meu Perfil</h1>
    <p class="account-page-subtitle">Gerencie seus dados pessoais e preferências atualizadas</p>
</header>

<?php if ($flash['message'] !== ''): ?>
    <div class="account-alert account-alert--<?php echo e($flash['type']); ?>" role="status">
        <i class="fas <?php echo $flash['type'] === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>" aria-hidden="true"></i>
        <span><?php echo e($flash['message']); ?></span>
    </div>
<?php endif; ?>

<form id="profile-form" method="post" novalidate
      data-account-form data-profile-form
      data-endpoint="<?php echo e(base_url('api/account/profile.php')); ?>"
      action="<?php echo e(base_url('pages/auth/profile.php')); ?>">
    <input type="hidden" name="action" value="personal">
    <?php echo csrf_field(); ?>

    <!-- ============================ Dados Pessoais ============================ -->
    <section class="account-card" id="secao-dados">
        <div class="account-card-head">
            <span class="account-card-icon" aria-hidden="true"><i class="fas fa-id-card"></i></span>
            <div>
                <h2 class="account-card-title">Dados Pessoais</h2>
                <p class="account-card-hint">Informações da sua conta e identificação</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="account-field account-field--full">
                <label class="account-label" for="name">Nome completo</label>
                <input class="account-input" type="text" id="name" name="name" maxlength="80"
                       value="<?php echo e($user['name'] ?? ''); ?>" required>
            </div>

            <div class="account-field">
                <label class="account-label" for="cpf">CPF</label>
                <input class="account-input" type="text" id="cpf" name="cpf" maxlength="14"
                       value="<?php echo e($user['cpf'] ? format_cpf($user['cpf']) : ''); ?>"
                       inputmode="numeric" placeholder="000.000.000-00">
            </div>

            <div class="account-field">
                <label class="account-label" for="email">E-mail</label>
                <input class="account-input" type="email" id="email" name="email" maxlength="120"
                       value="<?php echo e($user['email'] ?? ''); ?>" required>
            </div>

            <div class="account-field">
                <label class="account-label" for="username">Nome de usuário</label>
                <input class="account-input" type="text" id="username" name="username" maxlength="30"
                       value="<?php echo e($user['username'] ?? ''); ?>" required>
            </div>

            <div class="account-field">
                <label class="account-label" for="phone">Telefone / Celular</label>
                <input class="account-input" type="tel" id="phone" name="phone" maxlength="16"
                       value="<?php echo e($user['phone'] ? format_phone($user['phone']) : ''); ?>"
                       inputmode="tel" placeholder="(12) 97814-9392">
            </div>

            <!-- Rótulo "SENHA" (não "Senha atual"): o único campo
                 desabilitado da página, metade da largura. -->
            <div class="account-field">
                <label class="account-label" for="passwordMasked">Senha</label>
                <div class="account-input-group">
                    <input class="account-input" type="password" id="passwordMasked"
                           value="<?php echo e($maskedPassword); ?>" disabled>
                    <span class="account-input-icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
                </div>
            </div>
        </div>
    </section>

<!-- ============================ Endereços Salvos ============================ -->
<section class="account-card" id="secao-enderecos-salvos">
    <div class="account-card-head">
        <span class="account-card-icon" aria-hidden="true"><i class="fas fa-map-marked-alt"></i></span>
        <div>
            <h2 class="account-card-title">Endereços Salvos</h2>
            <p class="account-card-hint">Gerencie múltiplos endereços de entrega</p>
        </div>
        <button type="button" class="account-btn account-btn--outline" data-modal-open="modal-address" data-address-reset>
            <i class="fas fa-plus" aria-hidden="true"></i> Adicionar endereço
        </button>
    </div>

        <?php if ($addresses === []): ?>
            <div class="account-empty account-empty--dashed">
                <i class="fas fa-map-location-dot" aria-hidden="true"></i>
                <p>Nenhum endereço salvo.</p>
                <button type="button" class="account-btn account-btn--outline" data-modal-open="modal-address">
                    <i class="fas fa-plus" aria-hidden="true"></i> Adicionar endereço
                </button>
            </div>
        <?php else: ?>
            <div class="account-address-list">
                <?php foreach ($addresses as $address): ?>
                    <?php $isDefault = (int) $address['is_default'] === 1; ?>
                    <article class="account-address<?= $isDefault ? ' is-default' : '' ?>"
                             data-address-id="<?= e((string) $address['id']) ?>">
                        <header class="account-address-head">
                            <span class="account-address-icon" aria-hidden="true">
                                <i class="fas <?= $isDefault ? 'fa-house-chimney' : 'fa-location-dot' ?>"></i>
                            </span>
                            <h3 class="account-address-title"><?= e((string) $address['label']) ?></h3>
                            <?php if ($isDefault): ?>
                                <span class="account-badge account-badge--active">Padrão</span>
                            <?php endif; ?>
                        </header>

                        <p class="account-address-text">
                            <?= e((string) $address['street']) ?>, <?= e((string) $address['number']) ?>
                            <?php if (($address['complement'] ?? '') !== ''): ?>
                                &mdash; <?= e((string) $address['complement']) ?>
                            <?php endif; ?>
                        </p>
                        <p class="account-address-text">
                            <?= e((string) $address['neighborhood'] ?? '') ?><?php
                                echo ($address['neighborhood'] ?? '') !== '' ? ' &middot; ' : '';
                            ?><?= e((string) $address['city']) ?>/<?= e((string) $address['state']) ?>
                            &middot; CEP <?= e((string) $address['postal_code']) ?>
                        </p>

                        <div class="account-actions">
                            <?php if (!$isDefault): ?>
                                <button type="button" class="account-btn account-btn--sm"
                                        data-address-action="set_default"
                                        data-address-id="<?= e((string) $address['id']) ?>">
                                    <i class="fas fa-star" aria-hidden="true"></i> Tornar padrão
                                </button>
                            <?php endif; ?>

                            <button type="button" class="account-btn account-btn--sm"
                                    data-address-action="edit"
                                    data-address-id="<?= e((string) $address['id']) ?>">
                                <i class="fas fa-pen" aria-hidden="true"></i> Editar
                            </button>

                            <button type="button" class="account-btn account-btn--sm account-btn--danger"
                                    data-address-action="delete"
                                    data-address-id="<?= e((string) $address['id']) ?>">
                                <i class="fas fa-trash-can" aria-hidden="true"></i> Remover
                            </button>

                            <span class="account-save-status" data-address-status role="status" aria-live="polite"></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <p class="account-hint">Você pode salvar até 10 endereços.</p>
        <?php endif; ?>
    </section>

    <!-- ============================ Alterar Senha ============================ -->
    <section class="account-card" id="secao-senha">
        <div class="account-card-head">
            <span class="account-card-icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
            <div>
                <h2 class="account-card-title">Alterar Senha</h2>
                <p class="account-card-hint">Atualize sua senha de acesso</p>
            </div>
        </div>

        <form method="post" novalidate data-account-form data-clear-passwords
              data-endpoint="<?php echo e(base_url('api/account/password.php')); ?>"
              action="<?php echo e(base_url('pages/auth/profile.php')); ?>">
            <input type="hidden" name="action" value="password">
            <?php echo csrf_field(); ?>

            <div class="form-grid cols-3">
                <div class="account-field">
                    <label class="account-label" for="current_password">Senha atual</label>
                    <div class="account-input-group">
                        <input class="account-input" type="password" id="current_password"
                               name="current_password" autocomplete="current-password" required>
                        <button type="button" class="account-btn account-btn--sm js-toggle-password"
                                aria-label="Mostrar senha atual">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="account-field">
                    <label class="account-label" for="new_password">Nova senha</label>
                    <div class="account-input-group">
                        <input class="account-input" type="password" id="new_password"
                               name="new_password" minlength="6" maxlength="72"
                               autocomplete="new-password" required>
                        <button type="button" class="account-btn account-btn--sm js-toggle-password"
                                aria-label="Mostrar nova senha">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="account-field">
                    <label class="account-label" for="confirm_password">Confirmar nova senha</label>
                    <div class="account-input-group">
                        <input class="account-input" type="password" id="confirm_password"
                               name="confirm_password" minlength="6" maxlength="72"
                               autocomplete="new-password" required>
                        <button type="button" class="account-btn account-btn--sm js-toggle-password"
                                aria-label="Mostrar confirmação">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="account-actions">
                <button type="submit" class="account-btn account-btn--primary">
                    <i class="fas fa-key" aria-hidden="true"></i> Alterar senha
                </button>
                <span class="account-save-status" data-save-status role="status" aria-live="polite"></span>
            </div>
        </form>
    </section>
</form><!-- fim do form de perfil (Dados Pessoais) -->

<!-- ============================ Preferências de Notificação ============================ -->
<section class="account-card" id="secao-avisos">
    <div class="account-card-head">
        <span class="account-card-icon" aria-hidden="true"><i class="fas fa-bell"></i></span>
        <div>
            <h2 class="account-card-title">Preferências de Notificação</h2>
            <p class="account-card-hint">Escolha por onde quer receber mensagens e atualizações</p>
        </div>
    </div>

    <form method="post" data-account-form data-notifications
          data-endpoint="<?php echo e(base_url('api/account/notifications.php')); ?>"
          action="<?php echo e(base_url('pages/auth/profile.php')); ?>">
        <input type="hidden" name="action" value="notifications">
        <?php echo csrf_field(); ?>

        <div class="account-toggle-row">
            <span class="account-toggle-label">Notificações por e-mail</span>
            <label class="account-toggle">
                <input type="checkbox" name="notify_email" value="1"
                       <?php echo (int) ($user['notify_email'] ?? 1) === 1 ? 'checked' : ''; ?>>
                <span class="account-toggle-track" aria-hidden="true"></span>
                <span class="sr-only">Notificações por e-mail</span>
            </label>
        </div>

        <div class="account-toggle-row">
            <span class="account-toggle-label">Notificações por WhatsApp</span>
            <label class="account-toggle">
                <input type="checkbox" name="notify_whatsapp" value="1"
                       <?php echo (int) ($user['notify_whatsapp'] ?? 1) === 1 ? 'checked' : ''; ?>>
                <span class="account-toggle-track" aria-hidden="true"></span>
                <span class="sr-only">Notificações por WhatsApp</span>
            </label>
        </div>

        <span class="sr-only" data-save-status role="status" aria-live="polite"></span>
    </form>
</section>

<!-- ============================ Ações ============================ -->
<div class="account-actions account-actions--page">
    <button type="submit" class="account-btn account-btn--primary" form="profile-form">
        <i class="fas fa-save" aria-hidden="true"></i> Salvar Alterações
    </button>
    <a href="<?php echo e(base_url('pages/auth/orders.php')); ?>" class="account-btn account-btn--outline">
        <i class="fas fa-box-open" aria-hidden="true"></i> Meus Pedidos
    </a>
</div>

<!-- ============================ Cartões Salvos ============================ -->
<section class="account-card" id="secao-cartoes-salvos">
    <div class="account-card-head">
        <span class="account-card-icon" aria-hidden="true"><i class="fas fa-credit-card"></i></span>
        <div>
            <h2 class="account-card-title">Cartões Salvos</h2>
            <p class="account-card-hint">Métodos de pagamento para compras rápidas</p>
        </div>
    </div>

    <div class="account-empty account-empty--dashed">
        <i class="fas fa-credit-card" aria-hidden="true"></i>
        <p>Nenhum cartão salvo.</p>
        <button type="button" class="account-btn account-btn--outline" data-modal-open="modal-card">
            <i class="fas fa-plus" aria-hidden="true"></i> Adicionar cartão
        </button>
    </div>
</section>

<!-- ============================ Modais (endereço / cartão) ============================ -->
<div class="account-modal" id="modal-address" role="dialog" aria-modal="true"
     aria-labelledby="modal-address-title" hidden>
    <div class="account-modal-backdrop" data-modal-close></div>
    <div class="account-modal-panel">
        <header class="account-modal-head">
            <h3 id="modal-address-title">Adicionar endereço</h3>
            <button type="button" class="account-btn account-btn--sm" data-modal-close aria-label="Fechar">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <!-- O mesmo form serve create e update: o botão "Editar" da lista
             preenche os campos e troca o action para update (ver JS no fim
             deste arquivo). O fallback sem JS cai no handler create no fim. -->
        <form method="post" data-address-form
              data-endpoint="<?php echo e(base_url('api/account/address.php')); ?>"
              action="<?php echo e(base_url('pages/auth/profile.php')); ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="id" value="">
            <?php echo csrf_field(); ?>

            <div class="form-grid">
                <div class="account-field account-field--full">
                    <label class="account-label" for="addr_label">Apelido</label>
                    <input class="account-input" type="text" id="addr_label" name="label"
                           maxlength="40" placeholder="Casa, Trabalho..." required>
                </div>

                <div class="account-field">
                    <label class="account-label" for="addr_postal_code">CEP</label>
                    <input class="account-input" type="text" id="addr_postal_code" name="postal_code"
                           maxlength="9" placeholder="00000-000" inputmode="numeric" required>
                </div>

                <div class="account-field">
                    <label class="account-label" for="addr_number">Número</label>
                    <input class="account-input" type="text" id="addr_number" name="number"
                           maxlength="10" inputmode="numeric" required>
                </div>

                <div class="account-field account-field--full">
                    <label class="account-label" for="addr_street">Rua</label>
                    <input class="account-input" type="text" id="addr_street" name="street"
                           maxlength="120" required>
                </div>

                <div class="account-field account-field--full">
                    <label class="account-label" for="addr_complement">Complemento</label>
                    <input class="account-input" type="text" id="addr_complement" name="complement"
                           maxlength="80">
                </div>

                <div class="account-field">
                    <label class="account-label" for="addr_neighborhood">Bairro</label>
                    <input class="account-input" type="text" id="addr_neighborhood" name="neighborhood"
                           maxlength="80">
                </div>

                <div class="account-field">
                    <label class="account-label" for="addr_city">Cidade</label>
                    <input class="account-input" type="text" id="addr_city" name="city"
                           maxlength="80" required>
                </div>

                <div class="account-field">
                    <label class="account-label" for="addr_state">UF</label>
                    <select class="account-select" id="addr_state" name="state" required>
                        <option value="">—</option>
                        <?php foreach ($ufs as $uf): ?>
                            <option value="<?php echo e($uf); ?>"><?php echo e($uf); ?></option>
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
    </div>
</div>

<div class="account-modal" id="modal-card" role="dialog" aria-modal="true"
     aria-labelledby="modal-card-title" hidden>
    <div class="account-modal-backdrop" data-modal-close></div>
    <div class="account-modal-panel">
        <header class="account-modal-head">
            <h3 id="modal-card-title">Adicionar cartão</h3>
            <button type="button" class="account-btn account-btn--sm" data-modal-close aria-label="Fechar">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <!-- A API de cartões hoje só lista e remove (api/account/cards.php:
             GET + action=delete). Cadastro de cartão exige nova ação na
             API e armazenamento tokenizado; o formulário abaixo fica
             desabilitado até esse endpoint existir, em vez de fingir que
             grava. -->
        <div class="account-empty">
            <i class="fas fa-shield-halved" aria-hidden="true"></i>
            <p>O cadastro de cartões está indisponível no momento.</p>
            <p class="account-hint">Nenhum dado de cartão é armazenado nesta loja.</p>
        </div>
    </div>
</div>

</main>

<!-- Modal: Editor de Avatar (Cropper.js) -->
<div class="account-modal" id="modal-avatar" role="dialog" aria-modal="true"
     aria-labelledby="modal-avatar-title" hidden>
    <div class="account-modal-backdrop" data-avatar-close></div>
    <div class="account-modal-panel account-modal-panel--full">
        <header class="account-modal-head">
            <h3 id="modal-avatar-title">Ajustar foto</h3>
            <button type="button" class="account-btn account-btn--sm" data-avatar-close aria-label="Fechar">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </header>
        <div class="account-modal-body">
            <div class="avatar-editor">
                <!-- Área de corte principal -->
                <div class="avatar-editor-preview">
                    <div class="avatar-editor-frame">
                        <img id="avatarCropperImage" src="" alt="Foto para recortar">
                    </div>
                    <div class="avatar-editor-preview-mini" aria-hidden="true">
                        <div class="avatar-editor-preview-circle"></div>
                    </div>
                </div>
                <!-- Controles -->
                <div class="avatar-editor-controls">
                    <div class="avatar-editor-zoom">
                        <button type="button" class="avatar-editor-btn" id="zoomOut" aria-label="Diminuir zoom"><i class="fas fa-minus"></i></button>
                        <input type="range" id="zoomSlider" min="0.1" max="3" step="0.05" value="1" aria-label="Zoom">
                        <button type="button" class="avatar-editor-btn" id="zoomIn" aria-label="Aumentar zoom"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="avatar-editor-rotate">
                        <button type="button" class="avatar-editor-btn" id="rotateLeft" aria-label="Girar 90° à esquerda"><i class="fas fa-rotate-left"></i></button>
                        <button type="button" class="avatar-editor-btn" id="rotateRight" aria-label="Girar 90° à direita"><i class="fas fa-rotate-right"></i></button>
                    </div>
                </div>
                <!-- Ações -->
                <div class="avatar-editor-actions">
                    <button type="button" class="account-btn account-btn--outline" id="avatarReset">Redefinir</button>
                    <button type="button" class="account-btn account-btn--danger" id="avatarRemove" style="display:none;">Remover foto</button>
                    <button type="button" class="account-btn account-btn--secondary" id="avatarCancel">Cancelar</button>
                    <button type="button" class="account-btn account-btn--primary" id="avatarSave">
                        <i class="fas fa-save" aria-hidden="true"></i> Salvar foto
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cropper.js (local) -->
<script src="<?php echo asset_url('assets/vendor/cropperjs/cropper.min.js'); ?>"></script>

<script>
(function () {
    'use strict';

    // --- Máscara de CPF -------------------------------------------------
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

    // --- Toast Utility (FASE 4) ---------------------------------------------
    var toastContainer = null;

    function ensureToastContainer() {
        if (toastContainer) return toastContainer;
        toastContainer = document.createElement('div');
        toastContainer.className = 'account-toast-container';
        document.body.appendChild(toastContainer);
        return toastContainer;
    }

    function showToast(message, type) {
        var container = ensureToastContainer();
        var toast = document.createElement('div');
        toast.className = 'account-toast account-toast--' + (type || 'info');
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = '<span>' + message + '</span>';
        container.appendChild(toast);
        // Force reflow then show
        toast.offsetHeight;
        toast.classList.add('show');
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 400);
        }, 4000);
    }

    // --- Loading Button State (FASE 4) ------------------------------------
    function setButtonLoading(button, loading) {
        if (!button) return;
        if (loading) {
            button.classList.add('btn-loading');
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
        } else {
            button.classList.remove('btn-loading');
            button.disabled = false;
            if (button.dataset.originalText) {
                button.innerHTML = button.dataset.originalText;
                delete button.dataset.originalText;
            }
        }
    }

    // --- Form Dirty Tracking (FASE 4) -------------------------------------
    var formDirty = false;
    var originalFormValues = {};

    function captureFormState(form) {
        var inputs = form.querySelectorAll('input, select, textarea');
        originalFormValues = {};
        inputs.forEach(function (input) {
            if (input.name) originalFormValues[input.name] = input.value;
        });
    }

    function checkFormDirty(form) {
        var dirty = false;
        var inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(function (input) {
            if (input.name && originalFormValues[input.name] !== input.value) {
                dirty = true;
            }
        });
        formDirty = dirty;
        return dirty;
    }

    function markFieldDirty(field) {
        if (field && field.closest('.account-field')) {
            field.closest('.account-field').classList.add('account-field--dirty');
        }
    }

    // --- CEP Autocomplete on 8 digits (FASE 4) ----------------------------
    var cepInput   = document.getElementById('postal_code');
    var cepButton  = document.getElementById('cepLookup');
    var cepStatus  = document.getElementById('cepStatus');
    var cepDebounce = null;

    function setCepStatus(msg, type) {
        if (!cepStatus) return;
        cepStatus.textContent = msg || '';
        cepStatus.className = 'account-hint cep-' + (type || 'idle');
    }

    function lookupCep(cep) {
        var basePath = document.body.getAttribute('data-base-path') || '';
        var url = basePath + 'api/account/cep.php?cep=' + encodeURIComponent(cep);
        setCepStatus('Buscando endereço...', 'pending');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.data) {
                    setCepStatus((data && data.message) || 'CEP não encontrado. Preencha a rua manualmente.', 'error');
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
                        var f = document.getElementById(key);
                        if (f && map[key] !== '') f.value = map[key];
                    }
                }
                setCepStatus('Endereço encontrado.', 'ok');
            })
            .catch(function () {
                setCepStatus('Não foi possível consultar o CEP agora.', 'error');
            });
    }

    if (cepInput) {
        // Autocomplete on 8 digits (FASE 4c)
        cepInput.addEventListener('input', function () {
            var v = cepInput.value.replace(/\D/g, '');
            if (v.length === 8) {
                clearTimeout(cepDebounce);
                cepDebounce = setTimeout(function () { lookupCep(v); }, 300);
            }
        });
        // Also trigger on blur/change for backward compatibility
        cepInput.addEventListener('change', function () {
            var v = cepInput.value.replace(/\D/g, '');
            if (v.length === 8) lookupCep(v);
        });
        if (cepButton) {
            cepButton.addEventListener('click', function () {
                var v = cepInput.value.replace(/\D/g, '');
                if (v.length === 8) lookupCep(v);
            });
        }
    }

    // --- Unsaved Changes Warning (FASE 4d) --------------------------------
    var profileForm = document.getElementById('profile-form');
    if (profileForm) {
        captureFormState(profileForm);
        // Track changes
        profileForm.querySelectorAll('input, select').forEach(function (input) {
            input.addEventListener('change', function () {
                checkFormDirty(profileForm);
                markFieldDirty(input);
            });
            input.addEventListener('input', function () {
                if (input.type === 'checkbox' || input.type === 'radio') return;
                checkFormDirty(profileForm);
            });
        });
    }

    window.addEventListener('beforeunload', function (e) {
        if (formDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // --- Avatar Editor (Cropper.js) ---------------------------------------
    var avatarCropper = null;
    var avatarCropperModal = document.getElementById('modal-avatar');
    var avatarCropperImage = document.getElementById('avatarCropperImage');
    var avatarPreviewCircle = avatarCropperModal ? avatarCropperModal.querySelector('.avatar-editor-preview-circle') : null;

    // Abrir editor ao selecionar arquivo
    var avatarInput = document.getElementById('avatarInput');
    var avatarForm = document.getElementById('avatarForm');
    if (avatarInput && avatarForm && avatarCropperModal) {
        avatarInput.addEventListener('change', function () {
            var file = avatarInput.files[0];
            if (!file) return;

            // Valida tipo e tamanho (origem até 15 MB)
            var allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showToast('Formato não suportado. Use JPEG, PNG ou WebP.', 'error');
                avatarInput.value = '';
                return;
            }
            if (file.size > 15 * 1024 * 1024) {
                showToast('A imagem original excede 15 MB.', 'error');
                avatarInput.value = '';
                return;
            }

            // Cria URL temporária e abre modal
            var objectUrl = URL.createObjectURL(file);
            avatarCropperImage.src = objectUrl;
            avatarCropperImage.onload = function () {
                URL.revokeObjectURL(objectUrl);
            };

            // Inicializa Cropper.js
            if (avatarCropper) {
                avatarCropper.destroy();
            }
            avatarCropper = new Cropper(avatarCropperImage, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                cropBoxMovable: false,
                cropBoxResizable: false,
                guides: false,
                center: false,
                highlight: false,
                background: false,
                toggleDragModeOnDblclick: false,
                checkOrientation: true,
                ready: function () {
                    // Atualiza prévia mini
                    updateAvatarPreview();
                },
                cropmove: function () {
                    updateAvatarPreview();
                },
                zoom: function () {
                    updateAvatarPreview();
                },
            });

            // Atualiza botões de rotação
            document.getElementById('rotateLeft').onclick = function () { if (avatarCropper) avatarCropper.rotate(-90); };
            document.getElementById('rotateRight').onclick = function () { if (avatarCropper) avatarCropper.rotate(90); };
            document.getElementById('zoomIn').onclick = function () { if (avatarCropper) avatarCropper.zoom(0.1); };
            document.getElementById('zoomOut').onclick = function () { if (avatarCropper) avatarCropper.zoom(-0.1); };
            document.getElementById('zoomSlider').oninput = function () { if (avatarCropper) avatarCropper.zoomTo(parseFloat(this.value)); };
            document.getElementById('avatarReset').onclick = function () { if (avatarCropper) { avatarCropper.reset(); updateAvatarPreview(); } };
            document.getElementById('avatarRemove').onclick = function () { removeAvatar(); };
            document.getElementById('avatarCancel').onclick = function () { closeAvatarModal(); };
            document.getElementById('avatarSave').onclick = function () { saveAvatarCrop(); };

            // Slider de zoom
            var zoomSlider = document.getElementById('zoomSlider');
            zoomSlider.oninput = function () {
                if (avatarCropper) avatarCropper.zoomTo(parseFloat(this.value));
            };

            // Abre modal
            openAvatarModal();
        });
    }

    function updateAvatarPreview() {
        if (!avatarCropper || !avatarPreviewCircle) return;
        try {
            var canvas = avatarCropper.getCroppedCanvas({
                width: 80,
                height: 80,
                imageSmoothingQuality: 'high',
            });
            avatarPreviewCircle.style.backgroundImage = 'url(' + canvas.toDataURL('image/jpeg', 0.9) + ')';
        } catch (e) {
            // Ignora erros de canvas
        }
    }

    function openAvatarModal() {
        if (!avatarCropperModal) return;
        var lastFocused = document.activeElement;
        avatarCropperModal.hidden = false;
        document.body.classList.add('account-modal-open');
        var first = avatarCropperModal.querySelector('button, input, select');
        if (first) first.focus();
        // Focus trap
        avatarCropperModal._focusTrapHandler = function (e) {
            if (e.key === 'Tab') {
                var focusable = avatarCropperModal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                focusable = Array.from(focusable).filter(function (el) { return el.offsetWidth > 0 || el.offsetHeight > 0; });
                if (focusable.length === 0) return;
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            } else if (e.key === 'Escape') {
                closeAvatarModal();
            }
        };
        avatarCropperModal.addEventListener('keydown', avatarCropperModal._focusTrapHandler);
    }

    function closeAvatarModal() {
        if (!avatarCropperModal) return;
        if (avatarCropperModal._focusTrapHandler) {
            avatarCropperModal.removeEventListener('keydown', avatarCropperModal._focusTrapHandler);
        }
        if (avatarCropper) {
            avatarCropper.destroy();
            avatarCropper = null;
        }
        if (avatarCropperImage.src) {
            URL.revokeObjectURL(avatarCropperImage.src);
            avatarCropperImage.src = '';
        }
        avatarCropperModal.hidden = true;
        document.body.classList.remove('account-modal-open');
        var avatarInput = document.getElementById('avatarInput');
        if (avatarInput) avatarInput.value = '';
    }

    // Fechar ao clicar no backdrop ou botão fechar
    if (avatarCropperModal) {
        var backdrop = avatarCropperModal.querySelector('.account-modal-backdrop');
        var closeBtn = avatarCropperModal.querySelector('[data-avatar-close]');
        if (backdrop) backdrop.addEventListener('click', closeAvatarModal);
        if (closeBtn) closeBtn.addEventListener('click', closeAvatarModal);
    }

    // Salvar recorte
    function saveAvatarCrop() {
        if (!avatarCropper) return;
        var saveBtn = document.getElementById('avatarSave');
        setButtonLoading(saveBtn, true);

        try {
            var canvas = avatarCropper.getCroppedCanvas({
                width: 512,
                height: 512,
                imageSmoothingQuality: 'high',
            });
            canvas.toBlob(function (blob) {
                if (!blob) {
                    setButtonLoading(saveBtn, false);
                    showToast('Não foi possível gerar o recorte.', 'error');
                    return;
                }
                // Envia como base64
                var reader = new FileReader();
                reader.onload = function () {
                    var base64 = reader.result;
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({ action: 'avatar_crop', image: base64 })
                    }).then(function (r) { return r.json(); })
                    .then(function (data) {
                        setButtonLoading(saveBtn, false);
                        if (data.ok) {
                            showToast(data.message || 'Foto atualizada!', 'success');
                            // Atualiza todos os avatares na página com cache-busting
                            var newUrl = data.avatar_url;
                            document.querySelectorAll('.avatar, .account-avatar-img').forEach(function (img) {
                                if (img.tagName === 'IMG') {
                                    img.src = newUrl;
                                } else if (img.classList.contains('avatar-initials')) {
                                    // Substitui iniciais por imagem
                                    var parent = img.parentElement;
                                    var newImg = document.createElement('img');
                                    newImg.src = newUrl;
                                    newImg.alt = img.alt || 'Avatar';
                                    newImg.className = img.className.replace('avatar-initials', '');
                                    newImg.onerror = img.onerror;
                                    parent.replaceChild(newImg, img);
                                }
                            });
                            closeAvatarModal();
                        } else {
                            showToast(data.message || 'Erro ao salvar.', 'error');
                        }
                    }).catch(function () {
                        setButtonLoading(saveBtn, false);
                        showToast('Erro de conexão.', 'error');
                    });
                };
                reader.readAsDataURL(blob);
            }, 'image/jpeg', 0.9);
        } catch (e) {
            setButtonLoading(saveBtn, false);
            showToast('Erro ao gerar recorte.', 'error');
        }
    }

    function removeAvatar() {
        if (!confirm('Remover a foto de perfil?')) return;
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ action: 'avatar_remove' })
        }).then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                showToast(data.message, 'success');
                // Volta às iniciais
                document.querySelectorAll('.avatar, .account-avatar-img').forEach(function (img) {
                    if (img.tagName === 'IMG') {
                        var parent = img.parentElement;
                        var initials = img.dataset.initials || '??';
                        var newSpan = document.createElement('span');
                        newSpan.className = img.className.replace('account-avatar-img', 'account-avatar--initials');
                        newSpan.textContent = initials;
                        newSpan.dataset.initials = initials;
                        newSpan.setAttribute('role', 'img');
                        newSpan.setAttribute('aria-label', img.alt || 'Avatar');
                        parent.replaceChild(newSpan, img);
                    }
                });
                closeAvatarModal();
            } else {
                showToast(data.message || 'Erro ao remover.', 'error');
            }
        }).catch(function () { showToast('Erro de conexão.', 'error'); });
    }
    document.querySelectorAll('.js-toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input');
            if (!input) return;
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.setAttribute('aria-label', showing ? 'Mostrar nova senha' : 'Ocultar nova senha');
            var icon = btn.querySelector('i');
            if (icon) icon.className = showing ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    });

    // --- Modais (FASE 4f: Focus Trap) ---------------------------------------
    var lastFocused = null;
    var focusableSelectors = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

    function getFocusableElements(modal) {
        return Array.from(modal.querySelectorAll(focusableSelectors)).filter(function (el) {
            return el.offsetWidth > 0 || el.offsetHeight > 0;
        });
    }

    function handleTabKey(e, modal) {
        if (e.key !== 'Tab') return;
        var focusable = getFocusableElements(modal);
        if (focusable.length === 0) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        lastFocused = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('account-modal-open');
        var first = modal.querySelector('input, select, button');
        if (first) first.focus();
        // Add focus trap listener (FASE 4f)
        modal._focusTrapHandler = function (e) { handleTabKey(e, modal); };
        modal.addEventListener('keydown', modal._focusTrapHandler);
    }

    function closeModal(modal) {
        if (!modal) return;
        if (modal._focusTrapHandler) {
            modal.removeEventListener('keydown', modal._focusTrapHandler);
        }
        modal.hidden = true;
        document.body.classList.remove('account-modal-open');
        if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    }

    function handleTabKey(e, modal) {
        if (e.key !== 'Tab') return;
        var focusable = getFocusableElements(modal);
        if (focusable.length === 0) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            // "Adicionar" precisa começar com o modal em branco; um
            // "Editar" anterior poderia ter deixado campos preenchidos.
            if (btn.hasAttribute('data-address-reset')) resetAddressForm();
            openModal(btn.getAttribute('data-modal-open'));
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (el) {
        el.addEventListener('click', function () { closeModal(el.closest('.account-modal')); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.account-modal:not([hidden])').forEach(closeModal);
    });

    // --- Salvamento genérico (forms data-account-form) -------------------
    function wireForm(form) {
        var status     = form.querySelector('[data-save-status]');
        var endpoint   = form.getAttribute('data-endpoint') || form.action;
        var clearPass  = form.hasAttribute('data-clear-passwords');
        var isProfile  = form.hasAttribute('data-profile-form');
        var isNotif    = form.hasAttribute('data-notifications');
        var isModal    = form.hasAttribute('data-modal-form');

        function setSave(msg, type) {
            if (!status) return;
            status.textContent = msg || '';
            status.className = 'account-save-status' + (type ? ' account-save-status--' + type : '');
        }

        function markErrors(errors) {
            form.querySelectorAll('.account-field').forEach(function (field) {
                var input = field.querySelector('input, select');
                if (!input || !input.name) return;
                var has = Object.prototype.hasOwnProperty.call(errors || {}, input.name);
                field.classList.toggle('account-field--error', has);
                field.querySelectorAll('.account-hint--error').forEach(function (n) { n.remove(); });
                if (has) {
                    var err = document.createElement('span');
                    err.className = 'account-hint account-hint--error';
                    err.textContent = errors[input.name];
                    field.appendChild(err);
                }
            });
        }

        function flash(field) {
            if (!field) return;
            field.classList.remove('account-field--saved');
            void field.offsetWidth;
            field.classList.add('account-field--saved');
        }

        function applySaved(data) {
            if (!data) return;
            form.querySelectorAll('input, select').forEach(function (input) {
                if (!input.name || !Object.prototype.hasOwnProperty.call(data, input.name)) return;
                var val = data[input.name];

                if (input.type === 'checkbox') { input.checked = !!val; flash(input.closest('.account-field')); return; }

                if (isProfile) {
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
                flash(input.closest('.account-field'));
            });
        }

        if (!window.fetch) return;

        // Notificações: salvam ao alternar, sem botão.
        if (isNotif) {
            form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    setSave('Salvando...', 'pending');
                    fetch(endpoint, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            if (json.ok) { setSave('Preferências salvas.', 'ok'); applySaved(json.data); }
                            else { setSave(json.message || 'Não foi possível salvar.', 'error'); }
                        })
                        .catch(function () { setSave('Sem conexão com o servidor.', 'error'); });
                });
            });
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var button = form.querySelector('button[type="submit"]');
            setButtonLoading(button, true);
            setSave('Salvando...', 'pending');

            fetch(endpoint, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
                .then(function (res) {
                    if (res.json && res.json.ok) {
                        setSave('Salvo!', 'ok');
                        showToast('Salvo com sucesso!', 'success');
                        applySaved(res.json.data);
                        if (clearPass) form.querySelectorAll('input[type="password"]').forEach(function (p) { p.value = ''; });
                        if (isModal) {
                            form.reset();
                            var modal = form.closest('.account-modal');
                            setTimeout(function () { closeModal(modal); }, 600);
                        }
                        // Reset dirty state on successful save
                        if (form === profileForm) {
                            captureFormState(profileForm);
                            formDirty = false;
                        }
                    } else {
                        setSave((res.json && res.json.message) || 'Não foi possível salvar.', 'error');
                        showToast(res.json && res.json.message || 'Erro ao salvar', 'error');
                        markErrors(res.json && res.json.errors);
                    }
                })
                .catch(function () {
                    setSave('Sem conexão com o servidor agora. Tente de novo.', 'error');
                    showToast('Erro de conexão. Tente novamente.', 'error');
                })
                .then(function () { setButtonLoading(button, false); });
        });
    }

    document.querySelectorAll('form[data-account-form]').forEach(wireForm);

    // --- Endereços salvos -----------------------------------------------
    var ADDRESSES = <?php echo $addressesJson ?: '[]'; ?>;

    // O JSON é uma lista na ordem da API, mas os botões trazem o id.
    // indexedById permite preencher o modal sem request extra.
    var ADDRESS_BY_ID = ADDRESSES.reduce(function (map, a) {
        map[a.id] = a;
        return map;
    }, {});
    var addrForm  = document.querySelector('form[data-address-form]');
    var addrId    = addrForm ? addrForm.querySelector('[name="id"]') : null;
    var addrTitle = document.getElementById('modal-address-title');
    var addrUrl   = addrForm ? addrForm.getAttribute('data-endpoint') : '';
    // csrf_field() gera o campo "_csrf_token"; api_require_csrf() aceita
    // esse nome no corpo do POST ou o cabeçalho X-CSRF-Token.
    function csrfToken() {
        var f = document.querySelector('form[data-address-form] [name="_csrf_token"]')
             || document.querySelector('input[name="_csrf_token"]')
             || document.querySelector('meta[name="csrf-token"]');
        if (!f) return '';
        return f.tagName === 'META' ? (f.getAttribute('content') || '') : f.value;
    }

    function resetAddressForm() {
        if (!addrForm) return;
        addrForm.reset();
        if (addrId) addrId.value = '';
        var act = addrForm.querySelector('[name="action"]');
        if (act) act.value = 'create';
        if (addrTitle) addrTitle.textContent = 'Adicionar endereço';
        addrForm.querySelectorAll('.account-field--error').forEach(function (f) {
            f.classList.remove('account-field--error');
        });
        addrForm.querySelectorAll('.account-hint--error').forEach(function (n) { n.remove(); });
        var status = addrForm.querySelector('[data-save-status]');
        if (status) { status.textContent = ''; status.className = 'account-save-status'; }
    }

    function setAddrStatus(msg, type) {
        var status = document.querySelector('[data-address-status]');
        if (!status) return;
        status.textContent = msg || '';
        status.className = 'account-save-status' + (type ? ' account-save-status--' + type : '');
    }

    function callAddress(action, id, extra) {
        var body = new FormData();
        body.append('action', action);
        body.append('csrf_token', csrfToken());
        if (id) body.append('id', id);
        if (extra) {
            Object.keys(extra).forEach(function (k) { body.append(k, extra[k]); });
        }
        return fetch(addrUrl, {
            method: 'POST', body: body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken()
            }
        }).then(function (r) { return r.json(); });
    }

    // Depois de qualquer mutação a API devolve a lista atualizada; o
    // PRG é o mesmo caminho do fallback sem JS, então recarregamos.
    function afterAddressChange(json) {
        if (!json || !json.ok) return false;
        setAddrStatus('Salvo!', 'ok');
        setTimeout(function () { window.location.reload(); }, 550);
        return true;
    }

    function openAddressEditor(address) {
        if (!addrForm) return;
        if (addrId) addrId.value = address.id || '';
        var act = addrForm.querySelector('[name="action"]');
        if (act) act.value = 'update';
        if (addrTitle) addrTitle.textContent = 'Editar endereço';

        var set = {
            label: address.label, postal_code: address.postal_code,
            street: address.street, number: address.number,
            complement: address.complement, neighborhood: address.neighborhood,
            city: address.city, state: address.state
        };
        Object.keys(set).forEach(function (name) {
            var field = addrForm.querySelector('[name="' + name + '"]');
            if (field) field.value = set[name] == null ? '' : set[name];
        });
        openModal('modal-address');
    }

    // Botões da lista: editar (preenche o modal), tornar padrão, remover.
    document.querySelectorAll('[data-address-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-address-action');
            var id = btn.getAttribute('data-address-id');
            var data = ADDRESS_BY_ID[parseInt(id, 10)] || null;

            if (action === 'edit') {
                if (data) openAddressEditor(data);
                return;
            }

            if (action === 'delete' && !window.confirm('Remover este endereço?')) return;

            setButtonLoading(btn, true);
            setAddrStatus('Salvando...', 'pending');
            callAddress(action, id).then(function (json) {
                if (!afterAddressChange(json)) {
                    setButtonLoading(btn, false);
                    setAddrStatus((json && json.message) || 'Não foi possível salvar.', 'error');
                    showToast((json && json.message) || 'Erro ao salvar', 'error');
                } else {
                    showToast('Endereço salvo!', 'success');
                }
            }).catch(function () {
                setButtonLoading(btn, false);
                setAddrStatus('Sem conexão com o servidor.', 'error');
                showToast('Erro de conexão. Tente novamente.', 'error');
            });
        });
    });

    if (addrForm) {
        addrForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var button = addrForm.querySelector('button[type="submit"]');
            var status = addrForm.querySelector('[data-save-status]');

            function setSave(msg, type) {
                if (!status) return;
                status.textContent = msg || '';
                status.className = 'account-save-status' + (type ? ' account-save-status--' + type : '');
            }

            var action = (addrForm.querySelector('[name="action"]') || {}).value || 'create';
            var payload = {
                label: addrForm.querySelector('[name="label"]').value,
                postal_code: addrForm.querySelector('[name="postal_code"]').value,
                street: addrForm.querySelector('[name="street"]').value,
                number: addrForm.querySelector('[name="number"]').value,
                complement: addrForm.querySelector('[name="complement"]').value,
                neighborhood: addrForm.querySelector('[name="neighborhood"]').value,
                city: addrForm.querySelector('[name="city"]').value,
                state: addrForm.querySelector('[name="state"]').value
            };
            var id = addrId ? addrId.value : '';

            setButtonLoading(button, true);
            setSave('Salvando...', 'pending');

            callAddress(action, id, payload).then(function (json) {
                if (json && json.ok) {
                    setSave('Salvo!', 'ok');
                    showToast('Endereço salvo!', 'success');
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    setSave((json && json.message) || 'Não foi possível salvar.', 'error');
                    showToast(json && json.message || 'Erro ao salvar', 'error');
                    setButtonLoading(button, false);
                }
            }).catch(function () {
                setSave('Sem conexão com o servidor agora. Tente de novo.', 'error');
                showToast('Erro de conexão. Tente novamente.', 'error');
                setButtonLoading(button, false);
            });
        });
    }
})();
</script>

<?php account_layout_foot(); ?>
