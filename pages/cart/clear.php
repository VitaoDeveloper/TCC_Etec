<?php
session_start();
header('Content-Type: application/json');

$isGuest = !isset($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// CSRF: valida token obrigatoriamente
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? null);
if (!$csrfToken || !isset($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token inválido.']);
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
