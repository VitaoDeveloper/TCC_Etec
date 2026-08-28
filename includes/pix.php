<?php
/**
 * PIX BR Code (EMV) Generator — Static PIX for manual transfer
 *
 * Gera o payload "copia-e-cola" e QR Code compatível com BR Code (Banco Central).
 * Chave PIX padrão: royaltech.original@gmail.com (configurável via store_config)
 *
 * Especificação: Manual do BR Code (Banco Central do Brasil) v2.10+
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment.php';

/**
 * Calcula CRC16-CCITT (polinômio 0x1021, init 0xFFFF, sem reflexão, sem XOR final)
 * Usado no campo 63 do BR Code.
 */
function pixCrc16Ccitt(string $data): string
{
    $poly = 0x1021;
    $crc = 0xFFFF;
    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $crc ^= (ord($data[$i]) << 8);
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = ($crc << 1) ^ $poly;
            } else {
                $crc <<= 1;
            }
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(sprintf('%04X', $crc));
}

/**
 * Formata campo EMV: ID (2 dígitos) + length (1 ou 2 dígitos) + value.
 * Conforme spec BCB BR Code:
 * - IDs com maxLen <= 9: length 1 dígito (00, 52, 53, 58, 63)
 * - IDs com maxLen >= 10: length 2 dígitos (01, 02, 05, 26, 54, 59, 60, 62)
 * Subcampos (dentro de 26 e 62) SEMPRE usam 2 dígitos.
 */
function pixEmvField(string $id, string $value, bool $isSubfield = false): string
{
    $len = strlen($value);
    if ($len === 0) return '';

    // IDs que usam length 1-dígito (maxLen <= 9)
    $oneDigitLenIDs = ['00', '52', '53', '58', '63'];

    if ($isSubfield || !in_array($id, $oneDigitLenIDs, true)) {
        // 2 dígitos (com leading zero se < 10)
        $lengthStr = sprintf('%02d', $len);
    } else {
        // 1 dígito
        $lengthStr = (string) $len;
    }
    return $id . $lengthStr . $value;
}

/**
 * Detecta tipo de chave PIX e retorna o campo 01 (chave) formatado.
 * Tipos suportados: e-mail, CPF, CNPJ, telefone, EVP (aleatória/UUID).
 */
function pixFormatKey(string $key): string
{
    $key = trim($key);
    if ($key === '') return '';

    // ID 01 é subfield dentro de MAI (26) -> sempre 2-digitos
    $subfield = true;

    // EVP (aleatória) — 32 hex chars com hífens
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
        return pixEmvField('01', $key, $subfield);
    }

    // E-mail
    if (str_contains($key, '@')) {
        return pixEmvField('01', $key, $subfield);
    }

    // Telefone: +55XXXXXXXXXX ou 55XXXXXXXXXX
    if (preg_match('/^\+?55\d{10,11}$/', $key)) {
        return pixEmvField('01', $key, $subfield);
    }

    // CPF (11 dígitos)
    if (preg_match('/^\d{11}$/', $key)) {
        return pixEmvField('01', $key, $subfield);
    }

    // CNPJ (14 dígitos)
    if (preg_match('/^\d{14}$/', $key)) {
        return pixEmvField('01', $key, $subfield);
    }

    // Fallback: trata como string genérica
    return pixEmvField('01', $key, $subfield);
}

/**
 * Sanitiza nome do recebedor (máx. 25 chars, ASCII permitido, sem acentos).
 */
function pixSanitizeName(string $name, int $maxLen = 25): string
{
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $name = preg_replace('/[^A-Za-z0-9\s\-\.]/', '', $name);
    $name = strtoupper(trim($name));
    if (mb_strlen($name) > $maxLen) {
        $name = mb_substr($name, 0, $maxLen);
    }
    return $name;
}

/**
 * Sanitiza cidade do recebedor (máx. 15 chars).
 */
function pixSanitizeCity(string $city, int $maxLen = 15): string
{
    $city = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $city);
    $city = preg_replace('/[^A-Za-z0-9\s\-]/', '', $city);
    $city = strtoupper(trim($city));
    if (mb_strlen($city) > $maxLen) {
        $city = mb_substr($city, 0, $maxLen);
    }
    return $city;
}

/**
 * Gera o BR Code "copia-e-cola" completo (com CRC16).
 *
 * @param float   $amount         Valor em reais (ex.: 123.45). Null para PIX estático sem valor.
 * @param string  $txid           TXID do pedido (ex.: "PEDIDO0001"). Deve ter <= 25 chars.
 * @param string  $pixKey         Chave PIX do recebedor.
 * @param string  $merchantName   Nome do recebedor (ex.: "ROYAL TECH").
 * @param string  $merchantCity   Cidade do recebedor (ex.: "SAO PAULO").
 * @param string|null $description Descrição adicional (campo 02 do Merchant Account Info).
 * @return string BR Code payload.
 */
