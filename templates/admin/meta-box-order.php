<?php
/**
 * Template: Order Details Meta Box
 *
 * @package BueDoc_WooCommerce
 * @var WC_Order $order
 * @var string   $doc_id
 * @var string   $doc_number
 * @var string   $doc_type
 * @var string   $doc_date
 * @var string   $doc_hash_short
 * @var string   $doc_qr_code
 * @var string   $agt_status
 * @var float    $gross_total
 * @var string   $download_url
 * @var bool     $has_refunds
 * @var string   $nc_number
 */

if (!defined('ABSPATH')) {
    exit;
}

$order_id = $order->get_id();
?>
<div class="buedoc-meta-box">

    <?php if (!empty($doc_id)) : ?>
        <!-- Documento Emitido -->
        <div class="buedoc-field-row">
            <span class="buedoc-label"><?php esc_html_e('Documento Fiscal:', 'buedoc-woocommerce'); ?></span>
            <span class="buedoc-value">
                <span class="buedoc-badge buedoc-badge-doc-<?php echo esc_attr(strtolower($doc_type)); ?>">
                    <?php echo esc_html($doc_number); ?>
                </span>
            </span>
        </div>

        <div class="buedoc-field-row">
            <span class="buedoc-label"><?php esc_html_e('Data de Emissão:', 'buedoc-woocommerce'); ?></span>
            <span class="buedoc-value"><?php echo esc_html($doc_date); ?></span>
        </div>

        <div class="buedoc-field-row">
            <span class="buedoc-label"><?php esc_html_e('Estado na AGT:', 'buedoc-woocommerce'); ?></span>
            <span class="buedoc-value">
                <span class="buedoc-badge buedoc-badge-<?php echo esc_attr($agt_status); ?>">
                    <?php echo esc_html($agt_status); ?>
                </span>
            </span>
        </div>

        <?php if (!empty($doc_hash_short)) : ?>
            <div class="buedoc-field-row">
                <span class="buedoc-label"><?php esc_html_e('Assinatura Fiscal:', 'buedoc-woocommerce'); ?></span>
                <span class="buedoc-value" style="font-family: monospace; font-size: 11px;">
                    <?php echo esc_html($doc_hash_short); ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="buedoc-field-row">
            <span class="buedoc-label"><?php esc_html_e('Total Facturado:', 'buedoc-woocommerce'); ?></span>
            <span class="buedoc-value">
                <?php echo esc_html(number_format((float)$gross_total, 2, ',', ' ') . ' Kz'); ?>
            </span>
        </div>

        <?php if (!empty($nc_number)) : ?>
            <div class="buedoc-field-row" style="background:#fef2f2; padding: 6px 8px; border-radius: 4px; margin-top: 6px;">
                <span class="buedoc-label" style="color:#b91c1c;"><?php esc_html_e('Nota de Crédito:', 'buedoc-woocommerce'); ?></span>
                <span class="buedoc-value">
                    <span class="buedoc-badge buedoc-badge-doc-nc"><?php echo esc_html($nc_number); ?></span>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($doc_qr_code)) : ?>
            <div class="buedoc-qr-box">
                <img src="<?php echo esc_attr($doc_qr_code); ?>" alt="QR Code AGT" />
                <div class="buedoc-qr-desc"><?php esc_html_e('QR Code Fiscal certificado pela AGT', 'buedoc-woocommerce'); ?></div>
            </div>
        <?php endif; ?>

        <div class="buedoc-actions-row">
            <a href="<?php echo esc_url($download_url); ?>" class="buedoc-btn buedoc-btn-primary" target="_blank">
                <span class="dashicons dashicons-pdf" style="font-size: 16px; width: 16px; height: 16px;"></span>
                <?php esc_html_e('Descarregar PDF', 'buedoc-woocommerce'); ?>
            </a>

            <button type="button" class="buedoc-btn buedoc-btn-secondary buedoc-refresh-agt-btn" data-order-id="<?php echo esc_attr($order_id); ?>">
                <span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px;"></span>
                <?php esc_html_e('Actualizar Estado AGT', 'buedoc-woocommerce'); ?>
            </button>

            <?php if ($has_refunds && empty($nc_number)) : ?>
                <button type="button" class="buedoc-btn buedoc-btn-danger buedoc-issue-nc-btn" data-order-id="<?php echo esc_attr($order_id); ?>">
                    <span class="dashicons dashicons-undo" style="font-size: 16px; width: 16px; height: 16px;"></span>
                    <?php esc_html_e('Emitir Nota de Crédito (NC)', 'buedoc-woocommerce'); ?>
                </button>
            <?php endif; ?>
        </div>

    <?php else : ?>
        <!-- Nenhum Documento Emitido Ainda -->
        <p style="margin: 0 0 12px 0; color: #64748b;">
            <?php esc_html_e('Nenhum documento fiscal foi gerado para esta encomenda no BueDoc.', 'buedoc-woocommerce'); ?>
        </p>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 12px;">
            <label for="buedoc-manual-doc-type" style="display:block; margin-bottom: 6px; font-weight: 600; font-size: 12px;">
                <?php esc_html_e('Seleccione o tipo de documento a emitir:', 'buedoc-woocommerce'); ?>
            </label>
            <select id="buedoc-manual-doc-type" style="width: 100%; margin-bottom: 10px;">
                <option value="FR"><?php esc_html_e('Factura-Recibo (FR) — Venda paga', 'buedoc-woocommerce'); ?></option>
                <option value="FT"><?php esc_html_e('Factura (FT) — A prazo / pendente', 'buedoc-woocommerce'); ?></option>
            </select>

            <button type="button" class="buedoc-btn buedoc-btn-primary buedoc-issue-manual-btn" data-order-id="<?php echo esc_attr($order_id); ?>" style="width: 100%;">
                <span class="dashicons dashicons-media-document" style="font-size: 16px; width: 16px; height: 16px;"></span>
                <?php esc_html_e('Emitir Factura no BueDoc', 'buedoc-woocommerce'); ?>
            </button>
        </div>
    <?php endif; ?>

</div>
