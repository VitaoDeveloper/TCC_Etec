<?php

declare(strict_types=1);

/**
 * API de cartões salvos (e5_saved_cards).
 *
 * GET  api/account/cards.php            -> lista ativos
 * POST api/account/cards.php action=delete -> inativa um cartão
 *
 * Nunca devolve número completo nem CVV: a tabela não os guarda de
 * propósito (só bandeira, titular, últimos 4, validade). "Deletar" é
 * inativar (is_active=0): o checkout só enxerga is_active=1, e um
 * cartão usado em pedido passado continua resolvível no histórico.
 */

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/api_response.php';
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
                exp_month, exp_year, max_installments, created_at
           FROM e5_saved_cards
          WHERE user_id = :uid AND is_active = 1
          ORDER BY id ASC'
    );
    $stmt->execute([':uid' => $userId]);

    api_ok(['cards' => $stmt->fetchAll()], '');
}

$action = (string) ($_POST['action'] ?? '');

if ($action !== 'delete') {
    api_error('Ação não reconhecida.', 400, ['action' => 'Valores válidos: delete.']);
}

$id = (int) ($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT id FROM e5_saved_cards WHERE id = :id AND user_id = :uid LIMIT 1');
$stmt->execute([':id' => $id, ':uid' => $userId]);

if (!$stmt->fetch()) {
    api_error('Cartão não encontrado.', 404);
}

$pdo->prepare('UPDATE e5_saved_cards SET is_active = 0 WHERE id = :id AND user_id = :uid')
    ->execute([':id' => $id, ':uid' => $userId]);

api_ok(null, 'Cartão removido.');