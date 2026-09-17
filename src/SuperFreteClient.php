<?php

declare(strict_types=1);

namespace TCC;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TimeoutException;
use TCC\Exception\SuperFreteException;

/**
 * Cliente completo para a API SuperFrete (v0).
 *
 * Cobertura de endpoints:
 *   POST /calculator       — Cotação de frete
 *   POST /cart             — Criar frete (carrinho)
 *   POST /checkout         — Finalizar pedido / gerar etiqueta
 *   GET  /order/info/{id}  — Informações do pedido
 *   POST /tag/print        — Link de impressão da etiqueta
 *   GET  /me/orders        — Listar etiquetas (com filtros)
 *   POST /order/cancel     — Cancelar pedido
 *   POST /webhook          — Criar webhook app
 *   GET  /webhook          — Listar webhook apps
 *   PUT  /webhook/{id}     — Atualizar webhook app
 *   DELETE /webhook/{id}   — Deletar webhook app
 *
 * Regras de uso:
 *   - Token e User-Agent vêm do .env via Dotenv.
 *   - NUNCA logar o token completo ou secret_token em logs/erros.
 *   - postal_code: padronizar para 8 dígitos sem hífen.
 *   - Nome: precisa ter nome + sobrenome. Loja com 1 palavra → prefixar "Loja ".
 *   - Telefone destinatário: exatamente 11 dígitos sem máscara (obrigatório J&T).
 *   - Documento destinatário (CPF/CNPJ): obrigatório.
 *   - volumes: usar dimensões da caixa ideal retornada pelo /calculator.
 */
class SuperFreteClient
{
    private Client $httpClient;
    private string $token;
    private string $baseUrl;
    private string $userAgent;

    /** Tentativas extras em falhas transitórias (0 = sem retry). */
    private int $maxRetries;

    /** Base do backoff exponencial em milissegundos. */
    private int $retryBaseMs;

    /** @var resource|null Handle de log para debug (NUNCA expor token) */
    private $logHandle = null;

    /**
     * Construtor: recebe variáveis de ambiente já carregadas.
     *
     * @param array<string,string> $env Variáveis SUPERFRETE_* do .env
     * @param string|null         $logPath Caminho opcional para arquivo de log
     * @param Client|null         $httpClient Cliente Guzzle injetável (testes)
     */
    public function __construct(array $env, ?string $logPath = null, ?Client $httpClient = null)
    {
        $this->token     = $env['SUPERFRETE_TOKEN'] ?? '';
        $this->baseUrl   = rtrim($env['SUPERFRETE_BASE_URL'] ?? 'https://sandbox.superfrete.com/', '/');
        $this->userAgent = $env['SUPERFRETE_USER_AGENT'] ?? 'RoyalTech 1.0 (integracao@superfrete.com)';

        $this->maxRetries  = max(0, (int) ($env['SUPERFRETE_MAX_RETRIES'] ?? 2));
        $this->retryBaseMs = max(0, (int) ($env['SUPERFRETE_RETRY_BASE_MS'] ?? 250));

        if ($this->token === '') {
            throw new \InvalidArgumentException(
                'Token SuperFrete não configurado. Defina SUPERFRETE_TOKEN no .env'
            );
        }

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 30,
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent'    => $this->userAgent,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ]);

        if ($logPath !== null) {
            $this->logHandle = fopen($logPath, 'a');
        }
    }

    public function __destruct()
    {
        if (is_resource($this->logHandle)) {
            fclose($this->logHandle);
        }
    }

    // =====================================================================
    //  HEALTH CHECK
    // =====================================================================

    /**
     * Verifica se o token + User-Agent estão corretos.
     *
     * GET /api/v0/me/orders?page=1&per_page=1
     *
     * @return array Resposta bruta da API (lista de pedidos, pode ser vazia)
     * @throws SuperFreteException
     */
    public function healthCheck(): array
    {
        return $this->request('GET', '/api/v0/me/orders', [
            'query' => ['page' => 1, 'per_page' => 1],
        ]);
    }

    // =====================================================================
    //  COTAÇÃO DE FRETE
    // =====================================================================

    /**
     * Calcula o valor do frete.
     *
     * POST /api/v0/calculator
     *
     * @param array{
     *   from: array{postal_code: string},
     *   to: array{postal_code: string},
     *   services: string,
     *   options?: array{own_hand?: bool, receipt?: bool, insurance_value?: float, use_insurance_value?: bool},
     *   package?: array{height: float, width: float, length: float, weight: float},
     *   products?: array<int, array{quantity: int, height: float, width: float, length: float, weight: float}>
     * } $data Dados da cotação
     *
     * @return array Lista de opções de frete retornadas
     * @throws SuperFreteException
     */
    public function calculateShipping(array $data): array
    {
        return $this->request('POST', '/api/v0/calculator', [
            'json' => $data,
        ]);
    }

