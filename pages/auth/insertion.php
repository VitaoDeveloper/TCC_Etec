<?php
session_start();
require_once __DIR__ . '/../../includes/csrf.php';
include "../../database/connection.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

if (!csrf_verify($_POST['_csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Sessão expirada. Recarregue a página.');
}

$name = trim((string) filter_input(INPUT_POST, 'name'));
$email = trim((string) filter_input(INPUT_POST, 'email'));
$username = trim((string) filter_input(INPUT_POST, 'username'));
$password = (string) filter_input(INPUT_POST, 'password');
$postalCode = trim((string) filter_input(INPUT_POST, 'postalcode'));
$street = trim((string) filter_input(INPUT_POST, 'street'));
$number = (int) (filter_input(INPUT_POST, 'number') ?: 0);
$complement = trim((string) filter_input(INPUT_POST, 'complement')) ?: null;

$_SESSION['auth_old'] = [
    'name' => $name,
    'email' => $email,
    'username' => $username,
    'postalcode' => $postalCode,
    'street' => $street,
    'number' => $number > 0 ? (string) $number : '',
    'complement' => $complement ?? '',
];

$errors = [];
if (mb_strlen($name) < 3) $errors[] = 'Nome completo deve conter pelo menos 3 caracteres.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
if (!preg_match('/^[a-zA-Z0-9._-]{4,}$/', $username)) $errors[] = 'Nome de usuário deve ter ao menos 4 caracteres e conter apenas letras, números, ponto, traço ou underline.';
if (strlen($password) < 8) $errors[] = 'Senha deve ter no mínimo 8 caracteres.';
if (!preg_match('/^\d{5}-?\d{3}$/', $postalCode)) $errors[] = 'CEP inválido. Use o formato 00000-000.';
if (mb_strlen($street) < 4) $errors[] = 'Rua deve conter pelo menos 4 caracteres.';
if ($number <= 0) $errors[] = 'Número deve ser maior que zero.';

if (!empty($errors)) {
    $_SESSION['auth_errors'] = $errors;
    header('Location: register.php');
    exit;
}

$sql = 'INSERT INTO e5_users (name, email, username, password, postal_code, street, number, complement) VALUES (:name, :email, :username, :password, :postal_code, :street, :number, :complement)';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':username' => $username,
        ':password' => password_hash($password, PASSWORD_ARGON2ID),
        ':postal_code' => $postalCode,
        ':street' => $street,
        ':number' => $number,
        ':complement' => $complement,
    ]);

    unset($_SESSION['auth_old']);
    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = 'customer';
    $_SESSION['auth_success'] = 'Cadastro concluído com sucesso. Sua conta já está ativa.';

    header('Location: ../products/products.php');
    exit;
} catch (PDOException $e) {
    if ((int) $e->errorInfo[1] === 1062) {
        $_SESSION['auth_errors'] = ['E-mail ou nome de usuário já estão em uso.'];
    } else {
        error_log('User registration error: ' . $e->getMessage());
        $_SESSION['auth_errors'] = ['Não foi possível concluir o cadastro no momento. Tente novamente em instantes.'];
    }

    header('Location: register.php');
    exit;
}
