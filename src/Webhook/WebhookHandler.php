<?php

declare(strict_types=1);

namespace TCC\Webhook;

use PDO;

/**
 * Receptor e processador de webhooks da SuperFrete.
 *
 * Funcionalidades:
 *   1. Validação HMAC-SHA256 via header X-ME-Signature
 *   2. Idempotência via tabela superfrete_webhook_log (event_id único)
 *   3. Resposta rápida (timeout SuperFrete = 30s, retries a cada 15min, até 5x)
 *
 * Eventos suportados:
 *   order.created, order.released, order.generated,
 *   order.posted, order.delivered, order.cancelled
 */
class WebhookHandler
{
    private PDO $db;
    private string $secretToken;

    /** Eventos válidos da SuperFrete */
    private const VALID_EVENTS = [
        'order.created',
        'order.released',
        'order.generated',
        'order.posted',
        'order.delivered',
        'order.cancelled',
    ];

    public function __construct(PDO $db, string $secretToken)
    {
        $this->db = $db;
        $this->secretToken = $secretToken;
    }

    /**
     * Processa um webhook recebido da SuperFrete.
     *
     * Fluxo:
     *   1. Ler body raw da requisição
     *   2. Validar assinatura HMAC-SHA256 (X-ME-Signature)
     *   3. Decodificar payload
     *   4. Verificar idempotência (event_id já processado?)
     *   5. Se duplicata → responder 200 sem reprocessar
     *   6. Se novo → registrar no log e processar
     *   7. Retornar array com resultado do processamento
     *
     * @return array{status: string, message: string, event_id?: string}
     */
    public function handle(): array
    {
        // 1. Ler body raw (sobrescrevível em testes via readInput())
        $rawBody = $this->readInput();
        if ($rawBody === false || $rawBody === '') {
            http_response_code(400);
            return ['status' => 'error', 'message' => 'Body vazio'];
        }

        // 2. Validar assinatura HMAC-SHA256
        $signature = $_SERVER['HTTP_X_ME_SIGNATURE'] ?? '';
        if (!$this->validateSignature($rawBody, $signature)) {
            http_response_code(401);
            return ['status' => 'error', 'message' => 'Assinatura inválida'];
        }

        // 3. Decodificar payload
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            http_response_code(400);
            return ['status' => 'error', 'message' => 'Payload JSON inválido'];
        }

        // Extrair dados do evento
        $eventType = $payload['event'] ?? '';
        $eventData = $payload['data'] ?? [];
        $orderId   = $eventData['id'] ?? '';
        $eventId   = $this->generateEventId($eventType, $orderId, $eventData);

        // 4. Validar tipo de evento
        if (!in_array($eventType, self::VALID_EVENTS, true)) {
            http_response_code(200);
            return ['status' => 'ignored', 'message' => "Evento desconhecido: $eventType"];
        }

        // 5. Verificar idempotência
        if ($this->isDuplicate($eventId)) {
            http_response_code(200);
            return [
                'status'   => 'duplicate',
                'message'  => "Evento $eventId já processado",
                'event_id' => $eventId,
            ];
        }

        // 6. Registrar no log de idempotência
        $this->logEvent($eventId, $eventType, $orderId, $payload);

        // 7. Processar o evento (hook customizado)
        $this->processEvent($eventType, $eventData);

        http_response_code(200);
        return [
            'status'   => 'processed',
            'message'  => "Evento $eventType processado com sucesso",
            'event_id' => $eventId,
        ];
    }

    /**
     * Lê o body raw da requisição HTTP.
     * Método visível para testes (pode ser sobrescrito por um fake).
     */
    protected function readInput(): string
    {
        $raw = file_get_contents('php://input');
        return $raw === false ? '' : $raw;
    }

    /**
     * Valida a assinatura HMAC-SHA256 do body.
     *
     * A SuperFrete gera: HMAC-SHA256(body, secret_token)
     * e envia no header X-ME-Signature.
     *
     * @param string $rawBody  Body raw da requisição
     * @param string $signature Valor do header X-ME-Signature
     * @return bool true se a assinatura for válida
     */
    public function validateSignature(string $rawBody, string $signature): bool
    {
        if ($signature === '' || $this->secretToken === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->secretToken);

        // Comparação segura contra timing attacks
        return hash_equals($expected, $signature);
    }

    /**
     * Gera um ID único para o evento (idempotência).
     *
     * Usa o event type + order_id + timestamps do payload para garantir
     * unicidade mesmo sem um event_id explícito da SuperFrete.
     */
    protected function generateEventId(string $eventType, string $orderId, array $eventData): string
    {
        // Se a SuperFrete fornecer um event_id explícito, usar ele
        if (!empty($eventData['event_id'])) {
            return (string) $eventData['event_id'];
        }

        // Senão, gerar a partir dos dados disponíveis
        $createdAt = $eventData['created_at'] ?? '';
        $updatedAt = $eventData['updated_at'] ?? '';

        $uniqueString = "$eventType:$orderId:$createdAt:$updatedAt";
        return hash('sha256', $uniqueString);
    }

    /**
     * Verifica se o evento já foi processado (idempotência).
     *
     * Sobrescrevível em testes.
     *
     * @param string $eventId ID único do evento
     * @return bool true se já existe no log
     */
    protected function isDuplicate(string $eventId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM superfrete_webhook_log WHERE event_id = :eid LIMIT 1'
        );
        $stmt->execute([':eid' => $eventId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Registra o evento no log de idempotência.
     *
     * Guarda apenas o payload_hash (SHA-256 do body) — nunca o JSON em
     * claro — para não reter dados pessoais do destinatário (LGPD).
     *
     * Sobrescrevível em testes.
     */
    protected function logEvent(string $eventId, string $eventType, string $orderId, array $payload): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO superfrete_webhook_log (event_id, event_type, order_id, payload_hash)
             VALUES (:eid, :etype, :oid, :payload_hash)'
        );
        $stmt->execute([
            ':eid'          => $eventId,
            ':etype'        => $eventType,
            ':oid'          => $orderId,
            ':payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE)),
        ]);
    }

    /**
     * Processa o evento recebido.
     *
     * Este é o hook onde a aplicação deve implementar a lógica
     * específica para cada tipo de evento. Sobrescreva em uma subclasse.
     *
     * @param string $eventType Tipo do evento (order.created, etc.)
     * @param array  $eventData Dados do evento
     */
    protected function processEvent(string $eventType, array $eventData): void
    {
        // Por padrão, apenas loga o evento.
        // Sobrescrever esta classe ou adicionar callback para lógica customizada.
        error_log(
            sprintf(
                '[SuperFrete Webhook] Evento processado: %s | Order: %s | Status: %s',
                $eventType,
                $eventData['id'] ?? 'N/A',
                $eventData['status'] ?? 'N/A'
            )
        );
    }

    // =====================================================================
    //  MÉTODOS ESTÁTICOS UTILITÁRIOS
    // =====================================================================

    /**
     * Gera a assinatura HMAC-SHA256 para teste/envio.
     *
     * Útil para testar a validação ou criar webhooks em ambientes de teste.
     *
     * @param string $rawBody     Body JSON da requisição
     * @param string $secretToken Secret token do webhook
     * @return string Assinatura hex
     */
    public static function generateSignature(string $rawBody, string $secretToken): string
    {
        return hash_hmac('sha256', $rawBody, $secretToken);
    }

    /**
     * Retorna a lista de eventos válidos da SuperFrete.
     *
     * @return array<string>
     */
    public static function getValidEvents(): array
    {
        return self::VALID_EVENTS;
    }
}
