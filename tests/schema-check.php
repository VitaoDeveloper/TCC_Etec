<?php

declare(strict_types=1);

/**
 * Confere se o banco de desenvolvimento bate com database/database.sql.
 *
 * Cria um banco descartável, importa o arquivo e compara as colunas de
 * information_schema entre os dois. Sem isto o arquivo pode ficar
 * desatualizado do banco e ninguém percebe até uma tela quebrar em produção.
 *
 *   /opt/lampp/bin/php tests/schema-check.php
 *
 * Sai com código 0 se forem idênticos e 1 se houver diferença. O banco de
 * desenvolvimento é somente lido; o banco de teste é sempre descartado,
 * inclusive quando o script falha no meio.
 */

$root = dirname(__DIR__);

require $root . '/includes/config.php';
loadEnv($root . '/.env');

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = trim((string) ($_ENV['DB_PORT'] ?? ''));
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$schema = $_ENV['DB_NAME'] ?? 'e5_royaltech';

// O nome do banco entra em DDL via interpolação (o mysql client não aceita
// prepared statement em "CREATE DATABASE"), então precisa ser validado.
if (preg_match('/^[A-Za-z0-9_]+$/', $schema) !== 1) {
    fwrite(STDERR, "DB_NAME invalido: " . $schema . "\n");
    exit(1);
}
$probeDb = $schema . '_schema_check';

$dsnBase = 'mysql:host=' . $host;
if ($port !== '' && ctype_digit($port)) {
    $dsnBase .= ';port=' . $port;
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    // information_schema devolve os nomes das colunas em caixa alta; fixar
    // o caso torna a leitura dos arrays determinística entre ambientes.
    PDO::ATTR_CASE => PDO::CASE_LOWER,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

/** Colunas de um schema, agrupadas por tabela. */
function colunas(PDO $pdo, string $schema): array
{
    $st = $pdo->prepare(
        'SELECT table_name, column_name, column_type, is_nullable, column_default,
                extra, character_set_name, collation_name
         FROM information_schema.columns
         WHERE table_schema = :s
         ORDER BY table_name, ordinal_position'
    );
    $st->execute([':s' => $schema]);

    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[$r['table_name']][$r['column_name']] = implode('|', [
            $r['column_type'],
            $r['is_nullable'],
            $r['column_default'] ?? '',
            $r['extra'],
            $r['character_set_name'] ?? '-',
            $r['collation_name'] ?? '-',
        ]);
    }
    return $out;
}

$server = new PDO($dsnBase, $user, $pass, $options);
$server->exec("DROP DATABASE IF EXISTS `$probeDb`");
$server->exec("CREATE DATABASE `$probeDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

// O cliente do XAMPP fica em /opt/lampp/bin; senão usa o do PATH.
$mysql = is_file('/opt/lampp/bin/mysql') ? '/opt/lampp/bin/mysql' : 'mysql';

// Senha por variável de ambiente: passar --password na linha de comando
// deixa a credencial visível na lista de processos do servidor.
if ($pass !== '') {
    putenv('MYSQL_PWD=' . $pass);
}

$cmd = sprintf(
    '%s --host=%s%s --user=%s --default-character-set=utf8mb4 %s < %s 2>&1',
    escapeshellcmd($mysql),
    escapeshellarg($host),
    ($port !== '' && ctype_digit($port)) ? ' --port=' . escapeshellarg($port) : '',
    escapeshellarg($user),
    escapeshellarg($probeDb),
    escapeshellarg($root . '/database/database.sql')
);

$problems = [];
$falhouImport = false;

try {
    $out = [];
    $rc = 0;
    exec($cmd, $out, $rc);

    if ($rc !== 0) {
        $falhouImport = true;
        fwrite(STDERR, "A importacao falhou (codigo $rc):\n" . implode("\n", $out) . "\n");
    } else {
        $live = new PDO($dsnBase . ';dbname=' . $schema . ';charset=utf8mb4', $user, $pass, $options);
        $probe = new PDO($dsnBase . ';dbname=' . $probeDb . ';charset=utf8mb4', $user, $pass, $options);

        $a = colunas($live, $schema);
        $b = colunas($probe, $probeDb);

        $soNoBanco = array_diff(array_keys($a), array_keys($b));
        $soNoArquivo = array_diff(array_keys($b), array_keys($a));

        if ($soNoBanco !== []) {
            $problems[] = 'tabelas so no banco (faltam no arquivo): ' . implode(', ', $soNoBanco);
        }
        if ($soNoArquivo !== []) {
            $problems[] = 'tabelas so no arquivo (nao aplicadas no banco): ' . implode(', ', $soNoArquivo);
        }

        foreach (array_intersect(array_keys($a), array_keys($b)) as $t) {
            foreach ($a[$t] as $col => $def) {
                if (!isset($b[$t][$col])) {
                    $problems[] = "coluna $t.$col existe no banco e nao no arquivo";
                } elseif ($b[$t][$col] !== $def) {
                    $problems[] = "coluna $t.$col difere | banco: $def | arquivo: {$b[$t][$col]}";
                }
            }
            foreach (array_diff(array_keys($b[$t]), array_keys($a[$t])) as $col) {
                $problems[] = "coluna $t.$col existe no arquivo e nao no banco";
            }
        }
    }
} finally {
    $server->exec("DROP DATABASE IF EXISTS `$probeDb`");
    if ($pass !== '') {
        putenv('MYSQL_PWD');
    }
}

if ($falhouImport) {
    exit(1);
}

if ($problems === []) {
    printf("Schema conferido: %d tabelas iguais em '%s' e em database/database.sql.\n", count($a), $schema);
    exit(0);
}

fwrite(STDERR, "Schema divergente:\n - " . implode("\n - ", $problems) . "\n");
exit(1);
