<?php
/**
 * Main plugin loader.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

require_once TKR_PLUGIN_DIR . 'includes/database/class-schema.php';
require_once TKR_PLUGIN_DIR . 'includes/database/repositories/class-repository.php';
require_once TKR_PLUGIN_DIR . 'includes/database/repositories/class-lookup-repository.php';
require_once TKR_PLUGIN_DIR . 'includes/calculator/class-calculator.php';
require_once TKR_PLUGIN_DIR . 'includes/search/class-normalizer.php';
require_once TKR_PLUGIN_DIR . 'includes/search/class-search-service.php';
require_once TKR_PLUGIN_DIR . 'includes/rest/class-rest-controller.php';
require_once TKR_PLUGIN_DIR . 'includes/import/class-validator.php';
require_once TKR_PLUGIN_DIR . 'includes/import/class-importer.php';
require_once TKR_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';

/**
 * Coordinates plugin services.
 */
final class TKR_Plugin {
	/** @var TKR_Plugin|null */
	private static $instance = null;

	/** @var TKR_Lookup_Repository */
	private $repository;

	/**
	 * Singleton accessor.
	 */
	public static function instance(): TKR_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		$this->repository = new TKR_Lookup_Repository();

		add_shortcode( 'tierarztkostenrechner', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		( new TKR_REST_Controller( $this->repository, new TKR_Calculator(), new TKR_Search_Service( $this->repository, new TKR_Normalizer() ) ) )->register();

		if ( is_admin() ) {
			( new TKR_Admin_Menu( new TKR_Importer( new TKR_Validator() ) ) )->register();
		}
	}

	/**
	 * Register frontend assets, enqueued only by shortcode/widget rendering.
	 */
	public function register_assets(): void {
		wp_register_style( 'tkr-frontend', TKR_PLUGIN_URL . 'assets/css/tkr-frontend.css', array(), TKR_VERSION );
		wp_register_style( 'tkr-theme-default', TKR_PLUGIN_URL . 'assets/css/tkr-theme-default.css', array( 'tkr-frontend' ), TKR_VERSION );
		wp_register_script( 'tkr-frontend', TKR_PLUGIN_URL . 'assets/js/tkr-frontend.js', array(), TKR_VERSION, true );
	}

	/**
	 * Render shortcode shell for the frontend app.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 */
	public function render_shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'default_animal'    => '',
				'default_treatment' => '',
				'layout'            => 'full',
			),
			$atts,
			'tierarztkostenrechner'
		);

		wp_enqueue_style( 'tkr-theme-default' );
		wp_enqueue_script( 'tkr-frontend' );
		wp_localize_script(
			'tkr-frontend',
			'TKR_RECHNER',
			array(
				'restUrl' => esc_url_raw( rest_url( 'tkr/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'loading'       => __( 'Daten werden geladen …', 'tierarztkostenrechner' ),
					'searchPrompt'  => __( 'Beschreiben Sie die Behandlung, ein Symptom oder einen Alltagsbegriff.', 'tierarztkostenrechner' ),
					'calculate'     => __( 'Kostenorientierung berechnen', 'tierarztkostenrechner' ),
					'choose'        => __( 'Bitte auswählen', 'tierarztkostenrechner' ),
					'unknown'       => __( 'Steht noch nicht fest', 'tierarztkostenrechner' ),
					'noResults'     => __( 'Keine passenden Treffer gefunden.', 'tierarztkostenrechner' ),
					'error'         => __( 'Die Anfrage konnte nicht verarbeitet werden.', 'tierarztkostenrechner' ),
				),
			)
		);

		$dataset = array(
			'default-animal'    => sanitize_key( (string) $atts['default_animal'] ),
			'default-treatment' => sanitize_key( (string) $atts['default_treatment'] ),
			'layout'            => sanitize_key( (string) $atts['layout'] ),
		);

		$attrs = '';
		foreach ( $dataset as $key => $value ) {
			$attrs .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		return '<div class="tkr-app"' . $attrs . '><noscript>' . esc_html__( 'Bitte aktivieren Sie JavaScript, um den Tierarztkostenrechner zu nutzen.', 'tierarztkostenrechner' ) . '</noscript></div>';
	}
}
