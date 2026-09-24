<?php
/**
 * BueDoc My Account Integration
 *
 * Visualização e download de facturas na área de cliente "A Minha Conta".
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_My_Account {

    /**
     * Construtor e ganchos.
     */
    public function __construct() {
        // Adicionar botão de acção na tabela de encomendas do cliente
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'add_invoice_action_to_orders_table'], 20, 2);

        // Exibir secção de factura nos detalhes da encomenda
        add_action('woocommerce_order_details_after_order_table', [$this, 'render_invoice_details_in_order'], 20, 1);
    }

    /**
     * Adiciona o botão "Factura (PDF)" na lista de encomendas do cliente.
     *
     * @param array    $actions
     * @param WC_Order $order
     * @return array
     */
    public function add_invoice_action_to_orders_table($actions, $order) {
        $show_link = (BueDoc_Settings::get_option('show_link_my_account', 'yes') === 'yes');
        if (!$show_link) {
            return $actions;
        }

        $doc_id = $order->get_meta('_buedoc_document_id');
        if (empty($doc_id)) {
            return $actions;
        }

        $download_url = add_query_arg([
            'action'   => 'buedoc_download_pdf',
            'order_id' => $order->get_id(),
            'nonce'    => wp_create_nonce('buedoc_download_pdf_' . $order->get_id()),
        ], admin_url('admin-post.php'));

        $actions['buedoc_pdf'] = [
            'url'  => $download_url,
            'name' => __('Factura PDF', 'buedoc-woocommerce'),
        ];

        return $actions;
    }

    /**
     * Exibe os detalhes e botão de download na página de visualização da encomenda.
     *
     * @param WC_Order $order
     */
    public function render_invoice_details_in_order($order) {
        $show_link = (BueDoc_Settings::get_option('show_link_my_account', 'yes') === 'yes');
        if (!$show_link) {
            return;
        }

        if (!$order instanceof WC_Order) {
            return;
        }

        $doc_id     = $order->get_meta('_buedoc_document_id');
        $doc_number = $order->get_meta('_buedoc_document_number');
        $doc_type   = $order->get_meta('_buedoc_document_type');
        $qr_code    = $order->get_meta('_buedoc_document_qr_code');

        if (empty($doc_id) || empty($doc_number)) {
            return;
        }

        $download_url = add_query_arg([
            'action'   => 'buedoc_download_pdf',
            'order_id' => $order->get_id(),
            'nonce'    => wp_create_nonce('buedoc_download_pdf_' . $order->get_id()),
        ], admin_url('admin-post.php'));
        ?>
        <div class="buedoc-myaccount-invoice-card" style="margin: 28px 0; padding: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
            <div class="buedoc-myaccount-invoice-info" style="margin-bottom: 12px;">
                <h3 style="margin-top: 0; margin-bottom: 6px; font-size: 16px; color: #0f172a;">
                    <?php esc_html_e('Documento Fiscal Certificado (AGT)', 'buedoc-woocommerce'); ?>
                </h3>
                <p style="margin: 0; color: #475569; font-size: 13px;">
                    <?php
                    printf(
                        esc_html__('Documento emitido: %1$s %2$s', 'buedoc-woocommerce'),
                        esc_html($doc_type === 'FR' ? 'Factura-Recibo' : 'Factura'),
                        '<strong>' . esc_html($doc_number) . '</strong>'
                    );
                    ?>
                </p>
            </div>

            <?php if (!empty($qr_code)) : ?>
                <div style="margin-bottom: 16px;">
                    <img src="<?php echo esc_attr($qr_code); ?>" alt="QR Code AGT" style="width: 90px; height: 90px; border: 1px solid #cbd5e1; padding: 4px; background: #ffffff; border-radius: 4px; display: block;" />
                </div>
            <?php endif; ?>

            <div>
                <a href="<?php echo esc_url($download_url); ?>" target="_blank" class="button" style="display: inline-block;">
                    <?php esc_html_e('Descarregar Factura (PDF)', 'buedoc-woocommerce'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
