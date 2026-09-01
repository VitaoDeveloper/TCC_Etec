<?php
session_start();
header('Content-Type: application/json');

$isGuest = !isset($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

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
