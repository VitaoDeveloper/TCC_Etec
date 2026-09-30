<?php

declare(strict_types=1);

/**
 * API de troca de senha.
 *
 * POST api/account/password.php
 *   current_password, new_password, confirm_password
 *
 * Mesmas regras do form server-side de pages/auth/profile.php mais o
 * rate limit: a senha é o único dado que, errado, vale para tentar em
 * todas as contas. Nó ainda guarda o histórico da senha antiga.
 */

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/api_response.php';
require_once __DIR__ . '/../../database/connection.php';

api_require_method('POST');
api_require_csrf();
$userId = api_require_login();

// Janela curta e tolerância baixa: não há motivo para um usuário
// legítimo errar a senha atual mais de ~5 vezes em 10 minutos.
api_rate_limit('password_change', 5, 10);

$current      = (string) ($_POST['current_password'] ?? '');
$newPassword  = (string) ($_POST['new_password'] ?? '');
$confirmation = (string) ($_POST['confirm_password'] ?? '');

$stmt = $pdo->prepare('SELECT password FROM e5_users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$hash = $stmt->fetchColumn();

$errors = [];

if (!is_string($hash) || $hash === '' || !password_verify($current, $hash)) {
    $errors['current_password'] = 'A senha atual está incorreta.';
}

if (mb_strlen($newPassword) < 6) {
    $errors['new_password'] = 'A nova senha precisa de no mínimo 6 caracteres.';
} elseif (strlen($newPassword) > 72) {
    $errors['new_password'] = 'A nova senha pode ter no máximo 72 caracteres.';
} elseif ($newPassword !== $confirmation) {
    $errors['confirm_password'] = 'A confirmação não confere com a nova senha.';
}

if ($errors !== []) {
    api_error(reset($errors), 400, $errors);
}

$pdo->prepare('UPDATE e5_users SET password = :hash, updated_at = NOW() WHERE id = :id')
    ->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);

api_ok(null, 'Senha alterada com sucesso.');