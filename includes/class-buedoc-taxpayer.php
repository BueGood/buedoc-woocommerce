<?php
/**
 * BueDoc Taxpayer (NIF) Management
 *
 * Gestão do campo NIF no checkout e validação na AGT.
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_Taxpayer {

    /**
     * Construtor e ganchos do WooCommerce.
     */
    public function __construct() {
        // Adicionar campo NIF no checkout
        add_filter('woocommerce_billing_fields', [$this, 'add_billing_nif_field'], 20);

        // Validação no checkout
        add_action('woocommerce_checkout_process', [$this, 'validate_billing_nif_on_checkout']);

        // Gravação nos metadados da encomenda
        add_action('woocommerce_checkout_create_order', [$this, 'save_nif_to_order'], 20, 2);

        // Gravação no perfil do cliente
        add_action('woocommerce_customer_save_address', [$this, 'save_nif_to_customer'], 20, 2);

        // Exibir NIF no painel de administração da encomenda
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_nif_in_admin_order']);

        // AJAX para validação em tempo real no checkout
        add_action('wp_ajax_buedoc_validate_nif', [$this, 'ajax_validate_nif']);
        add_action('wp_ajax_nopriv_buedoc_validate_nif', [$this, 'ajax_validate_nif']);

        // Scripts e estilos de checkout
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_assets']);
    }

    /**
     * Adiciona o campo NIF aos campos de facturação do WooCommerce.
     *
     * @param array $fields
     * @return array
     */
    public function add_billing_nif_field($fields) {
        $requirement = BueDoc_Settings::get_option('nif_field_requirement', 'optional');
        if ($requirement === 'hidden') {
            return $fields;
        }

        $label = BueDoc_Settings::get_option('nif_field_label', __('NIF (Contribuinte)', 'buedoc-facturacao-electronica-agt'));
        $is_required = ($requirement === 'required');

        $fields['billing_nif'] = [
            'label'        => $label,
            'placeholder'  => __('Ex.: 5417000000 ou 004512873LA041', 'buedoc-facturacao-electronica-agt'),
            'required'     => $is_required,
            'class'        => ['form-row-wide'],
            'clear'        => true,
            'priority'     => 35, // Logo após nome ou empresa
            'autocomplete' => 'tax-id',
        ];

        return $fields;
    }

    /**
     * Valida o NIF no momento da submissão do checkout.
     */
    public function validate_billing_nif_on_checkout() {
        $requirement = BueDoc_Settings::get_option('nif_field_requirement', 'optional');
        if ($requirement === 'hidden') {
            return;
        }

        $nif = isset($_POST['billing_nif']) ? sanitize_text_field(wp_unslash($_POST['billing_nif'])) : '';
        $nif = trim(strtoupper($nif));

        if ($requirement === 'required' && empty($nif)) {
            wc_add_notice(__('Por favor, introduza o seu NIF (Número de Identificação Fiscal).', 'buedoc-facturacao-electronica-agt'), 'error');
            return;
        }

        if (empty($nif)) {
            return; // Opcional e não preenchido
        }

        // Se for consumidor final convencional, é válido
        if ($nif === '999999999') {
            return;
        }

        // Validação de formato em Angola:
        // Empresas: tipicamente 10 dígitos numéricos (ex: 5417000000)
        // Pessoais: número do BI, frequentemente 14 caracteres (ex: 004512873LA041)
        if (strlen($nif) < 9 || strlen($nif) > 15) {
            wc_add_notice(__('O NIF introduzido possui um formato inválido.', 'buedoc-facturacao-electronica-agt'), 'error');
            return;
        }

        // Validação online via API BueDoc se habilitado
        $validate_live = (BueDoc_Settings::get_option('validate_nif_live', 'yes') === 'yes');
        $api_key = BueDoc_Settings::get_option('api_key', '');

        if ($validate_live && !empty($api_key)) {
            $client = new BueDoc_API_Client();
            $res = $client->validate_taxpayer($nif);

            if ($res['success'] && isset($res['data'])) {
                if (empty($res['data']['isValid']) || empty($res['data']['active'])) {
                    $msg = !empty($res['data']['message'])
                        ? $res['data']['message']
                        : __('O NIF indicado não é válido ou não se encontra activo na AGT.', 'buedoc-facturacao-electronica-agt');
                    wc_add_notice($msg, 'error');
                }
            }
        }
    }

    /**
     * Guarda o NIF nos metadados da encomenda do WooCommerce.
     *
     * @param WC_Order $order
     * @param array    $data
     */
    public function save_nif_to_order($order, $data) {
        $requirement = BueDoc_Settings::get_option('nif_field_requirement', 'optional');
        $nif = isset($_POST['billing_nif']) ? sanitize_text_field(wp_unslash($_POST['billing_nif'])) : '';
        $nif = trim(strtoupper($nif));

        if (empty($nif)) {
            $default_cf = BueDoc_Settings::get_option('consumer_final_nif', '999999999');
            $nif = !empty($default_cf) ? $default_cf : '999999999';
        }

        $order->update_meta_data('_billing_nif', $nif);
        $order->update_meta_data('_buedoc_client_nif', $nif);
    }

    /**
     * Guarda o NIF no perfil do cliente ao actualizar morada de facturação.
     *
     * @param int    $user_id
     * @param string $load_address
     */
    public function save_nif_to_customer($user_id, $load_address) {
        if ($load_address === 'billing' && isset($_POST['billing_nif'])) {
            $nif = sanitize_text_field(wp_unslash($_POST['billing_nif']));
            update_user_meta($user_id, 'billing_nif', trim(strtoupper($nif)));
        }
    }

    /**
     * Exibe o NIF no painel de administração da encomenda.
     *
     * @param WC_Order $order
     */
    public function display_nif_in_admin_order($order) {
        $nif = $order->get_meta('_billing_nif');
        if (!$nif) {
            $nif = $order->get_meta('_buedoc_client_nif');
        }

        if ($nif) {
            echo '<p><strong>' . esc_html__('NIF:', 'buedoc-facturacao-electronica-agt') . '</strong> ' . esc_html($nif) . '</p>';
        }
    }

    /**
     * AJAX handler para validação de NIF no frontend.
     */
    public function ajax_validate_nif() {
        check_ajax_referer('buedoc_checkout_nonce', 'nonce');

        $nif = isset($_POST['nif']) ? sanitize_text_field(wp_unslash($_POST['nif'])) : '';
        $nif = trim(strtoupper($nif));

        if (empty($nif)) {
            wp_send_json_error(['message' => __('NIF vazio.', 'buedoc-facturacao-electronica-agt')]);
        }

        if ($nif === '999999999') {
            wp_send_json_success([
                'isValid' => true,
                'active'  => true,
                'kind'    => 'INDIVIDUAL',
                'message' => __('Consumidor Final', 'buedoc-facturacao-electronica-agt'),
            ]);
        }

        $client = new BueDoc_API_Client();
        $res = $client->validate_taxpayer($nif);

        if ($res['success'] && isset($res['data'])) {
            wp_send_json_success($res['data']);
        } else {
            $msg = !empty($res['error']) ? $res['error'] : __('NIF inválido ou não encontrado na AGT.', 'buedoc-facturacao-electronica-agt');
            wp_send_json_error(['message' => $msg]);
        }
    }

    /**
     * Carrega estilos e scripts na página de checkout.
     */
    public function enqueue_checkout_assets() {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_style(
            'buedoc-checkout',
            plugins_url('assets/css/checkout.css', dirname(__FILE__)),
            [],
            defined('BUEDOC_WC_VERSION') ? BUEDOC_WC_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'buedoc-checkout',
            plugins_url('assets/js/checkout.js', dirname(__FILE__)),
            ['jquery'],
            defined('BUEDOC_WC_VERSION') ? BUEDOC_WC_VERSION : '1.0.0',
            true
        );

        $validate_live = (BueDoc_Settings::get_option('validate_nif_live', 'yes') === 'yes');

        wp_localize_script('buedoc-checkout', 'buedocCheckoutData', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('buedoc_checkout_nonce'),
            'validateLive' => $validate_live,
        ]);
    }
}
