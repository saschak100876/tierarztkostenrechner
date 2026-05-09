<?php
/**
 * Database schema management.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates custom plugin tables.
 */
class TKR_Schema {
	/**
	 * Activation callback.
	 */
	public static function activate(): void {
		self::create_tables();
		update_option( 'tkr_db_version', TKR_DB_VERSION );
	}

	/**
	 * Table names keyed by logical entity.
	 *
	 * @return array<string,string>
	 */
	public static function tables(): array {
		global $wpdb;
		return array(
			'animals'            => $wpdb->prefix . 'tkr_animals',
			'animal_subgroups'   => $wpdb->prefix . 'tkr_animal_subgroups',
			'got_services'       => $wpdb->prefix . 'tkr_got_services',
			'fee_rules'          => $wpdb->prefix . 'tkr_fee_rules',
			'treatments'         => $wpdb->prefix . 'tkr_treatments',
			'treatment_services' => $wpdb->prefix . 'tkr_treatment_services',
			'search_terms'       => $wpdb->prefix . 'tkr_search_terms',
			'import_reports'     => $wpdb->prefix . 'tkr_import_reports',
		);
	}

	/**
	 * Create or update tables via dbDelta.
	 */
	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$tables  = self::tables();

		$sql = array();
		$sql[] = "CREATE TABLE {$tables['animals']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			animal_uid VARCHAR(64) NOT NULL,
			animal_label_de VARCHAR(120) NOT NULL,
			animal_slug VARCHAR(120) NOT NULL,
			animal_group VARCHAR(50) NOT NULL,
			has_subgroups TINYINT(1) NOT NULL DEFAULT 0,
			has_sex_options TINYINT(1) NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY animal_uid (animal_uid),
			KEY is_active_sort (is_active, sort_order)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['animal_subgroups']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subgroup_uid VARCHAR(64) NOT NULL,
			animal_uid VARCHAR(64) NOT NULL,
			subgroup_label_de VARCHAR(120) NOT NULL,
			subgroup_slug VARCHAR(120) NOT NULL,
			got_scope_terms TEXT NOT NULL,
			has_direct_got_hits TINYINT(1) NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY subgroup_uid (subgroup_uid),
			KEY animal_active_sort (animal_uid, is_active, sort_order)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['got_services']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			service_uid VARCHAR(64) NOT NULL,
			got_number INT NOT NULL,
			got_part CHAR(1) NOT NULL,
			service_original_de TEXT NOT NULL,
			service_label_de TEXT NOT NULL,
			fee_1x DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			animal_scope_raw TEXT NOT NULL,
			animal_uids TEXT NOT NULL,
			subgroup_uids TEXT NOT NULL,
			sex_scope VARCHAR(20) NOT NULL DEFAULT 'any',
			is_general TINYINT(1) NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY service_uid (service_uid),
			KEY got_number (got_number),
			KEY is_active (is_active)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['fee_rules']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rule_uid VARCHAR(64) NOT NULL,
			rule_label_de VARCHAR(160) NOT NULL,
			rule_slug VARCHAR(120) NOT NULL,
			factor_min DECIMAL(4,2) NOT NULL DEFAULT 1.00,
			factor_max DECIMAL(4,2) NOT NULL DEFAULT 3.00,
			fixed_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			is_emergency TINYINT(1) NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			legal_basis VARCHAR(120) NOT NULL,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY rule_uid (rule_uid),
			KEY active_sort (is_active, sort_order)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['treatments']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			treatment_uid VARCHAR(96) NOT NULL,
			animal_uid VARCHAR(64) NOT NULL,
			subgroup_uid VARCHAR(64) NOT NULL,
			treatment_label_de VARCHAR(180) NOT NULL,
			treatment_slug VARCHAR(160) NOT NULL,
			requires_search TINYINT(1) NOT NULL DEFAULT 0,
			requires_sex TINYINT(1) NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY treatment_uid (treatment_uid),
			KEY animal_subgroup_active_sort (animal_uid, subgroup_uid, is_active, sort_order)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['treatment_services']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			map_uid VARCHAR(128) NOT NULL,
			treatment_uid VARCHAR(96) NOT NULL,
			service_uid VARCHAR(64) NOT NULL,
			item_type VARCHAR(20) NOT NULL,
			role VARCHAR(50) NOT NULL,
			is_default TINYINT(1) NOT NULL DEFAULT 1,
			is_required TINYINT(1) NOT NULL DEFAULT 0,
			condition_key VARCHAR(120) NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			user_note_de TEXT NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY map_uid (map_uid),
			KEY treatment_sort (treatment_uid, sort_order),
			KEY service_uid (service_uid)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['search_terms']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			search_uid VARCHAR(128) NOT NULL,
			term_de VARCHAR(255) NOT NULL,
			term_normalized VARCHAR(255) NOT NULL,
			term_type VARCHAR(40) NOT NULL,
			animal_uid VARCHAR(64) NOT NULL,
			subgroup_uid VARCHAR(64) NOT NULL,
			treatment_uid VARCHAR(96) NOT NULL,
			service_uid VARCHAR(64) NOT NULL,
			priority INT NOT NULL DEFAULT 50,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY search_uid (search_uid),
			KEY term_normalized (term_normalized(191)),
			KEY animal_active_priority (animal_uid, is_active, priority)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['import_reports']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			source_name VARCHAR(255) NOT NULL,
			dry_run TINYINT(1) NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL,
			report_json LONGTEXT NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
