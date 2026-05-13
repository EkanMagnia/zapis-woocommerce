<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Zapis\WooCommerce\OrderHandler;
use Zapis\WooCommerce\WebhookReceiver;

class WebhookReceiverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg(1);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeOrder(int $id, string $contractStatus = OrderHandler::STATUS_PENDING): \WC_Order
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($id);
        $order->shouldReceive('get_meta')
            ->with(OrderHandler::META_CONTRACT_STATUS, true)
            ->andReturn($contractStatus);
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('update_meta_data')->byDefault();
        $order->shouldReceive('update_status')->byDefault();
        $order->shouldReceive('save')->byDefault();
        $order->shouldReceive('add_order_note')->byDefault();

        return $order;
    }

    public function test_rejects_request_with_missing_signature(): void
    {
        $receiver = new WebhookReceiver('whsec_test');

        $result = $receiver->process('{}', null);

        $this->assertSame(401, $result['status']);
    }

    public function test_rejects_request_with_invalid_signature(): void
    {
        $receiver = new WebhookReceiver('whsec_test');

        $result = $receiver->process('{"event":"contract.signed"}', 'wrong-signature');

        $this->assertSame(401, $result['status']);
    }

    public function test_rejects_when_no_secret_configured(): void
    {
        $receiver = new WebhookReceiver('');

        $result = $receiver->process('{}', 'any');

        $this->assertSame(503, $result['status']);
    }

    public function test_accepts_valid_signature_but_unsupported_event(): void
    {
        $secret = 'whsec_test';
        $payload = json_encode(['event' => 'unknown.event', 'data' => []]);
        $sig = hash_hmac('sha256', $payload, $secret);

        $receiver = new WebhookReceiver($secret);
        $result = $receiver->process($payload, $sig);

        $this->assertSame(200, $result['status']);
        $this->assertSame('ignored', $result['result']);
    }

    public function test_processes_contract_signed_and_updates_order(): void
    {
        $secret = 'whsec_test';
        $payload = json_encode([
            'event' => 'contract.signed',
            'data' => [
                'external_order_id' => '42',
                'submission_uuid' => 'sub-uuid',
                'pdf_url' => 'https://zapis.test/pdf/abc',
                'signed_at' => '2026-05-13T10:00:00+00:00',
            ],
        ]);
        $sig = hash_hmac('sha256', $payload, $secret);

        $order = $this->makeOrder(42);
        $order->shouldReceive('update_meta_data')
            ->with(OrderHandler::META_CONTRACT_STATUS, OrderHandler::STATUS_SIGNED)->once();
        $order->shouldReceive('update_meta_data')
            ->with(OrderHandler::META_PDF_URL, 'https://zapis.test/pdf/abc')->once();
        $order->shouldReceive('update_status')
            ->with('completed', Mockery::any())->once();
        $order->shouldReceive('save')->once();

        Functions\when('wc_get_order')->justReturn($order);

        $receiver = new WebhookReceiver($secret);
        $result = $receiver->process($payload, $sig);

        $this->assertSame(200, $result['status']);
        $this->assertSame('processed', $result['result']);
    }

    public function test_returns_200_when_order_not_found_but_signature_valid(): void
    {
        $secret = 'whsec_test';
        $payload = json_encode([
            'event' => 'contract.signed',
            'data' => ['external_order_id' => '999', 'submission_uuid' => 'x'],
        ]);
        $sig = hash_hmac('sha256', $payload, $secret);

        Functions\when('wc_get_order')->justReturn(false);

        $receiver = new WebhookReceiver($secret);
        $result = $receiver->process($payload, $sig);

        // Don't reveal whether the order exists; return 200 so Zapis doesn't retry forever.
        $this->assertSame(200, $result['status']);
        $this->assertSame('not_found', $result['result']);
    }

    public function test_idempotent_when_order_already_signed(): void
    {
        $secret = 'whsec_test';
        $payload = json_encode([
            'event' => 'contract.signed',
            'data' => ['external_order_id' => '42', 'submission_uuid' => 'sub-uuid'],
        ]);
        $sig = hash_hmac('sha256', $payload, $secret);

        $order = $this->makeOrder(42, OrderHandler::STATUS_SIGNED);
        $order->shouldNotReceive('update_status');
        Functions\when('wc_get_order')->justReturn($order);

        $receiver = new WebhookReceiver($secret);
        $result = $receiver->process($payload, $sig);

        $this->assertSame(200, $result['status']);
        $this->assertSame('already_processed', $result['result']);
    }

    public function test_rejects_malformed_json(): void
    {
        $secret = 'whsec_test';
        $payload = 'this is not json';
        $sig = hash_hmac('sha256', $payload, $secret);

        $receiver = new WebhookReceiver($secret);
        $result = $receiver->process($payload, $sig);

        $this->assertSame(400, $result['status']);
    }

    public function test_missing_external_order_id_returns_400(): void
    {
        $secret = 'whsec_test';
        $payload = json_encode(['event' => 'contract.signed', 'data' => []]);
        $sig = hash_hmac('sha256', $payload, $secret);

        $receiver = new WebhookReceiver($secret);
        $result = $receiver->process($payload, $sig);

        $this->assertSame(400, $result['status']);
    }
}
