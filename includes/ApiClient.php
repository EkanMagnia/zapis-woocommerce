<?php

declare(strict_types=1);

namespace Zapis\WooCommerce;

use Zapis\WooCommerce\Exceptions\ApiException;
use Zapis\WooCommerce\Exceptions\AuthenticationException;
use Zapis\WooCommerce\Exceptions\NotFoundException;
use Zapis\WooCommerce\Exceptions\ValidationException;
use Zapis\WooCommerce\Http\HttpClientInterface;
use Zapis\WooCommerce\Http\HttpResponse;
use Zapis\WooCommerce\Http\WordPressHttpClient;

/**
 * Client for the Zapis public REST API (v1). Pure PHP — receives an
 * HttpClientInterface so it can be unit-tested without WordPress.
 */
final class ApiClient
{
    private string $apiKey;
    private string $baseUrl;
    private HttpClientInterface $http;

    public function __construct(string $apiKey, string $baseUrl, ?HttpClientInterface $http = null)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = $http ?? new WordPressHttpClient();
    }

    /**
     * Create a direct-sign submission for an offer.
     *
     * @param array<string, mixed> $payload  See Zapis API docs for fields.
     * @param string|null          $idempotencyKey Optional, for safe retries.
     *
     * @return array{url:string, submission_uuid:string, expires_at:?string}
     */
    public function directSign(string $offerUuid, array $payload, ?string $idempotencyKey = null): array
    {
        $headers = [];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $response = $this->send(
            'POST',
            "/api/v1/offers/{$offerUuid}/direct-sign",
            $payload,
            $headers
        );

        return $response->json();
    }

    /**
     * Fetch submission status.
     *
     * @return array<string, mixed>
     */
    public function getSubmission(string $submissionUuid): array
    {
        return $this->send('GET', "/api/v1/submissions/{$submissionUuid}")->json();
    }

    /**
     * Cancel an in-progress submission.
     *
     * @return array<string, mixed>
     */
    public function cancelSubmission(string $submissionUuid): array
    {
        return $this->send('DELETE', "/api/v1/submissions/{$submissionUuid}")->json();
    }

    /**
     * Verify an HMAC-SHA256 webhook signature using constant-time comparison.
     */
    public static function verifyWebhookSignature(string $payload, string $providedSignature, string $secret): bool
    {
        if ($secret === '' || $providedSignature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $providedSignature);
    }

    // ── Internals ──

    private function send(string $method, string $path, ?array $body = null, array $extraHeaders = []): HttpResponse
    {
        $url = $this->baseUrl . $path;
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $extraHeaders);

        $rawBody = $body !== null ? (string) json_encode($body) : null;

        $response = $this->http->request($method, $url, $headers, $rawBody);

        if ($response->isSuccessful()) {
            return $response;
        }

        $this->throwForResponse($response);
    }

    private function throwForResponse(HttpResponse $response): void
    {
        $status = $response->status;
        $decoded = $response->json();
        $message = $decoded['message'] ?? $decoded['error'] ?? "Zapis API returned HTTP {$status}";

        if ($status === 401 || $status === 403) {
            throw new AuthenticationException($message, $status, $decoded);
        }

        if ($status === 404) {
            throw new NotFoundException($message, $status, $decoded);
        }

        if ($status === 422) {
            throw new ValidationException($message, $status, $decoded);
        }

        throw new ApiException($message, $status, $decoded);
    }
}
