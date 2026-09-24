<?php
/**
 * Fired during plugin deactivation
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_Deactivator {

    /**
     * Limpeza suave ao desactivar o plugin.
     */
    public static function deactivate() {
        // Preserva os metadados das encomendas e as definições para não perder dados acidentalmente.
    }
}
