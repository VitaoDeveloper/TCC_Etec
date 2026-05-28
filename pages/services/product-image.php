<?php
include '../../database/connection.php';
$productId = (int) ($_GET['product_id'] ?? 0);

$placeholder = '../../assets/img/placeholder-product.svg';
if ($productId <= 0) {
    echo $placeholder;
    exit;
}

$stmt = $pdo->prepare('SELECT image_path FROM e5_product_images WHERE product_id = :product_id ORDER BY is_primary DESC, id ASC LIMIT 1');
$stmt->execute([':product_id' => $productId]);
$row = $stmt->fetch();

if (!$row || empty($row['image_path'])) {
    echo $placeholder;
    exit;
}

echo $row['image_path'];
