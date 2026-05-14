<?php
/**
 * Admin settings page view.
 *
 * @var string $apiKey
 * @var string $defaultOfferUuid
 * @var string $webhookSecret
 * @var string $apiBaseUrl
 * @var string $webhookUrl
 * @var string $signingBoxHeadingStored
 * @var string $signingBoxBodyStored
 * @var string $signingBoxCtaStored
 * @var string $signingBoxHeadingDefault
 * @var string $signingBoxBodyDefault
 * @var string $signingBoxCtaDefault
 * @var string $signingBoxColorFromStored
 * @var string $signingBoxColorToStored
 * @var string $signingBoxColorFromDefault
 * @var string $signingBoxColorToDefault
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Zapis Contracts', 'zapis-woocommerce'); ?></h1>
    <p class="description" style="max-width:720px">
        <?php esc_html_e('Conectează WooCommerce cu Zapis. După plată, clientul este redirecționat să semneze electronic contractul; pe semnătură, comanda se finalizează automat prin webhook.', 'zapis-woocommerce'); ?>
    </p>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php settings_fields('zapis_wc_settings'); ?>

        <h2><?php esc_html_e('Credențiale Zapis', 'zapis-woocommerce'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="zapis_wc_api_key"><?php esc_html_e('API Key', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="password" id="zapis_wc_api_key" name="<?php echo esc_attr(\Zapis\WooCommerce\Settings::OPTION_API_KEY); ?>"
                           value="<?php echo esc_attr($apiKey); ?>" class="regular-text" autocomplete="off"
                           placeholder="zapis_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    <p class="description"><?php esc_html_e('Generează una din Zapis Dashboard → Settings → Integrations → API keys.', 'zapis-woocommerce'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zapis_wc_default_offer_uuid"><?php esc_html_e('Default Offer UUID', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" id="zapis_wc_default_offer_uuid" name="<?php echo esc_attr(\Zapis\WooCommerce\Settings::OPTION_DEFAULT_OFFER_UUID); ?>"
                           value="<?php echo esc_attr($defaultOfferUuid); ?>" class="regular-text" style="font-family:monospace"
                           placeholder="9568f502-1634-4c33-adb3-9dd94ebd001d">
                    <p class="description"><?php esc_html_e('Ofertă "template" publicată în Zapis, folosită pentru orice produs nemapat individual. Vezi UUID-ul în Zapis pe modalul Partajare al ofertei.', 'zapis-woocommerce'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zapis_wc_webhook_secret"><?php esc_html_e('Webhook Secret', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="password" id="zapis_wc_webhook_secret" name="<?php echo esc_attr(\Zapis\WooCommerce\Settings::OPTION_WEBHOOK_SECRET); ?>"
                           value="<?php echo esc_attr($webhookSecret); ?>" class="regular-text" autocomplete="off">
                    <p class="description">
                        <?php esc_html_e('Secret-ul afișat de Zapis când configurezi webhook endpoint-ul. Folosit pentru a verifica HMAC signature pe payload-ul primit.', 'zapis-woocommerce'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Webhook URL', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <code style="background:#f0f0f1;padding:6px 8px;display:inline-block"><?php echo esc_html($webhookUrl); ?></code>
                    <p class="description"><?php esc_html_e('Copiază în Zapis: Settings → Integrations → Webhook endpoints → URL. Eveniment: contract.signed.', 'zapis-woocommerce'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Box semnătură pe pagina de mulțumire', 'zapis-woocommerce'); ?></h2>
        <p class="description" style="max-width:720px">
            <?php esc_html_e('Personalizează titlul, textul și butonul afișat clientului după plată, când îl invităm să semneze contractul. Lasă câmpurile goale pentru a folosi textele implicite.', 'zapis-woocommerce'); ?>
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="zapis_wc_signing_box_heading"><?php esc_html_e('Titlu', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" id="zapis_wc_signing_box_heading"
                           name="<?php echo esc_attr(\Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_HEADING); ?>"
                           value="<?php echo esc_attr($signingBoxHeadingStored); ?>"
                           class="regular-text"
                           placeholder="<?php echo esc_attr($signingBoxHeadingDefault); ?>">
                    <p class="description"><?php esc_html_e('Apare în partea de sus a box-ului. Default:', 'zapis-woocommerce'); ?> <em><?php echo esc_html($signingBoxHeadingDefault); ?></em></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zapis_wc_signing_box_body"><?php esc_html_e('Text body', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <?php
                    $editorValue = $signingBoxBodyStored !== '' ? $signingBoxBodyStored : $signingBoxBodyDefault;
                    wp_editor(
                        $editorValue,
                        'zapis_wc_signing_box_body',
                        [
                            'textarea_name' => \Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_BODY,
                            'textarea_rows' => 5,
                            'media_buttons' => false,
                            'tinymce' => [
                                'toolbar1' => 'bold,italic,underline,link,unlink,bullist,numlist,undo,redo',
                                'toolbar2' => '',
                            ],
                            'quicktags' => true,
                        ]
                    );
                    ?>
                    <p class="description"><?php esc_html_e('Formatare permisă: bold, italic, link-uri, liste. Lasă gol pentru textul implicit.', 'zapis-woocommerce'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zapis_wc_signing_box_cta"><?php esc_html_e('Text buton', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" id="zapis_wc_signing_box_cta"
                           name="<?php echo esc_attr(\Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_CTA); ?>"
                           value="<?php echo esc_attr($signingBoxCtaStored); ?>"
                           class="regular-text"
                           placeholder="<?php echo esc_attr($signingBoxCtaDefault); ?>">
                    <p class="description"><?php esc_html_e('Default:', 'zapis-woocommerce'); ?> <em><?php echo esc_html($signingBoxCtaDefault); ?></em></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zapis_wc_signing_box_color_from"><?php esc_html_e('Culoare gradient — start', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" id="zapis_wc_signing_box_color_from"
                           name="<?php echo esc_attr(\Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_COLOR_FROM); ?>"
                           value="<?php echo esc_attr($signingBoxColorFromStored); ?>"
                           class="zapis-color-picker"
                           data-default-color="<?php echo esc_attr($signingBoxColorFromDefault); ?>">
                    <p class="description"><?php esc_html_e('Culoarea din stânga-sus a gradientului. Folosită și pentru textul butonului. Default:', 'zapis-woocommerce'); ?> <code><?php echo esc_html($signingBoxColorFromDefault); ?></code></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zapis_wc_signing_box_color_to"><?php esc_html_e('Culoare gradient — final', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="text" id="zapis_wc_signing_box_color_to"
                           name="<?php echo esc_attr(\Zapis\WooCommerce\Settings::OPTION_SIGNING_BOX_COLOR_TO); ?>"
                           value="<?php echo esc_attr($signingBoxColorToStored); ?>"
                           class="zapis-color-picker"
                           data-default-color="<?php echo esc_attr($signingBoxColorToDefault); ?>">
                    <p class="description"><?php esc_html_e('Culoarea din dreapta-jos a gradientului. Default:', 'zapis-woocommerce'); ?> <code><?php echo esc_html($signingBoxColorToDefault); ?></code></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Avansat', 'zapis-woocommerce'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="zapis_wc_api_base_url"><?php esc_html_e('Zapis Base URL', 'zapis-woocommerce'); ?></label>
                </th>
                <td>
                    <input type="url" id="zapis_wc_api_base_url" name="<?php echo esc_attr(\Zapis\WooCommerce\Settings::OPTION_API_BASE_URL); ?>"
                           value="<?php echo esc_attr($apiBaseUrl === \Zapis\WooCommerce\Settings::DEFAULT_API_BASE_URL ? '' : $apiBaseUrl); ?>"
                           class="regular-text" placeholder="<?php echo esc_attr(\Zapis\WooCommerce\Settings::DEFAULT_API_BASE_URL); ?>">
                    <p class="description"><?php esc_html_e('Lasă gol pentru producție (https://zapis.io). Setează doar pentru staging / instalare custom.', 'zapis-woocommerce'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
