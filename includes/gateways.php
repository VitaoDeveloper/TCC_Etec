<?php
/**
 * Multi-Gateway Payment Management
 * 
 * Purpose: Handle multiple payment gateways (Mercado Pago + Asaas) simultaneously
 * - Gateway activation/deactivation with health checks
 * - Session locking for checkout
 * - Webhook management for both gateways
 * - Gateway snapshot per order
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';

/**
 * Get all configured gateways
 */
function gatewayGetAll(): array
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once dirname(__DIR__) . '/database/connection.php';
        }
        
        $stmt = $GLOBALS['pdo']->query('
            SELECT g.*, 
                   CASE WHEN es.encrypted_value IS NOT NULL THEN 1 ELSE 0 END as has_credentials
            FROM e5_payment_gateways g
            LEFT JOIN e5_encrypted_settings es ON es.setting_key = CONCAT(g.gateway_name, \'_access_token\')
            ORDER BY g.gateway_name
        ');
        
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Failed to load gateways: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get currently active gateway
 */
function gatewayGetActive(): ?array
{
    $gateways = gatewayGetAll();
    foreach ($gateways as $gw) {
        if ($gw['is_active']) {
            return $gw;
        }
    }
    return null;
}

/**
 * Health check for Mercado Pago API
 */
function gatewayHealthCheckMercadoPago(string $accessToken): array
{
    $url = 'https://api.mercadopago.com/users/me';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
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
            'message' => 'Access token inválido ou expirado.',
        ];
    }
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'message' => 'Conexão válida. Conta: ' . ($data['email'] ?? 'N/A'),
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Resposta inesperada: HTTP ' . $httpCode,
    ];
}

/**
 * Health check for Asaas API
 */
function gatewayHealthCheckAsaas(string $accessToken): array
{
    $url = 'https://www.asaas.com/api/v3/myAccount';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'access_token' => $accessToken,
            'Accept' => 'application/json',
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
            'message' => 'Access token inválido ou expirado.',
        ];
    }
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'message' => 'Conexão válida. Conta: ' . ($data['name'] ?? 'N/A'),
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Resposta inesperada: HTTP ' . $httpCode,
    ];
}

/**
 * Health check dispatcher
 */
function gatewayHealthCheck(string $gatewayName, string $accessToken): array
{
    switch ($gatewayName) {
        case 'mercadopago':
            return gatewayHealthCheckMercadoPago($accessToken);
        case 'asaas':
            return gatewayHealthCheckAsaas($accessToken);
        default:
            return ['success' => false, 'message' => 'Gateway desconhecido: ' . $gatewayName];
    }
}

/**
 * Activate a gateway (with validation)
 * Only one can be active at a time
 */
function gatewayActivate(PDO $pdo, string $gatewayName, int $userId): array
{
    try {
        $pdo->beginTransaction();
        
        // Lock the gateways table
        $stmt = $pdo->query('SELECT id, is_active FROM e5_payment_gateways FOR UPDATE');
        $gateways = $stmt->fetchAll();
        
        // Verify gateway exists
        $targetGateway = null;
        $previousGateway = null;
        foreach ($gateways as $gw) {
            if ($gw['gateway_name'] === $gatewayName) {
                $targetGateway = $gw;
            }
            if ($gw['is_active']) {
                $previousGateway = $gw;
            }
        }
        
        if (!$targetGateway) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Gateway não encontrado: ' . $gatewayName];
        }
        
        // Check if gateway has credentials
        $hasCredentials = $pdo->prepare('SELECT COUNT(*) FROM e5_encrypted_settings WHERE setting_key = :key');
        $hasCredentials->execute([':key' => $gatewayName . '_access_token']);
        
        if ((int)$hasCredentials->fetchColumn() === 0) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Credenciais não configuradas para ' . $gatewayName,
            ];
        }
        
        // Deactivate all gateways
        $pdo->exec('UPDATE e5_payment_gateways SET is_active = 0');
        
        // Activate target
        $stmt = $pdo->prepare('UPDATE e5_payment_gateways SET is_active = 1 WHERE gateway_name = :name');
        $stmt->execute([':name' => $gatewayName]);
        
        // Update global setting
        $stmt = $pdo->prepare('
            INSERT INTO e5_settings (setting_key, setting_value) 
            VALUES (:key, :value) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');
        $stmt->execute([':key' => 'active_gateway', ':value' => $gatewayName]);
        
        // Log the change
        $stmt = $pdo->prepare('
            INSERT INTO e5_system_change_log 
            (change_type, user_id, regime_anterior, regime_novo, ip_address, user_agent, success, gateway_name, config_before, config_after)
            VALUES (:type, :user, :regime_old, :regime_new, :ip, :ua, 1, :gw, :before, :after)
        ');
        $stmt->execute([
            ':type' => 'gateway',
            ':user' => $userId,
            ':regime_old' => store_config('tax_regime') ?: 'CPF',
            ':regime_new' => store_config('tax_regime') ?: 'CPF',
            ':ip' => getClientIP(),
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ':gw' => $gatewayName,
            ':before' => $previousGateway ? $previousGateway['gateway_name'] : null,
            ':after' => $gatewayName,
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Gateway ' . $gatewayName . ' ativado com sucesso!',
        ];
        
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Gateway activation failed: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao ativar gateway: ' . $e->getMessage(),
        ];
    }
}

