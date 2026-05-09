<?php
/**
 * Search normalizer.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

class TKR_Normalizer {
	public function normalize( string $value ): string {
		$value = strtolower( remove_accents( wp_strip_all_tags( $value ) ) );
		$value = preg_replace( '/[^a-z0-9äöüß\s-]/u', ' ', $value );
		$value = str_replace( array( 'ä', 'ö', 'ü', 'ß' ), array( 'ae', 'oe', 'ue', 'ss' ), $value );
		$value = preg_replace( '/\s+/', ' ', (string) $value );
		return trim( (string) $value );
	}
}
