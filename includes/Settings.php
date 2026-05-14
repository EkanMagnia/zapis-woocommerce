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

    public const OPTION_SIGNING_BOX_HEADING = 'zapis_wc_signing_box_heading';

    public const OPTION_SIGNING_BOX_BODY = 'zapis_wc_signing_box_body';

    public const OPTION_SIGNING_BOX_CTA = 'zapis_wc_signing_box_cta';

    public const OPTION_SIGNING_BOX_COLOR_FROM = 'zapis_wc_signing_box_color_from';

    public const OPTION_SIGNING_BOX_COLOR_TO = 'zapis_wc_signing_box_color_to';

    public const MENU_SLUG = 'zapis-contracts';

    public const DEFAULT_API_BASE_URL = 'https://zapis.io';

    public const DEFAULT_SIGNING_BOX_COLOR_FROM = '#4f46e5';

    public const DEFAULT_SIGNING_BOX_COLOR_TO = '#a855f7';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenu']);
        add_action('admin_init', [self::class, 'registerOptions']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script(
            'wp-color-picker',
            "jQuery(function($){ $('.zapis-color-picker').wpColorPicker(); });"
        );
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
        register_setting('zapis_wc_settings', self::OPTION_SIGNING_BOX_HEADING, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeSigningBoxHeading'],
            'default' => '',
        ]);
        register_setting('zapis_wc_settings', self::OPTION_SIGNING_BOX_BODY, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeSigningBoxBody'],
            'default' => '',
        ]);
        register_setting('zapis_wc_settings', self::OPTION_SIGNING_BOX_CTA, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeSigningBoxCta'],
            'default' => '',
        ]);
        register_setting('zapis_wc_settings', self::OPTION_SIGNING_BOX_COLOR_FROM, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeHexColor'],
            'default' => '',
        ]);
        register_setting('zapis_wc_settings', self::OPTION_SIGNING_BOX_COLOR_TO, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeHexColor'],
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

    public static function sanitizeSigningBoxHeading(string $value): string
    {
        return sanitize_text_field($value);
    }

    public static function sanitizeSigningBoxBody(string $value): string
    {
        return wp_kses_post($value);
    }

    public static function sanitizeSigningBoxCta(string $value): string
    {
        return sanitize_text_field($value);
    }

    public static function sanitizeHexColor(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (! preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return '';
        }
        $value = strtolower($value);
        if (strlen($value) === 4) {
            $value = '#' . str_repeat($value[1], 2) . str_repeat($value[2], 2) . str_repeat($value[3], 2);
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

    public static function getSigningBoxHeading(): string
    {
        $stored = trim((string) get_option(self::OPTION_SIGNING_BOX_HEADING, ''));

        return $stored !== '' ? $stored : self::defaultSigningBoxHeading();
    }

    public static function getSigningBoxBody(): string
    {
        $stored = trim((string) get_option(self::OPTION_SIGNING_BOX_BODY, ''));

        return $stored !== '' ? $stored : self::defaultSigningBoxBody();
    }

    public static function getSigningBoxCta(): string
    {
        $stored = trim((string) get_option(self::OPTION_SIGNING_BOX_CTA, ''));

        return $stored !== '' ? $stored : self::defaultSigningBoxCta();
    }

    public static function defaultSigningBoxHeading(): string
    {
        return __('One last step — sign your contract', 'zapis-woocommerce');
    }

    public static function defaultSigningBoxBody(): string
    {
        return __('<p>To complete your order we need your electronic signature on the contract. It takes under a minute.</p>', 'zapis-woocommerce');
    }

    public static function defaultSigningBoxCta(): string
    {
        return __('Sign contract now', 'zapis-woocommerce');
    }

    public static function getSigningBoxColorFrom(): string
    {
        $stored = self::sanitizeHexColor((string) get_option(self::OPTION_SIGNING_BOX_COLOR_FROM, ''));

        return $stored !== '' ? $stored : self::DEFAULT_SIGNING_BOX_COLOR_FROM;
    }

    public static function getSigningBoxColorTo(): string
    {
        $stored = self::sanitizeHexColor((string) get_option(self::OPTION_SIGNING_BOX_COLOR_TO, ''));

        return $stored !== '' ? $stored : self::DEFAULT_SIGNING_BOX_COLOR_TO;
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
        $signingBoxHeadingStored = (string) get_option(self::OPTION_SIGNING_BOX_HEADING, '');
        $signingBoxBodyStored = (string) get_option(self::OPTION_SIGNING_BOX_BODY, '');
        $signingBoxCtaStored = (string) get_option(self::OPTION_SIGNING_BOX_CTA, '');
        $signingBoxHeadingDefault = self::defaultSigningBoxHeading();
        $signingBoxBodyDefault = self::defaultSigningBoxBody();
        $signingBoxCtaDefault = self::defaultSigningBoxCta();
        $signingBoxColorFromStored = (string) get_option(self::OPTION_SIGNING_BOX_COLOR_FROM, '');
        $signingBoxColorToStored = (string) get_option(self::OPTION_SIGNING_BOX_COLOR_TO, '');
        $signingBoxColorFromDefault = self::DEFAULT_SIGNING_BOX_COLOR_FROM;
        $signingBoxColorToDefault = self::DEFAULT_SIGNING_BOX_COLOR_TO;

        include ZAPIS_WC_PLUGIN_DIR . 'views/admin/settings-page.php';
    }
}
