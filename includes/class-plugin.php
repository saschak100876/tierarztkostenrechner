<?php
defined('ABSPATH') || exit;

final class TKR_Plugin {
    private static $instance = null;
    private $services = array();

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->services['repositories'] = new TKR_Repositories();
        $this->services['calculator'] = new TKR_Calculator($this->services['repositories']);
        $this->services['search'] = new TKR_Search_Service($this->services['repositories']);
    }

    private function load_dependencies() {
        $files = array(
            'includes/database/class-schema.php',
            'includes/database/class-migrations.php',
            'includes/database/repositories/class-repositories.php',
            'includes/importer/class-validator.php',
            'includes/importer/class-importer.php',
            'includes/calculator/class-fee-rule-engine.php',
            'includes/calculator/class-calculator.php',
            'includes/search/class-search-normalizer.php',
            'includes/search/class-search-service.php',
            'includes/rest/class-rest-controller.php',
            'includes/admin/class-admin-menu.php',
            'includes/frontend/class-assets.php',
            'includes/frontend/class-shortcode.php',
            'includes/elementor/class-elementor-widget.php',
            'includes/embed/class-embed-controller.php',
        );
        foreach ($files as $file) {
            require_once TKR_PLUGIN_DIR . $file;
        }
    }

    public function run() {
        TKR_Migrations::maybe_migrate();
        add_action('init', array(new TKR_Shortcode(), 'register'));
        add_action('wp_enqueue_scripts', array(new TKR_Assets(), 'register'));
        add_action('rest_api_init', array(new TKR_REST_Controller($this->services['repositories'], $this->services['calculator'], $this->services['search']), 'register_routes'));
        add_action('admin_menu', array(new TKR_Admin_Menu(), 'register'));
        add_action('elementor/widgets/register', array('TKR_Elementor_Widget', 'register_widget'));
    }
}
