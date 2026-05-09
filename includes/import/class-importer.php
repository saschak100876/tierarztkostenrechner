<?php
defined( 'ABSPATH' ) || exit;

class TKR_Importer {

    private TKR_Validator $validator;
    private array $report = [
        'timestamp'  => '',
        'user_id'    => 0,
        'dry_run'    => false,
        'errors'     => [],
        'warnings'   => [],
        'counts'     => [],
    ];

    public function __construct() {
        $this->validator = new TKR_Validator();
    }

    public function get_report(): array {
        return $this->report;
    }

    /**
     * Import from an array of sheets (each sheet = array of assoc rows).
     * Set $dry_run = true to validate without writing.
     */
    public function import( array $sheets, bool $dry_run = false ): bool {
        global $wpdb;

        $this->report['timestamp'] = current_time( 'mysql' );
        $this->report['user_id']   = get_current_user_id();
        $this->report['dry_run']   = $dry_run;
        $this->report['errors']    = [];
        $this->report['warnings']  = [];
        $this->report['counts']    = [];

        $valid = $this->validator->validate_all( $sheets );
        $this->report['errors']   = $this->validator->get_errors();
        $this->report['warnings'] = $this->validator->get_warnings();

        if ( ! $valid ) {
            return false;
        }

        if ( $dry_run ) {
            foreach ( $sheets as $sheet => $rows ) {
                $this->report['counts'][ $sheet ] = count( $rows );
            }
            return true;
        }

        $wpdb->query( 'START TRANSACTION' );

        try {
            $this->report['counts']['animals']            = $this->upsert_animals( $sheets['animals'] );
            $this->report['counts']['animal_subgroups']   = $this->upsert_subgroups( $sheets['animal_subgroups'] );
            $this->report['counts']['got_services']       = $this->upsert_got_services( $sheets['got_services'] );
            $this->report['counts']['fee_rules']          = $this->upsert_fee_rules( $sheets['fee_rules'] );
            $this->report['counts']['treatments']         = $this->upsert_treatments( $sheets['treatments'] );
            $this->report['counts']['treatment_services'] = $this->upsert_treatment_services( $sheets['treatment_services'] );
            $this->report['counts']['search_terms']       = $this->upsert_search_terms( $sheets['search_terms'] );

            $wpdb->query( 'COMMIT' );
            update_option( 'tkr_last_import', $this->report );
            return true;
        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            $this->report['errors'][] = 'Datenbankfehler: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * Parse a CSV file into sheets. Expects files as [ 'sheet_name' => '/path/to/file.csv' ].
     */
    public static function parse_csv_files( array $file_paths ): array {
        $sheets = [];
        foreach ( $file_paths as $sheet => $path ) {
            if ( ! file_exists( $path ) ) {
                continue;
            }
            $handle = fopen( $path, 'r' );
            if ( ! $handle ) continue;

            $headers = null;
            $rows    = [];
            while ( ( $line = fgetcsv( $handle, 0, ',' ) ) !== false ) {
                if ( null === $headers ) {
                    $headers = array_map( 'trim', $line );
                    continue;
                }
                if ( count( $line ) !== count( $headers ) ) continue;
                $rows[] = array_combine( $headers, $line );
            }
            fclose( $handle );
            $sheets[ $sheet ] = $rows;
        }
        return $sheets;
    }

    private function upsert_animals( array $rows ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'tkr_animals';
        $count = 0;
        foreach ( $rows as $row ) {
            $wpdb->replace( $table, [
                'animal_uid'      => sanitize_text_field( $row['animal_uid'] ),
                'animal_label_de' => sanitize_text_field( $row['animal_label_de'] ),
                'animal_slug'     => sanitize_title( $row['animal_slug'] ),
                'animal_group'    => sanitize_text_field( $row['animal_group'] ),
                'has_subgroups'   => (int) $row['has_subgroups'],
                'has_sex_options' => (int) $row['has_sex_options'],
                'is_active'       => (int) $row['is_active'],
                'sort_order'      => (int) $row['sort_order'],
                'notes'           => sanitize_textarea_field( $row['notes'] ?? TKR_Validator::PLACEHOLDER_NO_NOTE ),
            ] );
            $count++;
        }
        return $count;
    }

    private function upsert_subgroups( array $rows ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'tkr_animal_subgroups';
        $count = 0;
        foreach ( $rows as $row ) {
            $wpdb->replace( $table, [
                'subgroup_uid'        => sanitize_text_field( $row['subgroup_uid'] ),
                'animal_uid'          => sanitize_text_field( $row['animal_uid'] ),
                'subgroup_label_de'   => sanitize_text_field( $row['subgroup_label_de'] ),
                'subgroup_slug'       => sanitize_title( $row['subgroup_slug'] ),
                'got_scope_terms'     => sanitize_textarea_field( $row['got_scope_terms'] ),
                'has_direct_got_hits' => (int) $row['has_direct_got_hits'],
                'is_active'           => (int) $row['is_active'],
                'sort_order'          => (int) $row['sort_order'],
                'notes'               => sanitize_textarea_field( $row['notes'] ?? TKR_Validator::PLACEHOLDER_NO_NOTE ),
            ] );
            $count++;
        }
        return $count;
    }

    private function upsert_got_services( array $rows ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'tkr_got_services';
        $count = 0;
        foreach ( $rows as $row ) {
            $wpdb->replace( $table, [
                'service_uid'        => sanitize_text_field( $row['service_uid'] ),
                'got_number'         => (int) $row['got_number'],
                'got_part'           => sanitize_text_field( $row['got_part'] ),
                'service_original_de'=> sanitize_textarea_field( $row['service_original_de'] ),
                'service_label_de'   => sanitize_textarea_field( $row['service_label_de'] ),
                'fee_1x'             => (float) $row['fee_1x'],
                'animal_scope_raw'   => sanitize_textarea_field( $row['animal_scope_raw'] ),
                'animal_uids'        => sanitize_text_field( $row['animal_uids'] ),
                'subgroup_uids'      => sanitize_text_field( $row['subgroup_uids'] ),
                'sex_scope'          => sanitize_text_field( $row['sex_scope'] ),
                'is_general'         => (int) $row['is_general'],
                'is_active'          => (int) $row['is_active'],
                'notes'              => sanitize_textarea_field( $row['notes'] ?? TKR_Validator::PLACEHOLDER_NO_NOTE ),
            ] );
            $count++;
        }
        return $count;
    }

    private function upsert_fee_rules( array $rows ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'tkr_fee_rules';
        $count = 0;
        foreach ( $rows as $row ) {
            $wpdb->replace( $table, [
                'rule_uid'      => sanitize_text_field( $row['rule_uid'] ),
                'rule_label_de' => sanitize_text_field( $row['rule_label_de'] ),
                'rule_slug'     => sanitize_title( $row['rule_slug'] ),
                'factor_min'    => (float) $row['factor_min'],
                'factor_max'    => (float) $row['factor_max'],
                'fixed_fee'     => (float) $row['fixed_fee'],
                'is_emergency'  => (int) $row['is_emergency'],
                'is_active'     => (int) $row['is_active'],
                'sort_order'    => (int) $row['sort_order'],
                'legal_basis'   => sanitize_text_field( $row['legal_basis'] ),
                'notes'         => sanitize_textarea_field( $row['notes'] ?? TKR_Validator::PLACEHOLDER_NO_NOTE ),
            ] );
            $count++;
        }
        return $count;
    }

    private function upsert_treatments( array $rows ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'tkr_treatments';
        $count = 0;
        foreach ( $rows as $row ) {
            $wpdb->replace( $table, [
                'treatment_uid'      => sanitize_text_field( $row['treatment_uid'] ),
                'animal_uid'         => sanitize_text_field( $row['animal_uid'] ),
                'subgroup_uid'       => sanitize_text_field( $row['subgroup_uid'] ),
                'treatment_label_de' => sanitize_text_field( $row['treatment_label_de'] ),
                'treatment_slug'     => sanitize_title( $row['treatment_slug'] ),
                'requires_search'    => (int) $row['requires_search'],
                'requires_sex'       => (int) $row['requires_sex'],
                'is_active'          => (int) $row['is_active'],
                'sort_order'         => (int) $row['sort_order'],
                'notes'              => sanitize_textarea_field( $row['notes'] ?? TKR_Validator::PLACEHOLDER_NO_NOTE ),
            ] );
            $count++;
        }
        return $count;
    }

    private function upsert_treatment_services( array $rows ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'tkr_treatment_services';
        $count = 0;
        foreach ( $rows as $row ) {
            $wpdb->replace( $table, [
                'map_uid'       => sanitize_text_field( $row['map_uid'] ),
                'treatment_uid' => sanitize_text_field( $row['treatment_uid'] ),
                'service_uid'   => sanitize_text_field( $row['service_uid'] ),
                'item_type'     => sanitize_text_field( $row['item_type'] ),
                'role'          => sanitize_text_field( $row['role'] ),
                'is_default'    => (int) $row['is_default'],
                'is_required'   => (int) $row['is_required'],
                'condition_key' => sanitize_text_field( $row['condition_key'] ),
                'sort_order'    => (int) $row['sort_order'],
                'user_note_de'  => sanitize_textarea_field( $row['user_note_de'] ),
            ] );
            $count++;
        }
        return $count;
    }

    private function upsert_search_terms( array $rows ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'tkr_search_terms';
        $count = 0;
        foreach ( $rows as $row ) {
            $wpdb->replace( $table, [
                'search_uid'      => sanitize_text_field( $row['search_uid'] ),
                'term_de'         => sanitize_text_field( $row['term_de'] ),
                'term_normalized' => sanitize_text_field( $row['term_normalized'] ),
                'term_type'       => sanitize_text_field( $row['term_type'] ),
                'animal_uid'      => sanitize_text_field( $row['animal_uid'] ),
                'subgroup_uid'    => sanitize_text_field( $row['subgroup_uid'] ),
                'treatment_uid'   => sanitize_text_field( $row['treatment_uid'] ),
                'service_uid'     => sanitize_text_field( $row['service_uid'] ),
                'priority'        => (int) $row['priority'],
                'is_active'       => (int) $row['is_active'],
                'notes'           => sanitize_textarea_field( $row['notes'] ?? TKR_Validator::PLACEHOLDER_NO_NOTE ),
            ] );
            $count++;
        }
        return $count;
    }
}
