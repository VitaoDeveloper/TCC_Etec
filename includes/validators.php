<?php

declare(strict_types=1);

/**
 * Validadores e mascaras do cadastro de conta.
 *
 * Funcoes puras, sem dependencia de banco nem de $_POST: sao o nucleo
 * testavel do "Meu Perfil" e sao usadas tanto pelo PHP (confianca) quanto
 * espelhadas em JS (feedback imediato sem round-trip).
 *
 * Regra que vale para todo este arquivo: a validacao de formato e a
 * checagem de unicidade sao coisas separadas. Aqui so o formato; a
 * unicidade (CPF, e-mail, username) e consultada no banco pela API, porque
 * um arquivo nao tem acesso ao PDO.
 */

/** Remove tudo que nao for digito. */
function only_digits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

// =====================================================================
// CPF
// =====================================================================

/**
 * Valida os dois digitos verificadores do CPF.
 *
 * Rejeita tambem as "CPFs de preenchimento" (000..., 111..., 222...),
 * que passam no calculo dos digitos mas nao existem na Receita Federal.
 */
function is_valid_cpf(?string $cpf): bool
{
    $digits = only_digits($cpf);

    if (strlen($digits) !== 11) {
        return false;
    }

    // Sequencias de digitos repetidos sao o erro de digitacao mais comum
    // e nenhuma delas e um CPF real.
    if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int) $digits[$i] * (($t + 1) - $i);
        }
        $check = ((10 * $sum) % 11) % 10;

        if ((int) $digits[$t] !== $check) {
            return false;
        }
    }

    return true;
}

