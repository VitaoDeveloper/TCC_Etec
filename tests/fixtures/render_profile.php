<?php
/**
 * Fixture de renderização da página de perfil da conta.
 *
 * Roda em processo separado porque a página emite <html> e depende de
 * header/footer legados, que leem variáveis de escopo de arquivo. Rodar
 * dentro do PHPUnit contaminaria a saída dos outros testes.
 *
 * Também cobre os caminhos de POST que a página trata sozinha (avatar,
 * personal, address, notifications, password). No CLI um POST é
 * representado por um array $_POST/$_FILES criado antes do include.
 *
 *   php tests/fixtures/render_profile.php <acao|render> <arquivo-de-saida>
 *
 *   render    -> só renderiza a página (GET)
 *   avatar    -> envia avatar fake
 *   personal  -> submete dados pessoais
 *   password  -> altera a senha
 */

if ($argc < 3) {
    fwrite(STDERR, "uso: render_profile.php <render|avatar|personal|address|password> <saida>\n");
    exit(2);
}

$mode    = $argv[1];
$outFile = $argv[2];

// Simula estar em pages/auth/, que é o caso real da tela.
$_SERVER['SCRIPT_NAME']   = '/TCC_Etec/pages/auth/profile.php';
$_SERVER['REQUEST_URI']   = '/TCC_Etec/pages/auth/profile.php';
$_SERVER['HTTP_HOST']     = 'localhost';

// NO CLI, atribuir $_SESSION antes de session_start() pode ser
// descartado: o PHP substitui o array pela sessão do disco. Por isso a
// sessão nasce primeiro e só depois ganha os valores. Sem isso a página
// redirecionaria quem não estiver "logado".
session_id('account-profile-fixture');
session_start();

$_SESSION['user_id']   = 16;
$_SESSION['user_role'] = 'customer';

require_once __DIR__ . '/../../database/connection.php';

// --- simula um POST específico, se pedido ---------------------------
if ($mode === 'personal') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'action'      => 'personal',
        '_csrf_token' => csrf_token(),
        'name'        => 'Kauã Caetano',
        'username'    => 'kaua',
        'email'       => 'kaua@etec.com',
        'cpf'         => '712.425.960-60',
        'phone'       => '(12) 97814-9392',
        'postal_code' => '12230-201',
        'street'      => 'Av. São João',
        'number'      => 1280,
        'complement'  => '',
        'neighborhood'=> 'Centro',
        'city'        => 'São José dos Campos',
        'state'       => 'SP',
    ];
} elseif ($mode === 'address') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'action'      => 'address',
        '_csrf_token' => csrf_token(),
        'postal_code' => '12230-201',
        'street'      => 'Av. São João',
        'number'      => 1280,
        'complement'  => '',
        'neighborhood'=> 'Centro',
        'city'        => 'São José dos Campos',
        'state'       => 'SP',
    ];
} elseif ($mode === 'password') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'action'       => 'password',
        '_csrf_token'  => csrf_token(),
        'current_password' => 'Senha@123',
        'new_password'     => 'Senha@456',
        'new_password_confirm' => 'Senha@456',
    ];
} elseif ($mode === 'avatar') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['action' => 'avatar', '_csrf_token' => csrf_token()];
    $_FILES['avatar'] = [
        'name'     => 'avatar.png',
        'type'     => 'image/png',
        'tmp_name' => tempnam(sys_get_temp_dir(), 'tcc_avatar_'),
        'error'    => UPLOAD_ERR_NO_FILE,
        'size'     => 0,
    ];
} else {
    $_SERVER['REQUEST_METHOD'] = 'GET';
}

// Modo "addresses": semeia endereços do usuário 16 para exercitar o
// estado preenchido da lista (a API devolve o mesmo formato). Usa o
// mesmo formato de linha que a página lê, e limpa tudo no fim para o
// banco de dev voltar ao estado original.
$seededAddressIds = [];

if ($mode === 'addresses') {
    $seededAddressIds = [900001, 900002];
    $pdo->prepare('DELETE FROM e5_addresses WHERE id IN (900001, 900002)')->execute();

    $seed = $pdo->prepare(
        'INSERT INTO e5_addresses
            (id, user_id, label, postal_code, street, number, complement,
             neighborhood, city, state, is_default)
         VALUES
            (900001, 16, :l1, :c1, :s1, :n1, NULL, :b1, :city1, :st1, 0),
            (900002, 16, :l2, :c2, :s2, :n2, :comp2, :b2, :city2, :st2, 1)'
    );
    $seed->execute([
        ':l1' => 'Casa',   ':c1' => '12053-831', ':s1' => 'Av. Banks',        ':n1' => '1200', ':b1' => 'Centro',        ':city1' => 'Taubaté',   ':st1' => 'SP',
        ':l2' => 'Trabalho', ':c2' => '01001-000', ':s2' => 'Av. Paulista',   ':n2' => '1000', ':comp2' => 'Sala 12', ':b2' => 'Bela Vista', ':city2' => 'São Paulo', ':st2' => 'SP',
    ]);
}

ob_start();
require_once __DIR__ . '/../../pages/auth/profile.php';
$html = (string) ob_get_clean();

file_put_contents($outFile, $html);

foreach ($seededAddressIds as $seededId) {
    $pdo->prepare('DELETE FROM e5_addresses WHERE id = :id')->execute([':id' => $seededId]);
}