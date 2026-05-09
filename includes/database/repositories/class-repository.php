<?php
/**
 * Base repository helpers.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared repository methods.
 */
abstract class TKR_Repository {
	/** @var wpdb */
	protected $wpdb;

	/** @var array<string,string> */
	protected $tables;

	public function __construct() {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->tables = TKR_Schema::tables();
	}

	/**
	 * Sanitize a UID-like value.
	 */
	protected function uid( string $uid ): string {
		return sanitize_key( $uid );
	}

	/**
	 * Cast DB row values for API output.
	 *
	 * @param object $row Database row.
	 * @return array<string,mixed>
	 */
	protected function row_to_array( $row ): array {
		return json_decode( wp_json_encode( $row ), true );
	}
}
