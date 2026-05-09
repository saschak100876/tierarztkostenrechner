<?php
defined( 'ABSPATH' ) || exit;

class TKR_Plugin {

    private static ?TKR_Plugin $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies(): void {
        require_once TKR_PLUGIN_DIR . 'includes/database/class-schema.php';
        require_once TKR_PLUGIN_DIR . 'includes/database/class-migrations.php';
        require_once TKR_PLUGIN_DIR . 'includes/database/repositories/class-animals-repository.php';
        require_once TKR_PLUGIN_DIR . 'includes/database/repositories/class-subgroups-repository.php';
        require_once TKR_PLUGIN_DIR . 'includes/database/repositories/class-got-services-repository.php';
        require_once TKR_PLUGIN_DIR . 'includes/database/repositories/class-fee-rules-repository.php';
        require_once TKR_PLUGIN_DIR . 'includes/database/repositories/class-treatments-repository.php';
        require_once TKR_PLUGIN_DIR . 'includes/database/repositories/class-treatment-services-repository.php';
        require_once TKR_PLUGIN_DIR . 'includes/database/repositories/class-search-terms-repository.php';
        require_once TKR_PLUGIN_DIR . 'includes/import/class-validator.php';
        require_once TKR_PLUGIN_DIR . 'includes/import/class-importer.php';
        require_once TKR_PLUGIN_DIR . 'includes/calculator/class-fee-rule-engine.php';
        require_once TKR_PLUGIN_DIR . 'includes/calculator/class-calculator.php';
        require_once TKR_PLUGIN_DIR . 'includes/search/class-normalizer.php';
        require_once TKR_PLUGIN_DIR . 'includes/search/class-search-service.php';
        require_once TKR_PLUGIN_DIR . 'includes/rest/class-rest-controller.php';
        require_once TKR_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
        require_once TKR_PLUGIN_DIR . 'includes/admin/class-import-page.php';
        require_once TKR_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
    }

    public function activate(): void {
        TKR_Schema::create_tables();
        TKR_Migrations::run();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }

    public function init(): void {
        load_plugin_textdomain(
            TKR_TEXT_DOMAIN,
            false,
            dirname( plugin_basename( TKR_PLUGIN_FILE ) ) . '/languages'
        );

        ( new TKR_REST_Controller() )->register_routes();
        ( new TKR_Admin_Menu() )->init();

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_shortcode( 'tierarztkostenrechner', [ $this, 'render_shortcode' ] );

        if ( did_action( 'elementor/loaded' ) ) {
            add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
        }
    }

    public function enqueue_frontend_assets(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'tierarztkostenrechner' ) ) {
            return;
        }
        $this->do_enqueue_frontend_assets();
    }

    public function do_enqueue_frontend_assets(): void {
        wp_enqueue_style(
            'tkr-frontend',
            TKR_PLUGIN_URL . 'assets/css/tkr-frontend.css',
            [],
            TKR_VERSION
        );
        wp_enqueue_script(
            'tkr-frontend',
            TKR_PLUGIN_URL . 'assets/js/tkr-frontend.js',
            [],
            TKR_VERSION,
            true
        );
        wp_localize_script( 'tkr-frontend', 'TKR', [
            'apiBase' => esc_url_raw( rest_url( 'tkr/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'i18n'    => [
                'selectAnimal'          => __( 'Bitte Tierart wählen', TKR_TEXT_DOMAIN ),
                'selectSituation'       => __( 'Behandlungssituation wählen', TKR_TEXT_DOMAIN ),
                'selectTreatment'       => __( 'Behandlung wählen', TKR_TEXT_DOMAIN ),
                'searchPlaceholder'     => __( 'Symptom, Behandlung oder Rasse eingeben ...', TKR_TEXT_DOMAIN ),
                'resultHeading'         => __( 'Orientierung nach GOT', TKR_TEXT_DOMAIN ),
                'disclaimer'            => __( 'Dies ist eine unverbindliche Orientierung auf Basis der GOT. Keine Rechtsberatung, keine Diagnose, keine verbindliche Kostenzusage.', TKR_TEXT_DOMAIN ),
                'emergencyFee'          => __( 'Notdienstgebühr (einmalig)', TKR_TEXT_DOMAIN ),
                'notKnownYet'           => __( 'Steht noch nicht fest', TKR_TEXT_DOMAIN ),
                'dontKnowSubgroup'      => __( 'Weiß ich nicht', TKR_TEXT_DOMAIN ),
            ],
        ] );
    }

    public function render_shortcode( array $atts ): string {
        $atts = shortcode_atts(
            [
                'default_animal'    => '',
                'default_treatment' => '',
                'layout'            => 'full',
                'show_disclaimer'   => '1',
            ],
            $atts,
            'tierarztkostenrechner'
        );

        $this->do_enqueue_frontend_assets();

        $data_attrs = '';
        foreach ( $atts as $key => $val ) {
            $data_attrs .= ' data-' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
        }

        return '<div id="tkr-app"' . $data_attrs . '></div>';
    }

    public function register_elementor_widget( \Elementor\Widgets_Manager $manager ): void {
        require_once TKR_PLUGIN_DIR . 'includes/elementor/class-elementor-widget.php';
        $manager->register( new TKR_Elementor_Widget() );
    }
}
