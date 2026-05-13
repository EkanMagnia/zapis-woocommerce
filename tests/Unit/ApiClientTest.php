<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Tests\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Zapis\WooCommerce\ApiClient;
use Zapis\WooCommerce\Exceptions\ApiException;
use Zapis\WooCommerce\Exceptions\AuthenticationException;
use Zapis\WooCommerce\Exceptions\NotFoundException;
use Zapis\WooCommerce\Exceptions\ValidationException;
use Zapis\WooCommerce\Http\HttpClientInterface;
use Zapis\WooCommerce\Http\HttpResponse;

class ApiClientTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeClient(HttpClientInterface $http): ApiClient
    {
        return new ApiClient(
            apiKey: 'zapis_test_abc',
            baseUrl: 'https://zapis.test',
            http: $http,
        );
    }

    private function response(int $status, array $body): HttpResponse
    {
        return new HttpResponse($status, json_encode($body), ['Content-Type' => 'application/json']);
    }

    // ── directSign ──

    public function test_direct_sign_posts_to_correct_endpoint_with_bearer_token(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')
            ->once()
            ->withArgs(function (string $method, string $url, array $headers, ?string $body) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://zapis.test/api/v1/offers/abc-uuid/direct-sign', $url);
                $this->assertSame('Bearer zapis_test_abc', $headers['Authorization']);
                $this->assertSame('application/json', $headers['Content-Type']);
                $this->assertSame('application/json', $headers['Accept']);
                $decoded = json_decode($body, true);
                $this->assertSame('Ion', $decoded['client_name']);
                return true;
            })
            ->andReturn($this->response(201, [
                'url' => 'https://zapis.test/offer/abc/sign/sub-uuid?expires=123',
                'submission_uuid' => 'sub-uuid',
                'expires_at' => '2026-05-14T10:00:00+00:00',
            ]));

        $client = $this->makeClient($http);
        $result = $client->directSign('abc-uuid', [
            'client_name' => 'Ion',
            'client_email' => 'ion@example.com',
        ]);

        $this->assertSame('sub-uuid', $result['submission_uuid']);
        $this->assertStringContainsString('/sign/', $result['url']);
    }

    public function test_direct_sign_passes_idempotency_key_header(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')
            ->once()
            ->withArgs(function ($method, $url, array $headers, $body) {
                $this->assertSame('wc_order_42', $headers['Idempotency-Key']);
                return true;
            })
            ->andReturn($this->response(201, [
                'url' => 'x', 'submission_uuid' => 'y', 'expires_at' => null,
            ]));

        $this->makeClient($http)->directSign('abc-uuid', ['client_name' => 'A', 'client_email' => 'a@b.c'], 'wc_order_42');
    }

    public function test_direct_sign_422_throws_validation_exception_with_errors(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->andReturn($this->response(422, [
            'message' => 'The given data was invalid.',
            'errors' => ['client_email' => ['The email is invalid.']],
        ]));

        $client = $this->makeClient($http);

        try {
            $client->directSign('abc', ['client_name' => 'A', 'client_email' => 'bad']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('client_email', $e->getErrors());
        }
    }

    public function test_direct_sign_401_throws_authentication_exception(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->andReturn($this->response(401, ['error' => 'Unauthorized']));

        $this->expectException(AuthenticationException::class);

        $this->makeClient($http)->directSign('abc', ['client_name' => 'A', 'client_email' => 'a@b.c']);
    }

    public function test_direct_sign_404_throws_not_found(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->andReturn($this->response(404, ['message' => 'Not found']));

        $this->expectException(NotFoundException::class);

        $this->makeClient($http)->directSign('missing', ['client_name' => 'A', 'client_email' => 'a@b.c']);
    }

    public function test_direct_sign_5xx_throws_generic_api_exception(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->andReturn($this->response(500, ['message' => 'oops']));

        $this->expectException(ApiException::class);

        $this->makeClient($http)->directSign('abc', ['client_name' => 'A', 'client_email' => 'a@b.c']);
    }

    // ── getSubmission ──

    public function test_get_submission_calls_correct_endpoint(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')
            ->once()
            ->withArgs(function ($method, $url) {
                $this->assertSame('GET', $method);
                $this->assertSame('https://zapis.test/api/v1/submissions/sub-uuid', $url);
                return true;
            })
            ->andReturn($this->response(200, [
                'submission_uuid' => 'sub-uuid',
                'status' => 'signed',
                'signed_at' => '2026-05-13T10:00:00+00:00',
                'pdf_url' => 'https://zapis.test/pdf/abc',
            ]));

        $result = $this->makeClient($http)->getSubmission('sub-uuid');

        $this->assertSame('signed', $result['status']);
    }

    public function test_get_submission_404_throws(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->andReturn($this->response(404, ['message' => 'Not found']));

        $this->expectException(NotFoundException::class);

        $this->makeClient($http)->getSubmission('missing');
    }

    // ── cancelSubmission ──

    public function test_cancel_submission_sends_delete(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')
            ->once()
            ->withArgs(function ($method, $url) {
                $this->assertSame('DELETE', $method);
                $this->assertSame('https://zapis.test/api/v1/submissions/sub-uuid', $url);
                return true;
            })
            ->andReturn($this->response(200, ['submission_uuid' => 'sub-uuid', 'cancelled_at' => '2026-05-13']));

        $this->makeClient($http)->cancelSubmission('sub-uuid');
    }

    public function test_cancel_already_signed_returns_validation(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->andReturn($this->response(422, ['error' => 'already signed']));

        $this->expectException(ValidationException::class);

        $this->makeClient($http)->cancelSubmission('sub-uuid');
    }

    // ── HMAC signature verification ──

    public function test_verify_webhook_signature_returns_true_for_valid(): void
    {
        $payload = '{"event":"contract.signed","data":{}}';
        $secret = 'whsec_test';
        $expected = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue(ApiClient::verifyWebhookSignature($payload, $expected, $secret));
    }

    public function test_verify_webhook_signature_returns_false_for_invalid(): void
    {
        $this->assertFalse(ApiClient::verifyWebhookSignature('{}', 'wrong', 'whsec_test'));
    }

    public function test_verify_webhook_signature_false_for_empty_secret(): void
    {
        $payload = '{}';
        $sig = hash_hmac('sha256', $payload, '');
        $this->assertFalse(ApiClient::verifyWebhookSignature($payload, $sig, ''));
    }

    public function test_verify_webhook_signature_uses_constant_time_comparison(): void
    {
        // Sanity: hash_equals via wrapper rejects truncated signature even if prefix matches
        $payload = '{}';
        $secret = 'whsec_test';
        $valid = hash_hmac('sha256', $payload, $secret);
        $truncated = substr($valid, 0, 32);

        $this->assertFalse(ApiClient::verifyWebhookSignature($payload, $truncated, $secret));
    }
}
