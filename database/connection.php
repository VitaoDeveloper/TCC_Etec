<?php
require_once __DIR__ . '/../includes/config.php';
loadEnv(__DIR__ . '/../../.env');

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'e5_royaltech';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';
$dbCharset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

if (!isset($GLOBALS['pdo'])) {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    $GLOBALS['pdo'] = $pdo;
}
$pdo = $GLOBALS['pdo'];