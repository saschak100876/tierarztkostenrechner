<?php
defined( 'ABSPATH' ) || exit;

class TKR_Search_Service {

    private TKR_Search_Terms_Repository $repo;
    private TKR_Treatments_Repository $treatment_repo;

    public function __construct() {
        $this->repo           = new TKR_Search_Terms_Repository();
        $this->treatment_repo = new TKR_Treatments_Repository();
    }

    /**
     * Searches and returns ranked results suitable for autocomplete/search display.
     */
    public function search( string $raw_query, string $animal_uid = '', string $subgroup_uid = '', int $limit = 15 ): array {
        $normalized = TKR_Normalizer::normalize( $raw_query );
        if ( mb_strlen( $normalized ) < 2 ) return [];

        $rows = $this->repo->search( $normalized, $animal_uid, $subgroup_uid, $limit * 2 );

        if ( empty( $rows ) ) return [];

        // De-duplicate: prefer treatment_uid hits; collapse rows pointing to same treatment
        $by_treatment = [];
        $by_service   = [];
        $other        = [];

        foreach ( $rows as $row ) {
            $t_uid = $row['treatment_uid'];
            $s_uid = $row['service_uid'];

            if ( $t_uid && $t_uid !== 'no_treatment' ) {
                if ( ! isset( $by_treatment[ $t_uid ] ) ) {
                    $by_treatment[ $t_uid ] = $row;
                } elseif ( (int) $row['priority'] > (int) $by_treatment[ $t_uid ]['priority'] ) {
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

        // Compose results: treatment hits first, then service-only, then other
        $combined = array_values( $by_treatment );
        foreach ( array_values( $by_service ) as $row ) {
            // Only add service hits when no treatment hits are present for same animal
            $combined[] = $row;
        }
        foreach ( $other as $row ) {
            $combined[] = $row;
        }

        // Sort by priority desc
        usort( $combined, fn( $a, $b ) => (int) $b['priority'] - (int) $a['priority'] );

        $results = [];
        foreach ( array_slice( $combined, 0, $limit ) as $row ) {
            $t_uid    = $row['treatment_uid'] !== 'no_treatment' ? $row['treatment_uid'] : null;
            $label    = $row['term_de'];

            // Fetch treatment label for better UX
            if ( $t_uid ) {
                $treatment = $this->treatment_repo->get_by_uid( $t_uid );
                if ( $treatment ) {
                    $label = $treatment['treatment_label_de'];
                }
            }

            $results[] = [
                'search_uid'    => $row['search_uid'],
                'term_de'       => $row['term_de'],
                'label'         => $label,
                'term_type'     => $row['term_type'],
                'treatment_uid' => $t_uid,
                'service_uid'   => $row['service_uid'] !== 'no_service' ? $row['service_uid'] : null,
                'animal_uid'    => $row['animal_uid'] !== 'no_animal' ? $row['animal_uid'] : null,
                'priority'      => (int) $row['priority'],
            ];
        }

        return $results;
    }
}
