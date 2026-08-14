<?php

/*
*
* @package aristidesgp
*
*/

namespace CSHR\Inc\Base;

class ShippingRates
{
    private $table_name;

    public function register()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cuba_shipping_rates';

        add_action('woocommerce_cart_totals_after_shipping', [$this, 'show_shipping_type_in_cart']);

        add_filter('woocommerce_states', [$this, 'add_cuba_provinces']);
        add_filter('woocommerce_checkout_fields', [$this, 'customize_checkout_fields']);
        add_filter('woocommerce_checkout_fields', [$this, 'add_shipping_type_field'], 20);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_shipping_type_to_session']);
        add_filter('woocommerce_package_rates', [$this, 'apply_shipping_rate'], 10, 2);
        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'custom_shipping_label'], 10, 2); // Add this line
        //add_filter('gettext', [$this, 'cambiar_label_envio_cart_totals'], 10, 3); // Add this line
        add_filter('woocommerce_locate_template', [$this, 'locate_template'], 10, 3);

        add_action('woocommerce_cart_totals_after_shipping', [$this, 'show_shipping_type_selector_in_cart'], 5);
        add_action('wp_ajax_update_shipping_type', [$this, 'ajax_update_shipping_type']);
        add_action('wp_ajax_nopriv_update_shipping_type', [$this, 'ajax_update_shipping_type']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_cart_js']);

        add_action('woocommerce_checkout_update_order_review', [$this, 'update_shipping_type_from_checkout']);
        add_filter('woocommerce_cart_shipping_packages', [$this, 'inject_shipping_type_into_packages']);

        // Forzar el label del campo shipping_city a 'Municipio' en el checkout
        add_filter('gettext', function($translated_text, $text, $domain) {
            if ($text === 'Población' && $domain === 'woocommerce') {
                return 'Municipio';
            }
            return $translated_text;
        }, 10, 3);
    }

    private function get_shipping_type_from_request_or_session(): ?string
    {
        // 1) Si estamos en la llamada AJAX de checkout, viene en post_data
        if (isset($_POST['post_data'])) {
            parse_str(wp_unslash($_POST['post_data']), $fields);
            if (!empty($fields['shipping_type']) && in_array($fields['shipping_type'], ['maritimo', 'aereo'], true)) {
                return $fields['shipping_type'];
            }
            if (!empty($fields['shipping']['shipping_type']) && in_array($fields['shipping']['shipping_type'], ['maritimo', 'aereo'], true)) {
                return $fields['shipping']['shipping_type'];
            }
        }
        // 2) Fallback a sesión
        $t = WC()->session->get('shipping_type');
        return in_array($t, ['maritimo', 'aereo'], true) ? $t : null;
    }

    public function update_shipping_type_from_checkout($post_data)
    {
        parse_str($post_data, $fields);
        $shipping_type = $this->get_shipping_type_from_request_or_session();
        if ($shipping_type) {
            WC()->session->set('shipping_type', sanitize_text_field($shipping_type));
        }
    }

    public function inject_shipping_type_into_packages($packages)
    {
        $type = $this->get_shipping_type_from_request_or_session();
        foreach ($packages as &$p) {
            $p['cshr_shipping_type'] = $type; // llévalo junto al package
        }
        return $packages;
    }

    /**
     * Muestra el selector de tipo de envío en el carrito y actualiza el shipping vía AJAX
     */
    public function show_shipping_type_selector_in_cart()
    {
        if (WC()->customer->get_shipping_country() === 'CU') {
            $shipping_type = $this->get_shipping_type();
?>
            <tr class="shipping-type-selector">
                <td colspan="2">
                    <label for="shipping_type_cart"><strong><?php echo esc_html(__('Tipo de Envío', 'woocommerce')); ?>:</strong></label>
                    <select id="shipping_type_cart" name="shipping_type_cart" style="margin-left:10px;">
                        <option value="maritimo" <?php selected($shipping_type, 'maritimo'); ?>><?php echo esc_html(__('Marítimo', 'woocommerce')); ?></option>
                        <option value="aereo" <?php selected($shipping_type, 'aereo'); ?>><?php echo esc_html(__('Aéreo', 'woocommerce')); ?></option>
                    </select>
                </td>
            </tr>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#shipping_type_cart').on('change', function() {
                        var shipping_type = $(this).val();
                        $.ajax({
                            type: 'POST',
                            url: wc_cart_params.ajax_url,
                            data: {
                                action: 'update_shipping_type',
                                shipping_type: shipping_type
                            },
                            success: function() {
                                $('body').trigger('update_checkout');
                                $('body').trigger('wc_fragment_refresh');
                            }
                        });
                    });
                });
            </script>
