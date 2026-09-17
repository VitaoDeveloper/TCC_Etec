<?php

declare(strict_types=1);

namespace TCC\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TCC\Exception\SuperFreteException;
use TCC\SuperFreteClient;

/**
 * Testes do retry com backoff do SuperFreteClient.
 *
 * Usa o MockHandler do Guzzle para simular falhas transitórias (5xx) e
 * verificar que a requisição é repetida antes de desistir.
 */
class SuperFreteClientRetryTest extends TestCase
{
    /**
     * Cria um client injetando um MockHandler e expõe o histórico de chamadas.
     *
     * @param array<int, Response|\Throwable> $responses Fila de respostas/erros
     * @param int $maxRetries Tentativas extras
     * @param array<int, array> $history Recebe o histórico por referência
     */
    private function makeClient(array $responses, int $maxRetries, array &$history): SuperFreteClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new Client(['handler' => $stack, 'http_errors' => true]);

        return new SuperFreteClient(
            [
                'SUPERFRETE_TOKEN' => 'tok_test_123',
                'SUPERFRETE_BASE_URL' => 'https://sandbox.superfrete.com/',
                'SUPERFRETE_MAX_RETRIES' => (string) $maxRetries,
                'SUPERFRETE_RETRY_BASE_MS' => '0',
            ],
            null,
            $http
        );
    }

    public function testRetryEmErro500DepoisSucesso(): void
    {
        $history = [];
        $client = $this->makeClient([
            new Response(500, [], '{"message":"boom"}'),
            new Response(200, [], '{"ok":true}'),
        ], 2, $history);

        $result = $client->healthCheck();

        $this->assertSame(['ok' => true], $result);
        $this->assertCount(2, $history, 'Deve tentar novamente após erro 500');
    }

    public function testNaoRetryEmErro400(): void
    {
        $history = [];
        $client = $this->makeClient([
            new Response(400, [], '{"message":"dados invalidos"}'),
            new Response(200, [], '{"ok":true}'),
        ], 2, $history);

        try {
            $client->healthCheck();
            $this->fail('Esperava SuperFreteException para erro 400');
        } catch (SuperFreteException $e) {
            $this->assertSame(400, $e->getHttpStatus());
            $this->assertCount(1, $history, 'Erro 4xx não deve gerar retry');
        }
    }

    public function testRetryEsgotaELancaErro(): void
    {
        $history = [];
        $client = $this->makeClient([
            new Response(503, [], '{"message":"indisponivel"}'),
            new Response(503, [], '{"message":"indisponivel"}'),
            new Response(503, [], '{"message":"indisponivel"}'),
        ], 2, $history);

        try {
            $client->healthCheck();
            $this->fail('Esperava SuperFreteException após esgotar tentativas');
        } catch (SuperFreteException $e) {
            $this->assertSame(503, $e->getHttpStatus());
            $this->assertCount(3, $history, 'Deve esgotar todas as tentativas (1 + 2 retries)');
        }
    }

    public function testSemRetryQuandoMaxRetriesZero(): void
    {
        $history = [];
        $client = $this->makeClient([
            new Response(500, [], '{"message":"boom"}'),
        ], 0, $history);

        try {
            $client->healthCheck();
            $this->fail('Esperava SuperFreteException');
        } catch (SuperFreteException $e) {
            $this->assertCount(1, $history, 'Sem retry deve haver apenas 1 chamada');
        }
    }
}
