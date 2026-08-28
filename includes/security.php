<?php
/**
 * Security and Encryption Utilities
 * 
 * NOTE: Uses OpenSSL AES-256-GCM encryption due to unavailable Sodium extension.
 * All cryptographic operations use 32-byte keys and authenticated encryption.
 */

// Generate a 32-byte key from password using SHA256 (compatible with OpenSSL)
function generateMasterKey(string $password, string $salt): string
{
    $key = hash('sha256', $password . $salt, true);
    if (strlen($key) !== 32) {
        throw new RuntimeException('Failed to generate key');
    }
    return $key;
}

/**
 * Save encrypted setting to e5_encrypted_settings
 */
function saveEncryptedSetting(PDO $pdo, $setting_key, $value): bool
{
    try {
        $key = generateMasterKey('!@#SDF$%lkjhgfd' . $setting_key . '&*()', 'royaltech_salt');
        $iv = random_bytes(16);
        $tag = '';
        
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($encrypted === false) return false;
        
        $iv_tag_data = $iv . $tag . $encrypted;
        $encrypted_value = base64_encode($iv_tag_data);
        
        // First try to insert
        $stmt_insert = $pdo->prepare('INSERT INTO e5_encrypted_settings (setting_key, encrypted_value) VALUES (:key, :encrypt)');
        $stmt_insert->execute([
            ':key' => $setting_key,
            ':encrypt' => $encrypted_value
        ]);
        
        return true;
    } catch (Throwable $e) {
        // If insert failed, try update instead
        $stmt_update = $pdo->prepare('UPDATE e5_encrypted_settings SET encrypted_value = :encrypt WHERE setting_key = :key');
        return $stmt_update->execute([
            ':key' => $setting_key,
            ':encrypt' => $encrypted_value
        ]);
    }
}

/**
 * Load and decrypt encrypted setting
 */
function loadEncryptedSetting(PDO $pdo, $setting_key)
{
    $stmt = $pdo->prepare('SELECT encrypted_value FROM e5_encrypted_settings WHERE setting_key = :key');
    $stmt->execute([':key' => $setting_key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) return null;
    
    try {
        $decoded = base64_decode($row['encrypted_value']);
        if ($decoded === false) return null;
        
        $iv_len = 16;
        if (strlen($decoded) < $iv_len * 2) return null;
        
        $iv = substr($decoded, 0, $iv_len);
        $tag = substr($decoded, $iv_len, 16);
        $data = substr($decoded, $iv_len + 16);
        
        $key = generateMasterKey('!@#SDF$%lkjhgfd' . $setting_key . '&*()', 'royaltech_salt');
        $plaintext = openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        return $plaintext !== false ? $plaintext : null;
    } catch (Throwable $e) {
        error_log('loadEncryptedSetting failed: ' . $e->getMessage());
        return null;
    }
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
