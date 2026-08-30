<?php
/**
 * Transactional MEI Activation with Health Checks
 * 
 * Purpose: Validate all external integrations BEFORE persisting regime change
 * Prevents "activated but broken" state
 */

require_once __DIR__ . '/security.php';

/**
 * Health check: Test NF-e provider API connectivity
 * 
 * @param string $provider 'focus' or 'nfeio'
 * @param string $apiKey API key to test
 * @param string $environment 'homologacao' or 'producao'
 * @return array ['success' => bool, 'message' => string]
 */
function healthCheckNFeProvider(string $provider, string $apiKey, string $environment): array
{
    if (empty($apiKey)) {
        return ['success' => false, 'message' => 'Chave API não fornecida.'];
    }
    
    if ($provider === 'focus') {
        return healthCheckFocusNFe($apiKey, $environment);
    } elseif ($provider === 'nfeio') {
        return healthCheckNFeIO($apiKey, $environment);
    }
    
    return ['success' => false, 'message' => 'Provedor desconhecido: ' . $provider];
}

/**
 * Health check for Focus NFe API
 * 
 * IMPORTANT: 
 * - Sandbox: POST /v2/nfe validates tokens (returns 401 for invalid)
 * - Production: GET /v2/empresas validates tokens (returns 401 for invalid)
 * - GET /v2/empresas in sandbox returns 404 for ALL tokens (does NOT validate)
 */
function healthCheckFocusNFe(string $apiKey, string $environment): array
{
    if ($environment === 'homologacao') {
        // Sandbox: POST /v2/nfe validates tokens properly
        $baseUrl = 'https://homologacao.focusnfe.com.br';
        $endpoint = '/v2/nfe';
        $method = 'POST';
        $payload = json_encode(['ref' => 'health_check_' . time()]);
    } else {
        // Production: GET /v2/empresas validates tokens
        $baseUrl = 'https://api.focusnfe.com.br';
        $endpoint = '/v2/empresas';
        $method = 'GET';
        $payload = null;
    }
    
    $ch = curl_init($baseUrl . $endpoint);
    $opts = [
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($apiKey . ':'),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $payload;
    }
    
    curl_setopt_array($ch, $opts);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseBody = curl_multi_getcontent($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'message' => 'Focus NFe: Erro de conexão: ' . $curlError,
        ];
    }
    
    // HTTP 401 = token definitely invalid
    if ($httpCode === 401) {
        return [
            'success' => false,
            'message' => 'Focus NFe: Chave API inválida ou expirada. Verifique sua credencial.',
        ];
    }
    
    // HTTP 200 = token valid
    if ($httpCode === 200) {
        $envLabel = $environment === 'homologacao' ? 'sandbox' : 'produção';
        return [
            'success' => true,
            'message' => 'Focus NFe: Chave API validada (' . $envLabel . ').',
        ];
    }
    
    // HTTP 403 = token valid but insufficient permissions
    if ($httpCode === 403) {
        return [
            'success' => false,
            'message' => 'Focus NFe: Chave API válida, mas sem permissões necessárias.',
        ];
    }
    
    // HTTP 422 = validation error (token valid, but request data invalid - expected for health check)
    if ($httpCode === 422) {
        $envLabel = $environment === 'homologacao' ? 'sandbox' : 'produção';
        return [
            'success' => true,
            'message' => 'Focus NFe: Chave API validada (' . $envLabel . ').',
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Focus NFe: Resposta inesperada HTTP ' . $httpCode . '.',
    ];
}

/**
 * Health check for NFe.io API
 */
function healthCheckNFeIO(string $apiKey, string $environment): array
{
    // NFe.io uses same URL for both environments (controlled by account settings)
    $baseUrl = 'https://api.nfe.io';
    
    $ch = curl_init($baseUrl . '/v1/companies');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'message' => 'Erro de conexão: ' . $curlError,
        ];
    }
    
    if ($httpCode === 401) {
        return [
            'success' => false,
            'message' => 'Token de acesso inválido ou expirado.',
        ];
    }
    
    if ($httpCode === 200) {
        return [
            'success' => true,
            'message' => 'Conexão com NFe.io validada com sucesso.',
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Resposta inesperada da API: HTTP ' . $httpCode,
    ];
}

