<?php

declare(strict_types=1);

namespace Zapis\WooCommerce;

/**
 * UX surfaces on the WooCommerce order-received page and inside WC
 * customer emails. Surfaces the Zapis signing CTA when a submission is
 * pending; renders nothing once the contract is signed or cancelled.
 */
final class ThankYouHandler
{
    public static function register(): void
    {
        add_action('woocommerce_thankyou', [self::class, 'onThankYouPage'], 5);
        add_action('woocommerce_email_after_order_table', [self::class, 'onEmailAfterOrderTable'], 10, 4);
    }

    public static function onThankYouPage(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }

        echo self::renderForOrder($order);
    }

    public static function onEmailAfterOrderTable(\WC_Order $order, bool $sentToAdmin, bool $plainText, $email): void
    {
        if ($sentToAdmin) {
            return;
        }

        $text = self::emailText($order);
        if ($text === '') {
            return;
        }

        if ($plainText) {
            echo "\n" . $text . "\n";
            return;
        }

        echo '<p style="margin-top:24px;padding:16px;border-left:4px solid #4f46e5;background:#f5f5ff;">'
            . nl2br(esc_html($text))
            . '</p>';
    }

    /**
     * Render the signing CTA block for the order-received page.
     * Returns empty string if there's no pending submission.
     */
    public static function renderForOrder(\WC_Order $order): string
    {
        $submissionUuid = (string) $order->get_meta(OrderHandler::META_SUBMISSION_UUID);
        $signingUrl = (string) $order->get_meta(OrderHandler::META_SIGNING_URL);
        $status = (string) $order->get_meta(OrderHandler::META_CONTRACT_STATUS);

        if ($submissionUuid === '' || $signingUrl === '') {
            return '';
        }

        if ($status !== OrderHandler::STATUS_PENDING) {
            return '';
        }

        $heading = esc_html__('One last step — sign your contract', 'zapis-woocommerce');
        $intro = esc_html__('To complete your order we need your electronic signature on the contract. It takes under a minute.', 'zapis-woocommerce');
        $cta = esc_html__('Sign contract now', 'zapis-woocommerce');

        return sprintf(
            '<section class="zapis-thank-you-cta" style="margin:24px 0;padding:20px;border:1px solid #c7c7ff;background:#f5f5ff;border-radius:8px;">
                <h2 style="margin:0 0 8px;font-size:18px;color:#3730a3;">%s</h2>
                <p style="margin:0 0 16px;">%s</p>
                <a href="%s" class="button" style="background:#4f46e5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;">%s</a>
            </section>',
            $heading,
            $intro,
            esc_url($signingUrl),
            $cta
        );
    }

    /**
     * Text snippet for inclusion in WooCommerce customer order emails.
     * Returns empty string if no pending submission.
     */
    public static function emailText(\WC_Order $order): string
    {
        $submissionUuid = (string) $order->get_meta(OrderHandler::META_SUBMISSION_UUID);
        $signingUrl = (string) $order->get_meta(OrderHandler::META_SIGNING_URL);
        $status = (string) $order->get_meta(OrderHandler::META_CONTRACT_STATUS);

        if ($submissionUuid === '' || $signingUrl === '') {
            return '';
        }
        if ($status !== OrderHandler::STATUS_PENDING) {
            return '';
        }

        $intro = __('Action required: please sign the contract for this order at the link below.', 'zapis-woocommerce');

        return $intro . "\n" . $signingUrl;
    }
}
