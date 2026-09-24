<?php
/**
 * Plugin Name:       BueDoc Facturação para WooCommerce
 * Plugin URI:        https://doc.buegood.com
 * Description:       Emissão automática de facturas e facturas-recibo certificadas pela AGT em Angola através da API BueDoc.
 * Version:           1.0.0
 * Author:            BueGood Tecnologias
 * Author URI:        https://buegood.com
 * Text Domain:       buedoc-woocommerce
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * WC tested up to:   9.5
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Constantes do Plugin
define('BUEDOC_WC_VERSION', '1.0.0');
define('BUEDOC_WC_PLUGIN_FILE', __FILE__);
define('BUEDOC_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BUEDOC_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BUEDOC_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 2. Compatibilidade com High-Performance Order Storage (HPOS) do WooCommerce
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// 3. Ficheiros de activação e desactivação
require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-activator.php';
require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-deactivator.php';

register_activation_hook(__FILE__, ['BueDoc_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['BueDoc_Deactivator', 'deactivate']);

/**
 * Classe principal do plugin.
 */
class BueDoc_WooCommerce {

    /**
     * Instância única (Singleton).
     *
     * @var BueDoc_WooCommerce|null
     */
    private static $instance = null;

    /**
     * Obtém a instância única.
     *
     * @return BueDoc_WooCommerce
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init_plugin'], 10);
        add_filter('plugin_action_links_' . BUEDOC_WC_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);
        add_action('admin_menu', [$this, 'register_admin_menu'], 60);
    }

    /**
     * Inicializa os módulos do plugin após o carregamento dos plugins.
     */
    public function init_plugin() {
        // Verificar se o WooCommerce está activo
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'notice_woocommerce_required']);
            return;
        }

        // Carregar dependências
        $this->load_dependencies();

        // Inicializar internacionalização
        $i18n = new BueDoc_i18n();
        $i18n->load_plugin_textdomain();

        // Inicializar componentes
        new BueDoc_Settings();
        new BueDoc_Taxpayer();
        new BueDoc_Order_Manager();
        new BueDoc_Admin();
        new BueDoc_Emails();
        new BueDoc_My_Account();
    }

    /**
     * Inclui as classes necessárias.
     */
    private function load_dependencies() {
        require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-i18n.php';
        require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-api-client.php';
        require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-settings.php';
        require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-taxpayer.php';
        require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-order-manager.php';
        require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-admin.php';
        require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-emails.php';
        require_once BUEDOC_WC_PLUGIN_DIR . 'includes/class-buedoc-my-account.php';
    }

    /**
     * Notificação caso o WooCommerce não esteja instalado ou activo.
     */
    public function notice_woocommerce_required() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php esc_html_e('BueDoc Facturação para WooCommerce', 'buedoc-woocommerce'); ?>:</strong>
                <?php esc_html_e('Este plugin necessita que o WooCommerce esteja instalado e activo.', 'buedoc-woocommerce'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Adiciona links directos nas acções da página de plugins.
     *
     * @param array $links
     * @return array
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=buedoc'),
            __('Definições', 'buedoc-woocommerce')
        );

        $docs_link = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            'https://doc.buegood.com/api/docs',
            __('Documentação', 'buedoc-woocommerce')
        );

        array_unshift($links, $settings_link);
        $links[] = $docs_link;

        return $links;
    }

    /**
     * Regista atalho no submenu do WooCommerce.
     */
    public function register_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('BueDoc Facturação', 'buedoc-woocommerce'),
            __('BueDoc Facturação', 'buedoc-woocommerce'),
            'manage_woocommerce',
            'admin.php?page=wc-settings&tab=buedoc'
        );
    }
}

// Inicializar plugin
BueDoc_WooCommerce::instance();
