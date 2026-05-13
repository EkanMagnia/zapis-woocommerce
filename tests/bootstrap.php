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
