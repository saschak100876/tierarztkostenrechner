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
        // Create / update tables first
        TKR_Schema::create_tables();
        // Seed standard fee rules only when the table is empty to protect imported data
        TKR_Migrations::seed_fee_rules_if_empty();
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

        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_frontend_assets' ] );
        add_shortcode( 'tierarztkostenrechner', [ $this, 'render_shortcode' ] );

        add_action( 'elementor/loaded', function () {
            add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
        } );
    }

    /**
     * Only enqueue assets on pages that actually contain the shortcode.
     */
    public function maybe_enqueue_frontend_assets(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return;
        if ( has_shortcode( $post->post_content, 'tierarztkostenrechner' ) ) {
            $this->do_enqueue_frontend_assets();
        }
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
                'selectAnimal'      => __( 'Bitte Tierart wählen', TKR_TEXT_DOMAIN ),
                'selectSituation'   => __( 'Behandlungssituation wählen', TKR_TEXT_DOMAIN ),
                'selectTreatment'   => __( 'Behandlung wählen', TKR_TEXT_DOMAIN ),
                'searchPlaceholder' => __( 'Symptom, Behandlung oder Rasse eingeben …', TKR_TEXT_DOMAIN ),
                'resultHeading'     => __( 'Unverbindliche Orientierung nach GOT', TKR_TEXT_DOMAIN ),
                'disclaimer'        => __( 'Diese Berechnung ist eine unverbindliche Orientierung auf Basis der GOT (Gebührenordnung für Tierärztinnen und Tierärzte). Sie ersetzt keine tierärztliche Beratung und ist keine Rechtsberatung, Diagnose oder verbindliche Kostenzusage. Die tatsächlichen Kosten können je nach Einzelfall abweichen.', TKR_TEXT_DOMAIN ),
                'emergencyFee'      => __( 'Notdienstgebühr (einmalig pro Angelegenheit)', TKR_TEXT_DOMAIN ),
                'notKnownYet'       => __( 'Steht noch nicht fest – Suche öffnen', TKR_TEXT_DOMAIN ),
                'dontKnowSubgroup'  => __( 'Weiß ich nicht / Allgemein', TKR_TEXT_DOMAIN ),
                'noData'            => __( 'Es wurden noch keine Daten importiert. Bitte importieren Sie zunächst die Masterdatei.', TKR_TEXT_DOMAIN ),
                'loading'           => __( 'Wird geladen …', TKR_TEXT_DOMAIN ),
                'calculating'       => __( 'Berechnung läuft …', TKR_TEXT_DOMAIN ),
                'errorLoad'         => __( 'Fehler beim Laden der Daten. Bitte Seite neu laden.', TKR_TEXT_DOMAIN ),
                'errorCalc'         => __( 'Fehler bei der Berechnung. Bitte erneut versuchen.', TKR_TEXT_DOMAIN ),
                'restartLabel'      => __( '↺ Neue Berechnung starten', TKR_TEXT_DOMAIN ),
                'backLabel'         => __( '← Zurück', TKR_TEXT_DOMAIN ),
                'sexMale'           => __( 'Männlich', TKR_TEXT_DOMAIN ),
                'sexFemale'         => __( 'Weiblich', TKR_TEXT_DOMAIN ),
                'sexUnknown'        => __( 'Unbekannt', TKR_TEXT_DOMAIN ),
                'sexQuestion'       => __( 'Geschlecht des Tieres', TKR_TEXT_DOMAIN ),
                'noSearchResults'   => __( 'Keine Treffer gefunden. Bitte anderen Suchbegriff versuchen.', TKR_TEXT_DOMAIN ),
                'orientationLabel'  => __( 'Kostenrahmen (unverbindlich)', TKR_TEXT_DOMAIN ),
                'normalCase'        => __( 'Normalfall', TKR_TEXT_DOMAIN ),
                'emergencyCase'     => __( 'Tierärztlicher Notdienst', TKR_TEXT_DOMAIN ),
            ],
        ] );

        // Output CSS custom properties from saved settings as inline style
        $css = $this->build_settings_css();
        if ( $css ) {
            wp_add_inline_style( 'tkr-frontend', $css );
        }
    }

    /**
     * Build an inline CSS block from saved admin settings.
     */
    private function build_settings_css(): string {
        $primary = TKR_Settings_Page::get( 'primary_color', '#20547E' );
        $accent  = TKR_Settings_Page::get( 'accent_color',  '#F39200' );

        $primary = sanitize_hex_color( $primary ) ?: '#20547E';
        $accent  = sanitize_hex_color( $accent )  ?: '#F39200';

        if ( $primary === '#20547E' && $accent === '#F39200' ) {
            return ''; // defaults already in stylesheet
        }

        return sprintf(
            '[data-tkr-instance] { --tkr-primary: %s; --tkr-accent: %s; }',
            esc_attr( $primary ),
            esc_attr( $accent )
        );
    }

    /**
     * Render [tierarztkostenrechner] shortcode.
     * Supports multiple instances on the same page via data-tkr-instance.
     */
    public function render_shortcode( array $atts ): string {
        static $count = 0;
        $count++;

        $settings = [
            'default_animal'    => TKR_Settings_Page::get( 'default_animal',    '' ),
            'default_treatment' => TKR_Settings_Page::get( 'default_treatment', '' ),
            'layout'            => TKR_Settings_Page::get( 'layout_mode',        'full' ),
            'show_disclaimer'   => TKR_Settings_Page::get( 'show_disclaimer',    '1' ),
        ];

        $atts = shortcode_atts( $settings, $atts, 'tierarztkostenrechner' );

        $this->do_enqueue_frontend_assets();

        $instance_id = 'tkr-app-' . $count;
        $data_attrs  = ' data-tkr-instance="1"';
        foreach ( $atts as $key => $val ) {
            $data_attrs .= ' data-' . esc_attr( str_replace( '_', '-', $key ) ) . '="' . esc_attr( $val ) . '"';
        }

        return '<div id="' . esc_attr( $instance_id ) . '"' . $data_attrs . '></div>';
    }

    public function register_elementor_widget( \Elementor\Widgets_Manager $manager ): void {
        require_once TKR_PLUGIN_DIR . 'includes/elementor/class-elementor-widget.php';
        $manager->register( new TKR_Elementor_Widget() );
    }
}
