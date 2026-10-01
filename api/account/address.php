<?php

declare(strict_types=1);

/**
 * API do caderno de endereços (e5_addresses).
 *
 * GET  api/account/address.php                    -> lista (id DESC)
 * POST api/account/address.php action=create      -> cria
 * POST api/account/address.php action=update      -> altera (próprio)
 * POST api/account/address.php action=delete      -> remove (próprio)
 * POST api/account/address.php action=set_default -> marca padrão
 *
 * Limites garantidos pelo banco (UNIQUE user_id+default_flag) e aqui:
 * no máximo 10 endereços por usuário e o primeiro da lista é o padrão
 * quando ainda não há nenhum. O usuário só mexe nos seus próprios
 * registros — checagem de dono em toda mutação.
 */

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/api_response.php';
require_once __DIR__ . '/../../includes/validators.php';
require_once __DIR__ . '/../../database/connection.php';

api_require_method('GET', 'POST');
api_require_login();

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_require_csrf();
}

function api_address_validate(array $in): array
{
    $errors = [];

    $label = clean_text($in['label'] ?? '');
    if ($label === '') {
        $errors['label'] = 'Dê um nome a este endereço.';
    } elseif (mb_strlen($label) > 40) {
        $errors['label'] = 'O rótulo deve ter no máximo 40 caracteres.';
    }

    $cep = only_digits($in['postal_code'] ?? '');
    if (!is_valid_cep($cep)) {
        $errors['postal_code'] = 'CEP inválido.';
    }

    if (clean_text($in['street'] ?? '') === '') {
        $errors['street'] = 'Informe a rua.';
    } elseif (mb_strlen((string) $in['street']) > 120) {
        $errors['street'] = 'A rua deve ter no máximo 120 caracteres.';
    }

    $number = clean_text($in['number'] ?? '');
    if ($number === '') {
        $errors['number'] = 'Informe o número.';
    } elseif (!ctype_digit($number)) {
        $errors['number'] = 'Número inválido: use apenas dígitos.';
    } elseif (mb_strlen($number) > 10) {
        $errors['number'] = 'O número deve ter no máximo 10 caracteres.';
    }

    if ((string) $in['complement'] !== '' && mb_strlen((string) $in['complement']) > 80) {
        $errors['complement'] = 'O complemento deve ter no máximo 80 caracteres.';
    }

    if ((string) $in['neighborhood'] !== '' && mb_strlen((string) $in['neighborhood']) > 80) {
        $errors['neighborhood'] = 'O bairro deve ter no máximo 80 caracteres.';
    }

    if (clean_text($in['city'] ?? '') === '') {
        $errors['city'] = 'Informe a cidade.';
    } elseif (mb_strlen((string) $in['city']) > 80) {
        $errors['city'] = 'A cidade deve ter no máximo 80 caracteres.';
    }

    $state = strtoupper(clean_text($in['state'] ?? ''));
    if (!is_valid_uf($state)) {
        $errors['state'] = 'UF inválida.';
    }

    $normalized = [
        'label'        => $label,
        'postal_code'  => format_cep($cep),
        'street'       => clean_text($in['street'] ?? ''),
        'number'       => clean_text($in['number'] ?? ''),
        'complement'   => clean_text($in['complement'] ?? ''),
        'neighborhood' => clean_text($in['neighborhood'] ?? ''),
        'city'         => clean_text($in['city'] ?? ''),
        'state'        => $state,
    ];

    return ['errors' => $errors, 'data' => $normalized];
}

function api_address_owned(?array $address, int $userId): bool
{
    return $address !== null && (int) $address['user_id'] === $userId;
}

/** Lista os endereços do usuário e o id do padrão (ou null). */
function api_address_list(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, label, postal_code, street, number, complement,
                neighborhood, city, state, is_default, created_at
           FROM e5_addresses WHERE user_id = :uid ORDER BY is_default DESC, id DESC'
    );
    $stmt->execute([':uid' => $userId]);

    $rows      = $stmt->fetchAll();
    $defaultId = null;
    foreach ($rows as $row) {
        if ((int) $row['is_default'] === 1) {
            $defaultId = (int) $row['id'];
            break;
        }
    }

    return ['addresses' => $rows, 'default_id' => $defaultId];
}

// ---------------------------------------------------------------------
//  GET
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    api_ok(api_address_list($pdo, $userId), '');
}

$action = (string) ($_POST['action'] ?? '');
$body   = api_body();

$allowed = ['create', 'update', 'delete', 'set_default'];
if (!in_array($action, $allowed, true)) {
    api_error('Ação não reconhecida.', 400, ['action' => 'Valores válidos: ' . implode(', ', $allowed) . '.']);
}

