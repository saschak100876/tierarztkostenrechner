<?php
/**
 * Plugin Name: Tierarztkostenrechner
 * Description: GOT-basierter Tierarztkostenrechner mit Datenimport, Suche, REST API und Shortcode.
 * Version: 1.0.0
 * Author: Tierarztkostenrechner
 * Text Domain: tierarztkostenrechner
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('TKR_VERSION', '1.0.0');
define('TKR_DB_VERSION', '1.0.0');
define('TKR_PLUGIN_FILE', __FILE__);
define('TKR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TKR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once TKR_PLUGIN_DIR . 'includes/class-plugin.php';
require_once TKR_PLUGIN_DIR . 'includes/class-activator.php';
require_once TKR_PLUGIN_DIR . 'includes/class-deactivator.php';

register_activation_hook(__FILE__, array('TKR_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('TKR_Deactivator', 'deactivate'));

add_action('plugins_loaded', static function () {
    load_plugin_textdomain('tierarztkostenrechner', false, dirname(plugin_basename(__FILE__)) . '/languages');
    TKR_Plugin::instance()->run();
});