<?php
        }
    }

    /**
     * Maneja la actualización del tipo de envío vía AJAX
     */
    public function ajax_update_shipping_type()
    {
        if (isset($_POST['shipping_type'])) {
            $shipping_type = sanitize_text_field($_POST['shipping_type']);
            WC()->session->set('shipping_type', $shipping_type);
        }
        wp_die();
    }

    /**
     * Encola el JS necesario para el AJAX en el carrito
     */
    public function enqueue_cart_js()
    {
        if (is_cart()) {
            wp_enqueue_script('jquery');
            wp_localize_script('jquery', 'wc_cart_params', [
                'ajax_url' => admin_url('admin-ajax.php')
            ]);
        }
    }

    /**
     * Muestra el tipo de envío en el resumen del carrito
     */
    public function show_shipping_type_in_cart()
    {
        if (WC()->customer->get_shipping_country() === 'CU') {
            $shipping_type = $this->get_shipping_type();
            if ($shipping_type === 'aereo') {
                $label = __('Tipo de Envío: Aéreo', 'woocommerce');
            } else {
                $label = __('Tipo de Envío: Marítimo', 'woocommerce');
            }
            echo '<tr class="shipping-type"><td colspan="2">' . esc_html($label) . '</td></tr>';
        }
    }


    /**
     * Agrega el campo de tipo de envío al checkout
     */
    public function add_shipping_type_field($fields)
    {
        if (WC()->customer->get_shipping_country() === 'CU') {
            $shipping_type = WC()->session->get('shipping_type'); // Obtener el valor actual de la sesión
            $fields['shipping']['shipping_type'] = [
                'type'     => 'select',
                'label'    => __('Tipo de Envío', 'woocommerce'),
                'required' => true,
                'class'    => ['form-row-wide', 'update_totals_on_change'],
                'options'  => [
                    '' => __('Seleccione tipo de envío', 'woocommerce'),
                    'maritimo' => __('Marítimo', 'woocommerce'),
                    'aereo'    => __('Aéreo', 'woocommerce'),
                ],
                'priority' => 25,
                'default'  => $shipping_type ? $shipping_type : '', // Aquí se carga el valor seleccionado
            ];
        }
        return $fields;
    }

    /**
     * Guarda el tipo de envío en la sesión y en los metadatos del pedido
     */
    public function save_shipping_type_to_session($order_id)
    {
        if (isset($_POST['shipping_type'])) {
            $shipping_type = sanitize_text_field($_POST['shipping_type']);
            WC()->session->set('shipping_type', $shipping_type);
            update_post_meta($order_id, '_shipping_type', $shipping_type);
        }
    }

    /**
     * Recupera el tipo de envío desde la sesión para la lógica de cálculo
     */
    public function get_shipping_type()
    {
        $shipping_type = WC()->session->get('shipping_type');
        // No asignar valor por defecto aquí
        return $shipping_type ? $shipping_type : null;
    }

    public function custom_shipping_label($label, $method)
    {
        if (WC()->customer->get_shipping_country() === 'CU') {
            $label = '' . wc_price($method->cost);
        }
        return $label;
    }

    function cambiar_label_envio_cart_totals($translated_text, $text, $domain)
    {
        if ($text === 'Shipping' && $domain === 'woocommerce') {
            return 'Envío y Manejo'; // Cambia esto por el texto que desees
        }
        return $translated_text;
    }

    public function locate_template($template, $template_name, $template_path)
    {
        $basename = basename($template);


        switch ($basename) {
            case 'cart-shipping.php':
                $template = CSHR_PLUGIN_PATH . 'templates/cart/cart-shipping.php';
                break;
        }

        return $template;
    }


    public function add_cuba_provinces($states)
    {
        global $wpdb;

        // Obtener todas las provincias con al menos un municipio activo
        $results = $wpdb->get_results("
            SELECT DISTINCT province 
            FROM {$this->table_name} 
            WHERE active = 1
        ");

        // Crear un array de provincias activas
        $active_provinces = [];
        foreach ($results as $row) {
            $active_provinces[] = $row->province;
        }

        // Definir todas las provincias y sus nombres
        $all_provinces = [
            'PRI' => 'Pinar del Río',
            'ART' => 'Artemisa',
            'HAB' => 'La Habana',
            'MAY' => 'Mayabeque',
            'MTZ' => 'Matanzas',
            'CFG' => 'Cienfuegos',
            'VCL' => 'Villa Clara',
            'SSP' => 'Sancti Spíritus',
            'CAV' => 'Ciego de Ávila',
            'CMG' => 'Camagüey',
            'LTU' => 'Las Tunas',
            'HOL' => 'Holguín',
            'GRM' => 'Granma',
            'SCU' => 'Santiago de Cuba',
            'GTM' => 'Guantánamo',
            'IJV' => 'Isla de la Juventud'
        ];

        // Filtrar las provincias para incluir solo las activas
        $states['CU'] = array_filter($all_provinces, function ($key) use ($active_provinces) {
            return in_array($key, $active_provinces);
        }, ARRAY_FILTER_USE_KEY);
        return $states;
    }

    public function customize_checkout_fields($fields)
    {
        $fields['shipping']['shipping_city'] = [
            'type' => 'select',
            'label' => 'Municipio',
            'required' => true,
            'class' => ['form-row-wide'],
            'options' => ['' => 'Seleccione un municipio'],
            'priority' => 81, // Justo debajo de la provincia
        ];        

        return $fields;
    }

    public function apply_shipping_rate($rates, $package)
    {
        if (WC()->customer->get_shipping_country() !== 'CU') {
            return $rates;
        }

        // Método elegido (primer paquete)
        $chosen = WC()->session->get('chosen_shipping_methods');
        $chosen_id = is_array($chosen) && !empty($chosen[0]) ? $chosen[0] : null;

        global $wpdb;
        $prov = WC()->customer->get_shipping_state();
        $mun  = WC()->customer->get_shipping_city();

        $shipping_type = $package['cshr_shipping_type'] ?? $this->get_shipping_type_from_request_or_session();

        if (!$shipping_type) {
            foreach ($rates as $key => $rate_obj) {
                $rates[$key]->cost = 0;
            }            
            return $rates;
        }

        $rate_maritimo = $wpdb->get_var($wpdb->prepare(
            "SELECT rate_maritimo FROM {$this->table_name} WHERE province=%s AND municipality=%s AND active=1",
            $prov,
            $mun
        ));
        $rate_aereo = $wpdb->get_var($wpdb->prepare(
            "SELECT rate_aereo FROM {$this->table_name} WHERE province=%s AND municipality=%s AND active=1",
            $prov,
            $mun
        ));

        $rate_maritimo_general = (float) get_option('rate_maritimo_general', 0);
        $rate_aereo_general    = (float) get_option('rate_aereo_general', 0);
        $percentage            = (float) get_option('cuba_shipping_percentage', 0);

        foreach ($rates as $key => $rate_obj) {
            // Solo tocar el elegido
            //add logs
            error_log("Applying shipping rate for method: $key");
            error_log("Chosen ID: $chosen_id");
            if ($chosen_id && $key !== $chosen_id) {
                continue;
            }
            $total_rate = 0.0;

            foreach ($package['contents'] as $item) {
                $product_id = $item['data']->get_id(); // respeta variaciones
                $total_rate += $this->calculate_shipping_rate(
                    $product_id,
                    $shipping_type,
                    $rate_maritimo,
                    $rate_aereo,
                    $rate_maritimo_general,
                    $rate_aereo_general,
                    $item['quantity']
                );
            }

            // Porcentaje adicional
            if ($percentage > 0) {
                $total_rate += ($total_rate * $percentage / 100);
            }

            // Establece costo final
            $rates[$key]->cost = max(0, wc_format_decimal($total_rate, wc_get_price_decimals()));
            // Si tu método es taxable, Woo calculará impuestos sobre ->cost
        }

        return $rates;
    }


    public function calculate_shipping_rate(
        $product_id,
        $shipping_type = 'maritimo',
        $rate_maritimo = null,
        $rate_aereo = null,
        $rate_maritimo_general = 0,
        $rate_aereo_general = 0,
        $quantity = 1
    ) {
        $rate = 0.0;
        //add logs
        error_log("Calculating shipping rate for product ID: $product_id");
        error_log("Shipping type: $shipping_type");
        error_log("Rates - Maritimo: $rate_maritimo, Aereo: $rate_aereo");
        error_log("Rates - Maritimo General: $rate_maritimo_general, Aereo General: $rate_aereo_general");

        // Categorías
        $categories = wp_get_post_terms($product_id, 'product_cat');
        $price_by_weight = 0.0;
        $flat_price = 0.0;

        foreach ($categories as $category) {
            $cat_flat  = (float) get_term_meta($category->term_id, 'flat_price', true);
            $cat_pbw   = (float) get_term_meta($category->term_id, 'price_by_weight', true);
            if ($cat_flat > 0) {
                $flat_price = $cat_flat;
                break;
            }
            if ($cat_pbw > 0) {
                $price_by_weight = $cat_pbw;
            }
        }
        //add logs
        error_log("Flat price: $flat_price, Price by weight: $price_by_weight");

        $product = wc_get_product($product_id);
        $product_weight = (float) $product->get_weight();
        if ($product->is_type('variation') && !$product_weight) {
            $parent = wc_get_product($product->get_parent_id());
            $product_weight = (float) $parent->get_weight();
        }
        $product_weight = wc_get_weight($product_weight, 'lbs');
        //add logs
        error_log("Product weight (lbs): $product_weight");
        if ($flat_price > 0) {
            // Calcular tarifa plana
            error_log("Flat price: $flat_price");
            $rate = $flat_price * $quantity;
        } else {
            // Escoger rate por tipo
            error_log("Shipping type: $shipping_type");
            if ($shipping_type === 'aereo') {
                $rate_to_use = ($rate_aereo !== null && (float)$rate_aereo > 0) ? (float)$rate_aereo : (float)$rate_aereo_general;
            } else {
                error_log("Shipping type: $shipping_type");
                $rate_to_use = ($rate_maritimo !== null && (float)$rate_maritimo > 0) ? (float)$rate_maritimo : (float)$rate_maritimo_general;
            }
            //add logs
            error_log("Rate to use: $rate_to_use");
            // Si la categoría define price_by_weight, multiplicamos por ese factor también
            $per_kg = $rate_to_use;


            $rate = $product_weight * $per_kg * $quantity;
            //add logs
            error_log("Calculated rate: $rate");
        }

        return $rate;
    }
}