    // =====================================================================
    //  CRIAR FRETE (CARRINHO)
    // =====================================================================

    /**
     * Envia detalhes do pedido e cria uma etiqueta (status: pending).
     *
     * POST /api/v0/cart
     *
     * CAMPOS OBRIGATÓRIOS documentados inline:
     *   - from.name / to.name: nome + sobrenome. Loja 1 palavra → "Loja NomeLoja"
     *   - to.phone: exatamente 11 dígitos sem máscara (obrigatório para J&T)
     *   - to.document: CPF/CNPJ do destinatário (obrigatório para todas transportadoras)
     *   - postal_code: 8 dígitos sem hífen
     *   - volumes: usar dimensões da caixa ideal do /calculator quando products[] é usado
     *
     * @param array{
     *   from: array{name: string, address: string, complement?: string, number?: string, district: string, city: string, state_abbr: string, postal_code: string, document?: string},
     *   to: array{name: string, address: string, complement?: string, number?: string, district: string, city: string, state_abbr: string, postal_code: string, email?: string, phone?: string, document: string},
     *   service: int,
     *   products?: array<int, array{name: string, quantity: int|string, unitary_value: int|float|string}>,
     *   volumes: array{height: float, width: float, length: float, weight: float},
     *   options?: array{insurance_value?: float, receipt?: bool, own_hand?: bool, non_comercial?: bool, invoice?: array{number: string, key?: string}, tags?: array<int, array{tag: string, url?: string}>},
     *   platform: string
     * } $data Dados do frete
     *
     * @return array{id: string, price: float, status: string}
     * @throws SuperFreteException
     */
    public function createShipping(array $data): array
    {
        return $this->request('POST', '/api/v0/cart', [
            'json' => $data,
        ]);
    }

    // =====================================================================
    //  CHECKOUT / GERAR ETIQUETA
    // =====================================================================

    /**
     * Finaliza o pagamento da etiqueta usando saldo da carteira SuperFrete.
     *
     * POST /api/v0/checkout
     *
     * Pré-requisito: saldo suficiente na carteira.
     * Após sucesso, status muda para "released".
     *
     * @param array{orders: list<string>} $data IDs dos pedidos para checkout
     * @return array{success: bool, purchase: array{status: string, orders: array}}
     * @throws SuperFreteException
     */
    public function checkout(array $data): array
    {
        return $this->request('POST', '/api/v0/checkout', [
            'json' => $data,
        ]);
    }

    // =====================================================================
    //  INFORMAÇÕES DO PEDIDO
    // =====================================================================

    /**
     * Retorna informações detalhadas de uma etiqueta de frete.
     *
     * GET /api/v0/order/info/{id}
     *
     * @param string $orderId ID da etiqueta gerada pelo /cart
     * @return array Dados completos do pedido (from, to, tracking, status, etc.)
     * @throws SuperFreteException
     */
    public function getOrderInfo(string $orderId): array
    {
        return $this->request('GET', '/api/v0/order/info/' . $orderId);
    }

    // =====================================================================
    //  LINK DE IMPRESSÃO DA ETIQUETA
    // =====================================================================

    /**
     * Retorna URL do PDF da etiqueta para impressão.
     *
     * POST /api/v0/tag/print
     *
     * @param array{orders: list<string>} $data IDs dos pedidos
     * @return array{url: string} URL do PDF
     * @throws SuperFreteException
     */
    public function getPrintLink(array $data): array
    {
        return $this->request('POST', '/api/v0/tag/print', [
            'json' => $data,
        ]);
    }

    // =====================================================================
    //  LISTAR ETIQUETAS
    // =====================================================================

    /**
     * Lista envios/pedidos com filtros, paginação e ordenação.
     *
     * GET /api/v0/me/orders
     *
     * @param array{status?: string, page?: int, per_page?: int, order?: string, sort_by?: string} $params
     *   - status: pending|released|posted|delivered|canceled (separados por ;)
     *   - page: número da página
     *   - per_page: resultados por página (padrão 20)
     *   - order: asc|desc
     *   - sort_by: created_at|updated_at
     * @return array Lista de etiquetas
     * @throws SuperFreteException
     */
    public function listOrders(array $params = []): array
    {
        return $this->request('GET', '/api/v0/me/orders', [
            'query' => $params,
        ]);
    }

