<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Zapis\WooCommerce\Settings;

class SettingsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── Registration ──

    public function test_register_hooks_admin_menu_and_admin_init(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('admin_menu', \Mockery::type('callable'));
        Functions\expect('add_action')
            ->once()
            ->with('admin_init', \Mockery::type('callable'));

        Settings::register();
    }

    public function test_add_menu_calls_add_menu_page_with_top_level_slug(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\expect('add_menu_page')
            ->once()
            ->with(
                \Mockery::type('string'),
                \Mockery::type('string'),
                'manage_woocommerce',
                Settings::MENU_SLUG,
                \Mockery::type('callable'),
                \Mockery::type('string'),
                \Mockery::type('int')
            );

        Settings::addMenu();
    }

    // ── Sanitization ──

    public function test_sanitize_api_key_accepts_zapis_prefixed_keys(): void
    {
        $this->assertSame('zapis_abc123', Settings::sanitizeApiKey('zapis_abc123'));
        $this->assertSame('zapis_a1B2c3D4', Settings::sanitizeApiKey('  zapis_a1B2c3D4  '));
    }

    public function test_sanitize_api_key_rejects_non_prefixed_strings(): void
    {
        $this->assertSame('', Settings::sanitizeApiKey('sk_live_abc'));
        $this->assertSame('', Settings::sanitizeApiKey('random'));
    }

    public function test_sanitize_api_key_allows_empty_string(): void
    {
        $this->assertSame('', Settings::sanitizeApiKey(''));
        $this->assertSame('', Settings::sanitizeApiKey('   '));
    }

    public function test_sanitize_offer_uuid_accepts_valid_uuid(): void
    {
        $valid = '9568f502-1634-4c33-adb3-9dd94ebd001d';
        $this->assertSame($valid, Settings::sanitizeOfferUuid($valid));
        $this->assertSame($valid, Settings::sanitizeOfferUuid('  ' . $valid . '  '));
    }

    public function test_sanitize_offer_uuid_rejects_invalid_format(): void
    {
        $this->assertSame('', Settings::sanitizeOfferUuid('not-a-uuid'));
        $this->assertSame('', Settings::sanitizeOfferUuid('1234'));
        $this->assertSame('', Settings::sanitizeOfferUuid('9568f502-1634-4c33-adb3'));
    }

    public function test_sanitize_offer_uuid_lowercases(): void
    {
        $upper = '9568F502-1634-4C33-ADB3-9DD94EBD001D';
        $this->assertSame('9568f502-1634-4c33-adb3-9dd94ebd001d', Settings::sanitizeOfferUuid($upper));
    }

    public function test_sanitize_webhook_secret_trims_and_keeps_arbitrary(): void
    {
        $this->assertSame('whsec_abc123', Settings::sanitizeWebhookSecret('  whsec_abc123  '));
        $this->assertSame('any-string-here', Settings::sanitizeWebhookSecret('any-string-here'));
    }

    public function test_sanitize_api_base_url_accepts_https(): void
    {
        $this->assertSame('https://zapis.app', Settings::sanitizeApiBaseUrl('https://zapis.app/'));
        $this->assertSame('https://staging.zapis.app', Settings::sanitizeApiBaseUrl('  https://staging.zapis.app/  '));
    }

    public function test_sanitize_api_base_url_rejects_invalid_scheme(): void
    {
        $this->assertSame('', Settings::sanitizeApiBaseUrl('ftp://nope'));
        $this->assertSame('', Settings::sanitizeApiBaseUrl('not-a-url'));
    }

    // ── Accessors ──

    public function test_get_api_key_reads_option(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_API_KEY, '')
            ->andReturn('zapis_stored_value');

        $this->assertSame('zapis_stored_value', Settings::getApiKey());
    }

    public function test_get_default_offer_uuid_reads_option(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_DEFAULT_OFFER_UUID, '')
            ->andReturn('9568f502-1634-4c33-adb3-9dd94ebd001d');

        $this->assertSame('9568f502-1634-4c33-adb3-9dd94ebd001d', Settings::getDefaultOfferUuid());
    }

    public function test_get_webhook_secret_reads_option(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_WEBHOOK_SECRET, '')
            ->andReturn('whsec_xyz');

        $this->assertSame('whsec_xyz', Settings::getWebhookSecret());
    }

    public function test_get_api_base_url_falls_back_to_default(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_API_BASE_URL, '')
            ->andReturn('');

        $this->assertSame('https://zapis.app', Settings::getApiBaseUrl());
    }

    public function test_is_configured_true_when_key_and_offer_set(): void
    {
        Functions\when('get_option')->alias(function ($key) {
            return match ($key) {
                Settings::OPTION_API_KEY => 'zapis_abc',
                Settings::OPTION_DEFAULT_OFFER_UUID => '9568f502-1634-4c33-adb3-9dd94ebd001d',
                default => '',
            };
        });

        $this->assertTrue(Settings::isConfigured());
    }

    public function test_is_configured_false_when_missing_pieces(): void
    {
        Functions\when('get_option')->alias(fn () => '');

        $this->assertFalse(Settings::isConfigured());
    }
}
