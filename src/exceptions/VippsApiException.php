<?php

declare(strict_types=1);

namespace superbig\vipps\exceptions;

use RuntimeException;

/**
 * Thrown when a Vipps ePayment API call fails.
 * Contains the HTTP status code and response body for debugging.
 */
class VippsApiException extends RuntimeException
{
    private ?array $responseBody;

    private int $httpStatusCode;

    public function __construct(
        string $message,
        int $httpStatusCode = 0,
        ?array $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->responseBody = $responseBody;

        parent::__construct($message, $httpStatusCode, $previous);
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