    // =====================================================================
    //  CANCELAR PEDIDO
    // =====================================================================

    /**
     * Cancela uma etiqueta de frete (só funciona antes da postagem).
     *
     * POST /api/v0/order/cancel
     *
     * @param string $orderId   ID da etiqueta
     * @param string $description Motivo do cancelamento (ex: "Cancelado pelo usuario")
     * @return array Resposta da API (ex: {id: {canceled: true}})
     * @throws SuperFreteException
     */
    public function cancelOrder(string $orderId, string $description = 'Cancelado pelo usuario'): array
    {
        return $this->request('POST', '/api/v0/order/cancel', [
            'json' => [
                'order' => [
                    'id'          => $orderId,
                    'description' => $description,
                ],
            ],
        ]);
    }

    // =====================================================================
    //  WEBHOOKS — CRUD
    // =====================================================================

    /**
     * Cria um novo webhook app.
     *
     * POST /api/v0/webhook
     *
     * Eventos disponíveis:
     *   order.created, order.released, order.generated,
     *   order.posted, order.delivered, order.cancelled
     *
     * @param string      $name   Nome do webhook app
     * @param string      $url    URL que receberá as notificações
     * @param string[]|null $events Lista de eventos (null = todos)
     * @return array Dados do webhook criado (inclui secret_token)
     * @throws SuperFreteException
     */
    public function createWebhook(string $name, string $url, ?array $events = null): array
    {
        $payload = ['name' => $name, 'url' => $url];
        if ($events !== null) {
            $payload['events'] = $events;
        }

        return $this->request('POST', '/api/v0/webhook', [
            'json' => $payload,
        ]);
    }

    /**
     * Lista todos os webhook apps cadastrados.
     *
     * GET /api/v0/webhook
     *
     * @return array Lista de webhooks
     * @throws SuperFreteException
     */
    public function listWebhooks(): array
    {
        return $this->request('GET', '/api/v0/webhook');
    }

    /**
     * Atualiza um webhook app (atualização parcial — só campos enviados).
     *
     * PUT /api/v0/webhook/{id}
     *
     * @param string $webhookId ID do webhook app
     * @param array{name?: string, url?: string, events?: string[], is_active?: bool} $data
     * @return array Dados atualizados
     * @throws SuperFreteException
     */
    public function updateWebhook(string $webhookId, array $data): array
    {
        return $this->request('PUT', '/api/v0/webhook/' . $webhookId, [
            'json' => $data,
        ]);
    }

    /**
     * Deleta um webhook app (remoção definitiva).
     *
     * DELETE /api/v0/webhook/{id}
     *
     * @param string $webhookId ID do webhook app
     * @return array Resposta (geralmente vazia)
     * @throws SuperFreteException
     */
    public function deleteWebhook(string $webhookId): array
    {
        return $this->request('DELETE', '/api/v0/webhook/' . $webhookId);
    }

    // =====================================================================
    //  MÉTODOS AUXILIARES PÚBLICOS
    // =====================================================================

    /**
     * Padroniza CEP para 8 dígitos sem hífen.
     *
     * Aceita formatos:
     *   "01153-000" → "01153000"
     *   "01153000"  → "01153000"
     */
    public static function normalizePostalCode(string $postalCode): string
    {
        return preg_replace('/\D/', '', $postalCode);
    }

    /**
     * Garante que o nome tenha nome + sobrenome.
     * Se tiver apenas 1 palavra, prefixa "Loja ".
     *
     * Exemplo: "SuperFrete" → "Loja SuperFrete"
     *          "João Silva" → "João Silva"
     */
    public static function ensureFullName(string $name): string
    {
        $name = trim($name);
        $parts = preg_split('/\s+/', $name);
        if (count($parts) < 2 || $parts[0] === null) {
            return 'Loja ' . $name;
        }
        return $name;
    }

    /**
     * Valida e formata telefone para exatamente 11 dígitos.
     *
     * Regra: sem máscara, exatamente 11 caracteres numéricos.
     * Exemplo: "(11) 99999-9999" → "11999999999"
     *
     * @throws \InvalidArgumentException Se telefone inválido
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) !== 11) {
            throw new \InvalidArgumentException(
                "Telefone deve ter exatamente 11 dígitos (DDD + número). Recebido: '$phone' → '$digits'"
            );
        }
        return $digits;
    }

    /**
     * Mascara token para log: mostra apenas os últimos 4 caracteres.
     *
     * Exemplo: "eyJhbGciOi...abc123" → "****c123"
     */
    public static function maskToken(string $token): string
    {
        if (strlen($token) <= 4) {
            return '****';
        }
        return str_repeat('*', strlen($token) - 4) . substr($token, -4);
    }

