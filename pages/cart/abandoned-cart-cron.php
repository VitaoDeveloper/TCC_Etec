<?php
/**
 * Recuperação de Carrinho Abandonado
 *
 * Cron job: executar periodicamente (ex: a cada hora via crontab).
 * Identifica carrinhos com itens há mais de 24h sem checkout finalizado
 * e envia e-mail de lembrete ao usuário.
 *
 * Uso:  php pages/cart/abandoned-cart-cron.php
 * Crontab: 0 * * * * cd /opt/lampp/htdocs/TCC_Etec && php pages/cart/abandoned-cart-cron.php >> storage/logs/abandoned-cart.log 2>&1
 */

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../includes/config.php';

$HOURS_THRESHOLD = 24;
$base_url = 'http://localhost:8080';

echo date('Y-m-d H:i:s') . " — Iniciando verificação de carrinhos abandonados (>{$HOURS_THRESHOLD}h)...\n";

// Buscar usuários com itens no carrinho que NÃO fizeram pedido nas últimas 24h
$stmt = $pdo->query('
    SELECT c.user_id, u.name, u.email,
           MIN(c.created_at) AS first_item_at,
           COUNT(*) AS item_count
    FROM e5_cart c
    INNER JOIN e5_users u ON u.id = c.user_id
    WHERE c.created_at < DATE_SUB(NOW(), INTERVAL ' . $HOURS_THRESHOLD . ' HOUR)
      AND c.user_id NOT IN (
          SELECT DISTINCT o.user_id
          FROM e5_orders o
          WHERE o.user_id IS NOT NULL
            AND o.created_at > DATE_SUB(NOW(), INTERVAL ' . $HOURS_THRESHOLD . ' HOUR)
      )
    GROUP BY c.user_id, u.name, u.email
    HAVING item_count > 0
');

$abandoned = $stmt->fetchAll();
$count = 0;

foreach ($abandoned as $row) {
    $userId = (int) $row['user_id'];
    $userName = $row['name'];
    $userEmail = $row['email'];

    if (empty($userEmail)) continue;

    // Buscar itens do carrinho
    $itemStmt = $pdo->prepare('
        SELECT c.quantity, p.name, p.price
        FROM e5_cart c
        INNER JOIN e5_products p ON p.id = c.product_id
        WHERE c.user_id = :uid
    ');
    $itemStmt->execute([':uid' => $userId]);
    $cartItems = $itemStmt->fetchAll();

    if (empty($cartItems)) continue;

    // Montar HTML do e-mail
    $itemsHtml = '';
    $total = 0;
    foreach ($cartItems as $item) {
        $subtotal = (float) $item['price'] * (int) $item['quantity'];
        $total += $subtotal;
        $itemsHtml .= sprintf(
            '<tr><td style="padding:8px; border-bottom:1px solid #eee;">%s</td><td style="padding:8px; border-bottom:1px solid #eee; text-align:center;">%d</td><td style="padding:8px; border-bottom:1px solid #eee; text-align:right;">R$ %s</td></tr>',
            htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'),
            (int) $item['quantity'],
            number_format($subtotal, 2, ',', '.')
        );
    }

    $body = sprintf('
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #1a237e; color: white; padding: 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 1.5rem;">Olá, %s!</h1>
            </div>
            <div style="padding: 20px; background: #f5f5f5;">
                <p style="font-size: 1rem;">Esqueceu algo no carrinho? Ainda dá tempo de finalizar sua compra!</p>
                <table style="width: 100%%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; margin: 15px 0;">
                    <thead><tr style="background: #1a237e; color: white;">
                        <th style="padding: 10px; text-align: left;">Produto</th>
                        <th style="padding: 10px; text-align: center;">Qtd</th>
                        <th style="padding: 10px; text-align: right;">Preço</th>
                    </tr></thead>
                    <tbody>%s</tbody>
                    <tfoot><tr style="font-weight: bold; background: #f5f5f5;">
                        <td colspan="2" style="padding: 12px; text-align: right;">Total:</td>
                        <td style="padding: 12px; text-align: right; color: #1a237e;">R$ %s</td>
                    </tr></tfoot>
                </table>
                <p style="text-align: center; margin: 25px 0;">
                    <a href="%s/pages/cart/cart.php" style="background: #1a237e; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-size: 1rem; font-weight: bold;">Finalizar Minha Compra</a>
                </p>
                <p style="color: #999; font-size: 0.85rem; text-align: center;">Se você não solicitou este e-mail, pode ignorá-lo.</p>
            </div>
        </div>',
        htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
        $itemsHtml,
        number_format($total, 2, ',', '.'),
        $base_url
    );

    $subject = 'Você tem itens esperando no carrinho!';

    try {
        $sent = sendMail($userEmail, $subject, $body);
        if ($sent) {
            $count++;
            echo "  ✓ E-mail enviado para {$userEmail} ({$row['item_count']} itens)\n";
        } else {
            echo "  ✗ Falha ao enviar para {$userEmail}\n";
        }
    } catch (Throwable $e) {
        echo "  ✗ Erro enviando para {$userEmail}: " . $e->getMessage() . "\n";
    }
}

echo date('Y-m-d H:i:s') . " — Concluído. {$count} e-mail(s) enviado(s).\n";
