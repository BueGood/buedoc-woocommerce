<?php
/**
 * Template: Email Invoice Info Snippet
 *
 * @package BueDoc_WooCommerce
 * @var WC_Order $order
 * @var string   $doc_number
 * @var string   $doc_type
 * @var string   $download_url
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div style="margin: 24px 0; padding: 16px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <h3 style="margin-top: 0; margin-bottom: 8px; color: #1e293b; font-size: 16px;">
        <?php esc_html_e('Documento Fiscal Certificado pela AGT', 'buedoc-woocommerce'); ?>
    </h3>
    <p style="margin: 0 0 12px 0; color: #475569; font-size: 14px; line-height: 1.5;">
        <?php
        printf(
            esc_html__('A sua compra foi processada e a respectiva %1$s com o número %2$s encontra-se emitida em conformidade com as regras fiscais de Angola.', 'buedoc-woocommerce'),
            esc_html($doc_type === 'FR' ? 'Factura-Recibo' : 'Factura'),
            '<strong>' . esc_html($doc_number) . '</strong>'
        );
        ?>
    </p>
    <a href="<?php echo esc_url($download_url); ?>" target="_blank" style="display: inline-block; padding: 10px 18px; background-color: #2563eb; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 13px; border-radius: 6px;">
        <?php esc_html_e('Descarregar Factura (PDF)', 'buedoc-woocommerce'); ?> &rarr;
    </a>
</div>
