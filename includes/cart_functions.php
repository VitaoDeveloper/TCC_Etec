<?php

function sessionCartInit() {
    if (!isset($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = [];
        $_SESSION['guest_cart_timestamp'] = time();
    }
}

function sessionCartCleanup() {
    sessionCartInit();
    $maxAge = 2 * 24 * 60 * 60; // 2 dias (LGPD compliance)
    if (isset($_SESSION['guest_cart_timestamp'])) {
        if (time() - $_SESSION['guest_cart_timestamp'] > $maxAge) {
            $_SESSION['guest_cart'] = [];
            $_SESSION['guest_cart_timestamp'] = time();
        }
    }
}

function sessionCartMergeToUser($pdo, $userId) {
    if (!isset($_SESSION['guest_cart']) || empty($_SESSION['guest_cart'])) {
        return;
    }
    foreach ($_SESSION['guest_cart'] as $productId => $quantity) {
        $stockCheck = validateStock($pdo, $productId, $quantity);
        if ($stockCheck['ok']) {
            cartAddItem($pdo, $userId, $productId, $quantity);
        }
    }
    $_SESSION['guest_cart'] = [];
    $_SESSION['guest_cart_timestamp'] = time();
}

function sessionCartGetItems($pdo) {
    sessionCartInit();
    sessionCartCleanup();
    $cart = $_SESSION['guest_cart'];
    if (empty($cart)) return [];
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.id AS product_id, p.name, p.price, p.old_price, p.brand, p.stock,
               COALESCE(pi.image_path, '') AS image_path
        FROM e5_products p
        LEFT JOIN e5_product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
        WHERE p.id IN ($placeholders)
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
    $_SESSION['guest_cart_timestamp'] = time();
    if (isset($_SESSION['guest_cart'][$productId])) {
        $_SESSION['guest_cart'][$productId] += $quantity;
    } else {
        $_SESSION['guest_cart'][$productId] = $quantity;
    }
}

function sessionCartUpdateQuantity($productId, $quantity) {
    sessionCartInit();
    $_SESSION['guest_cart_timestamp'] = time();
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
               COALESCE(pi.image_path, \'\') AS image_path
        FROM e5_cart c
        INNER JOIN e5_products p ON p.id = c.product_id
        LEFT JOIN e5_product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
        WHERE c.user_id = :uid
        ORDER BY c.created_at DESC
    ');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

function cartAddItem($pdo, $userId, $productId, $quantity = 1) {
    $stmt = $pdo->prepare('
        INSERT INTO e5_cart (user_id, product_id, quantity) VALUES (:uid, :pid, :qty)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ');
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
    $stmt = $pdo->prepare('SELECT stock, price, name FROM e5_products WHERE id = :pid LIMIT 1');
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
    return ['ok' => true, 'available' => $available, 'price' => (float) $product['price'], 'name' => $product['name']];
}

function validatePriceChange($pdo, $items) {
    $changes = [];
    foreach ($items as $item) {
        $stmt = $pdo->prepare('SELECT price FROM e5_products WHERE id = :pid LIMIT 1');
        $stmt->execute([':pid' => $item['product_id']]);
        $current = $stmt->fetch();
        if ($current && abs((float) $current['price'] - (float) $item['price']) > 0.001) {
            $changes[] = [
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'old_price' => (float) $item['price'],
                'new_price' => (float) $current['price']
            ];
        }
    }
    return $changes;
}

function decrementStock($pdo, $productId, $quantity) {
    // SELECT FOR UPDATE previne race condition (2 usuários comprando o último item)
    $stmt = $pdo->prepare('SELECT stock FROM e5_products WHERE id = :pid FOR UPDATE');
    $stmt->execute([':pid' => $productId]);
    $row = $stmt->fetch();
    
    if (!$row || (int)$row['stock'] < $quantity) {
        throw new RuntimeException('Estoque insuficiente para produto ID ' . $productId);
    }
    
    $stmt = $pdo->prepare('UPDATE e5_products SET stock = stock - :qty WHERE id = :pid');
    return $stmt->execute([':qty' => $quantity, ':pid' => $productId]);
}
