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



        //add_action('admin_enqueue_scripts',  array($this, 'CSHR_enqueue_frontend'));

        add_action('wp_enqueue_scripts',  array($this, 'CSHR_enqueue_frontend'));       



    }



    /**

     * Enqueueing the main scripts with all the javascript logic that this plugin offer

     */

    function CSHR_enqueue_frontend()

    {

        $css_ver = filemtime( CSHR_PLUGIN_PATH . 'assets/css/main.css' );

        $js_ver  = filemtime( CSHR_PLUGIN_PATH . 'assets/js/cuba-shipping-rates.js' );

        wp_enqueue_style('main-css', CSHR_PLUGIN_URL . 'assets/css/main.css', [], $css_ver);

        wp_enqueue_script('cuba-shipping-rates', CSHR_PLUGIN_URL  . 'assets/js/cuba-shipping-rates.js', ['jquery'], $js_ver, true); 

        wp_localize_script('cuba-shipping-rates', 'cubaShippingRates', [

            'municipalities' => $this->get_cuba_municipalities()

        ]);

        /* wp_enqueue_script('main-js', CSHR_PLUGIN_URL  . 'assets/js/main.js', array('jquery'), 'v-' . strtotime(date('h:i:s')), true);





        wp_localize_script('main-js', 'parameters', ['ajax_url' => admin_url('admin-ajax.php'), 'plugin_url' => CSHR_PLUGIN_URL]);

        wp_enqueue_script('checkout-js', CSHR_PLUGIN_URL  . 'assets/js/checkout.js', array('jquery', 'main-js'), '1.0', true); */

    }

    

    private function get_cuba_municipalities() {

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

