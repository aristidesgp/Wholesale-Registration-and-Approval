<?php
/*
*
* @package aristidesgp


* Plugin Name:  Cuba Shipping Rates for WooCommerce
* Plugin URI:   https://devfl.us
* Description:  Agrega provincias de Cuba en WooCommerce y asigna tarifas de envío según la provincia seleccionada.
* Version:      1.0.0
* Author:       Aristides Gutierrez
* Author URI:   https://devfl.us
*/


defined('ABSPATH') or die('You do not have access, sally human!!!');

define('CSHR_PLUGIN_VERSION', '1.0.0');

if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once  dirname(__FILE__) . '/vendor/autoload.php';
}

//Change WRPL for plugin's initials
define('CSHR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CSHR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CSHR_ADMIN_URL', get_admin_url());
define('CSHR_PLUGIN_DIR_BASENAME', dirname(plugin_basename(__FILE__)));
define('CSHR_THEME_DOMAIN', get_site_url());


//include the helpers
include 'inc/util/Helper.php';
include 'inc/Base/Logs.php';

if (class_exists('CSHR\\Inc\\Init')) {
    register_activation_hook(__FILE__, array('CSHR\\Inc\\Base\\Activate', 'activate'));
    CSHR\Inc\Init::register_services();
}
