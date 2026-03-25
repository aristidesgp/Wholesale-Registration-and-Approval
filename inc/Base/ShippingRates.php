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

}