<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Zapis\WooCommerce\Plugin;

class PluginTest extends TestCase
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

    public function test_returns_plugin_version(): void
    {
        $this->assertSame(ZAPIS_WC_VERSION, Plugin::version());
    }

    public function test_returns_plugin_file_path(): void
    {
        $this->assertSame(ZAPIS_WC_PLUGIN_FILE, Plugin::pluginFile());
    }

    public function test_returns_plugin_dir(): void
    {
        $this->assertSame(ZAPIS_WC_PLUGIN_DIR, Plugin::pluginDir());
    }

    public function test_boot_registers_init_hook(): void
    {
        Functions\expect('add_action')
            ->atLeast()->once()
            ->with('plugins_loaded', \Mockery::type('callable'));

        Plugin::boot();
    }

    public function test_is_woocommerce_active_returns_false_when_class_missing(): void
    {
        $this->assertFalse(Plugin::isWooCommerceActive());
    }
}
