<?php
/**
 * BueDoc Emails Integration
 *
 * Anexar PDF e adicionar links de factura nos emails do WooCommerce.
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_Emails {

    /**
     * Construtor e ganchos.
     */
    public function __construct() {
        // Adicionar bloco de informação da factura no corpo do email
        add_action('woocommerce_email_after_order_table', [$this, 'add_invoice_info_to_email'], 20, 4);

        // Anexar ficheiro PDF aos emails do WooCommerce
        add_filter('woocommerce_email_attachments', [$this, 'attach_invoice_pdf_to_email'], 20, 3);
    }

    /**
     * Adiciona o snippet da factura no corpo do email.
     *
     * @param WC_Order $order
     * @param bool     $sent_to_admin
     * @param bool     $plain_text
     * @param WC_Email $email
     */
    public function add_invoice_info_to_email($order, $sent_to_admin, $plain_text, $email = null) {
        if ($plain_text || $sent_to_admin) {
            return;
        }

        $show_link = (BueDoc_Settings::get_option('show_link_email', 'yes') === 'yes');
        if (!$show_link) {
            return;
        }

        if (!$order instanceof WC_Order) {
            return;
        }

        $doc_id     = $order->get_meta('_buedoc_document_id');
        $doc_number = $order->get_meta('_buedoc_document_number');
        $doc_type   = $order->get_meta('_buedoc_document_type');

        if (empty($doc_id) || empty($doc_number)) {
            return;
        }

        $download_url = add_query_arg([
            'action'   => 'buedoc_download_pdf',
            'order_id' => $order->get_id(),
            'nonce'    => wp_create_nonce('buedoc_download_pdf_' . $order->get_id()),
        ], admin_url('admin-post.php'));

        $template_path = dirname(dirname(__FILE__)) . '/templates/emails/invoice-info.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    /**
     * Anexa o PDF da factura aos emails configurados.
     *
     * @param array    $attachments
     * @param string   $email_id
     * @param WC_Order $order
     * @return array
     */
    public function attach_invoice_pdf_to_email($attachments, $email_id, $order) {
        $attach_pdf = (BueDoc_Settings::get_option('attach_pdf_email', 'yes') === 'yes');
        if (!$attach_pdf) {
            return $attachments;
        }

        // Anexar apenas em emails dirigidos ao cliente
        $allowed_emails = apply_filters('buedoc_invoice_email_ids', [
            'customer_completed_order',
            'customer_invoice',
            'customer_processing_order',
        ]);

        if (!in_array($email_id, $allowed_emails, true)) {
            return $attachments;
        }

        if (!$order instanceof WC_Order) {
            return $attachments;
        }

        $doc_id     = $order->get_meta('_buedoc_document_id');
        $doc_number = $order->get_meta('_buedoc_document_number');

        if (empty($doc_id)) {
            return $attachments;
        }

        // Criar directório seguro temporário para guardar o ficheiro PDF
        $upload_dir = wp_upload_dir();
        $buedoc_dir = $upload_dir['basedir'] . '/buedoc-invoices';

        if (!file_exists($buedoc_dir)) {
            wp_mkdir_p($buedoc_dir);
            // Proteger directório de acesso directo
            file_put_contents($buedoc_dir . '/index.php', '<?php // Silence is golden');
            file_put_contents($buedoc_dir . '/.htaccess', 'deny from all');
        }

        $clean_num = !empty($doc_number) ? preg_replace('/[^a-zA-Z0-9_\-]/', '-', $doc_number) : $order->get_id();
        $pdf_filepath = $buedoc_dir . '/Factura-' . $clean_num . '.pdf';

        // Se o ficheiro ainda não existir no cache local, descarrega via API
        if (!file_exists($pdf_filepath) || filesize($pdf_filepath) === 0) {
            $client = new BueDoc_API_Client();
            $res = $client->download_document_pdf($doc_id);

            if ($res['success'] && !empty($res['pdf_content'])) {
                file_put_contents($pdf_filepath, $res['pdf_content']);
            }
        }

        if (file_exists($pdf_filepath) && filesize($pdf_filepath) > 0) {
            $attachments[] = $pdf_filepath;
        }

        return $attachments;
    }
}
