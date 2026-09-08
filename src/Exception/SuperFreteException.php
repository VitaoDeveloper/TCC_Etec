<?php

declare(strict_types=1);

namespace TCC\Exception;

/**
 * Exceção tipada para erros da API SuperFrete.
 *
 * Cada instância carrega o código gRPC de erro retornado pela API,
 * a mensagem legível e o body JSON completo da resposta para diagnóstico.
 *
 * Códigos gRPC possíveis (lista parcial documentada):
 *   cancelled, unknown, invalid-argument, deadline-exceeded,
 *   not-found, already-exists, permission-denied, resource-exhausted,
 *   failed-precondition, aborted, out-of-range, unimplemented,
 *   internal, unavailable, data-loss, unauthenticated
 */
class SuperFreteException extends \RuntimeException
{
    /**
     * @var string Código gRPC do erro (ex: "invalid-argument")
     */
    private string $grpcCode;

    /**
     * @var array Body JSON completo da resposta de erro
     */
    private array $responseBody;

    /**
     * @var int HTTP status code retornado pela API
     */
    private int $httpStatus;

    public function __construct(
        string $grpcCode,
        string $message,
        int $httpStatus,
        array $responseBody = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->grpcCode = $grpcCode;
        $this->httpStatus = $httpStatus;
        $this->responseBody = $responseBody;
    }

    /**
     * Código gRPC do erro retornado pela API SuperFrete.
     */
    public function getGrpcCode(): string
    {
        return $this->grpcCode;
    }

    /**
     * HTTP status code da resposta.
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Body JSON completo da resposta de erro (útil para debug).
     */
    public function getResponseBody(): array
    {
        return $this->responseBody;
    }

    /**
     * Representação legível da exceção (sem expor tokens).
     */
    public function __toString(): string
    {
        return sprintf(
            'SuperFreteException [%s] (HTTP %d): %s',
            $this->grpcCode,
            $this->httpStatus,
            $this->getMessage()
        );
    }
}
