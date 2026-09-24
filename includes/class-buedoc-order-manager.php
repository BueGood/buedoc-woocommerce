<?php
/**
 * BueDoc Order Manager
 *
 * Emissão de facturas, sincronização de dados e tratamento de encomendas no WooCommerce.
 *
 * @package BueDoc_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class BueDoc_Order_Manager {

    /**
     * Construtor e ganchos.
     */
    public function __construct() {
        // Disparo automático na mudança de estado da encomenda
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 20, 3);
    }

    /**
     * Gancho executado na mudança de estado da encomenda.
     *
     * @param int    $order_id
     * @param string $old_status
     * @param string $new_status
     */
    public function on_order_status_changed($order_id, $old_status, $new_status) {
        $enabled = BueDoc_Settings::get_option('enabled', 'yes');
        if ($enabled !== 'yes') {
            return;
        }

        $auto_issue = BueDoc_Settings::get_option('auto_issue', 'yes');
        if ($auto_issue !== 'yes') {
            return;
        }

        $trigger_statuses = BueDoc_Settings::get_option('trigger_status', ['processing', 'completed']);
        if (!is_array($trigger_statuses)) {
            $trigger_statuses = (array)$trigger_statuses;
        }

        // Verifica se o novo estado está nos estados de disparo configurados
        if (in_array($new_status, $trigger_statuses, true)) {
            $order = wc_get_order($order_id);
            if ($order) {
                $this->issue_invoice_for_order($order);
            }
        }
    }

    /**
     * Emite o documento fiscal no BueDoc para uma determinada encomenda.
     *
     * @param WC_Order    $order
     * @param string|null $force_doc_type Tipo forçado ('FR', 'FT') se chamado manualmente
     * @return array ['success' => bool, 'document' => array|null, 'error' => string|null]
     */
    public function issue_invoice_for_order($order, $force_doc_type = null) {
        if (!$order instanceof WC_Order) {
            return ['success' => false, 'error' => __('Encomenda inválida.', 'buedoc-facturacao-electronica-agt')];
        }

        $order_id = $order->get_id();

        // 1. Evitar emissão duplicada
        $existing_doc_id = $order->get_meta('_buedoc_document_id');
        if (!empty($existing_doc_id)) {
            $existing_number = $order->get_meta('_buedoc_document_number');
            return [
                'success' => false,
                'error'   => sprintf(
                    /* translators: 1: order ID, 2: existing document number */
                    __('A encomenda #%1$d já possui documento emitido no BueDoc (%2$s).', 'buedoc-facturacao-electronica-agt'),
                    $order_id,
                    $existing_number
                ),
            ];
        }

        // 2. Chave de API
        $api_key = BueDoc_Settings::get_option('api_key', '');
        if (empty($api_key)) {
            $err = __('Chave de API do BueDoc não configurada nas definições.', 'buedoc-facturacao-electronica-agt');
            $order->add_order_note('BueDoc: ' . $err);
            return ['success' => false, 'error' => $err];
        }

        // 3. Determinar Tipo de Documento (FR ou FT)
        $doc_type = $force_doc_type;
        if (empty($doc_type)) {
            $configured_type = BueDoc_Settings::get_option('doc_type', 'FR');
            if ($configured_type === 'auto') {
                $status = $order->get_status();
                if (in_array($status, ['processing', 'completed'], true)) {
                    $doc_type = 'FR'; // Pago
                } else {
                    $doc_type = 'FT'; // Aguarda pagamento
                }
            } else {
                $doc_type = $configured_type;
            }
        }
        $doc_type = in_array($doc_type, ['FR', 'FT', 'PP', 'NC', 'AF'], true) ? $doc_type : 'FR';

        // 4. Resolver dados do Cliente e NIF
        $client_nif = $order->get_meta('_billing_nif');
        if (empty($client_nif)) {
            $client_nif = $order->get_meta('_buedoc_client_nif');
        }
        $client_nif = trim(strtoupper((string)$client_nif));
        if (empty($client_nif)) {
            $default_cf = BueDoc_Settings::get_option('consumer_final_nif', '999999999');
            $client_nif = !empty($default_cf) ? $default_cf : '999999999';
        }

        // Nome do cliente
        $client_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $company     = trim($order->get_billing_company());
        if (!empty($company)) {
            $client_name = $company;
        }
        if (empty($client_name)) {
            $client_name = 'Consumidor Final';
        }

        $client_email   = $order->get_billing_email();
        $client_phone   = $order->get_billing_phone();
        $client_address = trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());
        $client_city    = $order->get_billing_city();

        // 5. Meio de pagamento
        $payment_method = $order->get_payment_method_title();
        if (empty($payment_method)) {
            $payment_method = BueDoc_Settings::get_option('default_payment_method', 'Multicaixa');
        }

        // 6. Data de vencimento para FT
        $due_date = null;
        if ($doc_type === 'FT') {
            $due_days = (int)BueDoc_Settings::get_option('due_days', 30);
            $due_date = gmdate('Y-m-d', strtotime("+{$due_days} days"));
        }

        // 7. Observações com tags substituídas
        $notes_template = BueDoc_Settings::get_option('notes_template', 'Documento emitido automaticamente para a Encomenda #{order_number}.');
        $notes = str_replace(
            ['{order_number}', '{site_name}', '{order_date}'],
            [$order->get_order_number(), get_bloginfo('name'), $order->get_date_created() ? $order->get_date_created()->date('d/m/Y') : gmdate('d/m/Y')],
            $notes_template
        );

        // 8. Construir linhas do documento
        $lines = $this->build_document_lines($order);

        if (empty($lines)) {
            $err = __('Não foi possível emitir documento sem linhas de produtos/serviços.', 'buedoc-facturacao-electronica-agt');
            $order->add_order_note('BueDoc: ' . $err);
            return ['success' => false, 'error' => $err];
        }

        // 9. Montar payload conforme contrato da API v1 BueDoc
        $payload = [
            'type'          => $doc_type,
            'clientNif'     => $client_nif,
            'clientName'    => $client_name,
            'clientEmail'   => !empty($client_email) ? $client_email : null,
            'clientPhone'   => !empty($client_phone) ? $client_phone : null,
            'clientAddress' => !empty($client_address) ? $client_address : null,
            'clientCity'    => !empty($client_city) ? $client_city : null,
            'paymentMethod' => $payment_method,
            'notes'         => !empty($notes) ? $notes : null,
            'lines'         => $lines,
        ];

        if ($due_date !== null) {
            $payload['dueDate'] = $due_date;
        }

        // 10. Enviar à API BueDoc
        $client = new BueDoc_API_Client();
        $response = $client->create_document($payload);

        if (!$response['success']) {
            $err_msg = !empty($response['error']) ? $response['error'] : __('Erro desconhecido retornado pela API BueDoc.', 'buedoc-facturacao-electronica-agt');
            /* translators: 1: document type (FR or FT), 2: error message */
            $order->add_order_note(sprintf(__('BueDoc: Falha na emissão da %1$s: %2$s', 'buedoc-facturacao-electronica-agt'), $doc_type, $err_msg));
            return ['success' => false, 'error' => $err_msg];
        }

        $doc_data = $response['data'];

        // 11. Salvar metadados na encomenda
        $order->update_meta_data('_buedoc_document_id', $doc_data['id']);
        $order->update_meta_data('_buedoc_document_number', $doc_data['number']);
        $order->update_meta_data('_buedoc_document_type', $doc_data['type']);
        $order->update_meta_data('_buedoc_document_series', $doc_data['series']);
        $order->update_meta_data('_buedoc_document_seq', $doc_data['seq']);
        $order->update_meta_data('_buedoc_document_date', $doc_data['date']);
        $order->update_meta_data('_buedoc_document_hash', !empty($doc_data['hash']) ? $doc_data['hash'] : '');
        $order->update_meta_data('_buedoc_document_hash_short', !empty($doc_data['hashShort']) ? $doc_data['hashShort'] : '');
        $order->update_meta_data('_buedoc_document_qr_code', !empty($doc_data['qrCode']) ? $doc_data['qrCode'] : '');
        $order->update_meta_data('_buedoc_agt_status', !empty($doc_data['agtStatus']) ? $doc_data['agtStatus'] : 'PENDING_TRANSMISSION');
        $order->update_meta_data('_buedoc_document_gross_total', isset($doc_data['totals']['gross']) ? $doc_data['totals']['gross'] : $order->get_total());
        $order->update_meta_data('_buedoc_issued_at', current_time('mysql'));

        $order->save();

        // 12. Adicionar nota explicativa na encomenda
        $note = sprintf(
            /* translators: 1: document number, 2: AGT status, 3: gross total */
            __('Documento fiscal BueDoc emitido com sucesso: %1$s (AGT Status: %2$s). Total: %3$s Kz.', 'buedoc-facturacao-electronica-agt'),
            $doc_data['number'],
            $doc_data['agtStatus'],
            number_format(isset($doc_data['totals']['gross']) ? $doc_data['totals']['gross'] : $order->get_total(), 2, ',', ' ')
        );
        $order->add_order_note($note);

        // Dispara acção para extensões
        do_action('buedoc_document_issued', $order_id, $doc_data);

        return [
            'success'  => true,
            'document' => $doc_data,
        ];
    }

    /**
     * Constrói as linhas de produtos, portes e taxas para envio à API BueDoc.
     *
     * @param WC_Order $order
     * @return array
     */
    private function build_document_lines($order) {
        $lines = [];

        $default_vat_rate  = (float)BueDoc_Settings::get_option('default_vat_rate', 14);
        $default_exemption = BueDoc_Settings::get_option('default_exemption', 'M10');

        // 1. Linhas de Produtos
        foreach ($order->get_items('line_item') as $item_id => $item) {
            $product  = $item->get_product();
            $qty      = (float)$item->get_quantity();
            if ($qty <= 0) {
                continue;
            }

            $name = $item->get_name();
            $sku  = $product ? $product->get_sku() : '';

            // Totais sem IVA (net) do WooCommerce
            $subtotal = (float)$item->get_subtotal(); // Antes de descontos
            $total    = (float)$item->get_total();    // Após descontos da linha

            // Determinar taxa de IVA
            $vat_rate  = $default_vat_rate;
            $exemption = ($vat_rate == 0) ? $default_exemption : null;

            // Se o WooCommerce possui impostos calculados para o item
            if (wc_tax_enabled() && $item->get_total_tax() > 0 && $total > 0) {
                $calc_rate = round(($item->get_total_tax() / $total) * 100);
                if (in_array($calc_rate, [14, 7, 5, 1, 0])) {
                    $vat_rate = $calc_rate;
                }
            } elseif (wc_tax_enabled() && $item->get_total_tax() == 0) {
                $vat_rate  = 0;
                $exemption = $default_exemption;
            }

            // Cálculo do preço unitário e desconto percentual
            $unit_price   = 0;
            $discount_pct = 0;

            if ($subtotal > 0 && $total < $subtotal) {
                // Há desconto na linha
                $unit_price   = round($subtotal / $qty, 2);
                $discount_pct = round((($subtotal - $total) / $subtotal) * 100, 2);
            } else {
                $unit_price   = round($total / $qty, 2);
                $discount_pct = 0;
            }

            $line = [
                'description' => $name,
                'quantity'    => $qty,
                'unitPrice'   => max(0, $unit_price),
                'unit'        => 'un',
                'vatRate'     => $vat_rate,
                'discountPct' => $discount_pct,
            ];

            if (!empty($sku)) {
                $line['code'] = substr($sku, 0, 50);
            }

            if ($vat_rate == 0) {
                $line['exemption'] = !empty($exemption) ? $exemption : $default_exemption;
            }

            $lines[] = $line;
        }

        // 2. Portes de envio
        $shipping_total = (float)$order->get_shipping_total();
        if ($shipping_total > 0) {
            $shipping_vat_rate  = (float)BueDoc_Settings::get_option('shipping_vat_rate', 14);
            $shipping_exemption = BueDoc_Settings::get_option('shipping_exemption', 'M10');

            $shipping_name = __('Portes de Envio', 'buedoc-facturacao-electronica-agt');
            $method_title  = $order->get_shipping_method();
            if (!empty($method_title)) {
                $shipping_name .= ' (' . $method_title . ')';
            }

            $shipping_line = [
                'description' => $shipping_name,
                'quantity'    => 1,
                'unitPrice'   => round($shipping_total, 2),
                'unit'        => 'un',
                'vatRate'     => $shipping_vat_rate,
                'discountPct' => 0,
                'code'        => 'SHIPPING',
            ];

            if ($shipping_vat_rate == 0) {
                $shipping_line['exemption'] = $shipping_exemption;
            }

            $lines[] = $shipping_line;
        }

        // 3. Taxas / Encargos adicionais (Fees)
        foreach ($order->get_items('fee') as $fee_id => $fee) {
            $fee_total = (float)$fee->get_total();
            if ($fee_total <= 0) {
                continue;
            }

            $fee_line = [
                'description' => $fee->get_name(),
                'quantity'    => 1,
                'unitPrice'   => round($fee_total, 2),
                'unit'        => 'un',
                'vatRate'     => $default_vat_rate,
                'discountPct' => 0,
                'code'        => 'FEE',
            ];

            if ($default_vat_rate == 0) {
                $fee_line['exemption'] = $default_exemption;
            }

            $lines[] = $fee_line;
        }

        return $lines;
    }

    /**
     * Emite uma Nota de Crédito (NC) para uma encomenda reembolsada.
     *
     * @param WC_Order $order
     * @return array
     */
    public function issue_credit_note_for_order($order) {
        if (!$order instanceof WC_Order) {
            return ['success' => false, 'error' => __('Encomenda inválida.', 'buedoc-facturacao-electronica-agt')];
        }

        $orig_number = $order->get_meta('_buedoc_document_number');
        if (empty($orig_number)) {
            return ['success' => false, 'error' => __('Esta encomenda não possui documento de origem emitido no BueDoc.', 'buedoc-facturacao-electronica-agt')];
        }

        $existing_nc = $order->get_meta('_buedoc_nc_number');
        if (!empty($existing_nc)) {
            /* translators: %s: Credit note number */
            return ['success' => false, 'error' => sprintf(__('Já foi emitida uma Nota de Crédito (%s) para esta encomenda.', 'buedoc-facturacao-electronica-agt'), $existing_nc)];
        }

        $refund_total = abs((float)$order->get_total_refunded());
        if ($refund_total <= 0) {
            return ['success' => false, 'error' => __('Nenhum reembolso registado para esta encomenda no WooCommerce.', 'buedoc-facturacao-electronica-agt')];
        }

        $client_nif = $order->get_meta('_billing_nif');
        if (empty($client_nif)) {
            $client_nif = '999999999';
        }

        $default_vat_rate  = (float)BueDoc_Settings::get_option('default_vat_rate', 14);
        $default_exemption = BueDoc_Settings::get_option('default_exemption', 'M10');

        $lines = [
            [
                /* translators: %s: Order number */
                'description' => sprintf(__('Reembolso total/parcial ref. à Encomenda #%s', 'buedoc-facturacao-electronica-agt'), $order->get_order_number()),
                'quantity'    => 1,
                'unitPrice'   => round($refund_total, 2),
                'unit'        => 'un',
                'vatRate'     => $default_vat_rate,
                'discountPct' => 0,
            ],
        ];

        if ($default_vat_rate == 0) {
            $lines[0]['exemption'] = $default_exemption;
        }

        $payload = [
            'type'         => 'NC',
            'originNumber' => $orig_number,
            'clientNif'    => $client_nif,
            'clientName'   => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'clientEmail'  => $order->get_billing_email(),
            /* translators: %s: Original document number */
            'notes'        => sprintf(__('Nota de Crédito referente à anulação/reembolso da factura %s.', 'buedoc-facturacao-electronica-agt'), $orig_number),
            'lines'        => $lines,
        ];

        $client = new BueDoc_API_Client();
        $response = $client->create_document($payload);

        if (!$response['success']) {
            $err = !empty($response['error']) ? $response['error'] : __('Erro ao emitir Nota de Crédito no BueDoc.', 'buedoc-facturacao-electronica-agt');
            $order->add_order_note('BueDoc: ' . $err);
            return ['success' => false, 'error' => $err];
        }

        $nc_data = $response['data'];
        $order->update_meta_data('_buedoc_nc_id', $nc_data['id']);
        $order->update_meta_data('_buedoc_nc_number', $nc_data['number']);
        $order->update_meta_data('_buedoc_nc_issued_at', current_time('mysql'));
        $order->save();

        /* translators: %s: Credit note number */
        $order->add_order_note(sprintf(__('Nota de Crédito BueDoc emitida: %s referente ao reembolso.', 'buedoc-facturacao-electronica-agt'), $nc_data['number']));

        return ['success' => true, 'document' => $nc_data];
    }
}
