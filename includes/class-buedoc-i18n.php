<?php
/**
 * Internationalization functionality
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_i18n {

    /**
     * Carrega o text domain para traduções.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'buedoc-facturacao-electronica-agt',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
