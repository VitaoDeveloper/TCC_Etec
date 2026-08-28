<?php
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "PHP " . PHP_VERSION . "\n";
require_once __DIR__ . '/database/connection.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/comprovante.php';

$path = dirname(__DIR__, 2) . '/vendor/autoload.php';
echo "path=" . $path . " exists=" . (file_exists($path) ? "YES" : "NO") . "\n";

$r = enviarComprovanteEmail($GLOBALS['pdo'], 17);
echo "result=" . var_export($r, true) . "\n";
