<?php

declare(strict_types=1);

namespace Zapis\WooCommerce;

/**
 * Per-product Zapis offer override. Tenants can set a different offer
 * template for specific products via a field in the WooCommerce product
 * editor; the order handler picks the first product with an override,
 * or falls back to the global default.
 */
final class ProductMeta
{
    public const META_OFFER_UUID = '_zapis_offer_uuid';

    public const FORM_FIELD = 'zapis_offer_uuid';

    public static function register(): void
    {
        add_action('woocommerce_product_options_general_product_data', [self::class, 'renderField']);
        add_action('woocommerce_process_product_meta', [self::class, 'saveField']);
    }

    public static function renderField(): void
    {
        woocommerce_wp_text_input([
            'id' => self::FORM_FIELD,
            'label' => __('Zapis Offer UUID', 'zapis-woocommerce'),
            'description' => __('Optional: override the default Zapis offer UUID for this product. Leave empty to use the global default.', 'zapis-woocommerce'),
            'desc_tip' => true,
            'placeholder' => '9568f502-1634-4c33-adb3-9dd94ebd001d',
            'value' => get_post_meta(get_the_ID(), self::META_OFFER_UUID, true),
        ]);
    }

    public static function saveField(int $productId): void
    {
        if (! array_key_exists(self::FORM_FIELD, $_POST)) {
            return;
        }

        $raw = (string) $_POST[self::FORM_FIELD];
        $value = strtolower(trim($raw));

        if ($value === '') {
            delete_post_meta($productId, self::META_OFFER_UUID);
            return;
        }

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
        if (! preg_match($pattern, $value)) {
            return; // silently reject invalid format
        }

        update_post_meta($productId, self::META_OFFER_UUID, $value);
    }

    /**
     * Resolve the Zapis offer UUID for an order: first per-product
     * override found wins; otherwise the global default from Settings.
     */
    public static function resolveForOrder(\WC_Order $order): string
    {
        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $productId = (int) $item->get_product_id();
            if ($productId === 0) {
                continue;
            }
            $override = (string) get_post_meta($productId, self::META_OFFER_UUID, true);
            if ($override !== '') {
                return $override;
            }
        }

        return Settings::getDefaultOfferUuid();
    }
}
