<?php
/**
 * Fixture de renderização do shell da conta.
 *
 * Roda em processo separado porque o shell emite <html> e depende de
 * header/footer legados, que leem variáveis de escopo de arquivo. Rodar
 * dentro do PHPUnit contaminaria a saída dos outros testes.
 *
 *   php tests/fixtures/render_account_layout.php <arquivo-de-saida>
 */

if ($argc < 2) {
    fwrite(STDERR, "uso: render_account_layout.php <saida.html> [admin|customer]\n");
    exit(2);
}

$outFile = $argv[1];
// Força o papel para conferir a pílula de admin sem depender do papel
// real do usuário 16 (que é admin no banco de desenvolvimento).
$forcedRole = in_array($argv[2] ?? '', ['admin', 'customer'], true) ? $argv[2] : null;

// Simula estar em pages/auth/, que é o caso real das telas da conta.
$_SERVER['SCRIPT_NAME']   = '/TCC_Etec/pages/auth/profile.php';
$_SERVER['REQUEST_URI']   = '/TCC_Etec/pages/auth/profile.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']     = 'localhost';

// NO CLI, atribuir $_SESSION antes de session_start() pode ser
// descartado: o PHP substitui o array pela sessão do disco. Por isso a
// sessão nasce primeiro e só depois ganha os valores. Sem isso o header
// renderizaria a loja como "logado de fora".
session_id('account-layout-fixture');
session_start();

$_SESSION['user_id']   = 16;
$_SESSION['user_role'] = 'customer';

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/account_layout.php';

$stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id');
$stmt->execute([':id' => 16]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    fwrite(STDERR, "usuario 16 nao encontrado\n");
    exit(3);
}

if ($forcedRole !== null) {
    $user['role'] = $forcedRole;
}

$page_title = 'Meu Perfil - Royal Tech';

ob_start();
account_layout_head($user, 'perfil');
?>
<article class="account-card"><h1 class="account-page-title">Meu Perfil</h1></article>
<?php
account_layout_foot();

file_put_contents($outFile, (string) ob_get_clean());
