/**
 * BueDoc Facturação para WooCommerce - Checkout JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        var $nifField = $('#billing_nif');
        if (!$nifField.length) {
            return;
        }

        var $wrapper = $nifField.closest('#billing_nif_field');
        var $feedback = $('<span class="buedoc-nif-feedback" style="display:none;"></span>');
        $wrapper.append($feedback);

        var debounceTimer = null;

        function validateNifLive(nif) {
            nif = $.trim(nif);

            if (!nif) {
                $feedback.hide().empty();
                return;
            }

            // Consumidor Final convencional
            if (nif === '999999999') {
                $feedback
                    .removeClass('is-invalid is-checking')
                    .addClass('is-valid')
                    .html('✓ Consumidor Final')
                    .show();
                return;
            }

            // Validação mínima de comprimento (NIFs em Angola têm entre 9 e 14 caracteres)
            if (nif.length < 9) {
                $feedback
                    .removeClass('is-valid is-checking')
                    .addClass('is-invalid')
                    .html('✕ O NIF deve ter pelo menos 9 caracteres.')
                    .show();
                return;
            }

            // Se validação remota estiver activa
            if (typeof buedocCheckoutData !== 'undefined' && buedocCheckoutData.validateLive) {
                $feedback
                    .removeClass('is-valid is-invalid')
                    .addClass('is-checking')
                    .html('<span class="buedoc-mini-spinner"></span> A validar NIF na AGT...')
                    .show();

                $.ajax({
                    url: buedocCheckoutData.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'buedoc_validate_nif',
                        nonce: buedocCheckoutData.nonce,
                        nif: nif
                    },
                    success: function(res) {
                        if (res.success && res.data && res.data.isValid) {
                            var kindText = res.data.kind === 'COMPANY' ? 'Empresa' : 'Particular';
                            $feedback
                                .removeClass('is-invalid is-checking')
                                .addClass('is-valid')
                                .html('✓ Contribuinte activo na AGT (' + kindText + ')')
                                .show();
                        } else {
                            var msg = res.data && res.data.message ? res.data.message : 'NIF inválido ou inactivo perante a AGT.';
                            $feedback
                                .removeClass('is-valid is-checking')
                                .addClass('is-invalid')
                                .html('✕ ' + msg)
                                .show();
                        }
                    },
                    error: function() {
                        $feedback.hide().empty();
                    }
                });
            }
        }

        $nifField.on('input change', function() {
            clearTimeout(debounceTimer);
            var val = $(this).val();
            debounceTimer = setTimeout(function() {
                validateNifLive(val);
            }, 600);
        });

        // Validar caso já venha pré-preenchido
        if ($nifField.val()) {
            validateNifLive($nifField.val());
        }
    });

})(jQuery);
