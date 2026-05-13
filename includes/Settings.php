<?php

declare(strict_types=1);

namespace Zapis\WooCommerce;

/**
 * Plugin settings: API credentials, default offer, webhook secret.
 * Registers a top-level admin menu and persists values via WP Options API.
 */
final class Settings
{
    public const OPTION_API_KEY = 'zapis_wc_api_key';

    public const OPTION_DEFAULT_OFFER_UUID = 'zapis_wc_default_offer_uuid';

    public const OPTION_WEBHOOK_SECRET = 'zapis_wc_webhook_secret';

    public const OPTION_API_BASE_URL = 'zapis_wc_api_base_url';

    public const MENU_SLUG = 'zapis-contracts';

    public const DEFAULT_API_BASE_URL = 'https://zapis.io';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenu']);
        add_action('admin_init', [self::class, 'registerOptions']);
    }

    public static function addMenu(): void
    {
        add_menu_page(
            __('Zapis Contracts', 'zapis-woocommerce'),
            __('Zapis Contracts', 'zapis-woocommerce'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [self::class, 'renderPage'],
            'dashicons-clipboard',
            58
        );
    }

    public static function registerOptions(): void
    {
        register_setting('zapis_wc_settings', self::OPTION_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeApiKey'],
            'default' => '',
        ]);
        register_setting('zapis_wc_settings', self::OPTION_DEFAULT_OFFER_UUID, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeOfferUuid'],
            'default' => '',
        ]);
        register_setting('zapis_wc_settings', self::OPTION_WEBHOOK_SECRET, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeWebhookSecret'],
            'default' => '',
        ]);
        register_setting('zapis_wc_settings', self::OPTION_API_BASE_URL, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeApiBaseUrl'],
            'default' => '',
        ]);
    }

    // ── Sanitizers ──

    public static function sanitizeApiKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (! str_starts_with($value, 'zapis_')) {
            return '';
        }

        return $value;
    }

    public static function sanitizeOfferUuid(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
        if (! preg_match($pattern, $value)) {
            return '';
        }

        return $value;
    }

    public static function sanitizeWebhookSecret(string $value): string
    {
        return trim($value);
    }

    public static function sanitizeApiBaseUrl(string $value): string
    {
        $value = rtrim(trim($value), '/');
        if ($value === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $value)) {
            return '';
        }
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $value;
    }

    // ── Accessors ──

    public static function getApiKey(): string
    {
        return (string) get_option(self::OPTION_API_KEY, '');
    }

    public static function getDefaultOfferUuid(): string
    {
        return (string) get_option(self::OPTION_DEFAULT_OFFER_UUID, '');
    }

    public static function getWebhookSecret(): string
    {
        return (string) get_option(self::OPTION_WEBHOOK_SECRET, '');
    }

    public static function getApiBaseUrl(): string
    {
        $stored = (string) get_option(self::OPTION_API_BASE_URL, '');

        return $stored !== '' ? $stored : self::DEFAULT_API_BASE_URL;
    }

    public static function isConfigured(): bool
    {
        return self::getApiKey() !== '' && self::getDefaultOfferUuid() !== '';
    }

    // ── View rendering ──

    public static function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'zapis-woocommerce'));
        }

        $apiKey = self::getApiKey();
        $defaultOfferUuid = self::getDefaultOfferUuid();
        $webhookSecret = self::getWebhookSecret();
        $apiBaseUrl = self::getApiBaseUrl();
        $webhookUrl = home_url('/?zapis_webhook=1');

        include ZAPIS_WC_PLUGIN_DIR . 'views/admin/settings-page.php';
    }
}
