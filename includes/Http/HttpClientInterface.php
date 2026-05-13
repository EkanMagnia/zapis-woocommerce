<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Http;

interface HttpClientInterface
{
    /**
     * Perform an HTTP request.
     *
     * @param string                $method  HTTP method (GET, POST, DELETE, ...).
     * @param string                $url     Absolute URL.
     * @param array<string, string> $headers Header key => value pairs.
     * @param string|null           $body    Raw request body (already encoded).
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse;
}
