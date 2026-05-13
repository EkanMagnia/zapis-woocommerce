<?php

/**
 * Plugin Name:       Zapis for WooCommerce
 * Plugin URI:        https://zapis.app
 * Description:       Cere clientului să semneze electronic un contract după plată, direct din WooCommerce. Trimite datele comenzii la Zapis, redirecționează clientul către semnătură, marchează comanda completă pe webhook.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Zapis
 * Author URI:        https://zapis.app
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zapis-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.4
 *
 * @package Zapis\WooCommerce
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('ZAPIS_WC_VERSION', '0.1.0');
define('ZAPIS_WC_PLUGIN_FILE', __FILE__);
define('ZAPIS_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZAPIS_WC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ZAPIS_WC_PLUGIN_DIR . 'vendor/autoload.php';

\Zapis\WooCommerce\Plugin::boot();

register_activation_hook(__FILE__, static function (): void {
    // Reserve hooks for future schema/meta setup.
});

register_deactivation_hook(__FILE__, static function (): void {
    // Cleanup transients/scheduled events here if added later.
});
