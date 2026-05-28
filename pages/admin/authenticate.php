<?php
session_start();
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
include '../../database/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!csrf_verify($_POST['_csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Sessão expirada. Recarregue a página.');
}

if (!rate_limit_check('admin_login_' . $_SERVER['REMOTE_ADDR'], 5, 15)) {
    $_SESSION['auth_error'] = 'Muitas tentativas de login. Aguarde 15 minutos.';
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
