<?php

declare(strict_types=1);

namespace Zapis\WooCommerce\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Zapis\WooCommerce\ProductMeta;
use Zapis\WooCommerce\Settings;

class ProductMetaTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg(1);
        Functions\when('esc_html__')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_resolve_returns_default_when_no_items_have_override(): void
    {
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_option')->alias(fn ($key) => match ($key) {
            Settings::OPTION_DEFAULT_OFFER_UUID => '9568f502-1634-4c33-adb3-9dd94ebd001d',
            default => '',
        });

        $order = $this->makeOrderWithProducts([10, 20]);

        $resolved = ProductMeta::resolveForOrder($order);

        $this->assertSame('9568f502-1634-4c33-adb3-9dd94ebd001d', $resolved);
    }

    public function test_resolve_returns_first_per_product_override_found(): void
    {
        Functions\when('get_post_meta')->alias(function (int $productId, string $key) {
            if ($key !== ProductMeta::META_OFFER_UUID) {
                return '';
            }
            return match ($productId) {
                20 => 'aaaa1111-2222-3333-4444-555566667777',
                default => '',
            };
        });
        Functions\when('get_option')->alias(fn () => 'default-uuid');

        $order = $this->makeOrderWithProducts([10, 20, 30]);

        $resolved = ProductMeta::resolveForOrder($order);

        $this->assertSame('aaaa1111-2222-3333-4444-555566667777', $resolved);
    }

    public function test_resolve_returns_empty_when_no_default_and_no_override(): void
    {
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_option')->justReturn('');

        $order = $this->makeOrderWithProducts([10]);

        $this->assertSame('', ProductMeta::resolveForOrder($order));
    }

    public function test_save_field_persists_valid_uuid(): void
    {
        Functions\expect('update_post_meta')
            ->once()
            ->with(7, ProductMeta::META_OFFER_UUID, '9568f502-1634-4c33-adb3-9dd94ebd001d');

        $_POST[ProductMeta::FORM_FIELD] = '9568f502-1634-4c33-adb3-9dd94ebd001d';
        ProductMeta::saveField(7);
        unset($_POST[ProductMeta::FORM_FIELD]);
    }

    public function test_save_field_deletes_meta_when_value_empty(): void
    {
        Functions\expect('delete_post_meta')
            ->once()
            ->with(7, ProductMeta::META_OFFER_UUID);
        Functions\expect('update_post_meta')->never();

        $_POST[ProductMeta::FORM_FIELD] = '';
        ProductMeta::saveField(7);
        unset($_POST[ProductMeta::FORM_FIELD]);
    }

    public function test_save_field_rejects_invalid_uuid_format(): void
    {
        Functions\expect('update_post_meta')->never();
        Functions\expect('delete_post_meta')->never();

        $_POST[ProductMeta::FORM_FIELD] = 'not-a-uuid';
        ProductMeta::saveField(7);
        unset($_POST[ProductMeta::FORM_FIELD]);
    }

    /** @param array<int> $productIds */
    private function makeOrderWithProducts(array $productIds): \WC_Order
    {
        $items = [];
        foreach ($productIds as $i => $pid) {
            $item = Mockery::mock(\WC_Order_Item_Product::class);
            $item->shouldReceive('get_product_id')->andReturn($pid);
            $items['L' . $i] = $item;
        }

        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_items')->andReturn($items);

        return $order;
    }
}