/**
 * Health check: Test SuperFrete API connectivity
 * 
 * @param string $token API token to test
 * @return array ['success' => bool, 'message' => string]
 */
function healthCheckSuperFrete(string $token): array
{
    if (empty($token)) {
        return [
            'success' => false,
            'message' => 'Token não fornecido.',
        ];
    }
    
    // Test endpoint: shipping calculator (SP→SP, pacote padrão)
    $payload = [
        'from' => ['postal_code' => '01310100'],
        'to' => ['postal_code' => '01310100'],
        'services' => '1,2',
        'package' => ['height' => 10, 'width' => 20, 'length' => 30, 'weight' => 1],
        'options' => ['insurance_value' => 100],
    ];
    $ch = curl_init('https://sandbox.superfrete.com/api/v0/calculator');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'User-Agent: Royal Tech (royaltech.original@gmail.com)',
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'message' => 'Erro de conexão: ' . $curlError,
        ];
    }
    
    if ($httpCode === 401) {
        return [
            'success' => false,
            'message' => 'Token inválido ou expirado.',
        ];
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        
        $count = 0;
        if (is_array($data)) {
            foreach ($data as $srv) {
                if (!empty($srv['price']) && empty($srv['error'])) {
                    $count++;
                }
            }
        }
        
        return [
            'success' => true,
            'message' => 'Token válido. SuperFrete retornou ' . $count . ' opção(ões) de frete.',
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Resposta inesperada da API: HTTP ' . $httpCode,
    ];
}

/**
 * Activate MEI with full transactional validation
 * 
 * @param PDO $pdo Database connection
 * @param int $userId Admin user performing activation
 * @param array $data Activation data (cnpj, legal_name, etc)
 * @return array ['success' => bool, 'message' => string, 'errors' => array]
 */
