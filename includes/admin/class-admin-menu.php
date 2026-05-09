<?php
/**
 * Admin UI.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

class TKR_Admin_Menu {
	private TKR_Importer $importer;

	public function __construct( TKR_Importer $importer ) { $this->importer = $importer; }

	public function register(): void { add_action( 'admin_menu', array( $this, 'add_menu' ) ); }

	public function add_menu(): void {
		add_menu_page( __( 'Tierarztkostenrechner', 'tierarztkostenrechner' ), __( 'Tierarztkosten', 'tierarztkostenrechner' ), 'manage_options', 'tkr-dashboard', array( $this, 'dashboard' ), 'dashicons-calculator', 58 );
		add_submenu_page( 'tkr-dashboard', __( 'Import', 'tierarztkostenrechner' ), __( 'Import', 'tierarztkostenrechner' ), 'manage_options', 'tkr-import', array( $this, 'import_page' ) );
	}

	public function dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb;
		$tables = TKR_Schema::tables();
		echo '<div class="wrap"><h1>' . esc_html__( 'Tierarztkostenrechner', 'tierarztkostenrechner' ) . '</h1>';
		echo '<p>' . esc_html__( 'Status der Datenbasis und Plugin-Tabellen.', 'tierarztkostenrechner' ) . '</p><table class="widefat striped"><tbody>';
		echo '<tr><th>' . esc_html__( 'Schema-Version', 'tierarztkostenrechner' ) . '</th><td>' . esc_html( get_option( 'tkr_db_version', '—' ) ) . '</td></tr>';
		foreach ( $tables as $key => $table ) {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
			echo '<tr><th>' . esc_html( $key ) . '</th><td>' . esc_html( (string) $count ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public function import_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$report = null;
		if ( isset( $_POST['tkr_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tkr_import_nonce'] ) ), 'tkr_import' ) && ! empty( $_FILES['tkr_csv']['tmp_name'] ) ) {
			$sheet = sanitize_key( (string) ( $_POST['tkr_sheet'] ?? '' ) );
			$dry_run = empty( $_POST['tkr_commit'] );
			$report = $this->importer->import_csv( $sheet, sanitize_text_field( $_FILES['tkr_csv']['tmp_name'] ), $dry_run );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Masterdaten importieren', 'tierarztkostenrechner' ) . '</h1>';
		echo '<p>' . esc_html__( 'CSV je Sheet hochladen. Ein Dry Run validiert die Datei ohne Schreibvorgang.', 'tierarztkostenrechner' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'tkr_import', 'tkr_import_nonce' );
		echo '<table class="form-table"><tr><th><label for="tkr_sheet">' . esc_html__( 'Sheet', 'tierarztkostenrechner' ) . '</label></th><td><select name="tkr_sheet" id="tkr_sheet">';
		foreach ( array( 'animals', 'animal_subgroups', 'got_services', 'fee_rules', 'treatments', 'treatment_services', 'search_terms' ) as $sheet ) { echo '<option value="' . esc_attr( $sheet ) . '">' . esc_html( $sheet ) . '</option>'; }
		echo '</select></td></tr><tr><th><label for="tkr_csv">CSV</label></th><td><input type="file" name="tkr_csv" id="tkr_csv" accept=".csv,text/csv" required></td></tr>';
		echo '<tr><th>' . esc_html__( 'Schreiben', 'tierarztkostenrechner' ) . '</th><td><label><input type="checkbox" name="tkr_commit" value="1"> ' . esc_html__( 'Import ausführen (nicht nur Dry Run)', 'tierarztkostenrechner' ) . '</label></td></tr></table>';
		submit_button( __( 'Import prüfen / starten', 'tierarztkostenrechner' ) );
		echo '</form>';

		if ( $report ) {
			echo '<h2>' . esc_html__( 'Importbericht', 'tierarztkostenrechner' ) . '</h2><pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;white-space:pre-wrap;">' . esc_html( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';
		}
		echo '</div>';
	}
}
