<?php
/**
 * Security and Encryption Utilities
 *
 * Criptografia: AES-256-GCM com chave de arquivo (melhor prática).
 * Dados antigos criptografados com chave derivada (legacy) são lidos
 * automaticamente via fallback em loadEncryptedSetting().
 */

/**
 * Obtém ou gera chave de criptografia persistente (32 bytes aleatórios).
 * Armazenada em arquivo oculto .encryption_key na raiz do projeto.
 * Protegido via .htaccess (acesso HTTP bloqueado).
 */
function getEncryptionKey(): string
{
    $keyFile = __DIR__ . '/../.encryption_key';

    if (file_exists($keyFile)) {
        $key = file_get_contents($keyFile);
        if ($key !== false && strlen($key) === 32) {
            return $key;
        }
    }

    $key = random_bytes(32);
    file_put_contents($keyFile, $key);
    if (function_exists('chmod')) {
        @chmod($keyFile, 0600);
    }
    return $key;
}

/**
 * Deriva chave legacy (compatibilidade com dados existentes no banco).
 * SHA-256 de senha hardcoded + setting_key + salt — mantido SOMENTE para
 * ler registros antigos. Novos dados usam getEncryptionKey().
 */
function generateLegacyKey(string $password, string $salt): string
{
    return hash('sha256', $password . $salt, true);
}

/**
 * Criptografa com chave do arquivo.
 */
function secureEncrypt(string $plaintext, string $key): string
{
    if (strlen($key) !== 32) {
        throw new RuntimeException('Encryption key must be 32 bytes');
    }
    $iv = random_bytes(16);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Descriptografa com chave do arquivo.
 */
function secureDecrypt(string $encrypted, string $key): ?string
{
    if (strlen($key) !== 32) return null;
    $decoded = base64_decode($encrypted);
    if ($decoded === false || strlen($decoded) < 32) return null;
    $iv = substr($decoded, 0, 16);
    $tag = substr($decoded, 16, 16);
    $data = substr($decoded, 32);
    $result = openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $result !== false ? $result : null;
}

/**
 * Salva setting criptografado (chave do arquivo = novo padrão).
 */
function saveEncryptedSetting(PDO $pdo, $setting_key, $value): bool
{
    try {
        $key = getEncryptionKey();
        $encrypted = secureEncrypt($value, $key);

        $stmt_insert = $pdo->prepare(
            'INSERT INTO e5_encrypted_settings (setting_key, encrypted_value, encryption_version)
             VALUES (:key, :encrypt, :ver)'
        );
        $stmt_insert->execute([':key' => $setting_key, ':encrypt' => $encrypted, ':ver' => 'v2']);

        return true;
    } catch (Throwable $e) {
        try {
            $key = getEncryptionKey();
            $encrypted = secureEncrypt($value, $key);
            $stmt_update = $pdo->prepare(
                'UPDATE e5_encrypted_settings SET encrypted_value = :encrypt, encryption_version = :ver
                 WHERE setting_key = :key'
            );
            return $stmt_update->execute([':key' => $setting_key, ':encrypt' => $encrypted, ':ver' => 'v2']);
        } catch (Throwable $e2) {
            error_log('saveEncryptedSetting failed: ' . $e2->getMessage());
            return false;
        }
    }
}

/**
 * Lê e descriptografa setting.
 * Tenta: 1) chave do arquivo (v2) → 2) chave legacy derivada (v1) → null.
 */
function loadEncryptedSetting(PDO $pdo, $setting_key)
{
    $stmt = $pdo->prepare('SELECT encrypted_value, encryption_version FROM e5_encrypted_settings WHERE setting_key = :key');
    $stmt->execute([':key' => $setting_key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return null;

    $version = $row['encryption_version'] ?? 'v1';
    $encrypted = $row['encrypted_value'];

    // 1) Tenta com chave do arquivo (padrão v2)
    if ($version === 'v2') {
        try {
            $result = secureDecrypt($encrypted, getEncryptionKey());
            if ($result !== null) return $result;
        } catch (Throwable $e) {
            error_log('loadEncryptedSetting v2 failed: ' . $e->getMessage());
        }
    }

    // 2) Fallback: chave legacy derivada (v1) — compatibilidade com dados existentes
    try {
        $legacyKey = generateLegacyKey('!@#SDF$%lkjhgfd' . $setting_key . '&*()', 'royaltech_salt');
        $decoded = base64_decode($encrypted);
        if ($decoded !== false && strlen($decoded) >= 32) {
            $iv = substr($decoded, 0, 16);
            $tag = substr($decoded, 16, 16);
            $data = substr($decoded, 32);
            $result = openssl_decrypt($data, 'aes-256-gcm', $legacyKey, OPENSSL_RAW_DATA, $iv, $tag);
            if ($result !== false) return $result;
        }
    } catch (Throwable $e) {
        error_log('loadEncryptedSetting legacy failed: ' . $e->getMessage());
    }

    return null;
}

/**
 * Validate CNPJ with official checksum algorithm
 * Returns bool for backward compatibility
 */
function validateCNPJ(string $cnpj): bool
{
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) !== 14) {
        return false;
    }
    
    $cnpjBody = substr($cnpj, 0, 12);
    
    $sum = 0;
    $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$cnpjBody[$i] * $weights[$i];
    }
    $remainder = $sum % 11;
    $d1 = $remainder < 2 ? 0 : 11 - $remainder;
    if ((int)$cnpj[12] !== $d1) return false;
    
    $sum = 0;
    $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    for ($i = 0; $i < 13; $i++) {
        $sum += (int)$cnpj[$i] * $weights[$i];
    }
    $remainder = $sum % 11;
    $d2 = $remainder < 2 ? 0 : 11 - $remainder;
    if ((int)$cnpj[13] !== $d2) return false;
    
    if (in_array($cnpj, [
        '00000000000000','11111111111111','22222222222222',
        '33333333333333','44444444444444','55555555555555',
        '66666666666666','77777777777777','88888888888888',
        '99999999999999'
    ])) {
        return false;
    }
    
    return true;
}

