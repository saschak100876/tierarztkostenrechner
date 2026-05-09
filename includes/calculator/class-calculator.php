<?php
defined( 'ABSPATH' ) || exit;

class TKR_Calculator {

    private TKR_Treatment_Services_Repository $ts_repo;
    private TKR_Fee_Rules_Repository $rule_repo;
    private TKR_Treatments_Repository $treatment_repo;
    private TKR_Fee_Rule_Engine $engine;

    public function __construct() {
        $this->ts_repo        = new TKR_Treatment_Services_Repository();
        $this->rule_repo      = new TKR_Fee_Rules_Repository();
        $this->treatment_repo = new TKR_Treatments_Repository();
        $this->engine         = new TKR_Fee_Rule_Engine();
    }

    /**
     * Main entry point for the calculate REST endpoint.
     *
     * @param string $animal_uid
     * @param string $treatment_uid
     * @param string $rule_uid
     * @param string $sex  'male'|'female'|'any'
     * @return array|WP_Error
     */
    public function calculate( string $animal_uid, string $treatment_uid, string $rule_uid, string $sex = 'any' ) {
        $rule = $this->rule_repo->get_by_uid( $rule_uid );
        if ( ! $rule ) {
            return new WP_Error( 'tkr_invalid_rule', __( 'Ungueltige Behandlungssituation.', TKR_TEXT_DOMAIN ), [ 'status' => 400 ] );
        }

        $treatment = $this->treatment_repo->get_by_uid( $treatment_uid );
        if ( ! $treatment ) {
            return new WP_Error( 'tkr_invalid_treatment', __( 'Behandlung nicht gefunden.', TKR_TEXT_DOMAIN ), [ 'status' => 404 ] );
        }

        $service_rows = $this->ts_repo->get_by_treatment( $treatment_uid );

        $result = $this->engine->calculate( $rule, $service_rows, $sex );

        $notices = $this->build_notices( $rule );

        return [
            'input' => [
                'animal_uid'    => $animal_uid,
                'treatment_uid' => $treatment_uid,
                'rule_uid'      => $rule_uid,
                'sex'           => $sex,
            ],
            'range'   => $result,
            'notices' => $notices,
        ];
    }

    private function build_notices( array $rule ): array {
        $notices = [
            __( 'Der Rechner zeigt eine unverbindliche Orientierung auf Basis der GOT.', TKR_TEXT_DOMAIN ),
            __( 'Medikamente, Verbrauchsmaterial oder Laborleistungen koennen zusaetzlich anfallen.', TKR_TEXT_DOMAIN ),
            __( 'Wegegeld und Fahrtkosten sind in dieser Berechnung nicht enthalten.', TKR_TEXT_DOMAIN ),
        ];

        if ( (int) $rule['is_emergency'] === 1 ) {
            $notices[] = __( 'Die Notdienstgebuehr faellt nur einmal pro Angelegenheit an.', TKR_TEXT_DOMAIN );
        }

        if ( in_array( $rule['rule_uid'], [ 'rule_evening_night', 'rule_weekend_holiday' ], true ) ) {
            $notices[] = __( 'Besondere Zeiten ermoeglichen hoehere Faktoren, bedeuten aber nicht automatisch Notdienst.', TKR_TEXT_DOMAIN );
        }

        return $notices;
    }
}
