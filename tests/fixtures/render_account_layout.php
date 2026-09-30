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
    fwrite(STDERR, "uso: render_account_layout.php <saida.html>\n");
    exit(2);
}

$outFile = $argv[1];

// Simula estar em pages/auth/, que é o caso real das telas da conta.
$_SERVER['SCRIPT_NAME']   = '/TCC_Etec/pages/auth/profile.php';
$_SERVER['REQUEST_URI']   = '/TCC_Etec/pages/auth/profile.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']     = 'localhost';

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

$page_title = 'Meu Perfil - Royal Tech';

ob_start();
account_layout_head($user, 'perfil');
?>
<article class="account-card"><h1 class="account-page-title">Meu Perfil</h1></article>
<?php
account_layout_foot();

file_put_contents($outFile, (string) ob_get_clean());