/**
 * Format CNPJ for display
 */
function formatCNPJ(string $cnpj): string
{
    $cnpj = preg_replace('/\D/', '', $cnpj);
    if (strlen($cnpj) !== 14) return $cnpj;
    return sprintf('%s.%s.%s/%s-%s',
        substr($cnpj,0,2), substr($cnpj,2,3), substr($cnpj,5,3),
        substr($cnpj,8,4), substr($cnpj,12,2));
}

/**
 * Format CPF for display
 */
function formatCPF(string $cpf): string
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11) return $cpf;
    return sprintf('%s.%s.%s-%s',
        substr($cpf,0,3), substr($cpf,3,3), substr($cpf,6,3), substr($cpf,9,2));
}

/**
 * Sanitize document input (digits only)
 */
function sanitizeDocument(string $document): string
{
    return preg_replace('/\D/', '', $document);
}

/**
 * Get client IP address
 */
function getClientIP(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = explode(',', $_SERVER[$h])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Log regime/gateway change to e5_system_change_log
 */
function logRegimeChange(
    PDO $pdo, int $userId, string $oldRegime, string $newRegime,
    bool $success, ?string $errorMessage = null, ?string $cnpj = null
): void {
    try {
        $stmt = $pdo->prepare('
            INSERT INTO e5_system_change_log
            (change_type, user_id, regime_anterior, regime_novo, ip_address, user_agent, success, error_message, cnpj)
            VALUES (:type, :user_id, :old, :new, :ip, :ua, :success, :error, :cnpj)
        ');
        $stmt->execute([
            ':type' => 'regime',
            ':user_id' => $userId,
            ':old' => $oldRegime,
            ':new' => $newRegime,
            ':ip' => getClientIP(),
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ':success' => $success ? 1 : 0,
            ':error' => $errorMessage,
            ':cnpj' => $cnpj,
        ]);
    } catch (Throwable $e) {
        error_log('logRegimeChange failed: ' . $e->getMessage());
    }
}

/**
 * Require admin authentication
 */
function requireAdmin(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Acesso negado.');
    }
}

/**
 * Check if current user is admin
 */
function isAdmin(): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}
