<?php
// =============================================================================
// Nota Fiscal (simulação) — numeração sequencial em e5_settings e chave de
// acesso no padrão NF-e (44 dígitos, com dígito verificador mod-11).
// =============================================================================

function nfNextNumber(): string
{
    if (!isset($GLOBALS['pdo'])) {
        include_once dirname(__DIR__) . '/database/connection.php';
    }
    try {
        $GLOBALS['pdo']->exec("UPDATE e5_settings SET setting_value = COALESCE(setting_value, '0') + 1 WHERE setting_key = 'nf_counter'");
        $counter = (int) $GLOBALS['pdo']->query("SELECT setting_value FROM e5_settings WHERE setting_key = 'nf_counter'")->fetchColumn();
    } catch (Throwable $e) {
        $counter = random_int(1, 999999999);
    }
    return str_pad((string) $counter, 9, '0', STR_PAD_LEFT);
}

// Chave de acesso fictícia com 43 dígitos + DV (módulo 11).
function nfAccessKey(): string
{
    $digits = '';
    for ($i = 0; $i < 43; $i++) {
        $digits .= random_int(0, 9);
    }
    $weight = 2;
    $sum = 0;
    for ($i = 42; $i >= 0; $i--) {
        $sum += (int) $digits[$i] * $weight;
        $weight = $weight === 9 ? 2 : $weight + 1;
    }
    $dv = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);
    return $digits . $dv;
}

// Garante que o pedido tenha NF emitida (idempotente). Retorna os dados da NF.
function nfEnsure($pdo, int $orderId): array
{
    $stmt = $pdo->prepare('SELECT nf_number, nf_key, nf_emitted_at FROM e5_orders WHERE id = :id');
    $stmt->execute([':id' => $orderId]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'message' => 'Pedido não encontrado.'];

    if (!empty($row['nf_number'])) {
        return [
            'ok' => true,
            'nf_number' => $row['nf_number'],
            'nf_key' => $row['nf_key'],
            'nf_emitted_at' => $row['nf_emitted_at'],
            'fresh' => false,
        ];
    }

    $number = nfNextNumber();
    $key = nfAccessKey();
    $pdo->prepare('UPDATE e5_orders SET nf_number = :n, nf_key = :k, nf_emitted_at = NOW() WHERE id = :id')
        ->execute([':n' => $number, ':k' => $key, ':id' => $orderId]);
    return [
        'ok' => true,
        'nf_number' => $number,
        'nf_key' => $key,
        'nf_emitted_at' => date('Y-m-d H:i:s'),
        'fresh' => true,
    ];
}