<?php

declare(strict_types=1);

/**
 * API do perfil da conta.
 *
 * GET  api/account/profile.php           -> dados atuais (sem senha)
 * POST api/account/profile.php           -> action=personal  atualiza dados
 * POST api/account/profile.php (multipart)-> action=avatar    troca a foto
 *
 * Espelha as regras do form server-side de pages/auth/profile.php; a
 * tela passou a chamar aqui por fetch() e o fallback no-POST continua
 * lá. Nunca devolve a coluna password.
 */

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/api_response.php';
require_once __DIR__ . '/../../includes/validators.php';
require_once __DIR__ . '/../../includes/url_helpers.php';
require_once __DIR__ . '/../../database/connection.php';

api_require_method('GET', 'POST');
api_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_require_csrf();
}

$userId = (int) $_SESSION['user_id'];

function api_profile_fetch_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, username, email, cpf, role, phone,
                postal_code, street, number, complement, neighborhood,
                city, state, avatar_path, notify_email, notify_whatsapp,
                created_at, updated_at
           FROM e5_users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    return $user === false ? null : $user;
}

function api_profile_avatar_path(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare('SELECT avatar_path FROM e5_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);

    return ($v = $stmt->fetchColumn()) === false ? null : (string) $v;
}

// ---------------------------------------------------------------------
//  GET — estado atual (o fetch() carrega a tela com ele)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = api_profile_fetch_user($pdo, $userId);

    if ($user === null) {
        api_error('Usuário não encontrado.', 404);
    }

    api_ok($user, '');
}

// ---------------------------------------------------------------------
//  POST — avatar (multipart; o FormData do fetch cai aqui)
// ---------------------------------------------------------------------
$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'avatar') {
    if (empty($_FILES['avatar']['name'])) {
        api_error('Escolha uma imagem antes de enviar.', 400, ['avatar' => 'Nenhuma imagem enviada.']);
    }

    $file = $_FILES['avatar'];
    $errorCode = (int) $file['error'];

    if ($errorCode !== UPLOAD_ERR_OK) {
        $msg = match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem excede o tamanho máximo de 2 MB.',
            UPLOAD_ERR_NO_FILE => 'Nenhuma imagem enviada.',
            UPLOAD_ERR_PARTIAL => 'O upload foi interrompido. Tente novamente.',
            default => 'Não foi possível enviar a imagem.',
        };
        api_error($msg, 400, ['avatar' => $msg]);
    }

    $tmpName = (string) $file['tmp_name'];
    $size    = (int) $file['size'];

    if ($size > 2 * 1024 * 1024) {
        api_error('A imagem excede o tamanho máximo de 2 MB.', 400, ['avatar' => 'A imagem excede 2 MB.']);
    }

    $info = @getimagesize($tmpName);
    $mime = $info['mime'] ?? '';
    $supported = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($supported[$mime])) {
        api_error('Formato não aceito. Use JPG, PNG ou WebP.', 400, ['avatar' => 'Formato não aceito.']);
    }

    $dir = dirname(__DIR__, 2) . '/assets/uploads/avatars';

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        api_error('Não foi possível gravar a imagem no servidor.', 500);
    }

    if (!is_writable($dir)) {
        api_error('A pasta de avatares não tem permissão de escrita.', 500);
    }

    $current = (string) (api_profile_avatar_path($pdo, $userId) ?? '');
    $filename = 'u' . $userId . '-' . bin2hex(random_bytes(6)) . '.' . $supported[$mime];

    if (!move_uploaded_file($tmpName, $dir . '/' . $filename)) {
        api_error('Não foi possível salvar a imagem.', 500);
    }

    // Limpa o avatar antigo se ele era upload nosso (nunca placeholder do tema).
    if ($current !== '' && str_contains($current, 'uploads/avatars/')) {
        $oldAbs = dirname(__DIR__, 2) . '/' . ltrim($current, '/');
        if (is_file($oldAbs)) {
            @unlink($oldAbs);
        }
    }

    $relative = 'uploads/avatars/' . $filename;
    $pdo->prepare('UPDATE e5_users SET avatar_path = :path WHERE id = :id')
        ->execute([':path' => $relative, ':id' => $userId]);

    api_ok(['avatar_path' => $relative, 'avatar_url' => asset_url($relative)], 'Foto de perfil atualizada!');
}

// ---------------------------------------------------------------------
//  POST — dados pessoais (mesmas regras do form server-side)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'personal') {
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

    if ($input['name'] === '') {
        $errors['name'] = 'Informe seu nome.';
    } elseif (mb_strlen($input['name']) > 80) {
        $errors['name'] = 'O nome deve ter no máximo 80 caracteres.';
    }

    if (!is_valid_email($input['email'])) {
        $errors['email'] = 'E-mail inválido.';
    }

    if (!is_valid_username($input['username'])) {
        $errors['username'] = 'Usuário inválido: use letras, números, ponto, hífen ou sublinhado, com 3 a 20 caracteres.';
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

    if ($errors !== []) {
        api_error(reset($errors), 400, $errors);
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM e5_users WHERE (email = :email OR username = :username) AND id != :id LIMIT 1'
    );
    $stmt->execute([
        ':email'    => $input['email'],
        ':username' => $input['username'],
        ':id'       => $userId,
    ]);

    if ($stmt->fetch()) {
        api_error('E-mail ou usuário já em uso por outra conta.', 409, ['email' => 'Já em uso.']);
    }

    $pdo->prepare(
        'UPDATE e5_users SET
            name = :name, email = :email, username = :username, cpf = :cpf,
            phone = :phone, postal_code = :postal_code, street = :street,
            number = :number, complement = :complement,
            neighborhood = :neighborhood, city = :city, state = :state,
            updated_at = NOW()
         WHERE id = :id'
    )->execute([
        ':name'         => $input['name'],
        ':email'        => $input['email'],
        ':username'     => $input['username'],
        ':cpf'          => $input['cpf'],
        ':phone'        => $input['phone'] !== '' ? $input['phone'] : null,
        ':postal_code'  => $input['postal_code'],
        ':street'       => $input['street'],
        ':number'       => $input['number'] !== '' ? $input['number'] : null,
        ':complement'   => $input['complement'] !== '' ? $input['complement'] : null,
        ':neighborhood' => $input['neighborhood'] !== '' ? $input['neighborhood'] : null,
        ':city'         => $input['city'] !== '' ? $input['city'] : null,
        ':state'        => $input['state'] !== '' ? $input['state'] : null,
        ':id'           => $userId,
    ]);

    $user = api_profile_fetch_user($pdo, $userId);

    api_ok($user, 'Seus dados foram atualizados.');
}

// Qualquer POST sem action conhecida.
api_error('Ação não reconhecida.', 400, ['action' => 'Valores válidos: personal, avatar.']);