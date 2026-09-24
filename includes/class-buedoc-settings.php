<?php
/**
 * BueDoc Settings
 *
 * Configurações do BueDoc no painel do WooCommerce.
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_Settings {

    /**
     * ID da aba nas definições do WooCommerce.
     */
    const TAB_ID = 'buedoc';

    /**
     * Inicializa os ganchos de definições.
     */
    public function __construct() {
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_' . self::TAB_ID, [$this, 'output_settings']);
        add_action('woocommerce_update_options_' . self::TAB_ID, [$this, 'save_settings']);
        add_action('wp_ajax_buedoc_test_connection', [$this, 'ajax_test_connection']);
    }

    /**
     * Adiciona a aba BueDoc às configurações do WooCommerce.
     *
     * @param array $tabs
     * @return array
     */
    public function add_settings_tab($tabs) {
        $tabs[self::TAB_ID] = __('BueDoc Facturação', 'buedoc-facturacao-electronica-agt');
        return $tabs;
    }

    /**
     * Retorna a lista de motivos de isenção oficiais da AGT (Anexo 9.1 DS-120).
     *
     * @return array
     */
    public static function get_agt_exemptions() {
        return [
            'M00' => 'M00 - IVA – Regime Simplificado',
            'M02' => 'M02 - Transmissão de bens e serviço não sujeita',
            'M04' => 'M04 - IVA – Regime de Exclusão',
            'M10' => 'M10 - Isento nos termos da alínea a) do nº1 do artigo 12.º do CIVA (Bens alimentares)',
            'M11' => 'M11 - Isento nos termos da alínea b) do nº1 do artigo 12.º do CIVA (Medicamentos)',
            'M12' => 'M12 - Isento nos termos da alínea c) do nº1 do artigo 12.º do CIVA (Próteses/deficiência)',
            'M13' => 'M13 - Isento nos termos da alínea d) do nº1 do artigo 12.º do CIVA (Livros)',
            'M14' => 'M14 - Isento nos termos da alínea e) do nº1 do artigo 12.º do CIVA (Locação habitacional)',
            'M15' => 'M15 - Isento nos termos da alínea f) do nº1 do artigo 12.º do CIVA (Operações sujeitas a SISA)',
            'M16' => 'M16 - Isento nos termos da alínea g) do nº1 do artigo 12.º do CIVA (Jogos de fortuna ou azar)',
            'M17' => 'M17 - Isento nos termos da alínea h) do nº1 do artigo 12.º do CIVA (Transporte colectivo de passageiros)',
            'M18' => 'M18 - Isento nos termos da alínea i) do nº1 do artigo 12.º do CIVA (Intermediação financeira)',
            'M20' => 'M20 - IVA – Regime simplificado',
            'M30' => 'M30 - Isento nos termos da alínea a) do artigo 15.º do CIVA (Exportações de bens)',
            'M38' => 'M38 - Isento nos termos da alínea i) do artigo 15.º do CIVA (Transporte internacional de passageiros)',
            'M90' => 'M90 - Regime de zona franca/armazém aduaneiro',
            'M91' => 'M91 - Bens expedidos para depósitos aduaneiros',
            'M92' => 'M92 - Transmissões em regimes aduaneiros',
            'M93' => 'M93 - Trânsito/draubaque/importação temporária',
            'M99' => 'M99 - Outro motivo de isenção',
        ];
    }

    /**
     * Define o esquema de campos das definições.
     *
     * @return array
     */
    public function get_settings_schema() {
        $order_statuses = wc_get_order_statuses();
        // Remove 'wc-' prefix dos estados para facilitar comparação
        $clean_statuses = [];
        foreach ($order_statuses as $key => $label) {
            $clean_key = str_replace('wc-', '', $key);
            $clean_statuses[$clean_key] = $label;
        }

        return [
            [
                'title' => __('Ligação à API BueDoc', 'buedoc-facturacao-electronica-agt'),
                'type'  => 'title',
                'desc'  => __('Configure a integração com o software de facturação certificado pela AGT BueDoc.', 'buedoc-facturacao-electronica-agt'),
                'id'    => 'buedoc_api_section',
            ],
            [
                'title'   => __('Activar BueDoc', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Activar emissão de facturas certificadas pela AGT no WooCommerce.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_enabled',
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            [
                'title'   => __('Ambiente da API', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Escolha o ambiente da API BueDoc.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_environment',
                'type'    => 'select',
                'default' => 'production',
                'options' => [
                    'production' => __('Produção (doc.buegood.com)', 'buedoc-facturacao-electronica-agt'),
                    'sandbox'    => __('Homologação / Sandbox (sandbox.buegood.com)', 'buedoc-facturacao-electronica-agt'),
                    'custom'     => __('URL Personalizada / Localhost', 'buedoc-facturacao-electronica-agt'),
                ],
            ],
            [
                'title'             => __('URL da API Personalizada', 'buedoc-facturacao-electronica-agt'),
                'desc'              => __('Preencha apenas se tiver seleccionado URL Personalizada (ex.: http://localhost:3000/api).', 'buedoc-facturacao-electronica-agt'),
                'id'                => 'woocommerce_buedoc_api_url',
                'type'              => 'text',
                'css'               => 'min-width:350px;',
                'desc_tip'          => true,
            ],
            [
                'title'    => __('Chave de API BueDoc', 'buedoc-facturacao-electronica-agt'),
                'desc'     => __('Chave de API gerada no painel BueDoc (menu Definições da Empresa > Chaves de API). Ex.: bd_live_...', 'buedoc-facturacao-electronica-agt'),
                'id'       => 'woocommerce_buedoc_api_key',
                'type'     => 'password',
                'css'      => 'min-width:350px;',
                'desc_tip' => true,
            ],
            [
                'type' => 'sectionend',
                'id'   => 'buedoc_api_section',
            ],

            // Secção: Emissão de Facturas
            [
                'title' => __('Emissão Automática de Facturas', 'buedoc-facturacao-electronica-agt'),
                'type'  => 'title',
                'desc'  => __('Defina quando e como os documentos fiscais são gerados e comunicados à AGT.', 'buedoc-facturacao-electronica-agt'),
                'id'    => 'buedoc_invoicing_section',
            ],
            [
                'title'   => __('Emissão Automática', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Emitir facturas automaticamente quando a encomenda atingir os estados seleccionados.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_auto_issue',
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            [
                'title'    => __('Estados para Emissão', 'buedoc-facturacao-electronica-agt'),
                'desc'     => __('A factura será gerada quando o estado da encomenda for alterado para qualquer um dos estados seleccionados.', 'buedoc-facturacao-electronica-agt'),
                'id'       => 'woocommerce_buedoc_trigger_status',
                'type'     => 'multiselect',
                'class'    => 'wc-enhanced-select',
                'css'      => 'min-width: 350px;',
                'default'  => ['processing', 'completed'],
                'options'  => $clean_statuses,
                'desc_tip' => true,
            ],
            [
                'title'   => __('Tipo de Documento Fiscal', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Seleccione o tipo de documento a emitir por defeito.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_doc_type',
                'type'    => 'select',
                'default' => 'FR',
                'options' => [
                    'FR'   => __('Factura-Recibo (FR) — Venda paga no acto (Recomendado para e-commerce)', 'buedoc-facturacao-electronica-agt'),
                    'FT'   => __('Factura (FT) — Venda a prazo / aguarda pagamento', 'buedoc-facturacao-electronica-agt'),
                    'auto' => __('Automático: FR se paga (Processing/Completed), FT se pendente (On-Hold)', 'buedoc-facturacao-electronica-agt'),
                ],
            ],
            [
                'title'   => __('Meio de Pagamento Padrão', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Texto do meio de pagamento a imprimir na Factura-Recibo (ex.: Multicaixa, Transferência Bancária, Cartão de Crédito).', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_default_payment_method',
                'type'    => 'text',
                'default' => 'Multicaixa',
                'desc_tip' => true,
            ],
            [
                'title'             => __('Prazo de Vencimento (Dias)', 'buedoc-facturacao-electronica-agt'),
                'desc'              => __('Prazo em dias para a data de vencimento em caso de emissão de Facturas (FT).', 'buedoc-facturacao-electronica-agt'),
                'id'                => 'woocommerce_buedoc_due_days',
                'type'              => 'number',
                'custom_attributes' => ['min' => 0, 'step' => 1],
                'default'           => '30',
            ],
            [
                'title'   => __('Observações do Documento', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Texto a constar no rodapé da factura. Tags disponíveis: {order_number}, {site_name}, {order_date}.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_notes_template',
                'type'    => 'textarea',
                'css'     => 'min-width:350px; min-height: 70px;',
                'default' => 'Documento emitido automaticamente via BueDoc para a Encomenda #{order_number}.',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'buedoc_invoicing_section',
            ],

            // Secção: Regras de IVA e Isenção AGT
            [
                'title' => __('Regras Fiscais de IVA (AGT Angola)', 'buedoc-facturacao-electronica-agt'),
                'type'  => 'title',
                'desc'  => __('Definições padrão de IVA caso os produtos não possuam taxas específicas configuradas no WooCommerce.', 'buedoc-facturacao-electronica-agt'),
                'id'    => 'buedoc_tax_section',
            ],
            [
                'title'   => __('Taxa de IVA Padrão (%)', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Taxa de IVA geral aplicada aos produtos (Regime Geral: 14%).', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_default_vat_rate',
                'type'    => 'select',
                'default' => '14',
                'options' => [
                    '14' => '14% (Regime Geral)',
                    '7'  => '7% (Taxa reduzida)',
                    '5'  => '5% (Bens essenciais)',
                    '1'  => '1%',
                    '0'  => '0% (Isento / Não sujeito)',
                ],
            ],
            [
                'title'    => __('Motivo de Isenção Padrão (0%)', 'buedoc-facturacao-electronica-agt'),
                'desc'     => __('Exigido pela AGT para qualquer produto ou serviço tributado a 0%.', 'buedoc-facturacao-electronica-agt'),
                'id'       => 'woocommerce_buedoc_default_exemption',
                'type'     => 'select',
                'default'  => 'M10',
                'options'  => self::get_agt_exemptions(),
                'desc_tip' => true,
            ],
            [
                'title'   => __('Taxa de IVA nos Portes de Envio (%)', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Taxa de IVA aplicada aos custos de transporte.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_shipping_vat_rate',
                'type'    => 'select',
                'default' => '14',
                'options' => [
                    '14' => '14%',
                    '7'  => '7%',
                    '5'  => '5%',
                    '0'  => '0% (Isento)',
                ],
            ],
            [
                'title'    => __('Motivo de Isenção nos Portes (0%)', 'buedoc-facturacao-electronica-agt'),
                'desc'     => __('Motivo caso a taxa de envio seja 0%.', 'buedoc-facturacao-electronica-agt'),
                'id'       => 'woocommerce_buedoc_shipping_exemption',
                'type'     => 'select',
                'default'  => 'M10',
                'options'  => self::get_agt_exemptions(),
                'desc_tip' => true,
            ],
            [
                'type' => 'sectionend',
                'id'   => 'buedoc_tax_section',
            ],

            // Secção: NIF no Checkout
            [
                'title' => __('Campo de NIF no Checkout', 'buedoc-facturacao-electronica-agt'),
                'type'  => 'title',
                'desc'  => __('Configuração do campo de Número de Identificação Fiscal para clientes no checkout.', 'buedoc-facturacao-electronica-agt'),
                'id'    => 'buedoc_checkout_section',
            ],
            [
                'title'   => __('Exigência do NIF', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Comportamento do campo NIF na finalização de compra.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_nif_field_requirement',
                'type'    => 'select',
                'default' => 'optional',
                'options' => [
                    'optional' => __('Opcional (Preenche Consumidor Final 999999999 se vazio)', 'buedoc-facturacao-electronica-agt'),
                    'required' => __('Obrigatório (Exige NIF válido para concluir a compra)', 'buedoc-facturacao-electronica-agt'),
                    'hidden'   => __('Oculto (Emite sempre como Consumidor Final 999999999)', 'buedoc-facturacao-electronica-agt'),
                ],
            ],
            [
                'title'   => __('Rótulo do Campo NIF', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_nif_field_label',
                'type'    => 'text',
                'default' => 'NIF (Contribuinte)',
            ],
            [
                'title'   => __('Validação em Tempo Real', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Verificar a validade e actividade do NIF directamente na AGT durante o checkout.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_validate_nif_live',
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            [
                'title'   => __('NIF de Consumidor Final', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('NIF padrão quando o cliente não indicar o seu próprio NIF.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_consumer_final_nif',
                'type'    => 'text',
                'default' => '999999999',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'buedoc_checkout_section',
            ],

            // Secção: Emails e Área de Cliente
            [
                'title' => __('Emails e Área de Cliente', 'buedoc-facturacao-electronica-agt'),
                'type'  => 'title',
                'desc'  => __('Disponibilização do PDF da factura aos clientes.', 'buedoc-facturacao-electronica-agt'),
                'id'    => 'buedoc_emails_section',
            ],
            [
                'title'   => __('Anexar PDF aos Emails', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Anexar o PDF oficial certificado da factura aos emails de encomenda enviada ao cliente.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_attach_pdf_email',
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            [
                'title'   => __('Link de Descarga nos Emails', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Incluir botão/link de download do PDF no corpo do email da encomenda.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_show_link_email',
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            [
                'title'   => __('Botão em "A Minha Conta"', 'buedoc-facturacao-electronica-agt'),
                'desc'    => __('Permitir que clientes descarreguem a factura na lista de encomendas na área de cliente.', 'buedoc-facturacao-electronica-agt'),
                'id'      => 'woocommerce_buedoc_show_link_my_account',
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'buedoc_emails_section',
            ],

            // Secção: Depuração
            [
                'title' => __('Depuração e Diagnóstico', 'buedoc-facturacao-electronica-agt'),
                'type'  => 'title',
                'desc'  => __('Ferramentas de registo para suporte técnico.', 'buedoc-facturacao-electronica-agt'),
                'id'    => 'buedoc_debug_section',
            ],
            [
                'title'   => __('Registo de Logs (Debug)', 'buedoc-facturacao-electronica-agt'),
                'desc'    => sprintf(
                    __('Registar comunicações da API no registo do WooCommerce (%s).', 'buedoc-facturacao-electronica-agt'),
                    '<a href="' . admin_url('admin.php?page=wc-status&tab=logs') . '" target="_blank">' . __('Ver Logs', 'buedoc-facturacao-electronica-agt') . '</a>'
                ),
                'id'      => 'woocommerce_buedoc_debug_log',
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'buedoc_debug_section',
            ],
        ];
    }

    /**
     * Renderiza o cabeçalho e a página de definições.
     */
    public function output_settings() {
        $logo_url = plugins_url('assets/images/logo.svg', dirname(__FILE__));
        ?>
        <div class="buedoc-header-wrap">
            <div class="buedoc-header-logo">
                <img src="<?php echo esc_url($logo_url); ?>" alt="BueDoc" />
            </div>
            <div class="buedoc-header-badge">
                <span class="dashicons dashicons-shield"></span>
                <span>Certificado pela AGT (Angola) &bull; API v1</span>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <button type="button" id="buedoc-test-connection-btn" class="button button-secondary">
                <span class="dashicons dashicons-update" style="vertical-align: middle; margin-top: -2px;"></span>
                <?php esc_html_e('Testar Ligação à API BueDoc', 'buedoc-facturacao-electronica-agt'); ?>
            </button>
            <div id="buedoc-test-result" class="buedoc-connection-box" style="display:none;"></div>
        </div>
        <?php

        woocommerce_admin_fields($this->get_settings_schema());
    }

    /**
     * Salva as definições do WooCommerce.
     */
    public function save_settings() {
        woocommerce_update_options($this->get_settings_schema());

        // Salvar também em 'woocommerce_buedoc_settings' consolidado
        $consolidated = [];
        foreach ($this->get_settings_schema() as $field) {
            if (isset($field['id']) && strpos($field['id'], 'woocommerce_buedoc_') === 0) {
                $clean_key = str_replace('woocommerce_buedoc_', '', $field['id']);
                $val = get_option($field['id'], isset($field['default']) ? $field['default'] : '');
                $consolidated[$clean_key] = $val;
            }
        }
        update_option('woocommerce_buedoc_settings', $consolidated);
    }

    /**
     * AJAX handler para testar a ligação.
     */
    public function ajax_test_connection() {
        check_ajax_referer('buedoc_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Sem permissões para realizar esta acção.', 'buedoc-facturacao-electronica-agt')]);
        }

        $api_key     = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        $environment = isset($_POST['environment']) ? sanitize_text_field(wp_unslash($_POST['environment'])) : 'production';
        $custom_url  = isset($_POST['custom_url']) ? esc_url_raw(wp_unslash($_POST['custom_url'])) : '';

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('Chave de API não informada.', 'buedoc-facturacao-electronica-agt')]);
        }

        $client = new BueDoc_API_Client($api_key, $environment, $custom_url, true);
        $result = $client->test_connection();

        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error(['message' => !empty($result['error']) ? $result['error'] : __('Falha na ligação.', 'buedoc-facturacao-electronica-agt')]);
        }
    }

    /**
     * Obtém uma opção do BueDoc com valor padrão.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get_option($key, $default = '') {
        $settings = get_option('woocommerce_buedoc_settings', []);
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        return get_option('woocommerce_buedoc_' . $key, $default);
    }
}
