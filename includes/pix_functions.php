<?php
// =============================================================================
// Pix — geração de BR Code (padrão EMV® QRCPS-MPM do Banco Central).
//
// O código "Copia e Cola" é montado em TLV (id + tamanho + valor) e finalizado
// com o CRC16-CCITT (polinômio 0x1021, valor inicial 0xFFFF), exatamente como
// exigido pelo BR Code. O QR exibido ao cliente é gerado no navegador a partir
// deste mesmo payload.
// =============================================================================

// Campo TLV: id (2 dígitos) + tamanho (2 dígitos) + valor.
function pixEmvField(string $id, string $value): string
{
    return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

// CRC16-CCITT (0x1021) usado pelo BR Code.
function pixCrc16(string $payload): string
{
    $crc = 0xFFFF;
    $len = strlen($payload);
    for ($i = 0; $i < $len; $i++) {
        $crc ^= ord($payload[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

// Normaliza texto para o conjunto aceito pelo BR Code (ASCII maiúsculo).
function pixAscii(string $value): string
{
    $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($value === false) {
        $value = preg_replace('/[^\x20-\x7E]/', '', $value);
    }
    $value = strtoupper((string) $value);
    return trim(preg_replace('/[^A-Z0-9 .\-]/', '', $value));
}

// Trunca respeitando o limite do campo (evita cortar no meio de multibyte).
function pixLimit(string $value, int $max): string
{
    if (strlen($value) <= $max) {
        return $value;
    }
    return substr($value, 0, $max);
}

// Monta o BR Code. Chaves esperadas: key, name, city, amount, txid, description.
function pixBuildBrCode(array $opts): string
{
    $key = pixLimit(trim((string) ($opts['key'] ?? '')), 77);
    $name = pixLimit(pixAscii((string) ($opts['name'] ?? '')) ?: 'LOJA', 25);
    $city = pixLimit(pixAscii((string) ($opts['city'] ?? '')) ?: 'SAO PAULO', 15);
    $description = pixLimit(pixAscii((string) ($opts['description'] ?? '')), 72);
    $txid = preg_replace('/[^A-Za-z0-9]/', '', (string) ($opts['txid'] ?? ''));
    $txid = pixLimit($txid !== '' ? $txid : '***', 25);

    if ($key === '') {
        throw new InvalidArgumentException('Chave Pix não configurada.');
    }

    $merchantAccount = pixEmvField('00', 'br.gov.bcb.pix') . pixEmvField('01', $key);
    if ($description !== '') {
        $merchantAccount .= pixEmvField('02', $description);
    }

    $payload = pixEmvField('00', '01');   // Payload Format Indicator
    $payload .= pixEmvField('01', '12');  // 12 = uso único (one-time)
    $payload .= pixEmvField('26', $merchantAccount);
    $payload .= pixEmvField('52', '0000'); // Merchant Category Code
    $payload .= pixEmvField('53', '986');  // Moeda: BRL

    if (isset($opts['amount']) && (float) $opts['amount'] > 0) {
        $payload .= pixEmvField('54', number_format((float) $opts['amount'], 2, '.', ''));
    }

    $payload .= pixEmvField('58', 'BR');
    $payload .= pixEmvField('59', $name);
    $payload .= pixEmvField('60', $city);
    $payload .= pixEmvField('62', pixEmvField('05', $txid));

    $payload .= '6304'; // id + tamanho do CRC

    return $payload . pixCrc16($payload);
}
