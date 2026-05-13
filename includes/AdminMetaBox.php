<?php

declare(strict_types=1);

namespace Zapis\WooCommerce;

/**
 * Adds a "Zapis Contract" meta box to the WooCommerce order edit screen
 * with status, submission UUID, signing URL, PDF link and a resend action.
 */
final class AdminMetaBox
{
    public const META_BOX_ID = 'zapis_contract_meta_box';

    public const ACTION_RESEND = 'zapis_resend_signing_email';

    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBox']);
        add_action('admin_post_' . self::ACTION_RESEND, [self::class, 'handleResend']);
    }

    public static function addMetaBox(): void
    {
        add_meta_box(
            self::META_BOX_ID,
            __('Zapis Contract', 'zapis-woocommerce'),
            [self::class, 'renderForCurrentOrder'],
            ['shop_order', 'woocommerce_page_wc-orders'],
            'side',
            'default'
        );
    }

    public static function renderForCurrentOrder(\WP_Post $postOrOrder = null): void
    {
        $orderId = 0;
        if ($postOrOrder && property_exists($postOrOrder, 'ID')) {
            $orderId = (int) $postOrOrder->ID;
        }
        if ($orderId === 0) {
            return;
        }

        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }

        echo self::renderFor($order);
    }

    public static function renderFor(\WC_Order $order): string
    {
        $submissionUuid = (string) $order->get_meta(OrderHandler::META_SUBMISSION_UUID);
        $status = (string) $order->get_meta(OrderHandler::META_CONTRACT_STATUS);
        $signingUrl = (string) $order->get_meta(OrderHandler::META_SIGNING_URL);
        $pdfUrl = (string) $order->get_meta(OrderHandler::META_PDF_URL);
        $expiresAt = (string) $order->get_meta(OrderHandler::META_EXPIRES_AT);

        if ($submissionUuid === '') {
            return '<p>' . esc_html__('No contract has been sent for signing on this order.', 'zapis-woocommerce') . '</p>';
        }

        $statusLabel = self::statusLabel($status);
        $statusColor = self::statusColor($status);

        $rows = [];
        $rows[] = sprintf(
            '<tr><th style="text-align:left;padding:4px 0;">%s</th><td><strong style="color:%s;">%s</strong></td></tr>',
            esc_html__('Status', 'zapis-woocommerce'),
            esc_attr($statusColor),
            esc_html($statusLabel)
        );
        $rows[] = sprintf(
            '<tr><th style="text-align:left;padding:4px 0;">%s</th><td><code style="font-size:11px;">%s</code></td></tr>',
            esc_html__('Submission', 'zapis-woocommerce'),
            esc_html($submissionUuid)
        );

        if ($signingUrl !== '' && $status === OrderHandler::STATUS_PENDING) {
            $rows[] = sprintf(
                '<tr><th style="text-align:left;padding:4px 0;">%s</th><td><a href="%s" target="_blank">%s</a></td></tr>',
                esc_html__('Signing link', 'zapis-woocommerce'),
                esc_url($signingUrl),
                esc_html__('Open in new tab →', 'zapis-woocommerce')
            );
        }

        if ($expiresAt !== '' && $status === OrderHandler::STATUS_PENDING) {
            $rows[] = sprintf(
                '<tr><th style="text-align:left;padding:4px 0;">%s</th><td>%s</td></tr>',
                esc_html__('Expires at', 'zapis-woocommerce'),
                esc_html($expiresAt)
            );
        }

        if ($pdfUrl !== '' && $status === OrderHandler::STATUS_SIGNED) {
            $rows[] = sprintf(
                '<tr><th style="text-align:left;padding:4px 0;">%s</th><td><a href="%s" target="_blank">%s</a></td></tr>',
                esc_html__('Signed PDF', 'zapis-woocommerce'),
                esc_url($pdfUrl),
                esc_html__('Download →', 'zapis-woocommerce')
            );
        }

        $tableHtml = '<table style="width:100%;border-collapse:collapse;font-size:13px;">' . implode('', $rows) . '</table>';

        $actionsHtml = '';
        if ($status === OrderHandler::STATUS_PENDING && $signingUrl !== '') {
            $actionsHtml = sprintf(
                '<form method="post" action="%s" style="margin-top:12px;">
                    %s
                    <input type="hidden" name="action" value="%s">
                    <input type="hidden" name="order_id" value="%d">
                    <button type="submit" class="button">%s</button>
                </form>',
                esc_url(admin_url('admin-post.php')),
                wp_nonce_field(self::ACTION_RESEND, '_wpnonce', true, false),
                esc_attr(self::ACTION_RESEND),
                (int) $order->get_id(),
                esc_html__('Resend signing email to customer', 'zapis-woocommerce')
            );
        }

        return $tableHtml . $actionsHtml;
    }

    public static function handleResend(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'zapis-woocommerce'));
        }
        check_admin_referer(self::ACTION_RESEND);

        $orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $order = $orderId > 0 ? wc_get_order($orderId) : null;
        if (! $order instanceof \WC_Order) {
            wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
            exit;
        }

        $signingUrl = (string) $order->get_meta(OrderHandler::META_SIGNING_URL);
        $email = $order->get_billing_email();

        if ($signingUrl !== '' && $email !== '') {
            wp_mail(
                $email,
                __('Please sign your contract', 'zapis-woocommerce'),
                sprintf(
                    /* translators: %s: signing URL */
                    __("To finalise your order please sign the contract here:\n\n%s", 'zapis-woocommerce'),
                    $signingUrl
                )
            );
            $order->add_order_note(__('Zapis: signing email resent to customer (manual).', 'zapis-woocommerce'));
        }

        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
        exit;
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            OrderHandler::STATUS_PENDING => __('Pending signature', 'zapis-woocommerce'),
            OrderHandler::STATUS_SIGNED => __('Signed', 'zapis-woocommerce'),
            OrderHandler::STATUS_CANCELLED => __('Cancelled', 'zapis-woocommerce'),
            default => __('Unknown', 'zapis-woocommerce'),
        };
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            OrderHandler::STATUS_PENDING => '#b45309',
            OrderHandler::STATUS_SIGNED => '#15803d',
            OrderHandler::STATUS_CANCELLED => '#dc2626',
            default => '#6b7280',
        };
    }
}
