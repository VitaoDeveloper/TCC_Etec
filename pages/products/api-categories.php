<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$connPath = dirname(__DIR__, 2) . '/database/connection.php';
if (!file_exists($connPath)) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database connection not found']));
}
include $connPath;

try {
    $categories = $pdo->query(
        'SELECT c.id, c.name, c.slug, c.description, 
         (SELECT COUNT(*) FROM e5_products p WHERE p.category_id = c.id) AS product_count 
         FROM e5_categories c ORDER BY c.name ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['categories' => $categories], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load categories']);
}
