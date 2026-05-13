<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Exceptions;

class ApiException extends \RuntimeException
{
    private int $statusCode;
    private array $responseBody;

    public function __construct(string $message, int $statusCode = 0, array $responseBody = [])
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): array
    {
        return $this->responseBody;
    }
}