/** 12345678909 -> "123.456.789-09" */
function format_cpf(?string $cpf): string
{
    $d = only_digits($cpf);
    if (strlen($d) !== 11) {
        return (string) $cpf;
    }
    return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

// =====================================================================
// CEP
// =====================================================================

/** Exactly 8 digitos. A faixa 00000-000 nao existe. */
function is_valid_cep(?string $cep): bool
{
    $d = only_digits($cep);
    return strlen($d) === 8 && $d !== '00000000';
}

/** 12053831 -> "12053-831" */
function format_cep(?string $cep): string
{
    $d = only_digits($cep);
    if (strlen($d) !== 8) {
        return (string) $cep;
    }
    return substr($d, 0, 5) . '-' . substr($d, 5, 3);
}

// =====================================================================
// Telefone
// =====================================================================

/** DDDs validos (11-99). A faixa 00 nao existe; 1x e 2x sao-deployados. */
function is_valid_ddd(int $ddd): bool
{
    return $ddd >= 11 && $ddd <= 99;
}

/**
 * Telefone brasileiro: DDD + 8 (fixo) ou 9 (celular, com o 9 na frente).
 * Aceita com ou sem o codigo 55 e devolve sempre so os digitos nacionais.
 *
 * O fixo volta com 10 digitos, sem inventar o "9" da frente. Acrescentar
 * o 9 a um fixo faria (12) 3456-7890 aparecer como (12) 93456-7890 — um
 * celular que o cliente nunca digitou — e a proxima gravacao persistiria
 * essa numero falso.
 */
function normalize_phone(?string $phone): string
{
    $d = only_digits($phone);

    if (strlen($d) === 13 || strlen($d) === 12) { // com 55
        $d = substr($d, -11);
    }

    if (!is_valid_ddd((int) substr($d, 0, 2))) {
        return '';
    }

    $subscriber = substr($d, 2);

    // 9 = celular, 8 = fixo. Qualquer outro tamanho e entrada invalida.
    if (strlen($subscriber) !== 9 && strlen($subscriber) !== 8) {
        return '';
    }

    return substr($d, 0, 2) . $subscriber;
}

/** (11) 99999-9999 ou (11) 3333-4444. */
function format_phone(?string $phone): string
{
    $d = only_digits($phone);
    if (strlen($d) === 13) {
        $d = substr($d, -11);
    }

    $ddd = substr($d, 0, 2);
    $rest = substr($d, 2);

    if (strlen($rest) === 9) {              // (11) 99999-9999
        return '(' . $ddd . ') ' . substr($rest, 0, 5) . '-' . substr($rest, 5);
    }
    if (strlen($rest) === 8) {              // (11) 9999-9999
        return '(' . $ddd . ') ' . substr($rest, 0, 4) . '-' . substr($rest, 4);
    }

    // Não é um telefone recognise: devolve como veio, para o campo
    // mostrar o que o utilizador digitou em vez de um resto de máscara.
    return (string) $phone;
}

// =====================================================================
// Username
// =====================================================================

/** [a-z0-9._-], 3-30 chars, sem ponto/underscore/hifen nas pontas. */
function is_valid_username(?string $username): bool
{
    $u = (string) $username;
    if ($u === '' || strlen($u) < 3 || strlen($u) > 30) {
        return false;
    }
    return preg_match('/^[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/', $u) === 1;
}

// =====================================================================
// E-mail
// =====================================================================

function is_valid_email(?string $email): bool
{
    $e = trim((string) $email);
    if ($e === '' || strlen($e) > 120 || mb_strlen($e) > 120) {
        return false;
    }
    return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

// =====================================================================
// UF
// =====================================================================

/** Lista completa das 27 unidades federativas (inclui DF). */
function is_valid_uf(?string $uf): bool
{
    $u = strtoupper(trim((string) $uf));
    $ufs = [
        'AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT',
        'PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO',
    ];
    return in_array($u, $ufs, true);
}

// =====================================================================
// Cartao de credito
// =====================================================================

/** Luhn. Usado para validar o numero ANTES de qualquer coisa. */
function is_valid_card_number(?string $number): bool
{
    $d = only_digits($number);

    if (strlen($d) < 13 || strlen($d) > 19) {
        return false;
    }

    $sum = 0;
    $double = false;
    for ($i = strlen($d) - 1; $i >= 0; $i--) {
        $digit = (int) $d[$i];
        if ($double) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $double = !$double;
    }

    return $sum % 10 === 0;
}

/**
 * Bandeira pelos prefixos (ISO/IEC 7812). Devolve 'others' quando nao
 * casa com nenhuma regra conhecida.
 */
function detect_card_brand(?string $number): string
{
    $d = only_digits($number);
    if ($d === '') {
        return 'others';
    }

    // Elo e Hipercard sao testados ANTES do Visa/Mastercard: varios
    // prefixos brasileiros caem dentro da faixa do Visa (4xxx) e do
    // MasterCard (50xx/51xx..55xx) e seriam classificados errado.
    $six   = substr($d, 0, 6);
    $four  = substr($d, 0, 4);
    $three = substr($d, 0, 3);
    $two   = substr($d, 0, 2);

    // Hipercard: prefixo unico 606282.
    if ($six === '606282') {
        return 'hipercard';
    }

    // Elo: coeficientes binarios 4011, 4312, 4389, 4514, 4573, 5041,
    // 5066, 5090, 6277, 6362, 6363, 6500-6507, 6510-6511.
    $eloFour = ['4312', '4389', '4514', '4573', '5041', '5066', '5090', '6277', '6362', '6363'];
    if (in_array($four, $eloFour, true)) {
        return 'elo';
    }
    $eloThree = ['650', '651'];
    if (in_array($three, $eloThree, true)) {
        // 6500-6511 sao Elo; 6520+ ja e Elo Internacional / outros.
        $group = (int) substr($d, 3, 2);
        if ($group <= 11) {
            return 'elo';
        }
    }

    // Visa: comeca em 4.
    if (str_starts_with($d, '4')) {
        return 'visa';
    }
    // MasterCard: 51-55 e a faixa 2221-2720.
    if (preg_match('/^5[1-5]/', $d) === 1) {
        return 'mastercard';
    }
    $range = (int) substr($d, 0, 4);
    if ($range >= 2221 && $range <= 2720) {
        return 'mastercard';
    }
    // American Express: 34 / 37.
    if ($two === '34' || $two === '37') {
        return 'amex';
    }
    // Diners: 300-305, 36, 38.
    if (preg_match('/^3(0[0-5]|6|8)/', $d) === 1) {
        return 'diners';
    }
    // Discover: 6011 e 65.
    if ($four === '6011' || $two === '65') {
        return 'discover';
    }

    return 'others';
}

/** Rotulo legivel da bandeira. */
function card_brand_label(string $brand): string
{
    return [
        'visa'       => 'Visa',
        'mastercard' => 'Mastercard',
        'amex'       => 'American Express',
        'elo'        => 'Elo',
        'hipercard'  => 'Hipercard',
        'diners'     => 'Diners',
        'discover'   => 'Discover',
    ][$brand] ?? 'Cartão';
}

/**
 * Validade: mes 1-12 e ano no futuro. "09/29" e o formato usado no card.
 */
function is_valid_card_expiry(?string $expiry, ?int $referenceYear = null, ?int $referenceMonth = null): bool
{
    $e = trim((string) $expiry);
    if (preg_match('/^(0[1-9]|1[0-2])\s*\/\s*(\d{2}|\d{4})$/', $e, $m) !== 1) {
        return false;
    }

    $month = (int) $m[1];
    $year  = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];

    $refYear  = $referenceYear  ?? (int) date('Y');
    $refMonth = $referenceMonth ?? (int) date('n');

    // Compara o par (ano, mes) para nao rejeitar o mes corrente.
    if ($year > $refYear) {
        return true;
    }
    if ($year === $refYear && $month >= $refMonth) {
        return true;
    }

    return false;
}

/** Ultimos 4 digitos — o unico pedaco do numero que o projeto guarda. */
function card_last_four(?string $number): string
{
    return substr(only_digits($number), -4);
}

/** "Visa •••• 4242 · vence 08/29" */
function card_display(string $brand, string $lastFour, int $expMonth, int $expYear): string
{
    return card_brand_label($brand) . ' •••• ' . $lastFour
        . ' · vence ' . str_pad((string) $expMonth, 2, '0', STR_PAD_LEFT)
        . '/' . substr((string) $expYear, -2);
}

// =====================================================================
// Documentos / texto
// =====================================================================

/** Remove espacos extras e colapsa sequencias de branco. */
function clean_text(?string $value): string
{
    $v = preg_replace('/\s+/u', ' ', (string) $value) ?? '';
    return trim($v);
}

/** Corta no limite de bytes do VARCHAR sem quebrar um caractere UTF-8. */
function limit_text(?string $value, int $max): string
{
    $v = (string) $value;
    if (mb_strlen($v, 'UTF-8') <= $max) {
        return $v;
    }
    return mb_substr($v, 0, $max, 'UTF-8');
}

/** Numero do pedido sempre com 4 digitos: 12 -> "0012". */
function order_reference(int|string|null $id): string
{
    return str_pad((string) (int) $id, 4, '0', STR_PAD_LEFT);
}
