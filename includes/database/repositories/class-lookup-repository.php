<?php
/**
 * Lookup repository.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads calculator lookup data.
 */
class TKR_Lookup_Repository extends TKR_Repository {
	/** @return array<int,array<string,mixed>> */
	public function get_animals(): array {
		$table = $this->tables['animals'];
		return $this->wpdb->get_results( "SELECT * FROM $table WHERE is_active = 1 ORDER BY sort_order ASC, animal_label_de ASC", ARRAY_A ) ?: array();
	}

	/** @return array<int,array<string,mixed>> */
	public function get_subgroups( string $animal_uid ): array {
		$table = $this->tables['animal_subgroups'];
		return $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM $table WHERE is_active = 1 AND animal_uid = %s ORDER BY sort_order ASC, subgroup_label_de ASC", $this->uid( $animal_uid ) ), ARRAY_A ) ?: array();
	}

	/** @return array<int,array<string,mixed>> */
	public function get_fee_rules(): array {
		$table = $this->tables['fee_rules'];
		return $this->wpdb->get_results( "SELECT * FROM $table WHERE is_active = 1 ORDER BY sort_order ASC, rule_label_de ASC", ARRAY_A ) ?: array();
	}

	/** @return array<string,mixed>|null */
	public function get_fee_rule( string $rule_uid ): ?array {
		$table = $this->tables['fee_rules'];
		$row   = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $table WHERE is_active = 1 AND rule_uid = %s", $this->uid( $rule_uid ) ), ARRAY_A );
		return $row ?: null;
	}

	/** @return array<int,array<string,mixed>> */
	public function get_treatments( string $animal_uid, string $subgroup_uid = 'no_subgroup' ): array {
		$table        = $this->tables['treatments'];
		$animal_uid   = $this->uid( $animal_uid );
		$subgroup_uid = $this->uid( $subgroup_uid ?: 'no_subgroup' );
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM $table WHERE is_active = 1 AND animal_uid = %s AND (subgroup_uid = %s OR subgroup_uid = 'no_subgroup') ORDER BY requires_search DESC, sort_order ASC, treatment_label_de ASC",
				$animal_uid,
				$subgroup_uid
			),
			ARRAY_A
		) ?: array();
	}

	/** @return array<string,mixed>|null */
	public function get_treatment( string $treatment_uid ): ?array {
		$table = $this->tables['treatments'];
		$row   = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $table WHERE is_active = 1 AND treatment_uid = %s", $this->uid( $treatment_uid ) ), ARRAY_A );
		return $row ?: null;
	}

	/** @return array<int,array<string,mixed>> */
	public function get_treatment_services( string $treatment_uid ): array {
		$maps     = $this->tables['treatment_services'];
		$services = $this->tables['got_services'];
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT m.*, s.got_number, s.got_part, s.service_label_de, s.fee_1x, s.sex_scope
				 FROM $maps m
				 LEFT JOIN $services s ON s.service_uid = m.service_uid AND s.is_active = 1
				 WHERE m.treatment_uid = %s
				 ORDER BY m.sort_order ASC",
				$this->uid( $treatment_uid )
			),
			ARRAY_A
		) ?: array();
	}

	/** @return array<int,array<string,mixed>> */
	public function search_terms( string $normalized_query, string $animal_uid = '', string $subgroup_uid = '', int $limit = 10 ): array {
		$table    = $this->tables['search_terms'];
		$like     = '%' . $this->wpdb->esc_like( $normalized_query ) . '%';
		$animal   = $this->uid( $animal_uid );
		$subgroup = $this->uid( $subgroup_uid ?: 'no_subgroup' );
		$where    = "is_active = 1 AND term_normalized LIKE %s";
		$params   = array( $like );

		if ( $animal ) {
			$where   .= " AND (animal_uid = %s OR animal_uid = 'no_animal')";
			$params[] = $animal;
		}
		if ( $subgroup && 'no_subgroup' !== $subgroup ) {
			$where   .= " AND (subgroup_uid = %s OR subgroup_uid = 'no_subgroup')";
			$params[] = $subgroup;
		}

		$params[] = max( 1, min( 50, $limit ) );
		$sql      = "SELECT * FROM $table WHERE $where ORDER BY priority DESC, term_de ASC LIMIT %d";
		return $this->wpdb->get_results( $this->wpdb->prepare( $sql, $params ), ARRAY_A ) ?: array();
	}
}