// ---------------------------------------------------------------------
//  Criar
// ---------------------------------------------------------------------
if ($action === 'create') {
    $countSt = $pdo->prepare('SELECT COUNT(*) FROM e5_addresses WHERE user_id = :uid');
    $countSt->execute([':uid' => $userId]);

    if ((int) $countSt->fetchColumn() >= 10) {
        api_error('Você atingiu o limite de 10 endereços salvos.', 400, ['label' => 'Limite de 10 endereços.']);
    }

    $validated = api_address_validate($body);

    if ($validated['errors'] !== []) {
        api_error(reset($validated['errors']), 400, $validated['errors']);
    }

    $d = $validated['data'];

    $st = $pdo->prepare('SELECT COUNT(*) FROM e5_addresses WHERE user_id = :uid AND is_default = 1');
    $st->execute([':uid' => $userId]);
    $hasDefault = (int) $st->fetchColumn() > 0;

    $stmt = $pdo->prepare(
        'INSERT INTO e5_addresses
            (user_id, label, postal_code, street, number, complement,
             neighborhood, city, state, is_default)
         VALUES
            (:uid, :label, :cep, :street, :number, :complement,
             :neighborhood, :city, :state, :is_default)'
    );
    $stmt->execute([
        ':uid'          => $userId,
        ':label'        => $d['label'],
        ':cep'          => $d['postal_code'],
        ':street'       => $d['street'],
        ':number'       => $d['number'],
        ':complement'   => $d['complement'] !== '' ? $d['complement'] : null,
        ':neighborhood' => $d['neighborhood'] !== '' ? $d['neighborhood'] : null,
        ':city'         => $d['city'],
        ':state'        => $d['state'],
        ':is_default'   => $hasDefault ? 0 : 1,
    ]);

    api_created(api_address_list($pdo, $userId), 'Endereço salvo.');
}

// ---------------------------------------------------------------------
//  Atualizar
// ---------------------------------------------------------------------
if ($action === 'update') {
    $id = (int) ($body['id'] ?? 0);

    $st = $pdo->prepare('SELECT * FROM e5_addresses WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $address = $st->fetch();

    if (!$address) {
        api_error('Endereço não encontrado.', 404);
    }

    if (!api_address_owned($address, $userId)) {
        api_error('Acesso negado.', 403);
    }

    $validated = api_address_validate($body);

    if ($validated['errors'] !== []) {
        api_error(reset($validated['errors']), 400, $validated['errors']);
    }

    $d = $validated['data'];

    $pdo->prepare(
        'UPDATE e5_addresses SET
            label = :label, postal_code = :cep, street = :street,
            number = :number, complement = :complement,
            neighborhood = :neighborhood, city = :city, state = :state,
            updated_at = NOW()
         WHERE id = :id AND user_id = :uid'
    )->execute([
        ':label'        => $d['label'],
        ':cep'          => $d['postal_code'],
        ':street'       => $d['street'],
        ':number'       => $d['number'],
        ':complement'   => $d['complement'] !== '' ? $d['complement'] : null,
        ':neighborhood' => $d['neighborhood'] !== '' ? $d['neighborhood'] : null,
        ':city'         => $d['city'],
        ':state'        => $d['state'],
        ':id'           => $id,
        ':uid'          => $userId,
    ]);

    api_ok(api_address_list($pdo, $userId), 'Endereço atualizado.');
}

// ---------------------------------------------------------------------
//  Remover
// ---------------------------------------------------------------------
if ($action === 'delete') {
    $id = (int) ($body['id'] ?? 0);

    $st = $pdo->prepare('SELECT * FROM e5_addresses WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $address = $st->fetch();

    if (!$address) {
        api_error('Endereço não encontrado.', 404);
    }

    if (!api_address_owned($address, $userId)) {
        api_error('Acesso negado.', 403);
    }

    $wasDefault = (int) $address['is_default'] === 1;

    $pdo->prepare('DELETE FROM e5_addresses WHERE id = :id AND user_id = :uid')
        ->execute([':id' => $id, ':uid' => $userId]);

    // Se o padrão sumiu e ainda há endereços, o mais novo assume como
    // padrão — a tela nunca fica sem um.
    if ($wasDefault) {
        $pdo->prepare(
            'UPDATE e5_addresses SET is_default = 1 WHERE id = (
                 SELECT id FROM (
                     SELECT id FROM e5_addresses WHERE user_id = :uid ORDER BY id DESC LIMIT 1
                 ) t
             )'
        )->execute([':uid' => $userId]);
    }

    api_ok(api_address_list($pdo, $userId), 'Endereço removido.');
}

// ---------------------------------------------------------------------
//  Marcar como padrão
// ---------------------------------------------------------------------
if ($action === 'set_default') {
    $id = (int) ($body['id'] ?? 0);

    $st = $pdo->prepare('SELECT * FROM e5_addresses WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $address = $st->fetch();

    if (!$address) {
        api_error('Endereço não encontrado.', 404);
    }

    if (!api_address_owned($address, $userId)) {
        api_error('Acesso negado.', 403);
    }

    // O UNIQUE(user_id, default_flag) do banco garante a exclusividade;
    // aqui zeramos o resto numa transação para não chegar com dois 1.
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE e5_addresses SET is_default = 0 WHERE user_id = :uid')->execute([':uid' => $userId]);
        $pdo->prepare('UPDATE e5_addresses SET is_default = 1 WHERE id = :id AND user_id = :uid')
            ->execute([':id' => $id, ':uid' => $userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    api_ok(api_address_list($pdo, $userId), 'Endereço padrão atualizado.');
}