<?php

require "vendor/autoload.php";

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
    $dotenv->required([
        'DB_HOST',
        'DB_NAME', 
        'DB_USER'
    ])->notEmpty();
} catch (RuntimeException $e) {
    echo "Erro de configuração: " . $e->getMessage();
    exit; 
}
?>