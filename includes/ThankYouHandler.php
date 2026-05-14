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

        $heading = esc_html(Settings::getSigningBoxHeading());
        $body = wp_kses_post(Settings::getSigningBoxBody());
        $cta = esc_html(Settings::getSigningBoxCta());
        $trust = esc_html__('Secured by Zapis', 'zapis-woocommerce');
        $url = esc_url($signingUrl);

        $colorFrom = Settings::getSigningBoxColorFrom();
        $colorTo = Settings::getSigningBoxColorTo();
        $shadowTint = self::hexToRgba($colorFrom, 0.28);
        $btnText = $colorFrom;

        $svg = '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 16 12"/></svg>';

        return <<<HTML
<style>.zapis-cta-btn:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(0,0,0,.22)!important;}@media (max-width:560px){.zapis-thank-you-cta{flex-direction:column!important;text-align:center;}.zapis-thank-you-cta .zapis-cta-actions{align-items:center!important;}}</style>
<section class="zapis-thank-you-cta" style="margin:32px 0;padding:28px 32px;background:linear-gradient(135deg,{$colorFrom} 0%,{$colorTo} 100%);border-radius:16px;color:#fff;box-shadow:0 12px 32px {$shadowTint};display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
  <div style="flex-shrink:0;width:64px;height:64px;background:rgba(255,255,255,.16);border-radius:14px;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);">{$svg}</div>
  <div style="flex:1;min-width:240px;">
    <h2 style="margin:0 0 8px;font-size:22px;line-height:1.25;color:#fff;font-weight:700;letter-spacing:-.01em;">{$heading}</h2>
    <div style="margin:0;color:rgba(255,255,255,.94);font-size:15px;line-height:1.55;">{$body}</div>
  </div>
  <div class="zapis-cta-actions" style="flex-shrink:0;display:flex;flex-direction:column;align-items:flex-start;gap:6px;">
    <a href="{$url}" class="zapis-cta-btn" style="display:inline-flex;align-items:center;gap:8px;background:#fff;color:{$btnText};font-weight:600;padding:14px 26px;border-radius:999px;text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,.15);transition:transform .15s,box-shadow .15s;font-size:15px;">{$cta} <span aria-hidden="true">→</span></a>
    <small style="color:rgba(255,255,255,.82);font-size:12px;display:inline-flex;align-items:center;gap:4px;">🔒 {$trust}</small>
  </div>
</section>
HTML;
    }

    /**
     * Convert "#rrggbb" into "rgba(r,g,b,alpha)". Returns a safe fallback
     * if the input is not a valid hex color.
     */
    public static function hexToRgba(string $hex, float $alpha): string
    {
        $hex = ltrim(trim($hex), '#');
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return 'rgba(0,0,0,' . $alpha . ')';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim(rtrim(sprintf('%.3f', $alpha), '0'), '.'));
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
