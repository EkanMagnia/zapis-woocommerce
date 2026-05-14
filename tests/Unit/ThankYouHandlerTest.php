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
        Functions\when('wp_kses_post')->returnArg(1);
        Functions\when('get_option')->justReturn('');
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

    public function test_render_uses_default_heading_body_and_cta(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/x',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_PENDING,
        ]);

        $html = ThankYouHandler::renderForOrder($order);

        $this->assertStringContainsString('One last step', $html);
        $this->assertStringContainsString('electronic signature', $html);
        $this->assertStringContainsString('Sign contract now', $html);
    }

    public function test_render_uses_admin_customized_texts_when_present(): void
    {
        Functions\when('get_option')->alias(function ($key) {
            return match ($key) {
                \Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_HEADING => 'My title',
                \Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_BODY => '<p>My <strong>body</strong></p>',
                \Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_CTA => 'Click here',
                default => '',
            };
        });

        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/x',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_PENDING,
        ]);

        $html = ThankYouHandler::renderForOrder($order);

        $this->assertStringContainsString('My title', $html);
        $this->assertStringContainsString('<strong>body</strong>', $html);
        $this->assertStringContainsString('Click here', $html);
        $this->assertStringNotContainsString('One last step', $html);
    }

    public function test_render_uses_gradient_design(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/x',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_PENDING,
        ]);

        $html = ThankYouHandler::renderForOrder($order);

        $this->assertStringContainsString('linear-gradient', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('zapis-cta-btn', $html);
    }

    public function test_render_uses_default_colors_when_not_set(): void
    {
        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/x',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_PENDING,
        ]);

        $html = ThankYouHandler::renderForOrder($order);

        $this->assertStringContainsString('#4f46e5', $html);
        $this->assertStringContainsString('#a855f7', $html);
    }

    public function test_render_uses_custom_colors_when_set(): void
    {
        Functions\when('get_option')->alias(function ($key) {
            return match ($key) {
                \Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_COLOR_FROM => '#ff0000',
                \Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_COLOR_TO => '#00ff00',
                default => '',
            };
        });

        $order = $this->makeOrder([
            OrderHandler::META_SUBMISSION_UUID => 'sub-uuid',
            OrderHandler::META_SIGNING_URL => 'https://zapis.test/sign/x',
            OrderHandler::META_CONTRACT_STATUS => OrderHandler::STATUS_PENDING,
        ]);

        $html = ThankYouHandler::renderForOrder($order);

        $this->assertStringContainsString('#ff0000', $html);
        $this->assertStringContainsString('#00ff00', $html);
        $this->assertStringContainsString('rgba(255,0,0,', $html);
    }

    public function test_hex_to_rgba_converts_six_digit_hex(): void
    {
        $this->assertSame('rgba(255,0,0,0.5)', ThankYouHandler::hexToRgba('#ff0000', 0.5));
        $this->assertSame('rgba(79,70,229,0.28)', ThankYouHandler::hexToRgba('#4f46e5', 0.28));
    }

    public function test_hex_to_rgba_falls_back_on_invalid_input(): void
    {
        $this->assertSame('rgba(0,0,0,0.3)', ThankYouHandler::hexToRgba('not-a-color', 0.3));
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
