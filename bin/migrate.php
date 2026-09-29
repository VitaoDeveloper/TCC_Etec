<?php

declare(strict_types=1);

/**
 * Runner de migrations.
 *
 *   php bin/migrate.php status          lista o que ja foi aplicado e o que falta
 *   php bin/migrate.php up              aplica as migrations pendentes, em ordem
 *   php bin/migrate.php down            reverte a ultima migration aplicada
 *   php bin/migrate.php down <arquivo>  reverte uma migration especifica
 *
 * POR QUE ISTO EXISTE
 * -------------------
 * O projeto tinha dois arquivos .sql em database/migrations/ e nenhum
 * registro do que ja tinha sido aplicado. Ninguem discovers que 20260915
 * nunca rodou, e o resultado foi um banco 21 colunas atrasado em relacao ao
 * codigo — cart.php e checkout.php respondiam 500 por causa disso. A
 * tabela e5_schema_migrations elimina a duvida: o estado do schema passa a
 * ser consultavel em vez de deduzido.
 *
 * REGRA DE REVERSIBILIDADE
 * ------------------------
 * `down` so funciona se existir um arquivo `<nome>.down.sql`. Uma migration
 * que nao sabe como se desfazer e recusada no `down` em vez de fingir que
 * reverteu. MySQL nao tem DDL transacional (todo CREATE/ALTER faz commit
 * implicito), entao o `up` nao roda dentro de transacao: se uma migration
 * falhar no meio, as anteriores dela ja ficaram aplicadas e o arquivo
 * precisa ser corrigido a mao.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 - Este script e restrito a linha de comando.\n");
}

require_once __DIR__ . '/../includes/config.php';
loadEnv(__DIR__ . '/../.env');

const MIGRATIONS_DIR = __DIR__ . '/../database/migrations';
const TABELA_LOG     = 'e5_schema_migrations';

try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'localhost',
            !empty($_ENV['DB_PORT']) ? ';port=' . $_ENV['DB_PORT'] : '',
            $_ENV['DB_NAME'] ?? 'e5_royaltech'
        ),
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Falha ao conectar no banco: {$e->getMessage()}\n");
    fwrite(STDERR, "Verifique DB_HOST/DB_PORT/DB_NAME no .env.\n");
    exit(1);
}

function ensureTabelaLog(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS " . TABELA_LOG . " (
            filename   VARCHAR(255) NOT NULL,
            checksum   CHAR(64)     NOT NULL,
            applied_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/** Migrations `up`, em ordem lexicografica (o prefixo de data ordena). */
function migrationsUp(): array
{
    $files = [];

    foreach (new DirectoryIterator(MIGRATIONS_DIR) as $f) {
        if ($f->isDot() || !$f->isFile()) {
            continue;
        }

        $nome = $f->getFilename();

        // Sem prefixo numerico nao e migration (evita pegar .down.sql e
        // qualquer README que apareca na pasta).
        if (!preg_match('/^[0-9].*\.sql$/', $nome) || str_ends_with($nome, '.down.sql')) {
            continue;
        }

        $files[] = $f->getPathname();
    }

    sort($files, SORT_STRING);
    return $files;
}

/**
 * Quebra um arquivo .sql em comandos.
 *
 * Nao pode ser um explode(';') simples: `;` aparece dentro de strings e de
 * comentarios, e um trigger/procedure tem delimitador proprio. Aqui sao
 * removidos comentarios de linha e de bloco, e o texto dentro de aspas
 * simples/duplas e ignorado ao procurar o `;`.
 */
function splitSql(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;

    $comandos = [];
    $atual    = '';
    $len      = strlen($sql);
    $aspas    = null;

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];

        if ($aspas !== null) {
            $atual .= $c;
            if ($c === '\\') {          // escape: pula o proximo caractere
                if ($i + 1 < $len) {
                    $atual .= $sql[$i + 1];
                    $i++;
                }
            } elseif ($c === $aspas) {
                $aspas = null;
            }
            continue;
        }

        if ($c === "'" || $c === '"' || $c === '`') {
            $aspas = $c;
            $atual .= $c;
            continue;
        }

        if ($c === ';') {
            if (trim($atual) !== '') {
                $comandos[] = trim($atual);
            }
            $atual = '';
            continue;
        }

        $atual .= $c;
    }

    if (trim($atual) !== '') {
        $comandos[] = trim($atual);
    }

    return $comandos;
}

