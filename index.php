<?php
/*
*
* @package aristidesgp


Plugin Name:  Wholesale Registration and Approval
Plugin URI:   https://thomasgbennett.com/
Description:  Adds a custom registration form for wholesale users, handles approval process, and restricts access based on approval status.
Version:      1.0.0
Author:       Bennett Web Group (Aristides Gutierrez)
Author URI:   https://thomasgbennett.com/
*/


defined('ABSPATH') or die('You do not have access, sally human!!!');

define('WRA_PLUGIN_VERSION', '1.0.0');

if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once  dirname(__FILE__) . '/vendor/autoload.php';
}

//Change WRPL for plugin's initials
define('WRA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WRA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WRA_ADMIN_URL', get_admin_url());
define('WRA_PLUGIN_DIR_BASENAME', dirname(plugin_basename(__FILE__)));
define('WRA_THEME_DOMAIN', get_site_url());


//include the helpers
include 'inc/util/Helper.php';
include 'inc/Base/Logs.php';

if (class_exists('WRA\\Inc\\Init')) {
    register_activation_hook(__FILE__, array('WRA\\Inc\\Base\\Activate', 'activate'));
    WRA\Inc\Init::register_services();
}
