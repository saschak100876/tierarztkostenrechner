<?php
/**
 * CSV import validator.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

class TKR_Validator {
	/** @var array<string,array<int,string>> */
	private array $required = array(
		'animals'            => array( 'animal_uid', 'animal_label_de', 'animal_slug', 'animal_group', 'has_subgroups', 'has_sex_options', 'is_active', 'sort_order', 'notes' ),
		'animal_subgroups'   => array( 'subgroup_uid', 'animal_uid', 'subgroup_label_de', 'subgroup_slug', 'got_scope_terms', 'has_direct_got_hits', 'is_active', 'sort_order', 'notes' ),
		'got_services'       => array( 'service_uid', 'got_number', 'got_part', 'service_original_de', 'service_label_de', 'fee_1x', 'animal_scope_raw', 'animal_uids', 'subgroup_uids', 'sex_scope', 'is_general', 'is_active', 'notes' ),
		'fee_rules'          => array( 'rule_uid', 'rule_label_de', 'rule_slug', 'factor_min', 'factor_max', 'fixed_fee', 'is_emergency', 'is_active', 'sort_order', 'legal_basis', 'notes' ),
		'treatments'         => array( 'treatment_uid', 'animal_uid', 'subgroup_uid', 'treatment_label_de', 'treatment_slug', 'requires_search', 'requires_sex', 'is_active', 'sort_order', 'notes' ),
		'treatment_services' => array( 'map_uid', 'treatment_uid', 'service_uid', 'item_type', 'role', 'is_default', 'is_required', 'condition_key', 'sort_order', 'user_note_de' ),
		'search_terms'       => array( 'search_uid', 'term_de', 'term_normalized', 'term_type', 'animal_uid', 'subgroup_uid', 'treatment_uid', 'service_uid', 'priority', 'is_active', 'notes' ),
	);

	/** @return array<string,mixed> */
	public function validate( string $sheet, array $rows ): array {
		$errors = array();
		$warnings = array();
		if ( ! isset( $this->required[ $sheet ] ) ) {
			return array( 'valid' => false, 'errors' => array( __( 'Unbekanntes Import-Sheet.', 'tierarztkostenrechner' ) ), 'warnings' => array() );
		}

		$uid_field = $this->uid_field( $sheet );
		$seen = array();
		foreach ( $rows as $index => $row ) {
			$line = $index + 2;
			foreach ( $this->required[ $sheet ] as $column ) {
				if ( ! array_key_exists( $column, $row ) || '' === trim( (string) $row[ $column ] ) ) {
					$errors[] = sprintf( 'Zeile %d: Pflichtfeld %s fehlt oder ist leer.', $line, $column );
				}
			}
			if ( isset( $row[ $uid_field ] ) ) {
				$uid = (string) $row[ $uid_field ];
				if ( isset( $seen[ $uid ] ) ) {
					$errors[] = sprintf( 'Zeile %d: UID %s ist doppelt.', $line, $uid );
				}
				$seen[ $uid ] = true;
			}
			$this->validate_types( $sheet, $row, $line, $errors );
		}

		return array( 'valid' => empty( $errors ), 'errors' => $errors, 'warnings' => $warnings );
	}

	/** @return array<int,string> */
	public function required_columns( string $sheet ): array {
		return $this->required[ $sheet ] ?? array();
	}

	private function uid_field( string $sheet ): string {
		return array( 'animals' => 'animal_uid', 'animal_subgroups' => 'subgroup_uid', 'got_services' => 'service_uid', 'fee_rules' => 'rule_uid', 'treatments' => 'treatment_uid', 'treatment_services' => 'map_uid', 'search_terms' => 'search_uid' )[ $sheet ];
	}

	private function validate_types( string $sheet, array $row, int $line, array &$errors ): void {
		foreach ( $row as $key => $value ) {
			if ( in_array( $key, array( 'has_subgroups', 'has_sex_options', 'is_active', 'has_direct_got_hits', 'is_general', 'is_emergency', 'requires_search', 'requires_sex', 'is_default', 'is_required' ), true ) && ! in_array( (string) $value, array( '0', '1' ), true ) ) {
				$errors[] = sprintf( 'Zeile %d: %s muss 0 oder 1 sein.', $line, $key );
			}
		}
		if ( 'got_services' === $sheet ) {
			if ( isset( $row['got_part'] ) && ! in_array( (string) $row['got_part'], array( 'A', 'B', 'C' ), true ) ) { $errors[] = sprintf( 'Zeile %d: got_part muss A, B oder C sein.', $line ); }
			if ( isset( $row['fee_1x'] ) && ( ! is_numeric( $row['fee_1x'] ) || (float) $row['fee_1x'] < 0 ) ) { $errors[] = sprintf( 'Zeile %d: fee_1x muss numerisch und >= 0 sein.', $line ); }
		}
		if ( 'treatment_services' === $sheet && isset( $row['item_type'] ) && ! in_array( (string) $row['item_type'], array( 'service', 'note' ), true ) ) { $errors[] = sprintf( 'Zeile %d: item_type muss service oder note sein.', $line ); }
		if ( 'search_terms' === $sheet && isset( $row['term_type'] ) && ! in_array( (string) $row['term_type'], array( 'synonym', 'symptom', 'breed', 'species', 'lay_term', 'spelling_variant', 'got_term', 'treatment' ), true ) ) { $errors[] = sprintf( 'Zeile %d: term_type ist nicht erlaubt.', $line ); }
	}
}
