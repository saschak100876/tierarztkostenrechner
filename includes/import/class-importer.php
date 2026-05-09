<?php
/**
 * CSV importer.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

class TKR_Importer {
	private TKR_Validator $validator;

	public function __construct( TKR_Validator $validator ) { $this->validator = $validator; }

	/** @return array<string,mixed> */
	public function import_csv( string $sheet, string $file, bool $dry_run = true ): array {
		$rows = $this->read_csv( $file );
		$validation = $this->validator->validate( $sheet, $rows );
		$report = array( 'sheet' => $sheet, 'rows' => count( $rows ), 'created' => 0, 'updated' => 0, 'dry_run' => $dry_run, 'validation' => $validation );
		if ( ! $validation['valid'] || $dry_run ) { $this->store_report( basename( $file ), $dry_run, $validation['valid'] ? 'dry_run' : 'failed', $report ); return $report; }

		global $wpdb;
		$tables = TKR_Schema::tables();
		$table = $tables[ $sheet ] ?? '';
		$uid_field = $this->uid_field( $sheet );
		$wpdb->query( 'START TRANSACTION' );
		try {
			foreach ( $rows as $row ) {
				$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $uid_field = %s", $row[ $uid_field ] ) );
				$data = $this->sanitize_row( $row );
				if ( $exists ) { $wpdb->update( $table, $data, array( $uid_field => $row[ $uid_field ] ) ); $report['updated']++; }
				else { $wpdb->insert( $table, $data ); $report['created']++; }
			}
			$wpdb->query( 'COMMIT' );
			$this->store_report( basename( $file ), false, 'success', $report );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			$report['validation']['valid'] = false;
			$report['validation']['errors'][] = $e->getMessage();
			$this->store_report( basename( $file ), false, 'failed', $report );
		}
		return $report;
	}

	/** @return array<int,array<string,string>> */
	private function read_csv( string $file ): array {
		$handle = fopen( $file, 'r' );
		if ( false === $handle ) { return array(); }
		$header = fgetcsv( $handle, 0, ',' );
		$rows = array();
		while ( ( $data = fgetcsv( $handle, 0, ',' ) ) !== false ) {
			if ( ! is_array( $header ) ) { continue; }
			$rows[] = array_combine( $header, array_pad( $data, count( $header ), '' ) );
		}
		fclose( $handle );
		return $rows;
	}

	/** @return array<string,string> */
	private function sanitize_row( array $row ): array {
		$clean = array();
		foreach ( $row as $key => $value ) { $clean[ sanitize_key( $key ) ] = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : ''; }
		return $clean;
	}

	private function uid_field( string $sheet ): string { return array( 'animals' => 'animal_uid', 'animal_subgroups' => 'subgroup_uid', 'got_services' => 'service_uid', 'fee_rules' => 'rule_uid', 'treatments' => 'treatment_uid', 'treatment_services' => 'map_uid', 'search_terms' => 'search_uid' )[ $sheet ]; }

	private function store_report( string $source, bool $dry_run, string $status, array $report ): void {
		global $wpdb;
		$tables = TKR_Schema::tables();
		$wpdb->insert( $tables['import_reports'], array( 'created_at' => current_time( 'mysql' ), 'user_id' => get_current_user_id(), 'source_name' => sanitize_file_name( $source ), 'dry_run' => $dry_run ? 1 : 0, 'status' => sanitize_key( $status ), 'report_json' => wp_json_encode( $report ) ) );
	}
}