/**
 * Save gateway credentials (encrypted)
 */
function gatewaySaveCredentials(PDO $pdo, string $gatewayName, array $credentials): array
{
    $required = ['access_token'];
    
    foreach ($required as $field) {
        if (empty($credentials[$field])) {
            return ['success' => false, 'message' => 'Campo obrigatório não preenchido: ' . $field];
        }
    }
    
    $fields = ['access_token', 'public_key', 'webhook_secret'];
    
    foreach ($fields as $field) {
        if (!empty($credentials[$field])) {
            $result = saveEncryptedSetting($pdo, $gatewayName . '_' . $field, $credentials[$field]);
            if (!$result) {
                return ['success' => false, 'message' => 'Falha ao salvar ' . $field];
            }
        }
    }
    
    // Update gateway configured status
    $stmt = $pdo->prepare('
        UPDATE e5_payment_gateways 
        SET is_configured = 1 
        WHERE gateway_name = :name
    ');
    $stmt->execute([':name' => $gatewayName]);
    
    return ['success' => true, 'message' => 'Credenciais salvas com sucesso!'];
}

/**
 * Lock gateway for checkout session
 * Prevents mid-checkout gateway switching
 */
function gatewayLockForCheckout(string $sessionId, string $gatewayName, string $taxRegime): bool
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once dirname(__DIR__) . '/database/connection.php';
        }
        
        $stmt = $GLOBALS['pdo']->prepare('
            INSERT INTO e5_checkout_sessions (session_id, gateway_locked, tax_regime_locked, expires_at)
            VALUES (:sid, :gw, :regime, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ON DUPLICATE KEY UPDATE 
                gateway_locked = VALUES(gateway_locked),
                tax_regime_locked = VALUES(tax_regime_locked),
                expires_at = VALUES(expires_at)
        ');
        
        return $stmt->execute([
            ':sid' => $sessionId,
            ':gw' => $gatewayName,
            ':regime' => $taxRegime,
        ]);
        
    } catch (Throwable $e) {
        error_log('Failed to lock gateway for checkout: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get locked gateway for checkout session
 */
function gatewayGetLocked(string $sessionId): ?array
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once dirname(__DIR__) . '/database/connection.php';
        }
        
        $stmt = $GLOBALS['pdo']->prepare('
            SELECT * FROM e5_checkout_sessions 
            WHERE session_id = :sid 
            AND expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([':sid' => $sessionId]);
        
        return $stmt->fetch() ?: null;
        
    } catch (Throwable $e) {
        error_log('Failed to get locked gateway: ' . $e->getMessage());
        return null;
    }
}

/**
 * Release checkout session lock
 */
function gatewayReleaseLock(string $sessionId): bool
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once dirname(__DIR__) . '/database/connection.php';
        }
        
        $stmt = $GLOBALS['pdo']->prepare('DELETE FROM e5_checkout_sessions WHERE session_id = :sid');
        return $stmt->execute([':sid' => $sessionId]);
        
    } catch (Throwable $e) {
        error_log('Failed to release gateway lock: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get gateway snapshot for order
 */
function gatewayGetForOrder(PDO $pdo, int $orderId): ?string
{
    $stmt = $pdo->prepare('SELECT gateway_used FROM e5_orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $row = $stmt->fetch();
    
    return $row ? $row['gateway_used'] : null;
}

/**
 * Process incoming webhook
 * Always processes regardless of gateway active status
 */
function gatewayProcessWebhook(PDO $pdo, string $gatewayName, string $eventType, string $payload, ?string $signature = null): array
{
    // Log the webhook
    $stmt = $pdo->prepare('
        INSERT INTO e5_webhook_log 
        (gateway_name, event_type, payload, signature, processing_status)
        VALUES (:gw, :event, :payload, :sig, :status)
    ');
    $stmt->execute([
        ':gw' => $gatewayName,
        ':event' => $eventType,
        ':payload' => $payload,
        ':sig' => $signature,
        ':status' => 'pending',
    ]);
    
    $webhookId = (int) $pdo->lastInsertId();
    
    // Verify signature
    if ($signature) {
        $isValid = webhookVerifySignature($gatewayName, $payload, $signature);
        
        $pdo->prepare('UPDATE e5_webhook_log SET signature_valid = :valid WHERE id = :id')
            ->execute([':valid' => $isValid ? 1 : 0, ':id' => $webhookId]);
        
        if (!$isValid) {
            $pdo->prepare('UPDATE e5_webhook_log SET processing_status = :status, error_message = :msg WHERE id = :id')
                ->execute([':status' => 'failed', ':msg' => 'Invalid signature', ':id' => $webhookId]);
            
            return ['success' => false, 'message' => 'Assinatura inválida'];
        }
    }
    
    // Process based on gateway and event type
    $result = webhookProcessByGateway($pdo, $gatewayName, $eventType, json_decode($payload, true), $webhookId);
    
    return $result;
}

/**
 * Verify webhook signature
 */
function webhookVerifySignature(string $gatewayName, string $payload, string $signature): bool
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once dirname(__DIR__) . '/database/connection.php';
        }
        
        $webhookSecret = loadEncryptedSetting($GLOBALS['pdo'], $gatewayName . '_webhook_secret');
        
        if (!$webhookSecret) {
            return false;
        }
        
        if ($gatewayName === 'mercadopago') {
            // Mercado Pago uses HMAC-SHA256
            $expected = hash_hmac('sha256', $payload, $webhookSecret);
            return hash_equals($expected, $signature);
        } elseif ($gatewayName === 'asaas') {
            // Asaas uses token in header, simplified validation
            return !empty($signature);
        }
        
        return false;
        
    } catch (Throwable $e) {
        error_log('Webhook signature verification failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Process webhook by gateway type
 *
 * Para Mercado Pago: quando external_reference não está no payload (API de Orders),
 * usa o SDK para buscar a order por data.id e obter o external_reference.
 */
function webhookProcessByGateway(PDO $pdo, string $gatewayName, string $eventType, ?array $data, int $webhookId): array
{
    try {
        // Update webhook status
        $pdo->prepare('UPDATE e5_webhook_log SET processing_status = :status WHERE id = :id')
            ->execute([':status' => 'processed', ':id' => $webhookId]);
        
        // Extract order ID from webhook data
        $orderId = null;
        $orderStatus = null;
        if ($gatewayName === 'mercadopago' && isset($data['external_reference'])) {
            $orderId = (int) $data['external_reference'];
        } elseif ($gatewayName === 'asaas' && isset($data['payment']['externalReference'])) {
            $orderId = (int) $data['payment']['externalReference'];
        }

        // Fallback para Orders API: buscar order pelo ID retornado em data.id
        if (!$orderId && $gatewayName === 'mercadopago' && !empty($data['data']['id'])) {
            require_once __DIR__ . '/payment.php';
            $orderResult = paymentMercadoPagoGetOrder((string) $data['data']['id']);
            if ($orderResult['success'] && !empty($orderResult['data']['external_reference'])) {
                $orderId = (int) $orderResult['data']['external_reference'];
                $orderStatus = $orderResult['data']['status'] ?? null;
            }
        }
        
        if ($orderId) {
            // Determine new status — usar status real do SDK quando disponível
            $newStatus = 'paid';
            if ($orderStatus) {
                if (in_array($orderStatus, ['refunded', 'cancelled'])) {
                    $newStatus = $orderStatus === 'cancelled' ? 'canceled' : 'refunded';
                }
            } else {
                if (in_array($eventType, ['payment_refunded', 'payment_refunded_in_process'])) {
                    $newStatus = 'refunded';
                } elseif ($eventType === 'payment_cancelled') {
                    $newStatus = 'canceled';
                }
            }
            
            $pdo->prepare('
                UPDATE e5_orders 
                SET payment_status = :status, updated_at = NOW()
                WHERE id = :id
            ')->execute([':status' => $newStatus, ':id' => $orderId]);
            
            $pdo->prepare('UPDATE e5_webhook_log SET order_id = :id WHERE id = :wh')
                ->execute([':id' => $orderId, ':wh' => $webhookId]);
        }
        
        return ['success' => true, 'message' => 'Webhook processado'];
        
    } catch (Throwable $e) {
        $pdo->prepare('
            UPDATE e5_webhook_log 
            SET processing_status = :status, error_message = :msg, processing_attempts = processing_attempts + 1
            WHERE id = :id
        ')->execute([':status' => 'failed', ':msg' => $e->getMessage(), ':id' => $webhookId]);
        
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Cleanup expired checkout sessions
 */
function gatewayCleanupSessions(): int
{
    try {
        if (!isset($GLOBALS['pdo'])) {
            include_once dirname(__DIR__) . '/database/connection.php';
        }
        
        $stmt = $GLOBALS['pdo']->exec('DELETE FROM e5_checkout_sessions WHERE expires_at < NOW()');
        return $stmt;
        
    } catch (Throwable $e) {
        error_log('Session cleanup failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get gateway change history
 */
function gatewayGetChangeHistory(PDO $pdo, int $limit = 20): array
{
    $stmt = $pdo->prepare('
        SELECT scl.*, u.name as admin_name
        FROM e5_system_change_log scl
        LEFT JOIN e5_users u ON u.id = scl.user_id
        WHERE scl.change_type = :type
        ORDER BY scl.created_at DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':type', 'gateway', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}
