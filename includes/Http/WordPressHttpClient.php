<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Http;

use Zapis\WooCommerce\Exceptions\ApiException;

/**
 * HTTP client backed by WordPress's wp_remote_request.
 */
final class WordPressHttpClient implements HttpClientInterface
{
    public function __construct(private int $timeout = 15)
    {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $args = [
            'method' => strtoupper($method),
            'headers' => $headers,
            'timeout' => $this->timeout,
            'body' => $body,
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new ApiException(
                'HTTP request failed: ' . $response->get_error_message(),
                0
            );
        }

        return new HttpResponse(
            (int) wp_remote_retrieve_response_code($response),
            (string) wp_remote_retrieve_body($response),
            (array) wp_remote_retrieve_headers($response)
        );
    }
}
