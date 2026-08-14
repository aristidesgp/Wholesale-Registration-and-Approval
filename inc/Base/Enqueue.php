<?php

/*
*
* @package aristidesgp
*
*/

namespace CSHR\Inc\Base;

use CSHR\Inc\Base\Logs;

class Enqueue
{
    private $table_name;
    public function register()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cuba_shipping_rates';

        add_action('wp_enqueue_scripts',  array($this, 'CSHR_enqueue_frontend'));
    }

    /**
     * Enqueueing the main scripts with all the javascript logic that this plugin offer
     */
    function CSHR_enqueue_frontend()
    {
        wp_enqueue_style('cshr-main', CSHR_PLUGIN_URL . 'assets/css/main.css', [], CSHR_PLUGIN_VERSION);
        wp_enqueue_script('cuba-shipping-rates', CSHR_PLUGIN_URL  . 'assets/js/cuba-shipping-rates.js', ['jquery'], CSHR_PLUGIN_VERSION, true);
        wp_localize_script('cuba-shipping-rates', 'cubaShippingRates', [
            'ajax_url'            => admin_url('admin-ajax.php'),
            'estimate_nonce'      => wp_create_nonce('cshr_estimate'),
            'shipping_type_nonce' => wp_create_nonce('cshr_shipping_type'),
            'municipalities'      => $this->get_cuba_municipalities()
        ]);
    }

    private function get_cuba_municipalities()
    {
        global $wpdb;

        // Obtener todas las provincias y municipios activos
        $results = $wpdb->get_results("
            SELECT province, municipality 
            FROM {$this->table_name} 
            WHERE active = 1
        ");

        // Crear un array para almacenar los municipios activos por provincia
        $municipalities = [];
        foreach ($results as $row) {
            if (!isset($municipalities[$row->province])) {
                $municipalities[$row->province] = [];
            }
            $municipalities[$row->province][] = $row->municipality;
        }
        return $municipalities;
    }
}
