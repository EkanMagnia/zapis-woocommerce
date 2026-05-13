<?php

declare(strict_types=1);

namespace Zapis\WooCommerce;

/**
 * Receives Zapis webhook payloads (contract.signed for now) at
 * /?zapis_webhook=1 and applies them to the matching WooCommerce order.
 */
class WebhookReceiver
{
    public const QUERY_VAR = 'zapis_webhook';

    public const SIGNATURE_HEADER = 'X-Webhook-Signature';

    private string $webhookSecret;

    public function __construct(string $webhookSecret)
    {
        $this->webhookSecret = $webhookSecret;
    }

    public static function register(): void
    {
        add_action('init', [self::class, 'maybeHandleRequest'], 1);
    }

    public static function maybeHandleRequest(): void
    {
        if (! isset($_GET[self::QUERY_VAR]) || $_GET[self::QUERY_VAR] !== '1') {
            return;
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? null;

        $receiver = new self(Settings::getWebhookSecret());
        $result = $receiver->process($rawBody, is_string($signature) ? $signature : null);

        status_header($result['status']);
        wp_send_json($result);
    }

    /**
     * Process a raw webhook payload. Returns ['status' => int, 'result' => string].
     *
     * @return array{status:int, result:string}
     */
    public function process(string $rawBody, ?string $signature): array
    {
        if ($this->webhookSecret === '') {
            return ['status' => 503, 'result' => 'webhook_secret_missing'];
        }

        if ($signature === null || $signature === '') {
            return ['status' => 401, 'result' => 'missing_signature'];
        }

        if (! ApiClient::verifyWebhookSignature($rawBody, $signature, $this->webhookSecret)) {
            return ['status' => 401, 'result' => 'invalid_signature'];
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return ['status' => 400, 'result' => 'invalid_json'];
        }

        $event = (string) ($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if ($event !== 'contract.signed') {
            return ['status' => 200, 'result' => 'ignored'];
        }

        $externalOrderId = (string) ($data['external_order_id'] ?? '');
        if ($externalOrderId === '') {
            return ['status' => 400, 'result' => 'missing_external_order_id'];
        }

        $order = wc_get_order((int) $externalOrderId);
        if (! $order instanceof \WC_Order) {
            return ['status' => 200, 'result' => 'not_found'];
        }

        $currentStatus = (string) $order->get_meta(OrderHandler::META_CONTRACT_STATUS, true);
        if ($currentStatus === OrderHandler::STATUS_SIGNED) {
            return ['status' => 200, 'result' => 'already_processed'];
        }

        $order->update_meta_data(OrderHandler::META_CONTRACT_STATUS, OrderHandler::STATUS_SIGNED);

        if (! empty($data['pdf_url'])) {
            $order->update_meta_data(OrderHandler::META_PDF_URL, (string) $data['pdf_url']);
        }

        $order->update_status(
            'completed',
            __('Contract signed via Zapis. Order completed automatically.', 'zapis-woocommerce')
        );

        $order->save();

        return ['status' => 200, 'result' => 'processed'];
    }
}
