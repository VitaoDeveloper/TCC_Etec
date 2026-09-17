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

        // 6. Registrar no log de idempotência + processar de forma atômica.
        // Se o processamento falhar, o log é revertido para permitir o retry
        // automático da SuperFrete (mesmo event_id será reprocessado).
        $useTransaction = isset($this->db);
        try {
            if ($useTransaction) {
                $this->db->beginTransaction();
            }
            $this->logEvent($eventId, $eventType, $orderId, $payload);
            $this->processEvent($eventType, $eventData);
            if ($useTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($useTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[SuperFrete Webhook] Falha ao processar ' . $eventType . ': ' . $e->getMessage());
            http_response_code(500);
            return ['status' => 'error', 'message' => 'Falha ao processar evento'];
        }

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
     * Processa o evento recebido e reflete o novo estado no pedido e no envio.
     *
     * Mapeamento evento → status do envio (e5_shippings):
     *   order.created   → pending
     *   order.generated → generated
     *   order.released  → released
     *   order.posted    → posted    (pedido → shipped)
     *   order.delivered → delivered (pedido → delivered)
     *   order.cancelled → canceled  (pedido → canceled)
     *
     * O pedido é localizado por e5_shippings.superfrete_order_id ou pela tag
     * "ORDER-<id>" enviada na criação da etiqueta.
     *
     * @param string $eventType Tipo do evento (order.created, etc.)
     * @param array  $eventData Dados do evento
     */
    protected function processEvent(string $eventType, array $eventData): void
    {
        $superfreteId = (string) ($eventData['id'] ?? '');
        $orderId = $this->resolveLocalOrderId($eventData, $superfreteId);

        error_log(sprintf(
            '[SuperFrete Webhook] %s | SuperFrete: %s | Pedido: %s',
            $eventType,
            $superfreteId !== '' ? $superfreteId : 'N/A',
            $orderId !== null ? (string) $orderId : 'não localizado'
        ));

        if ($orderId === null) {
            return; // evento de pedido que não é desta loja
        }

        $shippingStatus = [
            'order.created'   => 'pending',
            'order.generated' => 'generated',
            'order.released'  => 'released',
            'order.posted'    => 'posted',
            'order.delivered' => 'delivered',
            'order.cancelled' => 'canceled',
        ][$eventType] ?? null;

        $tracking = (string) ($eventData['tracking'] ?? $eventData['tracking_code'] ?? '');
        $carrier  = (string) ($eventData['carrier'] ?? $eventData['service']['name'] ?? '');

        // Atualiza o envio (e5_shippings).
        $set = ['last_event = :ev', 'last_event_at = :now'];
        $params = [':ev' => $eventType, ':now' => date('Y-m-d H:i:s'), ':oid' => $orderId];
        if ($shippingStatus !== null) {
            $set[] = 'status = :st';
            $params[':st'] = $shippingStatus;
        }
        if ($tracking !== '') {
            $set[] = 'tracking_code = :tr';
            $params[':tr'] = $tracking;
        }
        if ($carrier !== '') {
            $set[] = 'carrier = :cr';
            $params[':cr'] = $carrier;
        }
        if ($eventType === 'order.cancelled') {
            $set[] = 'canceled = 1';
        }
        $this->db->prepare('UPDATE e5_shippings SET ' . implode(', ', $set) . ' WHERE order_id = :oid')
            ->execute($params);

        // Atualiza o pedido (e5_orders) respeitando a progressão de status.
        $stmt = $this->db->prepare('SELECT status, payment_status, tracking_code FROM e5_orders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return;
        }

        $rank = ['pending' => 0, 'paid' => 1, 'shipped' => 2, 'delivered' => 3, 'canceled' => 4];
        $current = (string) $order['status'];

        if ($eventType === 'order.cancelled') {
            if ($current !== 'canceled') {
                $this->db->prepare("UPDATE e5_orders SET status = 'canceled' WHERE id = :id")->execute([':id' => $orderId]);
                $this->addHistory($orderId, 'canceled', (string) $order['payment_status'], 'Etiqueta cancelada na SuperFrete (webhook).');
            }
            return;
        }

        $target = null;
        $note = '';
        if ($eventType === 'order.posted') {
            $target = 'shipped';
            $note = 'Pedido postado na transportadora (webhook)' . ($tracking !== '' ? '. Rastreio: ' . $tracking : '.');
        } elseif ($eventType === 'order.delivered') {
            $target = 'delivered';
            $note = 'Pedido entregue ao destinatário (webhook).';
        }

        if ($target !== null && ($rank[$target] ?? 0) > ($rank[$current] ?? 0)) {
            $sql = 'UPDATE e5_orders SET status = :status';
            $up = [':status' => $target, ':id' => $orderId];
            if ($tracking !== '' && empty($order['tracking_code'])) {
                $sql .= ', tracking_code = :track';
                $up[':track'] = $tracking;
            }
            $sql .= ' WHERE id = :id';
            $this->db->prepare($sql)->execute($up);
            $this->addHistory($orderId, $target, (string) $order['payment_status'], $note);
        }
    }

    /**
     * Descobre o pedido local a partir do evento do webhook.
     */
    protected function resolveLocalOrderId(array $eventData, string $superfreteId): ?int
    {
        if ($superfreteId !== '') {
            $stmt = $this->db->prepare('SELECT order_id FROM e5_shippings WHERE superfrete_order_id = :sid LIMIT 1');
            $stmt->execute([':sid' => $superfreteId]);
            $found = $stmt->fetchColumn();
            if ($found !== false && $found !== null) {
                return (int) $found;
            }
        }

        // Tags enviadas na criação ("ORDER-<id>").
        $tags = $eventData['tags'] ?? [];
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $value = is_array($tag) ? (string) ($tag['tag'] ?? '') : (string) $tag;
                if (preg_match('/^ORDER-(\d+)$/i', trim($value), $m)) {
                    return (int) $m[1];
                }
            }
        }

        return null;
    }

    /**
     * Registra a transição na timeline do pedido (e5_order_status_history).
     */
    protected function addHistory(int $orderId, string $status, ?string $paymentStatus, string $note): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO e5_order_status_history (order_id, status, payment_status, note, changed_by)
             VALUES (:oid, :st, :ps, :note, :by)'
        );
        $stmt->execute([
            ':oid'  => $orderId,
            ':st'   => $status,
            ':ps'   => $paymentStatus,
            ':note' => mb_substr($note, 0, 255),
            ':by'   => 'SuperFrete',
        ]);
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
