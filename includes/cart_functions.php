<?php

function sessionCartInit() {
    if (!isset($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = [];
    }
}

function sessionCartGetItems($pdo) {
    sessionCartInit();
    $cart = $_SESSION['guest_cart'];
    if (empty($cart)) return [];
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.id AS product_id, p.name, p.price, p.old_price, p.brand, p.stock,
               (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
        FROM e5_products p WHERE p.id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $products = [];
    foreach ($stmt->fetchAll() as $row) {
        $products[$row['product_id']] = $row;
    }
    $items = [];
    foreach ($cart as $pid => $qty) {
        if (isset($products[$pid]) && $qty > 0) {
            $item = $products[$pid];
            $item['quantity'] = $qty;
            $items[] = $item;
        }
    }
    return $items;
}

function sessionCartAddItem($productId, $quantity = 1) {
    sessionCartInit();
    if (isset($_SESSION['guest_cart'][$productId])) {
        $_SESSION['guest_cart'][$productId] += $quantity;
    } else {
        $_SESSION['guest_cart'][$productId] = $quantity;
    }
}

function sessionCartUpdateQuantity($productId, $quantity) {
    sessionCartInit();
    if ($quantity <= 0) {
        sessionCartRemoveItem($productId);
        return;
    }
    $_SESSION['guest_cart'][$productId] = $quantity;
}

function sessionCartRemoveItem($productId) {
    sessionCartInit();
    unset($_SESSION['guest_cart'][$productId]);
}

function sessionCartGetCount() {
    sessionCartInit();
    $count = 0;
    foreach ($_SESSION['guest_cart'] as $qty) {
        $count += (int) $qty;
    }
    return $count;
}

function sessionCartClear() {
    $_SESSION['guest_cart'] = [];
}

function cartGetCount($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM e5_cart WHERE user_id = :uid');
    $stmt->execute([':uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

function cartGetItems($pdo, $userId) {
    $stmt = $pdo->prepare('
        SELECT c.product_id, c.quantity,
               p.name, p.price, p.old_price, p.brand, p.stock,
               (SELECT pi.image_path FROM e5_product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
        FROM e5_cart c
        INNER JOIN e5_products p ON p.id = c.product_id
        WHERE c.user_id = :uid
        ORDER BY c.created_at DESC
    ');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

function cartAddItem($pdo, $userId, $productId, $quantity = 1) {
    $stmtCheck = $pdo->prepare('SELECT id, quantity FROM e5_cart WHERE user_id = :uid AND product_id = :pid');
    $stmtCheck->execute([':uid' => $userId, ':pid' => $productId]);
    $existing = $stmtCheck->fetch();
    if ($existing) {
        $stmt = $pdo->prepare('UPDATE e5_cart SET quantity = :qty WHERE id = :id');
        return $stmt->execute([':qty' => (int)$existing['quantity'] + $quantity, ':id' => $existing['id']]);
    }
    $stmt = $pdo->prepare('INSERT INTO e5_cart (user_id, product_id, quantity) VALUES (:uid, :pid, :qty)');
    return $stmt->execute([':uid' => $userId, ':pid' => $productId, ':qty' => $quantity]);
}

function cartUpdateQuantity($pdo, $userId, $productId, $quantity) {
    if ($quantity <= 0) {
        return cartRemoveItem($pdo, $userId, $productId);
    }
    $stmt = $pdo->prepare('UPDATE e5_cart SET quantity = :qty WHERE user_id = :uid AND product_id = :pid');
    return $stmt->execute([':uid' => $userId, ':pid' => $productId, ':qty' => $quantity]);
}

function cartRemoveItem($pdo, $userId, $productId) {
    $stmt = $pdo->prepare('DELETE FROM e5_cart WHERE user_id = :uid AND product_id = :pid');
    return $stmt->execute([':uid' => $userId, ':pid' => $productId]);
}

function cartGetItemQuantity($pdo, $userId, $productId) {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM e5_cart WHERE user_id = :uid AND product_id = :pid');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    return (int) $stmt->fetchColumn();
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
    return $stmt->execute([':qty' => $quantity, ':pid' => $productId, ':qty2' => $quantity]);
}
