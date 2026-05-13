<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Exceptions;

class ValidationException extends ApiException
{
    public function getErrors(): array
    {
        $body = $this->getResponseBody();

        return is_array($body['errors'] ?? null) ? $body['errors'] : [];
    }
}
