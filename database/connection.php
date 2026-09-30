<?php
require_once __DIR__ . '/../includes/config.php';
loadEnv(__DIR__ . '/../.env');

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'e5_royaltech';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';
$dbCharset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// Porta do MySQL. O DSN abaixo só recebe ";port=" quando DB_PORT existe de
// fato, para não quebrar as instalações que confiam na porta 3306 ou no
// socket unix (é o caso de "localhost", que ignora a porta e usa o socket).
//
// Sem isto, apontar o .env para um MySQL em porta diferente era impossível:
// o DSN ficava "mysql:host=127.0.0.1;dbname=..." e o PDO caia sempre no
// 3306, produzindo "SQLSTATE[HY000] [2002] Connection refused".
$dbPort = trim((string) ($_ENV['DB_PORT'] ?? ''));
$portDsn = ($dbPort !== '' && ctype_digit($dbPort)) ? ";port=$dbPort" : '';

$initSqlFile = __DIR__ . '/database.sql';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
];

if (!isset($GLOBALS['pdo'])) {
    $dsn = "mysql:host=$dbHost$portDsn;dbname=$dbName;charset=$dbCharset";

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (PDOException $e) {
        if ($e->getCode() == 1049 or $e->getCode() == 1146) {
            $dsnSemBanco = "mysql:host=$dbHost$portDsn;charset=$dbCharset";
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