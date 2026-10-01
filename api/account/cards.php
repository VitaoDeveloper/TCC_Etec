<?php

declare(strict_types=1);

/**
 * API de cartões salvos (e5_saved_cards).
 *
 * GET  api/account/cards.php                    -> lista ativos
 * POST api/account/cards.php action=create     -> adiciona cartão
 * POST api/account/cards.php action=delete     -> inativa um cartão
 * POST api/account/cards.php action=set_default -> define como padrão
 *
 * Nunca devolve número completo nem CVV: a tabela não os guarda de
 * propósito (só bandeira, titular, últimos 4, validade). "Deletar" é
 * inativar (is_active=0): o checkout só enxerga is_active=1, e um
 * cartão usado em pedido passado continua resolvível no histórico.
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT id, card_brand, holder_name, last_four,
                exp_month, exp_year, max_installments, is_default, created_at
           FROM e5_saved_cards
          WHERE user_id = :uid AND is_active = 1
          ORDER BY is_default DESC, id ASC'
    );
    $stmt->execute([':uid' => $userId]);

    api_ok(['cards' => $stmt->fetchAll()], '');
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'create') {
    // Validação dos campos
    $number = only_digits($_POST['number'] ?? '');
    $holder = clean_text($_POST['holder_name'] ?? '');
    $expMonth = (int) ($_POST['exp_month'] ?? 0);
    $expYear = (int) ($_POST['exp_year'] ?? 0);
    $maxInstallments = isset($_POST['max_installments']) ? max(1, min(12, (int) $_POST['max_installments'])) : 1;

    $errors = [];

    if (!is_valid_card_number($number)) {
        $errors['number'] = 'Número de cartão inválido (Luhn).';
    }
    if ($holder === '') {
        $errors['holder_name'] = 'Informe o nome do titular.';
    } elseif (mb_strlen($holder) > 100) {
        $errors['holder_name'] = 'Nome do titular muito longo.';
    }
    if (!is_valid_card_expiry(sprintf('%02d/%02d', $expMonth, $expYear % 100))) {
        $errors['exp'] = 'Validade inválida ou expirada.';
    }

    // Teto de cartões por cliente
    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM e5_saved_cards WHERE user_id = :uid AND is_active = 1');
    $stmtCount->execute([':uid' => $userId]);
    if ((int) $stmtCount->fetchColumn() >= 10) {
        $errors['number'] = 'Você já atingiu o limite de 10 cartões salvos.';
    }

    if ($errors !== []) {
        api_error('Dados inválidos.', 400, $errors);
    }

    $brand = detect_card_brand($number);
    $lastFour = card_last_four($number);

    // Se não há cartão padrão ainda, este vira o padrão
    $stmtDefault = $pdo->prepare('SELECT COUNT(*) FROM e5_saved_cards WHERE user_id = :uid AND is_active = 1');
    $stmtDefault->execute([':uid' => $userId]);
    $isDefault = (int) $stmtDefault->fetchColumn() === 0 ? 1 : 0;

    $pdo->prepare(
        'INSERT INTO e5_saved_cards
            (user_id, card_brand, holder_name, last_four, exp_month, exp_year, max_installments, is_default, created_at)
         VALUES (:uid, :brand, :holder, :last4, :expM, :expY, :maxInst, :isDefault, NOW())'
    )->execute([
        ':uid'        => $userId,
        ':brand'      => $brand,
        ':holder'     => $holder,
        ':last4'      => $lastFour,
        ':expM'       => $expMonth,
        ':expY'       => $expYear,
        ':maxInst'    => $maxInstallments,
        ':isDefault'  => $isDefault,
    ]);

    $newId = (int) $pdo->lastInsertId();

    api_ok(['id' => $newId, 'card_brand' => $brand, 'last_four' => $lastFour, 'is_default' => $isDefault], 'Cartão salvo.');
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT id FROM e5_saved_cards WHERE id = :id AND user_id = :uid LIMIT 1');
    $stmt->execute([':id' => $id, ':uid' => $userId]);

    if (!$stmt->fetch()) {
        api_error('Cartão não encontrado.', 404);
    }

    $pdo->prepare('UPDATE e5_saved_cards SET is_active = 0 WHERE id = :id AND user_id = :uid')
        ->execute([':id' => $id, ':uid' => $userId]);

    api_ok(null, 'Cartão removido.');
}

if ($action === 'set_default') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT id FROM e5_saved_cards WHERE id = :id AND user_id = :uid AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $id, ':uid' => $userId]);

    if (!$stmt->fetch()) {
        api_error('Cartão não encontrado.', 404);
    }

    $pdo->prepare('UPDATE e5_saved_cards SET is_default = 0 WHERE user_id = :uid')
        ->execute([':uid' => $userId]);
    $pdo->prepare('UPDATE e5_saved_cards SET is_default = 1 WHERE id = :id AND user_id = :uid')
        ->execute([':id' => $id, ':uid' => $userId]);

    api_ok(null, 'Cartão padrão atualizado.');
}

api_error('Ação não reconhecida.', 400, ['action' => 'Valores válidos: create, delete, set_default.']);