function pixBuildBRCode(
    ?float $amount,
    string $txid,
    string $pixKey,
    string $merchantName = 'ROYAL TECH',
    string $merchantCity = 'SAO PAULO',
    ?string $description = null
): string {
    $payload = '';

    // 00 Payload Format Indicator (sempre "01")
    $payload .= pixEmvField('00', '01');

    // 26 Merchant Account Information (MAI)
    $mai = '';
    $mai .= pixEmvField('00', 'BR.GOV.BCB.PIX', true); // GUI (subfield)
    $mai .= pixFormatKey($pixKey);               // 01 Chave PIX (subfield, handled inside)
    if ($description !== null && $description !== '') {
        $desc = substr($description, 0, 99); // máx 99 chars no campo 02
        $mai .= pixEmvField('02', $desc, true); // subfield
    }
    $payload .= pixEmvField('26', $mai);

    // 52 Merchant Category Code (0000 = não especificado)
    $payload .= pixEmvField('52', '0000');

    // 53 Transaction Currency (986 = BRL)
    $payload .= pixEmvField('53', '986');

    // 54 Transaction Amount (opcional; se null, omite para PIX estático)
    if ($amount !== null && $amount >= 0) {
        $amountStr = sprintf('%.2f', $amount);
        $payload .= pixEmvField('54', $amountStr);
    }

    // 58 Country Code (BR)
    $payload .= pixEmvField('58', 'BR');

    // 59 Merchant Name
    $payload .= pixEmvField('59', pixSanitizeName($merchantName));

    // 60 Merchant City
    $payload .= pixEmvField('60', pixSanitizeCity($merchantCity));

    // 62 Additional Data Field (TXID no sub-campo 05)
    $add = pixEmvField('05', $txid, true); // subfield
    $payload .= pixEmvField('62', $add);

    // 63 CRC16 — calcular sobre tudo até aqui + "6304"
    $crcPayload = $payload . '6304';
    $crc = pixCrc16Ccitt($crcPayload);
    $payload .= '6304' . $crc;

    return $payload;
}

/**
 * Gera QR Code do BR Code como Data URI PNG (base64).
 * Requer chillerlan/php-qrcode (instalado via composer).
 *
 * @return string|null Data URI "data:image/png;base64,..." ou null se lib indisponível.
 */
function pixRenderQrDataUri(string $brCode, int $size = 512): ?string
{
    if (!class_exists(\chillerlan\QRCode\QRCode::class)) {
        return null;
    }

    try {
        $scale = max(1, (int)($size / 100));
        $options = new \chillerlan\QRCode\QROptions([
            'version' => 10, // BR Code pode ser longo; 10 garante capacidade
            'versionMax' => 40,
            'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => true,
            'scale' => $scale,
            'eccLevel' => \chillerlan\QRCode\QRCode::ECC_L,
            'quietzoneSize' => 4,
            'drawLightModules' => false,
            'connectPaths' => true,
        ]);

        $qr = new \chillerlan\QRCode\QRCode($options);
        $pngBase64 = $qr->render($brCode);

        return 'data:image/png;base64,' . $pngBase64;
    } catch (Throwable $e) {
        error_log('pixRenderQrDataUri: ' . $e->getMessage());
        return null;
    }
}

/**
 * Gera PIX completo para um pedido: payload BR Code + QR Data URI + expiração.
 *
 * @param float  $amount    Valor do pedido (sem taxa).
 * @param string $orderId   ID do pedido (ex.: "123").
 * @param array  $customer  Dados do cliente ['name' => ...].
 * @return array ['success'=>bool, 'data'=>['br_code','qr_data_uri','expires_at','pix_key','amount','txid']]
 */
function pixGenerateForOrder(float $amount, string $orderId, array $customer): array
{
    $config = paymentGetConfig();
    $pixKey = $config['pix_key'] ?? 'royaltech.original@gmail.com';

    if (empty($pixKey)) {
        return [
            'success' => false,
            'message' => 'Chave PIX não configurada.',
            'data' => null,
        ];
    }

    $txid = 'PED' . str_pad($orderId, 8, '0', STR_PAD_LEFT) . '_' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    if (strlen($txid) > 25) $txid = substr($txid, 0, 25);

    $merchantName = store_config('store_name') ?: 'ROYAL TECH';
    $merchantCity = 'SAO PAULO';

    $brCode = pixBuildBRCode($amount, $txid, $pixKey, $merchantName, $merchantCity, 'Pedido #' . $orderId);
    $qrDataUri = pixRenderQrDataUri($brCode);

    return [
        'success' => true,
        'message' => 'Código PIX gerado com sucesso.',
        'data' => [
            'pix_key' => $pixKey,
            'amount' => $amount,
            'order_id' => $orderId,
            'txid' => $txid,
            'br_code' => $brCode,
            'qr_data_uri' => $qrDataUri,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        ],
    ];
}

/**
 * Valida formato básico de um BR Code (tem CRC correto).
 * Útil para auditoria/log.
 */
function pixValidateBRCode(string $brCode): bool
{
    if (strlen($brCode) < 10) return false;
    // CRC field is "6304" + 4 hex chars = 8 chars at end
    // Payload for CRC is everything before "6304" + "6304"
    $payloadForCrc = substr($brCode, 0, -8);
    $crcProvided = substr($brCode, -4);
    $calc = pixCrc16Ccitt($payloadForCrc . '6304');
    return strtoupper($crcProvided) === $calc;
}

