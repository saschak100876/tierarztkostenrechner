<?php
defined( 'ABSPATH' ) || exit;

class TKR_Calculator {

    private TKR_Treatment_Services_Repository $ts_repo;
    private TKR_Fee_Rules_Repository          $rule_repo;
    private TKR_Treatments_Repository         $treatment_repo;
    private TKR_Fee_Rule_Engine               $engine;

    public function __construct() {
        $this->ts_repo        = new TKR_Treatment_Services_Repository();
        $this->rule_repo      = new TKR_Fee_Rules_Repository();
        $this->treatment_repo = new TKR_Treatments_Repository();
        $this->engine         = new TKR_Fee_Rule_Engine();
    }

    /**
     * @return array|WP_Error
     */
    public function calculate( string $animal_uid, string $treatment_uid, string $rule_uid, string $sex = 'any' ) {
        $rule = $this->rule_repo->get_by_uid( $rule_uid );
        if ( ! $rule ) {
            return new WP_Error( 'tkr_invalid_rule', __( 'Unbekannte Behandlungssituation.', TKR_TEXT_DOMAIN ), [ 'status' => 400 ] );
        }

        $treatment = $this->treatment_repo->get_by_uid( $treatment_uid );
        if ( ! $treatment ) {
            return new WP_Error( 'tkr_invalid_treatment', __( 'Behandlung nicht gefunden.', TKR_TEXT_DOMAIN ), [ 'status' => 404 ] );
        }

        $service_rows = $this->ts_repo->get_by_treatment( $treatment_uid );
        $result       = $this->engine->calculate( $rule, $service_rows, $sex );

        return [
            'input' => [
                'animal_uid'    => sanitize_text_field( $animal_uid ),
                'treatment_uid' => sanitize_text_field( $treatment_uid ),
                'rule_uid'      => sanitize_text_field( $rule_uid ),
                'sex'           => sanitize_text_field( $sex ),
            ],
            'range'   => $result,
            'notices' => $this->build_notices( $rule ),
        ];
    }

    private function build_notices( array $rule ): array {
        $notices = [
            __( 'Diese Berechnung ist eine unverbindliche Orientierung nach GOT. Die tatsächlichen Kosten können je nach Einzelfall abweichen.', TKR_TEXT_DOMAIN ),
            __( 'Medikamente, Verbrauchsmaterial und Laborleistungen können zusätzlich anfallen.', TKR_TEXT_DOMAIN ),
            __( 'Wegegeld und Fahrtkosten sind in dieser Berechnung nicht enthalten.', TKR_TEXT_DOMAIN ),
        ];

        if ( (int) $rule['is_emergency'] === 1 ) {
            $notices[] = __( 'Die Notdienstgebühr fällt nur einmal pro Angelegenheit an, nicht pro Einzelleistung.', TKR_TEXT_DOMAIN );
        }
        if ( in_array( $rule['rule_uid'], [ 'rule_evening_night', 'rule_weekend_holiday' ], true ) ) {
            $notices[] = __( 'Besondere Zeiten ermöglichen höhere GOT-Faktoren, bedeuten aber nicht automatisch Notdienst.', TKR_TEXT_DOMAIN );
        }

        return $notices;
    }
}
