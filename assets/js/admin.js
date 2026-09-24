/**
 * BueDoc Facturação para WooCommerce - Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // 1. Botão "Testar Ligação" nas definições
        $('#buedoc-test-connection-btn').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $resultBox = $('#buedoc-test-result');
            
            var apiKey = $('#woocommerce_buedoc_api_key').val() || '';
            var environment = $('#woocommerce_buedoc_environment').val() || 'production';
            var customUrl = $('#woocommerce_buedoc_api_url').val() || '';

            if (!apiKey) {
                $resultBox
                    .removeClass('is-success')
                    .addClass('is-error')
                    .html('<strong>Aviso:</strong> Por favor introduza uma Chave de API antes de testar a ligação.')
                    .show();
                return;
            }

            $btn.prop('disabled', true).addClass('is-loading');
            $resultBox.hide().removeClass('is-success is-error');

            $.ajax({
                url: buedocAdminData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'buedoc_test_connection',
                    nonce: buedocAdminData.nonce,
                    api_key: apiKey,
                    environment: environment,
                    custom_url: customUrl
                },
                success: function(res) {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    $resultBox.show();

                    if (res.success && res.data) {
                        var quota = res.data.quota || {};
                        var series = res.data.series || {};
                        
                        var html = '<strong>✓ Ligação bem-sucedida à API BueDoc!</strong><br>';
                        if (quota.planCode) {
                            html += 'Plano Activo: <strong>' + quota.planCode + '</strong> | ';
                        }
                        if (quota.maxDocumentsPerMonth !== null && quota.maxDocumentsPerMonth !== undefined) {
                            html += 'Quota Mensal: <strong>' + (quota.remainingThisMonth !== null ? quota.remainingThisMonth : 0) + ' / ' + quota.maxDocumentsPerMonth + ' restantes</strong>';
                        } else {
                            html += 'Quota Mensal: <strong>Ilimitada</strong>';
                        }
                        if (series.series) {
                            html += '<br>Série API Reservada: <strong>' + series.series + '</strong> (Próx. Sequencial: #' + series.nextSeq + ')';
                        }

                        $resultBox.addClass('is-success').html(html);
                    } else {
                        var errMsg = res.data && res.data.message ? res.data.message : 'Falha na validação com a API.';
                        $resultBox.addClass('is-error').html('<strong>✕ Erro na ligação:</strong> ' + errMsg);
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    $resultBox.show().addClass('is-error').html('<strong>✕ Erro de rede ou comunicação:</strong> ' + error);
                }
            });
        });

        // 2. Controlar visibilidade de URL personalizada nas definições
        function toggleCustomUrlField() {
            var env = $('#woocommerce_buedoc_environment').val();
            var $customRow = $('#woocommerce_buedoc_api_url').closest('tr');
            if (env === 'custom') {
                $customRow.show();
            } else {
                $customRow.hide();
            }
        }
        $('#woocommerce_buedoc_environment').on('change', toggleCustomUrlField);
        toggleCustomUrlField();

        // 3. Acção no Meta Box: Actualizar Estado AGT
        $(document).on('click', '.buedoc-refresh-agt-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var orderId = $btn.data('order-id');

            $btn.prop('disabled', true).addClass('is-loading');

            $.ajax({
                url: buedocAdminData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'buedoc_refresh_agt_status',
                    nonce: buedocAdminData.nonce,
                    order_id: orderId
                },
                success: function(res) {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    if (res.success) {
                        window.location.reload();
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Erro ao actualizar estado na AGT.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    alert('Erro de comunicação ao actualizar estado na AGT.');
                }
            });
        });

        // 4. Acção no Meta Box: Emitir Factura Manualmente
        $(document).on('click', '.buedoc-issue-manual-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var docType = $('#buedoc-manual-doc-type').val() || 'FR';

            if (!confirm('Tem a certeza que deseja emitir esta ' + (docType === 'FR' ? 'Factura-Recibo' : 'Factura') + ' no BueDoc e submeter à AGT?')) {
                return;
            }

            $btn.prop('disabled', true).addClass('is-loading');

            $.ajax({
                url: buedocAdminData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'buedoc_issue_order_document',
                    nonce: buedocAdminData.nonce,
                    order_id: orderId,
                    doc_type: docType
                },
                success: function(res) {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    if (res.success) {
                        window.location.reload();
                    } else {
                        alert('Erro ao emitir documento: ' + (res.data && res.data.message ? res.data.message : 'Falha desconhecida.'));
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    alert('Erro de rede ao comunicar com o servidor: ' + error);
                }
            });
        });

        // 5. Acção no Meta Box: Emitir Nota de Crédito (NC)
        $(document).on('click', '.buedoc-issue-nc-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var orderId = $btn.data('order-id');

            if (!confirm('Deseja emitir uma Nota de Crédito (NC) referente ao reembolso desta encomenda no BueDoc?')) {
                return;
            }

            $btn.prop('disabled', true).addClass('is-loading');

            $.ajax({
                url: buedocAdminData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'buedoc_issue_credit_note',
                    nonce: buedocAdminData.nonce,
                    order_id: orderId
                },
                success: function(res) {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    if (res.success) {
                        window.location.reload();
                    } else {
                        alert('Erro ao emitir Nota de Crédito: ' + (res.data && res.data.message ? res.data.message : 'Falha desconhecida.'));
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    alert('Erro de comunicação: ' + error);
                }
            });
        });

    });

})(jQuery);
