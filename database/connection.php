<?php

require "vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

try {
    $dotenv->required([
        'DB_HOST',
        'DB_NAME', 
        'DB_USER'
    ])->notEmpty();
} catch (RuntimeException $e) {
    echo "Erro de configuração: " . $e->getMessage();
    exit; 
}

$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USER'];

$dbPass = $_ENV['DB_PASS'] ?? "";
$dbCharset = $_ENV['DB_CHARSET'] ?? "utf8mb4";

$dsn = "mysql:host=$host;dbname=$database;charset=$dbCharset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno ao conectar com o banco de dados.');
}
?>
