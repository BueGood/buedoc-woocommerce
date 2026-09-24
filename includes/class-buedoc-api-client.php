<?php
/**
 * BueDoc API Client
 *
 * Comunicação HTTP com a API v1 para Programadores do BueDoc.
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_API_Client {

    /**
     * Chave de API BueDoc.
     *
     * @var string
     */
    private $api_key;

    /**
     * Ambiente ('production', 'sandbox', 'custom').
     *
     * @var string
     */
    private $environment;

    /**
     * URL personalizada (caso environment seja 'custom').
     *
     * @var string
     */
    private $custom_url;

    /**
     * Se os logs de depuração estão activos.
     *
     * @var bool
     */
    private $debug = false;

    /**
     * Construtor.
     *
     * @param string|null $api_key
     * @param string|null $environment
     * @param string|null $custom_url
     * @param bool|null   $debug
     */
    public function __construct($api_key = null, $environment = null, $custom_url = null, $debug = null) {
        $settings = get_option('woocommerce_buedoc_settings', []);

        $this->api_key     = $api_key     !== null ? trim($api_key)     : (isset($settings['api_key']) ? trim($settings['api_key']) : '');
        $this->environment = $environment !== null ? $environment       : (isset($settings['environment']) ? $settings['environment'] : 'production');
        $this->custom_url  = $custom_url  !== null ? trim($custom_url)  : (isset($settings['api_url']) ? trim($settings['api_url']) : '');
        $this->debug       = $debug       !== null ? (bool)$debug       : (isset($settings['debug_log']) && $settings['debug_log'] === 'yes');
    }

    /**
     * Devolve a URL base da API BueDoc.
     *
     * @return string
     */
    public function get_base_url() {
        if ($this->environment === 'sandbox') {
            return 'https://sandbox.buegood.com/api';
        }

        if ($this->environment === 'custom' && !empty($this->custom_url)) {
            return untrailingslashit($this->custom_url);
        }

        return 'https://doc.buegood.com/api';
    }

    /**
     * Executa um pedido HTTP à API BueDoc.
     *
     * @param string $endpoint Ex.: '/v1/quota'
     * @param string $method   GET, POST, etc.
     * @param array|null $body Payload JSON
     * @param array $extra_headers
     * @param int $timeout Segundos
     * @return array Resposta normalizada ['success' => bool, 'code' => int, 'data' => mixed, 'error' => string|null]
     */
    public function request($endpoint, $method = 'GET', $body = null, $extra_headers = [], $timeout = 30) {
        $url = $this->get_base_url() . '/' . ltrim($endpoint, '/');

        $headers = array_merge([
            'X-API-Key'    => $this->api_key,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'User-Agent'   => 'BueDoc-WooCommerce/' . (defined('BUEDOC_WC_VERSION') ? BUEDOC_WC_VERSION : '1.0.0') . '; WordPress/' . get_bloginfo('version'),
        ], $extra_headers);

        $args = [
            'method'      => strtoupper($method),
            'timeout'     => $timeout,
            'headers'     => $headers,
            'sslverify'   => true,
            'data_format' => 'body',
        ];

        if ($body !== null) {
            $args['body'] = is_array($body) ? wp_json_encode($body) : $body;
        }

        $this->log("Início do pedido {$method} {$url}", [
            'method'  => $method,
            'url'     => $url,
            'headers' => array_diff_key($headers, ['X-API-Key' => '']), // Omite a chave dos logs
            'body'    => $body,
        ]);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log("Erro HTTP no pedido para {$url}: {$error_message}", [], 'error');
            return [
                'success' => false,
                'code'    => 0,
                'data'    => null,
                'error'   => $error_message,
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $data        = json_decode($raw_body, true);

        $this->log("Resposta recebida [HTTP {$status_code}] de {$url}", [
            'code' => $status_code,
            'data' => $data !== null ? $data : substr($raw_body, 0, 500),
        ]);

        $is_success = ($status_code >= 200 && $status_code < 300);

        $error_msg = null;
        if (!$is_success) {
            if (is_array($data) && !empty($data['message'])) {
                if (is_array($data['message'])) {
                    $error_msg = implode('; ', $data['message']);
                } else {
                    $error_msg = (string)$data['message'];
                }
            } elseif (is_array($data) && !empty($data['error'])) {
                $error_msg = (string)$data['error'];
            } else {
                $error_msg = "A API retornou erro HTTP {$status_code}.";
            }
        }

        return [
            'success' => $is_success,
            'code'    => $status_code,
            'data'    => $data !== null ? $data : $raw_body,
            'error'   => $error_msg,
        ];
    }

    /**
     * Testa a ligação com a API (valida a chave e obtém a quota).
     *
     * @return array
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'code'    => 400,
                'data'    => null,
                'error'   => 'Nenhuma Chave de API configurada.',
            ];
        }

        // 1. Obter Quota mensal
        $quota_res = $this->get_quota();
        if (!$quota_res['success']) {
            return $quota_res;
        }

        // 2. Obter Série reservada de API
        $series_res = $this->get_series('FT');

        return [
            'success' => true,
            'code'    => 200,
            'data'    => [
                'quota'  => $quota_res['data'],
                'series' => $series_res['success'] ? $series_res['data'] : null,
            ],
            'error'   => null,
        ];
    }

    /**
     * Consulta a quota mensal de documentos.
     *
     * @return array
     */
    public function get_quota() {
        return $this->request('/v1/quota', 'GET');
    }

    /**
     * Obtém as séries de API disponíveis para um tipo de documento.
     *
     * @param string $type FT, FR, PP, NC, AF
     * @return array
     */
    public function get_series($type = 'FT') {
        $type = strtoupper(trim($type));
        return $this->request('/v1/series?type=' . rawurlencode($type), 'GET');
    }

    /**
     * Valida um NIF perante a AGT.
     *
     * @param string $nif
     * @return array
     */
    public function validate_taxpayer($nif) {
        $nif = trim($nif);
        if (empty($nif)) {
            return [
                'success' => false,
                'code'    => 400,
                'data'    => null,
                'error'   => 'NIF não fornecido.',
            ];
        }

        return $this->request('/v1/taxpayers/' . rawurlencode($nif) . '/validate', 'GET');
    }

    /**
     * Emite um documento fiscal via API.
     *
     * @param array $payload
     * @return array
     */
    public function create_document(array $payload) {
        return $this->request('/v1/documents', 'POST', $payload, [], 45);
    }

    /**
     * Consulta os detalhes de um documento emitido.
     * Actualiza o estado na AGT debaixo dos panos se pendente.
     *
     * @param string $document_id
     * @return array
     */
    public function get_document($document_id) {
        return $this->request('/v1/documents/' . rawurlencode($document_id), 'GET');
    }

    /**
     * Descarrega o PDF oficial do documento emitido.
     *
     * @param string $document_id
     * @return array ['success' => bool, 'code' => int, 'pdf_content' => string, 'error' => string|null]
     */
    public function download_document_pdf($document_id) {
        $url = $this->get_base_url() . '/v1/documents/' . rawurlencode($document_id) . '/pdf';

        $args = [
            'method'    => 'GET',
            'timeout'   => 45,
            'headers'   => [
                'X-API-Key' => $this->api_key,
                'Accept'    => 'application/pdf',
            ],
            'sslverify' => true,
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success'     => false,
                'code'        => 0,
                'pdf_content' => null,
                'error'       => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code >= 200 && $code < 300 && !empty($body)) {
            return [
                'success'     => true,
                'code'        => $code,
                'pdf_content' => $body,
                'error'       => null,
            ];
        }

        return [
            'success'     => false,
            'code'        => $code,
            'pdf_content' => null,
            'error'       => "Erro ao descarregar PDF (HTTP {$code}).",
        ];
    }

    /**
     * Registo de logs com WC_Logger.
     *
     * @param string $message
     * @param array  $context
     * @param string $level info, notice, warning, error
     */
    public function log($message, $context = [], $level = 'info') {
        if (!$this->debug && $level === 'info') {
            return;
        }

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $log_context = ['source' => 'buedoc-woocommerce'];
            if (!empty($context)) {
                $message .= ' | Dados: ' . wp_json_encode($context);
            }
            $logger->log($level, $message, $log_context);
        }
    }
}
