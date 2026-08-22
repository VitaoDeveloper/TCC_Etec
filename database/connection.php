<?php

$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile) && !isset($_ENV['DB_HOST'])) {
    foreach (file($envFile) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $_ENV[$parts[0]] = $parts[1];
        }
    }
}

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