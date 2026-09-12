<?php
// =============================================================================
// Cartões salvos do cliente (tabela e5_saved_cards).
// Nunca armazena dados completos do cartão — apenas bandeira, nome do titular,
// últimos 4 dígitos, validade e limite de parcelas.
// =============================================================================

function cardDetectBrand(string $number): string
{
    $n = preg_replace('/\D/', '', $number);
    if (preg_match('/^4/', $n)) return 'visa';
    if (preg_match('/^(5[1-5]|2[2-7])/', $n)) return 'mastercard';
    if (preg_match('/^3[47]/', $n)) return 'amex';
    if (preg_match('/^(4011|4312|4389|4514|4573|5041|5066|5067|509|6277|6362|6363|650|6516|6550)/', $n)) return 'elo';
    if (preg_match('/^(3841|60)/', $n)) return 'hipercard';
    return 'others';
}

function cardLuhnValid(string $number): bool
{
    $n = preg_replace('/\D/', '', $number);
    if (strlen($n) < 12 || strlen($n) > 19) return false;
    $sum = 0;
    $alt = false;
    for ($i = strlen($n) - 1; $i >= 0; $i--) {
        $d = (int) $n[$i];
        if ($alt) {
            $d *= 2;
            if ($d > 9) $d -= 9;
        }
        $sum += $d;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

function cardGetAll($pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM e5_saved_cards WHERE user_id = :u AND is_active = 1 ORDER BY id ASC');
    $stmt->execute([':u' => $userId]);
    return $stmt->fetchAll() ?: [];
}

// Salva um cartão a partir de dados de POST. Só guarda dados não sensíveis.
function cardSave($pdo, int $userId, array $in): array
{
    $number = preg_replace('/\D/', '', (string) ($in['card_number'] ?? ''));
    $holder = trim((string) ($in['holder_name'] ?? ''));
    $expMonth = (int) ($in['exp_month'] ?? 0);
    $expYear = (int) ($in['exp_year'] ?? 0);
    $maxInstallments = (int) ($in['max_installments'] ?? 12);
    $saveCard = !empty($in['save_card']);

    if ($number === '' || !cardLuhnValid($number)) {
        return ['ok' => false, 'message' => 'Número do cartão inválido. Verifique os dígitos.'];
    }
    if ($holder === '' || mb_strlen($holder) < 3) {
        return ['ok' => false, 'message' => 'Informe o nome como está no cartão.'];
    }
    $nowYear = (int) date('Y');
    $nowMonth = (int) date('m');
    if ($expMonth < 1 || $expMonth > 12 || $expYear < $nowYear - 1 || $expYear > $nowYear + 20) {
        return ['ok' => false, 'message' => 'Validade do cartão inválida (MM/AA).'];
    }
    if ($expYear < $nowYear || ($expYear === $nowYear && $expMonth < $nowMonth)) {
        return ['ok' => false, 'message' => 'Este cartão já está vencido. Use outro cartão.'];
    }
    if ($maxInstallments < 1 || $maxInstallments > 12) {
        return ['ok' => false, 'message' => 'Número de parcelas inválido (1 a 12).'];
    }

    try {
        if ($saveCard) {
            $stmt = $pdo->prepare(
                'INSERT INTO e5_saved_cards (user_id, card_brand, holder_name, last_four, exp_month, exp_year, max_installments)
                 VALUES (:u, :brand, :holder, :last4, :m, :y, :max)'
            );
            $stmt->execute([
                ':u' => $userId,
                ':brand' => cardDetectBrand($number),
                ':holder' => substr($holder, 0, 80),
                ':last4' => substr($number, -4),
                ':m' => $expMonth,
                ':y' => $expYear,
                ':max' => $maxInstallments,
            ]);
            return ['ok' => true, 'card_id' => (int) $pdo->lastInsertId(), 'message' => 'Cartão salvo com sucesso.'];
        }
        return ['ok' => true, 'message' => 'Cartão válido.'];
    } catch (Throwable $e) {
        error_log('Card save error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Erro ao salvar o cartão.'];
    }
}

function cardDelete($pdo, int $userId, int $cardId): array
{
    $stmt = $pdo->prepare('UPDATE e5_saved_cards SET is_active = 0 WHERE id = :id AND user_id = :u');
    $stmt->execute([':id' => $cardId, ':u' => $userId]);
    if ($stmt->rowCount() > 0) {
        return ['ok' => true, 'message' => 'Cartão removido.'];
    }
    return ['ok' => false, 'message' => 'Cartão não encontrado.'];
}