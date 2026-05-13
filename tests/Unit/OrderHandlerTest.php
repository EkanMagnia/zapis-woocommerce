<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Zapis\WooCommerce\ApiClient;
use Zapis\WooCommerce\Exceptions\AuthenticationException;
use Zapis\WooCommerce\Exceptions\ValidationException;
use Zapis\WooCommerce\OrderHandler;

class OrderHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        // Default: silence all WP helpers used in code under test.
        Functions\when('__')->returnArg(1);
        Functions\when('sanitize_text_field')->returnArg(1);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Build a Mockery WC_Order with sensible defaults overridable per-call.
     *
     * @param array<string, mixed> $overrides
     */
    private function makeOrder(int $id = 42, array $overrides = []): \WC_Order
    {
        $defaults = [
            'get_id' => $id,
            'get_formatted_billing_full_name' => 'Ion Popescu',
            'get_billing_email' => 'ion@example.com',
            'get_billing_phone' => '0722123456',
            'get_total' => '499.90',
            'get_currency' => 'RON',
            'get_checkout_order_received_url' => 'https://shop.test/order-received/42',
            'get_meta' => '',
        ];
        $stubs = array_merge($defaults, $overrides);

        $order = Mockery::mock(\WC_Order::class);
        foreach ($stubs as $method => $return) {
            $order->shouldReceive($method)->andReturn($return);
        }
        $order->shouldReceive('update_meta_data')->byDefault();
        $order->shouldReceive('save')->byDefault();
        $order->shouldReceive('add_order_note')->byDefault();
        $order->shouldReceive('get_date_created')
            ->andReturn(new class {
                public function date(string $format): string { return '2026-05-13T10:00:00+00:00'; }
            })
            ->byDefault();

        // Items default: one product line
        if (! isset($overrides['get_items'])) {
            $item = Mockery::mock(\WC_Order_Item_Product::class);
            $item->shouldReceive('get_name')->andReturn('Curs PHP avansat');
            $item->shouldReceive('get_quantity')->andReturn(1);
            $item->shouldReceive('get_subtotal')->andReturn('499.90');
            $item->shouldReceive('get_product_id')->andReturn(7);
            $order->shouldReceive('get_items')->andReturn(['L1' => $item]);
        }

        return $order;
    }

    // ── buildPayload (pure data mapping) ──

    public function test_build_payload_maps_billing_data_and_external_order_id(): void
    {
        $order = $this->makeOrder(42);

        $payload = OrderHandler::buildPayload($order);

        $this->assertSame('Ion Popescu', $payload['client_name']);
        $this->assertSame('ion@example.com', $payload['client_email']);
        $this->assertSame('0722123456', $payload['client_phone']);
        $this->assertSame('42', $payload['external_order_id']);
        $this->assertSame('https://shop.test/order-received/42', $payload['redirect_url']);
    }

    public function test_build_payload_maps_order_total_and_currency(): void
    {
        $order = $this->makeOrder();

        $payload = OrderHandler::buildPayload($order);

        $this->assertSame(499.90, $payload['order']['total']);
        $this->assertSame('RON', $payload['order']['currency']);
        $this->assertSame('2026-05-13T10:00:00+00:00', $payload['order']['placed_at']);
    }

    public function test_build_payload_maps_line_items(): void
    {
        $order = $this->makeOrder();

        $payload = OrderHandler::buildPayload($order);

        $this->assertCount(1, $payload['order']['items']);
        $this->assertSame('Curs PHP avansat', $payload['order']['items'][0]['name']);
        $this->assertSame(1, $payload['order']['items'][0]['quantity']);
        $this->assertSame(499.90, $payload['order']['items'][0]['price']);
    }

    // ── handlePaymentComplete behavior ──

    public function test_skips_if_no_order_found(): void
    {
        Functions\when('wc_get_order')->justReturn(false);

        $apiClient = Mockery::mock(ApiClient::class);
        $apiClient->shouldNotReceive('directSign');

        $handler = new OrderHandler($apiClient, fn () => 'offer-uuid');
        $handler->handlePaymentComplete(99);
    }

    public function test_skips_if_order_already_has_submission(): void
    {
        $order = $this->makeOrder(42, ['get_meta' => 'existing-submission-uuid']);
        Functions\when('wc_get_order')->justReturn($order);

        $apiClient = Mockery::mock(ApiClient::class);
        $apiClient->shouldNotReceive('directSign');

        $handler = new OrderHandler($apiClient, fn () => 'offer-uuid');
        $handler->handlePaymentComplete(42);
    }

    public function test_skips_if_offer_uuid_resolver_returns_empty(): void
    {
        $order = $this->makeOrder(42);
        $order->shouldReceive('add_order_note')
            ->with(Mockery::on(fn ($note) => str_contains($note, 'offer UUID')));
        Functions\when('wc_get_order')->justReturn($order);

        $apiClient = Mockery::mock(ApiClient::class);
        $apiClient->shouldNotReceive('directSign');

        $handler = new OrderHandler($apiClient, fn () => '');
        $handler->handlePaymentComplete(42);
    }

    public function test_calls_direct_sign_with_correct_idempotency_key(): void
    {
        $order = $this->makeOrder(42);
        Functions\when('wc_get_order')->justReturn($order);

        $apiClient = Mockery::mock(ApiClient::class);
        $apiClient->shouldReceive('directSign')
            ->once()
            ->withArgs(function (string $offerUuid, array $payload, ?string $idempotencyKey) {
                $this->assertSame('offer-abc', $offerUuid);
                $this->assertSame('wc_order_42', $idempotencyKey);
                $this->assertSame('Ion Popescu', $payload['client_name']);
                return true;
            })
            ->andReturn([
                'url' => 'https://zapis.test/sign/sub-uuid',
                'submission_uuid' => 'sub-uuid',
                'expires_at' => '2026-05-14T10:00:00+00:00',
            ]);

        $handler = new OrderHandler($apiClient, fn () => 'offer-abc');
        $handler->handlePaymentComplete(42);
    }

    public function test_saves_submission_meta_on_success(): void
    {
        $order = $this->makeOrder(42);
        $order->shouldReceive('update_meta_data')
            ->with(OrderHandler::META_SUBMISSION_UUID, 'sub-uuid')->once();
        $order->shouldReceive('update_meta_data')
            ->with(OrderHandler::META_SIGNING_URL, 'https://zapis.test/sign/sub-uuid')->once();
        $order->shouldReceive('update_meta_data')
            ->with(OrderHandler::META_CONTRACT_STATUS, 'pending')->once();
        $order->shouldReceive('save')->once();
        Functions\when('wc_get_order')->justReturn($order);

        $apiClient = Mockery::mock(ApiClient::class);
        $apiClient->shouldReceive('directSign')->andReturn([
            'url' => 'https://zapis.test/sign/sub-uuid',
            'submission_uuid' => 'sub-uuid',
            'expires_at' => '2026-05-14T10:00:00+00:00',
        ]);

        $handler = new OrderHandler($apiClient, fn () => 'offer-abc');
        $handler->handlePaymentComplete(42);
    }

    public function test_logs_order_note_on_authentication_failure(): void
    {
        $order = $this->makeOrder(42);
        $order->shouldReceive('add_order_note')
            ->with(Mockery::on(fn ($note) => str_contains($note, 'API key')));
        $order->shouldNotReceive('update_meta_data')
            ->with(OrderHandler::META_SUBMISSION_UUID, Mockery::any());
        Functions\when('wc_get_order')->justReturn($order);

        $apiClient = Mockery::mock(ApiClient::class);
        $apiClient->shouldReceive('directSign')
            ->andThrow(new AuthenticationException('Invalid API key', 401));

        $handler = new OrderHandler($apiClient, fn () => 'offer-abc');
        $handler->handlePaymentComplete(42);
    }

    public function test_logs_validation_errors_to_order_note(): void
    {
        $capturedNote = null;
        $order = $this->makeOrder(42);
        $order->shouldReceive('add_order_note')
            ->andReturnUsing(function ($note) use (&$capturedNote) {
                $capturedNote = $note;
                return 1;
            });
        Functions\when('wc_get_order')->justReturn($order);

        $apiClient = Mockery::mock(ApiClient::class);
        $apiClient->shouldReceive('directSign')
            ->andThrow(new ValidationException('Validation failed', 422, [
                'errors' => ['client_email' => ['The email is invalid']],
            ]));

        $handler = new OrderHandler($apiClient, fn () => 'offer-abc');
        $handler->handlePaymentComplete(42);

        $this->assertNotNull($capturedNote);
        $this->assertStringContainsString('client_email', $capturedNote);
    }
}
