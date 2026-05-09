<?php
defined( 'ABSPATH' ) || exit;

class TKR_Migrations {

    public static function run(): void {
        $installed = get_option( TKR_Schema::DB_VERSION_OPTION, '0.0.0' );
        if ( version_compare( $installed, TKR_Schema::DB_VERSION, '<' ) ) {
            TKR_Schema::create_tables();
        }
    }

    /**
     * Seeds the five standard fee rules, but only when the table is completely empty.
     * This protects imported data from being overwritten on re-activation.
     */
    public static function seed_fee_rules_if_empty(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'tkr_fee_rules';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
        if ( $count > 0 ) {
            return;
        }

        $rules = [
            [
                'rule_uid'      => 'rule_normal',
                'rule_label_de' => 'Normale Sprechstunde',
                'rule_slug'     => 'normal',
                'factor_min'    => 1.00,
                'factor_max'    => 3.00,
                'fixed_fee'     => 0.00,
                'is_emergency'  => 0,
                'is_active'     => 1,
                'sort_order'    => 10,
                'legal_basis'   => '§ 4 GOT',
                'notes'         => 'Normaler GOT-Rahmen',
            ],
            [
                'rule_uid'      => 'rule_evening_night',
                'rule_label_de' => 'Abend / Nacht (18:00–8:00 Uhr)',
                'rule_slug'     => 'abendnacht',
                'factor_min'    => 1.00,
                'factor_max'    => 3.00,
                'fixed_fee'     => 0.00,
                'is_emergency'  => 0,
                'is_active'     => 1,
                'sort_order'    => 20,
                'legal_basis'   => '§ 4 GOT',
                'notes'         => 'Besondere Zeit als Hinweis; nicht automatisch Notdienst',
            ],
            [
                'rule_uid'      => 'rule_weekend_holiday',
                'rule_label_de' => 'Wochenende / Feiertag',
                'rule_slug'     => 'wochenende-feiertag',
                'factor_min'    => 1.00,
                'factor_max'    => 3.00,
                'fixed_fee'     => 0.00,
                'is_emergency'  => 0,
                'is_active'     => 1,
                'sort_order'    => 30,
                'legal_basis'   => '§ 4 GOT',
                'notes'         => 'Besondere Zeit als Hinweis; nicht automatisch Notdienst',
            ],
            [
                'rule_uid'      => 'rule_emergency',
                'rule_label_de' => 'Tierärztlicher Notdienst',
                'rule_slug'     => 'notdienst',
                'factor_min'    => 2.00,
                'factor_max'    => 4.00,
                'fixed_fee'     => 50.00,
                'is_emergency'  => 1,
                'is_active'     => 1,
                'sort_order'    => 40,
                'legal_basis'   => '§ 4 GOT',
                'notes'         => 'Notdienstgebühr nur einmal pro Angelegenheit',
            ],
            [
                'rule_uid'      => 'rule_unknown',
                'rule_label_de' => 'Ich weiß es noch nicht / erst informieren',
                'rule_slug'     => 'unbekannt',
                'factor_min'    => 1.00,
                'factor_max'    => 4.00,
                'fixed_fee'     => 0.00,
                'is_emergency'  => 0,
                'is_active'     => 1,
                'sort_order'    => 50,
                'legal_basis'   => '§ 4 GOT',
                'notes'         => 'Orientierungsmodus: Vergleich Normal und Notdienst',
            ],
        ];

        foreach ( $rules as $rule ) {
            $wpdb->insert( $table, $rule );
        }
    }
}
