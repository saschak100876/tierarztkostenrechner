<?php
/**
 * GOT fee calculator.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calculates GOT ranges.
 */
class TKR_Calculator {
	/**
	 * Calculate range from selected treatment service rows and a fee rule.
	 *
	 * @param array<int,array<string,mixed>> $items Treatment service rows.
	 * @param array<string,mixed>           $rule Fee rule row.
	 * @param array<string,mixed>           $input Request context.
	 * @return array<string,mixed>
	 */
	public function calculate( array $items, array $rule, array $input = array() ): array {
		$selected_uids = isset( $input['selected_map_uids'] ) && is_array( $input['selected_map_uids'] ) ? array_map( 'sanitize_key', $input['selected_map_uids'] ) : array();
		$sex           = isset( $input['sex'] ) ? sanitize_key( (string) $input['sex'] ) : 'unknown';
		$factor_min    = (float) $rule['factor_min'];
		$factor_max    = (float) $rule['factor_max'];
		$fixed_fee     = (float) $rule['fixed_fee'];
		$sum_1x        = 0.0;
		$output_items  = array();

		foreach ( $items as $item ) {
			$map_uid   = sanitize_key( (string) $item['map_uid'] );
			$is_note   = 'note' === (string) $item['item_type'];
			$selected  = empty( $selected_uids ) ? (bool) $item['is_default'] : in_array( $map_uid, $selected_uids, true );
			$condition = (string) $item['condition_key'];

			if ( $selected && ! $this->condition_matches( $condition, $sex ) ) {
				$selected = false;
			}

			$fee_1x = $is_note ? 0.0 : (float) ( $item['fee_1x'] ?? 0 );
			if ( $selected ) {
				$sum_1x += $fee_1x;
			}

			$output_items[] = array(
				'map_uid'      => $map_uid,
				'service_uid'  => sanitize_key( (string) $item['service_uid'] ),
				'item_type'    => sanitize_key( (string) $item['item_type'] ),
				'got_number'   => isset( $item['got_number'] ) ? (int) $item['got_number'] : null,
				'label_de'     => $is_note ? (string) $item['user_note_de'] : (string) ( $item['service_label_de'] ?? '' ),
				'fee_1x'       => round( $fee_1x, 2 ),
				'selected'     => $selected,
				'is_required'  => (bool) $item['is_required'],
				'user_note_de' => (string) $item['user_note_de'],
			);
		}

		$total_min = ( $sum_1x * $factor_min ) + $fixed_fee;
		$total_max = ( $sum_1x * $factor_max ) + $fixed_fee;

		return array(
			'range'   => array(
				'factor_min' => $factor_min,
				'factor_max' => $factor_max,
				'total_min'  => round( $total_min, 2 ),
				'total_max'  => round( $total_max, 2 ),
				'fixed_fee'  => round( $fixed_fee, 2 ),
			),
			'items'   => $output_items,
			'notices' => $this->notices( ! empty( $rule['is_emergency'] ), $fixed_fee ),
		);
	}

	private function condition_matches( string $condition, string $sex ): bool {
		if ( '' === $condition || 'no_condition' === $condition ) {
			return true;
		}
		if ( 0 === strpos( $condition, 'sex=' ) ) {
			return substr( $condition, 4 ) === $sex;
		}
		return true;
	}

	/** @return array<int,string> */
	private function notices( bool $is_emergency, float $fixed_fee ): array {
		$notices = array(
			__( 'Der Rechner zeigt eine unverbindliche Orientierung auf Basis der GOT und ersetzt keine tierärztliche Beratung.', 'tierarztkostenrechner' ),
			__( 'Medikamente, Verbrauchsmaterial, Laborleistungen, Bildgebung, Wegegeld und Besonderheiten des Einzelfalls können zusätzlich anfallen.', 'tierarztkostenrechner' ),
			__( 'Die Ausgabe ist keine Diagnose, keine Rechtsberatung und keine verbindliche Kostenzusage.', 'tierarztkostenrechner' ),
		);
		if ( $is_emergency && $fixed_fee > 0 ) {
			$notices[] = __( 'Die Notdienstgebühr wird einmalig pro Angelegenheit berücksichtigt und nicht je Einzelposition multipliziert.', 'tierarztkostenrechner' );
		}
		return $notices;
	}
}
