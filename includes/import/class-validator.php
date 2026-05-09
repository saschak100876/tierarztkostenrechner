<?php
defined( 'ABSPATH' ) || exit;

class TKR_Validator {

    const ALLOWED_TERM_TYPES   = [ 'synonym', 'symptom', 'breed', 'species', 'lay_term', 'spelling_variant', 'got_term', 'treatment' ];
    const ALLOWED_ITEM_TYPES   = [ 'service', 'note' ];
    const ALLOWED_GOT_PARTS    = [ 'A', 'B', 'C' ];
    const ALLOWED_SEX_SCOPES   = [ 'any', 'male', 'female', 'unknown' ];
    const REQUIRED_SHEETS      = [ 'animals', 'animal_subgroups', 'got_services', 'fee_rules', 'treatments', 'treatment_services', 'search_terms' ];
    const PLACEHOLDER_NO_SUBGROUP  = 'no_subgroup';
    const PLACEHOLDER_NO_SERVICE   = 'no_service';
    const PLACEHOLDER_NO_ANIMAL    = 'no_animal';
    const PLACEHOLDER_NO_TREATMENT = 'no_treatment';
    const PLACEHOLDER_NO_CONDITION = 'no_condition';
    const PLACEHOLDER_NO_NOTE      = 'no_note';

    private array $errors   = [];
    private array $warnings = [];

    private array $animal_uids    = [];
    private array $subgroup_uids  = [];
    private array $service_uids   = [];
    private array $treatment_uids = [];

    public function get_errors(): array   { return $this->errors; }
    public function get_warnings(): array { return $this->warnings; }
    public function has_errors(): bool    { return ! empty( $this->errors ); }

    public function validate_all( array $sheets ): bool {
        $this->errors   = [];
        $this->warnings = [];

        // Check which required sheets are present
        $missing = [];
        foreach ( self::REQUIRED_SHEETS as $sheet ) {
            if ( ! array_key_exists( $sheet, $sheets ) ) {
                $missing[] = $sheet;
            }
        }
        if ( ! empty( $missing ) ) {
            foreach ( $missing as $m ) {
                $this->errors[] = sprintf( 'Pflicht-Sheet fehlt: „%s“. Bitte die entsprechende CSV-Datei hochladen.', $m );
            }
            return false;
        }

        // Validate each sheet
        $this->validate_animals( $sheets['animals'] );
        $this->validate_subgroups( $sheets['animal_subgroups'] );
        $this->validate_got_services( $sheets['got_services'] );
        $this->validate_fee_rules( $sheets['fee_rules'] );
        $this->validate_treatments( $sheets['treatments'] );
        $this->validate_treatment_services( $sheets['treatment_services'] );
        $this->validate_search_terms( $sheets['search_terms'] );

        return ! $this->has_errors();
    }

    // ---- Internal helpers ----

    private function err( string $sheet, int $line, string $msg ): void {
        $this->errors[] = sprintf( '[%s, Zeile %d] %s', $sheet, $line, $msg );
    }

    private function warn( string $sheet, int $line, string $msg ): void {
        $this->warnings[] = sprintf( '[%s, Zeile %d] %s', $sheet, $line, $msg );
    }

    private function require_fields( array $row, array $fields, string $sheet, int $line ): void {
        foreach ( $fields as $f ) {
            if ( ! isset( $row[ $f ] ) || trim( (string) $row[ $f ] ) === '' ) {
                $this->err( $sheet, $line, "Pflichtfeld „{$f}“ ist leer." );
            }
        }
    }

    private function require_bool( array $row, string $field, string $sheet, int $line ): void {
        if ( isset( $row[ $field ] ) && ! in_array( trim( (string) $row[ $field ] ), [ '0', '1' ], true ) ) {
            $this->err( $sheet, $line, "Feld „{$field}“ muss 0 oder 1 sein (Wert: „{$row[$field]}“)." );
        }
    }

    private function require_numeric_positive( array $row, string $field, string $sheet, int $line ): void {
        if ( isset( $row[ $field ] ) && ( ! is_numeric( $row[ $field ] ) || (float) $row[ $field ] < 0 ) ) {
            $this->err( $sheet, $line, "Feld „{$field}“ muss eine Zahl ≥ 0 sein (Wert: „{$row[$field]}“)." );
        }
    }

    private function require_in( array $row, string $field, array $allowed, string $sheet, int $line ): void {
        if ( isset( $row[ $field ] ) && ! in_array( trim( $row[ $field ] ), $allowed, true ) ) {
            $this->err( $sheet, $line, "Feld „{$field}“ hat unzulässigen Wert „{$row[$field]}“. Erlaubt: " . implode( ', ', $allowed ) . '.' );
        }
    }

