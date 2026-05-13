<?php

declare(strict_types=1);

namespace Zapis\WooCommerce;

/**
 * Central plugin coordinator. Holds metadata, boots subsystems and exposes
 * accessors for plugin paths/version.
 */
final class Plugin
{
    public static function version(): string
    {
        return ZAPIS_WC_VERSION;
    }

    public static function pluginFile(): string
    {
        return ZAPIS_WC_PLUGIN_FILE;
    }

    public static function pluginDir(): string
    {
        return ZAPIS_WC_PLUGIN_DIR;
    }

    /**
     * Wire up the plugin once WordPress is ready.
     */
    public static function boot(): void
    {
        add_action('plugins_loaded', [self::class, 'onPluginsLoaded']);
    }

    public static function onPluginsLoaded(): void
    {
        if (! self::isWooCommerceActive()) {
            add_action('admin_notices', [self::class, 'renderWooCommerceMissingNotice']);

            return;
        }

        Settings::register();
        OrderHandler::register();

        // Future iterations:
        //  - WebhookReceiver::register()
        //  - ProductMeta::register()
    }

    public static function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function renderWooCommerceMissingNotice(): void
    {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Zapis for WooCommerce requires WooCommerce to be installed and active.', 'zapis-woocommerce');
        echo '</p></div>';
    }
}
