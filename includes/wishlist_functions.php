<?php
function wishlistToggle($pdo, $userId, $productId) {
    $stmt = $pdo->prepare('SELECT id FROM e5_wishlist WHERE user_id = :uid AND product_id = :pid LIMIT 1');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare('DELETE FROM e5_wishlist WHERE user_id = :uid AND product_id = :pid');
        $stmt->execute([':uid' => $userId, ':pid' => $productId]);
        return ['active' => false, 'message' => 'Removido dos favoritos.'];
    }
    $stmt = $pdo->prepare('INSERT INTO e5_wishlist (user_id, product_id) VALUES (:uid, :pid)');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    return ['active' => true, 'message' => 'Adicionado aos favoritos!'];
}

function wishlistGetIds($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT product_id FROM e5_wishlist WHERE user_id = :uid');
    $stmt->execute([':uid' => $userId]);
    return array_column($stmt->fetchAll(), 'product_id');
}

function wishlistGetItems($pdo, $userId) {
    $stmt = $pdo->prepare('
        SELECT p.id AS product_id, p.name AS product_name, p.price, p.old_price, p.brand, p.stock AS product_stock,
            (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path,
            c.name AS product_category
        FROM e5_wishlist w
        INNER JOIN e5_products p ON p.id = w.product_id
        INNER JOIN e5_categories c ON c.id = p.category_id
        WHERE w.user_id = :uid
        ORDER BY w.created_at DESC
    ');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

function wishlistCount($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM e5_wishlist WHERE user_id = :uid');
    $stmt->execute([':uid' => $userId]);
    return (int) $stmt->fetchColumn();
}
