<?php
// =============================================================================
// Avaliações de produto (tabela e5_reviews).
// Regra: cliente que comprou e o pedido foi pago (paid/shipped/delivered)
// pode avaliar — uma única avaliação por usuário/produto (UPSERT).
// =============================================================================

function reviewGetSummary($pdo, int $productId): array
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating FROM e5_reviews WHERE product_id = :pid');
    $stmt->execute([':pid' => $productId]);
    $row = $stmt->fetch();
    return [
        'count' => (int) ($row['cnt'] ?? 0),
        'avg' => round((float) ($row['avg_rating'] ?? 0), 1),
    ];
}

function reviewGetList($pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, u.name AS user_name
           FROM e5_reviews r
           INNER JOIN e5_users u ON u.id = r.user_id
          WHERE r.product_id = :pid
          ORDER BY r.created_at DESC'
    );
    $stmt->execute([':pid' => $productId]);
    return $stmt->fetchAll() ?: [];
}

function reviewGetUser($pdo, int $userId, int $productId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM e5_reviews WHERE user_id = :u AND product_id = :pid LIMIT 1');
    $stmt->execute([':u' => $userId, ':pid' => $productId]);
    return $stmt->fetch() ?: null;
}

// Cliente só avalia se já comprou o produto e o pedido foi pago.
function reviewCanUser($pdo, int $userId, int $productId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
           FROM e5_order_items oi
           INNER JOIN e5_orders o ON o.id = oi.order_id
          WHERE oi.product_id = :pid AND o.user_id = :u
            AND o.status IN (\'paid\', \'shipped\', \'delivered\')
            AND o.payment_status = \'paid\'
          LIMIT 1'
    );
    $stmt->execute([':pid' => $productId, ':u' => $userId]);
    return (bool) $stmt->fetchColumn();
}

// Upsert: cliente que já avaliou tem a avaliação atualizada.
function reviewSave($pdo, int $userId, int $productId, int $rating, string $comment): array
{
    if ($rating < 1 || $rating > 5) {
        return ['ok' => false, 'message' => 'Nota inválida (1 a 5 estrelas).'];
    }
    $comment = trim($comment);
    if (mb_strlen($comment) > 1000) {
        return ['ok' => false, 'message' => 'Comentário muito longo (máx. 1000 caracteres).'];
    }
    if (!reviewCanUser($pdo, $userId, $productId)) {
        return ['ok' => false, 'message' => 'Avalie depois de receber seu pedido.'];
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO e5_reviews (user_id, product_id, rating, comment)
             VALUES (:u, :pid, :rating, :comment)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':u' => $userId,
            ':pid' => $productId,
            ':rating' => $rating,
            ':comment' => $comment !== '' ? $comment : null,
        ]);
        return ['ok' => true, 'message' => 'Avaliação salva. Obrigado pelo feedback!'];
    } catch (Throwable $e) {
        error_log('Review save error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Erro ao salvar a avaliação.'];
    }
}

function reviewDelete($pdo, int $userId, int $productId): array
{
    $stmt = $pdo->prepare('DELETE FROM e5_reviews WHERE user_id = :u AND product_id = :pid');
    $stmt->execute([':u' => $userId, ':pid' => $productId]);
    return ['ok' => true, 'message' => 'Avaliação removida.'];
}