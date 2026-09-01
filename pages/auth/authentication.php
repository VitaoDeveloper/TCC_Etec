<?php
session_start();
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
include "../../database/connection.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

csrf_require_valid();

if (!rate_limit_check('login_' . $_SERVER['REMOTE_ADDR'], 5, 15)) {
    $_SESSION['auth_errors'] = ['Muitas tentativas de login. Aguarde 15 minutos.'];
    header('Location: login.php');
    exit;
}

$identifier = trim((string) filter_input(INPUT_POST, 'identifier'));
$password = (string) filter_input(INPUT_POST, 'password');
$next = trim((string) ($_POST['next'] ?? '../products/products.php'));

$errors = [];
if ($identifier === '') {
    $errors[] = 'Informe seu e-mail ou nome de usuário.';
}
if ($password === '') {
    $errors[] = 'Informe sua senha.';
}

if (!empty($errors)) {
    $_SESSION['auth_errors'] = $errors;
    $_SESSION['auth_old']['identifier'] = $identifier;
    header('Location: login.php');
    exit;
}

$identifierType = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
$sql = "SELECT id, name, {$identifierType}, password, role FROM e5_users WHERE {$identifierType} = :identifier LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':identifier' => $identifier]);
$user = $stmt->fetch();

if (!$user) {
    // Mensagem genérica — evita enumerar e-mails/usuários cadastrados
    $_SESSION['auth_errors'] = ['Credenciais inválidas. Verifique e tente novamente.'];
    $_SESSION['auth_old']['identifier'] = $identifier;
    header('Location: login.php');
    exit;
}

if (!password_verify($password, $user['password'])) {
    $_SESSION['auth_errors'] = ['Credenciais inválidas. Verifique e tente novamente.'];
    $_SESSION['auth_old']['identifier'] = $identifier;
    header('Location: login.php');
    exit;
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'] ?? 'customer';
$_SESSION['auth_success'] = 'Login realizado com sucesso. Bem-vindo(a)!';

// Merge do carrinho guest → user
if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
    require_once __DIR__ . '/../../includes/cart_functions.php';
    sessionCartMergeToUser($pdo, $_SESSION['user_id']);
}

if ($_SESSION['user_role'] === 'admin') {
    header('Location: ../admin/index.php');
    exit;
}

// Open redirect fix: aceitar apenas paths sem scheme, host ou CRLF
if ($next !== '') {
    $uri = parse_url($next);
    if (!isset($uri['scheme']) && !isset($uri['host'])
        && strpos($next, "\r") === false && strpos($next, "\n") === false) {
        header('Location: ' . $next);
        exit;
    }
}

header('Location: ../products/products.php');
exit;
