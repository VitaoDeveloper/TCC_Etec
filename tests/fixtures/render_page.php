<?php

/**
 * Fixture de renderização para o checklist da Stage 5.
 *
 * Roda em processo isolado (a página emite <html> e depende de
 * variáveis de escopo de arquivo).
 *
 *   php tests/fixtures/render_page.php <script> <query-json> <saida>
 *
 *   <script>     nome do arquivo em pages/auth/
 *   <query-json> ex.: {"id":"12"}
 *   <saida>      caminho do arquivo de saída
 */

if ($argc < 4) {
    fwrite(STDERR, "uso: render_page.php <script> <query-json> <saida>\n");
    exit(2);
}

$script  = $argv[1];
$query   = json_decode($argv[2], true) ?: [];
$outFile = $argv[3];

// Só aceita nome simples de arquivo dentro de pages/auth/.
if (preg_match('/^[A-Za-z0-9_\-]+\.php$/', $script) !== 1) {
    fwrite(STDERR, "script inválido: {$script}\n");
    exit(2);
}

$_SERVER['SCRIPT_NAME']    = '/pages/auth/' . $script;
$_SERVER['REQUEST_URI']    = '/pages/auth/' . $script;
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

$_GET  = $query;
$_POST = [];

session_id('stage5-checklist');
session_start();

$_SESSION['user_id']   = 16;
$_SESSION['user_role'] = 'admin';

require_once __DIR__ . '/../../database/connection.php';

ob_start();
require __DIR__ . '/../../pages/auth/' . $script;
$html = (string) ob_get_clean();

file_put_contents($outFile, $html);
echo strlen($html), "\n";
