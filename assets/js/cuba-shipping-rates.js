jQuery(document).ready(function ($) {
    function updateMunicipalities(province, target) {
        var municipalities = cubaShippingRates.municipalities[province] || [];
        var current = $(target).val();
        var options = '<option value="">' + 'Seleccione un municipio' + '</option>';
        $.each(municipalities, function (index, municipality) {
            options += '<option value="' + municipality + '">' + municipality + '</option>';
        });
        $(target).html(options);
        if (current && municipalities.indexOf(current) !== -1) {
            $(target).val(current);
        }
    }

    /**
     * Cuba-only checkout rows (shipping type, recipient ID, Cuban phone) are
     * always rendered by PHP and shown/hidden here, because the customer picks
     * the country after the form was built.
     */
    function toggleCubaOnlyFields(isCuba) {
        $('.cshr-cu-only').each(function () {
            var $row = $(this);
            $row.toggle(isCuba);
            $row.find('input, select').prop('required', isCuba);
            if (isCuba && $row.find('label abbr.required').length === 0) {
                $row.find('label').first().append(' <abbr class="required" title="obligatorio">*</abbr>');
            } else if (!isCuba) {
                $row.find('label abbr.required').remove();
            }
        });
    }

    function checkCountryAndUpdateMunicipalities() {
        var country = $('select#shipping_country').val() || $('select#billing_country').val();
        var province = $('select#shipping_state').val();
        var target = '#shipping_city';

        if (country === 'CU') {
            if ($(target).prop('tagName') !== 'SELECT') {
                var preset = $(target).val();
                var select = $('<select id="shipping_city" name="shipping_city" class="form-row-wide"></select>');
                $(target).replaceWith(select);
                target = select;
                if (preset) {
                    select.data('preset', preset);
                }
            }
            updateMunicipalities(province, target);
            var preset = $(target).data('preset');
            if (preset) {
                $(target).val(preset);
            }

            $('#shipping_postcode').val('10100');

            // Add the shipping message
            if ($('#cuba-shipping-message').length === 0) {
                var message = '<p id="cuba-shipping-message" style="background-color: #ffefc2; padding: 10px; border-left: 4px solid #ffa500; font-weight: bold;">' +
                    'Equipos electrodomésticos se entregan en un termino de 15 a 28 días hábiles, el tiempo de entrega de otras categorías es de 7 a 10 dias hábiles' +
                    '</p>';
                $('#shipping_country_field').prepend(message);
            }
        } else {
            if ($(target).prop('tagName') === 'SELECT') {
                var input = $('<input type="text" class="input-text" name="shipping_city" id="shipping_city" />');
                $(target).replaceWith(input);
            }

            // Remove the shipping message
            $('#cuba-shipping-message').remove();
        }

        toggleCubaOnlyFields(country === 'CU');
    }

    $(document).on('change', 'select#shipping_country, select#billing_country, select#shipping_state', function () {
        checkCountryAndUpdateMunicipalities();
    });

    // Update cart totals when municipality is selected
    $(document).on('change', '#shipping_city', function () {
        $('body').trigger('update_checkout');
    });

    // WooCommerce redraws parts of the checkout after every AJAX refresh.
    $(document.body).on('updated_checkout country_to_state_changed', function () {
        checkCountryAndUpdateMunicipalities();
    });

    // Initial check on page load
    checkCountryAndUpdateMunicipalities();
});
