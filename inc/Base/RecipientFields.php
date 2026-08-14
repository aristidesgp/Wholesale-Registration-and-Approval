<?php

/*
*
* @package aristidesgp
*
*/

namespace CSHR\Inc\Base;

/**
 * Cuba recipient fields at checkout: Carnet de Identidad + Cuban phone.
 *
 * Feature-gated behind the 'cshr_recipient_fields_enabled' option (default OFF)
 * because this plugin is shared across sites — enabling it is a per-site decision.
 */
class RecipientFields
{
    const OPTION_ENABLED = 'cshr_recipient_fields_enabled';
    const META_CI        = '_shipping_ci';
    const META_PHONE     = '_shipping_cuba_phone';

    public function register()
    {
        if (get_option(self::OPTION_ENABLED, 'no') !== 'yes') {
            return;
        }

        add_filter('woocommerce_checkout_fields', [$this, 'add_fields'], 30);
        add_action('woocommerce_after_checkout_validation', [$this, 'validate'], 10, 2);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save']);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'show_in_admin']);
        add_action('woocommerce_order_details_after_customer_details', [$this, 'show_in_order_details']);
        add_action('woocommerce_email_customer_details', [$this, 'show_in_email'], 15, 3);
    }

    private function is_cuba_shipping(): bool
    {
        return WC()->customer && WC()->customer->get_shipping_country() === 'CU';
    }

    /**
     * Fields are ALWAYS registered and hidden client-side when the destination
     * is not Cuba (see cuba-shipping-rates.js). Gating them on the session
     * country here meant a customer who arrived with another country selected
     * could never see them: this filter runs once at render, the country is
     * chosen afterwards in the browser. Required-ness is enforced in validate().
     */
    public function add_fields($fields)
    {
        $fields['shipping']['shipping_ci'] = [
            'type'        => 'text',
            'label'       => __('Carnet de Identidad del destinatario', 'woocommerce'),
            'placeholder' => __('11 dígitos', 'woocommerce'),
            'description' => __('11 dígitos, requerido por aduana', 'woocommerce'),
            'required'    => false, // enforced in validate() for CU only
            'class'       => ['form-row-first', 'cshr-cu-only'],
            'priority'    => 26,
            'maxlength'   => 11,
        ];

        $fields['shipping']['shipping_cuba_phone'] = [
            'type'        => 'tel',
            'label'       => __('Teléfono del destinatario en Cuba', 'woocommerce'),
            'placeholder' => __('+53 5 XXX XXXX', 'woocommerce'),
            'required'    => false, // enforced in validate() for CU only
            'class'       => ['form-row-last', 'cshr-cu-only'],
            'priority'    => 27,
        ];

        return $fields;
    }

    public function validate($data, $errors)
    {
        $country = isset($data['shipping_country']) ? $data['shipping_country'] : '';
        if (!$country && isset($data['billing_country'])) {
            $country = $data['billing_country'];
        }
        // Only the submitted destination decides: the session may still hold a
        // stale country while the customer is changing it in the form.
        if ($country !== 'CU') {
            return;
        }

        $ci = isset($_POST['shipping_ci']) ? preg_replace('/\D/', '', wp_unslash($_POST['shipping_ci'])) : '';
        if ($ci === '' || strlen($ci) !== 11) {
            $errors->add(
                'shipping_ci',
                __('El Carnet de Identidad del destinatario debe tener 11 dígitos.', 'woocommerce')
            );
        }

        $phone = isset($_POST['shipping_cuba_phone']) ? trim(wp_unslash($_POST['shipping_cuba_phone'])) : '';
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '' || strlen($digits) < 8) {
            $errors->add(
                'shipping_cuba_phone',
                __('Ingresa un teléfono de contacto válido en Cuba.', 'woocommerce')
            );
        }
    }

    public function save($order_id)
    {
        if (isset($_POST['shipping_ci'])) {
            $ci = preg_replace('/\D/', '', wp_unslash($_POST['shipping_ci']));
            if ($ci !== '') {
                update_post_meta($order_id, self::META_CI, $ci);
            }
        }
        if (isset($_POST['shipping_cuba_phone'])) {
            $phone = sanitize_text_field(wp_unslash($_POST['shipping_cuba_phone']));
            if ($phone !== '') {
                update_post_meta($order_id, self::META_PHONE, $phone);
            }
        }
    }

    private function get_meta($order): array
    {
        $order_id = is_object($order) ? $order->get_id() : (int) $order;
        return [
            'ci'    => get_post_meta($order_id, self::META_CI, true),
            'phone' => get_post_meta($order_id, self::META_PHONE, true),
        ];
    }

    public function show_in_admin($order)
    {
        $meta = $this->get_meta($order);
        if (!$meta['ci'] && !$meta['phone']) {
            return;
        }
        echo '<div class="cshr-recipient-fields">';
        if ($meta['ci']) {
            echo '<p><strong>' . esc_html__('Carnet de Identidad:', 'woocommerce') . '</strong> ' . esc_html($meta['ci']) . '</p>';
        }
        if ($meta['phone']) {
            echo '<p><strong>' . esc_html__('Teléfono en Cuba:', 'woocommerce') . '</strong> ' . esc_html($meta['phone']) . '</p>';
        }
        echo '</div>';
    }

    public function show_in_order_details($order)
    {
        $meta = $this->get_meta($order);
        if (!$meta['ci'] && !$meta['phone']) {
            return;
        }
        echo '<table class="woocommerce-table cshr-recipient-table"><tbody>';
        if ($meta['ci']) {
            echo '<tr><th>' . esc_html__('Carnet de Identidad', 'woocommerce') . '</th><td>' . esc_html($meta['ci']) . '</td></tr>';
        }
        if ($meta['phone']) {
            echo '<tr><th>' . esc_html__('Teléfono en Cuba', 'woocommerce') . '</th><td>' . esc_html($meta['phone']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public function show_in_email($order, $sent_to_admin = false, $plain_text = false)
    {
        $meta = $this->get_meta($order);
        if (!$meta['ci'] && !$meta['phone']) {
            return;
        }
        if ($plain_text) {
            if ($meta['ci']) {
                echo esc_html__('Carnet de Identidad:', 'woocommerce') . ' ' . esc_html($meta['ci']) . "\n";
            }
            if ($meta['phone']) {
                echo esc_html__('Teléfono en Cuba:', 'woocommerce') . ' ' . esc_html($meta['phone']) . "\n";
            }
            return;
        }
        echo '<div class="cshr-email-recipient" style="margin-bottom:16px;">';
        if ($meta['ci']) {
            echo '<p style="margin:0;"><strong>' . esc_html__('Carnet de Identidad:', 'woocommerce') . '</strong> ' . esc_html($meta['ci']) . '</p>';
        }
        if ($meta['phone']) {
            echo '<p style="margin:0;"><strong>' . esc_html__('Teléfono en Cuba:', 'woocommerce') . '</strong> ' . esc_html($meta['phone']) . '</p>';
        }
        echo '</div>';
    }
}
