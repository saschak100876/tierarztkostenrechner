<?php
/**
 * Plugin Name: Tierarztkostenrechner
 * Description: GOT-basierter Kostenorientierungsrechner fuer Tierarztkosten mit eigenen Tabellen, REST API, Suche und Shortcode.
 * Version: 1.0.0
 * Author: Tierarztkostenrechner
 * Text Domain: tierarztkostenrechner
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

define( 'TKR_VERSION', '1.0.0' );
define( 'TKR_DB_VERSION', '1.0.0' );
define( 'TKR_PLUGIN_FILE', __FILE__ );
define( 'TKR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TKR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TKR_PLUGIN_DIR . 'includes/class-plugin.php';
require_once TKR_PLUGIN_DIR . 'includes/database/class-schema.php';

register_activation_hook( __FILE__, array( 'TKR_Schema', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'tierarztkostenrechner', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		TKR_Plugin::instance()->init();
	}
);
