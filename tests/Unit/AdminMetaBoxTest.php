<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Zapis\WooCommerce\AdminMetaBox;
use Zapis\WooCommerce\OrderHandler;

class AdminMetaBoxTest extends TestCase
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
        Functions\when('wp_nonce_field')->justReturn('<input name="_wpnonce" />');
        Functions\when('admin_url')->returnArg(1);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeOrder(array $metaMap, int $id = 42): \WC_Order
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($id);
        $order->shouldReceive('get_meta')
            ->andReturnUsing(fn ($key) => $metaMap[$key] ?? '');

        return $order;
    }

    public function test_renders_not_configured_when_no_submission(): void
    {
        $order = $this->makeOrder([]);

        $html = AdminMetaBox::renderFor($order);

        $this->assertStringContainsString('no contract', strtolower($html));
    }

    public function test_renders_pending_state_with_signing_link(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid-123',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/abc',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_PENDING,
        ]);

        $html = AdminMetaBox::renderFor($order);

        $this->assertStringContainsString('sub-uuid-123', $html);
        $this->assertStringContainsString('https://zapis.test/sign/abc', $html);
        $this->assertStringContainsString('Pending', $html);
    }

    public function test_renders_signed_state_with_pdf_link(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_SIGNED,
            OrderHandler::META_PDF_URL => 'https://zapis.test/pdf/abc',
        ]);

        $html = AdminMetaBox::renderFor($order);

        $this->assertStringContainsString('Signed', $html);
        $this->assertStringContainsString('https://zapis.test/pdf/abc', $html);
    }

    public function test_renders_cancelled_state(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_CANCELLED,
        ]);

        $html = AdminMetaBox::renderFor($order);

        $this->assertStringContainsString('Cancelled', $html);
    }

    public function test_pending_state_shows_resend_button(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/abc',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_PENDING,
        ]);

        $html = AdminMetaBox::renderFor($order);

        $this->assertStringContainsString('resend', strtolower($html));
    }

    public function test_signed_state_does_not_show_resend_button(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_SIGNED,
        ]);

        $html = AdminMetaBox::renderFor($order);

        $this->assertStringNotContainsString('Resend signing email', $html);
    }
}
