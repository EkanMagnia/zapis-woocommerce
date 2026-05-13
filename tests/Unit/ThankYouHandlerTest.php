<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Zapis\WooCommerce\OrderHandler;
use Zapis\WooCommerce\ThankYouHandler;

class ThankYouHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg(1);
        Functions\when('esc_html__')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);
        Functions\when('esc_attr')->returnArg(1);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeOrder(array $metaMap): \WC_Order
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_meta')
            ->andReturnUsing(fn ($key) => $metaMap[$key] ?? '');

        return $order;
    }

    // ── renderForOrder ──

    public function test_render_returns_empty_when_no_submission(): void
    {
        $order = $this->makeOrder([]);

        $this->assertSame('', ThankYouHandler::renderForOrder($order));
    }

    public function test_render_returns_empty_when_contract_already_signed(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/x',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_SIGNED,
        ]);

        $this->assertSame('', ThankYouHandler::renderForOrder($order));
    }

    public function test_render_returns_empty_when_cancelled(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/x',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_CANCELLED,
        ]);

        $this->assertSame('', ThankYouHandler::renderForOrder($order));
    }

    public function test_render_returns_html_with_signing_url_when_pending(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/sub-uuid?expires=123',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_PENDING,
        ]);

        $html = ThankYouHandler::renderForOrder($order);

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('https://zapis.test/sign/sub-uuid', $html);
        $this->assertStringContainsString('<a', $html);
    }

    // ── appendToEmail (text body for WC emails) ──

    public function test_email_text_empty_when_no_submission(): void
    {
        $order = $this->makeOrder([]);

        $this->assertSame('', ThankYouHandler::emailText($order));
    }

    public function test_email_text_contains_signing_link_when_pending(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/x',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_PENDING,
        ]);

        $text = ThankYouHandler::emailText($order);

        $this->assertStringContainsString('https://zapis.test/sign/x', $text);
    }

    public function test_email_text_empty_when_signed(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/x',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_SIGNED,
        ]);

        $this->assertSame('', ThankYouHandler::emailText($order));
    }
}
