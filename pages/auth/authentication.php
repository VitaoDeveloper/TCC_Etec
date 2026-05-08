<?php
session_start();
include "../../database/connection.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$identifier = trim((string) filter_input(INPUT_POST, 'identifier'));
$password = (string) filter_input(INPUT_POST, 'password');

if ($identifier === '' || $password === '') {
    $_SESSION['auth_error'] = 'Informe usuário/e-mail e senha.';
    header('Location: login.php');
    exit;
}

$identifierType = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
$sql = "SELECT id, name, email, username, password, role FROM users WHERE {$identifierType} = :identifier LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':identifier' => $identifier]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['auth_error'] = 'Credenciais inválidas.';
    header('Location: login.php');
    exit;
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'] ?? 'customer';

if ($_SESSION['user_role'] === 'admin') {
    header('Location: ../admin/index.php');
    exit;
}

$next = $_POST['next'] ?? '../products/products.php';
header('Location: ' . $next);
header('Location: ../products/products.php');
exit;
?>