function aplicar(PDO $pdo, string $caminho, bool $registrar): void
{
    $sql = file_get_contents($caminho);
    if ($sql === false) {
        throw new RuntimeException("Nao consegui ler {$caminho}");
    }

    $n = 0;

    // Modo -- @raw: o arquivo inteiro vai em um unico exec(). Necessario para
    // migrations que usam CREATE PROCEDURE, porque o BEGIN...END contem `;`
    // que o splitSql trataria como fim de comando.
    if (preg_match('/^\s*--\s*@raw\b/m', $sql) === 1) {
        $pdo->exec($sql);
        $n = 1;
    } else {
        foreach (splitSql($sql) as $comando) {
            $pdo->exec($comando);
            $n++;
        }
    }

    $nome = basename($caminho);
    echo "  aplicado: {$nome} ({$n} comando(s))\n";

    if ($registrar) {
        $ins = $pdo->prepare(
            'INSERT INTO ' . TABELA_LOG . ' (filename, checksum) VALUES (:f, :c)
             ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), applied_at = CURRENT_TIMESTAMP'
        );
        $ins->execute([':f' => $nome, ':c' => hash_file('sha256', $caminho)]);
    }
}

function aplicadas(PDO $pdo): array
{
    $st = $pdo->query('SELECT filename, checksum, applied_at FROM ' . TABELA_LOG . ' ORDER BY filename');
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[$r['filename']] = $r;
    }
    return $out;
}

$comando = $argv[1] ?? 'status';
ensureTabelaLog($pdo);

switch ($comando) {
    case 'status': {
        $aplicadas = aplicadas($pdo);
        $pendentes = 0;
        echo "Migrations em " . MIGRATIONS_DIR . "\n\n";
        foreach (migrationsUp() as $arquivo) {
            $nome = basename($arquivo);
            if (isset($aplicadas[$nome])) {
                $igual = hash_file('sha256', $arquivo) === $aplicadas[$nome]['checksum'];
                echo sprintf("  [aplicada] %-52s %s%s\n", $nome, $aplicadas[$nome]['applied_at'],
                    $igual ? '' : '  <-- ARQUIVO MUDOU DEPOIS DE APLICADO');
            } else {
                echo sprintf("  [PENDENTE] %-52s\n", $nome);
                $pendentes++;
            }
        }
        // .down.sql orfao: rollback sem migration correspondente
        foreach (glob(MIGRATIONS_DIR . '/*.down.sql') ?: [] as $down) {
            $up = str_replace('.down.sql', '.sql', $down);
            if (!file_exists($up)) {
                echo "  [orfa]     " . basename($down) . "  (sem .sql correspondente)\n";
            }
        }
        echo "\n" . $pendentes . " pendente(s).\n";
        exit($pendentes > 0 ? 1 : 0);
    }

    case 'up': {
        $aplicadas = aplicadas($pdo);
        $fez = 0;
        foreach (migrationsUp() as $arquivo) {
            $nome = basename($arquivo);
            if (isset($aplicadas[$nome])) {
                continue;
            }
            echo "aplicando {$nome}\n";
            try {
                aplicar($pdo, $arquivo, true);
                $fez++;
            } catch (Throwable $e) {
                fwrite(STDERR, "\nFALHOU em {$nome}: " . $e->getMessage() . "\n");
                fwrite(STDERR, "MySQL nao tem DDL transacional: o que ja rodou antes deste comando permanece.\n");
                exit(1);
            }
        }
        echo $fez === 0 ? "Nada pendente.\n" : "\n{$fez} migration(s) aplicada(s).\n";
        exit(0);
    }

    case 'down': {
        $alvo = $argv[2] ?? null;

        if ($alvo === null) {
            $aplicadas = aplicadas($pdo);
            if (!$aplicadas) {
                echo "Nenhuma migration aplicada.\n";
                exit(0);
            }
            $alvo = array_key_last($aplicadas);
        }

        $up    = MIGRATIONS_DIR . '/' . $alvo;
        $down  = MIGRATIONS_DIR . '/' . preg_replace('/\.sql$/', '.down.sql', $alvo);

        if (!file_exists($up)) {
            fwrite(STDERR, "Migration desconhecida: {$alvo}\n");
            exit(1);
        }
        if (!file_exists($down)) {
            fwrite(STDERR, "{$alvo} nao tem .down.sql — nao e possivel reverter com seguranca.\n");
            fwrite(STDERR, "Escreva o rollback antes de usar esta migration em producao.\n");
            exit(1);
        }

        echo "revertendo {$alvo}\n";
        try {
            aplicar($pdo, $down, false);
        } catch (Throwable $e) {
            fwrite(STDERR, "FALHOU ao reverter: " . $e->getMessage() . "\n");
            exit(1);
        }

        $del = $pdo->prepare('DELETE FROM ' . TABELA_LOG . ' WHERE filename = :f');
        $del->execute([':f' => $alvo]);
        echo "  revertida: {$alvo}\n";
        exit(0);
    }

    default:
        fwrite(STDERR, "Comando desconhecido: {$comando}\n");
        fwrite(STDERR, "Uso: php bin/migrate.php [status|up|down [arquivo]]\n");
        exit(1);
}
