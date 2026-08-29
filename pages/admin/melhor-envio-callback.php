<?php
/**
 * Melhor Envio OAuth Callback Handler
 *
 * Processa o callback OAuth do Melhor Envio após o usuário autorizar o aplicativo.
 * Troca o código recebido por um access_token e salva-o criptografado.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../database/connection.php';

$page_title = 'Callback OAuth Melhor Envio';
$message = null;
$messageType = 'success';
$error = null;

// Log da tentativa de callback
function logCallbackAttempt(PDO $pdo, int $userId, string $action, array $details = [], bool $success = false, ?string $errorMessage = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO e5_system_change_log 
            (change_type, user_id, regime_anterior, regime_novo, ip_address, user_agent, success, error_message, gateway_name, config_before, config_after)
            VALUES (:type, :user, :old, :new, :ip, :ua, :success, :error, :gw, :before, :after)'
        );
        $stmt->execute([
            ':type' => $action,
            ':user' => $userId,
            ':old' => 'OAuth',
            ':new' => 'OAuth',
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ':success' => $success ? 1 : 0,
            ':error' => $errorMessage,
            ':gw' => 'melhor_envio',
            ':before' => null,
            ':after' => json_encode($details),
        ]);
    } catch (Throwable $e) {
        error_log('logCallbackAttempt failed: ' . $e->getMessage());
    }
}

// Validar presença do parâmetro code
if (!isset($_GET['code']) || empty($_GET['code'])) {
    $error = 'Parâmetro obrigatório "code" não fornecido pelo Melhor Envio.';
    $messageType = 'error';
} else {
    $code = $_GET['code'];
    $state = $_GET['state'] ?? null; // Parâmetro opcional de segurança
    
    try {
        // Carregar credenciais do OAuth do Melhor Envio
        $clientId = loadEncryptedSetting($pdo, 'melhor_envio_client_id');
        $clientSecret = loadEncryptedSetting($pdo, 'melhor_envio_client_secret');
        $redirectUri = loadEncryptedSetting($pdo, 'melhor_envio_redirect_uri');
        
        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception('Credenciais do OAuth do Melhor Envio não configuradas.');
        }
        
        // Validar redirect_uri correspondente (para segurança)
        if (!empty($redirectUri) && $redirectUri !== ($_SERVER['HTTP_HOST'] ?? '') . '/pages/admin/melhor-envio-callback.php') {
            $error = 'URI de redirecionamento não corresponde à configurada no aplicativo Melhor Envio.';
            $messageType = 'error';
        } else {
            // Trocar código por access_token na API do Melhor Envio
            $tokenUrl = 'https://melhorenvio.com.br/oauth/token';
            
            $postData = [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri ?: 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/pages/admin/melhor-envio-callback.php',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ];
            
            $ch = curl_init($tokenUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'User-Agent: RoyalTech-Ecommerce/1.0',
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception('Erro de conexão com a API do Melhor Envio: ' . $curlError);
            }
            
            $responseData = json_decode($response, true);
            
            if ($httpCode !== 200 || !isset($responseData['access_token'])) {
                $errorMessage = $responseData['error_description'] ?? $responseData['error'] ?? 'Token inválido ou expirado.';
                throw new Exception('Falha ao trocar código por access_token: ' . $errorMessage);
            }
            
            $accessToken = $responseData['access_token'];
            $refreshToken = $responseData['refresh_token'] ?? null;
            $expiresIn = $responseData['expires_in'] ?? 1800; // 30 dias em segundos
            $tokenType = $responseData['token_type'] ?? 'bearer';
            
            // Salvar o access_token criptografado
            $saveResult = saveEncryptedSetting($pdo, 'melhor_envio_token', $accessToken);
            if ($saveResult) {
                // Salvar também o refresh_token, se disponível
                if ($refreshToken) {
                    saveEncryptedSetting($pdo, 'melhor_envio_refresh_token', $refreshToken);
                }
                
                $message = '✅ OAuth do Melhor Envio autorizado com sucesso! O access_token foi salvo criptografado.';
                
                // Log de sucesso
                logCallbackAttempt($pdo, $_SESSION['user_id'], 'melhor_envio_oauth_callback', [
                    'access_token_preview' => substr($accessToken, 0, 20) . '...',
                    'expires_in' => $expiresIn,
                    'token_type' => $tokenType,
                    'has_refresh_token' => !empty($refreshToken),
                ], true);
                
            } else {
                throw new Exception('Falha ao salvar o access_token criptografado no banco de dados.');
            }
        }
        
    } catch (Throwable $e) {
        $error = 'Erro durante o processamento do callback OAuth: ' . $e->getMessage();
        $messageType = 'error';
        
        // Log de falha
        logCallbackAttempt($pdo, $_SESSION['user_id'], 'melhor_envio_oauth_callback_error', [], false, $e->getMessage());
        
        // Exibir erro no painel admin (sem expor tokens em texto puro)
        $message = '⚠️ Não foi possível processar o retorno OAuth do Melhor Envio. ';
        if (strpos($e->getMessage(), 'Token inválido') !== false) {
            $message .= 'O código pode ter expirado ou sido invalidado. Tente novamente com o Melhor Envio.';
        } elseif (strpos($e->getMessage(), 'Credenciais') !== false) {
            $message .= 'As credenciais do OAuth não estão configuradas corretamente.';
        } else {
            $message .= 'Por favor, verifique o log de auditoria para mais detalhes.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include 'head_inc.php'; ?>
    <style>
    .admin-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 30px;
        background: var(--color-bg-card);
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        text-align: center;
    }
    
    .callback-result {
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .callback-result.success {
        background: rgba(76, 175, 80, 0.1);
        border: 2px solid rgba(76, 175, 80, 0.3);
        color: #2e7d32;
    }
    
    .callback-result.error {
        background: rgba(244, 67, 54, 0.1);
        border: 2px solid rgba(244, 67, 54, 0.3);
        color: #c62828;
    }
    
    .btn {
        display: inline-block;
        padding: 12px 24px;
        background: var(--color-primary);
        color: #1a1a1a;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        margin: 10px;
        transition: all 0.3s ease;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(212, 175, 55, 0.3);
    }
    
    .icon {
        font-size: 48px;
        margin-bottom: 20px;
    }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php $activePage = 'settings'; include 'sidebar_inc.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Callback OAuth Melhor Envio</h2>
                    <p>Processamento do retorno de autorização do Melhor Envio</p>
                </div>
            </header>
            
            <div class="admin-container">
                <?php if ($message): ?>                    <div class="callback-result <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                        <div class="icon">
                            <?php echo $messageType === 'success' ? '✅' : '❌'; ?>
                        </div>
                        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if ($error): ?>
                        <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.8;">
                            <strong>Detalhes técnicos:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <a href="settings.php?tab=frete" class="btn">
                    <i class="fas fa-cog"></i> Voltar para Configurações de Frete
                </a>
                
                <p style="color: var(--color-gray); font-size: 0.9rem; margin-top: 20px;">
                    Esta rota processa automaticamente o callback OAuth do Melhor Envio. O access_token é salvo criptografado e pronto para uso.
                </p>
            </div>
        </main>
    </div>
    
    <script src="../../assets/js/script.js"></script>
</body>
</html>
