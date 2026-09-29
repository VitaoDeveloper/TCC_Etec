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
     * Mapa evento da SuperFrete -> status gravado em e5_shipments.
     *
     * "generated" e "released" caem no mesmo valor de propósito: a
     * diferença entre "etiqueta gerada" e "etiqueta liberada pela
     * transportadora" não muda o que o cliente precisa ver.
     */
    private const STATUS_MAP = [
        'order.created'    => 'pending',
        'order.released'   => 'released',
        'order.generated'  => 'released',
        'order.posted'     => 'released',
        'order.delivered'  => 'delivered',
        'order.cancelled'  => 'canceled',
    ];

    /**
     * Processa o evento recebido: grava rastreio e status do envio.
     *
     * O rastreio chega em nomes diferentes dependendo do evento
     * (tracking_code, self_tracking ou tracking), então os três são
     * testados antes de desistir.
     *
     * @param string $eventType Tipo do evento (order.created, etc.)
     * @param array  $eventData Dados do evento
     */
    protected function processEvent(string $eventType, array $eventData): void
    {
        $sfId = (string) ($eventData['id'] ?? '');
        if ($sfId === '') {
            return;
        }

        $tracking = '';
        foreach (['tracking_code', 'self_tracking', 'tracking'] as $key) {
            if (!empty($eventData[$key]) && is_scalar($eventData[$key])) {
                $tracking = (string) $eventData[$key];
                break;
            }
        }

        $status = self::STATUS_MAP[$eventType] ?? null;
        if ($status === null) {
            return;
        }

        // Traz o envio pelo id da SuperFrete, junto com o pedido.
        $st = $this->db->prepare(
            'SELECT s.id, s.order_id, s.status AS shipment_status, s.tracking_code,
                    o.status AS order_status
             FROM e5_shipments s
             JOIN e5_orders o ON o.id = s.order_id
             WHERE s.superfrete_id = :sf
             LIMIT 1'
        );
        $st->execute([':sf' => $sfId]);
        $ship = $st->fetch(PDO::FETCH_ASSOC);

        // Envio desconhecido: pode ser uma etiqueta criada fora do painel.
        // Registrado no log e ignorado, sem erro — a SuperFrete reenvia.
        if (!$ship) {
            error_log("[SuperFrete Webhook] {$eventType} para etiqueta {$sfId} sem envio local");
            return;
        }

        $orderId = (int) $ship['order_id'];

        $up = $this->db->prepare(
            'UPDATE e5_shipments
             SET status = :status,
                 tracking_code = COALESCE(NULLIF(:track, ""), tracking_code)
             WHERE id = :id'
        );
        $up->execute([
            ':status' => $status,
            ':track'  => $tracking,
            ':id'     => (int) $ship['id'],
        ]);

        // Atraso de entrega estimado, quando a SuperFrete mandar.
        $min = $eventData['delivery_min_days'] ?? $eventData['min_delivery_days'] ?? null;
        $max = $eventData['delivery_max_days'] ?? $eventData['max_delivery_days'] ?? null;
        if (is_numeric($min) && is_numeric($max)) {
            $this->db->prepare(
                'UPDATE e5_shipments SET delivery_min_days = :min, delivery_max_days = :max WHERE id = :id'
            )->execute([
                ':min' => (int) $min,
                ':max' => (int) $max,
                ':id'  => (int) $ship['id'],
            ]);
        }

        // Não regrava status que o cliente já viu, para não disparar
        // e-mail de "entregue" a cada reenvio do mesmo webhook.
        $newOrderStatus = $status === 'delivered' ? 'delivered' : 'shipped';

        if ($newOrderStatus !== $ship['order_status']) {
            $this->db->prepare('UPDATE e5_orders SET status = :s WHERE id = :id')
                ->execute([':s' => $newOrderStatus, ':id' => $orderId]);

            $this->db->prepare(
                'UPDATE e5_orders SET tracking_code = COALESCE(NULLIF(:t, ""), tracking_code) WHERE id = :id'
            )->execute([':t' => $tracking, ':id' => $orderId]);

            $this->notify($orderId, $newOrderStatus === 'delivered' ? 'order_delivered' : 'order_shipped', $tracking);
        } elseif ($tracking !== '' && $tracking !== $ship['tracking_code']) {
            // Mesmo estado, mas o código de rastreio apareceu depois.
            $this->db->prepare('UPDATE e5_orders SET tracking_code = :t WHERE id = :id')
                ->execute([':t' => $tracking, ':id' => $orderId]);

            $this->notify($orderId, 'order_shipped', $tracking);
        }
    }

    /**
     * Enfileira a notificação do pedido, se a fila estiver carregada.
     *
     * A classe é testável sem includes/notification_functions.php: quem
     * chama o handler em teste unitário não quer depender de SMTP nem de
     * e5_notifications existir.
     */
    private function notify(int $orderId, string $event, string $tracking): void
    {
        if (!function_exists('notification_enqueue_order_event')) {
            return;
        }

        try {
            notification_enqueue_order_event($this->db, $orderId, $event, ['tracking' => $tracking]);
        } catch (\Throwable $e) {
            // \Throwable é obrigatório: sem a barra, o namespace TCC\Webhook
            // faz "Throwable" virar TCC\Webhook\Throwable, que não existe, e a
            // exceção escapa do catch — exatamente o que este bloco existe
            // para impedir.
            //
            // Falha de notificação não pode derrubar o processamento do
            // webhook: o rastreio já foi gravado, que é o essencial.
            error_log('[SuperFrete Webhook] Falha ao enfileirar notificação: ' . $e->getMessage());
        }
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
