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



        add_filter('woocommerce_states', [$this, 'add_cuba_provinces']);

        add_filter('woocommerce_checkout_fields', [$this, 'customize_checkout_fields']);

        add_filter('woocommerce_package_rates', [$this, 'apply_shipping_rate'], 10, 2);

        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'custom_shipping_label'], 10, 2); // Add this line

        //add_filter('gettext', [$this, 'cambiar_label_envio_cart_totals'], 10, 3); // Add this line

        add_filter('woocommerce_locate_template', [$this, 'locate_template'], 10, 3);



        // Cuba: product weight display & minimum weight validation

        add_action('woocommerce_after_shop_loop_item_title', [$this, 'display_product_weight_shop'], 5);

        add_action('woocommerce_product_meta_start', [$this, 'display_product_weight_single'], 5);

        add_action('woocommerce_before_cart', [$this, 'display_cart_weight_summary']);
        add_action('woocommerce_review_order_before_payment', [$this, 'display_cart_weight_summary']);

        add_filter('woocommerce_cart_item_name', [$this, 'add_weight_to_cart_item'], 10, 3);

        add_action('woocommerce_checkout_process', [$this, 'validate_cart_minimum_weight']);

        // Refresh the shipping summary via WooCommerce AJAX fragments when address changes
        add_filter('woocommerce_update_order_review_fragments', [$this, 'add_summary_fragment']);

    }



    public function custom_shipping_label($label, $method) {

        if (WC()->customer->get_shipping_country() === 'CU') {

            $label = '' . wc_price($method->cost);

        }

        return $label;

    }



    function cambiar_label_envio_cart_totals($translated_text, $text, $domain) {

        if ($text === 'Shipping' && $domain === 'woocommerce') {

            return 'Envío y Manejo'; // Cambia esto por el texto que desees

        }

        return $translated_text;

    }

    

    public function locate_template($template, $template_name, $template_path) {

        $basename = basename($template);       



        

        switch ($basename) {

            case 'cart-shipping.php':

                $template = CSHR_PLUGIN_PATH . 'templates/cart/cart-shipping.php';

                break;                

        }       



        return $template;

    }

    



    public function add_cuba_provinces($states) {

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

        $states['CU'] = array_filter($all_provinces, function($key) use ($active_provinces) {

            return in_array($key, $active_provinces);

        }, ARRAY_FILTER_USE_KEY);        

        return $states;

    }



    public function customize_checkout_fields($fields) {

        $fields['shipping']['shipping_city'] = [

            'type' => 'select',

            'label' => __('Municipio', 'woocommerce'),

            'required' => true,

            'class' => ['form-row-wide'],

            'options' => ['' => __('Seleccione un municipio', 'woocommerce')]

        ];



        $fields['shipping']['shipping_phone']['required'] = true;



        return $fields;

    }



    public function apply_shipping_rate($rates, $package) {

        global $wpdb;

        $chosen_country = WC()->customer->get_shipping_country();

        $chosen_province = WC()->customer->get_shipping_state();

        $chosen_municipality = WC()->customer->get_shipping_city();



        // Verificar si el país es Cuba

        if ($chosen_country !== 'CU') {

            return $rates;

        }



        // Debugging message

        error_log('Chosen province (shipping): ' . $chosen_province);

        error_log('Chosen municipality (shipping): ' . $chosen_municipality);



        if ($chosen_province && $chosen_municipality) {

            $rate = floatval($wpdb->get_var($wpdb->prepare("SELECT rate FROM {$this->table_name} WHERE province = %s AND municipality = %s AND active = 1", $chosen_province, $chosen_municipality)));



            // Debugging message

            error_log('Rate for province ' . $chosen_province . ' and municipality ' . $chosen_municipality . ': ' . $rate);



            if ($rate !== null) {

                foreach ($rates as $rate_key => $rate_data) {

                    $total_rate = 0;



                    // Obtener los productos en el paquete

                    foreach ($package['contents'] as $item_id => $values) {

                        $product_id = $values['product_id'];

                        $product_rate = $this->calculate_shipping_rate($product_id);

                        $total_rate += $product_rate;

                    }

                    $rate+=$total_rate;



                    // Aplicar el porcentaje adicional

                    $percentage = get_option('cuba_shipping_percentage', 0);

                    $rate += ($rate * $percentage / 100);

                    // Aplicar el rate al costo del método de envío

                    $rates[$rate_key]->cost = $rate;



                    // Debugging message

                    error_log('Applying rate: ' . $total_rate . ' to rate key: ' . $rate_key);

                }

            } else {

                // Debugging message

                error_log('No rate found for province: ' . $chosen_province . ' and municipality: ' . $chosen_municipality);

            }

        } else {

            // Debugging message

            error_log('No province or municipality chosen.');

        }

        return $rates;

    }



    public function calculate_shipping_rate($product_id) {

        global $wpdb;

        

        $rate = 0;



        // Obtener las categorías del producto

        $categories = wp_get_post_terms($product_id, 'product_cat');



        //obtener la cantidad de productos

        $quantity = WC()->cart->get_cart_item_quantities()[$product_id];



        // Inicializar variables para price_by_weight y flat_price

        $price_by_weight = 0;

        $flat_price = 0;



        // Recorrer las categorías del producto

        foreach ($categories as $category) {

            $category_price_by_weight = floatval(get_term_meta($category->term_id, 'price_by_weight', true));

            $category_flat_price = floatval(get_term_meta($category->term_id, 'flat_price', true));



            if ($category_price_by_weight != 0) {

                $price_by_weight = $category_price_by_weight;

                break;

            } elseif ($category_flat_price != 0) {

                $flat_price = $category_flat_price;

                break;

            }

        }



        // Obtener el peso del producto       

        $product_weight = floatval(get_post_meta($product_id, '_weight', true));

        error_log('Product "' . get_the_title($product_id) . '" weight: ' . $product_weight);

        // Calcular el rate según las reglas

        if ($price_by_weight != 0) {

            $rate += $product_weight * $price_by_weight*$quantity;

            error_log('Price by weight: ' . $price_by_weight . ' for product weight: ' . $product_weight);

        } elseif ($flat_price != 0) {

            $rate += $flat_price*$quantity;

            error_log('Flat price: ' . $flat_price);

        } else {

            $cuba_shipping_rate = floatval(get_post_meta($product_id, 'cuba_shipping_rate', true));

            $cuba_shipping_by_weight = get_post_meta($product_id, 'cuba_shipping_by_weight', true);



            if ($cuba_shipping_by_weight == 'yes') {

                $rate += $product_weight * $cuba_shipping_rate*$quantity;

                error_log('Cuba shipping by weight: ' . $cuba_shipping_rate . ' for product weight: ' . $product_weight);

            } else {

                $rate += $cuba_shipping_rate*$quantity;

                error_log('Cuba shipping rate: ' . $cuba_shipping_rate);

            }

        }



        return $rate;

    }



    /**
     * Get per-product shipping details (rate type, weight, unit cost)
     */
    private function get_product_shipping_info($product_id) {
        $categories = wp_get_post_terms($product_id, 'product_cat');
        $weight     = floatval(get_post_meta($product_id, '_weight', true));

        foreach ($categories as $category) {
            $pbw = floatval(get_term_meta($category->term_id, 'price_by_weight', true));
            $fp  = floatval(get_term_meta($category->term_id, 'flat_price', true));
            if ($pbw > 0) {
                return ['rate' => $pbw, 'by_weight' => true, 'weight' => $weight, 'per_unit_cost' => $weight * $pbw];
            } elseif ($fp > 0) {
                return ['rate' => $fp, 'by_weight' => false, 'weight' => $weight, 'per_unit_cost' => $fp];
            }
        }

        $rate = floatval(get_post_meta($product_id, 'cuba_shipping_rate', true));
        $by_w = get_post_meta($product_id, 'cuba_shipping_by_weight', true) === 'yes';
        return [
            'rate'          => $rate,
            'by_weight'     => $by_w,
            'weight'        => $weight,
            'per_unit_cost' => $by_w ? $weight * $rate : $rate,
        ];
    }

    /**
     * Build full shipping cost breakdown for the current cart.
     * Cuba-badge products are excluded from weight restrictions and weight-based shipping cost.
     */
    private function get_cart_shipping_breakdown() {
        global $wpdb;

        $global_min = floatval(get_option('cuba_min_weight', 0));
        $percentage = floatval(get_option('cuba_shipping_percentage', 0));

        $breakdown = [
            'items'         => [],
            'total_weight'  => 0.0,
            'envio_total'   => 0.0,
            'base_rate'     => 0.0,
            'percentage'    => $percentage,
            'surcharge'     => 0.0,
            'entrega_total' => 0.0,
            'grand_total'   => 0.0,
            'min_weight'          => $global_min,
            'below_min'           => false,
            'has_shippable_items' => false,
        ];

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $product    = $cart_item['data'];
            $quantity   = $cart_item['quantity'];

            // Products in Cuba (badge category) are already local — exclude from shipping weight/cost
            $is_cuba = function_exists('devfl_product_has_cuba_badge_category')
                       && devfl_product_has_cuba_badge_category($product_id);

            $info            = $this->get_product_shipping_info($product_id);
            $item_total_ship = $is_cuba ? 0.0 : ($info['per_unit_cost'] * $quantity);

            $breakdown['items'][] = [
                'name'         => $product->get_name(),
                'weight'       => $info['weight'],
                'quantity'     => $quantity,
                'total_weight' => $info['weight'] * $quantity,
                'rate'         => $info['rate'],
                'by_weight'    => $info['by_weight'],
                'ship_cost'    => $item_total_ship,
                'is_cuba'      => $is_cuba,
            ];

            if (!$is_cuba) {
                $breakdown['has_shippable_items'] = true;
                $breakdown['total_weight'] += $info['weight'] * $quantity;
                $breakdown['envio_total']  += $item_total_ship;
            }
        }

        // Province/municipality delivery base rate
        $province     = WC()->customer->get_shipping_state();
        $municipality = WC()->customer->get_shipping_city();
        if ($province && $municipality) {
            $breakdown['base_rate'] = floatval($wpdb->get_var($wpdb->prepare(
                "SELECT rate FROM {$this->table_name} WHERE province = %s AND municipality = %s AND active = 1",
                $province, $municipality
            )));
        }

        $subtotal                   = $breakdown['envio_total'] + $breakdown['base_rate'];
        $breakdown['surcharge']     = $subtotal * $percentage / 100;
        $breakdown['entrega_total'] = $breakdown['base_rate'] + $breakdown['surcharge'];
        $breakdown['grand_total']   = $breakdown['envio_total'] + $breakdown['entrega_total'];

        if ($global_min > 0 && $breakdown['has_shippable_items'] && $breakdown['total_weight'] < $global_min) {
            $breakdown['below_min'] = true;
        }

        return $breakdown;
    }

    /**
     * Show product weight badge on shop loop cards
     */
    public function display_product_weight_shop() {
        global $product;
        if (!$product) return;
        $weight = floatval($product->get_weight());
        if ($weight > 0) {
            echo '<span class="cshr-product-weight-badge">' . number_format($weight, 2) . ' lb</span>';
        }
    }

    /**
     * Show weight + per-lb rate on single product page
     */
    public function display_product_weight_single() {
        global $product;
        if (!$product) return;
        $weight = floatval($product->get_weight());
        if ($weight <= 0) return;

        $rate_per_lb = 0;
        foreach ($product->get_category_ids() as $cat_id) {
            $r = floatval(get_term_meta($cat_id, 'price_by_weight', true));
            if ($r > 0) { $rate_per_lb = $r; break; }
        }

        echo '<div class="cshr-product-weight-info">';
        echo '<span class="cshr-weight-label"><strong>Peso:</strong> ' . number_format($weight, 2) . ' lb</span>';
        if ($rate_per_lb > 0) {
            echo '<span class="cshr-rate-label">' . wc_price($rate_per_lb) . ' por libra (env&iacute;o Cuba)</span>';
        }
        echo '</div>';
    }

    /**
     * Show shipping cost breakdown above the WooCommerce cart (Cuba only)
     */
    public function display_cart_weight_summary() {
        $shipping_country = WC()->customer->get_shipping_country();
        if (!empty($shipping_country) && $shipping_country !== 'CU') {
            // Render empty wrapper on checkout so the AJAX fragment replacement has a target
            if (is_checkout()) {
                echo '<div class="cshr-cart-weight-summary"></div>';
            }
            return;
        }

        $d = $this->get_cart_shipping_breakdown();
        if (empty($d['items'])) {
            if (is_checkout()) {
                echo '<div class="cshr-cart-weight-summary"></div>';
            }
            return;
        }

        $below_min = $d['below_min'];
        $min       = $d['min_weight'];

        echo '<div class="cshr-cart-weight-summary">';

        // ── Header: total shippable weight ──────────────────────────────────
        echo '<div class="cshr-summary-header">';
        echo '<span class="cshr-total-weight-label">Peso total a enviar: <strong>' . number_format($d['total_weight'], 2) . ' lb</strong></span>';
        if ($min > 0 && $below_min) {
            echo '<div><span class="cshr-min-badge">Env&iacute;o m&iacute;nimo: ' . number_format($min, 2) . ' lb</span></div>';
        }
        echo '</div>';

        // ── Minimum weight warning ───────────────────────────────────────────
        if ($below_min) {
            $missing = $min - $d['total_weight'];
            echo '<div class="cshr-weight-warning">';
            echo '<span class="cshr-warn-icon">&#9888;</span> ';
            echo '<strong>Peso insuficiente para el env&iacute;o</strong> &mdash; ';
            echo 'Necesitas un m&iacute;nimo de ' . number_format($min, 2) . ' lbs en productos a enviar. A&uacute;n te faltan ' . number_format($missing, 2) . ' lbs.';
            echo '</div>';
        }

        // ── Per-product breakdown ────────────────────────────────────────────
        echo '<div class="cshr-items-breakdown">';
        foreach ($d['items'] as $item) {
            echo '<div class="cshr-item-row' . ($item['is_cuba'] ? ' cshr-item-cuba' : '') . '">';

            echo '<span class="cshr-item-name">' . esc_html($item['name']) . ' &times; ' . $item['quantity'] . '</span>';

            if ($item['weight'] > 0) {
                $w_str = number_format($item['weight'], 2) . ' lb';
                if ($item['quantity'] > 1) {
                    $w_str .= ' &times; ' . $item['quantity'] . ' = ' . number_format($item['total_weight'], 2) . ' lb';
                }
                echo '<span class="cshr-item-weight">' . $w_str . '</span>';
            }

            if ($item['is_cuba']) {
                echo '<span class="cshr-item-cuba-label">0</span>';
            } elseif ($item['ship_cost'] > 0) {
                $rate_str = $item['by_weight']
                    ? wc_price($item['rate']) . '/lb'
                    : wc_price($item['rate']) . ' fijo';
                echo '<span class="cshr-item-ship-cost">' . $rate_str . ' = ' . wc_price($item['ship_cost']) . '</span>';
            }

            echo '</div>';
        }
        echo '</div>';

        // ── Cost summary ─────────────────────────────────────────────────────
        echo '<div class="cshr-cost-summary">';

        echo '<div class="cshr-cost-row">';
        echo '<span>Env&iacute;o (por peso)</span><span>' . wc_price($d['envio_total']) . '</span>';
        echo '</div>';

        echo '<div class="cshr-cost-row">';
        echo '<span>Entrega a domicilio</span><span>' . wc_price($d['entrega_total']) . '</span>';
        echo '</div>';

        echo '<div class="cshr-cost-row cshr-cost-total">';
        echo '<span><strong>Total env&iacute;o estimado</strong></span><span><strong>' . wc_price($d['grand_total']) . '</strong></span>';
        echo '</div>';
        echo '</div>';

        // Signal for the always-loaded JS (cuba-shipping-rates.js) to disable Place Order
        if ($below_min) {
            echo '<span id="cshr-below-min" style="display:none;"></span>';
        }

        echo '</div>'; // .cshr-cart-weight-summary
    }

    /**
     * Inject the shipping summary as a WooCommerce AJAX fragment so it refreshes
     * automatically whenever the customer updates their address at checkout.
     */
    public function add_summary_fragment($fragments) {
        ob_start();
        $this->display_cart_weight_summary();
        $html = ob_get_clean();

        // Always return a wrapper div so jQuery can replace it even on the first AJAX update
        if (empty(trim($html))) {
            $html = '<div class="cshr-cart-weight-summary"></div>';
        }

        $fragments['.cshr-cart-weight-summary'] = $html;
        return $fragments;
    }

    /**
     * Append weight info to cart item names
     */
    public function add_weight_to_cart_item($product_name, $cart_item, $cart_item_key) {
        if (is_cart() || is_checkout()) {
            $weight = floatval(get_post_meta($cart_item['product_id'], '_weight', true));
            if ($weight > 0) {
                $product_name .= '<br><small class="cshr-cart-item-weight">Peso: ' . number_format($weight, 2) . ' lb</small>';
            }
        }
        return $product_name;
    }

    /**
     * Block checkout if TOTAL shippable weight is below the global minimum (Cuba only).
     * Products with the Cuba badge are excluded from the weight check.
     */
    public function validate_cart_minimum_weight() {
        if (WC()->customer->get_shipping_country() !== 'CU') return;

        $d = $this->get_cart_shipping_breakdown();
        if ($d['min_weight'] > 0 && $d['below_min']) {
            $missing = $d['min_weight'] - $d['total_weight'];
            wc_add_notice(
                sprintf(
                    'Peso insuficiente para el env&iacute;o. M&iacute;nimo requerido: <strong>%.2f lbs</strong>. Total en carrito: <strong>%.2f lbs</strong>. Faltan: <strong>%.2f lbs</strong>.',
                    $d['min_weight'],
                    $d['total_weight'],
                    $missing
                ),
                'error'
            );
        }
    }
}