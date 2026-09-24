<?php
/**
 * BueDoc Admin Interface
 *
 * Meta boxes, colunas da lista de encomendas, acções em massa e descarga de PDF.
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_Admin {

    /**
     * Construtor e registo de ganchos.
     */
    public function __construct() {
        // Enfileirar scripts e estilos no painel de administração
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Registar Meta Box da encomenda (Compatível com CPT e HPOS)
        add_action('add_meta_boxes', [$this, 'register_order_meta_box'], 10, 2);

        // Colunas na lista de encomendas (CPT clássico)
        add_filter('manage_shop_order_posts_columns', [$this, 'add_order_column']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_order_column_cpt'], 10, 2);

        // Colunas na lista de encomendas (HPOS - High-Performance Order Storage)
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_order_column']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_order_column_hpos'], 10, 2);

        // Acções em massa na listagem de encomendas
        add_filter('bulk_actions-edit-shop_order', [$this, 'register_bulk_actions']);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'register_bulk_actions']);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_actions'], 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk_actions'], 10, 3);

        // Notificações administrativas de acções em massa
        add_action('admin_notices', [$this, 'render_bulk_action_notices']);

        // Ponto de terminação para descarregar o PDF da factura
        add_action('admin_post_buedoc_download_pdf', [$this, 'handle_download_pdf']);
        add_action('admin_post_nopriv_buedoc_download_pdf', [$this, 'handle_download_pdf']);

        // Handlers AJAX
        add_action('wp_ajax_buedoc_issue_order_document', [$this, 'ajax_issue_order_document']);
        add_action('wp_ajax_buedoc_refresh_agt_status', [$this, 'ajax_refresh_agt_status']);
        add_action('wp_ajax_buedoc_issue_credit_note', [$this, 'ajax_issue_credit_note']);
    }

    /**
     * Carrega estilos e scripts nas páginas relevantes do painel.
     *
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $is_order_page = in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders', 'edit-shop_order'], true);
        $is_settings_page = (strpos($screen->id, 'woocommerce_page_wc-settings') !== false);

        if (!$is_order_page && !$is_settings_page) {
            return;
        }

        wp_enqueue_style(
            'buedoc-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            ['dashicons'],
            defined('BUEDOC_WC_VERSION') ? BUEDOC_WC_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'buedoc-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery'],
            defined('BUEDOC_WC_VERSION') ? BUEDOC_WC_VERSION : '1.0.0',
            true
        );

        wp_localize_script('buedoc-admin', 'buedocAdminData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('buedoc_admin_nonce'),
        ]);
    }

    /**
     * Regista a Meta Box de detalhes da factura na encomenda.
     *
     * @param string $post_type
     * @param WP_Post|WC_Order $post_or_order
     */
    public function register_order_meta_box($post_type, $post_or_order) {
        $order_screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') &&
            wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'buedoc_order_invoice',
            __('Factura BueDoc (AGT)', 'buedoc-woocommerce'),
            [$this, 'render_order_meta_box'],
            $order_screen,
            'side',
            'high'
        );
    }

    /**
     * Renderiza o conteúdo da Meta Box.
     *
     * @param WP_Post|WC_Order $post_or_order
     */
    public function render_order_meta_box($post_or_order) {
        $order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID);
        if (!$order) {
            echo '<p>' . esc_html__('Encomenda não encontrada.', 'buedoc-woocommerce') . '</p>';
            return;
        }

        $order_id        = $order->get_id();
        $doc_id          = $order->get_meta('_buedoc_document_id');
        $doc_number      = $order->get_meta('_buedoc_document_number');
        $doc_type        = $order->get_meta('_buedoc_document_type');
        $doc_date        = $order->get_meta('_buedoc_document_date');
        $doc_hash_short  = $order->get_meta('_buedoc_document_hash_short');
        $doc_qr_code     = $order->get_meta('_buedoc_document_qr_code');
        $agt_status      = $order->get_meta('_buedoc_agt_status');
        $gross_total     = $order->get_meta('_buedoc_document_gross_total');
        $nc_number       = $order->get_meta('_buedoc_nc_number');
        $has_refunds     = ((float)$order->get_total_refunded() > 0);

        $download_url = add_query_arg([
            'action'   => 'buedoc_download_pdf',
            'order_id' => $order_id,
            'nonce'    => wp_create_nonce('buedoc_download_pdf_' . $order_id),
        ], admin_url('admin-post.php'));

        $template_path = dirname(dirname(__FILE__)) . '/templates/admin/meta-box-order.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    /**
     * Adiciona coluna BueDoc à tabela de encomendas.
     *
     * @param array $columns
     * @return array
     */
    public function add_order_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'order_status' || $key === 'order_number') {
                $new_columns['buedoc_invoice'] = __('Factura BueDoc', 'buedoc-woocommerce');
            }
        }
        if (!isset($new_columns['buedoc_invoice'])) {
            $new_columns['buedoc_invoice'] = __('Factura BueDoc', 'buedoc-woocommerce');
        }
        return $new_columns;
    }

    /**
     * Renderiza o conteúdo da coluna BueDoc no CPT clássico.
     *
     * @param string $column
     * @param int    $post_id
     */
    public function render_order_column_cpt($column, $post_id) {
        if ($column === 'buedoc_invoice') {
            $order = wc_get_order($post_id);
            if ($order) {
                $this->output_column_content($order);
            }
        }
    }

    /**
     * Renderiza o conteúdo da coluna BueDoc no HPOS.
     *
     * @param string   $column
     * @param WC_Order $order
     */
    public function render_order_column_hpos($column, $order) {
        if ($column === 'buedoc_invoice' && $order instanceof WC_Order) {
            $this->output_column_content($order);
        }
    }

    /**
     * Gera o HTML da coluna de factura.
     *
     * @param WC_Order $order
     */
    private function output_column_content($order) {
        $doc_id     = $order->get_meta('_buedoc_document_id');
        $doc_number = $order->get_meta('_buedoc_document_number');
        $doc_type   = $order->get_meta('_buedoc_document_type');
        $agt_status = $order->get_meta('_buedoc_agt_status');
        $order_id   = $order->get_id();

        if (!empty($doc_number)) {
            $download_url = add_query_arg([
                'action'   => 'buedoc_download_pdf',
                'order_id' => $order_id,
                'nonce'    => wp_create_nonce('buedoc_download_pdf_' . $order_id),
            ], admin_url('admin-post.php'));

            echo '<a href="' . esc_url($download_url) . '" target="_blank" class="buedoc-badge buedoc-badge-doc-' . esc_attr(strtolower($doc_type)) . '" title="' . esc_attr__('Descarregar PDF', 'buedoc-woocommerce') . '">';
            echo esc_html($doc_number);
            echo '</a>';

            if (!empty($agt_status)) {
                echo '<br><span class="buedoc-badge buedoc-badge-' . esc_attr($agt_status) . '" style="margin-top:3px; font-size:9px;">' . esc_html($agt_status) . '</span>';
            }
        } else {
            echo '<span style="color:#94a3b8;">&mdash;</span>';
        }
    }

    /**
     * Adiciona a acção em massa para emitir facturas no BueDoc.
     *
     * @param array $actions
     * @return array
     */
    public function register_bulk_actions($actions) {
        $actions['buedoc_bulk_issue'] = __('Emitir Facturas no BueDoc', 'buedoc-woocommerce');
        return $actions;
    }

    /**
     * Processa a acção em massa.
     *
     * @param string $redirect_to
     * @param string $action
     * @param array  $order_ids
     * @return string
     */
    public function handle_bulk_actions($redirect_to, $action, $order_ids) {
        if ($action !== 'buedoc_bulk_issue') {
            return $redirect_to;
        }

        $order_manager = new BueDoc_Order_Manager();
        $emitted_count = 0;
        $failed_count  = 0;

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            // Pula se já tiver documento emitido
            if ($order->get_meta('_buedoc_document_id')) {
                continue;
            }

            $res = $order_manager->issue_invoice_for_order($order);
            if ($res['success']) {
                $emitted_count++;
            } else {
                $failed_count++;
            }
        }

        $redirect_to = add_query_arg([
            'buedoc_bulk_emitted' => $emitted_count,
            'buedoc_bulk_failed'  => $failed_count,
        ], $redirect_to);

        return $redirect_to;
    }

    /**
     * Exibe avisos de resultado da acção em massa.
     */
    public function render_bulk_action_notices() {
        if (isset($_GET['buedoc_bulk_emitted'])) {
            $emitted = (int)$_GET['buedoc_bulk_emitted'];
            $failed  = isset($_GET['buedoc_bulk_failed']) ? (int)$_GET['buedoc_bulk_failed'] : 0;

            if ($emitted > 0) {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html(sprintf(_n('%d factura BueDoc emitida com sucesso.', '%d facturas BueDoc emitidas com sucesso.', $emitted, 'buedoc-woocommerce'), $emitted))
                );
            }

            if ($failed > 0) {
                printf(
                    '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                    esc_html(sprintf(_n('%d encomenda falhou ou já possuía factura.', '%d encomendas falharam ou já possuíam factura.', $failed, 'buedoc-woocommerce'), $failed))
                );
            }
        }
    }

    /**
     * Processa a descarga segura do PDF da factura.
     */
    public function handle_download_pdf() {
        $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
        if (!$order_id) {
            wp_die(esc_html__('ID de encomenda inválido.', 'buedoc-woocommerce'));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(esc_html__('Encomenda não encontrada.', 'buedoc-woocommerce'));
        }

        // Validação de permissões: Admin ou o próprio cliente titular
        $can_view = false;
        if (current_user_can('manage_woocommerce')) {
            $can_view = true;
        } elseif (is_user_logged_in() && (int)$order->get_customer_id() === get_current_user_id()) {
            $can_view = true;
        } elseif (isset($_GET['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'buedoc_download_pdf_' . $order_id)) {
            $can_view = true;
        }

        if (!$can_view) {
            wp_die(esc_html__('Acesso negado para descarregar este documento fiscal.', 'buedoc-woocommerce'), 403);
        }

        $doc_id     = $order->get_meta('_buedoc_document_id');
        $doc_number = $order->get_meta('_buedoc_document_number');

        if (empty($doc_id)) {
            wp_die(esc_html__('Nenhum documento fiscal BueDoc registado nesta encomenda.', 'buedoc-woocommerce'));
        }

        $client = new BueDoc_API_Client();
        $pdf_res = $client->download_document_pdf($doc_id);

        if (!$pdf_res['success'] || empty($pdf_res['pdf_content'])) {
            wp_die(esc_html(!empty($pdf_res['error']) ? $pdf_res['error'] : __('Falha ao descarregar o PDF do BueDoc.', 'buedoc-woocommerce')));
        }

        $filename = sanitize_file_name('Factura-' . (!empty($doc_number) ? str_replace(' ', '-', $doc_number) : $order_id) . '.pdf');

        // Headers para envio do PDF
        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($pdf_res['pdf_content']));

        echo $pdf_res['pdf_content'];
        exit;
    }

    /**
     * AJAX handler para emissão manual a partir do Meta Box.
     */
    public function ajax_issue_order_document() {
        check_ajax_referer('buedoc_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Sem permissões para emitir documentos.', 'buedoc-woocommerce')]);
        }

        $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $doc_type = isset($_POST['doc_type']) ? sanitize_text_field(wp_unslash($_POST['doc_type'])) : 'FR';

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Encomenda não encontrada.', 'buedoc-woocommerce')]);
        }

        $order_manager = new BueDoc_Order_Manager();
        $res = $order_manager->issue_invoice_for_order($order, $doc_type);

        if ($res['success']) {
            wp_send_json_success($res['document']);
        } else {
            wp_send_json_error(['message' => $res['error']]);
        }
    }

    /**
     * AJAX handler para actualizar estado da AGT a partir do Meta Box.
     */
    public function ajax_refresh_agt_status() {
        check_ajax_referer('buedoc_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Sem permissões para esta acção.', 'buedoc-woocommerce')]);
        }

        $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Encomenda não encontrada.', 'buedoc-woocommerce')]);
        }

        $doc_id = $order->get_meta('_buedoc_document_id');
        if (empty($doc_id)) {
            wp_send_json_error(['message' => __('Nenhum documento associado a esta encomenda.', 'buedoc-woocommerce')]);
        }

        $client = new BueDoc_API_Client();
        $res = $client->get_document($doc_id);

        if ($res['success'] && isset($res['data'])) {
            $doc = $res['data'];
            if (!empty($doc['agtStatus'])) {
                $order->update_meta_data('_buedoc_agt_status', $doc['agtStatus']);
            }
            if (!empty($doc['qrCode'])) {
                $order->update_meta_data('_buedoc_document_qr_code', $doc['qrCode']);
            }
            $order->save();

            wp_send_json_success(['agtStatus' => $doc['agtStatus']]);
        } else {
            wp_send_json_error(['message' => !empty($res['error']) ? $res['error'] : __('Erro ao consultar documento na API BueDoc.', 'buedoc-woocommerce')]);
        }
    }

    /**
     * AJAX handler para emissão de Nota de Crédito (NC).
     */
    public function ajax_issue_credit_note() {
        check_ajax_referer('buedoc_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Sem permissões.', 'buedoc-woocommerce')]);
        }

        $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Encomenda não encontrada.', 'buedoc-woocommerce')]);
        }

        $order_manager = new BueDoc_Order_Manager();
        $res = $order_manager->issue_credit_note_for_order($order);

        if ($res['success']) {
            wp_send_json_success($res['document']);
        } else {
            wp_send_json_error(['message' => $res['error']]);
        }
    }
}
