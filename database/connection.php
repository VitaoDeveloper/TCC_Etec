<?php
require_once __DIR__ . '/../includes/config.php';
loadEnv(__DIR__ . '/../.env');

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'e5_royaltech';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';
$dbCharset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$initSqlFile = __DIR__ . '/database.sql';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

if (!isset($GLOBALS['pdo'])) {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (PDOException $e) {
        if ($e->getCode() == 1049 or $e->getCode() == 1146) {
            $dsnSemBanco = "mysql:host=$dbHost;charset=$dbCharset";
            $pdoTemp = new PDO($dsnSemBanco, $dbUser, $dbPass, $options);
            
            if (is_file($initSqlFile)) {
                $sql = file_get_contents($initSqlFile);
                $pdoTemp->exec($sql);
            }

            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        } else {
            throw $e;
        }
    }

    $GLOBALS['pdo'] = $pdo;
}
$pdo = $GLOBALS['pdo'];

// Alinha o fuso da sessão MySQL ao fuso da loja (America/Sao_Paulo, UTC-3),
// para que NOW()/created_at batam com as datas geradas em PHP.
try {
    $pdo->exec("SET time_zone = '-03:00'");
} catch (Throwable $e) {
    // Servidor sem tabelas de fuso horário: mantém o padrão do sistema.
}