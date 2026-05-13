<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WP constants used by plugin code so unit tests don't blow up.
if (! defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (! defined('ZAPIS_WC_PLUGIN_FILE')) {
    define('ZAPIS_WC_PLUGIN_FILE', dirname(__DIR__) . '/zapis-woocommerce.php');
}
if (! defined('ZAPIS_WC_PLUGIN_DIR')) {
    define('ZAPIS_WC_PLUGIN_DIR', dirname(__DIR__) . '/');
}
if (! defined('ZAPIS_WC_VERSION')) {
    define('ZAPIS_WC_VERSION', '0.1.0-dev');
}

// Lightweight stubs for WooCommerce classes used in unit tests.
if (! class_exists('WC_Order')) {
    class WC_Order
    {
        public function get_id() {}
        public function get_billing_first_name(): string { return ''; }
        public function get_billing_last_name(): string { return ''; }
        public function get_formatted_billing_full_name(): string { return ''; }
        public function get_billing_email(): string { return ''; }
        public function get_billing_phone(): string { return ''; }
        public function get_total(): string { return '0'; }
        public function get_currency(): string { return ''; }
        public function get_date_created() {}
        public function get_items(string $type = 'line_item'): array { return []; }
        public function get_checkout_order_received_url(): string { return ''; }
        public function get_meta(string $key, bool $single = true) {}
        public function update_meta_data(string $key, $value): void {}
        public function save(): int { return 0; }
        public function add_order_note(string $note, int $is_customer_note = 0, bool $added_by_user = false): int { return 0; }
    }
}

if (! class_exists('WC_Order_Item_Product')) {
    class WC_Order_Item_Product
    {
        public function get_name(): string { return ''; }
        public function get_quantity(): int { return 0; }
        public function get_total(): string { return '0'; }
        public function get_subtotal(): string { return '0'; }
        public function get_product_id(): int { return 0; }
    }
}
