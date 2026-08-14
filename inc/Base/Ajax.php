<?php

/*
*
* @package aristidesgp
*
*/

namespace CSHR\Inc\Base;

use CSHR\Inc\Base\ShippingRates;

class Ajax
{
    public function register()
    {
        // Public shipping-cost estimate (home calculator, product page widget)
        add_action('wp_ajax_cshr_estimate', [$this, 'estimate']);
        add_action('wp_ajax_nopriv_cshr_estimate', [$this, 'estimate']);
    }

    /**
     * POST: nonce, province, municipality, shipping_type (maritimo|aereo), weight (lbs)
     * Applies the same rules as the cart: municipality rate with general fallback,
     * minimum billable lbs, percentage surcharge.
     */
    public function estimate()
    {
        check_ajax_referer('cshr_estimate', 'nonce');

        $province      = isset($_POST['province']) ? sanitize_text_field(wp_unslash($_POST['province'])) : '';
        $municipality  = isset($_POST['municipality']) ? sanitize_text_field(wp_unslash($_POST['municipality'])) : '';
        $shipping_type = isset($_POST['shipping_type']) ? sanitize_text_field(wp_unslash($_POST['shipping_type'])) : 'maritimo';
        $weight        = isset($_POST['weight']) ? (float) $_POST['weight'] : 0.0;

        if ($weight <= 0) {
            wp_send_json_error(['message' => __('Ingresa un peso válido en libras.', 'woocommerce')], 400);
        }
        if ($weight > 10000) {
            wp_send_json_error(['message' => __('Peso fuera de rango.', 'woocommerce')], 400);
        }

        $calculator = new ShippingRates();

        wp_send_json_success($calculator->estimate($province, $municipality, $shipping_type, $weight));
    }
}
