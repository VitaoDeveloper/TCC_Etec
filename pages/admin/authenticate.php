<?php
session_start();
include '../../database/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = trim((string) filter_input(INPUT_POST, 'email'));
$password = (string) filter_input(INPUT_POST, 'password');

$sql = 'SELECT id, name, password FROM e5_users WHERE email = :email AND role = :role LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute([':email' => $email, ':role' => 'admin']);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password'])) {
    $_SESSION['auth_error'] = 'Credenciais administrativas inválidas.';
    header('Location: login.php');
    exit;
}

$_SESSION['user_id'] = (int) $admin['id'];
$_SESSION['user_name'] = $admin['name'];
$_SESSION['user_role'] = 'admin';

header('Location: index.php');
exit;
?>