    // ---- Sheet validators ----

    private function validate_animals( array $rows ): void {
        $seen = [];
        foreach ( $rows as $i => $row ) {
            $line = $i + 2;
            $this->require_fields( $row, [ 'animal_uid', 'animal_label_de', 'animal_slug', 'animal_group', 'has_subgroups', 'has_sex_options', 'is_active', 'sort_order' ], 'animals', $line );
            $this->require_bool( $row, 'has_subgroups',   'animals', $line );
            $this->require_bool( $row, 'has_sex_options', 'animals', $line );
            $this->require_bool( $row, 'is_active',       'animals', $line );

            if ( ! empty( $row['animal_uid'] ) ) {
                $uid = trim( $row['animal_uid'] );
                if ( isset( $seen[ $uid ] ) ) {
                    $this->err( 'animals', $line, "Doppelte animal_uid „{$uid}“." );
                }
                $seen[ $uid ]         = true;
                $this->animal_uids[]  = $uid;
            }
        }
    }

    private function validate_subgroups( array $rows ): void {
        $seen = [];
        foreach ( $rows as $i => $row ) {
            $line = $i + 2;
            $this->require_fields( $row, [ 'subgroup_uid', 'animal_uid', 'subgroup_label_de', 'subgroup_slug', 'got_scope_terms', 'has_direct_got_hits', 'is_active', 'sort_order' ], 'animal_subgroups', $line );
            $this->require_bool( $row, 'has_direct_got_hits', 'animal_subgroups', $line );
            $this->require_bool( $row, 'is_active',           'animal_subgroups', $line );

            if ( ! empty( $row['subgroup_uid'] ) ) {
                $uid = trim( $row['subgroup_uid'] );
                if ( isset( $seen[ $uid ] ) ) {
                    $this->err( 'animal_subgroups', $line, "Doppelte subgroup_uid „{$uid}“." );
                }
                $seen[ $uid ]          = true;
                $this->subgroup_uids[] = $uid;
            }
            if ( ! empty( $row['animal_uid'] ) && ! in_array( trim( $row['animal_uid'] ), $this->animal_uids, true ) ) {
                $this->err( 'animal_subgroups', $line, "animal_uid „{$row['animal_uid']}\" existiert nicht im animals-Sheet." );
            }
        }
    }

    private function validate_got_services( array $rows ): void {
        $seen = [];
        foreach ( $rows as $i => $row ) {
            $line = $i + 2;
            $this->require_fields( $row, [ 'service_uid', 'got_number', 'got_part', 'service_original_de', 'service_label_de', 'fee_1x', 'animal_scope_raw', 'animal_uids', 'subgroup_uids', 'sex_scope', 'is_general', 'is_active' ], 'got_services', $line );
            $this->require_numeric_positive( $row, 'fee_1x', 'got_services', $line );
            $this->require_bool( $row, 'is_general', 'got_services', $line );
            $this->require_bool( $row, 'is_active',  'got_services', $line );
            $this->require_in( $row, 'got_part',  self::ALLOWED_GOT_PARTS,  'got_services', $line );
            $this->require_in( $row, 'sex_scope', self::ALLOWED_SEX_SCOPES, 'got_services', $line );

            if ( ! empty( $row['service_uid'] ) ) {
                $uid = trim( $row['service_uid'] );
                if ( isset( $seen[ $uid ] ) ) {
                    $this->err( 'got_services', $line, "Doppelte service_uid „{$uid}“." );
                }
                $seen[ $uid ]         = true;
                $this->service_uids[] = $uid;
            }
        }
    }

    private function validate_fee_rules( array $rows ): void {
        $seen = [];
        foreach ( $rows as $i => $row ) {
            $line = $i + 2;
            $this->require_fields( $row, [ 'rule_uid', 'rule_label_de', 'rule_slug', 'factor_min', 'factor_max', 'fixed_fee', 'is_emergency', 'is_active', 'sort_order', 'legal_basis' ], 'fee_rules', $line );
            $this->require_bool( $row, 'is_emergency', 'fee_rules', $line );
            $this->require_bool( $row, 'is_active',    'fee_rules', $line );
            $this->require_numeric_positive( $row, 'factor_min', 'fee_rules', $line );
            $this->require_numeric_positive( $row, 'factor_max', 'fee_rules', $line );
            $this->require_numeric_positive( $row, 'fixed_fee',  'fee_rules', $line );

            if ( ! empty( $row['rule_uid'] ) ) {
                $uid = trim( $row['rule_uid'] );
                if ( isset( $seen[ $uid ] ) ) {
                    $this->err( 'fee_rules', $line, "Doppelte rule_uid „{$uid}“." );
                }
                $seen[ $uid ] = true;
            }
        }
    }

