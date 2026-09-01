<?php
session_start();
header('Content-Type: application/json');

$isGuest = !isset($_SESSION['user_id']);

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/cart_functions.php';

if ($isGuest) {
    sessionCartClear();
    $count = 0;
} else {
    cartClear($pdo, (int) $_SESSION['user_id']);
    $count = 0;
}

echo json_encode(['success' => true, 'count' => $count]);
