<?php
defined( 'ABSPATH' ) || exit;

class TKR_Search_Service {

    private TKR_Search_Terms_Repository $repo;
    private TKR_Treatments_Repository   $treatment_repo;

    public function __construct() {
        $this->repo           = new TKR_Search_Terms_Repository();
        $this->treatment_repo = new TKR_Treatments_Repository();
    }

    public function search( string $raw_query, string $animal_uid = '', string $subgroup_uid = '', int $limit = 15 ): array {
        $normalized = TKR_Normalizer::normalize( $raw_query );
        if ( mb_strlen( $normalized ) < 2 ) return [];

        $rows = $this->repo->search( $normalized, $animal_uid, $subgroup_uid, $limit * 2 );
        if ( empty( $rows ) ) return [];

        // Deduplicate: prefer treatment hits, then service hits, then other
        $by_treatment = [];
        $by_service   = [];
        $other        = [];

        foreach ( $rows as $row ) {
            $t_uid = $row['treatment_uid'] ?? '';
            $s_uid = $row['service_uid']   ?? '';

            if ( $t_uid && $t_uid !== 'no_treatment' ) {
                if ( ! isset( $by_treatment[ $t_uid ] ) || (int) $row['priority'] > (int) $by_treatment[ $t_uid ]['priority'] ) {
                    $by_treatment[ $t_uid ] = $row;
                }
            } elseif ( $s_uid && $s_uid !== 'no_service' ) {
                if ( ! isset( $by_service[ $s_uid ] ) ) {
                    $by_service[ $s_uid ] = $row;
                }
            } else {
                $other[] = $row;
            }
        }

        $combined = array_values( $by_treatment );
        foreach ( array_values( $by_service ) as $row ) {
            $combined[] = $row;
        }
        foreach ( $other as $row ) {
            $combined[] = $row;
        }

        usort( $combined, fn( $a, $b ) => (int) $b['priority'] - (int) $a['priority'] );

        $results = [];
        foreach ( array_slice( $combined, 0, $limit ) as $row ) {
            $t_uid = ( $row['treatment_uid'] ?? '' ) !== 'no_treatment' ? ( $row['treatment_uid'] ?? '' ) : null;
            $label = sanitize_text_field( $row['term_de'] );

            if ( $t_uid ) {
                $treatment = $this->treatment_repo->get_by_uid( $t_uid );
                if ( $treatment ) {
                    $label = sanitize_text_field( $treatment['treatment_label_de'] );
                }
            }

            $results[] = [
                'search_uid'    => sanitize_text_field( $row['search_uid'] ),
                'term_de'       => sanitize_text_field( $row['term_de'] ),
                'label'         => $label,
                'term_type'     => sanitize_text_field( $row['term_type'] ),
                'treatment_uid' => $t_uid ? sanitize_text_field( $t_uid ) : null,
                'service_uid'   => ( ( $row['service_uid'] ?? '' ) !== 'no_service' ) ? sanitize_text_field( $row['service_uid'] ) : null,
                'animal_uid'    => ( ( $row['animal_uid'] ?? '' ) !== 'no_animal' ) ? sanitize_text_field( $row['animal_uid'] ) : null,
                'priority'      => (int) $row['priority'],
            ];
        }

        return $results;
    }
}
