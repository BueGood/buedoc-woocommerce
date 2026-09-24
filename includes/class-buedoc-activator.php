<?php
/**
 * Fired during plugin activation
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_Activator {

    /**
     * Define as opções padrão no momento da ativação do plugin.
     */
    public static function activate() {
        $default_options = [
            'enabled'                => 'yes',
            'environment'            => 'production',
            'api_url'                => '',
            'api_key'                => '',
            'auto_issue'             => 'yes',
            'trigger_status'         => ['processing', 'completed'],
            'doc_type'               => 'FR',
            'default_payment_method' => 'Multicaixa',
            'due_days'               => '30',
            'notes_template'         => 'Documento emitido automaticamente via BueDoc para a Encomenda #{order_number}.',
            'default_vat_rate'       => '14',
            'default_exemption'      => 'M10',
            'shipping_vat_rate'      => '14',
            'shipping_exemption'     => 'M10',
            'nif_field_requirement'  => 'optional',
            'nif_field_label'        => 'NIF (Contribuinte)',
            'validate_nif_live'      => 'yes',
            'consumer_final_nif'     => '999999999',
            'attach_pdf_email'       => 'yes',
            'show_link_email'        => 'yes',
            'show_link_my_account'   => 'yes',
            'debug_log'              => 'no',
        ];

        $current_settings = get_option('woocommerce_buedoc_settings', []);
        $merged_settings = wp_parse_args($current_settings, $default_options);
        update_option('woocommerce_buedoc_settings', $merged_settings);
    }
}
