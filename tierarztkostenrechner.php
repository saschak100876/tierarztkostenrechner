<?php
/**
 * Plugin Name: Tierarztkostenrechner
 * Plugin URI:  https://tierarztkostenrechner.de
 * Description: GOT-basierter Tierarztkostenrechner fuer Tierhalter – unverbindliche Orientierung.
 * Version:     1.1.0
 * Author:      tierarztkostenrechner.de
 * Text Domain: tierarztkostenrechner
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'TKR_VERSION',     '1.1.0' );
define( 'TKR_PLUGIN_FILE', __FILE__ );
define( 'TKR_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'TKR_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'TKR_TEXT_DOMAIN', 'tierarztkostenrechner' );

require_once TKR_PLUGIN_DIR . 'includes/class-plugin.php';

function tkr_plugin(): TKR_Plugin {
    return TKR_Plugin::instance();
}

register_activation_hook( __FILE__,   [ tkr_plugin(), 'activate' ] );
register_deactivation_hook( __FILE__, [ tkr_plugin(), 'deactivate' ] );

add_action( 'plugins_loaded', [ tkr_plugin(), 'init' ] );
