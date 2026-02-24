jQuery(document).ready(function($) {
    function updateMunicipalities(province, target) {
        var municipalities = cubaShippingRates.municipalities[province] || [];
        var options = '<option value="">' + 'Seleccione un municipio' + '</option>';
        $.each(municipalities, function(index, municipality) {
            options += '<option value="' + municipality + '">' + municipality + '</option>';
        });
        $(target).html(options);
    }

    function checkCountryAndUpdateMunicipalities() {
        var country = $('select#shipping_country').val();
        var province = $('select#shipping_state').val();
        var target = '#shipping_city';

        if (country === 'CU') {            
            if ($(target).prop('tagName') !== 'SELECT') {
                var select = $('<select id="shipping_city" name="shipping_city" class="form-row-wide"></select>');
                $(target).replaceWith(select);
                target = select;
            }
            updateMunicipalities(province, target);

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
    }

    $('select#shipping_country').change(function() {
        checkCountryAndUpdateMunicipalities();
    });

    $('select#shipping_state').change(function() {
        checkCountryAndUpdateMunicipalities();
    });

    // Update cart totals when municipality is selected
    $(document).on('change', '#shipping_city', function() {
        $('body').trigger('update_checkout');
    });

    // Initial check on page load
    checkCountryAndUpdateMunicipalities();
});