<?php
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
$next = safe_local_path($_POST['next'] ?? null) ?? '../products/products.php';

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

// A coluna nunca vem do input: e escolhida aqui e usada em duas queries
// fixas. Evita concatenar identificador em SQL.
$byEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
$sql = $byEmail
    ? 'SELECT id, name, email, password, role FROM e5_users WHERE email = :identifier LIMIT 1'
    : 'SELECT id, name, username, password, role FROM e5_users WHERE username = :identifier LIMIT 1';

$stmt = $pdo->prepare($sql);
$stmt->execute([':identifier' => $identifier]);
$user = $stmt->fetch();

// Mensagem unica para "conta inexistente" e "senha errada": responder
// diferente permitia enumerar quais e-mails/usernames estao cadastrados.
// O custo e deUX (a dica de "e-mail nao encontrado" some), a beneficio e
// nao entregar a um atacante a lista de clientes da loja.
$invalidCredentials = static function () use ($identifier): void {
    $_SESSION['auth_errors'] = ['E-mail/usuário ou senha incorretos.'];
    $_SESSION['auth_old']['identifier'] = $identifier;
    header('Location: login.php');
    exit;
};

if (!$user) {
    // Hash descartavel: mantem o tempo de resposta parecido com o de uma
    // conta existente, para nao vazar a informacao pelo tempo gasto.
    password_verify($password, '$2y$10$usesomesillystringforsaltxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
    $invalidCredentials();
}

if (!password_verify($password, $user['password'])) {
    $invalidCredentials();
}

// Sessao regenerada no login. Sem isso, um atacante que consiga plantar um
// PHPSESSID conhecido na maquina da vitima (session fixation, via URL ou
// cookie de terceiro em subdominio) continua com a sessao valida depois
// que a pessoa faz login.
session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'] ?? 'customer';
$_SESSION['auth_success'] = 'Login realizado com sucesso. Bem-vindo(a)!';

if ($_SESSION['user_role'] === 'admin') {
    header('Location: ../admin/index.php');
    exit;
}

// $next ja passou por safe_local_path(): ou e um caminho do proprio app
// ou o valor padrao. Antes, a checagem estava invertida —
// "if (!str_starts_with($next, '/')) redirect($next)" — ou seja, o
// redirecionamento para fora do dominio acontecia justamente no caso que
// a condicao dizia proteger. Bastava mandar next=https://site-do-atacante
// para o login terminar num phishing.
header('Location: ' . $next);
exit;
