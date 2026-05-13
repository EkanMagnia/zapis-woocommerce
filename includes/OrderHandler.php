<?php

declare(strict_types=1);

namespace Zapis\WooCommerce;

use Zapis\WooCommerce\Exceptions\ApiException;
use Zapis\WooCommerce\Exceptions\AuthenticationException;
use Zapis\WooCommerce\Exceptions\ValidationException;

/**
 * Hooks into WooCommerce order lifecycle and POSTs a direct-sign
 * request to Zapis when an order is paid. Persists the resulting
 * submission UUID + signing URL in order meta.
 */
final class OrderHandler
{
    public const META_SUBMISSION_UUID = '_zapis_submission_uuid';

    public const META_SIGNING_URL = '_zapis_signing_url';

    public const META_EXPIRES_AT = '_zapis_expires_at';

    public const META_CONTRACT_STATUS = '_zapis_contract_status';

    public const META_PDF_URL = '_zapis_pdf_url';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var callable(\WC_Order):string */
    private $offerUuidResolver;

    public function __construct(
        private ApiClient $apiClient,
        callable $offerUuidResolver
    ) {
        $this->offerUuidResolver = $offerUuidResolver;
    }

    public static function register(): void
    {
        add_action('woocommerce_payment_complete', [self::class, 'onPaymentComplete']);
    }

    public static function onPaymentComplete(int $orderId): void
    {
        if (! Settings::isConfigured()) {
            return;
        }

        $client = new ApiClient(Settings::getApiKey(), Settings::getApiBaseUrl());
        $resolver = fn (\WC_Order $order): string => Settings::getDefaultOfferUuid();

        (new self($client, $resolver))->handlePaymentComplete($orderId);
    }

    public function handlePaymentComplete(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }

        if ($order->get_meta(self::META_SUBMISSION_UUID) !== '') {
            return; // Already sent for signing — idempotent skip.
        }

        $offerUuid = ($this->offerUuidResolver)($order);
        if ($offerUuid === '') {
            $order->add_order_note(__('Zapis: no offer UUID resolved for this order; signing skipped.', 'zapis-woocommerce'));
            return;
        }

        $payload = self::buildPayload($order);
        $idempotencyKey = 'wc_order_' . $order->get_id();

        try {
            $result = $this->apiClient->directSign($offerUuid, $payload, $idempotencyKey);
        } catch (AuthenticationException $e) {
            $order->add_order_note(sprintf(
                /* translators: %s: error message */
                __('Zapis API authentication failed (check your API key). Error: %s', 'zapis-woocommerce'),
                $e->getMessage()
            ));
            return;
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $detail = empty($errors) ? $e->getMessage() : self::formatErrors($errors);
            $order->add_order_note(sprintf(
                /* translators: %s: validation errors */
                __('Zapis API rejected order data: %s', 'zapis-woocommerce'),
                $detail
            ));
            return;
        } catch (ApiException $e) {
            $order->add_order_note(sprintf(
                /* translators: %s: error message */
                __('Zapis API error: %s', 'zapis-woocommerce'),
                $e->getMessage()
            ));
            return;
        }

        $order->update_meta_data(self::META_SUBMISSION_UUID, (string) ($result['submission_uuid'] ?? ''));
        $order->update_meta_data(self::META_SIGNING_URL, (string) ($result['url'] ?? ''));
        if (! empty($result['expires_at'])) {
            $order->update_meta_data(self::META_EXPIRES_AT, (string) $result['expires_at']);
        }
        $order->update_meta_data(self::META_CONTRACT_STATUS, self::STATUS_PENDING);
        $order->save();

        $order->add_order_note(sprintf(
            /* translators: %s: signing URL */
            __('Zapis: contract sent for signing. URL: %s', 'zapis-woocommerce'),
            (string) ($result['url'] ?? '')
        ));
    }

    /**
     * Build the direct-sign API payload from a WooCommerce order.
     *
     * @return array<string, mixed>
     */
    public static function buildPayload(\WC_Order $order): array
    {
        return [
            'client_name' => $order->get_formatted_billing_full_name(),
            'client_email' => $order->get_billing_email(),
            'client_phone' => $order->get_billing_phone(),
            'external_order_id' => (string) $order->get_id(),
            'redirect_url' => $order->get_checkout_order_received_url(),
            'order' => [
                'total' => (float) $order->get_total(),
                'currency' => $order->get_currency(),
                'placed_at' => $order->get_date_created() ? $order->get_date_created()->date('c') : null,
                'items' => self::mapItems($order),
            ],
        ];
    }

    /**
     * @return array<int, array{name:string, quantity:int, price:float}>
     */
    private static function mapItems(\WC_Order $order): array
    {
        $items = [];
        foreach ($order->get_items('line_item') as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $quantity = (int) $item->get_quantity();
            $subtotal = (float) $item->get_subtotal();
            $unitPrice = $quantity > 0 ? $subtotal / $quantity : $subtotal;

            $items[] = [
                'name' => $item->get_name(),
                'quantity' => $quantity,
                'price' => round($unitPrice, 2),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    private static function formatErrors(array $errors): string
    {
        $parts = [];
        foreach ($errors as $field => $messages) {
            $parts[] = $field . ': ' . implode(', ', (array) $messages);
        }

        return implode('; ', $parts);
    }
}
