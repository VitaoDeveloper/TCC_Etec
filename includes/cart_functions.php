<?php
// Itens ativos do carrinho (não inclui "salvos para depois").
function cartGetCount($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM e5_cart WHERE user_id = :uid AND saved_for_later = 0');
    $stmt->execute([':uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

function cartGetItems($pdo, $userId) {
    $stmt = $pdo->prepare('
        SELECT c.product_id, c.quantity,
               p.name, p.price, p.old_price, p.brand, p.stock,
               p.package_size_id, p.weight_kg, p.height_cm, p.width_cm, p.length_cm,
               ps.height_cm AS preset_height_cm, ps.width_cm AS preset_width_cm,
               ps.length_cm AS preset_length_cm, ps.max_weight_kg AS preset_max_weight_kg,
               (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
        FROM e5_cart c
        INNER JOIN e5_products p ON p.id = c.product_id
        LEFT JOIN e5_package_sizes ps ON ps.id = p.package_size_id
        WHERE c.user_id = :uid AND c.saved_for_later = 0
        ORDER BY c.created_at DESC
    ');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

// Itens marcados como "salvos para depois" (fora do total do carrinho).
function cartGetSavedItems($pdo, $userId) {
    $stmt = $pdo->prepare('
        SELECT c.product_id, c.quantity,
               p.name, p.price, p.old_price, p.brand, p.stock,
               (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
        FROM e5_cart c
        INNER JOIN e5_products p ON p.id = c.product_id
        WHERE c.user_id = :uid AND c.saved_for_later = 1
        ORDER BY c.updated_at DESC
    ');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

// Retorna a linha do carrinho (ativa ou salva) para o produto, ou null.
function cartGetRow($pdo, $userId, $productId) {
    $stmt = $pdo->prepare('SELECT id, quantity, saved_for_later FROM e5_cart WHERE user_id = :uid AND product_id = :pid LIMIT 1');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cartAddItem($pdo, $userId, $productId, $quantity = 1) {
    $existing = cartGetRow($pdo, $userId, $productId);
    if ($existing) {
        // Reativa o item caso estivesse "salvo para depois" e soma a quantidade.
        $stmt = $pdo->prepare('UPDATE e5_cart SET quantity = :qty, saved_for_later = 0 WHERE id = :id');
        return $stmt->execute([':qty' => (int) $existing['quantity'] + $quantity, ':id' => $existing['id']]);
    }
    $stmt = $pdo->prepare('INSERT INTO e5_cart (user_id, product_id, quantity, saved_for_later) VALUES (:uid, :pid, :qty, 0)');
    return $stmt->execute([':uid' => $userId, ':pid' => $productId, ':qty' => $quantity]);
}

// Marca/desmarca um item como "salvo para depois". Retorna true se alterou.
function cartSetSaved($pdo, $userId, $productId, $saved) {
    $stmt = $pdo->prepare('UPDATE e5_cart SET saved_for_later = :saved WHERE user_id = :uid AND product_id = :pid');
    $stmt->execute([':saved' => $saved ? 1 : 0, ':uid' => $userId, ':pid' => $productId]);
    return $stmt->rowCount() > 0;
}

function cartUpdateQuantity($pdo, $userId, $productId, $quantity) {
    if ($quantity <= 0) {
        return cartRemoveItem($pdo, $userId, $productId);
    }
    $stmt = $pdo->prepare('UPDATE e5_cart SET quantity = :qty WHERE user_id = :uid AND product_id = :pid');
    $stmt->execute([':uid' => $userId, ':pid' => $productId, ':qty' => $quantity]);
    return $stmt->rowCount();
}

function cartRemoveItem($pdo, $userId, $productId) {
    $stmt = $pdo->prepare('DELETE FROM e5_cart WHERE user_id = :uid AND product_id = :pid');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    return $stmt->rowCount();
}

function cartGetItemQuantity($pdo, $userId, $productId) {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM e5_cart WHERE user_id = :uid AND product_id = :pid AND saved_for_later = 0');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    return (int) $stmt->fetchColumn();
}

// Remove vários itens de uma vez (usado no checkout para limpar só o que foi comprado).
function cartRemoveItems($pdo, $userId, array $productIds) {
    $ids = array_values(array_filter(array_map('intval', $productIds), fn($v) => $v > 0));
    if (empty($ids)) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM e5_cart WHERE user_id = ? AND product_id IN ($in)");
    $stmt->execute(array_merge([$userId], $ids));
    return $stmt->rowCount();
}

function cartClear($pdo, $userId) {
    $stmt = $pdo->prepare('DELETE FROM e5_cart WHERE user_id = :uid');
    return $stmt->execute([':uid' => $userId]);
}

function validateStock($pdo, $productId, $quantity, $cartQty = 0) {
    $stmt = $pdo->prepare('SELECT stock FROM e5_products WHERE id = :pid LIMIT 1');
    $stmt->execute([':pid' => $productId]);
    $product = $stmt->fetch();
    if (!$product) return ['ok' => false, 'msg' => 'Produto não encontrado.'];
    $available = (int) $product['stock'];
    if ($available <= 0) return ['ok' => false, 'msg' => 'Produto esgotado.'];
    if ((int) $quantity + (int) $cartQty > $available) {
        $msg = "Apenas $available unidade(s) disponível(is).";
        if ($cartQty > 0) $msg .= " Você já tem $cartQty no carrinho.";
        return ['ok' => false, 'msg' => $msg];
    }
    return ['ok' => true, 'available' => $available];
}

function decrementStock($pdo, $productId, $quantity) {
    $stmt = $pdo->prepare('UPDATE e5_products SET stock = stock - :qty WHERE id = :pid AND stock >= :qty2');
    $stmt->execute([':qty' => $quantity, ':pid' => $productId, ':qty2' => $quantity]);
    return $stmt->rowCount();
}
