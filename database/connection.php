<?php


$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USER'];

$dbPass = $_ENV['DB_PASS'] ?? "";
$dbCharset = $_ENV['DB_CHARSET'] ?? "utf8mb4";

// Temp credentials
$host = 'u801921494_btcc';
$db = 'e5_royaltech';
$user = 'u801921494_btcc';
$pass = 'Etec_tte_125';
$charset = 'utf8mb4';


$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno ao conectar com o banco de dados.');
}
?>
