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

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cuba_shipping_rates';
    }

    public function register()
    {
        add_action('woocommerce_cart_totals_after_shipping', [$this, 'show_shipping_type_in_cart']);

        add_filter('woocommerce_states', [$this, 'add_cuba_provinces']);
        add_filter('woocommerce_checkout_fields', [$this, 'customize_checkout_fields']);
        add_filter('woocommerce_checkout_fields', [$this, 'add_shipping_type_field'], 20);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_shipping_type_to_session']);
        add_filter('woocommerce_package_rates', [$this, 'apply_shipping_rate'], 10, 2);
        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'custom_shipping_label'], 10, 2);
        add_filter('woocommerce_locate_template', [$this, 'locate_template'], 10, 3);

        add_action('woocommerce_cart_totals_after_shipping', [$this, 'show_shipping_type_selector_in_cart'], 5);
        add_action('woocommerce_cart_totals_after_shipping', [$this, 'show_billable_weight_in_cart'], 6);
        add_action('wp_ajax_update_shipping_type', [$this, 'ajax_update_shipping_type']);
        add_action('wp_ajax_nopriv_update_shipping_type', [$this, 'ajax_update_shipping_type']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_cart_js']);

        add_action('woocommerce_checkout_update_order_review', [$this, 'update_shipping_type_from_checkout']);
        add_filter('woocommerce_cart_shipping_packages', [$this, 'inject_shipping_type_into_packages']);

        // Minimum billable pounds — "block" mode refuses checkout below the minimum
        add_action('woocommerce_checkout_process', [$this, 'validate_minimum_weight']);
        add_action('woocommerce_before_cart', [$this, 'show_minimum_weight_notice']);

        // Forzar el label del campo shipping_city a 'Municipio' en el checkout
        add_filter('gettext', function ($translated_text, $text, $domain) {
            if ($text === 'Población' && $domain === 'woocommerce') {
                return 'Municipio';
            }
            return $translated_text;
        }, 10, 3);
    }

    /* ------------------------------------------------------------------ */
    /*  Minimum billable pounds (options default to 0 = feature disabled,  */
    /*  preserving pre-1.1.0 behavior on every site that does not opt in)  */
    /* ------------------------------------------------------------------ */

    public static function get_min_lbs(string $shipping_type): float
    {
        $key = $shipping_type === 'aereo' ? 'cshr_min_lbs_aereo' : 'cshr_min_lbs_maritimo';
        return max(0.0, (float) get_option($key, 0));
    }

    /**
     * 'bill'  => charge at least the minimum weight (billable weight clamp)
     * 'block' => refuse checkout while cart weight is below the minimum
     */
    public static function get_min_mode(): string
    {
        $mode = get_option('cshr_min_lbs_mode', 'bill');
        return in_array($mode, ['bill', 'block'], true) ? $mode : 'bill';
    }

    /**
     * Billable weight for a given real weight and shipping type.
     * With minimum disabled (0) or mode != 'bill', returns the real weight.
     */
    public static function billable_weight(float $weight, string $shipping_type): float
    {
        if ($weight <= 0) {
            return $weight;
        }
        if (self::get_min_mode() !== 'bill') {
            return $weight;
        }
        return max($weight, self::get_min_lbs($shipping_type));
    }

    /* ------------------------------------------------------------------ */
    /*  Shipping type (maritime/air) session plumbing                      */
    /* ------------------------------------------------------------------ */

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
        $t = WC()->session ? WC()->session->get('shipping_type') : null;
        return in_array($t, ['maritimo', 'aereo'], true) ? $t : null;
    }

    public function update_shipping_type_from_checkout($post_data)
    {
        $shipping_type = $this->get_shipping_type_from_request_or_session();
        if ($shipping_type) {
            WC()->session->set('shipping_type', sanitize_text_field($shipping_type));
        }
    }

    public function inject_shipping_type_into_packages($packages)
    {
        $type = $this->get_shipping_type_from_request_or_session();
        foreach ($packages as &$p) {
            $p['cshr_shipping_type'] = $type;
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
                            url: cubaShippingRates.ajax_url,
                            data: {
                                action: 'update_shipping_type',
                                nonce: cubaShippingRates.shipping_type_nonce,
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
     * Fila informativa en el carrito cuando el mínimo facturable está activo
     */
    public function show_billable_weight_in_cart()
    {
        if (WC()->customer->get_shipping_country() !== 'CU') {
            return;
        }
        $type = $this->get_shipping_type() ?: 'maritimo';
        $min  = self::get_min_lbs($type);
        if ($min <= 0 || self::get_min_mode() !== 'bill') {
            return;
        }
        $weights = $this->get_cart_weight_split();
        if ($weights['weight_based'] <= 0 || $weights['weight_based'] >= $min) {
            return;
        }
        printf(
            '<tr class="cshr-billable-weight"><td colspan="2"><small>%s</small></td></tr>',
            sprintf(
                esc_html__('Peso del carrito: %1$s lb — este envío se factura por el mínimo de %2$s lb.', 'woocommerce'),
                esc_html(wc_format_localized_decimal($weights['weight_based'])),
                esc_html(wc_format_localized_decimal($min))
            )
        );
    }

    /**
     * Maneja la actualización del tipo de envío vía AJAX
     */
    public function ajax_update_shipping_type()
    {
        check_ajax_referer('cshr_shipping_type', 'nonce');
        if (isset($_POST['shipping_type'])) {
            $shipping_type = sanitize_text_field(wp_unslash($_POST['shipping_type']));
            if (in_array($shipping_type, ['maritimo', 'aereo'], true)) {
                WC()->session->set('shipping_type', $shipping_type);
            }
        }
        wp_die();
    }

    /**
     * Encola el JS necesario para el AJAX en el carrito.
     * El objeto cubaShippingRates (Enqueue) trae ajax_url y nonces; aquí solo
     * garantizamos jQuery. Nunca sobrescribir wc_cart_params: WooCommerce
     * localiza el suyo en el carrito y pisa cualquier réplica.
     */
    public function enqueue_cart_js()
    {
        if (is_cart()) {
            wp_enqueue_script('jquery');
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
            $shipping_type = WC()->session->get('shipping_type');
            $fields['shipping']['shipping_type'] = [
                'type'     => 'select',
                'label'    => __('Tipo de Envío', 'woocommerce'),
                'required' => true,
                'class'    => ['form-row-wide', 'update_totals_on_change'],
                'options'  => [
                    ''         => __('Seleccione tipo de envío', 'woocommerce'),
                    'maritimo' => __('Marítimo', 'woocommerce'),
                    'aereo'    => __('Aéreo', 'woocommerce'),
                ],
                'priority' => 25,
                'default'  => $shipping_type ? $shipping_type : '',
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
            $shipping_type = sanitize_text_field(wp_unslash($_POST['shipping_type']));
            if (in_array($shipping_type, ['maritimo', 'aereo'], true)) {
                WC()->session->set('shipping_type', $shipping_type);
                update_post_meta($order_id, '_shipping_type', $shipping_type);
            }
        }
    }

    /**
     * Recupera el tipo de envío desde la sesión para la lógica de cálculo
     */
    public function get_shipping_type()
    {
        $shipping_type = WC()->session ? WC()->session->get('shipping_type') : null;
        return $shipping_type ? $shipping_type : null;
    }

    public function custom_shipping_label($label, $method)
    {
        if (WC()->customer->get_shipping_country() === 'CU') {
            $label = '' . wc_price($method->cost);
        }
        return $label;
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

        $results = $wpdb->get_results("
            SELECT DISTINCT province
            FROM {$this->table_name}
            WHERE active = 1
        ");

        $active_provinces = [];
        foreach ($results as $row) {
            $active_provinces[] = $row->province;
        }

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
            'GRA' => 'Granma', // matches the Settings catalog + rates table key

            'SCU' => 'Santiago de Cuba',
            'GTM' => 'Guantánamo',
            'IJV' => 'Isla de la Juventud'
        ];

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

    /* ------------------------------------------------------------------ */
    /*  Rate calculation                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Splits the cart weight into weight-billed lbs and counts flat-priced items.
     * Only weight-billed items participate in the minimum-lbs rules.
     */
    private function get_cart_weight_split(): array
    {
        $split = ['weight_based' => 0.0, 'flat_items' => 0];
        if (!WC()->cart) {
            return $split;
        }
        foreach (WC()->cart->get_cart() as $item) {
            $product = $item['data'];
            if (!$product) {
                continue;
            }
            if ($this->get_flat_price_for_product($product->get_id()) > 0) {
                $split['flat_items'] += (int) $item['quantity'];
            } else {
                $split['weight_based'] += $this->get_product_weight_lbs($product) * (float) $item['quantity'];
            }
        }
        return $split;
    }

    private function get_flat_price_for_product(int $product_id): float
    {
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            $product_id = $product->get_parent_id();
        }
        $categories = wp_get_post_terms($product_id, 'product_cat');
        foreach ($categories as $category) {
            $flat = (float) get_term_meta($category->term_id, 'flat_price', true);
            if ($flat > 0) {
                return $flat;
            }
        }
        return 0.0;
    }

    private function get_product_weight_lbs($product): float
    {
        $weight = (float) $product->get_weight();
        if ($product->is_type('variation') && !$weight) {
            $parent = wc_get_product($product->get_parent_id());
            $weight = $parent ? (float) $parent->get_weight() : 0.0;
        }
        return (float) wc_get_weight($weight, 'lbs');
    }

    /**
     * Looks up the per-lb rates for a province/municipality with general fallbacks.
     */
    private function get_rates_for_destination(?string $province, ?string $municipality): array
    {
        global $wpdb;

        $row = null;
        if ($province && $municipality) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT rate_maritimo, rate_aereo FROM {$this->table_name} WHERE province=%s AND municipality=%s AND active=1",
                $province,
                $municipality
            ));
        }

        $maritimo_specific = $row && (float) $row->rate_maritimo > 0;
        $aereo_specific    = $row && (float) $row->rate_aereo > 0;

        return [
            'maritimo'          => $maritimo_specific ? (float) $row->rate_maritimo : (float) get_option('rate_maritimo_general', 0),
            'aereo'             => $aereo_specific ? (float) $row->rate_aereo : (float) get_option('rate_aereo_general', 0),
            'maritimo_specific' => $maritimo_specific,
            'aereo_specific'    => $aereo_specific,
            'percentage'        => (float) get_option('cuba_shipping_percentage', 0),
        ];
    }

    public function apply_shipping_rate($rates, $package)
    {
        if (WC()->customer->get_shipping_country() !== 'CU') {
            return $rates;
        }

        $chosen = WC()->session ? WC()->session->get('chosen_shipping_methods') : null;
        $chosen_id = is_array($chosen) && !empty($chosen[0]) ? $chosen[0] : null;

        $shipping_type = $package['cshr_shipping_type'] ?? $this->get_shipping_type_from_request_or_session();

        if (!$shipping_type) {
            foreach ($rates as $key => $rate_obj) {
                $rates[$key]->cost = 0;
            }
            return $rates;
        }

        $prov = WC()->customer->get_shipping_state();
        $mun  = WC()->customer->get_shipping_city();
        $dest = $this->get_rates_for_destination($prov, $mun);
        $rate_per_lb = $shipping_type === 'aereo' ? $dest['aereo'] : $dest['maritimo'];

        foreach ($rates as $key => $rate_obj) {
            if ($chosen_id && $key !== $chosen_id) {
                continue;
            }

            $weight_total = 0.0;
            $flat_total   = 0.0;

            foreach ($package['contents'] as $item) {
                $product  = $item['data'];
                $quantity = (float) $item['quantity'];
                $flat     = $this->get_flat_price_for_product($product->get_id());

                if ($flat > 0) {
                    $flat_total += $flat * $quantity;
                } else {
                    $weight_total += $this->get_product_weight_lbs($product) * $quantity;
                }
            }

            $billable   = self::billable_weight($weight_total, $shipping_type);
            $total_rate = ($billable * $rate_per_lb) + $flat_total;

            if ($dest['percentage'] > 0) {
                $total_rate += ($total_rate * $dest['percentage'] / 100);
            }

            $rates[$key]->cost = max(0, wc_format_decimal($total_rate, wc_get_price_decimals()));
        }

        return $rates;
    }

    /**
     * Kept for backward compatibility with pre-1.1.0 callers.
     * Per-item rate without the minimum-lbs clamp (the clamp is per package).
     */
    public function calculate_shipping_rate(
        $product_id,
        $shipping_type = 'maritimo',
        $rate_maritimo = null,
        $rate_aereo = null,
        $rate_maritimo_general = 0,
        $rate_aereo_general = 0,
        $quantity = 1
    ) {
        $flat_price = $this->get_flat_price_for_product((int) $product_id);
        if ($flat_price > 0) {
            return $flat_price * $quantity;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return 0.0;
        }
        $product_weight = $this->get_product_weight_lbs($product);

        if ($shipping_type === 'aereo') {
            $rate_to_use = ($rate_aereo !== null && (float) $rate_aereo > 0) ? (float) $rate_aereo : (float) $rate_aereo_general;
        } else {
            $rate_to_use = ($rate_maritimo !== null && (float) $rate_maritimo > 0) ? (float) $rate_maritimo : (float) $rate_maritimo_general;
        }

        return $product_weight * $rate_to_use * $quantity;
    }

    /* ------------------------------------------------------------------ */
    /*  Session-independent estimate (home calculator, product page)       */
    /* ------------------------------------------------------------------ */

    /**
     * Estimate shipping for a destination + type + weight, applying the same
     * rules as the cart (rate lookup, minimum billable lbs, percentage).
     */
    public function estimate(string $province, string $municipality, string $shipping_type, float $weight): array
    {
        $shipping_type = $shipping_type === 'aereo' ? 'aereo' : 'maritimo';
        $weight        = max(0.0, $weight);

        $dest        = $this->get_rates_for_destination($province ?: null, $municipality ?: null);
        $rate_per_lb = $shipping_type === 'aereo' ? $dest['aereo'] : $dest['maritimo'];
        $is_specific = $shipping_type === 'aereo' ? $dest['aereo_specific'] : $dest['maritimo_specific'];

        $min      = self::get_min_lbs($shipping_type);
        $mode     = self::get_min_mode();
        $billable = self::billable_weight($weight, $shipping_type);

        $total = $billable * $rate_per_lb;
        if ($dest['percentage'] > 0) {
            $total += ($total * $dest['percentage'] / 100);
        }

        return [
            'shipping_type' => $shipping_type,
            'rate_per_lb'   => $rate_per_lb,
            'rate_source'   => $is_specific ? 'municipality' : 'general',
            'weight'        => $weight,
            'min_lbs'       => $min,
            'min_mode'      => $mode,
            'min_applied'   => ($mode === 'bill' && $min > 0 && $weight > 0 && $weight < $min),
            'below_min'     => ($mode === 'block' && $min > 0 && $weight > 0 && $weight < $min),
            'billable_lbs'  => $billable,
            'total'         => round((float) $total, wc_get_price_decimals()),
            'currency'      => get_woocommerce_currency(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Minimum weight — 'block' mode                                      */
    /* ------------------------------------------------------------------ */

    public function validate_minimum_weight()
    {
        if (WC()->customer->get_shipping_country() !== 'CU' || self::get_min_mode() !== 'block') {
            return;
        }
        $type = $this->get_shipping_type_from_request_or_session() ?: 'maritimo';
        $min  = self::get_min_lbs($type);
        if ($min <= 0) {
            return;
        }
        $weights = $this->get_cart_weight_split();
        if ($weights['weight_based'] > 0 && $weights['weight_based'] < $min) {
            wc_add_notice(
                sprintf(
                    __('Peso insuficiente para el envío. Mínimo requerido: %1$s lb. Peso actual: %2$s lb.', 'woocommerce'),
                    wc_format_localized_decimal($min),
                    wc_format_localized_decimal($weights['weight_based'])
                ),
                'error'
            );
        }
    }

    public function show_minimum_weight_notice()
    {
        if (WC()->customer->get_shipping_country() !== 'CU' || self::get_min_mode() !== 'block') {
            return;
        }
        $type = $this->get_shipping_type() ?: 'maritimo';
        $min  = self::get_min_lbs($type);
        if ($min <= 0) {
            return;
        }
        $weights = $this->get_cart_weight_split();
        if ($weights['weight_based'] > 0 && $weights['weight_based'] < $min) {
            wc_print_notice(
                sprintf(
                    __('Este envío requiere un mínimo de %1$s lb. Tu carrito tiene %2$s lb — agrega %3$s lb más para poder completar la compra.', 'woocommerce'),
                    wc_format_localized_decimal($min),
                    wc_format_localized_decimal($weights['weight_based']),
                    wc_format_localized_decimal($min - $weights['weight_based'])
                ),
                'notice'
            );
        }
    }
}
