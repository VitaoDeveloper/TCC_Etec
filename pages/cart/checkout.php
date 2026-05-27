<?php
$page_title = 'Finalizar Pedido - Royal Tech';
$show_breadcrumb = true;
$breadcrumb_title = 'Finalizar Pedido';
$current_page = 'carrinho';
$base_path = '../../';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once $base_path . 'database/connection.php';
require_once $base_path . 'includes/cart_functions.php';

$userId = (int) $_SESSION['user_id'];
$items = cartGetItems($pdo, $userId);

if (empty($items)) {
    header('Location: cart.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM e5_users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

$total = 0;
foreach ($items as $item) {
    $total += (float) $item['price'] * (int) $item['quantity'];
}

$orderCreated = false;
$orderId = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO e5_orders (user_id, status, total) VALUES (:uid, :status, :total)');
        $stmt->execute([':uid' => $userId, ':status' => 'pending', ':total' => $total]);
        $orderId = (int) $pdo->lastInsertId();

        $stmtItem = $pdo->prepare('INSERT INTO e5_order_items (order_id, product_id, quantity, unit_price) VALUES (:oid, :pid, :qty, :price)');
        foreach ($items as $item) {
            $stmtItem->execute([
                ':oid' => $orderId,
                ':pid' => (int) $item['product_id'],
                ':qty' => (int) $item['quantity'],
                ':price' => (float) $item['price'],
            ]);
        }

        cartClear($pdo, $userId);

        $pdo->commit();
        $orderCreated = true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errorMessage = 'Erro ao processar pedido. Tente novamente.';
        error_log('Checkout error: ' . $e->getMessage());
    }
}

include $base_path . 'components/header.php';
?>
<section class="section"><div class="container">
    <div class="section-header"><h2>Finalizar Pedido</h2></div>

    <?php if ($orderCreated): ?>
        <div style="text-align:center; padding:60px 20px;">
            <i class="fas fa-check-circle" style="font-size:64px; color:#4caf50; margin-bottom:20px;"></i>
            <h3>Pedido Confirmado!</h3>
            <p>Seu pedido #<?php echo str_pad((string)$orderId, 4, '0', STR_PAD_LEFT); ?> foi criado com sucesso.</p>
            <p style="margin-bottom:20px;">Aguardando pagamento.</p>
            <a href="../products/products.php" class="btn btn-primary"><i class="fas fa-store"></i> Continuar Comprando</a>
        </div>
    <?php else: ?>
        <?php if ($errorMessage): ?>
            <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="admin-table-container" style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
            <div>
                <h3 style="margin-bottom:15px;">Resumo do Pedido</h3>
                <table class="admin-table">
                    <thead><tr><th>Produto</th><th>Qtd</th><th>Preço</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int)$item['quantity']; ?></td>
                            <td>R$ <?php echo number_format((float)$item['price'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="2" style="text-align:right; font-weight:600;">Total:</td><td style="font-weight:700; color:var(--color-gold);">R$ <?php echo number_format($total, 2, ',', '.'); ?></td></tr>
                    </tfoot>
                </table>
            </div>
            <div>
                <h3 style="margin-bottom:15px;">Endereço de Entrega</h3>
                <p><strong><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <p><?php echo htmlspecialchars($user['street'] ?? '', ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)($user['number'] ?? 0); ?><?php if ($user['complement']): ?> - <?php echo htmlspecialchars($user['complement'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></p>
                <p>CEP: <?php echo htmlspecialchars($user['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <p>Email: <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>

                <form method="POST" style="margin-top:25px;">
                    <p style="margin-bottom:15px; font-size:0.9rem; color:var(--color-gray);"><i class="fas fa-info-circle"></i> Ao finalizar, você concorda com nossos termos de compra. O pagamento será processado na entrega.</p>
                    <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-size:1.1rem;"><i class="fas fa-check"></i> Confirmar Pedido</button>
                    <a href="cart.php" class="btn btn-secondary" style="width:100%; margin-top:8px;"><i class="fas fa-arrow-left"></i> Voltar ao Carrinho</a>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div></section>
<?php include $base_path . 'components/footer.php'; ?>
