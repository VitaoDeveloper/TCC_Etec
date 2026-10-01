<?php

declare(strict_types=1);

/**
 * Migração idempotente para:
 *  1. Adicionar coluna discount_amount em e5_orders
 *  2. Backfill: calcular desconto = subtotal + frete - total (quando > 0)
 *  3. Adicionar colunas shipping_state (varchar 40) e shipping_postal_code (varchar 10) se não existirem
 *  4. Backfill de endereço: copiar endereço do usuário para shipping_* quando CEP coincidir
 *
 * Uso:
 *   php database/migration_discount_address.php
 *
 * Idempotente: só altera o que precisa; rodar de novo não muda nada.
 */

require_once __DIR__ . '/../includes/config.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/connection.php';

echo "=== Migração discount_amount + address fallback ===\n\n";

// 1. Coluna discount_amount
echo "1. Adicionando coluna discount_amount...\n";
try {
    $pdo->exec(
        'ALTER TABLE e5_orders 
         ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 
         AFTER total'
    );
    echo "   OK\n";
} catch (Throwable $e) {
    // MySQL < 8.0 não suporta IF NOT EXISTS em ALTER TABLE
    // Verifica se coluna existe
    $col = $pdo->query("SHOW COLUMNS FROM e5_orders LIKE 'discount_amount'")->fetch();
    if (!$col) {
        throw $e;
    }
    echo "   Coluna já existe\n";
}

// 2. Backfill discount_amount
echo "\n2. Backfill de discount_amount...\n";
$orders = $pdo->query('
    SELECT o.id, o.total, o.shipping_cost,
           COALESCE(SUM(oi.unit_price * oi.quantity), 0) AS subtotal
    FROM e5_orders o
    LEFT JOIN e5_order_items oi ON oi.order_id = o.id
    WHERE o.discount_amount = 0 OR o.discount_amount IS NULL
    GROUP BY o.id
')->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$updStmt = $pdo->prepare('UPDATE e5_orders SET discount_amount = :d WHERE id = :id');
foreach ($orders as $o) {
    $subtotal = (float) $o['subtotal'];
    $shipping = (float) $o['shipping_cost'];
    $total = (float) $o['total'];
    $discount = round($subtotal + $shipping - $total, 2);
    
    if ($discount > 0.01) { // tolerância de centavos
        $updStmt->execute([':d' => $discount, ':id' => $o['id']]);
        echo "   Pedido #{$o['id']}: subtotal=$subtotal + frete=$shipping - total=$total = desconto=$discount\n";
        $updated++;
    }
}
echo "   Atualizados: $updated pedidos\n";

// 3. Garantir colunas shipping_state e shipping_postal_code
echo "\n3. Verificando colunas de endereço de entrega...\n";
$cols = $pdo->query("SHOW COLUMNS FROM e5_orders")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('shipping_state', $cols)) {
    $pdo->exec('ALTER TABLE e5_orders ADD COLUMN shipping_state VARCHAR(40) NULL AFTER shipping_city');
    echo "   shipping_state adicionada\n";
}
if (!in_array('shipping_postal_code', $cols)) {
    $pdo->exec('ALTER TABLE e5_orders ADD COLUMN shipping_postal_code VARCHAR(10) NULL AFTER shipping_state');
    echo "   shipping_postal_code adicionada\n";
}
if (!in_array('shipping_neighborhood', $cols)) {
    $pdo->exec('ALTER TABLE e5_orders ADD COLUMN shipping_neighborhood VARCHAR(80) NULL AFTER shipping_city');
    echo "   shipping_neighborhood adicionada\n";
}
echo "   OK\n";

// 4. Backfill endereço: usar endereço do usuário quando CEP coincidir
echo "\n4. Backfill de endereço de entrega (fallback user -> order)...\n";
$ordersNoAddr = $pdo->query('
    SELECT o.id, o.shipping_postal_code, o.shipping_street, o.shipping_number,
           o.shipping_complement, o.shipping_neighborhood, o.shipping_city, o.shipping_state,
           u.postal_code AS user_cep, u.street AS user_street, u.number AS user_number,
           u.complement AS user_complement, u.neighborhood AS user_neighborhood,
           u.city AS user_city, u.state AS user_state
    FROM e5_orders o
    JOIN e5_users u ON u.id = o.user_id
    WHERE (o.shipping_street IS NULL OR o.shipping_street = "")
      AND u.postal_code IS NOT NULL AND u.postal_code != ""
')->fetchAll(PDO::FETCH_ASSOC);

$addrUpdated = 0;
$addrUpd = $pdo->prepare('
    UPDATE e5_orders SET
        shipping_street = :street,
        shipping_number = :number,
        shipping_complement = :complement,
        shipping_neighborhood = :neighborhood,
        shipping_city = :city,
        shipping_state = :state,
        shipping_postal_code = :postal_code
    WHERE id = :id
');

foreach ($ordersNoAddr as $o) {
    $orderCep = only_digits($o['shipping_postal_code'] ?? '');
    $userCep = only_digits($o['user_cep'] ?? '');
    
    if ($orderCep !== '' && $orderCep === $userCep) {
        $addrUpd->execute([
            ':street'       => $o['user_street'],
            ':number'       => $o['user_number'],
            ':complement'   => $o['user_complement'],
            ':neighborhood' => $o['user_neighborhood'],
            ':city'         => $o['user_city'],
            ':state'        => $o['user_state'],
            ':postal_code'  => format_cep($o['user_cep']),
            ':id'           => $o['id'],
        ]);
        echo "   Pedido #{$o['id']}: endereço copiado do usuário (CEP $userCep)\n";
        $addrUpdated++;
    }
}
echo "   Atualizados: $addrUpdated pedidos\n";

// Funções auxiliares inline (copiadas de validators.php para não depender)
function only_digits(?string $s): string {
    return preg_replace('/\D/', '', (string) $s) ?? '';
}

function format_cep(string $cep): string {
    $d = only_digits($cep);
    if (strlen($d) === 8) {
        return substr($d, 0, 5) . '-' . substr($d, 5);
    }
    return $cep;
}

echo "\n=== Migração concluída ===\n";