function activateMEITransactional(PDO $pdo, int $userId, array $data): array
{
    $errors = [];
    $warnings = [];
    
    // 1. Validate CNPJ
    $cnpj = sanitizeDocument($data['cnpj'] ?? '');
    if (!validateCNPJ($cnpj)) {
        $errors[] = 'CNPJ inválido. Verifique o número e tente novamente.';
    }
    
    // 2. Validate required fields
    if (empty($data['legal_name'])) {
        $errors[] = 'Razão Social é obrigatória.';
    }
    
    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => 'Validação falhou.',
            'errors' => $errors,
        ];
    }
    
    // 3. Health checks for external services (if provided)
    $nfeProvider = $data['nfe_provider'] ?? 'disabled';
    $nfeApiKey = $data['nfe_api_key'] ?? '';
    $nfeEnvironment = $data['nfe_environment'] ?? 'homologacao';
    $superfreteToken = $data['superfrete_token'] ?? '';
    
    // Test NF-e provider if not disabled
    if ($nfeProvider !== 'disabled' && !empty($nfeApiKey)) {
        $nfeCheck = healthCheckNFeProvider($nfeProvider, $nfeApiKey, $nfeEnvironment);
        if (!$nfeCheck['success']) {
            $errors[] = 'NF-e: ' . $nfeCheck['message'];
        } else {
            $warnings[] = $nfeCheck['message'];
        }
    }
    
    // Test SuperFrete if token provided
    if (!empty($superfreteToken)) {
        $superfreteCheck = healthCheckSuperFrete($superfreteToken);
        if (!$superfreteCheck['success']) {
            $errors[] = 'SuperFrete: ' . $superfreteCheck['message'];
        } else {
            $warnings[] = $superfreteCheck['message'];
            if (isset($superfreteCheck['warning'])) {
                $warnings[] = $superfreteCheck['warning'];
            }
        }
    }
    
    // Stop if health checks failed
    if (!empty($errors)) {
        logRegimeChange($pdo, $userId, 'CPF', 'MEI', false, implode('; ', $errors), $cnpj);
        
        return [
            'success' => false,
            'message' => 'Ativação bloqueada por falhas de validação.',
            'errors' => $errors,
        ];
    }
    
    // 4. Begin transaction
    try {
        $pdo->beginTransaction();
        
        // Lock seller_profile row to prevent race condition
        $stmt = $pdo->prepare('SELECT id FROM e5_seller_profile WHERE is_active = 1 FOR UPDATE');
        $stmt->execute();
        
        // Update seller profile
        $stmt = $pdo->prepare('
            UPDATE e5_seller_profile 
            SET document_type = :dtype,
                document_number = :doc,
                legal_name = :legal,
                trade_name = :trade,
                state_registration = :state_reg,
                tax_regime = :regime,
                nfe_enabled = :nfe_enabled,
                updated_at = NOW()
            WHERE is_active = 1
        ');
        
        $stmt->execute([
            ':dtype' => 'CNPJ',
            ':doc' => formatCNPJ($cnpj),
            ':legal' => $data['legal_name'],
            ':trade' => $data['trade_name'] ?: null,
            ':state_reg' => $data['state_registration'] ?: null,
            ':regime' => 'MEI',
            ':nfe_enabled' => $nfeProvider !== 'disabled' ? 1 : 0,
        ]);
        
        // Update settings (non-sensitive)
        $settings = [
            'tax_regime' => 'MEI',
            'nfe_provider' => $nfeProvider,
            'nfe_environment' => $nfeEnvironment,
        ];
        
        $stmt = $pdo->prepare('
            INSERT INTO e5_settings (setting_key, setting_value)
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');
        
        foreach ($settings as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => $value]);
        }
        
        // Store encrypted credentials if provided
        if (!empty($nfeApiKey)) {
            saveEncryptedSetting($pdo, 'nfe_api_key', $nfeApiKey);
        }
        
        if (!empty($superfreteToken)) {
            saveEncryptedSetting($pdo, 'superfrete_token', $superfreteToken);
        }
        
        // Log successful activation
        logRegimeChange($pdo, $userId, 'CPF', 'MEI', true, null, $cnpj);
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'MEI ativado com sucesso!',
            'warnings' => $warnings,
            'errors' => [],
        ];
        
    } catch (Throwable $e) {
        $pdo->rollBack();
        
        $errorMsg = 'Erro ao ativar MEI: ' . $e->getMessage();
        error_log($errorMsg);
        
        logRegimeChange($pdo, $userId, 'CPF', 'MEI', false, $errorMsg, $cnpj);
        
        return [
            'success' => false,
            'message' => 'Erro ao persistir alterações no banco de dados.',
            'errors' => [$e->getMessage()],
        ];
    }
}

/**
 * Deactivate MEI (rollback to CPF)
 * 
 * @param PDO $pdo Database connection
 * @param int $userId Admin user performing deactivation
 * @return array ['success' => bool, 'message' => string]
 */
function deactivateMEITransactional(PDO $pdo, int $userId): array
{
    try {
        $pdo->beginTransaction();
        
        // Get current CNPJ for audit log
        $stmt = $pdo->query('SELECT document_number FROM e5_seller_profile WHERE is_active = 1 LIMIT 1');
        $currentCNPJ = $stmt->fetchColumn();
        
        // Revert seller profile
        $pdo->exec("
            UPDATE e5_seller_profile 
            SET tax_regime = 'CPF', 
                nfe_enabled = 0,
                document_type = 'CPF',
                updated_at = NOW()
            WHERE is_active = 1
        ");
        
        // Revert settings
        $stmt = $pdo->prepare('
            INSERT INTO e5_settings (setting_key, setting_value)
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');
        
        $settings = [
            'tax_regime' => 'CPF',
            'nfe_provider' => 'disabled',
        ];
        
        foreach ($settings as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => $value]);
        }
        
        // Log deactivation
        logRegimeChange($pdo, $userId, 'MEI', 'CPF', true, null, $currentCNPJ);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Regime alterado para CPF. Emissão de notas fiscais desativada.',
        ];
        
    } catch (Throwable $e) {
        $pdo->rollBack();
        
        $errorMsg = 'Erro ao desativar MEI: ' . $e->getMessage();
        error_log($errorMsg);
        
        logRegimeChange($pdo, $userId, 'MEI', 'CPF', false, $errorMsg, null);
        
        return [
            'success' => false,
            'message' => 'Erro ao reverter para CPF: ' . $e->getMessage(),
        ];
    }
}
