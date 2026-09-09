<?php
/**
 * Helpers de cupom de desconto para o checkout.
 * Tabela: e5_coupons (type: percent|fixed).
 */

/**
 * Valida e calcula o desconto de um cupom.
 *
 * @param PDO      $pdo
 * @param string   $code        Código digitado pelo cliente
 * @param float    $baseAmount  Base sobre a qual o desconto incide (subtotal pós-promo)
 * @param int|null $userId      ID do usuário logado (usado p/ segmento: new/vip)
 * @return array{ok: bool, code?: string, discount?: float, msg?: string}
 */
function couponApply(PDO $pdo, string $code, float $baseAmount, ?int $userId = null): array
{
    $code = mb_strtoupper(trim($code));

    if ($code === '') {
        return ['ok' => false, 'msg' => 'Digite um código de cupom.'];
    }
    if ($baseAmount <= 0) {
        return ['ok' => false, 'msg' => 'O cupom só pode ser usado em compras com valor acima de zero.'];
    }

    $stmt = $pdo->prepare('SELECT * FROM e5_coupons WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return ['ok' => false, 'msg' => 'Cupom inválido. Confira o código e tente novamente.'];
    }

    $today = date('Y-m-d');
    if ((int) $coupon['active'] !== 1) {
        return ['ok' => false, 'msg' => 'Este cupom não está mais ativo.'];
    }
    if (!empty($coupon['valid_from']) && $coupon['valid_from'] > $today) {
        return ['ok' => false, 'msg' => 'Este cupom ainda não está válido.'];
    }
    if (!empty($coupon['valid_until']) && $coupon['valid_until'] < $today) {
        return ['ok' => false, 'msg' => 'Este cupom expirou.'];
    }
    if ((float) $coupon['min_amount'] > $baseAmount) {
        return [
            'ok' => false,
            'msg' => 'Este cupom exige um pedido mínimo de R$ ' . number_format((float) $coupon['min_amount'], 2, ',', '.') . '.',
        ];
    }
    if ((int) $coupon['max_uses'] > 0 && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
        return ['ok' => false, 'msg' => 'Este cupom já atingiu o limite de usos.'];
    }

    // ---------------------------------------------------------------
    // Segmento do cliente (customer_scope): all | new | vip
    // ---------------------------------------------------------------
    if ($userId !== null && $coupon['customer_scope'] !== 'all') {
        $orders = $pdo->prepare('SELECT COUNT(*) AS total_orders, COALESCE(SUM(o.total), 0) AS total_spent FROM e5_orders o WHERE o.user_id = :uid AND o.status NOT IN (\'canceled\', \'refunded\')');
        $orders->execute([':uid' => $userId]);
        $stats = $orders->fetch();

        $isNew = (int) ($stats['total_orders'] ?? 0) === 0;
        $vipThreshold = (float) (function_exists('store_config') ? (store_config('vip_spend_threshold') ?? 2000) : 2000);
        $isVip = (float) ($stats['total_spent'] ?? 0) >= $vipThreshold;

        if ($coupon['customer_scope'] === 'new' && !$isNew) {
            return ['ok' => false, 'msg' => 'Este cupom é exclusivo para clientes novos (primeira compra).'];
        }
        if ($coupon['customer_scope'] === 'vip' && !$isVip) {
            return ['ok' => false, 'msg' => 'Este cupom é exclusivo para clientes VIP.'];
        }
    }

    if ($coupon['type'] === 'percent') {
        $discount = round($baseAmount * ((float) $coupon['value'] / 100), 2);
        if (!empty($coupon['max_discount']) && (float) $coupon['max_discount'] > 0) {
            $discount = min($discount, (float) $coupon['max_discount']);
        }
    } else {
        $discount = (float) $coupon['value'];
    }

    $discount = min(max(0, $discount), $baseAmount);

    return [
        'ok'       => true,
        'code'     => $coupon['code'],
        'discount' => round($discount, 2),
    ];
}

/**
 * Incrementa o contador de usos de um cupom (chamado ao confirmar o pedido).
 */
function couponMarkUsed(PDO $pdo, string $code): void
{
    $stmt = $pdo->prepare('UPDATE e5_coupons SET used_count = used_count + 1 WHERE code = :code');
    $stmt->execute([':code' => mb_strtoupper(trim($code))]);
}