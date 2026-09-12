<?php
// =============================================================================
// Endereços salvos do cliente (tabela e5_user_addresses).
// API: listar, buscar, validar, salvar (novo/editar), deletar e definir default.
// =============================================================================

function userAddressGetAll($pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM e5_user_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC');
    $stmt->execute([':u' => $userId]);
    return $stmt->fetchAll() ?: [];
}

function userAddressGetById($pdo, int $userId, int $addressId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM e5_user_addresses WHERE id = :id AND user_id = :u LIMIT 1');
    $stmt->execute([':id' => $addressId, ':u' => $userId]);
    return $stmt->fetch() ?: null;
}

// Resolve CEP de forma síncrona no servidor (ViaCEP). Retorna null quando falha.
function userAddressViaCep(string $cep): ?array
{
    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) !== 8) return null;
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
        $raw = @file_get_contents('https://viacep.com.br/ws/' . $cep . '/json/', false, $ctx);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        if (!is_array($data) || !empty($data['erro'])) return null;
        return [
            'street' => trim((string) ($data['logradouro'] ?? '')),
            'neighborhood' => trim((string) ($data['bairro'] ?? '')),
            'city' => trim((string) ($data['localidade'] ?? '')),
            'state' => trim((string) ($data['uf'] ?? '')),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

// Valida e normaliza os dados de endereço vindos de POST.
function userAddressValidate(array $in): array
{
    $label = trim((string) ($in['label'] ?? ''));
    if ($label === '') $label = 'Entrega';

    $cep = preg_replace('/\D/', '', (string) ($in['postal_code'] ?? ''));
    $street = trim((string) ($in['street'] ?? ''));
    $number = trim((string) ($in['number'] ?? ''));
    $complement = trim((string) ($in['complement'] ?? ''));
    $neighborhood = trim((string) ($in['neighborhood'] ?? ''));
    $city = trim((string) ($in['city'] ?? ''));
    $state = strtoupper(trim((string) ($in['state'] ?? '')));

    if (strlen($cep) !== 8) {
        return ['ok' => false, 'message' => 'CEP inválido. Informe 8 dígitos.'];
    }

    // Se faltar rua/cidade/UF, tenta completar via ViaCEP.
    if ($street === '' || $city === '' || $state === '') {
        $via = userAddressViaCep($cep);
        if ($via) {
            if ($street === '') $street = $via['street'];
            if ($neighborhood === '') $neighborhood = $via['neighborhood'];
            if ($city === '') $city = $via['city'];
            if ($state === '') $state = $via['state'];
        }
    }

    if ($street === '') {
        return ['ok' => false, 'message' => 'Informe a rua (ou um CEP válido para preencher automaticamente).'];
    }
    if ($number === '') {
        return ['ok' => false, 'message' => 'Informe o número do endereço (use "S/N" se não houver).'];
    }
    if ($city === '') {
        return ['ok' => false, 'message' => 'Informe a cidade (ou um CEP válido).'];
    }
    if (strlen($state) !== 2) {
        return ['ok' => false, 'message' => 'Informe a UF (ex.: SP).'];
    }
    if (mb_strlen($label) > 40) {
        return ['ok' => false, 'message' => 'O rótulo do endereço é muito longo (máx. 40 caracteres).'];
    }

    return [
        'ok' => true,
        'data' => [
            'label' => $label,
            'postal_code' => $cep,
            'street' => substr($street, 0, 120),
            'number' => substr($number, 0, 10),
            'complement' => $complement !== '' ? substr($complement, 0, 80) : null,
            'neighborhood' => $neighborhood !== '' ? substr($neighborhood, 0, 80) : null,
            'city' => substr($city, 0, 80),
            'state' => $state,
        ],
    ];
}

// Insere (addressId null) ou atualiza um endereço do cliente.
function userAddressSave($pdo, int $userId, array $data, ?int $addressId = null): array
{
    $validated = userAddressValidate($data);
    if (!$validated['ok']) return $validated;
    $d = $validated['data'];
    $isDefault = !empty($data['is_default']);

    try {
        if ($addressId === null) {
            $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM e5_user_addresses WHERE user_id = :u');
            $stmtCount->execute([':u' => $userId]);
            $count = (int) $stmtCount->fetchColumn();
            if ($count === 0) $isDefault = true;

            $stmt = $pdo->prepare(
                'INSERT INTO e5_user_addresses
                     (user_id, label, postal_code, street, number, complement, neighborhood, city, state, is_default)
                 VALUES (:u, :label, :cep, :street, :number, :complement, :neighborhood, :city, :state, :def)'
            );
        } else {
            if (!userAddressGetById($pdo, $userId, $addressId)) {
                return ['ok' => false, 'message' => 'Endereço não encontrado.'];
            }
            $stmt = $pdo->prepare(
                'UPDATE e5_user_addresses SET
                     label = :label, postal_code = :cep, street = :street, number = :number,
                     complement = :complement, neighborhood = :neighborhood, city = :city, state = :state, is_default = :def
                 WHERE id = :id AND user_id = :u'
            );
        }

        if ($isDefault) {
            $pdo->prepare('UPDATE e5_user_addresses SET is_default = 0 WHERE user_id = :u')->execute([':u' => $userId]);
        }

        $params = [
            ':u' => $userId,
            ':label' => $d['label'],
            ':cep' => $d['postal_code'],
            ':street' => $d['street'],
            ':number' => $d['number'],
            ':complement' => $d['complement'],
            ':neighborhood' => $d['neighborhood'],
            ':city' => $d['city'],
            ':state' => $d['state'],
            ':def' => $isDefault ? 1 : 0,
        ];
        if ($addressId !== null) {
            $params[':id'] = $addressId;
        }
        $stmt->execute($params);

        if ($addressId === null && $isDefault) {
            $addressId = (int) $pdo->lastInsertId();
        }

        return ['ok' => true, 'address_id' => $addressId ?? null, 'message' => 'Endereço salvo com sucesso.'];
    } catch (Throwable $e) {
        error_log('Address save error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Erro ao salvar o endereço.'];
    }
}

function userAddressDelete($pdo, int $userId, int $addressId): array
{
    $address = userAddressGetById($pdo, $userId, $addressId);
    if (!$address) return ['ok' => false, 'message' => 'Endereço não encontrado.'];

    $stmt = $pdo->prepare('DELETE FROM e5_user_addresses WHERE id = :id AND user_id = :u');
    $stmt->execute([':id' => $addressId, ':u' => $userId]);

    if ((int) $address['is_default'] === 1) {
        $next = $pdo->prepare('SELECT id FROM e5_user_addresses WHERE user_id = :u ORDER BY id ASC LIMIT 1');
        $next->execute([':u' => $userId]);
        $nextId = $next->fetchColumn();
        if ($nextId) {
            $pdo->prepare('UPDATE e5_user_addresses SET is_default = 1 WHERE id = :id')
                ->execute([':id' => (int) $nextId]);
        }
    }
    return ['ok' => true, 'message' => 'Endereço excluído.'];
}

function userAddressSetDefault($pdo, int $userId, int $addressId): array
{
    if (!userAddressGetById($pdo, $userId, $addressId)) {
        return ['ok' => false, 'message' => 'Endereço não encontrado.'];
    }
    $pdo->prepare('UPDATE e5_user_addresses SET is_default = 0 WHERE user_id = :u')->execute([':u' => $userId]);
    $pdo->prepare('UPDATE e5_user_addresses SET is_default = 1 WHERE id = :id AND user_id = :u')
        ->execute([':id' => $addressId, ':u' => $userId]);
    return ['ok' => true, 'message' => 'Endereço definido como padrão.'];
}