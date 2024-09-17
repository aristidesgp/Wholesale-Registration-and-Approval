<?php

/*
*
* @package aristidesgp
*
*/

namespace WRA\Inc\Base;
use WRA\Inc\Base\Logs;
class Enqueue
{

    public function register()
    {

        //add_action('admin_enqueue_scripts',  array($this, 'WRA_enqueue_frontend'));
        add_action('wp_enqueue_scripts',  array($this, 'WRA_enqueue_frontend'));       

    }

    /**
     * Enqueueing the main scripts with all the javascript logic that this plugin offer
     */
    function WRA_enqueue_frontend()
    {
        wp_enqueue_style('main-css', WRA_PLUGIN_URL . 'assets/css/main.css');
        /* wp_enqueue_script('main-js', WRA_PLUGIN_URL  . 'assets/js/main.js', array('jquery'), 'v-' . strtotime(date('h:i:s')), true);


        wp_localize_script('main-js', 'parameters', ['ajax_url' => admin_url('admin-ajax.php'), 'plugin_url' => WRA_PLUGIN_URL]);
        wp_enqueue_script('checkout-js', WRA_PLUGIN_URL  . 'assets/js/checkout.js', array('jquery', 'main-js'), '1.0', true); */
    }    
}
