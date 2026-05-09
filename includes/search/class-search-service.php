<?php
/**
 * Search service.
 *
 * @package Tierarztkostenrechner
 */

defined( 'ABSPATH' ) || exit;

class TKR_Search_Service {
	private TKR_Lookup_Repository $repository;
	private TKR_Normalizer $normalizer;

	public function __construct( TKR_Lookup_Repository $repository, TKR_Normalizer $normalizer ) {
		$this->repository = $repository;
		$this->normalizer = $normalizer;
	}

	/** @return array<int,array<string,mixed>> */
	public function search( string $query, string $animal_uid = '', string $subgroup_uid = '', int $limit = 10 ): array {
		$normalized = $this->normalizer->normalize( $query );
		if ( strlen( $normalized ) < 2 ) {
			return array();
		}

		$rows = $this->repository->search_terms( $normalized, $animal_uid, $subgroup_uid, $limit * 2 );
		foreach ( $rows as &$row ) {
			$row['score'] = $this->score( $normalized, (string) $row['term_normalized'], (int) $row['priority'], (string) $row['treatment_uid'] );
		}
		unset( $row );

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return ( $b['score'] <=> $a['score'] ) ?: strcmp( (string) $a['term_de'], (string) $b['term_de'] );
			}
		);

		return array_slice( $rows, 0, max( 1, min( 50, $limit ) ) );
	}

	private function score( string $query, string $term, int $priority, string $treatment_uid ): int {
		$score = $priority;
		if ( $query === $term ) {
			$score += 100;
		} elseif ( 0 === strpos( $term, $query ) ) {
			$score += 50;
		}
		if ( 'no_treatment' !== $treatment_uid ) {
			$score += 25;
		}
		return $score;
	}
}