    private function validate_treatments( array $rows ): void {
        $seen      = [];
        $valid_sub = array_merge( $this->subgroup_uids, [ self::PLACEHOLDER_NO_SUBGROUP ] );
        foreach ( $rows as $i => $row ) {
            $line = $i + 2;
            $this->require_fields( $row, [ 'treatment_uid', 'animal_uid', 'subgroup_uid', 'treatment_label_de', 'treatment_slug', 'requires_search', 'requires_sex', 'is_active', 'sort_order' ], 'treatments', $line );
            $this->require_bool( $row, 'requires_search', 'treatments', $line );
            $this->require_bool( $row, 'requires_sex',    'treatments', $line );
            $this->require_bool( $row, 'is_active',       'treatments', $line );

            if ( ! empty( $row['animal_uid'] ) && ! in_array( trim( $row['animal_uid'] ), $this->animal_uids, true ) ) {
                $this->err( 'treatments', $line, "animal_uid „{$row['animal_uid']}\" existiert nicht." );
            }
            if ( ! empty( $row['subgroup_uid'] ) && ! in_array( trim( $row['subgroup_uid'] ), $valid_sub, true ) ) {
                $this->err( 'treatments', $line, "subgroup_uid „{$row['subgroup_uid']}\" existiert nicht (auch no_subgroup prüfen)." );
            }
            if ( ! empty( $row['treatment_uid'] ) ) {
                $uid = trim( $row['treatment_uid'] );
                if ( isset( $seen[ $uid ] ) ) {
                    $this->err( 'treatments', $line, "Doppelte treatment_uid „{$uid}“." );
                }
                $seen[ $uid ]            = true;
                $this->treatment_uids[]  = $uid;
            }
        }
    }

    private function validate_treatment_services( array $rows ): void {
        $seen      = [];
        $valid_srv = array_merge( $this->service_uids, [ self::PLACEHOLDER_NO_SERVICE ] );
        foreach ( $rows as $i => $row ) {
            $line = $i + 2;
            $this->require_fields( $row, [ 'map_uid', 'treatment_uid', 'service_uid', 'item_type', 'role', 'is_default', 'is_required', 'condition_key', 'sort_order', 'user_note_de' ], 'treatment_services', $line );
            $this->require_bool( $row, 'is_default',  'treatment_services', $line );
            $this->require_bool( $row, 'is_required', 'treatment_services', $line );
            $this->require_in( $row, 'item_type', self::ALLOWED_ITEM_TYPES, 'treatment_services', $line );

            if ( ! empty( $row['item_type'] ) && $row['item_type'] === 'note'
                && ! empty( $row['service_uid'] ) && trim( $row['service_uid'] ) !== self::PLACEHOLDER_NO_SERVICE ) {
                $this->warn( 'treatment_services', $line, "item_type ist „note“, service_uid sollte no_service sein." );
            }
            if ( ! empty( $row['treatment_uid'] ) && ! in_array( trim( $row['treatment_uid'] ), $this->treatment_uids, true ) ) {
                $this->err( 'treatment_services', $line, "treatment_uid „{$row['treatment_uid']}\" existiert nicht." );
            }
            if ( ! empty( $row['service_uid'] ) && ! in_array( trim( $row['service_uid'] ), $valid_srv, true ) ) {
                $this->err( 'treatment_services', $line, "service_uid „{$row['service_uid']}\" existiert nicht." );
            }
            if ( ! empty( $row['map_uid'] ) ) {
                $uid = trim( $row['map_uid'] );
                if ( isset( $seen[ $uid ] ) ) {
                    $this->err( 'treatment_services', $line, "Doppelte map_uid „{$uid}“." );
                }
                $seen[ $uid ] = true;
            }
        }
    }

    private function validate_search_terms( array $rows ): void {
        $seen = [];
        foreach ( $rows as $i => $row ) {
            $line = $i + 2;
            $this->require_fields( $row, [ 'search_uid', 'term_de', 'term_normalized', 'term_type', 'animal_uid', 'subgroup_uid', 'treatment_uid', 'service_uid', 'priority', 'is_active' ], 'search_terms', $line );
            $this->require_bool( $row, 'is_active', 'search_terms', $line );
            $this->require_in( $row, 'term_type', self::ALLOWED_TERM_TYPES, 'search_terms', $line );

            if ( ! empty( $row['search_uid'] ) ) {
                $uid = trim( $row['search_uid'] );
                if ( isset( $seen[ $uid ] ) ) {
                    $this->err( 'search_terms', $line, "Doppelte search_uid „{$uid}“." );
                }
                $seen[ $uid ] = true;
            }
        }
    }
}