    // =====================================================================
    //  MÉTODO INTERNO DE REQUISIÇÃO
    // =====================================================================

    /**
     * Executa uma requisição HTTP e trata erros da API SuperFrete.
     *
     * Aplica retry com backoff exponencial para falhas transitórias
     * (sem conexão, timeout, HTTP 429 ou 5xx). Erros de negócio (4xx)
     * são propagados sem retry.
     *
     * @param string $method  Método HTTP (GET, POST, PUT, DELETE)
     * @param string $uri     URI relativa (ex: /api/v0/calculator)
     * @param array  $options Opções do Guzzle (json, query, etc.)
     * @return array Resposta decodificada do JSON
     * @throws SuperFreteException Em caso de erro da API
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        $attempts = $this->maxRetries + 1;
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->performRequest($method, $uri, $options);
            } catch (SuperFreteException $e) {
                $lastError = $e;
                if ($attempt >= $attempts || !$this->isRetryable($e)) {
                    throw $e;
                }

                // Backoff exponencial: base, 2x base, 4x base... (limitado a 3s)
                $delayMs = min(3000, $this->retryBaseMs * (2 ** ($attempt - 1)));
                $this->log("<<< RETRY $attempt/$this->maxRetries em {$delayMs}ms ({$e->getMessage()})");
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        // Inalcançável (o loop sempre retorna ou lança), mantém o analisador feliz.
        throw $lastError ?? new SuperFreteException('unknown', 'Falha desconhecida na requisição');
    }

    /**
     * Indica se o erro permite nova tentativa (transitório).
     */
    private function isRetryable(SuperFreteException $e): bool
    {
        $code = $e->getGrpcCode();
        if (in_array((string) $code, ['unavailable', 'deadline-exceeded', 'resource-exhausted'], true)) {
            return true;
        }

        $status = $e->getHttpStatus();
        return $status === 429 || $status >= 500;
    }

    /**
     * Executa UMA tentativa HTTP e converte erros em SuperFreteException.
     *
     * @return array
     * @throws SuperFreteException
     */
    private function performRequest(string $method, string $uri, array $options = []): array
    {
        $this->log(">>> $method $uri");

        try {
            $response = $this->httpClient->request($method, $uri, $options);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $data = json_decode($body, true) ?? [];

            $this->log("<<< HTTP $statusCode (" . strlen($body) . " bytes)");

            return $data;
        } catch (ConnectException $e) {
            $this->log("<<< ERRO DE CONEXÃO: " . $e->getMessage());
            throw new SuperFreteException(
                'unavailable',
                'Falha de conexão com a API SuperFrete: ' . $e->getMessage(),
                0,
                [],
                $e
            );
        } catch (TimeoutException $e) {
            $this->log("<<< TIMEOUT: " . $e->getMessage());
            throw new SuperFreteException(
                'deadline-exceeded',
                'Timeout na requisição à API SuperFrete',
                0,
                [],
                $e
            );
        } catch (GuzzleException $e) {
            $this->log("<<< ERRO Guzzle: " . $e->getMessage());

            // Tentar extrair corpo de erro da resposta
            $response = null;
            $statusCode = 0;
            $body = '{}';

            if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();
            }

            $errorData = json_decode($body, true) ?? [];

            // Extrair código gRPC do erro
            $grpcCode = $errorData['code'] ?? $errorData['grpc_code'] ?? 'unknown';
            $message  = $errorData['message'] ?? $e->getMessage();

            $this->log("<<< ERRO API: [$grpcCode] $message (HTTP $statusCode)");
            $this->log("<<< BODY: " . substr($body, 0, 500));

            throw new SuperFreteException(
                (string) $grpcCode,
                $message,
                $statusCode,
                $errorData,
                $e
            );
        }
    }

    /**
     * Registra mensagem no log (se configurado).
     * NUNCA loga tokens ou secret_tokens.
     */
    private function log(string $message): void
    {
        if ($this->logHandle === null) {
            return;
        }

        $safeMessage = $message;

        // Mascara token se aparecer no log
        if (str_contains($safeMessage, $this->token)) {
            $safeMessage = str_replace($this->token, '****', $safeMessage);
        }

        $timestamp = date('Y-m-d H:i:s');
        fwrite($this->logHandle, "[$timestamp] $safeMessage" . PHP_EOL);
    }
}
