<?php
/**
 * Coupon System
 *
 * Valida, aplica e registra cupons de desconto.
 * Tipos: percentual (ex: 10%) ou fixo (ex: R$ 25,00).
 * Regras: validade, uso máximo, ativo.
 */

require_once __DIR__ . '/config.php';

/**
 * Validar e retornar dados de um cupom.
 *
 * @return array ['valid' => bool, 'message' => string, 'coupon' => ?array]
 */
function couponValidate(PDO $pdo, string $code): array
{
    $code = strtoupper(trim($code));
    if (empty($code)) {
        return ['valid' => false, 'message' => 'Informe um código de cupom.', 'coupon' => null];
    }

    $stmt = $pdo->prepare('SELECT * FROM e5_coupons WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return ['valid' => false, 'message' => 'Cupom não encontrado.', 'coupon' => null];
    }

    if (!$coupon['is_active']) {
        return ['valid' => false, 'message' => 'Este cupom está desativado.', 'coupon' => null];
    }

    if ($coupon['expires_at'] !== null && strtotime($coupon['expires_at']) < time()) {
        return ['valid' => false, 'message' => 'Este cupom expirou.', 'coupon' => null];
    }

    if ($coupon['max_uses'] !== null && $coupon['uses_current'] >= $coupon['max_uses']) {
        return ['valid' => false, 'message' => 'Este cupom atingiu o número máximo de usos.', 'coupon' => null];
    }

    return ['valid' => true, 'message' => 'Cupom aplicado com sucesso!', 'coupon' => $coupon];
}

/**
 * Calcular o valor do desconto de um cupom.
 *
 * @param array  $coupon   Linha da tabela e5_coupons
 * @param float  $subtotal Subtotal do pedido (sem frete)
 * @return float Valor do desconto (R$)
 */
function couponCalculateDiscount(array $coupon, float $subtotal): float
{
    if ($coupon['discount_type'] === 'percentage') {
        $discount = $subtotal * ($coupon['discount_value'] / 100);
    } else {
        $discount = min((float) $coupon['discount_value'], $subtotal);
    }
    return round($discount, 2);
}

/**
 * Incrementar o contador de usos do cupom (após pedido confirmado).
 */
function couponIncrementUsage(PDO $pdo, int $couponId): bool
{
    $stmt = $pdo->prepare('UPDATE e5_coupons SET uses_current = uses_current + 1 WHERE id = :id');
    return $stmt->execute([':id' => $couponId]);
}
